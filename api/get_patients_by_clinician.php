<?php
// Filename: ec/api/get_patients_by_clinician.php
// Description: Fetches a list of patients assigned to a specific clinician (user_id).

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Check for user authentication
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

// 1. Get the clinician ID from the GET request
if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing or invalid clinician ID."]);
    exit();
}

$clinician_id = (int)$_GET['user_id'];

try {
    // Query to select patients where the primary_user_id matches the provided clinician_id
    $sql = "SELECT patient_id, patient_code, first_name, last_name
            FROM patients
            WHERE primary_user_id = ?
            ORDER BY last_name ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $clinician_id);

    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Format output for the dropdown/select list
    $formatted_patients = array_map(function($p) {
        return [
            'id' => $p['patient_id'],
            'text' => $p['last_name'] . ', ' . $p['first_name'] . ' (' . $p['patient_code'] . ')'
        ];
    }, $patients);

    http_response_code(200);
    echo json_encode(["success" => true, "patients" => $formatted_patients]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>
