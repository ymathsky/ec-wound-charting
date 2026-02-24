<?php
// Filename: api/get_wound_assessment_for_profile.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;

if ($wound_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid Wound ID provided."));
    exit();
}

try {
    // Fetch wound location and type only
    $sql = "SELECT wound_id, location AS wound_location, wound_type FROM wounds WHERE wound_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $wound_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $wound = $result->fetch_assoc();
    $stmt->close();

    if ($wound) {
        http_response_code(200);
        echo json_encode($wound);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Wound not found."));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "An error occurred: " . $e->getMessage()));
}
$conn->close();
?>
