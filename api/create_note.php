<?php
// Filename: api/create_note.php

session_start();
// API Headers - DO NOT include presentation templates like header.php here
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"));

// Basic validation
if (empty($data->patient_id) || empty($data->note_date) || empty($data->appointment_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data. Patient ID, Appointment ID, and Note Date are required."]);
    exit();
}

$user_id = isset($_SESSION['ec_user_id']) ? $_SESSION['ec_user_id'] : null;

try {
    $note_date = date('Y-m-d H:i:s');
    // Check if a note already exists for this appointment_id (for update capability)
    $check_sql = "SELECT note_id FROM patient_notes WHERE appointment_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $data->appointment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing_note = $result->fetch_assoc();
    $check_stmt->close();

    // Sanitize all incoming fields, providing null if they are empty
    $patient_id = intval($data->patient_id);
    $appointment_id = intval($data->appointment_id);
    $note_date = htmlspecialchars(strip_tags($data->note_date));
    $chief_complaint = !empty($data->chief_complaint) ? htmlspecialchars(strip_tags($data->chief_complaint)) : null;
    // Removed $health_concerns as it is not used in the front-end form fields
    $subjective = !empty($data->subjective) ? htmlspecialchars(strip_tags($data->subjective)) : null;
    $objective = !empty($data->objective) ? htmlspecialchars(strip_tags($data->objective)) : null;
    $assessment = !empty($data->assessment) ? htmlspecialchars(strip_tags($data->assessment)) : null;
    $plan = !empty($data->plan) ? htmlspecialchars(strip_tags($data->plan)) : null;

    if ($existing_note) {
        // UPDATE existing record
        $sql = "UPDATE patient_notes SET user_id=?, note_date=?, chief_complaint=?, subjective=?, objective=?, assessment=?, plan=? WHERE appointment_id=?";
        $stmt = $conn->prepare($sql);
        // Binding: i, s, s, s, s, s, s, i (8 parameters for UPDATE)
        $stmt->bind_param("issssssi", $user_id, $note_date, $chief_complaint, $subjective, $objective, $assessment, $plan, $appointment_id);
        $message = "Visit note updated successfully.";
    } else {
        // INSERT new record
        $sql = "INSERT INTO patient_notes (patient_id, user_id, appointment_id, note_date, chief_complaint, subjective, objective, assessment, plan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        // Binding: i, i, i, s, s, s, s, s, s (9 parameters for INSERT)
        $stmt->bind_param("iiissssss", $patient_id, $user_id, $appointment_id, $note_date, $chief_complaint, $subjective, $objective, $assessment, $plan);
        $message = "Visit note saved successfully.";
    }


    if ($stmt->execute()) {
        // --- CRITICAL FIX 1: Set response code to 200 for successful update/insert ---
        http_response_code(200);
        echo json_encode(["message" => $message]);
    } else {
        throw new Exception("Database execution failed: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(["message" => "Unable to save visit note.", "error" => $e->getMessage()]);
}

// FIX: Ensure connection is closed outside of try/catch block if needed,
// but since the script exits/dies after output, this should be fine.

// If execution reaches here, the connection wasn't closed above.
if ($conn && $conn->ping()) {
    // $conn->close(); // Not needed if we rely on implicit closing, but safe to include if not throwing an exception.
}
?>
