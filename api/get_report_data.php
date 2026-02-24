<?php
// Filename: api/get_report_data.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

session_start();
// --- Role-based Access Control ---
// FIX: Added 'clinician' to the allowed roles to prevent 403 error on report page load.
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'facility', 'clinician'])) {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$user_role = $_SESSION['ec_role'];

// --- Build WHERE clauses for filtering based on role ---
$patient_where_clause = '';
$wound_where_clause = '';
$appointment_where_clause = '';
$superbill_where_clause = '';
$clinician_activity_filter = ''; // Used for filtering clinician activity itself

if ($user_role === 'facility') {
    $patient_where_clause = " WHERE facility_id = " . intval($user_id);
    // Wounds, Appointments, and Superbills relate to patients, so filter them based on facility's patients
    $wound_where_clause = " WHERE patient_id IN (SELECT patient_id FROM patients WHERE facility_id = " . intval($user_id) . ")";
    $appointment_where_clause = " WHERE patient_id IN (SELECT patient_id FROM patients WHERE facility_id = " . intval($user_id) . ")";
    $superbill_where_clause = " WHERE appointment_id IN (SELECT appointment_id FROM appointments WHERE patient_id IN (SELECT patient_id FROM patients WHERE facility_id = " . intval($user_id) . "))";
    // FIX: Changed 'WHERE' to 'AND' to prevent SQL syntax error
    $clinician_activity_filter = " AND u.user_id IN (SELECT primary_user_id FROM patients WHERE facility_id = " . intval($user_id) . ")";
} elseif ($user_role === 'clinician') {
    // Clinician only sees data for patients primarily assigned to them
    $patient_where_clause = " WHERE primary_user_id = " . intval($user_id);
    $wound_where_clause = " WHERE patient_id IN (SELECT patient_id FROM patients WHERE primary_user_id = " . intval($user_id) . ")";
    $appointment_where_clause = " WHERE patient_id IN (SELECT patient_id FROM patients WHERE primary_user_id = " . intval($user_id) . ")";
    $superbill_where_clause = " WHERE appointment_id IN (SELECT appointment_id FROM appointments WHERE patient_id IN (SELECT patient_id FROM patients WHERE primary_user_id = " . intval($user_id) . "))";
    // FIX: Changed 'WHERE' to 'AND' to prevent SQL syntax error
    $clinician_activity_filter = " AND u.user_id = " . intval($user_id);
}
// Admin filters remain empty (sees all data)


try {
    $response = [
        'healing_rates' => getHealingRates($conn, $wound_where_clause),
        'cpt_utilization' => getCptUtilization($conn, $superbill_where_clause),
        // Pass the new filter string to getClinicianActivity
        'clinician_activity' => getClinicianActivity($conn, $clinician_activity_filter),
        'patient_demographics' => getPatientDemographics($conn, $patient_where_clause),
        'appointment_status' => getAppointmentStatusDistribution($conn, $appointment_where_clause),
        'wound_type_distribution' => getWoundTypeDistribution($conn, $wound_where_clause),
        'operational_metrics' => getOperationalMetrics($conn, $appointment_where_clause, $patient_where_clause),
        'visit_frequency_distribution' => getVisitFrequencyDistribution($conn, $appointment_where_clause)
    ];

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while generating reports.", "error" => $e->getMessage()]);
}

$conn->close();


// --- Report Generation Functions (Updated to accept WHERE clauses) ---

function getHealingRates($conn, $wound_where_clause) {
    // FIX 1: Correctly inject WHERE/AND to prevent syntax error when $wound_where_clause is empty.
    $where_start = empty($wound_where_clause) ? " WHERE " : $wound_where_clause . " AND ";

    $sql = "SELECT w.wound_type, AVG(DATEDIFF(wa_healed.assessment_date, w.date_onset)) as avg_healing_days
            FROM wounds w
            JOIN wound_assessments wa_healed ON w.wound_id = wa_healed.wound_id
            " . $where_start . " w.status = 'Healed'
            GROUP BY w.wound_type";
    $result = $conn->query($sql);
    $labels = [];
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['wound_type'];
            $data[] = round($row['avg_healing_days']);
        }
    }
    return ['labels' => $labels, 'data' => $data];
}

function getCptUtilization($conn, $superbill_where_clause) {
    $sql = "SELECT cpt_code, COUNT(*) as count 
            FROM superbill_services 
            " . $superbill_where_clause . "
            GROUP BY cpt_code 
            ORDER BY count DESC 
            LIMIT 5";
    $result = $conn->query($sql);
    $labels = [];
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['cpt_code'];
            $data[] = (int)$row['count'];
        }
    }
    return ['labels' => $labels, 'data' => $data];
}

// Updated signature to accept the dynamic filter
function getClinicianActivity($conn, $clinician_activity_filter) {
    $sql = "SELECT u.full_name as clinician_name, 
                   COUNT(DISTINCT p.patient_id) as assigned_patients,
                   COUNT(DISTINCT a.appointment_id) as completed_appointments,
                   (SELECT COUNT(DISTINCT w.wound_id) FROM wounds w WHERE w.patient_id IN (SELECT p2.patient_id FROM patients p2 WHERE p2.primary_user_id = u.user_id)) as total_wounds_managed
            FROM users u
            LEFT JOIN patients p ON u.user_id = p.primary_user_id
            LEFT JOIN appointments a ON u.user_id = a.user_id AND a.status = 'Completed'
            WHERE u.role = 'clinician'
            " . $clinician_activity_filter . "
            GROUP BY u.user_id, u.full_name
            ORDER BY completed_appointments DESC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPatientDemographics($conn, $patient_where_clause) {
    $sql = "SELECT date_of_birth FROM patients" . $patient_where_clause;
    $result = $conn->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $age_groups = [
        "0-18" => 0, "19-35" => 0, "36-50" => 0,
        "51-65" => 0, "66-80" => 0, "81+" => 0
    ];

    foreach ($rows as $row) {
        if (!empty($row['date_of_birth'])) {
            $birthDate = new DateTime($row['date_of_birth']);
            $today = new DateTime('today');
            $age = $birthDate->diff($today)->y;

            if ($age <= 18) $age_groups["0-18"]++;
            elseif ($age <= 35) $age_groups["19-35"]++;
            elseif ($age <= 50) $age_groups["36-50"]++;
            elseif ($age <= 65) $age_groups["51-65"]++;
            elseif ($age <= 80) $age_groups["66-80"]++;
            else $age_groups["81+"]++;
        }
    }

    return ['labels' => array_keys($age_groups), 'data' => array_values($age_groups)];
}

function getAppointmentStatusDistribution($conn, $appointment_where_clause) {
    $sql = "SELECT status, COUNT(*) as count FROM appointments " . $appointment_where_clause . " GROUP BY status";
    $result = $conn->query($sql);
    $labels = [];
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['status'];
            $data[] = (int)$row['count'];
        }
    }
    return ['labels' => $labels, 'data' => $data];
}

function getWoundTypeDistribution($conn, $wound_where_clause) {
    // FIX 2: Correctly inject WHERE/AND to prevent syntax error when $wound_where_clause is empty.
    $where_start = empty($wound_where_clause) ? " WHERE " : $wound_where_clause . " AND ";

    $sql = "SELECT wound_type, COUNT(*) as count FROM wounds " . $where_start . " status = 'Active' GROUP BY wound_type";
    $result = $conn->query($sql);
    $labels = [];
    $data = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $labels[] = $row['wound_type'];
            $data[] = (int)$row['count'];
        }
    }
    return ['labels' => $labels, 'data' => $data];
}


function getOperationalMetrics($conn, $appointment_where_clause, $patient_where_clause) {
    $metrics = [
        'avg_visits_per_patient' => 0,
        'cancellation_rate' => 0,
        'avg_patient_age' => 0
    ];

    // Avg Visits Per Patient
    $sql_visits = "SELECT COUNT(*) as total_appointments, COUNT(DISTINCT patient_id) as total_patients FROM appointments" . $appointment_where_clause;
    $result_visits = $conn->query($sql_visits);
    $visit_stats = $result_visits ? $result_visits->fetch_assoc() : null;
    if ($visit_stats && $visit_stats['total_patients'] > 0) {
        $metrics['avg_visits_per_patient'] = round($visit_stats['total_appointments'] / $visit_stats['total_patients'], 1);
    }

    // Cancellation Rate
    $sql_status = "SELECT status, COUNT(*) as count FROM appointments " . $appointment_where_clause . " GROUP BY status";
    $result_status = $conn->query($sql_status);
    $status_counts = ['Scheduled' => 0, 'Completed' => 0, 'Cancelled' => 0];
    $total_appointments = 0;
    if ($result_status) {
        while($row = $result_status->fetch_assoc()) {
            if(isset($status_counts[$row['status']])) {
                $status_counts[$row['status']] = (int)$row['count'];
            }
            $total_appointments += (int)$row['count']; // Sum all statuses
        }
    }
    if ($total_appointments > 0) {
        $metrics['cancellation_rate'] = round(($status_counts['Cancelled'] / $total_appointments) * 100, 1);
    }

    // Avg Patient Age
    $sql_age = "SELECT AVG(DATEDIFF(CURDATE(), date_of_birth) / 365.25) as avg_age FROM patients" . $patient_where_clause;
    $result_age = $conn->query($sql_age);
    $age_data = $result_age ? $result_age->fetch_assoc() : null;
    if ($age_data && $age_data['avg_age']) {
        $metrics['avg_patient_age'] = round($age_data['avg_age'], 0);
    }

    return $metrics;
}

function getVisitFrequencyDistribution($conn, $appointment_where_clause) {
    $sql = "SELECT patient_id, COUNT(appointment_id) as visit_count 
            FROM appointments 
            " . $appointment_where_clause . "
            GROUP BY patient_id";
    $result = $conn->query($sql);
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $frequency_groups = [
        "1 Visit" => 0,
        "2-5 Visits" => 0,
        "6-10 Visits" => 0,
        "11+ Visits" => 0
    ];

    foreach($rows as $row) {
        $count = (int)$row['visit_count'];
        if ($count == 1) {
            $frequency_groups["1 Visit"]++;
        } elseif ($count <= 5) {
            $frequency_groups["2-5 Visits"]++;
        } elseif ($count <= 10) {
            $frequency_groups["6-10 Visits"]++;
        } else {
            $frequency_groups["11+ Visits"]++;
        }
    }

    return ['labels' => array_keys($frequency_groups), 'data' => array_values($frequency_groups)];
}
?>

