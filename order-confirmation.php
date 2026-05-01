<?php
require_once 'config/functions.php';
$page_title = 'Order Confirmation';

if (!isLoggedIn()) {
    redirect('login.php');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if (!$order_id) {
    redirect('orders.php');
}

$db = getDB();

// Get order details
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

// Get order items
$stmt = $db->prepare("
    SELECT oi.*, p.slug 
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <!-- Success Message -->
                <div class="text-center mb-5">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h2 class="fw-bold mb-2">Thank You for Your Order!</h2>
                    <p class="text-muted">Your order has been successfully placed and is being processed.</p>
                </div>
                
                <!-- Order Details Card -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-success text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Order Details</h5>
                            <span class="badge bg-white text-success"><?php echo ucfirst($order['status']); ?></span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-2">Order Information</h6>
                                <p class="mb-1"><strong>Order Number:</strong> #<?php echo $order['order_number']; ?></p>
                                <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F d, Y', strtotime($order['created_at'])); ?></p>
                                <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                                <p class="mb-0"><strong>Payment Status:</strong> 
                                    <span class="badge badge-success"><?php echo ucfirst($order['payment_status']); ?></span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold text-muted mb-2">Shipping Address</h6>
                                <p class="mb-1"><?php echo sanitize($order['shipping_name']); ?></p>
                                <p class="mb-1"><?php echo nl2br(sanitize($order['shipping_address'])); ?></p>
                                <p class="mb-0"><?php echo sanitize($order['shipping_city'] . ', ' . $order['shipping_state'] . ' ' . $order['shipping_zip']); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <!-- Order Items -->
                        <h6 class="fw-bold mb-3">Order Items</h6>
                        <div class="table-responsive">
                            <table class="table">
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
                                                <a href="<?php echo $item['slug'] ? 'product.php?slug=' . $item['slug'] : '#'; ?>" class="text-decoration-none">
                                                    <?php echo sanitize($item['product_name']); ?>
                                                </a>
                                            </td>
                                            <td class="text-end"><?php echo formatPrice($item['product_price']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end fw-bold"><?php echo formatPrice($item['total']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end">Subtotal:</td>
                                        <td class="text-end"><?php echo formatPrice($order['subtotal']); ?></td>
                                    </tr>
                                    <?php if ($order['discount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end text-success">Discount:</td>
                                            <td class="text-end text-success">-<?php echo formatPrice($order['discount']); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end">Tax:</td>
                                        <td class="text-end"><?php echo formatPrice($order['tax']); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end">Shipping:</td>
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
                
                <!-- Actions -->
                <div class="d-flex justify-content-center gap-3">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-box me-2"></i>View All Orders
                    </a>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="fas fa-home me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
