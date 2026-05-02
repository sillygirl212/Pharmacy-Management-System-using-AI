<?php
/**
 * Dashboard API
 * Returns statistics and summary data for dashboard
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    sendErrorResponse('Access denied. Admin privileges required.', 403);
}

$conn = getDBConnection();

// Get all dashboard statistics
$stats = [];

// 1. Total Sales (today)
$sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales 
        WHERE DATE(sale_date) = CURDATE() AND payment_status = 'completed'";
$result = $conn->query($sql);
$stats['today_sales'] = floatval($result->fetch_assoc()['total']);

// 2. Total Sales (this month)
$sql = "SELECT COALESCE(SUM(total_amount), 0) as total FROM sales 
        WHERE MONTH(sale_date) = MONTH(CURDATE()) 
        AND YEAR(sale_date) = YEAR(CURDATE()) 
        AND payment_status = 'completed'";
$result = $conn->query($sql);
$stats['month_sales'] = floatval($result->fetch_assoc()['total']);

// 3. Total Medicines count
$sql = "SELECT COUNT(*) as count FROM medicines WHERE is_active = 1";
$result = $conn->query($sql);
$stats['total_medicines'] = intval($result->fetch_assoc()['count']);

// 4. Low stock items
$sql = "SELECT COUNT(*) as count FROM medicines 
        WHERE stock_quantity <= reorder_level AND stock_quantity > 0 AND is_active = 1";
$result = $conn->query($sql);
$stats['low_stock_count'] = intval($result->fetch_assoc()['count']);

// 5. Out of stock items
$sql = "SELECT COUNT(*) as count FROM medicines 
        WHERE stock_quantity = 0 AND is_active = 1";
$result = $conn->query($sql);
$stats['out_of_stock_count'] = intval($result->fetch_assoc()['count']);

// 6. Total Customers
$sql = "SELECT COUNT(*) as count FROM customers WHERE is_active = 1";
$result = $conn->query($sql);
$stats['total_customers'] = intval($result->fetch_assoc()['count']);

// 7. Today's Orders count
$sql = "SELECT COUNT(*) as count FROM sales WHERE DATE(sale_date) = CURDATE()";
$result = $conn->query($sql);
$stats['today_orders'] = intval($result->fetch_assoc()['count']);

// 8. Sales comparison (today vs yesterday)
$sql = "SELECT 
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() THEN total_amount END), 0) as today,
            COALESCE(SUM(CASE WHEN DATE(sale_date) = CURDATE() - INTERVAL 1 DAY THEN total_amount END), 0) as yesterday
        FROM sales 
        WHERE payment_status = 'completed'";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$today = floatval($row['today']);
$yesterday = floatval($row['yesterday']);

if ($yesterday > 0) {
    $change = (($today - $yesterday) / $yesterday) * 100;
    $stats['sales_change_percent'] = round($change, 1);
    $stats['sales_change_direction'] = $change >= 0 ? 'up' : 'down';
} else {
    $stats['sales_change_percent'] = $today > 0 ? 100 : 0;
    $stats['sales_change_direction'] = 'up';
}

// 9. Recent Sales (last 5)
$sql = "SELECT s.id, s.invoice_number, CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
        s.total_amount, s.payment_status, s.sale_date 
        FROM sales s 
        LEFT JOIN customers c ON s.customer_id = c.id 
        ORDER BY s.sale_date DESC 
        LIMIT 5";
$result = $conn->query($sql);
$stats['recent_sales'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['recent_sales'][] = $row;
}

// 10. Low Stock Medicines (top 5)
$sql = "SELECT m.id, m.name, m.medicine_code, m.stock_quantity, m.reorder_level, 
        c.name as category_name 
        FROM medicines m 
        LEFT JOIN categories c ON m.category_id = c.id 
        WHERE m.stock_quantity <= m.reorder_level AND m.stock_quantity > 0 AND m.is_active = 1 
        ORDER BY m.stock_quantity ASC 
        LIMIT 5";
$result = $conn->query($sql);
$stats['low_stock_medicines'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['low_stock_medicines'][] = $row;
}

// 11. Expiring Soon (next 90 days)
$sql = "SELECT m.id, m.name, m.medicine_code, m.expiry_date, m.batch_number,
        DATEDIFF(m.expiry_date, CURDATE()) as days_left
        FROM medicines m 
        WHERE m.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) 
        AND m.expiry_date >= CURDATE() 
        AND m.is_active = 1 AND m.stock_quantity > 0
        ORDER BY m.expiry_date ASC 
        LIMIT 5";
$result = $conn->query($sql);
$stats['expiring_medicines'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['expiring_medicines'][] = $row;
}

// 12. Top Selling Medicines (this month)
$sql = "SELECT m.id, m.name, m.medicine_code, SUM(si.quantity) as total_sold, 
        SUM(si.total_price) as total_revenue 
        FROM sale_items si 
        JOIN medicines m ON si.medicine_id = m.id 
        JOIN sales s ON si.sale_id = s.id 
        WHERE MONTH(s.sale_date) = MONTH(CURDATE()) 
        AND YEAR(s.sale_date) = YEAR(CURDATE())
        AND s.payment_status = 'completed'
        GROUP BY m.id, m.name, m.medicine_code 
        ORDER BY total_sold DESC 
        LIMIT 5";
$result = $conn->query($sql);
$stats['top_selling'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['top_selling'][] = $row;
}

// 13. Sales Chart Data (last 7 days)
$sql = "SELECT 
            DATE(sale_date) as date,
            SUM(total_amount) as total
        FROM sales 
        WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND payment_status = 'completed'
        GROUP BY DATE(sale_date)
        ORDER BY date ASC";
$result = $conn->query($sql);

$chartData = [];
$labels = [];
$values = [];

// Create array with all 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('D', strtotime($date));
    $values[] = 0;
}

// Fill in actual values
while ($row = $result->fetch_assoc()) {
    $date = date('D', strtotime($row['date']));
    $index = array_search($date, $labels);
    if ($index !== false) {
        $values[$index] = floatval($row['total']);
    }
}

$stats['chart_data'] = [
    'labels' => $labels,
    'values' => $values
];

// 14. Inventory Summary by Category
$sql = "SELECT c.name as category, 
        COUNT(m.id) as medicine_count, 
        SUM(m.stock_quantity) as total_stock,
        SUM(m.stock_quantity * m.unit_price) as stock_value
        FROM categories c 
        LEFT JOIN medicines m ON c.id = m.category_id AND m.is_active = 1
        GROUP BY c.id, c.name 
        ORDER BY medicine_count DESC";
$result = $conn->query($sql);
$stats['inventory_by_category'] = [];
while ($row = $result->fetch_assoc()) {
    $stats['inventory_by_category'][] = $row;
}

closeDBConnection($conn);

sendSuccessResponse($stats);
