<?php
// Filename: api/get_cc_data.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// FIX: Change local variable name to $patient_id for consistency, but still look for 'id' parameter
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid patient ID."]);
    exit();
}

try {
    // --- 1. Fetch Patient Demographics, PMH, and Allergies ---
    $patient_sql = "SELECT 
                        first_name, last_name, date_of_birth,
                        past_medical_history, allergies
                    FROM patients
                    WHERE patient_id = ? LIMIT 1";

    // Check connection before preparing statement
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    $stmt = $conn->prepare($patient_sql);

    // Check if statement preparation was successful
    if ($stmt === false) {
        throw new Exception("Prepare statement failed for patient details: " . $conn->error);
    }

    $stmt->bind_param("i", $patient_id);

    if (!$stmt->execute()) {
        throw new Exception("Execute statement failed for patient details: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $patient_details = $result->fetch_assoc();
    $stmt->close();

    // --- 2. Fetch Active Wounds ---
    $wounds_sql = "SELECT location, wound_type
                   FROM wounds
                   WHERE patient_id = ? AND status = 'Active'";

    $stmt_wounds = $conn->prepare($wounds_sql);

    if ($stmt_wounds === false) {
        throw new Exception("Prepare statement failed for wounds: " . $conn->error);
    }

    $stmt_wounds->bind_param("i", $patient_id);

    if (!$stmt_wounds->execute()) {
        throw new Exception("Execute statement failed for wounds: " . $stmt_wounds->error);
    }

    $wounds = $stmt_wounds->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_wounds->close();

    if (!$patient_details) {
        http_response_code(404);
        echo json_encode(["message" => "Patient not found."]);
        exit();
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "details" => $patient_details,
        "wounds" => $wounds
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server Error fetching CC data.", "error" => $e->getMessage()]);
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>
