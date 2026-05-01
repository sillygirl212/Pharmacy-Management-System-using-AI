<?php
require_once 'config/functions.php';

if (!isLoggedIn()) {
    setFlashMessage('info', 'Please login to view order details.');
    redirect('login.php');
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    redirect('orders.php');
}

$db = getDB();

// Get order details - verify it belongs to current user
$stmt = $db->prepare("
    SELECT o.*, u.name as user_name, u.email as user_email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, getCurrentUserId()]);
$order = $stmt->fetch();

if (!$order) {
    setFlashMessage('error', 'Order not found.');
    redirect('orders.php');
}

$page_title = 'Order #' . $order['order_number'];

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.slug, p.prescription_required 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();

include 'includes/header.php';
?>

<section class="py-5">
    <div class="container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="orders.php">My Orders</a></li>
                <li class="breadcrumb-item active" aria-current="page">Order #<?php echo $order['order_number']; ?></li>
            </ol>
        </nav>
        
        <!-- Order Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Order #<?php echo $order['order_number']; ?></h2>
                <p class="text-muted mb-0">
                    Placed on <?php echo date('F d, Y', strtotime($order['created_at'])); ?>
                </p>
            </div>
            <div class="text-end">
                <?php
                $status_colors = [
                    'pending' => 'warning', 'processing' => 'info',
                    'shipped' => 'primary', 'delivered' => 'success',
                    'cancelled' => 'danger', 'refunded' => 'secondary'
                ];
                ?>
                <span class="badge badge-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?> mb-2 d-block">
                    <?php echo ucfirst($order['status']); ?>
                </span>
                <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                    Payment: <?php echo ucfirst($order['payment_status']); ?>
                </span>
            </div>
        </div>
        
        <div class="row g-4">
            <!-- Order Details -->
            <div class="col-lg-8">
                <!-- Items -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Order Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Product</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <?php if ($item['slug']): ?>
                                                                <a href="product.php?slug=<?php echo $item['slug']; ?>" class="text-decoration-none">
                                                                    <?php echo sanitize($item['product_name']); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <?php echo sanitize($item['product_name']); ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <?php if ($item['prescription_required']): ?>
                                                            <span class="badge badge-danger small">Prescription Required</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end"><?php echo formatPrice($item['product_price']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end fw-bold"><?php echo formatPrice($item['total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end"><?php echo formatPrice($order['subtotal']); ?></td>
                                    </tr>
                                    <?php if ($order['discount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end text-success"><strong>Discount:</strong></td>
                                            <td class="text-end text-success">-<?php echo formatPrice($order['discount']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Tax:</strong></td>
                                        <td class="text-end"><?php echo formatPrice($order['tax']); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                        <td class="text-end"><?php echo $order['shipping'] > 0 ? formatPrice($order['shipping']) : 'Free'; ?></td>
                                    </tr>
                                    <tr class="border-top">
                                        <td colspan="3" class="text-end h5 fw-bold">Total:</td>
                                        <td class="text-end h5 fw-bold text-primary"><?php echo formatPrice($order['total']); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Shipping & Billing -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="fw-bold mb-0">Shipping Address</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong><?php echo sanitize($order['shipping_name']); ?></strong></p>
                                <p class="mb-1"><?php echo nl2br(sanitize($order['shipping_address'])); ?></p>
                                <p class="mb-0"><?php echo sanitize($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white py-3">
                                <h5 class="fw-bold mb-0">Billing Address</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong><?php echo sanitize($order['billing_name']); ?></strong></p>
                                <p class="mb-1"><?php echo nl2br(sanitize($order['billing_address'])); ?></p>
                                <p class="mb-0"><?php echo sanitize($order['billing_city'] . ', ' . $order['billing_state'] . ' ' . $order['billing_zip']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="fw-bold mb-0">Order Information</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Order Number:</strong><br><?php echo $order['order_number']; ?></p>
                        <p class="mb-2"><strong>Order Date:</strong><br><?php echo date('F d, Y H:i', strtotime($order['created_at'])); ?></p>
                        <p class="mb-2">
                            <strong>Payment Method:</strong><br>
                            <?php 
                            $method_display = [
                                'bkash' => '<span style="color: #e2136e;"><strong>bKash</strong></span>',
                                'nagad' => '<span style="color: #ec1c24;"><strong>Nagad</strong></span>',
                                'cod' => '<i class="fas fa-money-bill-wave text-success me-1"></i><strong>Cash on Delivery</strong>'
                            ];
                            echo $method_display[$order['payment_method']] ?? ucfirst($order['payment_method']);
                            ?>
                        </p>
                        <p class="mb-2">
                            <strong>Payment Status:</strong><br>
                            <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : ($order['payment_status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        </p>
                        <?php if ($order['transaction_id']): ?>
                            <p class="mb-0"><strong>Transaction ID:</strong><br><?php echo $order['transaction_id']; ?></p>
                        <?php endif; ?>
                        <?php if ($order['notes']): ?>
                            <p class="mb-0 mt-2 text-muted small"><strong>Note:</strong> <?php echo sanitize($order['notes']); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($order['coupon_code']): ?>
                            <hr>
                            <p class="mb-0 text-success">
                                <i class="fas fa-ticket-alt me-2"></i>
                                Coupon Used: <strong><?php echo $order['coupon_code']; ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <a href="orders.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Orders
                        </a>
                        <a href="products.php" class="btn btn-primary w-100">
                            <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
