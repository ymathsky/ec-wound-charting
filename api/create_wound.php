<?php
// Filename: api/create_wound.php

// --- API Headers ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// --- Include Database Connection ---
require_once '../db_connect.php';

// --- Handle Incoming Data ---
$data = json_decode(file_get_contents("php://input"));

// --- Basic Validation ---
if (
    !empty($data->patient_id) &&
    !empty($data->location) &&
    !empty($data->wound_type) &&
    !empty($data->date_onset)
) {
    // --- Prepare and Execute SQL INSERT Statement ---
    $sql = "INSERT INTO wounds (patient_id, location, wound_type, diagnosis, date_onset, status) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Sanitize data
    $patient_id = intval($data->patient_id);
    $location = htmlspecialchars(strip_tags($data->location));
    $wound_type = htmlspecialchars(strip_tags($data->wound_type));
    $diagnosis = isset($data->diagnosis) ? htmlspecialchars(strip_tags($data->diagnosis)) : null;
    $date_onset = htmlspecialchars(strip_tags($data->date_onset));
    $status = "Active"; // New wounds are always active by default

    $stmt->bind_param("isssss", $patient_id, $location, $wound_type, $diagnosis, $date_onset, $status);

    // --- Send Response ---
    if ($stmt->execute()) {
        http_response_code(201); // Created
        echo json_encode(array("message" => "Wound was successfully added."));
    } else {
        http_response_code(503); // Service Unavailable
        echo json_encode(array("message" => "Unable to add wound."));
    }
} else {
    // If data is incomplete
    http_response_code(400); // Bad Request
    echo json_encode(array("message" => "Unable to add wound. Data is incomplete."));
}

$conn->close();
?>
