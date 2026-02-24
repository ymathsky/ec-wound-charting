<?php
// Filename: api/get_assigned_clinicians.php
// Purpose: Fetches only the clinicians (users) who have appointments assigned to them.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // SQL query to select unique user_ids from the appointments table (assigned clinicians)
    // and join with the users table to get their full names.
    // NOTE: Using WHERE a.user_id IS NOT NULL ensures only assigned appointments count.
    $sql = "SELECT DISTINCT
                u.user_id,
                u.full_name
            FROM appointments a
            JOIN users u ON a.user_id = u.user_id
            WHERE a.user_id IS NOT NULL
            ORDER BY u.full_name";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $clinicians = [];
    while ($row = $result->fetch_assoc()) {
        $clinicians[] = [
            'user_id' => $row['user_id'],
            'full_name' => $row['full_name']
        ];
    }

    http_response_code(200);
    echo json_encode($clinicians);

} catch (Exception $e) {
    // Ensure error messages are properly formatted in JSON response
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching clinician data.", "error" => $e->getMessage()]);
}

$conn->close();
?>