<?php
// Filename: api/get_patients_by_clinician_to_calendar.php
// Purpose: Fetches unique patients assigned to a specific clinician (user_id).

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Get the clinician ID from the query string
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

if (!$user_id) {
    // If no clinician ID is provided, return an empty array of patients
    http_response_code(200);
    echo json_encode(['patients' => []]);
    $conn->close();
    exit();
}

try {
    // SQL query to find distinct patients associated with the given user_id
    $sql = "SELECT DISTINCT
                p.patient_id,
                p.first_name,  -- Ensure this column exists in the database
                p.last_name    -- Ensure this column exists in the database
            FROM patients p
            JOIN appointments a ON p.patient_id = a.patient_id
            WHERE a.user_id = ?
            ORDER BY p.last_name, p.first_name";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = [
            'patient_id' => $row['patient_id'],
            // Use array keys directly from the database result
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name']
        ];
    }

    http_response_code(200);
    echo json_encode(['patients' => $patients]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching assigned patient data.", "error" => $e->getMessage()]);
}

$conn->close();
?>