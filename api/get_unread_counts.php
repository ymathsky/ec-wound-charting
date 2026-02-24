<?php
// Filename: ec/api/get_unread_counts.php
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['ec_user_id'];

// 1. Update current user's last_active_at (Safely)
$update_sql = "UPDATE users SET last_active_at = NOW() WHERE user_id = ?";
$update_stmt = $conn->prepare($update_sql);
if ($update_stmt) {
    $update_stmt->bind_param("i", $current_user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// 2. Get list of online users (active in last 30 seconds)
$online_users = [];
$online_sql = "SELECT user_id FROM users WHERE TIMESTAMPDIFF(SECOND, last_active_at, NOW()) < 30 AND user_id != ?";
$online_stmt = $conn->prepare($online_sql);
if ($online_stmt) {
    $online_stmt->bind_param("i", $current_user_id);
    $online_stmt->execute();
    $online_result = $online_stmt->get_result();

    while ($row = $online_result->fetch_assoc()) {
        $online_users[] = (int)$row['user_id'];
    }
    $online_stmt->close();
}

// 3. Count unread messages for the current user, grouped by sender
// We join with chat_rooms to ensure the current user is a participant (recipient)
$counts = [];
$sql = "SELECT m.sender_id, COUNT(*) as unread_count 
        FROM chat_messages m
        JOIN chat_rooms r ON m.room_id = r.room_id
        WHERE (r.user1_id = ? OR r.user2_id = ?)
          AND m.sender_id != ?
          AND m.read_at IS NULL 
        GROUP BY m.sender_id";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $counts[$row['sender_id']] = (int)$row['unread_count'];
    }
    $stmt->close();
}

echo json_encode([
    'status' => 'success', 
    'counts' => $counts,
    'online_users' => $online_users
]);

$conn->close();
?>
