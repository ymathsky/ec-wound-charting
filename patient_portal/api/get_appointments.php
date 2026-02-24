<?php
// Filename: ec/patient_portal/api/get_appointments.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

if (!isset($_SESSION['portal_patient_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$patient_id = $_SESSION['portal_patient_id'];

try {
    // Fetch all appointments (future and past) for this patient
    // Note: The SQL here must match the 'appointments' and 'users' tables
    $sql = "SELECT a.*, u.full_name as doctor_name 
            FROM appointments a 
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.patient_id = ? 
            ORDER BY appointment_date DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        // Log SQL preparation error
        error_log("MySQL Prepare Error: " . $conn->error);
        throw new Exception("Database query preparation failed.");
    }

    $stmt->bind_param("i", $patient_id);

    if (!$stmt->execute()) {
        // Log SQL execution error
        error_log("MySQL Execute Error: " . $stmt->error);
        throw new Exception("Database query execution failed.");
    }

    $result = $stmt->get_result();

    $appointments = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($appointments);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    // Return a more generic error to the client, but log the specific error on the server
    error_log("Server Error in get_appointments.php: " . $e->getMessage());
    echo json_encode(["message" => "Server error fetching appointments. Please contact support."]);
}

$conn->close();
?>