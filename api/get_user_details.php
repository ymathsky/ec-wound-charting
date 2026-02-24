<?php
// Filename: ec/api/get_user_details.php

header('Content-Type: application/json');
session_start(); // CRITICAL: Start session immediately

// If the user is not logged in, return 401 immediately
if (!isset($_SESSION['ec_user_id'])) { // Checks your custom session key
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

require_once '../db_connect.php';
$user_id = $_SESSION['ec_user_id']; // Uses the correct session key for user ID

try {
// 1. Fetch user details, profile image URL, and credentials
    // Note: 'role' is aliased as 'user_type' for consistency with front-end display
    $sql = "SELECT user_id, full_name, email, role AS user_type, profile_image_url, credentials 
            FROM users 
            WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User profile data not found.']);
        exit;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Convert DB nulls to explicit empty strings for safe JavaScript handling
    $user['profile_image_url'] = $user['profile_image_url'] ?? '';
    $user['credentials'] = $user['credentials'] ?? '';

    http_response_code(200);
    echo json_encode($user);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Database error in get_user_details.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A server error occurred.']);
}

$conn->close();
?>