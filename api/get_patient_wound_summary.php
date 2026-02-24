<?php
// Filename: ec/api/get_patient_wound_summary.php
// Purpose: Fetches active wounds with a summary of their healing progress (Sparkline data)
// Used by: patient_profile_logic.js (Wound Dashboard)

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

session_start();
if (!isset($_SESSION['ec_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Patient ID']);
    exit();
}

try {
    // 1. Get Active Wounds
    $stmt = $conn->prepare("
        SELECT wound_id, location, wound_type, created_at 
        FROM wounds 
        WHERE patient_id = ? AND status = 'Active' 
        ORDER BY wound_id ASC
    ");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $wounds_summary = [];

    while ($wound = $result->fetch_assoc()) {
        $w_id = $wound['wound_id'];

        // 2. Get Assessment History for Sparkline (Date & Area)
        // We fetch all to calculate baseline, but frontend might only show last 5-10 points
        $stmt_hist = $conn->prepare("
            SELECT assessment_date, area_cm2, length_cm, width_cm, depth_cm
            FROM wound_assessments 
            WHERE wound_id = ? 
            ORDER BY assessment_date ASC
        ");
        $stmt_hist->bind_param("i", $w_id);
        $stmt_hist->execute();
        $res_hist = $stmt_hist->get_result();

        $history = [];
        while ($row = $res_hist->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt_hist->close();

        // 3. Calculate Metrics
        $current_area = 0;
        $current_dims = "N/A";
        $baseline_area = 0;
        $trend_percentage = 0;
        $status = "New";

        if (count($history) > 0) {
            // Latest Assessment
            $latest = end($history);
            $current_area = floatval($latest['area_cm2']);
            $current_dims = $latest['length_cm'] . ' x ' . $latest['width_cm'] . ' x ' . $latest['depth_cm'];

            // Baseline (First Assessment)
            $first = reset($history);
            $baseline_area = floatval($first['area_cm2']);

            // Calc Trend: (Current - Baseline) / Baseline * 100
            // Negative % means healing (reduction in size)
            if ($baseline_area > 0) {
                $trend_percentage = (($current_area - $baseline_area) / $baseline_area) * 100;
            }

            // Simple Status Logic
            if ($trend_percentage < -10) {
                $status = "Healing"; // Reduced by > 10%
            } elseif ($trend_percentage > 10) {
                $status = "Worsening"; // Increased by > 10%
            } else {
                $status = "Stalled"; // Within +/- 10%
            }
        }

        $wounds_summary[] = [
            'wound_id' => $w_id,
            'location' => $wound['location'],
            'type' => $wound['wound_type'],
            'current_area' => $current_area,
            'current_dims' => $current_dims,
            'trend' => round($trend_percentage, 1),
            'status' => $status,
            'history' => $history // Send full history for chart
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'wounds' => $wounds_summary]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>