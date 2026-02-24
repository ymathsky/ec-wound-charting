<?php
// Filename: api/get_patients.php

session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db_connect.php';

// Check for user authentication
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access. User session not found."]);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$user_role = $_SESSION['ec_role'];

try {
    // Base SQL query: Select all necessary patient fields and join with users table
    // for Clinician and Facility names.
    $sql = "SELECT 
                p.patient_id, p.patient_code, p.first_name, p.last_name, 
                p.date_of_birth, p.gender, p.status, 
                p.primary_user_id, p.facility_id,
                u.full_name as primary_clinician_name,
                f.full_name as facility_name
            FROM patients p
            LEFT JOIN users u ON p.primary_user_id = u.user_id
            LEFT JOIN users f ON p.facility_id = f.user_id";

    $where_clauses = [];
    $params = [];
    $param_types = "";

    // --- Role-Based Access Control (RBAC) Filtering ---

    // Admins and Schedulers see all patients (no WHERE clause needed for them)
    if (in_array($user_role, ['clinician'])) {
        // Clinicians only see patients explicitly assigned to them
        $where_clauses[] = "p.primary_user_id = ?";
        $params[] = $user_id;
        $param_types .= "i";
    } elseif ($user_role === 'facility') {
        // Facility users only see patients explicitly assigned to their facility ID
        $where_clauses[] = "p.facility_id = ?";
        $params[] = $user_id;
        $param_types .= "i";
    }

    // Combine WHERE clauses
    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(' AND ', $where_clauses);
    }

    // Always order by last name for consistency
    $sql .= " ORDER BY p.last_name ASC";

    // Prepare the statement
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception("SQL Prepare failed: " . $conn->error);
    }

    // Bind parameters if necessary
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }

    // Execute and fetch results
    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // REMOVED $conn->close() HERE to allow script to finish successfully

    // Transform data for frontend readiness (add full_name for search)
    $formatted_patients = array_map(function($patient) {
        $patient['full_name'] = $patient['first_name'] . ' ' . $patient['last_name'];
        return $patient;
    }, $patients);


    // Send successful response
    http_response_code(200);
    echo json_encode(["success" => true, "patients" => $formatted_patients]);

} catch (Exception $e) {
    // Log the error
    error_log("Patient API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Server Error: Could not fetch patient data.", "error" => $e->getMessage()]);
}
// The connection will automatically close when the script finishes.
?>