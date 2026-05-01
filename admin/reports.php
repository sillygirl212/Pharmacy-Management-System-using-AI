<?php
require_once '../config/functions.php';

if (!isAdmin()) {
    redirect('../login.php');
}

$page_title = 'Reports';
$db = getDB();

// Get date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Sales summary
$sales_stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total) as total_revenue,
        SUM(tax) as total_tax,
        SUM(shipping) as total_shipping,
        SUM(discount) as total_discounts
    FROM orders 
    WHERE payment_status = 'paid' 
    AND DATE(created_at) BETWEEN ? AND ?
");
$sales_stmt->execute([$start_date, $end_date]);
$sales_summary = $sales_stmt->fetch();

// Daily sales
$daily_stmt = $db->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue
    FROM orders 
    WHERE payment_status = 'paid' 
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$daily_stmt->execute([$start_date, $end_date]);
$daily_sales = $daily_stmt->fetchAll();

// Top products
$top_products_stmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) as quantity, SUM(oi.total) as revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.payment_status = 'paid'
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY p.id
    ORDER BY quantity DESC
    LIMIT 10
");
$top_products_stmt->execute([$start_date, $end_date]);
$top_products = $top_products_stmt->fetchAll();

// Payment methods
$payment_stmt = $db->prepare("
    SELECT payment_method, COUNT(*) as count, SUM(total) as amount
    FROM orders 
    WHERE payment_status = 'paid'
    AND DATE(created_at) BETWEEN ? AND ?
    GROUP BY payment_method
");
$payment_stmt->execute([$start_date, $end_date]);
$payment_methods = $payment_stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="fw-bold mb-0">Reports</h2>
</div>

<!-- Date Filter -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Generate Report</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Total Orders</h6>
                <h3 class="fw-bold mb-0"><?php echo number_format($sales_summary['total_orders'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Total Revenue</h6>
                <h3 class="fw-bold mb-0"><?php echo formatPrice($sales_summary['total_revenue'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-info text-white">
            <div class="card-body p-4">
                <h6 class="mb-1">Tax Collected</h6>
                <h3 class="fw-bold mb-0"><?php echo formatPrice($sales_summary['total_tax'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-warning text-dark">
            <div class="card-body p-4">
                <h6 class="mb-1">Discounts</h6>
                <h3 class="fw-bold mb-0"><?php echo formatPrice($sales_summary['total_discounts'] ?? 0); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Sales Chart -->
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Daily Sales</h5>
            </div>
            <div class="card-body">
                <canvas id="salesChart" height="250"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods -->
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="fw-bold mb-0">Payment Methods</h5>
            </div>
            <div class="card-body">
                <canvas id="paymentChart" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Top Products -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white py-3">
        <h5 class="fw-bold mb-0">Top Selling Products</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Product</th>
                        <th class="text-center">Quantity Sold</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td><?php echo sanitize($product['name']); ?></td>
                            <td class="text-center">
                                <span class="badge badge-primary"><?php echo $product['quantity']; ?></span>
                            </td>
                            <td class="text-end fw-bold"><?php echo formatPrice($product['revenue']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($top_products)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-4 text-muted">No sales data for this period.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(function($d) { return date('M d', strtotime($d['date'])); }, $daily_sales)); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_map(function($d) { return $d['revenue']; }, $daily_sales)); ?>,
                borderColor: '#1266f1',
                backgroundColor: 'rgba(18, 102, 241, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Payment Chart
    const paymentCtx = document.getElementById('paymentChart').getContext('2d');
    new Chart(paymentCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_map(function($p) { return ucfirst($p['payment_method']); }, $payment_methods)); ?>,
            datasets: [{
                data: <?php echo json_encode(array_map(function($p) { return $p['count']; }, $payment_methods)); ?>,
                backgroundColor: ['#1266f1', '#00b74a', '#f93154', '#ffa900', '#39c0ed']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
