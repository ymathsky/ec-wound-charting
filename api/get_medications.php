<?php
// Filename: api/get_medications.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID."]);
    exit();
}

try {
    // Auto-update status for expired medications (Active -> Completed if end_date is past)
    $update_sql = "UPDATE patient_medications SET status = 'Completed' WHERE patient_id = ? AND end_date IS NOT NULL AND end_date < CURDATE() AND status = 'Active'";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $patient_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Fetch all medications for the patient, ordered by status (Active first)
    $sql = "SELECT * FROM patient_medications WHERE patient_id = ? ORDER BY status DESC, created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $medications = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($medications);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching medications.", "error" => $e->getMessage()]);
}
?>
