<?php
// Filename: api/update_appointment_date.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Role check - ensure only admins can reschedule
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied. You do not have permission to perform this action."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->appointment_id) || empty($data->new_datetime)) {
    http_response_code(400);
    echo json_encode(["message" => "Appointment ID and new datetime are required."]);
    exit();
}

$appointment_id = intval($data->appointment_id);
$new_datetime = $data->new_datetime; // This is now a full 'YYYY-MM-DD HH:MM:SS' string

try {
    // Directly update the appointment with the new combined datetime
    $sql_update = "UPDATE appointments SET appointment_date = ? WHERE appointment_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("si", $new_datetime, $appointment_id);

    if ($stmt_update->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Appointment rescheduled successfully."]);
    } else {
        throw new Exception("Failed to update appointment in the database.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred.", "error" => $e->getMessage()]);
}

$conn->close();

?>

