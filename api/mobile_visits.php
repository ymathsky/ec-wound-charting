<?php
/**
 * api/mobile_visits.php
 * GET  ?patient_id=&appointment_id=   → fetch visit note + appointment list
 * POST { appointment_id, patient_id, subjective, objective, assessment, plan,
 *        chief_complaint, hpi, ros }  → save/update visit note
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php'; // sets $mobile_user

$user_id = intval($mobile_user['user_id']);

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patient_id = intval($_GET['patient_id'] ?? 0);

    if (!$patient_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id required.']);
        exit;
    }

    // Patient info
    $stmt = $conn->prepare(
        "SELECT p.patient_id AS id, p.first_name, p.last_name, p.date_of_birth, p.gender,
                p.contact_number, p.allergies, p.past_medical_history,
                p.insurance_provider, p.insurance_policy_number,
                u.full_name AS facility_name
         FROM patients p
         LEFT JOIN users u ON p.facility_id = u.user_id
         WHERE p.patient_id = ?"
    );
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'SQL error (patient): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$patient) {
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    // Appointments list
    $stmt = $conn->prepare(
        "SELECT a.appointment_id, a.appointment_date, a.appointment_type, a.status,
                vn.note_id, vn.chief_complaint, vn.subjective, vn.objective,
                vn.assessment, vn.plan
         FROM appointments a
         LEFT JOIN visit_notes vn ON vn.appointment_id = a.appointment_id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC
         LIMIT 30"
    );
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'SQL error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $patient['visits'] = $appointments;
    echo json_encode(['success' => true, 'data' => $patient]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data           = json_decode(file_get_contents('php://input'), true);
    $patient_id     = intval($data['patient_id'] ?? 0);
    $appointment_id = intval($data['appointment_id'] ?? 0);

    if (!$patient_id || !$appointment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id and appointment_id required.']);
        exit;
    }

    $fields = ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan'];
    $values = [];
    foreach ($fields as $f) {
        $values[$f] = isset($data[$f]) ? trim($data[$f]) : '';
    }

    // Upsert visit note
    $stmt = $conn->prepare(
        "INSERT INTO visit_notes
             (patient_id, appointment_id, user_id, note_date,
              chief_complaint, subjective, objective, assessment, plan)
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
              chief_complaint = VALUES(chief_complaint),
              subjective      = VALUES(subjective),
              objective       = VALUES(objective),
              assessment      = VALUES(assessment),
              plan            = VALUES(plan)"
    );
    $stmt->bind_param(
        "iiisssss",
        $patient_id, $appointment_id, $user_id,
        $values['chief_complaint'],
        $values['subjective'], $values['objective'],
        $values['assessment'], $values['plan']
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Visit note saved.', 'note_id' => $conn->insert_id ?: null]);
    } else {
        error_log('mobile_visits save error: ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save visit note.']);
    }
    $stmt->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
