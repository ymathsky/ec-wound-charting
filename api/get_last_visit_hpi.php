<?php
// Filename: api/get_last_visit_hpi.php
// Purpose: Fetches HPI answers from the most recent previous appointment for a patient.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$current_appointment_id = isset($_GET['current_appointment_id']) ? intval($_GET['current_appointment_id']) : 0;

if ($patient_id <= 0 || $current_appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid Patient or Appointment ID.']);
    exit();
}

try {
    // 1. Find the most recent previous appointment that has HPI data
    // We join with patient_hpi_answers to ensure we only get an appointment that actually has data.
    $sql = "SELECT a.appointment_id, a.appointment_date
            FROM appointments a
            JOIN patient_hpi_answers pha ON a.appointment_id = pha.appointment_id
            WHERE a.patient_id = ? 
              AND a.appointment_id != ?
            GROUP BY a.appointment_id
            ORDER BY a.appointment_date DESC, a.appointment_id DESC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ii", $patient_id, $current_appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prev_appt = $result->fetch_assoc();

    if (!$prev_appt) {
        echo json_encode(['success' => false, 'message' => 'No previous HPI data found for this patient.']);
        exit();
    }

    $prev_appointment_id = $prev_appt['appointment_id'];
    $prev_date = $prev_appt['appointment_date'];

    // 2. Fetch the answers for that appointment
    // (Logic copied from get_hpi_data.php)
    $sql_answers = "SELECT 
                a.question_id, 
                a.wound_id,
                a.answer_value
            FROM patient_hpi_answers a
            WHERE a.appointment_id = ?";

    $stmt_answers = $conn->prepare($sql_answers);
    $stmt_answers->bind_param("i", $prev_appointment_id);
    $stmt_answers->execute();
    $result_answers = $stmt_answers->get_result();

    $answers_by_key = [];

    while ($row = $result_answers->fetch_assoc()) {
        // Create the composite key (e.g., "123_NULL" or "123_45")
        $wound_key = $row['wound_id'] ? $row['wound_id'] : 'NULL';
        $composite_key = $row['question_id'] . '_' . $wound_key;

        $answers_by_key[$composite_key] = [
            'answer_value' => $row['answer_value']
        ];
    }

    echo json_encode([
        'success' => true, 
        'data' => [
            'answers_by_key' => $answers_by_key,
            'source_date' => $prev_date,
            'source_appointment_id' => $prev_appointment_id
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
if (isset($stmt_answers)) $stmt_answers->close();
$conn->close();
?>