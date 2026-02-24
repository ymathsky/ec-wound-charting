<?php
// Filename: api/get_note_for_appointment.php
// --- FIX: Query `visit_notes` table instead of `patient_notes` ---

// Ensure headers are set before any output
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Check if db_connect.php exists and include it
if (!file_exists('../db_connect.php')) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection file missing."]);
    exit();
}
require_once '../db_connect.php';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    // If no valid ID is provided, return an empty set gracefully.
    http_response_code(200);
    echo json_encode(null);
    exit();
}

try {
    // Fetch the specific note tied to the appointment ID from visit_notes (aliased as vn)
    $sql = "SELECT vn.*, u.full_name 
            FROM visit_notes vn
            LEFT JOIN users u ON vn.user_id = u.user_id
            WHERE vn.appointment_id = ? 
            ORDER BY vn.created_at DESC LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();
    $stmt->close();

    http_response_code(200);
    // Return null if no note is found, or the note data if one exists.
    echo json_encode($note ? $note : null);

} catch (Exception $e) {
    http_response_code(500);
    // Return detailed error message for debugging the 500 error
    echo json_encode(["message" => "An error occurred while fetching the visit note.", "error" => $e->getMessage()]);
}

$conn->close();
?>