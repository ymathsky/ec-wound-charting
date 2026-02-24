<?php
// Filename: api/get_assessment_details_for_profile.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;

if ($assessment_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid Assessment ID provided."));
    exit();
}

try {
    // Fetch assessment details
    $sql = "SELECT a.*, w.location AS wound_location, w.wound_type, w.wound_id FROM wound_assessments a LEFT JOIN wounds w ON a.wound_id = w.wound_id WHERE a.assessment_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment = $result->fetch_assoc();
    $stmt->close();

    if ($assessment) {
        http_response_code(200);
        echo json_encode($assessment);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Assessment not found."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "An error occurred: " . $e->getMessage()));
}
$conn->close();
?>
