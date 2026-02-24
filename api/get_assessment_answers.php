<?php
// Filename: ec/api/get_assessment_answers.php
// NEW API: Fetches all answers for a *specific assessment_id*.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$assessment_id = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;

if ($assessment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Assessment ID.']);
    exit();
}

try {
    $sql = "SELECT question_id, answer_value 
            FROM patient_wound_assessment_answers 
            WHERE assessment_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $answers = [];
    while ($row = $result->fetch_assoc()) {
        // Key by question_id for easy lookup in JS
        $answers[$row['question_id']] = $row['answer_value'];
    }

    echo json_encode(['success' => true, 'answers' => $answers]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>