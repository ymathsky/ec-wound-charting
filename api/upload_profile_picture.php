<?php
// Filename: ec/api/upload_profile_picture.php

header('Content-Type: application/json');
session_start();

// 1. Configuration and Authentication
// Ensure the user is logged in using the custom session key
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

require_once '../db_connect.php';
$user_id = $_SESSION['ec_user_id']; // Use the correct session key
$upload_dir = '../uploads/profile_pictures/';
$base_url = 'uploads/profile_pictures/'; // URL path relative to the ec/ directory

// IMPORTANT: Check if the upload directory exists and is writable.
if (!is_dir($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upload directory does not exist and could not be created.']);
        exit;
    }
}

// 2. File Upload Validation
if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No file uploaded or file upload error occurred.']);
    exit;
}

$file = $_FILES['profile_picture'];
$max_size = 5 * 1024 * 1024; // 5 MB limit
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
$mime_type = mime_content_type($file['tmp_name']);

// Validate file type
if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

// Validate file size
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File size exceeds the 5MB limit.']);
    exit;
}

try {
    // 3. Delete Old Picture (Cleanup)
    // Fetch current image URL first
    $current_img_sql = "SELECT profile_image_url FROM users WHERE user_id = ?";
    $current_img_stmt = $conn->prepare($current_img_sql);
    $current_img_stmt->bind_param("i", $user_id);
    $current_img_stmt->execute();
    $result = $current_img_stmt->get_result();
    $old_path = $result->fetch_assoc()['profile_image_url'];
    $current_img_stmt->close();

    if ($old_path && strpos($old_path, $base_url) !== false) {
        // Construct physical path relative to this script's location
        $physical_old_path = '../' . $old_path;
        if (file_exists($physical_old_path) && is_file($physical_old_path)) {
            unlink($physical_old_path); // Delete the physical file
        }
    }

    // 4. Move New File to Permanent Location
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    // Generate a unique, secure filename based on user ID and a unique ID
    $unique_filename = $user_id . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $unique_filename;

    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $target_file)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move the uploaded file. Check directory permissions.']);
        exit;
    }

    // Construct the URL path to save in the database
    $new_image_url = $base_url . $unique_filename;

    // 5. Update Database Record
    $update_sql = "UPDATE users SET profile_image_url = ? WHERE user_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("si", $new_image_url, $user_id);

    if ($update_stmt->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Profile picture uploaded successfully.', 'image_url' => $new_image_url]);
    } else {
        // If DB update fails, delete the uploaded file to prevent orphans
        unlink($target_file);
        http_response_code(500);
        error_log("DB error: " . $update_stmt->error);
        echo json_encode(['success' => false, 'message' => 'Database error updating image path.']);
    }

    $update_stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("General upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred during upload.']);
}

$conn->close();
?>