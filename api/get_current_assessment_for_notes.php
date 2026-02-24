<?php
// Filename: api/get_current_assessment_for_notes.php
// Purpose: Provides detailed assessment data for the visit_notes.php page.
// FIX: Removed `w.onset_date` and `w.etiology` from the SELECT statement
// to match the database schema and prevent a 500 error.

header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_error_log.txt');
error_reporting(E_ALL);

require_once '../db_connect.php';

if (!$conn) {
    http_response_code(503);
    echo json_encode([]);
    exit();
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    http_response_code(400);
    echo json_encode([]);
    exit();
}

try {
    // --- START OF FIX: Safe Query ---
    // We are only selecting columns that exist in `ec_wound (15).sql`.
    $assessment_sql = "SELECT 
                           wa.*,  -- Select all columns from the assessment
                           w.location, w.wound_type 
                       FROM 
                           wound_assessments wa
                       JOIN 
                           wounds w ON wa.wound_id = w.wound_id
                       WHERE 
                           wa.appointment_id = ?
                       ORDER BY 
                           wa.created_at ASC";
    // --- END OF FIX: Safe Query ---

    $stmt_assess = $conn->prepare($assessment_sql);
    if ($stmt_assess === false) {
        throw new Exception("Failed to prepare assessment statement: ". $conn->error);
    }

    $stmt_assess->bind_param("i", $appointment_id);
    $stmt_assess->execute();
    $result = $stmt_assess->get_result();
    $assessments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_assess->close();

    http_response_code(200);
    echo json_encode($assessments);

} catch (Throwable $t) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching assessments.", "error" => $t->getMessage()]);
}

$conn->close();
?>