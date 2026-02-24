<?php
// Filename: api/get_all_appointments.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get filter parameters from the request
$filter_user_id = isset($_GET['user_id']) && !empty($_GET['user_id']) ? intval($_GET['user_id']) : null;
$filter_patient_id = isset($_GET['patient_id']) && !empty($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

try {
    // Base SQL query
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.status,
                a.user_id,
                a.appointment_type,  -- <-- ADDED
                a.notes,             -- <-- ADDED
                p.patient_id,
                p.first_name,
                p.last_name,
                p.contact_number,    -- <-- ADDED
                p.address,           -- <-- ADDED
                u.full_name as clinician_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN users u ON a.user_id = u.user_id";

    // Dynamically add WHERE clauses based on filters
    $where_clauses = [];
    $params = [];
    $types = '';

    if ($filter_user_id) {
        $where_clauses[] = "a.user_id = ?";
        $params[] = &$filter_user_id;
        $types .= 'i';
    }

    if ($filter_patient_id) {
        $where_clauses[] = "a.patient_id = ?";
        $params[] = &$filter_patient_id;
        $types .= 'i';
    }

    if ($filter_status) {
        $where_clauses[] = "a.status = ?";
        $params[] = &$filter_status;
        $types .= 's';
    }

    if (count($where_clauses) > 0) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    $stmt = $conn->prepare($sql);

    // Bind parameters if they exist
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $appointments_raw = $result->fetch_all(MYSQLI_ASSOC);

    // --- Process into two types of events: individual appointments and daily counts ---
    $events = [];
    $daily_counts = [];

    foreach ($appointments_raw as $appt) {
        // 1. Create the individual, clickable event
        $events[] = [
            'id' => $appt['appointment_id'],
            'title' => $appt['last_name'] . ', ' . $appt['first_name'],
            'start' => $appt['appointment_date'],
            'extendedProps' => [
                'clinician' => $appt['clinician_name'],
                'patient_id' => $appt['patient_id'],
                'user_id' => $appt['user_id'],
                'appointment_type' => $appt['appointment_type'],
                'notes' => $appt['notes'],
                'contact_number' => $appt['contact_number'], // <-- ADDED
                'address' => $appt['address']                // <-- ADDED
            ],
            'status' => $appt['status']
        ];

        // 2. Tally counts for each day
        $date = date('Y-m-d', strtotime($appt['appointment_date']));
        if (!isset($daily_counts[$date])) {
            $daily_counts[$date] = 0;
        }
        $daily_counts[$date]++;
    }

    // 3. Create the non-clickable daily count events
    foreach ($daily_counts as $date => $count) {
        if ($count > 0) {
            $events[] = [
                'id' => 'count-' . $date,
                'title' => $count . ($count > 1 ? ' Schedules' : ' Schedule'),
                'start' => $date,
                'allDay' => true,
                'classNames' => ['daily-count-event'], // Custom class for styling and interaction control
                'display' => 'block'
            ];
        }
    }


    http_response_code(200);
    echo json_encode($events);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching appointments.", "error" => $e->getMessage()]);
}

$conn->close();
?>