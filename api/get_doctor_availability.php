<?php
// Filename: api/get_doctor_availability.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '';

if ($user_id <= 0 || empty($date)) {
    http_response_code(400);
    echo json_encode([]); // Return empty array on error
    exit();
}

try {
    // Fetch appointments for the given clinician on the specified date
    $sql = "SELECT appointment_date FROM appointments WHERE user_id = ? AND DATE(appointment_date) = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked_slots = [];
    while($row = $result->fetch_assoc()) {
        // Extract just the HH:MM part of the datetime
        $booked_slots[] = date('H:i', strtotime($row['appointment_date']));
    }

    http_response_code(200);
    echo json_encode($booked_slots);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([]); // Return empty array on server error
}

$conn->close();
?>
