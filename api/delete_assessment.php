<?php
// Filename: api/delete_assessment.php
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

$data = json_decode(file_get_contents("php://input"));

if (empty($data->assessment_id)) {
    http_response_code(400);
    echo json_encode(array("message" => "Assessment ID is required."));
    exit();
}

$assessment_id = intval($data->assessment_id);

// --- Retrieve Assessment Info ---
$sql_info = "SELECT wa.appointment_id, p.facility_id 
             FROM wound_assessments wa
             JOIN wounds w ON wa.wound_id = w.wound_id
             JOIN patients p ON w.patient_id = p.patient_id
             WHERE wa.assessment_id = ?";
$stmt_info = $conn->prepare($sql_info);
$stmt_info->bind_param("i", $assessment_id);
$stmt_info->execute();
$result_info = $stmt_info->get_result();
$assessment_info = $result_info->fetch_assoc();
$stmt_info->close();

if (!$assessment_info) {
    http_response_code(404);
    echo json_encode(array("success" => false, "message" => "Assessment not found."));
    exit();
}

// --- Authorization Check ---
if ($_SESSION['ec_role'] === 'facility' && $_SESSION['ec_user_id'] != $assessment_info['facility_id']) {
    http_response_code(403);
    echo json_encode(array("success" => false, "message" => "Forbidden. You do not have access to this assessment."));
    exit();
}

// --- Check if Visit is Signed ---
if (!empty($assessment_info['appointment_id'])) {
    $appt_id = $assessment_info['appointment_id'];
    $sql_check_signed = "SELECT is_signed FROM visit_notes WHERE appointment_id = ?";
    $stmt_check = $conn->prepare($sql_check_signed);
    $stmt_check->bind_param("i", $appt_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    if ($row_check = $res_check->fetch_assoc()) {
        if ($row_check['is_signed']) {
            http_response_code(403);
            echo json_encode(array("success" => false, "message" => "Cannot delete assessment. The visit is already signed."));
            exit();
        }
    }
    $stmt_check->close();
}

$sql = "DELETE FROM wound_assessments WHERE assessment_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare statement failed: " . $conn->error));
    exit();
}

$stmt->bind_param("i", $assessment_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        http_response_code(200);
        echo json_encode(array("success" => true, "message" => "Assessment deleted successfully."));
    } else {
        http_response_code(404);
        echo json_encode(array("success" => false, "message" => "Assessment not found."));
    }
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Assessment deletion failed: " . $stmt->error));
}

$stmt->close();
$conn->close();
?>
