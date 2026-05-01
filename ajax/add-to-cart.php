<?php
require_once '../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? 0);
$quantity = intval($_POST['quantity'] ?? 1);

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Check if product exists and is active
$db = getDB();
$stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

// Check if prescription is required
if ($product['prescription_required']) {
    echo json_encode(['success' => false, 'message' => 'This product requires a prescription']);
    exit;
}

// Check stock
if ($product['stock_quantity'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
    exit;
}

// Add to cart
if (addToCart($product_id, $quantity)) {
    $cart_count = getCartCount();
    echo json_encode(['success' => true, 'cart_count' => $cart_count, 'message' => 'Added to cart']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
}
?>
