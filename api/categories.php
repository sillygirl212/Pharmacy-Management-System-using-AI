<?php
/**
 * Categories API
 * CRUD operations for medicine categories
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
        // Get all categories
        $sql = "SELECT c.*, COUNT(m.id) as medicine_count 
                FROM categories c 
                LEFT JOIN medicines m ON c.id = m.category_id AND m.is_active = 1 
                GROUP BY c.id 
                ORDER BY c.name ASC";
        
        $result = $conn->query($sql);
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        sendSuccessResponse($categories);
        break;
        
    case 'POST':
        // Create new category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || empty($data['name'])) {
            sendErrorResponse('Category name is required');
        }
        
        $sql = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $data['name'], $data['description']);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            sendSuccessResponse(['id' => $newId], 'Category created successfully');
        } else {
            sendErrorResponse('Failed to create category: ' . $stmt->error);
        }
        break;
        
    case 'PUT':
        // Update category
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            sendErrorResponse('Invalid data or missing ID');
        }
        
        $id = intval($data['id']);
        
        $sql = "UPDATE categories SET name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $data['name'], $data['description'], $id);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Category updated successfully');
        } else {
            sendErrorResponse('Failed to update category');
        }
        break;
        
    case 'DELETE':
        // Delete category (only if no medicines are using it)
        if (!isset($_GET['id'])) {
            sendErrorResponse('Missing ID parameter');
        }
        
        $id = intval($_GET['id']);
        
        // Check if category has medicines
        $checkSql = "SELECT COUNT(*) as count FROM medicines WHERE category_id = ? AND is_active = 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $count = $checkStmt->get_result()->fetch_assoc()['count'];
        
        if ($count > 0) {
            sendErrorResponse('Cannot delete category with active medicines');
        }
        
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            sendSuccessResponse(null, 'Category deleted successfully');
        } else {
            sendErrorResponse('Failed to delete category');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

closeDBConnection($conn);
