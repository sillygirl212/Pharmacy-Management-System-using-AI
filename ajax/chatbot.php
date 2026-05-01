<?php
/**
 * AI Chatbot AJAX Endpoint
 */

require_once '../config/ai-chatbot.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$session_id = isset($_POST['session_id']) ? $_POST['session_id'] : null;

if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

$user_id = isLoggedIn() ? getCurrentUserId() : null;

// Initialize chatbot
$chatbot = new PharmacyAIChatbot($user_id, $session_id);

// Process message
$result = $chatbot->processMessage($message);

echo json_encode([
    'success' => true,
    'response' => $result['response'],
    'intent' => $result['intent'],
    'session_id' => $result['session_id']
]);
?>
