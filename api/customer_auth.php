<?php
/**
 * Customer Authentication API
 * Login, Logout, and Registration for customers
 */

// CORS headers must come first
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$conn = getDBConnection();

switch ($action) {
    case 'login':
        // Handle customer login
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['email']) || empty($data['password'])) {
            sendErrorResponse('Email and password are required');
        }
        
        $email = $data['email'];
        $password = $data['password'];
        
        // Get customer from database
        $sql = "SELECT id, customer_code, first_name, last_name, email, phone, password, 
                address, date_of_birth, allergies, total_purchases, is_active, created_at 
                FROM customers WHERE email = ? AND password IS NOT NULL";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendErrorResponse('Invalid email or password', 401);
        }
        
        $customer = $result->fetch_assoc();
        
        // Check if customer is active
        if (!$customer['is_active']) {
            sendErrorResponse('Account is deactivated. Please contact administrator.', 403);
        }
        
        // Verify password
        $passwordValid = false;
        
        if (password_verify($password, $customer['password'])) {
            $passwordValid = true;
        } elseif ($password === $customer['password']) {
            // Plain text match - update to hashed password
            $passwordValid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE customers SET password = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $newHash, $customer['id']);
            $updateStmt->execute();
        }
        
        if (!$passwordValid) {
            sendErrorResponse('Invalid email or password', 401);
        }
        
        // Set session variables
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_email'] = $customer['email'];
        $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
        $_SESSION['user_type'] = 'customer';
        
        // Return customer data (excluding password)
        unset($customer['password']);
        
        sendSuccessResponse($customer, 'Login successful');
        break;
        
    case 'register':
        // Handle customer registration
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        // Validate required fields
        $required = ['first_name', 'last_name', 'email', 'password', 'phone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendErrorResponse("Missing required field: $field");
            }
        }
        
        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $email = $data['email'];
        $phone = $data['phone'];
        $password = $data['password'];
        $address = isset($data['address']) ? $data['address'] : '';
        $dateOfBirth = isset($data['date_of_birth']) ? $data['date_of_birth'] : null;
        
        // Check if email already exists
        $checkSql = "SELECT id FROM customers WHERE email = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            sendErrorResponse('Email already registered. Please login.');
        }
        
        // Generate customer code
        $customerCode = generateCustomerCode($conn);
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new customer
        $sql = "INSERT INTO customers (customer_code, first_name, last_name, email, phone, password, address, date_of_birth) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssss", $customerCode, $firstName, $lastName, $email, $phone, $hashedPassword, $address, $dateOfBirth);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            
            // Set session
            $_SESSION['customer_id'] = $newId;
            $_SESSION['customer_email'] = $email;
            $_SESSION['customer_name'] = $firstName . ' ' . $lastName;
            $_SESSION['user_type'] = 'customer';
            
            sendSuccessResponse([
                'id' => $newId,
                'customer_code' => $customerCode,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone
            ], 'Registration successful');
        } else {
            sendErrorResponse('Failed to create account: ' . $stmt->error);
        }
        break;
        
    case 'logout':
        // Handle logout
        session_unset();
        session_destroy();
        sendSuccessResponse(null, 'Logout successful');
        break;
        
    case 'check':
        // Check if customer is logged in
        if (isset($_SESSION['customer_id']) && $_SESSION['user_type'] === 'customer') {
            // Get updated customer info
            $sql = "SELECT id, customer_code, first_name, last_name, email, phone, address, 
                    date_of_birth, allergies, total_purchases, is_active, created_at 
                    FROM customers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $_SESSION['customer_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $customer = $result->fetch_assoc();
                sendSuccessResponse($customer);
            } else {
                sendErrorResponse('Customer not found', 404);
            }
        } else {
            sendErrorResponse('Not authenticated', 401);
        }
        break;
        
    case 'update':
        // Update customer profile
        if (!isset($_SESSION['customer_id']) || $_SESSION['user_type'] !== 'customer') {
            sendErrorResponse('Not authenticated', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $customerId = $_SESSION['customer_id'];
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        $allowedFields = ['first_name', 'last_name', 'email', 'phone', 'address', 'date_of_birth', 'allergies'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= "s";
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        if (empty($updates)) {
            sendErrorResponse('No fields to update');
        }
        
        // Check email uniqueness if being updated
        if (isset($data['email'])) {
            $checkSql = "SELECT id FROM customers WHERE email = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $data['email'], $customerId);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                sendErrorResponse('Email already in use by another account');
            }
        }
        
        $params[] = $customerId;
        $types .= "i";
        
        $sql = "UPDATE customers SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Update session
            if (isset($data['first_name']) || isset($data['last_name'])) {
                $_SESSION['customer_name'] = ($data['first_name'] ?? $_SESSION['customer_name']) . ' ' . ($data['last_name'] ?? '');
            }
            if (isset($data['email'])) {
                $_SESSION['customer_email'] = $data['email'];
            }
            sendSuccessResponse(null, 'Profile updated successfully');
        } else {
            sendErrorResponse('Failed to update profile');
        }
        break;
        
    case 'orders':
        // Get customer order history
        if (!isset($_SESSION['customer_id']) || $_SESSION['user_type'] !== 'customer') {
            sendErrorResponse('Not authenticated', 401);
        }
        
        $customerId = $_SESSION['customer_id'];
        
        $sql = "SELECT s.*, COUNT(si.id) as item_count 
                FROM sales s 
                LEFT JOIN sale_items si ON s.id = si.sale_id 
                WHERE s.customer_id = ? 
                GROUP BY s.id 
                ORDER BY s.sale_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
        
        sendSuccessResponse($orders);
        break;
        
    case 'order_details':
        // Get specific order details with items
        if (!isset($_SESSION['customer_id']) || $_SESSION['user_type'] !== 'customer') {
            sendErrorResponse('Not authenticated', 401);
        }
        
        if (!isset($_GET['order_id'])) {
            sendErrorResponse('Order ID required');
        }
        
        $customerId = $_SESSION['customer_id'];
        $orderId = intval($_GET['order_id']);
        
        // Verify order belongs to customer
        $sql = "SELECT s.* FROM sales s WHERE s.id = ? AND s.customer_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $orderId, $customerId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendErrorResponse('Order not found', 404);
        }
        
        $order = $result->fetch_assoc();
        
        // Get order items
        $itemsSql = "SELECT si.*, m.name as medicine_name, m.medicine_code 
                     FROM sale_items si 
                     LEFT JOIN medicines m ON si.medicine_id = m.id 
                     WHERE si.sale_id = ?";
        $itemsStmt = $conn->prepare($itemsSql);
        $itemsStmt->bind_param("i", $orderId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        
        $order['items'] = [];
        while ($item = $itemsResult->fetch_assoc()) {
            $order['items'][] = $item;
        }
        
        sendSuccessResponse($order);
        break;
        
    default:
        sendErrorResponse('Invalid action', 400);
}

// Helper function to generate customer code
function generateCustomerCode($conn) {
    $prefix = 'CUST-';
    $sql = "SELECT COUNT(*) as count FROM customers WHERE customer_code LIKE ?";
    $stmt = $conn->prepare($sql);
    $likePattern = $prefix . '%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    return $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

closeDBConnection($conn);
