<?php
// Filename: api/get_hpi_questions.php
// NEW API: Fetches the combined list of Global + Personalized questions for the visit workflow.
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// The ID of the clinician *conducting the visit* (passed in the URL)
$clinician_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($clinician_id <= 0) {
    // Fallback to session ID if not passed in URL
    // This is a good safety check, but the URL param is more reliable in the visit flow
    $clinician_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;
}

if ($clinician_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid Clinician User ID is required.']);
    exit();
}

try {
    // This query is the core of the new feature:
    // It selects all active questions where:
    // 1. user_id IS NULL (Global questions for everyone)
    // OR
    // 2. user_id = ? (Personalized questions for this specific clinician)
    $sql = "SELECT * FROM hpi_questions
            WHERE is_active = 1
            AND (user_id IS NULL OR user_id = ?)
            ORDER BY category, display_order, question_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $clinician_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $questions = [];
    while ($row = $result->fetch_assoc()) {
        $questions[] = $row;
    }

    echo json_encode(['success' => true, 'questions' => $questions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>