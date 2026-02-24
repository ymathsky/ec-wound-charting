<?php
// Filename: ec/api/reschedule_appointment.php

// Adjust path to point to 'ec/db_connect.php' from 'ec/api/'
require_once(__DIR__ . '/../db_connect.php');

// Set JSON header for API response
header('Content-Type: application/json');

// Check for required POST data
if (!isset($_POST['old_appointment_id']) || !isset($_POST['patient_id']) || !isset($_POST['user_id']) || !isset($_POST['appointment_date'])) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Missing required data for rescheduling."));
    exit();
}

// Sanitize and validate input
$old_appointment_id = filter_var($_POST['old_appointment_id'], FILTER_VALIDATE_INT);
$patient_id = filter_var($_POST['patient_id'], FILTER_VALIDATE_INT);

// FIX: Handle user_id being 0 (unassigned) which causes Foreign Key errors
$user_id_input = $_POST['user_id'];
if ($user_id_input === '0' || $user_id_input === 0) {
    $user_id = NULL;
} else {
    $user_id = filter_var($user_id_input, FILTER_VALIDATE_INT);
    // If filter failed, set to NULL or handle error, here we treat invalid as NULL/Unassigned to prevent crash
    if ($user_id === false) $user_id = NULL;
}

$appointment_date = trim($_POST['appointment_date']);

$appointment_type = isset($_POST['appointment_type']) ? trim($_POST['appointment_type']) : 'Follow Up Visit';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : NULL;
$estimated_travel_minutes = isset($_POST['estimated_travel_minutes']) ? filter_var($_POST['estimated_travel_minutes'], FILTER_VALIDATE_INT) : 0;
$recurrence_group_id = isset($_POST['recurrence_group_id']) && $_POST['recurrence_group_id'] !== ''
    ? filter_var($_POST['recurrence_group_id'], FILTER_VALIDATE_INT) : NULL;

if ($old_appointment_id === false || $patient_id === false) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Invalid appointment or patient ID."));
    exit();
}

$conn->begin_transaction();

try {
    // 1. Create the new appointment, linked to the old one via rescheduled_from_id
    $sql_insert = "INSERT INTO appointments (patient_id, user_id, appointment_date, appointment_type, notes, estimated_travel_minutes, recurrence_group_id, rescheduled_from_id) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    if ($stmt_insert = $conn->prepare($sql_insert)) {

        // Types: i (int), i (int), s (string), s (string), s (string), i (int), i (int), i (int)
        // Note: user_id is nullable, so 'i' works if we pass NULL
        $stmt_insert->bind_param("iisssiii",
            $patient_id,
            $user_id,
            $appointment_date,
            $appointment_type,
            $notes,
            $estimated_travel_minutes,
            $recurrence_group_id,
            $old_appointment_id
        );

        if (!$stmt_insert->execute()) {
            throw new Exception("Error creating new appointment: " . $stmt_insert->error);
        }
        $new_appointment_id = $conn->insert_id;
        $stmt_insert->close();
    } else {
        throw new Exception("Prepared statement for insert failed: " . $conn->error);
    }

    // 2. Mark the old appointment as 'Cancelled' and record the reason
    $cancellation_reason = "Rescheduled to appointment ID: " . $new_appointment_id;
    $sql_update_old = "UPDATE appointments SET status = 'Cancelled', cancellation_reason = ? WHERE appointment_id = ?";

    if ($stmt_update_old = $conn->prepare($sql_update_old)) {
        $stmt_update_old->bind_param("si", $cancellation_reason, $old_appointment_id);

        if (!$stmt_update_old->execute()) {
            throw new Exception("Error updating old appointment status: " . $stmt_update_old->error);
        }
        $stmt_update_old->close();
    } else {
        throw new Exception("Prepared statement for update failed: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode(array(
        "success" => true,
        "message" => "Appointment rescheduled successfully. Old appointment cancelled.",
        "new_appointment_id" => $new_appointment_id,
        "old_appointment_id" => $old_appointment_id
    ));

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Rescheduling failed: " . $e->getMessage()));
}

$conn->close();
?>