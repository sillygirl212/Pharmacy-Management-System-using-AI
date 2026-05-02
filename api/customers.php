<?php
/**
 * Customers API
 * CRUD operations for customers
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

switch ($method) {
    case 'GET':
        // Get customer(s)
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT * FROM customers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendSuccessResponse($result->fetch_assoc());
            } else {
                sendErrorResponse('Customer not found', 404);
            }
        } else {
            // Get all customers with search
            $sql = "SELECT * FROM customers WHERE is_active = 1";
            
            $params = [];
            $types = "";
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = "%" . $_GET['search'] . "%";
                $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ? OR customer_code LIKE ?)";
                $params = [$search, $search, $search, $search, $search];
                $types = "sssss";
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
            
            sendSuccessResponse($customers);
        }
        break;
        
    case 'POST':
        // Create new customer
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        // Validate required fields
        if (empty($data['first_name']) || empty($data['last_name'])) {
            sendErrorResponse('First name and last name are required');
        }
        
        // Generate customer code
        $customerCode = generateCustomerCode($conn);
        
        $sql = "INSERT INTO customers (customer_code, first_name, last_name, email, phone, 
                address, date_of_birth, allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssss",
            $customerCode,
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['date_of_birth'],
            $data['allergies']
        );
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            sendSuccessResponse(['id' => $newId, 'customer_code' => $customerCode], 'Customer created successfully');
        } else {
            sendErrorResponse('Failed to create customer: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        // Update customer
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            sendErrorResponse('Invalid data or missing ID');
        }
        
        $id = intval($data['id']);
        
        $sql = "UPDATE customers SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                address = ?, date_of_birth = ?, allergies = ?, is_active = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssssii",
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['date_of_birth'],
            $data['allergies'],
            $data['is_active'],
            $id
        );
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Customer updated successfully');
        } else {
            sendErrorResponse('Failed to update customer');
        }
        break;
        
    case 'DELETE':
        // Soft delete customer
        if (!isset($_GET['id'])) {
            sendErrorResponse('Missing ID parameter');
        }
        
        $id = intval($_GET['id']);
        
        $sql = "UPDATE customers SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Customer deleted successfully');
        } else {
            sendErrorResponse('Failed to delete customer');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
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
