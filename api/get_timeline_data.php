<?php
// Filename: api/get_timeline_data.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../db_connect.php';

// 1. Auth Check
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$user_role = $_SESSION['ec_role'];

// 2. Validate Role (Admin, Clinician, Scheduler)
if (!in_array($user_role, ['admin', 'clinician', 'scheduler'])) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied"]);
    exit();
}

try {
    // 3. Build Query
    // NOTE: Based on your DB, we use 'appointment_date' (DATETIME) and 'appointment_type'.
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                a.status,
                a.appointment_type,
                p.patient_id,
                p.first_name AS patient_first,
                p.last_name AS patient_last,
                p.patient_code,
                u.full_name AS clinician_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN users u ON a.user_id = u.user_id";

    // 4. Apply Role-Based Filtering
    if ($user_role === 'clinician') {
        // Clinicians only see their own appointments
        $sql .= " WHERE a.user_id = ?";
    }

    // Default sorting: Newest appointments first
    $sql .= " ORDER BY a.appointment_date DESC";

    $stmt = $conn->prepare($sql);

    if ($user_role === 'clinician') {
        $stmt->bind_param("i", $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        // Split the single DATETIME column into separate readable Date and Time strings
        $timestamp = strtotime($row['appointment_date']);
        $row['formatted_date'] = date('M d, Y', $timestamp);
        $row['formatted_time'] = date('h:i A', $timestamp);

        // Ensure status is capitalized for display
        $row['status'] = ucfirst($row['status']);

        $appointments[] = $row;
    }

    echo json_encode(["success" => true, "data" => $appointments, "role" => $user_role]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: " . $e->getMessage()]);
}

$conn->close();
?>