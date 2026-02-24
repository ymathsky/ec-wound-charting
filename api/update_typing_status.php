<?php
// Filename: api/update_typing_status.php
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $current_user_id = $_SESSION['ec_user_id'];
    
    // Support both JSON and POST
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        $input = $_POST;
    }

    $recipient_id = isset($input['recipient_id']) ? intval($input['recipient_id']) : 0;
    
    file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " Update request: User $current_user_id -> Recipient $recipient_id\n", FILE_APPEND);

    // Determine status from 'status' (string) or 'is_typing' (boolean/int)
    if (isset($input['is_typing'])) {
        $status = $input['is_typing'] == 1 ? 'typing' : 'stopped';
    } else {
        $status = isset($input['status']) ? $input['status'] : 'typing';
    }

    if ($recipient_id <= 0) {
        throw new Exception('Invalid recipient');
    }

    // Find room ID (reusing logic from get_chat_messages or similar)
    // Ideally this should be passed from frontend if known, but looking it up is safer
    $room_sql = "SELECT room_id FROM chat_rooms 
                 WHERE (user1_id = ? AND user2_id = ?) 
                    OR (user1_id = ? AND user2_id = ?) 
                 LIMIT 1";
    $stmt = $conn->prepare($room_sql);
    $stmt->bind_param("iiii", $current_user_id, $recipient_id, $recipient_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $room_id = $row['room_id'];

        if ($status === 'stopped') {
            // Remove typing status
            $del_sql = "DELETE FROM chat_typing_status WHERE user_id = ? AND room_id = ?";
            $del_stmt = $conn->prepare($del_sql);
            $del_stmt->bind_param("ii", $current_user_id, $room_id);
            $del_stmt->execute();
            file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " User $current_user_id stopped typing in room $room_id\n", FILE_APPEND);
        } else {
            // Update typing status (Insert or Update)
            $upsert_sql = "INSERT INTO chat_typing_status (user_id, room_id, last_typed_at) 
                           VALUES (?, ?, NOW()) 
                           ON DUPLICATE KEY UPDATE last_typed_at = NOW()";
            $upsert_stmt = $conn->prepare($upsert_sql);
            $upsert_stmt->bind_param("ii", $current_user_id, $room_id);
            $upsert_stmt->execute();
            file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " User $current_user_id typing in room $room_id\n", FILE_APPEND);
        }
        
        echo json_encode(['status' => 'success']);
    } else {
        // Room doesn't exist yet, so typing status is irrelevant
        file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " No room found for $current_user_id and $recipient_id\n", FILE_APPEND);
        echo json_encode(['status' => 'success', 'message' => 'No room']);
    }

} catch (Exception $e) {
    file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
