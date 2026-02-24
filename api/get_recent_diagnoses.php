<?php
// Filename: ec/api/get_recent_diagnoses.php
// Description: API to retrieve all diagnoses from the patient's
// most recent *previous* completed appointment.

require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$current_appointment_id = isset($_GET['current_appointment_id']) ? intval($_GET['current_appointment_id']) : 0;

if ($patient_id <= 0 || $current_appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing Patient ID or Current Appointment ID.']);
    exit;
}

$diagnoses = [];
$success = true;
$message = 'Recent diagnoses retrieved.';
$previous_appointment_id = 0;

try {
    // Step 1: Find the appointment_date of the current appointment
    $current_appt_date_sql = "SELECT appointment_date FROM appointments WHERE appointment_id = ? LIMIT 1";
    $stmt_current_date = $conn->prepare($current_appt_date_sql);
    $stmt_current_date->bind_param("i", $current_appointment_id);
    $stmt_current_date->execute();
    $current_date_result = $stmt_current_date->get_result();

    if ($current_date_result->num_rows === 0) {
        throw new Exception("Current appointment not found.");
    }
    $current_appt_row = $current_date_result->fetch_assoc();
    $current_appointment_date = $current_appt_row['appointment_date'];
    $stmt_current_date->close();


    // Step 2: Find the most recent *completed* appointment *before* this one
    $sql_prev_appt = "SELECT appointment_id
                      FROM appointments
                      WHERE patient_id = ?
                        AND appointment_date < ?
                        AND status = 'Completed'
                      ORDER BY appointment_date DESC
                      LIMIT 1";

    $stmt_prev = $conn->prepare($sql_prev_appt);
    $stmt_prev->bind_param("is", $patient_id, $current_appointment_date);
    $stmt_prev->execute();
    $result_prev = $stmt_prev->get_result();

    if ($result_prev->num_rows > 0) {
        $row_prev = $result_prev->fetch_assoc();
        $previous_appointment_id = $row_prev['appointment_id'];
    }
    $stmt_prev->close();

    // Step 3: If we found a previous appointment, get its diagnoses
    if ($previous_appointment_id > 0) {
        $sql_diag = "SELECT icd10_code, description, is_primary, wound_id 
                     FROM visit_diagnoses 
                     WHERE appointment_id = ?
                     ORDER BY is_primary DESC, visit_diagnosis_id ASC";

        $stmt_diag = $conn->prepare($sql_diag);
        $stmt_diag->bind_param("i", $previous_appointment_id);
        $stmt_diag->execute();
        $result_diag = $stmt_diag->get_result();

        while ($row_diag = $result_diag->fetch_assoc()) {
            $row_diag['is_primary'] = (int)$row_diag['is_primary'];
            $row_diag['wound_id'] = $row_diag['wound_id'] ? (int)$row_diag['wound_id'] : null;
            $diagnoses[] = $row_diag;
        }
        $stmt_diag->close();

        if (empty($diagnoses)) {
            $message = 'Previous visit found but had no diagnoses.';
        }

    } else {
        $message = 'No previous completed visits found.';
    }

} catch (Exception $e) {
    $success = false;
    $message = 'Database error: ' . $e->getMessage();
    error_log("Get Recent Diagnoses Error: " . $e->getMessage());
    http_response_code(500);
}

$conn->close();

echo json_encode(['success' => $success, 'message' => $message, 'data' => $diagnoses]);
?>