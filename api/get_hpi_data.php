<?php
// Filename: api/get_hpi_data.php
// REWRITE: Fetches answers from the `patient_hpi_answers` table.
// Groups answers by a composite key: "{question_id}_{wound_id}" (e.g., "123_NULL", "123_45")

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID.']);
    exit();
}

try {
    // Select answers and join with questions to get the narrative_key
    $sql = "SELECT 
                a.question_id, 
                a.wound_id,
                a.answer_value, 
                q.narrative_key 
            FROM patient_hpi_answers a
            JOIN hpi_questions q ON a.question_id = q.question_id
            WHERE a.appointment_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $answers_by_key = [];
    $answers_for_narrative = [];

    while ($row = $result->fetch_assoc()) {
        // Create the composite key (e.g., "123_NULL" or "123_45")
        $wound_key = $row['wound_id'] ? $row['wound_id'] : 'NULL';
        $composite_key = $row['question_id'] . '_' . $wound_key;

        // 1. For populating the form
        $answers_by_key[$composite_key] = [
            'answer_value' => $row['answer_value']
        ];

        // 2. For the auto-narrative (ONLY use the "General" / NULL wound answers)
        if (!empty($row['narrative_key']) && $row['wound_id'] === NULL) {
            if (!isset($answers_for_narrative[$row['narrative_key']])) {
                $answers_for_narrative[$row['narrative_key']] = $row['answer_value'];
            }
        }
    }

    $data = [
        'answers_by_key' => $answers_by_key,
        'answers_for_narrative' => $answers_for_narrative
    ];

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching HPI data: ' . $e->getMessage()]);
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>