<?php
// Filename: api/send_chat_message.php
// Disable display_errors to prevent HTML error output from breaking JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    // Support both JSON and POST (FormData)
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($input)) {
        if (!empty($_POST)) {
            $input = $_POST;
        } else {
            // If both fail, throw error
            throw new Exception('Invalid input: Expected JSON or Form Data');
        }
    }

    $sender_id = $_SESSION['ec_user_id'];
    $recipient_id = isset($input['recipient_id']) ? intval($input['recipient_id']) : 0;
    $message_text = isset($input['message_text']) ? trim($input['message_text']) : '';
    $file_url = isset($input['file_url']) ? $input['file_url'] : null;
    $file_name = isset($input['file_name']) ? $input['file_name'] : null;
    $message_type = isset($input['message_type']) ? $input['message_type'] : 'text';
    $reply_to_id = isset($input['reply_to_id']) ? intval($input['reply_to_id']) : null;

    if ($recipient_id === 0 || (empty($message_text) && empty($file_url))) {
        throw new Exception('Invalid input: Missing recipient or message content');
    }

    // --- DEBUG LOGGING ---
    $log_entry = date('Y-m-d H:i:s') . " - Sending Message:\n";
    $log_entry .= "Sender: $sender_id, Recipient: $recipient_id\n";
    $log_entry .= "Text: $message_text, File: $file_url, Type: $message_type\n";
    file_put_contents('api_error_log.txt', $log_entry, FILE_APPEND);
    // ---------------------

    // 1. Get or Create Room
    $room_id = null;

    // Check if room exists
    $check_sql = "SELECT room_id FROM chat_rooms 
                  WHERE (user1_id = ? AND user2_id = ?) 
                     OR (user1_id = ? AND user2_id = ?) 
                  LIMIT 1";
    $stmt = $conn->prepare($check_sql);
    if (!$stmt) {
        $error = $conn->error;
        file_put_contents('api_error_log.txt', "Prepare failed (check room): $error\n", FILE_APPEND);
        throw new Exception("Prepare failed: " . $error);
    }
    
    $stmt->bind_param("iiii", $sender_id, $recipient_id, $recipient_id, $sender_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $room_id = $row['room_id'];
    } else {
        // Create new room
        $u1 = min($sender_id, $recipient_id);
        $u2 = max($sender_id, $recipient_id);
        
        $create_sql = "INSERT INTO chat_rooms (user1_id, user2_id) VALUES (?, ?)";
        $create_stmt = $conn->prepare($create_sql);
        if (!$create_stmt) {
            $error = $conn->error;
            file_put_contents('api_error_log.txt', "Prepare create failed: $error\n", FILE_APPEND);
            throw new Exception("Prepare create failed: " . $error);
        }
        
        $create_stmt->bind_param("ii", $u1, $u2);
        if ($create_stmt->execute()) {
            $room_id = $conn->insert_id;
        } else {
            $error = $create_stmt->error;
            file_put_contents('api_error_log.txt', "Failed to create room: $error\n", FILE_APPEND);
            throw new Exception('Failed to create room: ' . $error);
        }
    }

    // 2. Insert Message
    // Try inserting with reply_to_id first
    $insert_sql = "INSERT INTO chat_messages (room_id, sender_id, message_text, file_url, file_name, message_type, reply_to_id) 
                   VALUES (?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    
    $inserted = false;
    if ($insert_stmt) {
        $insert_stmt->bind_param("iissssi", $room_id, $sender_id, $message_text, $file_url, $file_name, $message_type, $reply_to_id);
        if ($insert_stmt->execute()) {
            $inserted = true;
        } else {
            $error = $insert_stmt->error;
            file_put_contents('api_error_log.txt', "Insert failed (with reply_to): $error\n", FILE_APPEND);
            
            // Check if error is due to missing column OR duplicate entry
            if (strpos($error, "Unknown column") !== false) {
                $insert_stmt = false; // Trigger fallback
            } elseif (strpos($error, "Duplicate entry") !== false) {
                 // This is the specific fix for "Duplicate entry '0' for key 'PRIMARY'"
                 // It means the message_id column is NOT set to AUTO_INCREMENT
                 file_put_contents('api_error_log.txt', "CRITICAL: message_id is not AUTO_INCREMENT. Attempting to fix...\n", FILE_APPEND);
                 throw new Exception('Database Error: message_id is not AUTO_INCREMENT. Please run the fix script.');
            } else {
                throw new Exception('Failed to send message: ' . $error);
            }
        }
    } else {
         file_put_contents('api_error_log.txt', "Prepare insert failed (with reply_to): " . $conn->error . "\n", FILE_APPEND);
    }

    // Fallback: Insert without reply_to_id if column missing
    if (!$inserted && !$insert_stmt) {
        $insert_sql = "INSERT INTO chat_messages (room_id, sender_id, message_text, file_url, file_name, message_type) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        if (!$insert_stmt) {
            $error = $conn->error;
            file_put_contents('api_error_log.txt', "Prepare insert fallback failed: $error\n", FILE_APPEND);
            throw new Exception("Prepare insert fallback failed: " . $error);
        }
        
        $insert_stmt->bind_param("iissss", $room_id, $sender_id, $message_text, $file_url, $file_name, $message_type);
        if ($insert_stmt->execute()) {
            $inserted = true;
        } else {
            $error = $insert_stmt->error;
            file_put_contents('api_error_log.txt', "Failed to send message (fallback): $error\n", FILE_APPEND);
            throw new Exception('Failed to send message (fallback): ' . $error);
        }
    }

    if ($inserted) {
        // 3. Update Room's last_message_at
        $update_room = "UPDATE chat_rooms SET last_message_at = NOW() WHERE room_id = ?";
        $up_stmt = $conn->prepare($update_room);
        if ($up_stmt) {
            $up_stmt->bind_param("i", $room_id);
            $up_stmt->execute();
        }

        // --- DEBUG LOGGING ---
        file_put_contents('api_error_log.txt', "Success: Message inserted into Room $room_id\n\n", FILE_APPEND);
        // ---------------------

        echo json_encode(['status' => 'success']);
    } else {
        // --- DEBUG LOGGING ---
        file_put_contents('api_error_log.txt', "Error: Insert failed - " . $insert_stmt->error . "\n\n", FILE_APPEND);
        // ---------------------
        throw new Exception('Failed to send message: ' . $insert_stmt->error);
    }

} catch (Exception $e) {
    // --- DEBUG LOGGING ---
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('api_error_log.txt', "[$timestamp] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents('api_error_log.txt', "RAW INPUT: " . file_get_contents('php://input') . "\n", FILE_APPEND);
    file_put_contents('api_error_log.txt', "POST DATA: " . print_r($_POST, true) . "\n\n", FILE_APPEND);
    // ---------------------
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
