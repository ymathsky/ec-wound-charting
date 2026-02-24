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

    // Appointments list
    $stmt = $conn->prepare(
        "SELECT a.appointment_id, a.appointment_date, a.appointment_time,
                a.reason_for_visit, a.status,
                vn.note_id, vn.chief_complaint, vn.subjective, vn.objective,
                vn.assessment, vn.plan, vn.hpi, vn.ros
         FROM appointments a
         LEFT JOIN visit_notes vn ON vn.appointment_id = a.appointment_id
         WHERE a.patient_id = ?
         ORDER BY a.appointment_date DESC
         LIMIT 30"
    );
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'data' => $appointments]);
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

    $fields = ['chief_complaint', 'hpi', 'ros', 'subjective', 'objective', 'assessment', 'plan'];
    $values = [];
    foreach ($fields as $f) {
        $values[$f] = isset($data[$f]) ? trim($data[$f]) : '';
    }

    // Upsert visit note
    $stmt = $conn->prepare(
        "INSERT INTO visit_notes
             (patient_id, appointment_id, user_id, note_date,
              chief_complaint, hpi, ros, subjective, objective, assessment, plan)
         VALUES (?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
              chief_complaint = VALUES(chief_complaint),
              hpi             = VALUES(hpi),
              ros             = VALUES(ros),
              subjective      = VALUES(subjective),
              objective       = VALUES(objective),
              assessment      = VALUES(assessment),
              plan            = VALUES(plan),
              updated_at      = NOW()"
    );
    $stmt->bind_param(
        "iiissssss s",
        $patient_id, $appointment_id, $user_id,
        $values['chief_complaint'], $values['hpi'], $values['ros'],
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
