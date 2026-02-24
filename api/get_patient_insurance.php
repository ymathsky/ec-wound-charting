<?php
// Filename: ec/api/get_patient_insurance.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    echo json_encode([]);
    exit;
}

// Fetch policies sorted by specific priority order
// Primary -> Secondary -> Tertiary -> Other -> (Created Date)
$sql = "SELECT * FROM patient_insurance 
        WHERE patient_id = ? 
        ORDER BY CASE 
            WHEN priority = 'Primary' THEN 1 
            WHEN priority = 'Secondary' THEN 2 
            WHEN priority = 'Tertiary' THEN 3 
            ELSE 4 
        END, created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["message" => "Database error preparation failed."]);
    exit;
}

$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$policies = [];
while ($row = $result->fetch_assoc()) {
    $policies[] = $row;
}

echo json_encode($policies);

$stmt->close();
$conn->close();
?>