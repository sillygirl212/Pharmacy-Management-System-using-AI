<?php
/**
 * Sales API
 * Handles POS sales and orders
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
        // Get sales data
        if (isset($_GET['id'])) {
            // Get single sale with items
            $id = intval($_GET['id']);
            
            $sql = "SELECT s.*, c.first_name, c.last_name, c.email, c.phone, u.full_name as user_name 
                    FROM sales s 
                    LEFT JOIN customers c ON s.customer_id = c.id 
                    LEFT JOIN users u ON s.user_id = u.id 
                    WHERE s.id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $sale = $result->fetch_assoc();
                
                // Get sale items
                $itemsSql = "SELECT si.*, m.name as medicine_name, m.medicine_code 
                            FROM sale_items si 
                            LEFT JOIN medicines m ON si.medicine_id = m.id 
                            WHERE si.sale_id = ?";
                $itemsStmt = $conn->prepare($itemsSql);
                $itemsStmt->bind_param("i", $id);
                $itemsStmt->execute();
                $itemsResult = $itemsStmt->get_result();
                
                $sale['items'] = [];
                while ($item = $itemsResult->fetch_assoc()) {
                    $sale['items'][] = $item;
                }
                
                sendSuccessResponse($sale);
            } else {
                sendErrorResponse('Sale not found', 404);
            }
        } else {
            // Get all sales with pagination
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT s.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name 
                    FROM sales s 
                    LEFT JOIN customers c ON s.customer_id = c.id 
                    WHERE 1=1";
            
            $params = [];
            $types = "";
            
            // Date range filter
            if (isset($_GET['from_date']) && !empty($_GET['from_date'])) {
                $sql .= " AND DATE(s.sale_date) >= ?";
                $params[] = $_GET['from_date'];
                $types .= "s";
            }
            
            if (isset($_GET['to_date']) && !empty($_GET['to_date'])) {
                $sql .= " AND DATE(s.sale_date) <= ?";
                $params[] = $_GET['to_date'];
                $types .= "s";
            }
            
            // Payment status filter
            if (isset($_GET['status']) && !empty($_GET['status'])) {
                $sql .= " AND s.payment_status = ?";
                $params[] = $_GET['status'];
                $types .= "s";
            }
            
            $sql .= " ORDER BY s.sale_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $types .= "ii";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $sales = [];
            while ($row = $result->fetch_assoc()) {
                $sales[] = $row;
            }
            
            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM sales";
            $countResult = $conn->query($countSql);
            $total = $countResult->fetch_assoc()['total'];
            
            sendSuccessResponse([
                'sales' => $sales,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        }
        break;
        
    case 'POST':
        // Create new sale (POS checkout)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendErrorResponse('Invalid JSON data');
        }
        
        // Validate required fields
        if (!isset($data['items']) || empty($data['items'])) {
            sendErrorResponse('No items in cart');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Generate invoice number
            $invoiceNumber = generateInvoiceNumber($conn);
            
            // Calculate totals
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $subtotal += $item['price'] * $item['qty'];
            }
            
            $taxRate = isset($data['tax_rate']) ? floatval($data['tax_rate']) : 0.05;
            $taxAmount = $subtotal * $taxRate;
            $discountAmount = isset($data['discount']) ? floatval($data['discount']) : 0;
            $totalAmount = $subtotal + $taxAmount - $discountAmount;
            
            // Insert sale record
            $saleSql = "INSERT INTO sales (invoice_number, customer_id, user_id, subtotal, 
                        tax_amount, discount_amount, total_amount, payment_method, 
                        payment_phone, transaction_id, payment_status, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $customerId = isset($data['customer_id']) ? intval($data['customer_id']) : null;
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
            $paymentMethod = isset($data['payment_method']) ? $data['payment_method'] : 'cash';
            $paymentPhone = isset($data['payment_phone']) ? $data['payment_phone'] : null;
            $transactionId = isset($data['transaction_id']) ? $data['transaction_id'] : null;
            $notes = isset($data['notes']) ? $data['notes'] : '';
            
            // Set payment status based on method
            $paymentStatus = 'completed';
            if ($paymentMethod === 'cod') {
                $paymentStatus = 'pending_delivery';
            }
            
            $saleStmt = $conn->prepare($saleSql);
            $saleStmt->bind_param(
                "sidddddsssss",
                $invoiceNumber,
                $customerId,
                $userId,
                $subtotal,
                $taxAmount,
                $discountAmount,
                $totalAmount,
                $paymentMethod,
                $paymentPhone,
                $transactionId,
                $paymentStatus,
                $notes
            );
            
            $saleStmt->execute();
            $saleId = $saleStmt->insert_id;
            
            // Insert sale items and update stock
            foreach ($data['items'] as $item) {
                // Check stock availability
                $stockSql = "SELECT stock_quantity FROM medicines WHERE id = ?";
                $stockStmt = $conn->prepare($stockSql);
                
                // Get medicine ID by name (or use provided ID)
                $medicineId = isset($item['medicine_id']) ? intval($item['medicine_id']) : null;
                
                if (!$medicineId) {
                    // Find medicine by name
                    $findSql = "SELECT id FROM medicines WHERE name = ? AND is_active = 1";
                    $findStmt = $conn->prepare($findSql);
                    $findStmt->bind_param("s", $item['name']);
                    $findStmt->execute();
                    $findResult = $findStmt->get_result();
                    
                    if ($findResult->num_rows > 0) {
                        $medicineId = $findResult->fetch_assoc()['id'];
                    } else {
                        throw new Exception("Medicine not found: " . $item['name']);
                    }
                }
                
                $stockStmt->bind_param("i", $medicineId);
                $stockStmt->execute();
                $stockResult = $stockStmt->get_result();
                $currentStock = $stockResult->fetch_assoc()['stock_quantity'];
                
                if ($currentStock < $item['qty']) {
                    throw new Exception("Insufficient stock for: " . $item['name']);
                }
                
                // Insert sale item
                $itemSql = "INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, total_price) 
                           VALUES (?, ?, ?, ?, ?)";
                $itemStmt = $conn->prepare($itemSql);
                $itemTotal = $item['price'] * $item['qty'];
                $itemStmt->bind_param("iiidd", $saleId, $medicineId, $item['qty'], $item['price'], $itemTotal);
                $itemStmt->execute();
                
                // Update stock
                $newStock = $currentStock - $item['qty'];
                $updateSql = "UPDATE medicines SET stock_quantity = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("ii", $newStock, $medicineId);
                $updateStmt->execute();
                
                // Record stock movement
                $movementSql = "INSERT INTO stock_movements (medicine_id, type, quantity, 
                               reference_type, reference_id, notes) 
                               VALUES (?, 'out', ?, 'sale', ?, 'Sale: ' . ?)";
                $movementStmt = $conn->prepare($movementSql);
                $movementStmt->bind_param("iiis", $medicineId, $item['qty'], $saleId, $invoiceNumber);
                $movementStmt->execute();
            }
            
            // Update customer total purchases
            if ($customerId) {
                $custUpdateSql = "UPDATE customers SET total_purchases = total_purchases + ? 
                                 WHERE id = ?";
                $custUpdateStmt = $conn->prepare($custUpdateSql);
                $custUpdateStmt->bind_param("di", $totalAmount, $customerId);
                $custUpdateStmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            sendSuccessResponse([
                'sale_id' => $saleId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount
            ], 'Sale completed successfully');
            
        } catch (Exception $e) {
            $conn->rollback();
            sendErrorResponse($e->getMessage());
        }
        break;
        
    case 'PUT':
        // Update sale (e.g., refund)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['id'])) {
            sendErrorResponse('Invalid data or missing ID');
        }
        
        $id = intval($data['id']);
        
        // Update sale status
        if (isset($data['payment_status'])) {
            $sql = "UPDATE sales SET payment_status = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $data['payment_status'], $id);
            
            if ($stmt->execute()) {
                sendSuccessResponse(null, 'Sale updated successfully');
            } else {
                sendErrorResponse('Failed to update sale');
            }
        } else {
            sendErrorResponse('No fields to update');
        }
        break;
        
    default:
        sendErrorResponse('Method not allowed', 405);
}

// Helper function to generate invoice number
function generateInvoiceNumber($conn) {
    $prefix = 'INV-' . date('Y') . '-';
    $sql = "SELECT COUNT(*) as count FROM sales WHERE invoice_number LIKE ?";
    $stmt = $conn->prepare($sql);
    $likePattern = $prefix . '%';
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    return $prefix . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

closeDBConnection($conn);
