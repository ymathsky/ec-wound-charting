<?php
// Filename: ec/api/get_assigned_patients_on_appointment.php
// Description: Fetches a list of patients, filtered by the logged-in user's primary_user_id (clinician RBAC).

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Check for user authentication
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access."]);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$user_role = $_SESSION['ec_role'];

try {
    // Base query selects patient ID, code, and full name for the dropdown.
    // It joins with users table to filter efficiently.
    $sql = "SELECT patient_id, patient_code, first_name, last_name
            FROM patients";

    $params = [];
    $types = '';
    $where_clauses = [];

    // Role-based access control:
    // Clinicians (doctors) should only see patients where they are the primary doctor.
    if ($user_role === 'clinician') {
        $where_clauses[] = "primary_user_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    }
    // Admins and other roles see all patients (no WHERE clause needed)

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    // Order by last name for organized display
    $sql .= " ORDER BY last_name ASC";

    $stmt = $conn->prepare($sql);

    // Bind parameters if needed (for clinicians)
    if ($types) {
        $bind_params = array_merge([$types], $params);
        $stmt->bind_param(...$bind_params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Format output for the dropdown/select list
    $formatted_patients = array_map(function($p) {
        return [
            'id' => $p['patient_id'],
            'text' => $p['last_name'] . ', ' . $p['first_name'] . ' (' . $p['patient_code'] . ')'
        ];
    }, $patients);

    http_response_code(200);
    echo json_encode(["success" => true, "patients" => $formatted_patients]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}

$conn->close();
?>
