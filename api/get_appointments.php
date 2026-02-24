<?php
// Filename: api/get_appointments.php

// Start Session to get User Context ---
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get logged-in user details
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';

// Set the default timezone to avoid date discrepancies

// Check for date parameter
if (isset($_GET['date']) && !empty($_GET['date'])) {
    $target_date = $_GET['date'];
} else {
    $target_date = date('Y-m-d');
}

// Fetch appointments scheduled for the target date
$today_start = $target_date . ' 00:00:00';
$today_end = $target_date . ' 23:59:59';

try {
    // --- Base SQL Query & Parameters ---
    $sql_where = " WHERE a.appointment_date BETWEEN ? AND ?";
    $params = [&$today_start, &$today_end];
    $types = 'ss';

    // --- Apply Access Control Filtering ---
    if ($user_role === 'clinician') {
        // Clinicians only see appointments where they are the assigned user (user_id is the clinician field in appointments)
        $sql_where .= " AND a.user_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    } elseif ($user_role === 'facility') {
        // Facilities only see appointments for patients assigned to their facility
        $sql_where .= " AND p.facility_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    }
    // Admins have no additional filters

    // We join patients and users tables to get their names
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.status,
                a.check_in_time,
                p.patient_id,
                CONCAT('EC0', p.patient_id) AS patient_code, -- <<< MODIFIED: Create patient ID as EC + primary key
                p.first_name,
                p.last_name,
                p.date_of_birth,
                p.contact_number,
                p.address,
                u.user_id,
                u.full_name as clinician_name,
                vn.is_signed
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN users u ON a.user_id = u.user_id
            LEFT JOIN visit_notes vn ON a.appointment_id = vn.appointment_id
            " . $sql_where . "
            ORDER BY a.appointment_date ASC";

    $stmt = $conn->prepare($sql);

    // Dynamically bind parameters
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($appointments);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching appointments.", "error" => $e->getMessage()]);
}

$conn->close(); // <<< FIX: Connection explicitly closed
?>
