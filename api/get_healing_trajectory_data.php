<?php
// ec/api/get_healing_trajectory_data.php
// START: Output Buffering to catch and discard unwanted HTML/warnings before JSON output
ob_start();

// Set header for JSON response
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Invalid request method.'];

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Attempt to safely include database connection.
    if (!@include '../db_connect.php') {
        $response['message'] = 'Critical Error: Database connection file (db_connect.php) could not be loaded or executed. Check the include path.';
        // Don't exit yet, we need to clean the buffer
    } else {

        // Access the global $conn variable created by the user's db_connect.php file.
        global $conn;
        $db = $conn;

        // Check for immediate connection failure as set up by the user's db_connect.php file
        if ($db->connect_error) {
            $response['message'] = 'Database Connection Failed. Check credentials in db_connect.php.';
            // Don't exit yet, we need to clean the buffer
        } else {

            // 1. Get and sanitize input
            $data = json_decode(file_get_contents("php://input"), true);
            $wound_id = isset($data['wound_id']) ? intval($data['wound_id']) : 0;

            if ($wound_id > 0) {
                // --- Database Query (TEMPORARY FIX: REMOVING problematic 'eschar_percent' column) ---

                // NOTE: We have temporarily removed the 'eschar_percent' column from the SELECT list
                // because your running database repeatedly reported it as an 'Unknown column'.
                // This allows the feature to run, but the tissue calculation will only use slough_percent.
                $sql = "SELECT assessment_date, length_cm, width_cm, depth_cm, granulation_percent, slough_percent
                        FROM wound_assessments 
                        WHERE wound_id = ? 
                        ORDER BY assessment_date DESC"; // Most recent first

                $stmt = $db->prepare($sql);

                if (!$stmt) {
                    $response = ['success' => false, 'message' => 'Database query preparation failed: ' . $db->error];
                } else {

                    $stmt->bind_param("i", $wound_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $assessments = [];
                    while ($row = $result->fetch_assoc()) {
                        $assessments[] = $row;
                    }
                    $stmt->close();
                    // ------------------------------------------------------------------

                    if (count($assessments) >= 2) {
                        // Calculate comparison metrics between the latest (index 0) and previous (index 1)
                        $latest = $assessments[0];
                        $previous = $assessments[1];

                        // --- Calculation Logic (ADJUSTED: eschar_percent assumed 0) ---
                        $latest_area = floatval($latest['length_cm']) * floatval($latest['width_cm']);
                        $previous_area = floatval($previous['length_cm']) * floatval($previous['width_cm']);

                        // 1. Area Change Calculation (Negative change = Healing/Good)
                        $area_change = $latest_area - $previous_area;
                        $area_percent_change = ($previous_area > 0) ? round(($area_change / $previous_area) * 100, 2) : 0;
                        $area_trend = ($area_change < 0) ? 'Healing' : (($area_change > 0) ? 'Worsening' : 'Stagnant');

                        // 2. Depth Change Calculation (Negative change = Improved/Good)
                        $depth_change = floatval($latest['depth_cm']) - floatval($previous['depth_cm']);
                        $depth_percent_change = (floatval($previous['depth_cm']) > 0) ? round(($depth_change / floatval($previous['depth_cm'])) * 100, 2) : 0;
                        $depth_trend = ($depth_change < 0) ? 'Improved' : (($depth_change > 0) ? 'Worsening' : 'Stable');

                        // 3. Tissue Composition Analysis (Granulation Change: Positive change = Healing/Good)
                        $granulation_change = floatval($latest['granulation_percent']) - floatval($previous['granulation_percent']);
                        // Tissue calculation now only uses slough_percent (assuming eschar/necrotic is zero for now)
                        $slough_eschar_total_change = (floatval($latest['slough_percent'])) -
                            (floatval($previous['slough_percent']));
                        $tissue_trend = ($granulation_change > 0 && $slough_eschar_total_change <= 0) ? 'Positive' : (($granulation_change <= 0 && $slough_eschar_total_change > 0) ? 'Negative' : 'Neutral');

                        // --- Generate Final Trajectory Summary ---
                        $summary = [
                            'area' => [
                                'latest_area' => $latest_area,
                                'previous_area' => $previous_area,
                                'change_cm2' => $area_change,
                                'percent_change' => $area_percent_change,
                                'trend' => $area_trend,
                            ],
                            'depth' => [
                                'latest_depth' => floatval($latest['depth_cm']),
                                'previous_depth' => floatval($previous['depth_cm']),
                                'change_cm' => $depth_change,
                                'percent_change' => $depth_percent_change,
                                'trend' => $depth_trend,
                            ],
                            'tissue' => [
                                'granulation_change' => $granulation_change,
                                'slough_eschar_change' => $slough_eschar_total_change, // Note: This only uses slough now
                                'trend' => $tissue_trend,
                                'granulation_latest' => floatval($latest['granulation_percent']),
                                'necrotic_data_missing' => true // Flag for client side
                            ],
                            'latest_date' => $latest['assessment_date'],
                            'previous_date' => $previous['assessment_date'],
                        ];

                        $response = ['success' => true, 'trajectory_summary' => $summary, 'historical_data' => $assessments];

                    } else {
                        $response = ['success' => true, 'message' => 'Trajectory analysis requires at least two assessments for comparison.', 'historical_data' => $assessments];
                    }
                }
            } else {
                $response = ['success' => false, 'message' => 'Missing or invalid wound ID received.'];
            }
        }
    }
}

// END: Clean the output buffer and echo the JSON response.
ob_end_clean();
echo json_encode($response);
?>
