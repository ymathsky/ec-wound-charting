<?php
// Filename: api/create_medication.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"));

// Basic validation for required fields
if (empty($data->patient_id) || empty($data->drug_name) || empty($data->dosage) || empty($data->frequency)) {
    http_response_code(400);
    echo json_encode(["message" => "Drug Name, Dosage, Frequency, and Patient ID are required."]);
    exit();
}

try {
    // Sanitize data
    $patient_id = intval($data->patient_id);
    $drug_name = htmlspecialchars(strip_tags($data->drug_name));
    $dosage = htmlspecialchars(strip_tags($data->dosage));
    $frequency = htmlspecialchars(strip_tags($data->frequency));
    $route = !empty($data->route) ? htmlspecialchars(strip_tags($data->route)) : null;
    $start_date = !empty($data->start_date) ? htmlspecialchars(strip_tags($data->start_date)) : date('Y-m-d');
    $end_date = !empty($data->end_date) ? htmlspecialchars(strip_tags($data->end_date)) : null;
    $status = !empty($data->status) ? htmlspecialchars(strip_tags($data->status)) : 'Active';
    $notes = !empty($data->notes) ? htmlspecialchars(strip_tags($data->notes)) : null;

    // Check if updating an existing medication
    if (!empty($data->medication_id)) {
        // ARCHIVE Existing Medication (History Preservation)
        $medication_id = intval($data->medication_id);
        
        // 1. Mark the old record as 'Archived'
        // We do NOT change the end_date here, as it reflects the history of that specific record.
        // If the user wants to "stop" a med, they should set the status to Discontinued/Completed in the new record,
        // or we could set the old record's end_date to today if it was active?
        // For pure "Edit History", we just change status to Archived.
        $archive_sql = "UPDATE patient_medications SET status = 'Archived' WHERE medication_id = ? AND patient_id = ?";
        $archive_stmt = $conn->prepare($archive_sql);
        $archive_stmt->bind_param("ii", $medication_id, $patient_id);
        $archive_stmt->execute();
        $archive_stmt->close();

        // 2. INSERT New Medication with the updated details
        // This creates a new "Active" (or whatever status selected) record.
        $sql = "INSERT INTO patient_medications (patient_id, drug_name, dosage, frequency, route, start_date, end_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $patient_id, $drug_name, $dosage, $frequency, $route, $start_date, $end_date, $status, $notes);
        $message = "Medication updated successfully (History preserved).";
    } else {
        // INSERT New Medication
        $sql = "INSERT INTO patient_medications (patient_id, drug_name, dosage, frequency, route, start_date, end_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $patient_id, $drug_name, $dosage, $frequency, $route, $start_date, $end_date, $status, $notes);
        $message = "Medication added successfully.";
    }

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => $message, "medication_id" => $conn->insert_id]);
    } else {
        throw new Exception("Database execution failed: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(["message" => "Unable to save medication.", "error" => $e->getMessage()]);
}
?>
