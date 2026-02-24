<?php
// Filename: ec/api/get_messages.php
// API to retrieve chat messages for a specific recipient or the 'All Users' broadcast

session_start();
header('Content-Type: application/json');

// CORRECTED: Check for 'ec_user_id'
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit();
}

include_once '../db_connect.php';

// CORRECTED: Use 'ec_user_id'
$current_user_id = $_SESSION['ec_user_id'];
$recipient_id = $_GET['recipient_id'] ?? null; // Null or empty string for broadcast

// Prepare the base query with a JOIN to get sender username
$sql_base = "
    SELECT 
        cm.*, 
        u.username AS sender_username
    FROM chat_messages cm
    JOIN users u ON cm.sender_id = u.user_id
";

// Determine the WHERE clause based on recipient_id
if (empty($recipient_id)) {
    // 1. BROADCAST/ALL USERS messages
    // Recipient ID is NULL for broadcast messages. Everyone can read them.
    $sql_where = "WHERE cm.recipient_id IS NULL";
    $params = [];
} else {
    // 2. PRIVATE messages
    $recipient_id = (int)$recipient_id;

    // Messages are visible if:
    // a) I am the sender AND the recipient is the person I'm chatting with (recipient_id)
    // OR
    // b) I am the recipient (my user_id) AND the sender is the person I'm chatting with (recipient_id)
    $sql_where = "
        WHERE (
            (cm.sender_id = ? AND cm.recipient_id = ?) 
            OR 
            (cm.sender_id = ? AND cm.recipient_id = ?)
        )
    ";
    $params = [$current_user_id, $recipient_id, $recipient_id, $current_user_id];
}

// Complete the SQL query
$sql = $sql_base . " " . $sql_where . " ORDER BY cm.sent_at ASC LIMIT 100";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (PDOException $e) {
    error_log("Database Error in get_messages.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}