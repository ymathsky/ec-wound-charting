<?php
// Filename: api/delete_wound_photo.php
// Purpose: Deletes a specific wound image record from the database and removes the physical file from the server.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized."));
    exit();
}

// Decode JSON input
$data = json_decode(file_get_contents("php://input"));
$image_id = isset($data->image_id) ? intval($data->image_id) : 0;

if ($image_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid image ID provided."));
    exit();
}

try {
    // 1. Retrieve the image path and wound_id before deletion
    $sql_select = "SELECT wi.image_path, wi.wound_id, wi.appointment_id, p.facility_id 
                   FROM wound_images wi
                   JOIN wounds w ON wi.wound_id = w.wound_id
                   JOIN patients p ON w.patient_id = p.patient_id
                   WHERE wi.image_id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $image_id);
    $stmt_select->execute();
    $result_select = $stmt_select->get_result();
    $photo = $result_select->fetch_assoc();
    $stmt_select->close();

    if (!$photo) {
        http_response_code(404);
        echo json_encode(array("success" => false, "message" => "Image record not found in database."));
        exit();
    }

    // --- Authorization Check ---
    if ($_SESSION['ec_role'] === 'facility' && $_SESSION['ec_user_id'] != $photo['facility_id']) {
        http_response_code(403);
        echo json_encode(array("success" => false, "message" => "Forbidden. You do not have access to this photo."));
        exit();
    }

    // --- Check if Visit is Signed ---
    if (!empty($photo['appointment_id'])) {
        $appt_id = $photo['appointment_id'];
        $sql_check_signed = "SELECT is_signed FROM visit_notes WHERE appointment_id = ?";
        $stmt_check = $conn->prepare($sql_check_signed);
        $stmt_check->bind_param("i", $appt_id);
        $stmt_check->execute();
        $res_check = $stmt_check->get_result();
        if ($row_check = $res_check->fetch_assoc()) {
            if ($row_check['is_signed']) {
                http_response_code(403);
                echo json_encode(array("success" => false, "message" => "Cannot delete photo. The visit is already signed."));
                exit();
            }
        }
        $stmt_check->close();
    }

    $image_path_db = $photo['image_path'];
    // Adjust path: API file is in 'ec/api/', need to go up one level and then into the upload path.
    $file_to_delete = ".." . DIRECTORY_SEPARATOR . $image_path_db;

    $success_delete_file = true;
    $file_message = "";

    // 2. Delete the actual file from the file system
    if (file_exists($file_to_delete)) {
        if (!unlink($file_to_delete)) {
            $success_delete_file = false;
            $file_message = "Warning: Failed to delete file from disk. ";
            // Log the error but proceed with database deletion
            error_log("Failed to delete file from disk: " . $file_to_delete);
        }
    } else {
        $file_message = "Warning: Physical file not found on disk. ";
    }

    // 3. Delete the record from the database
    $sql_delete = "DELETE FROM wound_images WHERE image_id = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $image_id);

    if ($stmt_delete->execute()) {
        $stmt_delete->close();
        http_response_code(200);

        echo json_encode(array(
            "success" => true,
            "message" => $file_message . "Photo deleted successfully from the database."
        ));
    } else {
        $stmt_delete->close();
        http_response_code(500);
        echo json_encode(array("success" => false, "message" => "Database record deletion failed: " . $conn->error));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "An unexpected error occurred: " . $e->getMessage()));
}

$conn->close();
?>