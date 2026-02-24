<?php
// Filename: api/delete_chat_message.php
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
    $current_user_id = $_SESSION['ec_user_id'];

    if ($message_id <= 0) {
        throw new Exception('Invalid message ID');
    }

    // Verify ownership and delete (soft delete)
    $sql = "UPDATE chat_messages SET is_deleted = 1 WHERE message_id = ? AND sender_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $message_id, $current_user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        // Could be already deleted or not owned by user
        echo json_encode(['status' => 'error', 'message' => 'Message not found or permission denied']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
