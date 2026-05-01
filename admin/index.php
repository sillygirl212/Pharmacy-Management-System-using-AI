<?php
require_once '../config/functions.php';

// Check admin access
if (!isAdmin()) {
    setFlashMessage('error', 'Access denied.');
    redirect('../login.php');
}

$page_title = 'Admin Dashboard';
$db = getDB();

// Get statistics
$stats = [
    'total_products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'total_orders' => $db->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'total_users' => $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'total_revenue' => $db->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'paid'")->fetchColumn(),
    'pending_orders' => $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM products WHERE stock_quantity < 10 AND status = 'active'")->fetchColumn()
];

// Get recent orders
$recent_orders = $db->query("
    SELECT o.*, u.name as user_name 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    ORDER BY o.created_at DESC 
    LIMIT 10
")->fetchAll();

// Get sales chart data (last 7 days)
$sales_data = $db->query("
    SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND payment_status = 'paid'
    GROUP BY DATE(created_at)
    ORDER BY date ASC
")->fetchAll();

// Get top products
$top_products = $db->query("
    SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.total) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0">Dashboard</h2>
        <a href="../index.php" target="_blank" class="btn btn-primary">
            <i class="fas fa-external-link-alt me-2"></i>View Site
        </a>
    </div>
    
    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Products</h6>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_products']); ?></h3>
                        </div>
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                            <i class="fas fa-box fa-2x text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Orders</h6>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_orders']); ?></h3>
                        </div>
                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                            <i class="fas fa-shopping-cart fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Revenue</h6>
                            <h3 class="fw-bold mb-0"><?php echo formatPrice($stats['total_revenue']); ?></h3>
                        </div>
                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                            <i class="fas fa-dollar-sign fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Users</h6>
                            <h3 class="fw-bold mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                        </div>
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                            <i class="fas fa-users fa-2x text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Sales Chart -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Sales Overview (Last 7 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Alerts & Quick Stats -->
        <div class="col-lg-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Alerts</h5>
                </div>
                <div class="card-body">
                    <?php if ($stats['pending_orders'] > 0): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong><?php echo $stats['pending_orders']; ?></strong> pending orders need attention.
                            <a href="orders.php?status=pending" class="alert-link">View</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['low_stock'] > 0): ?>
                        <div class="alert alert-danger mb-3">
                            <i class="fas fa-boxes me-2"></i>
                            <strong><?php echo $stats['low_stock']; ?></strong> products are low in stock.
                            <a href="products.php?low_stock=1" class="alert-link">View</a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($stats['pending_orders'] == 0 && $stats['low_stock'] == 0): ?>
                        <div class="alert alert-success mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            All systems operational. No alerts at this time.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Top Products -->
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="fw-bold mb-0">Top Products</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($top_products as $product): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-truncate" style="max-width: 60%;"><?php echo sanitize($product['name']); ?></span>
                                <span class="badge badge-primary rounded-pill"><?php echo $product['total_sold']; ?> sold</span>
                            </li>
                        <?php endforeach; ?>
                        <?php if (empty($top_products)): ?>
                            <li class="list-group-item text-muted text-center">No sales data yet</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0">Recent Orders</h5>
            <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
        </div>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): 
                            $status_colors = [
                                'pending' => 'warning', 'processing' => 'info',
                                'shipped' => 'primary', 'delivered' => 'success',
                                'cancelled' => 'danger', 'refunded' => 'secondary'
                            ];
                        ?>
                            <tr>
                                <td><?php echo $order['order_number']; ?></td>
                                <td><?php echo sanitize($order['user_name']); ?></td>
                                <td><?php echo formatPrice($order['total']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $status_colors[$order['status']] ?? 'secondary'; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $order['payment_status'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Sales Chart
    const ctx = document.getElementById('salesChart').getContext('2d');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $sales_data)); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_map(function($d) { return $d['revenue']; }, $sales_data)); ?>,
                borderColor: '#1266f1',
                backgroundColor: 'rgba(18, 102, 241, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Orders',
                data: <?php echo json_encode(array_map(function($d) { return $d['orders']; }, $sales_data)); ?>,
                borderColor: '#00b74a',
                backgroundColor: 'transparent',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
