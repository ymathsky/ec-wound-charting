<?php
// Filename: ec/api/process_reset.php

header('Content-Type: application/json');
require_once '../db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['token'], $data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing token or new password.']);
    exit;
}

$token = $data['token'];
$new_password = $data['new_password'];

// Basic server-side strength check
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
    exit;
}

try {
    // 1. Fetch token and check validity
    $check_sql = "SELECT user_id, expires_at FROM password_resets WHERE token = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $token);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $reset_record = $result->fetch_assoc();
    $check_stmt->close();

    if (!$reset_record) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid reset token.']);
        exit;
    }

    $current_time = new DateTime();
    $expiry_time = new DateTime($reset_record['expires_at']);

    if ($current_time > $expiry_time) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Reset token has expired. Please request a new link.']);
        // Clean up expired token
        $delete_expired_sql = "DELETE FROM password_resets WHERE token = ?";
        $delete_expired_stmt = $conn->prepare($delete_expired_sql);
        $delete_expired_stmt->bind_param("s", $token);
        $delete_expired_stmt->execute();
        $delete_expired_stmt->close();
        exit;
    }

    $user_id = $reset_record['user_id'];

    // 2. Securely Hash and Update New Password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // CRITICAL FIX: Changed 'password' to 'password_hash' for consistency with users table.
    $update_sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);

    if ($update_stmt->execute()) {

        // 3. Delete the used token (critical security step)
        $delete_sql = "DELETE FROM password_resets WHERE user_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully!']);
    } else {
        http_response_code(500);
        error_log("Database error resetting password for user_id $user_id: " . $update_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error. Could not reset password.']);
    }

    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in process_reset.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>