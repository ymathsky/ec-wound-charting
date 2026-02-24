<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if (!$patient_id) {
    echo json_encode(["success" => false, "message" => "Invalid patient ID."]);
    exit;
}

$stmt = $conn->prepare("SELECT cl.*, u.full_name as logged_by 
    FROM communication_log cl 
    LEFT JOIN users u ON cl.user_id = u.user_id 
    WHERE cl.patient_id = ? 
    ORDER BY cl.created_at DESC 
    LIMIT 50");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

echo json_encode(["success" => true, "logs" => $logs]);
