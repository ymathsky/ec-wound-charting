<?php
// Filename: ec/api/get_user_list_for_chat.php
// This API fetches all active users for the chat list,
// excluding the currently logged-in user.

session_start();
header('Content-Type: application/json');

// --- Include Dependencies ---
include_once '../db_connect.php';

// --- Authorization Check ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: User not logged in."]);
    exit;
}

$current_user_id = $_SESSION['ec_user_id'];

// --- Database Query ---
// Updated to select the correct columns based on your 'users (3).sql' schema:
// - full_name
// - profile_image_url
// - last_active_at (restored)
$sql = "SELECT user_id, full_name, profile_image_url, last_active_at 
        FROM users 
        WHERE user_id != ? 
        AND status = 'active'
        ORDER BY full_name ASC";

try {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("i", $current_user_id);

    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $users = [];

    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode(["status" => "success", "users" => $users]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>