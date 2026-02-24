<?php
// Filename: api/get_patient_chart_history.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID."]);
    exit();
}

try {
    // 1. Fetch all appointments and associated notes for the patient
    $sql_appointments = "SELECT 
                            a.appointment_id,
                            a.appointment_date,
                            a.status,
                            u.full_name as clinician_name,
                            pn.note_id,
                            pn.chief_complaint,
                            pn.subjective,
                            pn.objective,
                            pn.assessment,
                            pn.plan
                        FROM appointments a
                        LEFT JOIN users u ON a.user_id = u.user_id
                        LEFT JOIN patient_notes pn ON a.appointment_id = pn.appointment_id
                        WHERE a.patient_id = ?
                        ORDER BY a.appointment_date DESC";

    $stmt_app = $conn->prepare($sql_appointments);
    $stmt_app->bind_param("i", $patient_id);
    $stmt_app->execute();
    $history = $stmt_app->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_app->close();

    // 2. Fetch ALL columns from wound assessments for the patient
    $sql_assessments = "SELECT wa.*, w.location 
                        FROM wound_assessments wa 
                        JOIN wounds w ON wa.wound_id = w.wound_id 
                        WHERE w.patient_id = ?";
    $stmt_asm = $conn->prepare($sql_assessments);
    $stmt_asm->bind_param("i", $patient_id);
    $stmt_asm->execute();
    $all_assessments = $stmt_asm->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_asm->close();

    // 3. Fetch all wound images for the patient
    $sql_images = "SELECT wi.appointment_id, w.wound_id, wi.image_path, wi.image_type 
                   FROM wound_images wi
                   JOIN wounds w ON wi.wound_id = w.wound_id
                   WHERE w.patient_id = ?";
    $stmt_img = $conn->prepare($sql_images);
    $stmt_img->bind_param("i", $patient_id);
    $stmt_img->execute();
    $all_images = $stmt_img->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_img->close();

    // 4. Structure the data by appointment in PHP
    foreach ($history as &$entry) {
        $appointment_id = $entry['appointment_id'];

        // Filter assessments for the current appointment
        $entry['wound_assessments'] = array_values(array_filter($all_assessments, function($assessment) use ($appointment_id) {
            return $assessment['appointment_id'] == $appointment_id;
        }));

        // Filter images for the current appointment
        $entry['wound_images'] = array_values(array_filter($all_images, function($image) use ($appointment_id) {
            return $image['appointment_id'] == $appointment_id;
        }));
    }

    http_response_code(200);
    echo json_encode($history);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching chart history.", "error" => $e->getMessage()]);
}

$conn->close();
?>

