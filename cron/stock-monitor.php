<?php
/**
 * Stock Monitoring Cron Job
 * Run this script periodically to check stock levels and create alerts
 * 
 * Setup: Add to crontab (Linux) or Task Scheduler (Windows)
 * Example (every hour): 0 * * * * /usr/bin/php /path/to/cron/stock-monitor.php
 */

require_once __DIR__ . '/../config/ai-chatbot.php';

echo "Stock Monitor Started: " . date('Y-m-d H:i:s') . "\n";

$chatbot = new PharmacyAIChatbot();

// Check for low stock and create alerts
$alerts = $chatbot->checkStockAlerts();

if (!empty($alerts)) {
    echo "Created " . count($alerts) . " stock alerts:\n";
    foreach ($alerts as $alert) {
        echo "  - {$alert['product']}: {$alert['stock']} units remaining (Alert ID: {$alert['alert_id']})\n";
    }
    
    // Send notification email to admin (optional)
    $admin_email = getSetting('site_email', 'admin@pharmacy.com');
    
    $subject = "Stock Alert: " . count($alerts) . " products need attention";
    $body = "<h2>Stock Alert</h2>";
    $body .= "<p>The following products are running low or out of stock:</p><ul>";
    
    foreach ($alerts as $alert) {
        $status = $alert['stock'] == 0 ? 'OUT OF STOCK' : 'LOW STOCK (' . $alert['stock'] . ' units)';
        $body .= "<li><strong>{$alert['product']}</strong> - {$status}</li>";
    }
    
    $body .= "</ul><p>Please restock these items soon.</p>";
    
    sendEmail($admin_email, $subject, $body);
    echo "Notification email sent to {$admin_email}\n";
} else {
    echo "No new stock alerts.\n";
}

// Check for expiring medicines (if expiry_date field is used)
$db = getDB();
$stmt = $db->query("
    SELECT id, name, expiry_date, stock_quantity
    FROM products
    WHERE expiry_date IS NOT NULL 
    AND expiry_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
    AND expiry_date >= NOW()
    AND status = 'active'
");

$expiring = $stmt->fetchAll();

if (!empty($expiring)) {
    echo "\nFound " . count($expiring) . " products expiring within 30 days:\n";
    foreach ($expiring as $product) {
        $days_left = floor((strtotime($product['expiry_date']) - time()) / 86400);
        echo "  - {$product['name']}: Expires in {$days_left} days ({$product['expiry_date']})\n";
        
        // Create expiry alert
        $stmt = $db->prepare("
            INSERT INTO stock_alerts (product_id, alert_type, is_triggered)
            VALUES (?, 'expiry', 1)
            ON DUPLICATE KEY UPDATE is_triggered = 1
        ");
        $stmt->execute([$product['id']]);
    }
} else {
    echo "\nNo products expiring within 30 days.\n";
}

echo "\nStock Monitor Completed: " . date('Y-m-d H:i:s') . "\n";
?>
