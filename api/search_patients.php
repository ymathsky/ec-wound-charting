<?php
// Filename: ec/api/search_patients.php
// Description: Dedicated API endpoint for live patient search with RBAC.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get logged-in user details for RBAC filtering
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';

// Get the search term from the query string
$search_term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (empty($search_term)) {
    // Return empty results gracefully if no search term is provided
    http_response_code(200);
    echo json_encode([]);
    exit();
}

try {
    $search_pattern = '%' . $search_term . '%';

    // --- Base SQL Query for Search ---
    // Join with users table twice to get the assigned doctor's name and the assigned facility's name (assuming facility_id references users.user_id)
    $sql = "SELECT 
                p.patient_id, p.patient_code, p.first_name, p.last_name, p.date_of_birth,
                u.full_name AS primary_doctor_name, f.full_name AS facility_name 
            FROM patients p
            LEFT JOIN users u ON p.primary_user_id = u.user_id
            LEFT JOIN users f ON p.facility_id = f.user_id";

    // --- Search Conditions ---
    // Search across first name, last name, patient code, or date of birth
    $search_conditions = " WHERE (p.first_name LIKE ? OR p.last_name LIKE ? OR p.patient_code LIKE ? OR p.date_of_birth LIKE ?)";
    $params = [&$search_pattern, &$search_pattern, &$search_pattern, &$search_pattern];
    $types = 'ssss';

    // --- Apply Access Control Filtering (RBAC) ---
    if ($user_role === 'clinician') {
        // Clinicians only see patients where they are the primary doctor
        $search_conditions .= " AND p.primary_user_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    } elseif ($user_role === 'facility') {
        // Facilities only see patients assigned to their facility ID
        $search_conditions .= " AND p.facility_id = ?";
        $params[] = &$user_id;
        $types .= 'i';
    }
    // Admins get the base query (all patients)

    $sql .= $search_conditions . " ORDER BY p.last_name ASC LIMIT 10";

    $stmt = $conn->prepare($sql);

    // Dynamic parameter binding
    $stmt->bind_param($types, ...$params);

    $stmt->execute();
    $result = $stmt->get_result();
    $patients = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    http_response_code(200);
    echo json_encode($patients);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred during patient search.", "error" => $e->getMessage()]);
}

$conn->close();
?>
