<?php
/**
 * Authentication API
 * Login, Logout, and Registration
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
        // Handle login
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['username']) || empty($data['password'])) {
            sendErrorResponse('Username and password are required');
        }
        
        $username = $data['username'];
        $password = $data['password'];
        
        // Get user from database
        $sql = "SELECT id, username, password, email, full_name, role, is_active FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendErrorResponse('Invalid username or password', 401);
        }
        
        $user = $result->fetch_assoc();
        
        // Check if user is active
        if (!$user['is_active']) {
            sendErrorResponse('Account is deactivated. Please contact administrator.', 403);
        }
        
        // Verify password
        // Note: For the initial admin user with plain text password 'admin123',
        // we need to handle both hashed and plain text passwords
        $passwordValid = false;
        
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } elseif ($password === $user['password']) {
            // Plain text match - update to hashed password
            $passwordValid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("si", $newHash, $user['id']);
            $updateStmt->execute();
        }
        
        if (!$passwordValid) {
            sendErrorResponse('Invalid username or password', 401);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // Return user data (excluding password)
        unset($user['password']);
        
        sendSuccessResponse($user, 'Login successful');
        break;
        
    case 'register':
        // Handle user registration
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        // Validate required fields
        $required = ['username', 'password', 'email', 'full_name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                sendErrorResponse("Missing required field: $field");
            }
        }
        
        $username = $data['username'];
        $password = $data['password'];
        $email = $data['email'];
        $fullName = $data['full_name'];
        $role = isset($data['role']) ? $data['role'] : 'pharmacist';
        
        // Validate role
        $validRoles = ['admin', 'pharmacist', 'cashier'];
        if (!in_array($role, $validRoles)) {
            $role = 'pharmacist';
        }
        
        // Check if username already exists
        $checkSql = "SELECT id FROM users WHERE username = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $username);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            sendErrorResponse('Username already exists');
        }
        
        // Check if email already exists
        $checkEmailSql = "SELECT id FROM users WHERE email = ?";
        $checkEmailStmt = $conn->prepare($checkEmailSql);
        $checkEmailStmt->bind_param("s", $email);
        $checkEmailStmt->execute();
        if ($checkEmailStmt->get_result()->num_rows > 0) {
            sendErrorResponse('Email already exists');
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $sql = "INSERT INTO users (username, password, email, full_name, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $hashedPassword, $email, $fullName, $role);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            sendSuccessResponse(['id' => $newId], 'User registered successfully');
        } else {
            sendErrorResponse('Failed to create user: ' . $stmt->error);
        }
        break;
        
    case 'logout':
        // Handle logout
        session_unset();
        session_destroy();
        sendSuccessResponse(null, 'Logout successful');
        break;
        
    case 'check':
        // Check if user is logged in
        if (isset($_SESSION['user_id'])) {
            $user = [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'full_name' => $_SESSION['full_name']
            ];
            sendSuccessResponse($user);
        } else {
            sendErrorResponse('Not authenticated', 401);
        }
        break;
        
    case 'update':
        // Update user profile
        if (!isset($_SESSION['user_id'])) {
            sendErrorResponse('Not authenticated', 401);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($data['email'])) {
            $updates[] = "email = ?";
            $params[] = $data['email'];
            $types .= "s";
        }
        
        if (isset($data['full_name'])) {
            $updates[] = "full_name = ?";
            $params[] = $data['full_name'];
            $types .= "s";
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $updates[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $types .= "s";
        }
        
        if (empty($updates)) {
            sendErrorResponse('No fields to update');
        }
        
        $params[] = $userId;
        $types .= "i";
        
        $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            // Update session if full_name changed
            if (isset($data['full_name'])) {
                $_SESSION['full_name'] = $data['full_name'];
            }
            sendSuccessResponse(null, 'Profile updated successfully');
        } else {
            sendErrorResponse('Failed to update profile');
        }
        break;
        
    case 'list':
        // List all users (admin only)
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            sendErrorResponse('Access denied', 403);
        }
        
        $sql = "SELECT id, username, email, full_name, role, is_active, created_at FROM users ORDER BY created_at DESC";
        $result = $conn->query($sql);
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        
        sendSuccessResponse($users);
        break;
        
    case 'toggle':
        // Toggle user active status (admin only)
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            sendErrorResponse('Access denied', 403);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['user_id'])) {
            sendErrorResponse('User ID required');
        }
        
        $targetUserId = intval($data['user_id']);
        
        // Prevent self-deactivation
        if ($targetUserId == $_SESSION['user_id']) {
            sendErrorResponse('Cannot deactivate your own account');
        }
        
        $sql = "UPDATE users SET is_active = NOT is_active WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $targetUserId);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'User status updated');
        } else {
            sendErrorResponse('Failed to update user status');
        }
        break;
        
    case 'stats':
        // Get user statistics
        if (!isset($_GET['user_id'])) {
            sendErrorResponse('User ID required');
        }
        
        $userId = intval($_GET['user_id']);
        
        // Get total orders
        $ordersSql = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent 
                     FROM sales WHERE user_id = ? AND payment_status = 'completed'";
        $ordersStmt = $conn->prepare($ordersSql);
        $ordersStmt->bind_param("i", $userId);
        $ordersStmt->execute();
        $ordersResult = $ordersStmt->get_result()->fetch_assoc();
        
        // Get user creation date
        $userSql = "SELECT created_at FROM users WHERE id = ?";
        $userStmt = $conn->prepare($userSql);
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result()->fetch_assoc();
        
        sendSuccessResponse([
            'total_orders' => intval($ordersResult['total_orders']),
            'total_spent' => floatval($ordersResult['total_spent']),
            'created_at' => $userResult['created_at']
        ]);
        break;
        
    default:
        sendErrorResponse('Invalid action', 400);
}

closeDBConnection($conn);
