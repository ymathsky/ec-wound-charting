<?php
// Filename: api/get_patient_appointments.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "A valid patient ID is required."]);
    exit();
}

try {
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.status,
                u.full_name as clinician_name
            FROM appointments a
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.patient_id = ? 
            ORDER BY a.appointment_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($appointments);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching appointments.", "error" => $e->getMessage()]);
}

$conn->close();
?>
