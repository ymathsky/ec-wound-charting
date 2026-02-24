<?php
// Filename: ec/api/update_password.php

// 1. Configuration and Session Management
header('Content-Type: application/json');
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

require_once '../db_connect.php'; // Database connection
$user_id = $_SESSION['ec_user_id']; // Use the correct session key

// 2. Input Handling and Validation
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['current_password'], $data['new_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing password fields.']);
    exit;
}

$current_password = $data['current_password'];
$new_password = $data['new_password'];

// Basic server-side validation (Client-side already checks length)
if (strlen($new_password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long.']);
    exit;
}

try {
    // 3. Retrieve current hashed password from database
    // NOTE: Assuming your password column is named 'password_hash' based on the users table structure.
    $sql = "SELECT password_hash FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // User somehow logged in but record not found (shouldn't happen, but safe check)
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit;
    }

    $stored_hash = $user['password_hash'];

    // 4. Verify current password
    if (!password_verify($current_password, $stored_hash)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'The current password you entered is incorrect.']);
        exit;
    }

    // 5. Hash new password and update database
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $update_sql = "UPDATE users SET password_hash = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Password updated successfully.']);
    } else {
        http_response_code(500);
        error_log("Database error updating password for user_id $user_id: " . $update_stmt->error);
        echo json_encode(['success' => false, 'message' => 'A database error occurred during password update.']);
    }

    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in update_password.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>