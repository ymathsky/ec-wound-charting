<?php
// Filename: ec/api/upload_chat_file.php
// PHP endpoint to handle file uploads for the chat feature
session_start();
header('Content-Type: application/json');

// Disable display errors to prevent HTML injection in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

include_once '../db_connect.php';

// --- CORRECTED SESSION VARIABLE USAGE ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Expected 'ec_user_id' session variable."]);
    exit;
}
// ----------------------------------------

// Check for POST size limit violation (File too big for server settings)
if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $max_size = ini_get('post_max_size');
    echo json_encode(["status" => "error", "message" => "File exceeds server POST limit of $max_size."]);
    exit;
}

if (!isset($_FILES['chat_file']) || $_FILES['chat_file']['error'] !== UPLOAD_ERR_OK) {
    $error_code = $_FILES['chat_file']['error'] ?? 'No file sent';
    $message = "Upload failed. Error code: $error_code";
    
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
            break;
        case UPLOAD_ERR_FORM_SIZE:
            $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
            break;
        case UPLOAD_ERR_PARTIAL:
            $message = "The uploaded file was only partially uploaded";
            break;
        case UPLOAD_ERR_NO_FILE:
            $message = "No file was uploaded";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $message = "Missing a temporary folder";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $message = "Failed to write file to disk";
            break;
        case UPLOAD_ERR_EXTENSION:
            $message = "File upload stopped by extension";
            break;
    }
    
    echo json_encode(["status" => "error", "message" => $message]);
    exit;
}

$uploaded_file = $_FILES['chat_file'];
$sender_id = $_SESSION['ec_user_id'];
$recipient_id = $_POST['recipient_id'] ?? null;

// Use absolute path for reliability
$base_dir = dirname(__DIR__); // Parent of 'api' folder
$upload_dir = $base_dir . '/uploads/chat_files/';

// Create directory if it doesn't exist
if (!is_dir($upload_dir)) {
    // Attempt to create directory with appropriate permissions (0755 is usually safer/better than 0777 on shared hosting)
    if (!mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        $error = error_get_last();
        echo json_encode(["status" => "error", "message" => "Failed to create upload directory. " . ($error['message'] ?? '')]);
        exit;
    }
}

// Security checks
$allowed_mimes = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf',
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
];

$file_mime = $uploaded_file['type'];
$file_size = $uploaded_file['size']; // in bytes, e.g., max 5MB
$max_file_size = 5 * 1024 * 1024;

if (!in_array($file_mime, $allowed_mimes)) {
    echo json_encode(["status" => "error", "message" => "Invalid file type: " . $file_mime]);
    exit;
}

if ($file_size > $max_file_size) {
    echo json_encode(["status" => "error", "message" => "File size exceeds 5MB limit."]);
    exit;
}

// Sanitize and create a unique filename
$original_filename = basename($uploaded_file['name']);
$file_extension = pathinfo($original_filename, PATHINFO_EXTENSION);
$unique_filename = md5(time() . $original_filename) . '.' . $file_extension;
$destination_path = $upload_dir . $unique_filename;

if (move_uploaded_file($uploaded_file['tmp_name'], $destination_path)) {
    // File successfully moved, construct URL
    $file_url = 'uploads/chat_files/' . $unique_filename;

    echo json_encode([
        "status" => "success",
        "file_url" => $file_url,
        "original_filename" => $original_filename,
        "mime_type" => $file_mime,
        "message" => "File uploaded successfully."
    ]);

} else {
    $error = error_get_last();
    echo json_encode(["status" => "error", "message" => "Failed to move uploaded file. " . ($error['message'] ?? '')]);
}

$conn->close();
?>