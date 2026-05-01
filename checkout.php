<?php
require_once 'config/functions.php';
$page_title = 'Checkout';

// Redirect if cart is empty
$cart_count = getCartCount();
if ($cart_count == 0) {
    setFlashMessage('error', 'Your cart is empty.');
    redirect('cart.php');
}

// User must be logged in to checkout
if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = 'checkout.php';
    setFlashMessage('info', 'Please login to complete your purchase.');
    redirect('login.php');
}

$db = getDB();
$user_id = getCurrentUserId();

// Get user details
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get cart items
$stmt = $db->prepare("
    SELECT c.*, p.id as product_id, p.name, p.slug, p.price, p.sale_price, p.prescription_required
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $price = $item['sale_price'] ?? $item['price'];
    $subtotal += $price * $item['quantity'];
}

$discount = $_SESSION['coupon']['discount'] ?? 0;
$tax = calculateTax($subtotal - $discount);
$shipping = getShippingCost();
$total = $subtotal - $discount + $tax + $shipping;

$error = '';

// Get enabled payment methods from settings
$enabled_payments = [
    'bkash' => getSetting('enable_bkash', '0') === '1',
    'nagad' => getSetting('enable_nagad', '0') === '1',
    'cod' => getSetting('enable_cod', '1') === '1'
];

// Default payment method
$default_payment = '';
if ($enabled_payments['bkash']) $default_payment = 'bkash';
elseif ($enabled_payments['nagad']) $default_payment = 'nagad';
elseif ($enabled_payments['cod']) $default_payment = 'cod';

// Process checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitize($_POST['payment_method'] ?? $default_payment);
    
    // Validate payment method is enabled
    if (!isset($enabled_payments[$payment_method]) || !$enabled_payments[$payment_method]) {
        $error = 'Selected payment method is not available.';
    }
    
    // Get billing details
    $billing_name = sanitize($_POST['billing_name'] ?? $user['name']);
    $billing_email = sanitize($_POST['billing_email'] ?? $user['email']);
    $billing_phone = sanitize($_POST['billing_phone'] ?? $user['phone']);
    $billing_address = sanitize($_POST['billing_address'] ?? '');
    $billing_city = sanitize($_POST['billing_city'] ?? '');
    $billing_state = sanitize($_POST['billing_state'] ?? '');
    $billing_zip = sanitize($_POST['billing_zip'] ?? '');
    $billing_country = sanitize($_POST['billing_country'] ?? '');
    
    // Shipping same as billing
    $shipping_name = $billing_name;
    $shipping_address = $billing_address;
    $shipping_city = $billing_city;
    $shipping_state = $billing_state;
    $shipping_zip = $billing_zip;
    $shipping_country = $billing_country;
    
    // Validation
    if (empty($billing_name) || empty($billing_email) || empty($billing_address) || empty($billing_city)) {
        $error = 'Please fill in all required billing information.';
    } else {
        // Create order
        $order_number = generateOrderNumber();
        $coupon_code = $_SESSION['coupon']['code'] ?? null;
        
        try {
            $db->beginTransaction();
            
            // Insert order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, status, payment_status, payment_method,
                    subtotal, discount, coupon_code, tax, shipping, total, currency,
                    billing_name, billing_email, billing_phone, billing_address,
                    billing_city, billing_state, billing_zip, billing_country,
                    shipping_name, shipping_address, shipping_city, shipping_state,
                    shipping_zip, shipping_country
                ) VALUES (?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?, 'USD',
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $order_number, $user_id, $payment_method,
                $subtotal, $discount, $coupon_code, $tax, $shipping, $total,
                $billing_name, $billing_email, $billing_phone, $billing_address,
                $billing_city, $billing_state, $billing_zip, $billing_country,
                $shipping_name, $shipping_address, $shipping_city, $shipping_state,
                $shipping_zip, $shipping_country
            ]);
            
            $order_id = $db->lastInsertId();
            
            // Insert order items
            $stmt = $db->prepare("
                INSERT INTO order_items (order_id, product_id, product_name, product_price, quantity, total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($cart_items as $item) {
                $price = $item['sale_price'] ?? $item['price'];
                $item_total = $price * $item['quantity'];
                
                $stmt->execute([
                    $order_id, $item['product_id'], $item['name'],
                    $price, $item['quantity'], $item_total
                ]);
                
                // Update stock
                $db->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?")
                   ->execute([$item['quantity'], $item['product_id']]);
            }
            
            // Clear cart
            $db->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);
            
            // Update coupon usage
            if ($coupon_code) {
                $db->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE code = ?")
                   ->execute([$coupon_code]);
            }
            
            $db->commit();
            
            // Clear coupon
            unset($_SESSION['coupon']);
            
            logActivity('Order Placed', "Order #{$order_number} placed by user {$user_id}");
            
            // For COD, mark as paid immediately (payment on delivery)
            if ($payment_method === 'cod') {
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET payment_status = 'pending', status = 'processing', notes = 'Payment will be collected on delivery'
                    WHERE id = ?
                ");
                $stmt->execute([$order_id]);
                
                logActivity('Order Placed (COD)', "COD Order #{$order_number} placed");
                setFlashMessage('success', 'Order placed successfully! You will pay on delivery.');
                redirect('order-confirmation.php?order_id=' . $order_id);
            } else {
                // For bKash/Nagad, go to payment page
                $_SESSION['pending_order'] = [
                    'id' => $order_id,
                    'number' => $order_number,
                    'total' => $total,
                    'method' => $payment_method
                ];
                redirect('payment.php?order_id=' . $order_id);
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Order could not be placed. Please try again.';
            error_log($e->getMessage());
        }
    }
}

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">Checkout</h1>
        <p class="mb-0">Complete your order by providing billing details</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <!-- Billing Information -->
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0"><i class="fas fa-map-marker-alt me-2 text-primary"></i>Billing Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="billing_name" class="form-control" value="<?php echo sanitize($user['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="billing_email" class="form-control" value="<?php echo sanitize($user['email']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" name="billing_phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address *</label>
                                    <textarea name="billing_address" class="form-control" rows="2" required><?php echo sanitize($user['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City *</label>
                                    <input type="text" name="billing_city" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" name="billing_state" class="form-control">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">ZIP/Postal Code</label>
                                    <input type="text" name="billing_zip" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <select name="billing_country" class="form-select">
                                        <option value="USA">United States</option>
                                        <option value="UK">United Kingdom</option>
                                        <option value="CA">Canada</option>
                                        <option value="AU">Australia</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0"><i class="fas fa-credit-card me-2 text-primary"></i>Payment Method</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <?php if ($enabled_payments['bkash']): ?>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 h-100 border-pink" style="border: 2px solid #e2136e;">
                                        <input class="form-check-input" type="radio" name="payment_method" id="bkash" value="bkash" <?php echo $default_payment === 'bkash' ? 'checked' : ''; ?>>
                                        <label class="form-check-label d-flex align-items-center" for="bkash">
                                            <span class="me-2" style="background: #e2136e; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold; font-size: 12px;">bKash</span>
                                            <span>bKash Payment</span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($enabled_payments['nagad']): ?>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 h-100" style="border: 2px solid #ec1c24;">
                                        <input class="form-check-input" type="radio" name="payment_method" id="nagad" value="nagad" <?php echo $default_payment === 'nagad' ? 'checked' : ''; ?>>
                                        <label class="form-check-label d-flex align-items-center" for="nagad">
                                            <span class="me-2" style="background: #ec1c24; color: white; padding: 5px 10px; border-radius: 5px; font-weight: bold; font-size: 12px;">Nagad</span>
                                            <span>Nagad Payment</span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($enabled_payments['cod']): ?>
                                <div class="col-md-4">
                                    <div class="form-check card p-3 h-100" style="border: 2px solid #00b74a;">
                                        <input class="form-check-input" type="radio" name="payment_method" id="cod" value="cod" <?php echo $default_payment === 'cod' ? 'checked' : ''; ?>>
                                        <label class="form-check-label d-flex align-items-center" for="cod">
                                            <i class="fas fa-money-bill-wave fa-2x me-2 text-success"></i>
                                            <span>Cash on Delivery</span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!$enabled_payments['bkash'] && !$enabled_payments['nagad'] && !$enabled_payments['cod']): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                No payment methods are currently enabled. Please contact support.
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                <?php if ($enabled_payments['bkash'] || $enabled_payments['nagad']): ?>
                                    You will be redirected to complete your payment after placing the order.
                                <?php endif; ?>
                                <?php if ($enabled_payments['cod']): ?>
                                    Pay with Cash on Delivery when your order arrives.
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card shadow-sm sticky-top" style="top: 100px;">
                        <div class="card-header bg-white py-3">
                            <h5 class="fw-bold mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body p-4">
                            <!-- Cart Items -->
                            <div class="mb-3">
                                <?php foreach ($cart_items as $item): 
                                    $price = $item['sale_price'] ?? $item['price'];
                                ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge badge-secondary me-2"><?php echo $item['quantity']; ?>x</span>
                                            <span class="small"><?php echo truncateText(sanitize($item['name']), 25); ?></span>
                                        </div>
                                        <span class="small"><?php echo formatPrice($price * $item['quantity']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <hr>
                            
                            <!-- Price Breakdown -->
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span><?php echo formatPrice($subtotal); ?></span>
                            </div>
                            
                            <?php if ($discount > 0): ?>
                                <div class="d-flex justify-content-between mb-2 text-success">
                                    <span>Discount</span>
                                    <span>-<?php echo formatPrice($discount); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Tax</span>
                                <span><?php echo formatPrice($tax); ?></span>
                            </div>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <span class="text-muted">Shipping</span>
                                <span><?php echo $shipping > 0 ? formatPrice($shipping) : 'Free'; ?></span>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between mb-4">
                                <span class="h5 fw-bold">Total</span>
                                <span class="h4 fw-bold text-primary"><?php echo formatPrice($total); ?></span>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-lock me-2"></i>Place Order
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>Secure SSL Encryption
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
