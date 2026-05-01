<?php
/**
 * Core Functions
 */

require_once 'database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get site setting
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getSetting($key, $default = '') {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

/**
 * Update site setting
 * @param string $key
 * @param mixed $value
 * @return bool
 */
function updateSetting($key, $value) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

/**
 * Generate unique slug
 * @param string $text
 * @param string $table
 * @param string $column
 * @param int $exclude_id
 * @return string
 */
function generateSlug($text, $table, $column = 'slug', $exclude_id = 0) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
    $original_slug = $slug;
    $count = 1;
    
    $db = getDB();
    
    while (true) {
        $sql = "SELECT id FROM {$table} WHERE {$column} = ?";
        $params = [$slug];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if (!$stmt->fetch()) {
            break;
        }
        
        $slug = $original_slug . '-' . $count;
        $count++;
    }
    
    return $slug;
}

/**
 * Format price with currency
 * @param float $amount
 * @return string
 */
function formatPrice($amount) {
    $symbol = getSetting('currency_symbol', '$');
    return $symbol . number_format($amount, 2);
}

/**
 * Sanitize input
 * @param string $data
 * @return string
 */
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Display flash message
 * @param string $type (success, error, warning, info)
 * @param string $message
 */
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 * @return array|null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if user is admin
 * @return bool
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['admin', 'editor']);
}

/**
 * Check if user is super admin
 * @return bool
 */
function isSuperAdmin() {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirect to URL
 * @param string $url
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Get current user ID
 * @return int|null
 */
function getCurrentUserId() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Log activity
 * @param string $action
 * @param string $description
 */
function logActivity($action, $description = '') {
    $db = getDB();
    $user_id = getCurrentUserId();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
    } catch (PDOException $e) {
        // If foreign key fails (user doesn't exist), log with NULL user_id
        if (strpos($e->getMessage(), 'foreign key constraint fails') !== false) {
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (NULL, ?, ?, ?, ?)");
            $stmt->execute([$action, $description, $ip, $user_agent]);
        }
    }
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate order number
 * @return string
 */
function generateOrderNumber() {
    $prefix = 'ORD';
    $date = date('Ymd');
    $random = strtoupper(substr(uniqid(), -4));
    return $prefix . $date . $random;
}

/**
 * Upload file
 * @param array $file
 * @param string $directory
 * @param array $allowed_types
 * @param int $max_size
 * @return array [success, path/message]
 */
function uploadFile($file, $directory, $allowed_types = [], $max_size = 5242880) {
    $upload_dir = __DIR__ . '/../uploads/' . $directory . '/';
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed with error code: ' . $file['error']];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Max size: ' . ($max_size / 1024 / 1024) . 'MB'];
    }
    
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!empty($allowed_types) && !in_array($file_type, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = generateRandomString(16) . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => 'uploads/' . $directory . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Send email
 * @param string $to
 * @param string $subject
 * @param string $body
 * @return bool
 */
function sendEmail($to, $subject, $body) {
    $headers = "From: " . getSetting('site_email', 'noreply@pharmacy.com') . "\r\n";
    $headers .= "Reply-To: " . getSetting('site_email', 'noreply@pharmacy.com') . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $body, $headers);
}

/**
 * Pagination
 * @param int $total_items
 * @param int $items_per_page
 * @param int $current_page
 * @return array
 */
function paginate($total_items, $items_per_page = 12, $current_page = 1) {
    $total_pages = ceil($total_items / $items_per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $items_per_page;
    
    return [
        'total_items' => $total_items,
        'items_per_page' => $items_per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

/**
 * Get cart count
 * @return int
 */
function getCartCount() {
    if (isLoggedIn()) {
        $db = getDB();
        $stmt = $db->prepare("SELECT SUM(quantity) as count FROM cart WHERE user_id = ?");
        $stmt->execute([getCurrentUserId()]);
        $result = $stmt->fetch();
        return $result['count'] ?: 0;
    } else {
        $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
        $count = 0;
        foreach ($cart as $item) {
            $count += $item['quantity'];
        }
        return $count;
    }
}

/**
 * Calculate cart total
 * @return float
 */
function getCartTotal() {
    $db = getDB();
    $total = 0;
    
    if (isLoggedIn()) {
        $stmt = $db->prepare("
            SELECT c.quantity, COALESCE(p.sale_price, p.price) as price 
            FROM cart c 
            JOIN products p ON c.product_id = p.id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([getCurrentUserId()]);
        $items = $stmt->fetchAll();
        
        foreach ($items as $item) {
            $total += $item['quantity'] * $item['price'];
        }
    } else {
        $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];
        foreach ($cart as $product_id => $item) {
            $stmt = $db->prepare("SELECT COALESCE(sale_price, price) as price FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if ($product) {
                $total += $item['quantity'] * $product['price'];
            }
        }
    }
    
    return $total;
}

/**
 * Add to cart
 * @param int $product_id
 * @param int $quantity
 * @return bool
 */
function addToCart($product_id, $quantity = 1) {
    $db = getDB();
    
    if (isLoggedIn()) {
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([getCurrentUserId(), $product_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $new_quantity = $existing['quantity'] + $quantity;
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            return $stmt->execute([$new_quantity, $existing['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            return $stmt->execute([getCurrentUserId(), $product_id, $quantity]);
        }
    } else {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$product_id] = ['quantity' => $quantity];
        }
        
        return true;
    }
}

/**
 * Validate coupon
 * @param string $code
 * @param float $cart_total
 * @return array
 */
function validateCoupon($code, $cart_total) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM coupons 
        WHERE code = ? AND status = 'active' 
        AND (start_date IS NULL OR start_date <= CURDATE())
        AND (end_date IS NULL OR end_date >= CURDATE())
        AND (usage_limit IS NULL OR usage_count < usage_limit)
        AND (min_purchase IS NULL OR min_purchase <= ?)
    ");
    $stmt->execute([$code, $cart_total]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        return ['valid' => false, 'message' => 'Invalid or expired coupon code'];
    }
    
    $discount = 0;
    if ($coupon['type'] === 'percentage') {
        $discount = $cart_total * ($coupon['value'] / 100);
    } else {
        $discount = $coupon['value'];
    }
    
    if ($coupon['max_discount'] && $discount > $coupon['max_discount']) {
        $discount = $coupon['max_discount'];
    }
    
    return [
        'valid' => true,
        'discount' => $discount,
        'coupon' => $coupon
    ];
}

/**
 * Get product image
 * @param int $product_id
 * @return string
 */
function getProductImage($product_id) {
    $db = getDB();
    $stmt = $db->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
    $stmt->execute([$product_id]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['image_path'];
    }
    
    // Return default placeholder
    return 'assets/images/product-placeholder.jpg';
}

/**
 * Truncate text
 * @param string $text
 * @param int $length
 * @return string
 */
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

/**
 * Calculate tax
 * @param float $amount
 * @return float
 */
function calculateTax($amount) {
    $tax_rate = floatval(getSetting('tax_rate', 0));
    return $amount * ($tax_rate / 100);
}

/**
 * Get shipping cost
 * @return float
 */
function getShippingCost() {
    return floatval(getSetting('shipping_cost', 0));
}

/**
 * Get featured products
 * @param int $limit
 * @return array
 */
function getFeaturedProducts($limit = 8) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.featured = 1 AND p.status = 'active'
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get related products
 * @param int $product_id
 * @param int $category_id
 * @param int $limit
 * @return array
 */
function getRelatedProducts($product_id, $category_id, $limit = 4) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id != ? AND p.category_id = ? AND p.status = 'active'
        LIMIT ?
    ");
    $stmt->execute([$product_id, $category_id, $limit]);
    return $stmt->fetchAll();
}

/**
 * Check if product is in wishlist
 * @param int $product_id
 * @return bool
 */
function isInWishlist($product_id) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $stmt->execute([getCurrentUserId(), $product_id]);
    return $stmt->fetch() ? true : false;
}

/**
 * Time ago function
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>
