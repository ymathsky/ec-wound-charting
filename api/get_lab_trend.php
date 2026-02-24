<?php
// Filename: api/get_lab_trend.php
// Description: Fetches time-series data for a specific metric (Lab or Vital) for a patient.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$metric_name = isset($_GET['metric']) ? $_GET['metric'] : '';

if ($patient_id <= 0 || empty($metric_name)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid patient ID or metric name."]);
    exit();
}

try {
    $labels = [];
    $data_points = [];
    $dataset_label = $metric_name;

    if ($metric_name == 'Weight') {
        // --- Get Vitals Data ---
        $stmt = $conn->prepare("SELECT visit_date, weight_kg 
                                FROM patient_vitals 
                                WHERE patient_id = ? AND weight_kg IS NOT NULL 
                                ORDER BY visit_date ASC");
        $stmt->bind_param("i", $patient_id);
        $dataset_label = 'Weight (kg)';

        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['visit_date'];
            $data_points[] = $row['weight_kg'];
        }

    } else {
        // --- Get Lab Data ---
        // We ensure the result_value is a valid number before adding it
        $stmt = $conn->prepare("SELECT result_date, result_value 
                                FROM lab_results 
                                WHERE patient_id = ? AND test_name = ? 
                                AND result_date IS NOT NULL AND result_value IS NOT NULL AND result_value != ''
                                ORDER BY result_date ASC");
        $stmt->bind_param("is", $patient_id, $metric_name);

        $stmt->execute();
        $result = $stmt->get_result();
        while($row = $result->fetch_assoc()) {
            // Only add points that are numeric
            if (is_numeric($row['result_value'])) {
                $labels[] = $row['result_date'];
                $data_points[] = floatval($row['result_value']);
            }
        }
    }

    $stmt->close();
    $conn->close();

    // --- Build Chart.js Data Object ---
    $chart_data = [
        'labels' => $labels,
        'datasets' => [
            [
                'label' => $dataset_label,
                'data' => $data_points,
                'borderColor' => 'rgb(79, 70, 229)', // text-indigo-600
                'backgroundColor' => 'rgba(79, 70, 229, 0.2)',
                'fill' => true,
                'tension' => 0.1,
                'borderWidth' => 2
            ]
        ]
    ];

    http_response_code(200);
    echo json_encode($chart_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server Error fetching trend data.", "error" => $e->getMessage()]);
}
?>