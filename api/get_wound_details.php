<?php
// Filename: api/get_wound_details.php

header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized."));
    exit();
}

$wound_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($wound_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid Wound ID provided."));
    exit();
}

try {
    // --- Fetch Wound and Patient Details ---
    // The query selects w.* (which includes patient_id) and p.first_name, p.last_name
    $sql_details = "SELECT w.*, p.first_name, p.last_name, p.facility_id
                    FROM wounds w
                    JOIN patients p ON w.patient_id = p.patient_id
                    WHERE w.wound_id = ?";
    $stmt_details = $conn->prepare($sql_details);
    $stmt_details->bind_param("i", $wound_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    $wound_data = $result_details->fetch_assoc();

    if (!$wound_data) {
        http_response_code(404);
        echo json_encode(array("message" => "Wound not found."));
        exit();
    }

    // --- Authorization Check ---
    if ($_SESSION['ec_role'] === 'facility' && $_SESSION['ec_user_id'] != $wound_data['facility_id']) {
        http_response_code(403);
        echo json_encode(array("message" => "Forbidden. You do not have access to this wound."));
        exit();
    }

    // --- Fetch Assessments ---
    $sql_assessments = "SELECT * FROM wound_assessments WHERE wound_id = ? ORDER BY assessment_date DESC, created_at DESC";
    $stmt_assessments = $conn->prepare($sql_assessments);
    $stmt_assessments->bind_param("i", $wound_id);
    $stmt_assessments->execute();
    $result_assessments = $stmt_assessments->get_result();
    $assessments = $result_assessments->fetch_all(MYSQLI_ASSOC);
    $stmt_assessments->close();

    // --- Fetch Wound Images ---
    // FIX: Added assessment_id to the query to ensure images can be linked to their assessments.
    $sql_images = "SELECT image_id, assessment_id, image_path, image_type, uploaded_at FROM wound_images WHERE wound_id = ? ORDER BY uploaded_at DESC";
    $stmt_images = $conn->prepare($sql_images);
    $stmt_images->bind_param("i", $wound_id);
    $stmt_images->execute();
    $result_images = $stmt_images->get_result();
    $images = $result_images->fetch_all(MYSQLI_ASSOC);
    $stmt_images->close();


    // --- Assemble Response with Correct Structure ---

    // FIX: Extract patient_id, first_name, and last_name and place them into the dedicated 'patient' object.
    $patient_details = [
        'patient_id' => $wound_data['patient_id'], // CRITICAL FIX: Include the patient ID
        'first_name' => $wound_data['first_name'],
        'last_name' => $wound_data['last_name']
    ];

    // Clean up $wound_data (details) so it doesn't duplicate patient info
    unset($wound_data['patient_id'], $wound_data['first_name'], $wound_data['last_name'], $wound_data['facility_id']);

    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "details" => $wound_data,
        "patient" => $patient_details,
        "assessments" => $assessments,
        "images" => $images
    ));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "An error occurred: " . $e->getMessage()));
}
$conn->close();
?>