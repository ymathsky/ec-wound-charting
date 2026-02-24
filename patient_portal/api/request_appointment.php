<?php
// Filename: ec/patient_portal/api/request_appointment.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

if (!isset($_SESSION['portal_patient_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$patient_id = $_SESSION['portal_patient_id'];
$input = json_decode(file_get_contents("php://input"), true);

// Validate Input
if (empty($input['requested_date']) || empty($input['reason']) || empty($input['time_preference'])) {
    http_response_code(400);
    echo json_encode(["message" => "Date, exact Time, and Reason are required."]);
    exit();
}

$requested_date = strip_tags($input['requested_date']);
// The client now sends the full time string, e.g., "10:30 AM"
$time_preference = strip_tags($input['time_preference']);
$reason = strip_tags($input['reason']);

// Basic Date Validation
if (strtotime($requested_date) < strtotime(date('Y-m-d'))) {
    http_response_code(400);
    echo json_encode(["message" => "Cannot request appointments in the past."]);
    exit();
}

try {
    // Default database time is set to 9 AM for the requested date.
    // The specific patient preference (e.g., "10:30 AM") is stored in the notes.
    $appt_datetime = $requested_date . ' 09:00:00';

    // Construct a descriptive note for the scheduler using the specific time chosen
    $notes = "Patient Request via Portal.\nReason: $reason\nTime Preference: $time_preference";

    $status = 'Pending';

    $sql = "INSERT INTO appointments (patient_id, appointment_date, appointment_type, status, notes) 
            VALUES (?, ?, 'Wound Check', ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isss", $patient_id, $appt_datetime, $status, $notes);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Appointment request submitted successfully."]);
    } else {
        throw new Exception("Database insert failed: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error in request_appointment.php: " . $e->getMessage());
    echo json_encode(["message" => "Server error processing request."]);
}

$conn->close();
?>