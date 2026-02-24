<?php
// Filename: api/get_patient_report_data.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

session_start();
// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    $received_id = isset($_GET['patient_id']) ? htmlspecialchars($_GET['patient_id']) : 'not set';
    echo json_encode(["message" => "Invalid Patient ID provided. Received: '" . $received_id . "'"]);
    exit();
}

try {
    // 1. Get all wounds for the patient
    $sql_wounds = "SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ?";
    $stmt_wounds = $conn->prepare($sql_wounds);
    $stmt_wounds->bind_param("i", $patient_id);
    $stmt_wounds->execute();
    $wounds = $stmt_wounds->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_wounds->close();

    $datasets = [];
    $colors = ['#3B82F6', '#10B981', '#EF4444', '#F59E0B', '#8B5CF6', '#EC4899'];
    $color_index = 0;

    // 2. For each wound, get its assessment history
    foreach ($wounds as $wound) {
        $sql_assessments = "SELECT assessment_date, length_cm, width_cm 
                            FROM wound_assessments 
                            WHERE wound_id = ? AND length_cm IS NOT NULL AND width_cm IS NOT NULL
                            ORDER BY assessment_date ASC";

        $stmt_assessments = $conn->prepare($sql_assessments);
        $stmt_assessments->bind_param("i", $wound['wound_id']);
        $stmt_assessments->execute();
        $assessments = $stmt_assessments->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_assessments->close();

        if (count($assessments) > 0) {
            $data_points = [];
            foreach ($assessments as $asm) {
                $area = (float)$asm['length_cm'] * (float)$asm['width_cm'];
                $data_points[] = ['x' => $asm['assessment_date'], 'y' => round($area, 2)];
            }

            $datasets[] = [
                'label' => "Wound #" . $wound['wound_id'] . ": " . $wound['location'],
                'data' => $data_points,
                'borderColor' => $colors[$color_index % count($colors)],
                'backgroundColor' => $colors[$color_index % count($colors)] . '33', // Lighter fill
                'fill' => false,
                'tension' => 0.1
            ];
            $color_index++;
        }
    }

    http_response_code(200);
    echo json_encode(['datasets' => $datasets]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while generating patient report.", "error" => $e->getMessage()]);
}

$conn->close();
?>

