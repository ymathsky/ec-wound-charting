<?php
// Filename: ec/api/update_user_profile.php

// 1. Configuration and Session Management
header('Content-Type: application/json');
session_start();

// Ensure the user is logged in using the custom session key
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

require_once '../db_connect.php'; // Database connection
$user_id = $_SESSION['ec_user_id']; // Use the correct session key

// 2. Input Handling and Sanitization
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['full_name'], $data['email'], $data['credentials'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required profile fields.']);
    exit;
}

// Sanitize inputs
$full_name = htmlspecialchars(trim($data['full_name']));
$email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
$credentials = htmlspecialchars(trim($data['credentials']));

// Server-side validation
if (empty($full_name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid or missing name or email format.']);
    exit;
}

try {
    // 3. Check for Email Duplication (if email is changing)
    $check_email_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
    $check_email_stmt = $conn->prepare($check_email_sql);
    $check_email_stmt->bind_param("si", $email, $user_id);
    $check_email_stmt->execute();
    $check_email_result = $check_email_stmt->get_result();

    if ($check_email_result->num_rows > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'This email is already registered to another user.']);
        $check_email_stmt->close();
        exit;
    }
    $check_email_stmt->close();

    // 4. Update Database
    $update_sql = "UPDATE users 
                   SET full_name = ?, email = ?, credentials = ? 
                   WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssi", $full_name, $email, $credentials, $user_id);

    if ($update_stmt->execute()) {
        http_response_code(200);

        // Optionally update session variable for full name if needed in header/sidebar
        $_SESSION['ec_full_name'] = $full_name;

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully.']);
    } else {
        http_response_code(500);
        error_log("Database error updating profile for user_id $user_id: " . $update_stmt->error);
        echo json_encode(['success' => false, 'message' => 'A database error occurred during profile update.']);
    }

    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in update_user_profile.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>