<?php
// Filename: ec/api/upload_document.php
// Handles file upload and records document metadata in the database.

require_once(__DIR__ . '/../db_connect.php');

// Set JSON header for API response
header('Content-Type: application/json');

// Function to respond with JSON and exit
function respond($success, $message, $code = 200) {
    http_response_code($code);
    echo json_encode(["success" => $success, "message" => $message]);
    exit();
}

// Check for session/user context (assuming user_id is in session)
session_start();
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : NULL;

// 1. Basic POST data validation
if (!isset($_POST['patient_id']) || !isset($_POST['document_type']) || !isset($_FILES['patient_document'])) {
    respond(false, "Missing required fields or file.", 400);
}

$patient_id = filter_var($_POST['patient_id'], FILTER_VALIDATE_INT);
$document_type = trim($_POST['document_type']);
$upload_date = date('Y-m-d'); // Use current date for upload date

if ($patient_id === false || $patient_id <= 0) {
    respond(false, "Invalid Patient ID.", 400);
}

// 2. File Upload Handling
$file = $_FILES['patient_document'];
$target_dir = "../uploads/patient_documents/";
$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Simple file validation
$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
if (!in_array($file_extension, $allowed_ext)) {
    respond(false, "Invalid file type. Only PDF, DOC, DOCX, JPG, JPEG, PNG are allowed.", 400);
}

// Create unique file name
$file_name_db = "doc_" . $patient_id . "_" . uniqid() . "." . $file_extension;
$target_file = $target_dir . $file_name_db;
$file_path_db = "uploads/patient_documents/" . $file_name_db; // Path used in DB

// Create directory if it doesn't exist
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

if (!move_uploaded_file($file["tmp_name"], $target_file)) {
    respond(false, "Failed to move uploaded file.", 500);
}

// 3. Database Insertion
$sql = "INSERT INTO patient_documents (patient_id, user_id, file_name, file_path, document_type, upload_date) 
        VALUES (?, ?, ?, ?, ?, ?)";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("iissss", $patient_id, $user_id, $file['name'], $file_path_db, $document_type, $upload_date);

    if ($stmt->execute()) {
        respond(true, "Document uploaded successfully.", 201);
    } else {
        // Delete the file if DB insertion fails
        unlink($target_file);
        respond(false, "DB Error: Could not record document metadata. " . $stmt->error, 500);
    }
    $stmt->close();
} else {
    // Delete the file if preparation fails
    unlink($target_file);
    respond(false, "DB Error: Could not prepare statement.", 500);
}

$conn->close();
?>