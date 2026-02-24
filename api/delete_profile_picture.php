<?php
// Filename: ec/api/delete_profile_picture.php

header('Content-Type: application/json');
session_start();

// 1. Authentication Check (using the correct custom session key)
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

require_once '../db_connect.php';
$user_id = $_SESSION['ec_user_id'];
// Define paths relative to the root 'ec' directory
$base_url = 'uploads/profile_pictures/';
$defaultImageUrl = 'https://placehold.co/128x128/9CA3AF/FFFFFF?text=User';

try {
    // 2. Fetch the current image URL from the database
    $fetch_sql = "SELECT profile_image_url FROM users WHERE user_id = ?";
    $fetch_stmt = $conn->prepare($fetch_sql);
    $fetch_stmt->bind_param("i", $user_id);
    $fetch_stmt->execute();
    $result = $fetch_stmt->get_result();
    $current_record = $result->fetch_assoc();
    $fetch_stmt->close();

    $current_url = $current_record['profile_image_url'];

    // 3. Attempt to delete the physical file
    if ($current_url && strpos($current_url, $base_url) !== false) {
        // Construct the physical file path relative to the current API script location
        $physical_path = '../' . $current_url;

        if (file_exists($physical_path) && is_file($physical_path)) {
            if (!unlink($physical_path)) {
                // Log failure but continue to clear DB entry
                error_log("Failed to delete physical file: " . $physical_path);
            }
        }
    }

    // 4. Update the database entry (set URL to NULL)
    $update_sql = "UPDATE users SET profile_image_url = NULL WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $user_id);

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Profile picture successfully removed.',
            'default_url' => $defaultImageUrl
        ]);
    } else {
        http_response_code(500);
        error_log("Database error clearing profile_image_url for user_id $user_id: " . $update_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error during removal.']);
    }

    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("General error in delete_profile_picture.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
}

$conn->close();
?>