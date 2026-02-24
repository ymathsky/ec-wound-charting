<?php
// Filename: api/upload_wound_photo.php

header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized.']);
    exit();
}

// --- Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['message' => 'Only POST method is accepted.']);
    exit();
}

if (empty($_POST['wound_id']) || empty($_POST['image_type']) || empty($_FILES['wound_photo'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['message' => 'Missing required fields: wound_id, image_type, and wound_photo are required.']);
    exit();
}

$wound_id = intval($_POST['wound_id']);
$image_type = htmlspecialchars(strip_tags($_POST['image_type']));

// --- Authorization Check ---
if ($_SESSION['ec_role'] === 'facility') {
    $sql_check = "SELECT p.facility_id 
                  FROM wounds w 
                  JOIN patients p ON w.patient_id = p.patient_id 
                  WHERE w.wound_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $wound_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $check_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$check_data || $check_data['facility_id'] != $_SESSION['ec_user_id']) {
        http_response_code(403);
        echo json_encode(['message' => 'Forbidden. You do not have access to this wound.']);
        exit();
    }
}

// --- CRITICAL CHANGE: Get assessment_id AND appointment_id from post data ---
$assessment_id = isset($_POST['assessment_id']) && !empty($_POST['assessment_id']) ? intval($_POST['assessment_id']) : null;
$appointment_id = isset($_POST['appointment_id']) && !empty($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;


// --- File Upload Handling ---
$photo = $_FILES['wound_photo'];
if ($photo['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['message' => 'Error during file upload. Code: ' . $photo['error']]);
    exit();
}

// --- SECURITY: Validate MIME type from actual file content, not extension/header ---
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $photo['tmp_name']);
finfo_close($finfo);
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mimes)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.']);
    exit();
}

// Map MIME to safe extension (ignore user-supplied extension entirely)
$mime_to_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$safe_extension = $mime_to_ext[$mime_type];

$upload_dir = '../uploads/wound_images/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate a unique filename to prevent collisions
// Use MIME-validated safe extension instead of user-supplied extension
$prefix = $assessment_id ? 'asm_' . $assessment_id : 'wound_' . $wound_id;
$unique_filename = uniqid($prefix . '_', true) . '.' . $safe_extension;
$target_path = $upload_dir . $unique_filename;

// Move the uploaded file to the target directory
if (!move_uploaded_file($photo['tmp_name'], $target_path)) {
    http_response_code(500);
    echo json_encode(['message' => 'Failed to save the uploaded file.']);
    exit();
}

// --- Database Insertion ---
// We store the relative path from the web root for use in <img> tags
$db_path = 'uploads/wound_images/' . $unique_filename;

try {
    // --- CRITICAL CHANGE: Include appointment_id in SQL ---
    $sql = "INSERT INTO wound_images (wound_id, assessment_id, appointment_id, image_path, image_type) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Use 'iiiss' for bind_param (int, int, int, string, string)
    // Note: The integers must be handled carefully when they can be null. For simplicity in the PHP context, we pass the integer value.
    $stmt->bind_param("iiiss",
        $wound_id,
        $assessment_id,
        $appointment_id, // NEW BINDING
        $db_path,
        $image_type
    );

    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode([
            'message' => 'Photo uploaded successfully.',
            'image_path' => $db_path
        ]);
    } else {
        // If DB insert fails, attempt to delete the orphaned file
        unlink($target_path);
        throw new Exception("Database insertion failed.");
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['message' => 'Failed to save photo record to the database.', 'error' => $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>
