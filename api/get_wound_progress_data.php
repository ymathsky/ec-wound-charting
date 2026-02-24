<?php
// Filename: api/get_wound_progress_data.php
// Description: Fetches historical wound assessment data (measurements) for a given wound ID.

session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
require_once '../db_connect.php';

// Check for required parameter
if (!isset($_GET['wound_id']) || empty($_GET['wound_id'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Wound ID is required."));
    exit();
}

$wound_id = intval($_GET['wound_id']);

try {
    // We fetch the basic wound details to include in the response header
    $wound_details_sql = "SELECT w.location, w.wound_type FROM wounds w WHERE w.wound_id = ?";
    $stmt_details = $conn->prepare($wound_details_sql);
    $stmt_details->bind_param("i", $wound_id);
    $stmt_details->execute();
    $details_result = $stmt_details->get_result();
    $wound_details = $details_result->fetch_assoc();
    $stmt_details->close();

    if (!$wound_details) {
        http_response_code(404);
        echo json_encode(array("message" => "Wound not found."));
        $conn->close();
        exit();
    }

    // Query to fetch all relevant assessment data, ordered by visit date
    $sql = "SELECT 
                wa.assessment_date,
                wa.length_cm,
                wa.width_cm,
                wa.depth_cm,
                (wa.length_cm * wa.width_cm) AS area_cm2,
                (wa.length_cm * wa.width_cm * wa.depth_cm) AS volume_cm3
            FROM 
                wound_assessments wa
            WHERE 
                wa.wound_id = ?
            ORDER BY 
                wa.assessment_date ASC, wa.assessment_id ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $wound_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $progress_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Structure the final output
    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "wound_id" => $wound_id,
        "details" => $wound_details,
        "data" => $progress_data
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Database error: " . $e->getMessage()));
}

$conn->close();
?>
