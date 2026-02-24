<?php
// Filename: api/edit_chat_message.php
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $message_id = isset($input['message_id']) ? intval($input['message_id']) : 0;
    $new_text = isset($input['message_text']) ? trim($input['message_text']) : '';
    $current_user_id = $_SESSION['ec_user_id'];

    if ($message_id <= 0 || empty($new_text)) {
        throw new Exception('Invalid input');
    }

    // Verify ownership and update
    // Only allow editing text messages for now, or update text of any message
    $sql = "UPDATE chat_messages 
            SET message_text = ?, edited_at = NOW() 
            WHERE message_id = ? AND sender_id = ? AND is_deleted = 0";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $new_text, $message_id, $current_user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Message not found, permission denied, or no change made']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
