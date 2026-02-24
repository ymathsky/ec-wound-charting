<?php
// Filename: api/get_notes.php
// --- FIX: Query `visit_notes` table instead of `patient_notes` ---

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID."]);
    exit();
}

try {
    // Fetch notes from visit_notes table (aliased as vn)
    $sql = "SELECT vn.*, u.full_name 
            FROM visit_notes vn
            LEFT JOIN users u ON vn.user_id = u.user_id
            WHERE vn.patient_id = ? 
            ORDER BY vn.note_date DESC, vn.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notes = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($notes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching notes.", "error" => $e->getMessage()]);
}
?>