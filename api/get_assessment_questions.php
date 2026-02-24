<?php
// Filename: ec/api/get_assessment_questions.php
// NEW API: Fetches all active questions for the dynamic wound assessment.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // Select all active questions, ordered by category and display_order
    $sql = "SELECT * FROM wound_assessment_questions 
            WHERE is_active = 1 
            ORDER BY category, display_order, question_text";

    $result = $conn->query($sql);

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