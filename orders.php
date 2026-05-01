<?php
require_once 'config/functions.php';
$page_title = 'My Orders';

if (!isLoggedIn()) {
    setFlashMessage('info', 'Please login to view your orders.');
    redirect('login.php');
}

$db = getDB();
$user_id = getCurrentUserId();

// Get all orders for user
$stmt = $db->prepare("
    SELECT * FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Page Banner -->
<section class="page-banner" style="padding: 60px 0;">
    <div class="container">
        <h1 class="fw-bold mb-2">My Orders</h1>
        <p class="mb-0">View and track your order history</p>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-box-open fa-4x text-muted mb-4"></i>
                <h3 class="fw-bold">No Orders Yet</h3>
                <p class="text-muted mb-4">You haven't placed any orders yet. Start shopping now!</p>
                <a href="products.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag me-2"></i>Browse Products
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): 
                    // Get status color
                    $status_colors = [
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        'refunded' => 'secondary'
                    ];
                    $status_color = $status_colors[$order['status']] ?? 'secondary';
                    
                    // Get order items count
                    $stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE order_id = ?");
                    $stmt->execute([$order['id']]);
                    $item_count = $stmt->fetchColumn();
                ?>
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white py-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0 fw-bold">#<?php echo $order['order_number']; ?></h6>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($order['created_at'])); ?></small>
                                    </div>
                                    <span class="badge badge-<?php echo $status_color; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Items</small>
                                        <p class="mb-0 fw-bold"><?php echo $item_count; ?> items</p>
                                    </div>
                                    <div class="col-6 text-end">
                                        <small class="text-muted">Total</small>
                                        <p class="mb-0 fw-bold text-primary"><?php echo formatPrice($order['total']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo $order['payment_status'] === 'paid' ? 'check' : 'clock'; ?> me-1"></i>
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View Details <i class="fas fa-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
