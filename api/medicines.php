<?php
/**
 * Medicines API
 * CRUD operations for medicines
 */

// Suppress warnings to ensure clean JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

// Helper function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Helper function to require admin access
function requireAdmin() {
    if (!isAdmin()) {
        sendErrorResponse('Access denied. Admin privileges required.', 403);
    }
}

switch ($method) {
    case 'GET':
        // Get all medicines or single medicine
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $sql = "SELECT m.*, c.name as category_name, s.name as supplier_name 
                    FROM medicines m 
                    LEFT JOIN categories c ON m.category_id = c.id 
                    LEFT JOIN suppliers s ON m.supplier_id = s.id 
                    WHERE m.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                sendSuccessResponse($result->fetch_assoc());
            } else {
                sendErrorResponse('Medicine not found', 404);
            }
        } else {
            // Get all medicines with optional filtering
            $sql = "SELECT m.*, c.name as category_name, s.name as supplier_name 
                    FROM medicines m 
                    LEFT JOIN categories c ON m.category_id = c.id 
                    LEFT JOIN suppliers s ON m.supplier_id = s.id 
                    WHERE m.is_active = 1";
            
            $params = [];
            $types = "";
            
            // Search filter
            if (isset($_GET['search']) && !empty($_GET['search'])) {
                $search = "%" . $_GET['search'] . "%";
                $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.medicine_code LIKE ?)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $types .= "sss";
            }
            
            // Category filter
            if (isset($_GET['category']) && !empty($_GET['category'])) {
                $category = $_GET['category'];
                $sql .= " AND c.name = ?";
                $params[] = $category;
                $types .= "s";
            }
            
            // Stock status filter
            if (isset($_GET['stock_status'])) {
                switch ($_GET['stock_status']) {
                    case 'low':
                        $sql .= " AND m.stock_quantity <= m.reorder_level AND m.stock_quantity > 0";
                        break;
                    case 'out':
                        $sql .= " AND m.stock_quantity = 0";
                        break;
                    case 'high':
                        $sql .= " AND m.stock_quantity > m.reorder_level";
                        break;
                }
            }
            
            $sql .= " ORDER BY m.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $medicines = [];
            while ($row = $result->fetch_assoc()) {
                $medicines[] = $row;
            }
            
            sendSuccessResponse($medicines);
        }
        break;
        
    case 'POST':
        // Create new medicine - Admin only
        requireAdmin();
        
        // Handle both JSON and multipart form data (for image uploads)
        $data = [];
        $imagePath = null;
        
        // Check if multipart form data (FormData from JavaScript)
        $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        
        if (strpos($contentType, 'multipart/form-data') !== false) {
            // Multipart form data - use $_POST
            $data = $_POST;
            error_log('MEDICINE POST: Received multipart/form-data');
            
            // Check for image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Handle image upload
                $uploadDir = '../uploads/medicines/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileName = time() . '_' . basename($_FILES['image']['name']);
                $targetPath = $uploadDir . $fileName;
                
                // Validate image type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($_FILES['image']['tmp_name']);
                
                if (!in_array($fileType, $allowedTypes)) {
                    sendErrorResponse('Invalid image type. Only JPG, PNG, GIF, WEBP allowed.');
                }
                
                // Validate file size (max 5MB)
                if ($_FILES['image']['size'] > 5 * 1024 * 1024) {
                    sendErrorResponse('Image too large. Maximum 5MB allowed.');
                }
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                    $imagePath = 'uploads/medicines/' . $fileName;
                } else {
                    sendErrorResponse('Failed to upload image');
                }
            }
        } else {
            // JSON data
            $data = json_decode(file_get_contents('php://input'), true);
            error_log('MEDICINE POST: Received JSON data');
        }
        
        if (!$data || empty($data)) {
            error_log('MEDICINE POST: No data received. Content-Type: ' . $contentType);
            error_log('MEDICINE POST: $_POST = ' . json_encode($_POST));
            error_log('MEDICINE POST: $_FILES = ' . json_encode($_FILES));
            sendErrorResponse('Invalid data received. Please fill in all required fields.');
        }
        
        // Ensure image_path key exists
        if (!isset($data['image_path'])) {
            $data['image_path'] = null;
        }
        
        // Validate required fields with detailed messages
        $missingFields = [];
        $required = ['medicine_code', 'name', 'unit_price', 'stock_quantity'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field]) || $data[$field] === '0' && $field !== 'stock_quantity') {
                $missingFields[] = $field;
            }
        }
        
        if (!empty($missingFields)) {
            sendErrorResponse('Please fill in all required fields: ' . implode(', ', $missingFields));
        }
        
        // Validate category_id is numeric if provided
        if (isset($data['category_id']) && $data['category_id'] !== '' && $data['category_id'] !== null) {
            if (!is_numeric($data['category_id'])) {
                sendErrorResponse('Invalid category selected. Please select from the dropdown.');
            }
        }
        
        // Convert expiry_date to MySQL format if needed (from MM/DD/YYYY to YYYY-MM-DD)
        if (isset($data['expiry_date']) && !empty($data['expiry_date'])) {
            $date = $data['expiry_date'];
            // Check if date is in MM/DD/YYYY format
            if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date, $matches)) {
                $data['expiry_date'] = $matches[3] . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $date)) {
                // Already in YYYY-MM-DD format
            } else {
                sendErrorResponse('Invalid expiry date format. Use MM/DD/YYYY or YYYY-MM-DD');
            }
        }
        
        // Check if medicine code already exists
        $checkSql = "SELECT id FROM medicines WHERE medicine_code = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("s", $data['medicine_code']);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            sendErrorResponse('Medicine code already exists');
        }
        
        $sql = "INSERT INTO medicines (medicine_code, name, generic_name, category_id, supplier_id, 
                description, image_path, unit_price, cost_price, stock_quantity, reorder_level, unit, 
                expiry_date, batch_number, barcode) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sssissdsddissss",
            $data['medicine_code'],
            $data['name'],
            $data['generic_name'],
            $data['category_id'],
            $data['supplier_id'],
            $data['description'],
            $imagePath,
            $data['unit_price'],
            $data['cost_price'],
            $data['stock_quantity'],
            $data['reorder_level'],
            $data['unit'],
            $data['expiry_date'],
            $data['batch_number'],
            $data['barcode']
        );
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            
            // Add stock movement record
            if ($data['stock_quantity'] > 0) {
                $movementSql = "INSERT INTO stock_movements (medicine_id, type, quantity, 
                               reference_type, batch_number, expiry_date, notes) 
                               VALUES (?, 'in', ?, 'purchase', ?, ?, 'Initial stock')";
                $movementStmt = $conn->prepare($movementSql);
                $movementStmt->bind_param("iiss", $newId, $data['stock_quantity'], 
                                          $data['batch_number'], $data['expiry_date']);
                $movementStmt->execute();
            }
            
            sendSuccessResponse(['id' => $newId, 'image_path' => $imagePath], 'Medicine created successfully');
        } else {
            sendErrorResponse('Failed to create medicine: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        // Update medicine - Admin only
        requireAdmin();
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            sendErrorResponse('Invalid data or missing ID');
        }
        
        $id = intval($data['id']);
        
        // Check if medicine exists
        $checkSql = "SELECT stock_quantity FROM medicines WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            sendErrorResponse('Medicine not found', 404);
        }
        
        $oldStock = $result->fetch_assoc()['stock_quantity'];
        
        $sql = "UPDATE medicines SET 
                name = ?, generic_name = ?, category_id = ?, supplier_id = ?, 
                description = ?, image_path = COALESCE(?, image_path), unit_price = ?, cost_price = ?, stock_quantity = ?, 
                reorder_level = ?, unit = ?, expiry_date = ?, batch_number = ?, 
                barcode = ?, is_active = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssisssdddissssii",
            $data['name'],
            $data['generic_name'],
            $data['category_id'],
            $data['supplier_id'],
            $data['description'],
            $data['image_path'],
            $data['unit_price'],
            $data['cost_price'],
            $data['stock_quantity'],
            $data['reorder_level'],
            $data['unit'],
            $data['expiry_date'],
            $data['batch_number'],
            $data['barcode'],
            $data['is_active'],
            $id
        );
        
        if ($stmt->execute()) {
            // Record stock movement if quantity changed
            if ($oldStock != $data['stock_quantity']) {
                $diff = $data['stock_quantity'] - $oldStock;
                $type = $diff > 0 ? 'in' : 'out';
                $absDiff = abs($diff);
                
                $movementSql = "INSERT INTO stock_movements (medicine_id, type, quantity, 
                               reference_type, notes) 
                               VALUES (?, ?, ?, 'adjustment', 'Stock adjustment')";
                $movementStmt = $conn->prepare($movementSql);
                $movementStmt->bind_param("isi", $id, $type, $absDiff);
                $movementStmt->execute();
            }
            
            sendSuccessResponse(null, 'Medicine updated successfully');
        } else {
            sendErrorResponse('Failed to update medicine: ' . $stmt->error);
        }
        break;
        
    case 'DELETE':
        // Soft delete medicine - Admin only
        requireAdmin();
        if (!isset($_GET['id'])) {
            sendErrorResponse('Missing ID parameter');
        }
        
        $id = intval($_GET['id']);
        
        $sql = "UPDATE medicines SET is_active = 0 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Medicine deleted successfully');
        } else {
            sendErrorResponse('Failed to delete medicine');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

closeDBConnection($conn);
