<?php
/**
 * Get chatbot conversation history
 */

require_once '../config.php';

header('Content-Type: application/json');

$conn = getDBConnection();

$session_id = $_GET['session_id'] ?? session_id() ?? null;
$limit = intval($_GET['limit'] ?? 50);

if (!$session_id) {
    sendSuccessResponse(['conversations' => []]);
}

$sql = "SELECT * FROM chatbot_conversations 
        WHERE session_id = ? 
        ORDER BY created_at ASC 
        LIMIT ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $session_id, $limit);
$stmt->execute();
$result = $stmt->get_result();

$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = [
        'id' => $row['id'],
        'message' => $row['message'],
        'response' => $row['response'],
        'intent' => $row['intent'],
        'source' => $row['source'],
        'created_at' => $row['created_at']
    ];
}

sendSuccessResponse(['conversations' => $conversations]);
