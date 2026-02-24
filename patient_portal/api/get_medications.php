<?php
// Filename: ec/patient_portal/api/get_medications.php
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
    // Fetch medications, sorting Active ones first
    $sql = "SELECT * FROM patient_medications 
            WHERE patient_id = ? 
            ORDER BY 
                CASE WHEN status = 'Active' THEN 1 ELSE 2 END,
                start_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $meds = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($meds);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error."]);
}

$conn->close();
?>