<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Orders';
$db = getDB();

// Update order status
if (isset($_POST['update_status']) && is_numeric($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $status = sanitize($_POST['status'] ?? '');
    $payment_status = sanitize($_POST['payment_status'] ?? '');
    
    $stmt = $db->prepare("UPDATE orders SET status = ?, payment_status = ? WHERE id = ?");
    $stmt->execute([$status, $payment_status, $order_id]);
    
    logActivity('Order Status Updated', "Order #{$order_id} status updated to {$status}");
    setFlashMessage('success', 'Order status updated!');
    redirect('orders.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$payment_status = isset($_GET['payment_status']) ? sanitize($_GET['payment_status']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;

// Build query
$where_clauses = [];
$params = [];

if ($search) {
    $where_clauses[] = "(o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status) {
    $where_clauses[] = "o.status = ?";
    $params[] = $status;
}

if ($payment_status) {
    $where_clauses[] = "o.payment_status = ?";
    $params[] = $payment_status;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total
$count_sql = "SELECT COUNT(*) FROM orders o JOIN users u ON o.user_id = u.id $where_sql";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_orders = $stmt->fetchColumn();

// Pagination
$pagination = paginate($total_orders, $per_page, $page);

// Get orders
$sql = "SELECT o.*, u.name as user_name, u.email as user_email 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        $where_sql 
        ORDER BY o.created_at DESC 
        LIMIT {$pagination['offset']}, {$pagination['items_per_page']}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Orders</h2>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?php echo $search; ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="payment_status" class="form-select">
                    <option value="">All Payment</option>
                    <option value="pending" <?php echo $payment_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $payment_status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="failed" <?php echo $payment_status === 'failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-5">
                <button type="submit" class="btn btn-primary me-2">Filter</button>
                <a href="orders.php" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $status_colors = [
                            'pending' => 'warning', 'processing' => 'info',
                            'shipped' => 'primary', 'delivered' => 'success',
                            'cancelled' => 'danger', 'refunded' => 'secondary'
                        ];
                    ?>
                        <tr>
                            <td><strong>#<?php echo $order['order_number']; ?></strong></td>
                            <td>
                                <div>
                                    <strong><?php echo sanitize($order['user_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo sanitize($order['user_email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo formatPrice($order['total']); ?></strong>
                                <?php if ($order['coupon_code']): ?>
                                    <br><small class="text-success">Coupon: <?php echo $order['coupon_code']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($order['payment_status']); ?>
                                </span>
                                <br><small class="text-muted">
                                    <?php 
                                    $method_icons = [
                                        'bkash' => '<span style="color: #e2136e;"><strong>bKash</strong></span>',
                                        'nagad' => '<span style="color: #ec1c24;"><strong>Nagad</strong></span>',
                                        'cod' => '<span style="color: #00b74a;">COD</span>'
                                    ];
                                    echo $method_icons[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                    ?>
                                </small>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-mdb-modal-init data-mdb-target="#orderModal<?php echo $order['id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Order Modal -->
                        <div class="modal fade" id="orderModal<?php echo $order['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Order #<?php echo $order['order_number']; ?></h5>
                                        <button type="button" class="btn-close" data-mdb-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Customer Information</h6>
                                                <p class="mb-1"><strong>Name:</strong> <?php echo sanitize($order['billing_name']); ?></p>
                                                <p class="mb-1"><strong>Email:</strong> <?php echo sanitize($order['billing_email']); ?></p>
                                                <p class="mb-1"><strong>Phone:</strong> <?php echo sanitize($order['billing_phone']); ?></p>
                                                <p class="mb-0"><strong>Address:</strong><br><?php echo nl2br(sanitize($order['billing_address'])); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="fw-bold">Order Information</h6>
                                                <p class="mb-1"><strong>Order Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></p>
                                                <p class="mb-1">
                                                    <strong>Payment Method:</strong> 
                                                    <?php 
                                                    $modal_methods = [
                                                        'bkash' => '<span style="color: #e2136e;"><strong>bKash</strong></span>',
                                                        'nagad' => '<span style="color: #ec1c24;"><strong>Nagad</strong></span>',
                                                        'cod' => '<span style="color: #00b74a;"><strong>Cash on Delivery</strong></span>'
                                                    ];
                                                    echo $modal_methods[$order['payment_method']] ?? ucfirst($order['payment_method']);
                                                    ?>
                                                </p>
                                                <p class="mb-1"><strong>Payment Status:</strong> 
                                                    <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($order['payment_status']); ?>
                                                    </span>
                                                </p>
                                                <?php if ($order['transaction_id']): ?>
                                                    <p class="mb-0"><strong>Transaction ID:</strong> <?php echo $order['transaction_id']; ?></p>
                                                <?php endif; ?>
                                                <?php if ($order['notes']): ?>
                                                    <p class="mb-0 text-muted small"><strong>Note:</strong> <?php echo sanitize($order['notes']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <h6 class="fw-bold">Order Items</h6>
                                        <table class="table table-sm">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Product</th>
                                                    <th class="text-end">Price</th>
                                                    <th class="text-center">Qty</th>
                                                    <th class="text-end">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $items_stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
                                                $items_stmt->execute([$order['id']]);
                                                $items = $items_stmt->fetchAll();
                                                foreach ($items as $item): 
                                                ?>
                                                    <tr>
                                                        <td><?php echo sanitize($item['product_name']); ?></td>
                                                        <td class="text-end"><?php echo formatPrice($item['product_price']); ?></td>
                                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                        <td class="text-end"><?php echo formatPrice($item['total']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                                    <td class="text-end"><?php echo formatPrice($order['subtotal']); ?></td>
                                                </tr>
                                                <?php if ($order['discount'] > 0): ?>
                                                    <tr class="text-success">
                                                        <td colspan="3" class="text-end"><strong>Discount:</strong></td>
                                                        <td class="text-end">-<?php echo formatPrice($order['discount']); ?></td>
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
                                                    <td colspan="3" class="text-end h5"><strong>Total:</strong></td>
                                                    <td class="text-end h5 text-primary"><strong><?php echo formatPrice($order['total']); ?></strong></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                        
                                        <hr>
                                        
                                        <form method="POST" action="" class="row g-3">
                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                            <div class="col-md-4">
                                                <label class="form-label">Order Status</label>
                                                <select name="status" class="form-select">
                                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <option value="refunded" <?php echo $order['status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label">Payment Status</label>
                                                <select name="payment_status" class="form-select">
                                                    <option value="pending" <?php echo $order['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="paid" <?php echo $order['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                                    <option value="failed" <?php echo $order['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                    <option value="refunded" <?php echo $order['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <button type="submit" name="update_status" class="btn btn-primary w-100">
                                                    <i class="fas fa-save me-2"></i>Update Status
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
