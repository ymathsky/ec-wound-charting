<?php
// Filename: ec/patient_portal/api/get_documents.php
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
    // Fetch only documents marked as visible to patient
    $sql = "SELECT document_id, document_type, file_name, file_path, upload_date 
            FROM patient_documents 
            WHERE patient_id = ? AND is_patient_visible = 1 
            ORDER BY upload_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $docs = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode($docs);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error."]);
}

$conn->close();
?>