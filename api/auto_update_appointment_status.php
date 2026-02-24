<?php
// Filename: api/auto_update_appointment_status.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// This script automatically updates appointment statuses to 'Cancelled' if:
// 1. The appointment date is in the past (before today).
// 2. The current status is 'Scheduled' or 'Confirmed'.
// 3. No clinical note (chart) has been created for the appointment.

try {
    // We use CURDATE() to ensure we only cancel appointments from yesterday or earlier.
    // We do not want to cancel appointments scheduled for later today just because they haven't happened yet.
    
    $sql = "UPDATE appointments a
            LEFT JOIN patient_notes n ON a.appointment_id = n.appointment_id
            SET a.status = 'Cancelled'
            WHERE a.appointment_date < CURDATE() 
              AND a.status IN ('Scheduled', 'Confirmed')
              AND n.note_id IS NULL";

    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Auto-cancellation complete.", 
            "cancelled_count" => $affected_rows
        ]);
    } else {
        throw new Exception("Database execution failed: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error updating statuses.", 
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>
