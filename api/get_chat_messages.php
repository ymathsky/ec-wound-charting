<?php
// Filename: api/get_chat_messages.php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $current_user_id = $_SESSION['ec_user_id'];
    $other_user_id = isset($_GET['recipient_id']) ? intval($_GET['recipient_id']) : 0;

    if ($other_user_id === 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid recipient']);
        exit;
    }

    // 1. Find the room ID
    $room_sql = "SELECT room_id FROM chat_rooms 
                 WHERE (user1_id = ? AND user2_id = ?) 
                    OR (user1_id = ? AND user2_id = ?) 
                 LIMIT 1";
    $stmt = $conn->prepare($room_sql);
    if (!$stmt) {
        error_log("Chat API - Prepare failed: " . $conn->error);
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param("iiii", $current_user_id, $other_user_id, $other_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $room_id = $row['room_id'];

        // 2. Fetch messages
        // Parameters for pagination/polling
        $before_id = isset($_GET['before_id']) ? intval($_GET['before_id']) : 0;
        $after_id = isset($_GET['after_id']) ? intval($_GET['after_id']) : 0;
        $limit = 20; // Default page size

        $msg_sql = "SELECT m.*, u.full_name as sender_name, u.profile_image_url as sender_pic,
                           q.message_text as quoted_text, q.message_type as quoted_type, 
                           q.file_name as quoted_file_name, qu.full_name as quoted_sender_name
                    FROM chat_messages m
                    JOIN users u ON m.sender_id = u.user_id
                    LEFT JOIN chat_messages q ON m.reply_to_id = q.message_id
                    LEFT JOIN users qu ON q.sender_id = qu.user_id
                    WHERE m.room_id = ?";
        
        $params = [$room_id];
        $types = "i";

        if ($before_id > 0) {
            // Load history: messages older than before_id
            $msg_sql .= " AND m.message_id < ?";
            $params[] = $before_id;
            $types .= "i";
            $msg_sql .= " ORDER BY m.sent_at DESC LIMIT ?";
            $params[] = $limit;
            $types .= "i";
        } elseif ($after_id > 0) {
            // Polling: messages newer than after_id
            $msg_sql .= " AND m.message_id > ?";
            $params[] = $after_id;
            $types .= "i";
            $msg_sql .= " ORDER BY m.sent_at ASC"; // Get all new messages
        } else {
            // Initial load: latest N messages
            $msg_sql .= " ORDER BY m.sent_at DESC LIMIT ?";
            $params[] = $limit;
            $types .= "i";
        }
        
        $msg_stmt = $conn->prepare($msg_sql);
        if (!$msg_stmt) throw new Exception("Prepare msg failed: " . $conn->error);

        $msg_stmt->bind_param($types, ...$params);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        
        $messages = [];
        $message_ids = [];
        while ($msg = $msg_result->fetch_assoc()) {
            $msg['reactions'] = []; // Initialize reactions array
            $messages[] = $msg;
            $message_ids[] = $msg['message_id'];
        }

        // If we fetched history or initial load (DESC), we need to reverse to get chronological order
        if ($after_id == 0) {
            $messages = array_reverse($messages);
            $message_ids = array_reverse($message_ids);
        }

        // 2.5 Fetch Reactions
        if (!empty($message_ids)) {
            $ids_str = implode(',', $message_ids);
            // Fetch all reactions for these messages
            $react_sql = "SELECT r.message_id, r.emoji, r.user_id, u.full_name 
                          FROM chat_reactions r
                          JOIN users u ON r.user_id = u.user_id
                          WHERE r.message_id IN ($ids_str)";
            $react_res = $conn->query($react_sql);
            
            $reactions_map = [];
            while ($r = $react_res->fetch_assoc()) {
                $mid = $r['message_id'];
                if (!isset($reactions_map[$mid])) {
                    $reactions_map[$mid] = [];
                }
                $reactions_map[$mid][] = [
                    'emoji' => $r['emoji'],
                    'user_id' => $r['user_id'],
                    'user_name' => $r['full_name']
                ];
            }

            // Attach to messages
            foreach ($messages as &$m) {
                if (isset($reactions_map[$m['message_id']])) {
                    $m['reactions'] = $reactions_map[$m['message_id']];
                }
            }
        }

        // 3. Check if the OTHER user is typing
        // We consider them typing if last_typed_at is within the last 4 seconds
        // We use TIMESTAMPDIFF in SQL to avoid PHP/MySQL timezone mismatches
        $typing_sql = "SELECT (TIMESTAMPDIFF(SECOND, last_typed_at, NOW()) < 4) as is_typing 
                       FROM chat_typing_status 
                       WHERE user_id = ? AND room_id = ?";
        $typing_stmt = $conn->prepare($typing_sql);
        $typing_stmt->bind_param("ii", $other_user_id, $room_id);
        $typing_stmt->execute();
        $typing_result = $typing_stmt->get_result();
        
        $is_typing = false;
        if ($t_row = $typing_result->fetch_assoc()) {
            $is_typing = (bool)$t_row['is_typing'];
        }
        // file_put_contents('../debug_typing.log', date('Y-m-d H:i:s') . " Checking user $other_user_id in room $room_id: " . ($is_typing ? 'TYPING' : 'NOT TYPING') . "\n", FILE_APPEND);

        // 4. Get Last Read Message ID by the OTHER user (for read receipts)
        // We want to know the max message_id sent by ME that the OTHER user has read.
        $read_sql = "SELECT MAX(message_id) as last_read_id 
                     FROM chat_messages 
                     WHERE room_id = ? AND sender_id = ? AND read_at IS NOT NULL";
        $read_stmt = $conn->prepare($read_sql);
        $read_stmt->bind_param("ii", $room_id, $current_user_id);
        $read_stmt->execute();
        $read_res = $read_stmt->get_result();
        $last_read_id = 0;
        if ($r_row = $read_res->fetch_assoc()) {
            $last_read_id = $r_row['last_read_id'] ? intval($r_row['last_read_id']) : 0;
        }

        // 5. Get Recent Reactions (for live updates)
        // If after_id is set, we assume this is a poll.
        // We want reactions created recently. Since we don't pass a timestamp, 
        // let's just fetch all reactions for the last 50 messages in the room to be safe/simple?
        // Or better: Fetch reactions created in the last 5 seconds?
        // Let's try fetching reactions for visible messages if possible, but that's hard.
        // Let's return a list of ALL reactions for the last 20 messages if polling?
        // Actually, simpler: Just return the last_read_id is enough for read status.
        // For reactions, let's return reactions created in the last 10 seconds.
        
        $recent_reactions = [];
        if ($after_id > 0) {
            $recent_react_sql = "SELECT r.message_id, r.emoji, r.user_id, u.full_name 
                                 FROM chat_reactions r
                                 JOIN chat_messages m ON r.message_id = m.message_id
                                 JOIN users u ON r.user_id = u.user_id
                                 WHERE m.room_id = ? AND r.created_at > (NOW() - INTERVAL 10 SECOND)";
            $rr_stmt = $conn->prepare($recent_react_sql);
            $rr_stmt->bind_param("i", $room_id);
            $rr_stmt->execute();
            $rr_res = $rr_stmt->get_result();
            while ($rr = $rr_res->fetch_assoc()) {
                $recent_reactions[] = [
                    'message_id' => $rr['message_id'],
                    'emoji' => $rr['emoji'],
                    'user_id' => $rr['user_id'],
                    'user_name' => $rr['full_name'],
                    'action' => 'added' // We only track adds easily this way
                ];
            }
        }
        
        echo json_encode([
            'status' => 'success', 
            'messages' => $messages,
            'partner_typing' => $is_typing,
            'last_read_message_id' => $last_read_id,
            'recent_reactions' => $recent_reactions
        ]);

    } else {
        // No room exists yet, return empty array
        echo json_encode(['status' => 'success', 'messages' => []]);
    }

} catch (Exception $e) {
    error_log("Chat Messages API Error - User: " . ($_SESSION['ec_user_id'] ?? 'unknown') . ", Recipient: " . ($other_user_id ?? 'unknown') . ", Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    http_response_code($e->getCode() ?: 500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>
