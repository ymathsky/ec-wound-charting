<?php
// Filename: api/save_hpi.php
// REWRITE: Saves HPI data to the new `patient_hpi_answers` table.
// Parses the new composite key ("{question_id}_{wound_id}") to save wound links.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"), true);

$appointment_id = isset($data['appointment_id']) ? intval($data['appointment_id']) : 0;
$patient_id = isset($data['patient_id']) ? intval($data['patient_id']) : 0;

if ($appointment_id <= 0 || $patient_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Appointment ID and Patient ID are required.']);
    exit();
}

// --- Check if Visit is Signed ---
$sql_check_signed = "SELECT is_signed FROM visit_notes WHERE appointment_id = ?";
$stmt_check = $conn->prepare($sql_check_signed);
$stmt_check->bind_param("i", $appointment_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();
if ($row_check = $res_check->fetch_assoc()) {
    if ($row_check['is_signed']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot save HPI. The visit is already signed.']);
        exit();
    }
}
$stmt_check->close();

// The 'answers' object (e.g., {"123_NULL": "Worsening", "123_45": "Improving"})
$answers = isset($data['answers']) ? $data['answers'] : [];

if (empty($answers)) {
    echo json_encode(['success' => true, 'message' => 'No data to save.']);
    exit();
}

$conn->begin_transaction();

try {
    // Updated query includes `wound_id` and the ON DUPLICATE KEY UPDATE clause
    // This allows a single query to handle both new and existing answers
    $sql = "INSERT INTO patient_hpi_answers (appointment_id, patient_id, question_id, wound_id, answer_value) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    foreach ($answers as $composite_key => $answer_value) {

        $parts = explode('_', $composite_key);
        if (count($parts) < 2) continue; // Invalid key, skip

        $question_id = intval($parts[0]);
        $wound_id_str = $parts[1];

        // Convert "NULL" string or empty string to actual SQL NULL
        $wound_id = ($wound_id_str === 'NULL' || $wound_id_str === '') ? NULL : intval($wound_id_str);

        if ($question_id <= 0) continue; // Invalid question ID, skip

        // Convert array answers (from checkboxes) to a comma-separated string
        if (is_array($answer_value)) {
            $answer_value_str = implode(', ', $answer_value);
        } else {
            $answer_value_str = $answer_value;
        }

        $stmt->bind_param("iiiis", $appointment_id, $patient_id, $question_id, $wound_id, $answer_value_str);
        if (!$stmt->execute()) {
            throw new Exception("Failed to save answer for Q_ID $question_id, W_ID $wound_id_str: " . $stmt->error);
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'HPI data saved successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$stmt->close();
$conn->close();
?>