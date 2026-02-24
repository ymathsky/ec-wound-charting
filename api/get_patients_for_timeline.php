<?php
// Filename: api/get_patients_for_timeline.php
header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../db_connect.php';

// 1. Authentication Check
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$user_role = $_SESSION['ec_role'];
$allowed_roles = ['admin', 'clinician', 'scheduler', 'facility'];

// 2. Validate Role
if (!in_array($user_role, $allowed_roles)) {
    http_response_code(403);
    echo json_encode(["success" => false, "message" => "Access Denied."]);
    exit();
}

try {
    // Base SQL Query: Select unique patients who have appointments
    $sql = "SELECT DISTINCT
                p.patient_id, 
                p.patient_code, 
                p.first_name, 
                p.last_name,
                p.primary_user_id
            FROM patients p
            JOIN appointments a ON p.patient_id = a.patient_id";

    $whereClauses = [];
    $params = [];
    $paramTypes = "";

    // 3. Role-Based Access Control (RBAC) - Server-Side Filtering
    if ($user_role === 'clinician') {
        // Clinicians only see patients whose appointments are assigned to them.
        $whereClauses[] = "a.user_id = ?";
        $params[] = $user_id;
        $paramTypes .= "i";
    }
    // Admin/Scheduler/Facility see all patients that have appointments.

    // 4. Handle optional search term filtering (for autocomplete integration)
    $searchTerm = isset($_GET['term']) ? trim($_GET['term']) : '';
    if (!empty($searchTerm)) {
        $searchWildcard = "%" . $searchTerm . "%";
        $whereClauses[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ?)";
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $params[] = $searchWildcard;
        $paramTypes .= "sss";
    }

    if (count($whereClauses) > 0) {
        $sql .= " WHERE " . implode(" AND ", $whereClauses);
    }

    $sql .= " ORDER BY p.last_name ASC";

    $stmt = $conn->prepare($sql);

    // Bind parameters if any exist
    if (!empty($paramTypes)) {
        // Use the splat operator (...) to pass parameters dynamically
        $stmt->bind_param($paramTypes, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }

    echo json_encode(["success" => true, "patients" => $patients, "count" => count($patients)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error during patient retrieval: " . $e->getMessage()]);
}

$conn->close();
?>