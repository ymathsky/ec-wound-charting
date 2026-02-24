<?php
// Filename: ec/api/send_message.php
// API to send a chat message, including files, to an individual or all users (broadcast)

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
$sender_id = $_SESSION['ec_user_id'];
$recipient_id = $_POST['recipient_id'] ?? null; // NULL for "All Users"
$message_text = trim($_POST['message_text'] ?? '');
$file_path = $_POST['file_path'] ?? null;
$file_type = $_POST['file_type'] ?? null;
$file_name = $_POST['file_name'] ?? null;

// Validate input
if (empty($message_text) && empty($file_path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message content or file is required.']);
    exit();
}

// Ensure message_text is set to NULL if empty, as message might be file-only
$message_text = empty($message_text) ? null : $message_text;

// Convert recipient_id to integer or keep as NULL
if (!empty($recipient_id)) {
    $recipient_id = (int)$recipient_id;
} else {
    // Treat empty recipient_id as a broadcast (NULL in DB)
    $recipient_id = null;
}

try {
    $sql = "INSERT INTO chat_messages (sender_id, recipient_id, message_text, file_path, file_type, file_name, sent_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $success = $stmt->execute([
        $sender_id,
        $recipient_id,
        $message_text,
        $file_path,
        $file_type,
        $file_name
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to insert message into database.']);
    }

} catch (PDOException $e) {
    error_log("Database Error in send_message.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}