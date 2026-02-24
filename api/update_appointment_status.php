<?php
// Filename: api/update_appointment_status.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Role check - CHANGED: Allow 'admin' OR 'clinician'
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician'])) {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied. You do not have permission to perform this action."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->appointment_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(["message" => "Appointment ID and new status are required."]);
    exit();
}

$appointment_id = intval($data->appointment_id);
$new_status = htmlspecialchars(strip_tags($data->status));

// Optional: Validate that the status is one of the allowed values
$allowed_statuses = ['Scheduled', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'No-show'];
if (!in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid status value provided."]);
    exit();
}

try {
    // Update status AND check_in_time if applicable
    if ($new_status === 'Checked-in') {
        $sql = "UPDATE appointments SET status = ?, check_in_time = NOW() WHERE appointment_id = ?";
    } else {
        // If moving away from Checked-in, we generally keep the time or clear it?
        // For now, let's keep it to preserve history, or we could clear it if reverting to Scheduled.
        // Let's just update status.
        $sql = "UPDATE appointments SET status = ? WHERE appointment_id = ?";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $appointment_id);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Appointment status updated successfully."]);
    } else {
        throw new Exception("Failed to update appointment status in the database.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred.", "error" => $e->getMessage()]);
}

$conn->close();
?>

