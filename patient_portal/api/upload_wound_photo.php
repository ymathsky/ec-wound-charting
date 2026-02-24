<?php
// Filename: ec/patient_portal/api/upload_wound_photo.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

if (!isset($_SESSION['portal_patient_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$patient_id = $_SESSION['portal_patient_id'];

// Check if file was uploaded
if (!isset($_FILES['wound_photo']) || $_FILES['wound_photo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["message" => "No image file selected or upload error."]);
    exit();
}

$wound_id = isset($_POST['wound_id']) ? intval($_POST['wound_id']) : 0;

if ($wound_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid wound selection."]);
    exit();
}

// Security: Verify this wound actually belongs to this patient
$check_sql = "SELECT wound_id FROM wounds WHERE wound_id = ? AND patient_id = ?";
$stmt = $conn->prepare($check_sql);
$stmt->bind_param("ii", $wound_id, $patient_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    echo json_encode(["message" => "Invalid wound ID for this patient."]);
    exit();
}
$stmt->close();

// Process Upload
// Store in a specific subfolder for patient uploads to keep them organized
$upload_dir = '../../uploads/patient_uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// SECURITY: Validate MIME type from actual file content, not extension/header
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $_FILES['wound_photo']['tmp_name']);
finfo_close($finfo);
$allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
$mime_to_ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if (!in_array($mime_type, $allowed_mimes)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid file type. Only JPG, PNG, and WebP allowed."]);
    exit();
}

// Use MIME-derived safe extension (ignore user-supplied extension entirely)
$ext = $mime_to_ext[$mime_type];

// Generate secure filename
$new_filename = uniqid("p_upload_{$patient_id}_{$wound_id}_") . '.' . $ext;
$target_path = $upload_dir . $new_filename;
// Path relative to root for DB storage
$db_path = 'uploads/patient_uploads/' . $new_filename;

try {
    if (move_uploaded_file($_FILES['wound_photo']['tmp_name'], $target_path)) {

        // INSERT INTO NEW TABLE (patient_wound_photos)
        $sql = "INSERT INTO patient_wound_photos (patient_id, wound_id, image_path, uploaded_at) VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $patient_id, $wound_id, $db_path);

        if ($stmt->execute()) {

            // Notification Logic
            $note_body = "Patient uploaded a new monitoring photo for wound ID #$wound_id.";
            $msg_sql = "INSERT INTO patient_messages (patient_id, direction, subject, body, is_read) VALUES (?, 'inbound', 'New Wound Photo Uploaded', ?, 0)";
            $msg_stmt = $conn->prepare($msg_sql);
            if($msg_stmt) {
                $msg_stmt->bind_param("is", $patient_id, $note_body);
                $msg_stmt->execute();
                $msg_stmt->close();
            }

            echo json_encode(["message" => "Photo uploaded successfully."]);
        } else {
            throw new Exception("Database insert failed.");
        }
        $stmt->close();
    } else {
        throw new Exception("Failed to move uploaded file.");
    }
} catch (Exception $e) {
    error_log("patient portal upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["message" => "A server error occurred. Please try again."]);
}

$conn->close();
?>