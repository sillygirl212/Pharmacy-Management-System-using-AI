<?php
/**
 * Pharmacy Management System - Database Configuration
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Default XAMPP username
define('DB_PASS', '');          // Default XAMPP password (empty)
define('DB_NAME', 'pharmacy');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to handle special characters
    $conn->set_charset("utf8mb4");
    
    return $conn;
}

// Close database connection
function closeDBConnection($conn) {
    $conn->close();
}

// Helper function to send JSON response
function sendJSONResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper function to send error response
function sendErrorResponse($message, $statusCode = 400) {
    sendJSONResponse(['success' => false, 'message' => $message], $statusCode);
}

// Helper function to send success response
function sendSuccessResponse($data = null, $message = 'Success') {
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    sendJSONResponse($response);
}

// CORS headers for API access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
