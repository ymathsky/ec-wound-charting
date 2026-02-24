<?php
// Filename: api/get_patient_to_calendar.php
// Purpose: Fetches basic patient information (ID, first name, last name)
// for populating filters and scheduling forms on the calendar page.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // SQL query to select patient details. Ordered by last name for better usability.
    $sql = "SELECT 
                patient_id,
                first_name,
                last_name
            FROM patients
            ORDER BY last_name, first_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = [
            'patient_id' => $row['patient_id'],
            // Ensure array keys are exactly 'first_name' and 'last_name'
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name']
        ];
    }

    http_response_code(200);
    echo json_encode($patients);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching patient data.", "error" => $e->getMessage()]);
}

$conn->close();
?>