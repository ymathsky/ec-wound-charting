<?php
// Filename: api/get_dashboard_stats.php

// Start output buffering to prevent accidental HTML/error output before JSON header
ob_start();

session_start(); // Start session to get user role/ID
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get logged-in user details
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';

// Initialize the response structure
$response = [
    'stats' => [
        'total_patients' => 0,
        'active_wounds' => 0,
        'assessments_today' => 0,
        'new_patients_month' => 0,
        'high_risk_wounds' => 0 // NEW STAT: Count of overdue assessments
    ],
    'todays_appointments' => [],
    'follow_up_watchlist' => [] // NEW LIST: Details of overdue patients
];

// --- Role-Based Filtering Setup ---
$patient_filter_sql = "";
$appointment_filter_sql = "";
$wound_filter_sql = "";
$params = [];
$types = '';

// Initialize variables used in the query binding logic outside the conditions
$params_patient = [];
$types_patient = '';

if ($user_role === 'clinician') {
    // Clinician sees patients/appointments/wounds assigned to them
    $patient_filter_sql = " WHERE primary_user_id = ?";
    // Appointments join `patients` table, so no need for separate filter here if the join handles it.
    // However, it's safer to filter appointments directly by `a.user_id`.
    $appointment_filter_sql = " AND a.user_id = ?";
    $wound_filter_sql = " JOIN patients p ON w.patient_id = p.patient_id WHERE p.primary_user_id = ?";

    $params_patient = [&$user_id];
    $types_patient = 'i';

    $params_wound = [&$user_id];
    $types_wound = 'i';
} elseif ($user_role === 'facility') {
    // Facility user sees patients/appointments/wounds assigned to their facility
    $patient_filter_sql = " WHERE facility_id = ?";
    $appointment_filter_sql = " AND p.facility_id = ?";
    $wound_filter_sql = " WHERE p.facility_id = ?";

    $params_patient = [&$user_id];
    $types_patient = 'i';

    $params_wound = [&$user_id];
    $types_wound = 'i';
}
// For Admin, filters remain empty (variables retain their empty initial state)


try {
    // --- Fetch Stats (Applying filters where necessary) ---
    // 1. Total Patients (Filtered)
    $sql = "SELECT COUNT(*) as total FROM patients" . $patient_filter_sql;
    $stmt = $conn->prepare($sql);
    // CRITICAL FIX: Only bind parameters if they exist
    if (!empty($params_patient)) { $stmt->bind_param($types_patient, ...$params_patient); }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $response['stats']['total_patients'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // 2. Active Wounds (Filtered - requires join for clinician/facility)
    $sql_wounds = "SELECT COUNT(w.wound_id) as total FROM wounds w";
    if ($user_role === 'clinician' || $user_role === 'facility') {
        // Use $patient_filter_sql here which includes WHERE clause
        $sql_wounds = "SELECT COUNT(w.wound_id) as total FROM wounds w JOIN patients p ON w.patient_id = p.patient_id" . $patient_filter_sql . " AND w.status = 'Active'";
    } else {
        $sql_wounds .= " WHERE status = 'Active'";
    }

    $stmt = $conn->prepare($sql_wounds);
    // CRITICAL FIX: Only bind parameters if they exist
    if (!empty($params_patient)) { $stmt->bind_param($types_patient, ...$params_patient); }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $response['stats']['active_wounds'] = $result->fetch_assoc()['total'];
    $stmt->close();


    // 3. Assessments Today (Not filtered, as any assessment counts for the system overview)
    $today = date('Y-m-d');
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM wound_assessments WHERE assessment_date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $response['stats']['assessments_today'] = $result->fetch_assoc()['total'];
    $stmt->close();

    // 4. New Patients This Month (Filtered)
    $month_start = date('Y-m-01');
    // If $patient_filter_sql is empty (Admin), the query starts with 'SELECT COUNT(*) as total FROM patients AND created_at >= ?' which is invalid.
    // We must handle the WHERE keyword insertion correctly.
    if (!empty($patient_filter_sql)) {
        $sql = "SELECT COUNT(*) as total FROM patients" . $patient_filter_sql . " AND created_at >= ?";
    } else {
        // Admin Case
        $sql = "SELECT COUNT(*) as total FROM patients WHERE created_at >= ?";
    }

    $types_month = $types_patient . 's';
    $params_month = array_merge($params_patient, [&$month_start]);

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types_month, ...$params_month);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $response['stats']['new_patients_month'] = $result->fetch_assoc()['total'];
    $stmt->close();


    // 5. --- NEW STAT: High-Risk Wounds (Assessment Overdue > 7 days) ---
    $seven_days_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

    $sql_high_risk = "SELECT COUNT(w.wound_id) AS total
                      FROM wounds w
                      LEFT JOIN (
                          SELECT wound_id, MAX(assessment_date) AS last_assessment
                          FROM wound_assessments
                          GROUP BY wound_id
                      ) wa ON w.wound_id = wa.wound_id
                      JOIN patients p ON w.patient_id = p.patient_id 
                      WHERE w.status = 'Active' 
                      AND (wa.last_assessment IS NULL OR wa.last_assessment < ?)";

    $high_risk_params = [&$seven_days_ago];
    $high_risk_types = 's';

    // Apply RBAC filters for high-risk count
    if (!empty($patient_filter_sql)) {
        if ($user_role === 'clinician') {
            $sql_high_risk .= " AND p.primary_user_id = ?";
        } elseif ($user_role === 'facility') {
            $sql_high_risk .= " AND p.facility_id = ?";
        }
        $high_risk_params[] = &$user_id;
        $high_risk_types .= 'i';
    }

    $stmt = $conn->prepare($sql_high_risk);
    $stmt->bind_param($high_risk_types, ...$high_risk_params);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) $response['stats']['high_risk_wounds'] = $result->fetch_assoc()['total'];
    $stmt->close();


    // 6. --- NEW LIST: Follow-Up Watchlist (Overdue Assessments, Limit 5) ---
    $sql_watchlist = "SELECT
                          w.wound_id,
                          w.location,
                          p.patient_id,
                          p.first_name,
                          p.last_name,
                          wa.last_assessment,
                          DATEDIFF(NOW(), wa.last_assessment) AS days_overdue
                      FROM wounds w
                      JOIN patients p ON w.patient_id = p.patient_id
                      LEFT JOIN (
                          SELECT wound_id, MAX(assessment_date) AS last_assessment
                          FROM wound_assessments
                          GROUP BY wound_id
                      ) wa ON w.wound_id = wa.wound_id
                      WHERE w.status = 'Active'
                      AND (wa.last_assessment IS NULL OR wa.last_assessment < ?)";

    $watchlist_params = [&$seven_days_ago];
    $watchlist_types = 's';

    // Apply RBAC filters for the watchlist
    if (!empty($patient_filter_sql)) {
        if ($user_role === 'clinician') {
            $sql_watchlist .= " AND p.primary_user_id = ?";
        } elseif ($user_role === 'facility') {
            $sql_watchlist .= " AND p.facility_id = ?";
        }
        $watchlist_params[] = &$user_id;
        $watchlist_types .= 'i';
    }

    $sql_watchlist .= " ORDER BY wa.last_assessment ASC, w.wound_id ASC LIMIT 5";

    $stmt = $conn->prepare($sql_watchlist);
    $stmt->bind_param($watchlist_types, ...$watchlist_params);
    $stmt->execute();
    $result_watchlist = $stmt->get_result();

    if ($result_watchlist && $result_watchlist->num_rows > 0) {
        while($row = $result_watchlist->fetch_assoc()) {
            $row['reason'] = $row['last_assessment'] ? "Overdue ({$row['days_overdue']} days)" : "No Assessment Recorded";
            $row['last_assessment_formatted'] = $row['last_assessment'] ? date("M j, Y", strtotime($row['last_assessment'])) : 'N/A';
            unset($row['days_overdue']);
            unset($row['last_assessment']);
            $response['follow_up_watchlist'][] = $row;
        }
    }
    $stmt->close();


    // --- Fetch Today's Appointments (Filtered) ---
    $today_start = date('Y-m-d 00:00:00');
    $today_end = date('Y-m-d 23:59:59');

    // Base query including join to patients for filtering by facility_id
    $sql_appointments = "SELECT 
                            p.first_name, 
                            p.last_name, 
                            a.appointment_date, 
                            a.appointment_id, 
                            p.patient_id, 
                            a.user_id
                         FROM appointments a
                         JOIN patients p ON a.patient_id = p.patient_id
                         WHERE a.appointment_date BETWEEN ? AND ?";

    $app_params = [&$today_start, &$today_end];
    $app_types = 'ss';

    if ($user_role === 'clinician') {
        $sql_appointments .= " AND a.user_id = ?";
        $app_params[] = &$user_id;
        $app_types .= 'i';
    } elseif ($user_role === 'facility') {
        $sql_appointments .= " AND p.facility_id = ?";
        $app_params[] = &$user_id;
        $app_types .= 'i';
    }

    $sql_appointments .= " ORDER BY a.appointment_date ASC LIMIT 5";
    $stmt = $conn->prepare($sql_appointments);
    $stmt->bind_param($app_types, ...$app_params);
    $stmt->execute();
    $result_appointments = $stmt->get_result();

    if ($result_appointments && $result_appointments->num_rows > 0) {
        while($row = $result_appointments->fetch_assoc()) {
            $row['appointment_time'] = date("g:i A", strtotime($row['appointment_date']));
            unset($row['appointment_date']);
            $response['todays_appointments'][] = $row;
        }
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode($response);
    ob_end_flush(); // Send buffered output

} catch (Exception $e) {
    // Clear any previous output and send error response
    ob_clean();
    http_response_code(500);
    echo json_encode(['message' => 'An error occurred on the server.', 'error' => $e->getMessage()]);
    ob_end_flush(); // Send error output
}

$conn->close();
?>
