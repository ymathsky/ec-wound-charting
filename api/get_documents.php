<?php
// Filename: api/get_documents.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID."]);
    exit();
}

try {
    $sql = "SELECT pd.*, u.full_name as uploader_name 
            FROM patient_documents pd
            LEFT JOIN users u ON pd.user_id = u.user_id
            WHERE pd.patient_id = ?
            ORDER BY pd.upload_date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $documents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    http_response_code(200);
    echo json_encode($documents);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching documents.", "error" => $e->getMessage()]);
}

$conn->close();
?>
