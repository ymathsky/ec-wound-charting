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
                vn.assessment, vn.plan, vn.is_signed, vn.signed_at, vn.status AS note_status
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
    $action         = $data['action'] ?? 'save';
    $patient_id     = intval($data['patient_id'] ?? 0);
    $appointment_id = intval($data['appointment_id'] ?? 0);

    if (!$patient_id || !$appointment_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id and appointment_id required.']);
        exit;
    }

    // ── Upload photo action ──────────────────────────────────────────────────
    if ($action === 'upload_photo') {
        $image_data = $data['image_data'] ?? '';
        $image_ext  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $data['image_ext'] ?? 'jpg'));

        if (!$image_data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'image_data required']);
            exit;
        }

        if (strpos($image_data, ',') !== false) {
            $image_data = explode(',', $image_data, 2)[1];
        }

        $decoded = base64_decode($image_data);
        if (!$decoded) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image data']);
            exit;
        }

        $allowed_exts = ['jpg', 'jpeg', 'png'];
        if (!in_array($image_ext, $allowed_exts)) $image_ext = 'jpg';

        $upload_dir = dirname(dirname(__FILE__)) . '/uploads/visit_photos/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = 'visit_' . $appointment_id . '_' . uniqid() . '.' . $image_ext;
        $filepath = $upload_dir . $filename;
        $db_path  = 'uploads/visit_photos/' . $filename;

        if (!file_put_contents($filepath, $decoded)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save image']);
            exit;
        }

        // Check if visit_attachments or appointment_attachments table exists; fallback to a simple column note
        $has_table = $conn->query("SHOW TABLES LIKE 'visit_attachments'")->num_rows > 0;
        if ($has_table) {
            $stmt = $conn->prepare(
                "INSERT INTO visit_attachments (appointment_id, patient_id, file_path, file_type, uploaded_by, created_at)
                 VALUES (?, ?, ?, 'photo', ?, NOW())"
            );
            $stmt->bind_param("iisi", $appointment_id, $patient_id, $db_path, $user_id);
            $stmt->execute();
            $stmt->close();
        }

        // Build URL
        $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $doc_root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
        $scrp_dir = rtrim(dirname(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)), '/\\');
        $app_path = str_replace(str_replace('\\', '/', $doc_root), '', str_replace('\\', '/', $scrp_dir));
        $base_url = $scheme . '://' . $host . rtrim($app_path, '/') . '/';

        echo json_encode([
            'success'   => true,
            'message'   => 'Photo uploaded.',
            'url'       => $base_url . $db_path,
            'file_path' => $db_path,
        ]);
        exit;
    }

    // ── Sign action ──────────────────────────────────────────────────────────
    if ($action === 'sign') {
        $stmt = $conn->prepare(
            "UPDATE visit_notes
             SET is_signed = 1, signed_at = NOW(), status = 'finalized',
                 finalized_at = NOW(), finalized_by = ?
             WHERE appointment_id = ? AND patient_id = ?"
        );
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'SQL error: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("iii", $user_id, $appointment_id, $patient_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Note signed and finalized.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No note found to sign. Save the note first.']);
        }
        $stmt->close();
        exit;
    }

    // ── Save action ──────────────────────────────────────────────────────────
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
        $note_id = $conn->insert_id ?: null;
        // Mark appointment as Completed when a note is saved
        $conn->query("UPDATE appointments SET status = 'Completed' WHERE appointment_id = $appointment_id AND status IN ('Scheduled','Confirmed','Checked-in')");
        echo json_encode(['success' => true, 'message' => 'Visit note saved.', 'note_id' => $note_id]);
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
