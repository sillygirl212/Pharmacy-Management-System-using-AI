<?php
/**
 * Suppliers API
 * CRUD operations for suppliers
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
        // Get supplier(s)
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT * FROM suppliers WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendSuccessResponse($result->fetch_assoc());
            } else {
                sendErrorResponse('Supplier not found', 404);
            }
        } else {
            // Get all suppliers with search
            $sql = "SELECT * FROM suppliers WHERE 1=1";
            
            $params = [];
            $types = "";
            
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = "%" . $_GET['search'] . "%";
                $sql .= " AND (name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR supplier_code LIKE ?)";
                $params = [$search, $search, $search, $search];
                $types = "ssss";
            }
            
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $sql .= " AND status = ?";
                $params[] = $_GET['status'];
                $types .= "s";
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $suppliers = [];
            while ($row = $result->fetch_assoc()) {
                $suppliers[] = $row;
            }
            
            sendSuccessResponse($suppliers);
        }
        break;
        
    case 'POST':
        // Create new supplier
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        // Validate required fields
        if (empty($data['name'])) {
            sendErrorResponse('Supplier name is required');
        }
        
        // Generate supplier code
        $supplierCode = generateSupplierCode($conn);
        
        $sql = "INSERT INTO suppliers (supplier_code, name, contact_person, email, phone, address, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $status = isset($data['status']) ? $data['status'] : 'active';
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssssss",
            $supplierCode,
            $data['name'],
            $data['contact_person'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $status
        );
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            sendSuccessResponse(['id' => $newId, 'supplier_code' => $supplierCode], 'Supplier created successfully');
        } else {
            sendErrorResponse('Failed to create supplier: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        // Update supplier
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            sendErrorResponse('Invalid data or missing ID');
        }
        
        $id = intval($data['id']);
        
        $sql = "UPDATE suppliers SET 
                name = ?, contact_person = ?, email = ?, phone = ?, 
                address = ?, status = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssi",
            $data['name'],
            $data['contact_person'],
            $data['email'],
            $data['phone'],
            $data['address'],
            $data['status'],
            $id
        );
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Supplier updated successfully');
        } else {
            sendErrorResponse('Failed to update supplier');
        }
        break;
        
    case 'DELETE':
        // Delete supplier
        if (!isset($_GET['id'])) {
            sendErrorResponse('Missing ID parameter');
        }
        
        $id = intval($_GET['id']);
        
        // Check if supplier has associated medicines
        $checkSql = "SELECT COUNT(*) as count FROM medicines WHERE supplier_id = ? AND is_active = 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $count = $checkStmt->get_result()->fetch_assoc()['count'];
        
        if ($count > 0) {
            sendErrorResponse('Cannot delete supplier with active medicines');
        }
        
        $sql = "DELETE FROM suppliers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Supplier deleted successfully');
        } else {
            sendErrorResponse('Failed to delete supplier');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

// Helper function to generate supplier code
function generateSupplierCode($conn) {
    $prefix = 'SUP-';
    $sql = "SELECT COUNT(*) as count FROM suppliers WHERE supplier_code LIKE ?";
    $stmt = $conn->prepare($sql);
    $likePattern = $prefix . '%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    return $prefix . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
}

closeDBConnection($conn);
