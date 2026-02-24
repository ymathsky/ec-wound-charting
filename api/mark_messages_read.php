<?php
// Filename: api/mark_messages_read.php
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        exit; // Silent fail
    }

    $current_user_id = $_SESSION['ec_user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $sender_id = isset($input['sender_id']) ? intval($input['sender_id']) : 0;

    if ($sender_id === 0) exit;

    // Find the room
    $room_sql = "SELECT room_id FROM chat_rooms 
                 WHERE (user1_id = ? AND user2_id = ?) 
                    OR (user1_id = ? AND user2_id = ?) 
                 LIMIT 1";
    $stmt = $conn->prepare($room_sql);
    if (!$stmt) throw new Exception("Prepare failed");

    $stmt->bind_param("iiii", $current_user_id, $sender_id, $sender_id, $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $room_id = $row['room_id'];
        
        // Update messages sent BY the other user in this room that are unread
        $update_sql = "UPDATE chat_messages 
                       SET read_at = NOW() 
                       WHERE room_id = ? 
                       AND sender_id = ? 
                       AND read_at IS NULL";
        
        $up_stmt = $conn->prepare($update_sql);
        if ($up_stmt) {
            $up_stmt->bind_param("ii", $room_id, $sender_id);
            $up_stmt->execute();
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Room not found']);
    }

} catch (Exception $e) {
    // Silent fail for read receipts usually
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
