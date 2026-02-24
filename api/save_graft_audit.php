<?php
// Filename: api/save_graft_audit.php
// UPDATED: To accept all new checklist fields from the PDF

session_start();
require_once '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'User not authenticated.']);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get POST data
$assessment_id = isset($_POST['assessment_id']) ? intval($_POST['assessment_id']) : 0;
$clinician_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0; // Signature is the logged-in user

// --- Data from Modal ---
$graft_conservative_care_failed = isset($_POST['graft_conservative_care_failed']) ? 1 : 0;
$graft_conditions_managed = isset($_POST['graft_conditions_managed']) ? 1 : 0;
$graft_product_name = $_POST['graft_product_name'] ?? null;
$graft_application_num = $_POST['graft_application_num'] ?? null;
$graft_serial = $_POST['graft_serial'] ?? null;
$graft_lot = $_POST['graft_lot'] ?? null;
$graft_batch = $_POST['graft_batch'] ?? null;
$graft_used_cm = $_POST['graft_used_cm'] ?? null;
$graft_discarded_cm = $_POST['graft_discarded_cm'] ?? null;
$graft_justification = $_POST['graft_justification'] ?? null;
$graft_attestation_user_id = $clinician_id; // The signature is the user ID

// *** NEW: All additional fields from PDF ***
$graft_check_osteo = isset($_POST['graft_check_osteo']) ? 1 : 0;
$graft_check_vasculitis = isset($_POST['graft_check_vasculitis']) ? 1 : 0;
$graft_check_charcot = isset($_POST['graft_check_charcot']) ? 1 : 0;
$graft_check_smoking = isset($_POST['graft_check_smoking']) ? 1 : 0;
$graft_check_bone = isset($_POST['graft_check_bone']) ? 1 : 0;
$graft_check_risks = isset($_POST['graft_check_risks']) ? 1 : 0;
$graft_wound_thickness = $_POST['graft_wound_thickness'] ?? null;
$graft_pressure_stage = $_POST['graft_pressure_stage'] ?? null;
$graft_check_offloading = isset($_POST['graft_check_offloading']) ? 1 : 0;
$graft_check_compression = isset($_POST['graft_check_compression']) ? 1 : 0;
$graft_abi_result = $_POST['graft_abi_result'] ?? null;
$graft_vlu_duplex = $_POST['graft_vlu_duplex'] ?? null;
$graft_a1c = $_POST['graft_a1c'] ?? null;
$graft_discard_justification = $_POST['graft_discard_justification'] ?? null;
$graft_treatment_goals = $_POST['graft_treatment_goals'] ?? null;


// Basic validation
if ($assessment_id <= 0 || $clinician_id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid assessment or user ID.']);
    exit;
}

if (empty($graft_product_name) || empty($graft_serial) || empty($graft_lot) || empty($graft_used_cm) || empty($graft_treatment_goals) || empty($graft_wound_thickness)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required graft product or justification fields.']);
    exit;
}

// --- Handle Serial Number Photo Upload ---
$graft_serial_photo_path = null;
if (isset($_FILES['graft_serial_photo']) && $_FILES['graft_serial_photo']['error'] == 0) {
    $target_dir = "../uploads/graft_serials/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $imageFileType = strtolower(pathinfo($_FILES["graft_serial_photo"]["name"], PATHINFO_EXTENSION));
    $new_filename = "graft_asm_" . $assessment_id . "_" . uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;

    // Check if image file is a actual image or fake image
    $check = getimagesize($_FILES["graft_serial_photo"]["tmp_name"]);
    if($check === false) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Uploaded file is not a valid image.']);
        exit;
    }

    // Check file size (e.g., 5MB limit)
    if ($_FILES["graft_serial_photo"]["size"] > 5000000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image file is too large (Max 5MB).']);
        exit;
    }

    // Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG & GIF files are allowed.']);
        exit;
    }

    if (move_uploaded_file($_FILES["graft_serial_photo"]["tmp_name"], $target_file)) {
        // We must store the path relative to the *web root* for the src attribute
        // The file was saved to ../uploads/graft_serials/
        // The web-accessible path is uploads/graft_serials/
        $graft_serial_photo_path = "uploads/graft_serials/" . $new_filename;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
        exit;
    }
}

// --- Update Database ---
// We are assuming these columns have been added to the `wound_assessments` table.
$sql = "UPDATE wound_assessments SET
            graft_conservative_care_failed = ?,
            graft_conditions_managed = ?,
            graft_product_name = ?,
            graft_application_num = ?,
            graft_serial = ?,
            graft_lot = ?,
            graft_batch = ?,
            graft_used_cm = ?,
            graft_discarded_cm = ?,
            graft_justification = ?,
            graft_serial_photo_path = ?,
            graft_attestation_user_id = ?,
            graft_attestation_timestamp = NOW(),

            graft_check_osteo = ?,
            graft_check_vasculitis = ?,
            graft_check_charcot = ?,
            graft_check_smoking = ?,
            graft_check_bone = ?,
            graft_check_risks = ?,
            graft_wound_thickness = ?,
            graft_pressure_stage = ?,
            graft_check_offloading = ?,
            graft_check_compression = ?,
            graft_abi_result = ?,
            graft_vlu_duplex = ?,
            graft_a1c = ?,
            graft_discard_justification = ?,
            graft_treatment_goals = ?

        WHERE assessment_id = ?";

try {
    $stmt = $conn->prepare($sql);
    // *** MODIFIED: Added all new bind params ***
    $stmt->bind_param("iisssssddssiiiiiisssiisssssi",
        $graft_conservative_care_failed,
        $graft_conditions_managed,
        $graft_product_name,
        $graft_application_num,
        $graft_serial,
        $graft_lot,
        $graft_batch,
        $graft_used_cm,
        $graft_discarded_cm,
        $graft_justification,
        $graft_serial_photo_path,
        $graft_attestation_user_id,

        // New fields
        $graft_check_osteo,
        $graft_check_vasculitis,
        $graft_check_charcot,
        $graft_check_smoking,
        $graft_check_bone,
        $graft_check_risks,
        $graft_wound_thickness,
        $graft_pressure_stage,
        $graft_check_offloading,
        $graft_check_compression,
        $graft_abi_result,
        $graft_vlu_duplex,
        $graft_a1c,
        $graft_discard_justification,
        $graft_treatment_goals,

        $assessment_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Graft audit saved and signed successfully.']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Assessment ID not found.']);
        }
    } else {
        throw new Exception("SQL execution failed: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    // Log the detailed error to the server error log
    error_log("Graft Audit Save Error: " . $e->getMessage());
    // Send a generic message to the client
    echo json_encode(['success' => false, 'message' => 'A database error occurred while saving the audit.']);
}

?>