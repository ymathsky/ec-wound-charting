<?php
// Filename: api/get_chart_data.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get logged-in user details for RBAC filtering
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';

try {
    // --- 1. Patient Status Distribution (Grouped by Gender) ---
    $status_data = [];
    
    // Base query
    $sql_status = "SELECT p.gender as status, COUNT(p.patient_id) as count FROM patients p";
    
    // RBAC Filtering
    $where_clause = "";
    $params = [];
    $types = "";

    if ($user_role === 'clinician') {
        $where_clause = " WHERE p.primary_user_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    } elseif ($user_role === 'facility') {
        $where_clause = " WHERE p.facility_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    }

    // Append Group By
    $sql_status .= $where_clause . " GROUP BY p.gender";

    // Execute
    if ($types) {
        $stmt = $conn->prepare($sql_status);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result_status = $stmt->get_result();
    } else {
        $result_status = $conn->query($sql_status);
    }

    if ($result_status && $result_status->num_rows > 0) {
        while ($row = $result_status->fetch_assoc()) {
            // Handle null or empty gender
            $status_label = !empty($row['status']) ? $row['status'] : 'Unknown';
            $status_data[$status_label] = $row['count'];
        }
    } else {
        // If no data found, return empty arrays rather than mock data to be accurate
        // Or keep mock data if preferred for demo purposes. Let's return empty to be safe.
        // $status_data = ['Male' => 0, 'Female' => 0]; 
    }

    // --- 2. Monthly Appointments Trend (Last 6 Months) ---

    $monthly_data = [];
    $current_month = date('Y-m');
    $month_labels = [];

    // Generate the last 6 month strings (e.g., 2025-01)
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i month"));
        $month_labels[] = $month;
        $monthly_data[$month] = 0; // Initialize count
    }

    // Filter by the last 6 months
    $first_month_start = $month_labels[0] . '-01 00:00:00';
    $last_month_end = date('Y-m-t 23:59:59');

    $params_appts = [&$first_month_start, &$last_month_end];
    $types_appts = 'ss';

    // RBAC Filtering for Appointments
    $where_clause_appts = "";
    
    if ($user_role === 'clinician') {
        $where_clause_appts = " AND p.primary_user_id = ?";
        $params_appts[] = &$user_id;
        $types_appts .= 'i';
    } elseif ($user_role === 'facility') {
        $where_clause_appts = " AND p.facility_id = ?";
        $params_appts[] = &$user_id;
        $types_appts .= 'i';
    }

    $final_sql_appointments = "SELECT DATE_FORMAT(a.appointment_date, '%Y-%m') as month, COUNT(a.appointment_id) as count
                               FROM appointments a
                               JOIN patients p ON a.patient_id = p.patient_id
                               WHERE a.appointment_date BETWEEN ? AND ? " . $where_clause_appts . "
                               GROUP BY month ORDER BY month ASC";

    $stmt_appts = $conn->prepare($final_sql_appointments);
    $stmt_appts->bind_param($types_appts, ...$params_appts);
    $stmt_appts->execute();
    $result_appts = $stmt_appts->get_result();

    if ($result_appts && $result_appts->num_rows > 0) {
        while ($row = $result_appts->fetch_assoc()) {
            $monthly_data[$row['month']] = intval($row['count']);
        }
    }

    // Format monthly data for Chart.js
    $chart_monthly_labels = [];
    $chart_monthly_data = [];
    foreach ($month_labels as $month_key) {
        $chart_monthly_labels[] = date('M', strtotime($month_key));
        $chart_monthly_data[] = $monthly_data[$month_key] ?? 0;
    }

    // --- Final Response Assembly ---
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'patient_status' => [
            'labels' => array_keys($status_data),
            'data' => array_values($status_data)
        ],
        'monthly_appointments' => [
            'labels' => $chart_monthly_labels,
            'data' => $chart_monthly_data
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while fetching chart data.',
        'error' => $e->getMessage()
    ]);
}
?>
