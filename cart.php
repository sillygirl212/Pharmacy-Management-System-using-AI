<?php
require_once 'config/functions.php';
$page_title = 'Shopping Cart';

$db = getDB();

// Update cart quantities
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    if (isLoggedIn()) {
        foreach ($_POST['quantities'] as $product_id => $quantity) {
            $quantity = intval($quantity);
            if ($quantity > 0) {
                $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$quantity, getCurrentUserId(), $product_id]);
            } else {
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([getCurrentUserId(), $product_id]);
            }
        }
    } else {
        foreach ($_POST['quantities'] as $product_id => $quantity) {
            $quantity = intval($quantity);
            if ($quantity > 0) {
                $_SESSION['cart'][$product_id]['quantity'] = $quantity;
            } else {
                unset($_SESSION['cart'][$product_id]);
            }
        }
    }
    setFlashMessage('success', 'Cart updated successfully!');
    redirect('cart.php');
}

// Remove item from cart
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $product_id = intval($_GET['remove']);
    
    if (isLoggedIn()) {
        $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([getCurrentUserId(), $product_id]);
    } else {
        unset($_SESSION['cart'][$product_id]);
    }
    
    setFlashMessage('success', 'Item removed from cart!');
    redirect('cart.php');
}

// Apply coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
    $coupon_code = sanitize($_POST['coupon_code'] ?? '');
    $cart_total = getCartTotal();
    
    $coupon_result = validateCoupon($coupon_code, $cart_total);
    
    if ($coupon_result['valid']) {
        $_SESSION['coupon'] = [
            'code' => $coupon_code,
            'discount' => $coupon_result['discount']
        ];
        setFlashMessage('success', "Coupon applied! You saved " . formatPrice($coupon_result['discount']));
    } else {
        unset($_SESSION['coupon']);
        setFlashMessage('error', $coupon_result['message']);
    }
    redirect('cart.php');
}

// Remove coupon
if (isset($_GET['remove_coupon'])) {
    unset($_SESSION['coupon']);
    setFlashMessage('info', 'Coupon removed.');
    redirect('cart.php');
}

// Get cart items
$cart_items = [];
$cart_total = 0;

if (isLoggedIn()) {
    $stmt = $db->prepare("
        SELECT c.*, p.id as product_id, p.name, p.slug, p.price, p.sale_price, p.stock_quantity, p.prescription_required
        FROM cart c 
        JOIN products p ON c.product_id = p.id 
        WHERE c.user_id = ?
    ");
    $stmt->execute([getCurrentUserId()]);
    $cart_items = $stmt->fetchAll();
} else {
    if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $product_id => $item) {
            $stmt = $db->prepare("
                SELECT id as product_id, name, slug, price, sale_price, stock_quantity, prescription_required 
                FROM products WHERE id = ?
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                $product['quantity'] = $item['quantity'];
                $cart_items[] = $product;
            }
        }
    }
}

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

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">Shopping Cart</h1>
        <p class="mb-0">Review your items and proceed to checkout</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (empty($cart_items)): ?>
            <div class="text-center py-5">
                <i class="fas fa-shopping-cart fa-4x text-muted mb-4"></i>
                <h3 class="fw-bold">Your cart is empty</h3>
                <p class="text-muted mb-4">Looks like you haven't added any products to your cart yet.</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <div class="row">
                    <!-- Cart Items -->
                    <div class="col-lg-8">
                        <div class="card shadow-sm mb-4">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th class="ps-4">Product</th>
                                                <th>Price</th>
                                                <th style="width: 150px;">Quantity</th>
                                                <th>Total</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cart_items as $item): 
                                                $price = $item['sale_price'] ?? $item['price'];
                                                $item_total = $price * $item['quantity'];
                                            ?>
                                                <tr>
                                                    <td class="ps-4">
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?php echo getProductImage($item['product_id']); ?>" 
                                                                 alt="<?php echo sanitize($item['name']); ?>" 
                                                                 class="rounded me-3" 
                                                                 style="width: 60px; height: 60px; object-fit: cover;">
                                                            <div>
                                                                <h6 class="mb-1">
                                                                    <a href="product.php?slug=<?php echo $item['slug']; ?>" class="text-dark text-decoration-none">
                                                                        <?php echo sanitize($item['name']); ?>
                                                                    </a>
                                                                </h6>
                                                                <?php if ($item['prescription_required']): ?>
                                                                    <span class="badge badge-danger small">Prescription Required</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php if ($item['sale_price']): ?>
                                                            <span class="text-primary fw-bold"><?php echo formatPrice($item['sale_price']); ?></span>
                                                            <br><small class="text-muted text-decoration-line-through"><?php echo formatPrice($item['price']); ?></small>
                                                        <?php else: ?>
                                                            <span class="fw-bold"><?php echo formatPrice($item['price']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div class="input-group input-group-sm">
                                                            <button type="button" class="btn btn-outline-secondary" onclick="this.parentNode.querySelector('input').stepDown()">
                                                                <i class="fas fa-minus"></i>
                                                            </button>
                                                            <input type="number" name="quantities[<?php echo $item['product_id']; ?>]" 
                                                                   class="form-control text-center" 
                                                                   value="<?php echo $item['quantity']; ?>" 
                                                                   min="0" 
                                                                   max="<?php echo $item['stock_quantity']; ?>">
                                                            <button type="button" class="btn btn-outline-secondary" onclick="this.parentNode.querySelector('input').stepUp()">
                                                                <i class="fas fa-plus"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="fw-bold"><?php echo formatPrice($item_total); ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="cart.php?remove=<?php echo $item['product_id']; ?>" 
                                                           class="btn btn-link text-danger p-0" 
                                                           onclick="return confirm('Remove this item from cart?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Cart Button -->
                        <div class="d-flex justify-content-between mb-4">
                            <a href="products.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                            <button type="submit" name="update_cart" class="btn btn-secondary">
                                <i class="fas fa-sync me-2"></i>Update Cart
                            </button>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4">Order Summary</h5>
                                
                                <!-- Coupon Code -->
                                <div class="mb-4">
                                    <label class="form-label">Coupon Code</label>
                                    <div class="input-group">
                                        <input type="text" name="coupon_code" class="form-control" placeholder="Enter coupon code"
                                               value="<?php echo $_SESSION['coupon']['code'] ?? ''; ?>">
                                        <button type="submit" name="apply_coupon" class="btn btn-outline-primary">Apply</button>
                                    </div>
                                    <?php if (isset($_SESSION['coupon'])): ?>
                                        <div class="mt-2 alert alert-success py-2">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Coupon applied: <?php echo $_SESSION['coupon']['code']; ?>
                                            <a href="cart.php?remove_coupon=1" class="float-end text-danger"><i class="fas fa-times"></i></a>
                                        </div>
                                    <?php endif; ?>
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
                                
                                <a href="checkout.php" class="btn btn-primary btn-lg w-100">
                                    <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                                </a>
                                
                                <div class="text-center mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-lock me-1"></i>Secure checkout
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
