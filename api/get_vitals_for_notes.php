<?php
// Filename: api/get_vitals_for_notes.php
// Purpose: Provides the vitals for a specific appointment for the visit_notes.php page.
// UPDATED: Now queries directly by appointment_id.

header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_error_log.txt');
error_reporting(E_ALL);

require_once '../db_connect.php';

if (!$conn) {
    http_response_code(503);
    echo json_encode(new stdClass()); // Return {}
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(new stdClass()); // Return {}
    exit();
}

try {
    // --- SIMPLIFIED LOGIC: Step 1 ---
    // Query the patient_vitals table directly using the appointment_id.
    // This assumes the appointment_id is saved during the vitals entry step.
    $vitals_sql = "SELECT * FROM patient_vitals 
                   WHERE appointment_id = ? 
                   ORDER BY created_at DESC 
                   LIMIT 1";

    $stmt_vitals = $conn->prepare($vitals_sql);
    if ($stmt_vitals === false) {
        throw new Exception("Failed to prepare vitals statement: ". $conn->error);
    }

    // Bind the appointment_id
    $stmt_vitals->bind_param("i", $appointment_id);
    $stmt_vitals->execute();
    $result = $stmt_vitals->get_result();
    $vitals = $result->fetch_assoc();
    $stmt_vitals->close();
    // --- END SIMPLIFIED LOGIC ---

    http_response_code(200);

    if ($vitals) {
        echo json_encode($vitals);
    } else {
        // This is the correct "not found" response for our JavaScript
        echo json_encode(new stdClass());
    }

} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching vitals.", "error" => $t->getMessage()]);
}

$conn->close();
?>