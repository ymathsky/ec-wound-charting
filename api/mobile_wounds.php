<?php
/**
 * api/mobile_wounds.php
 *
 * GET  ?patient_id=X
 *   → all wounds for the patient, each with:
 *       latest_assessment (measurements), assessments[] (trajectory), images[]
 *
 * POST { action:"upload_photo", wound_id, patient_id, appointment_id?,
 *         image_data (base64), image_ext }
 *   → saves photo to uploads/wound_images/, inserts into wound_images table
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php'; // validates JWT, sets $mobile_user

$user_id = intval($mobile_user['user_id']);

// Build reliable base URL — works on XAMPP (/ec/) and cPanel root or subdir
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
// Compute the app root by going two levels up from this script: api/mobile_wounds.php → api/ → /
$doc_root   = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\');
$script_dir = rtrim(dirname(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__)), '/\\');
$app_path   = str_replace(str_replace('\\', '/', $doc_root), '', str_replace('\\', '/', $script_dir));
$base_url   = $scheme . '://' . $host . rtrim($app_path, '/') . '/';

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patient_id = intval($_GET['patient_id'] ?? 0);
    if (!$patient_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id required.']);
        exit;
    }

    // Fetch all wounds for the patient
    $stmt = $conn->prepare(
        "SELECT wound_id, patient_id, location, wound_type, diagnosis, status,
                date_onset, created_at
         FROM wounds
         WHERE patient_id = ?
         ORDER BY date_onset DESC, wound_id DESC"
    );
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $wounds_raw = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $wounds = [];
    foreach ($wounds_raw as $w) {
        $wound_id = intval($w['wound_id']);

        // ── Assessment trajectory (all, ASC) ──
        $stmt2 = $conn->prepare(
            "SELECT assessment_id, assessment_date,
                    length_cm, width_cm, depth_cm,
                    ROUND(length_cm * width_cm, 2)              AS area_cm2,
                    ROUND(length_cm * width_cm * depth_cm, 2)   AS volume_cm3,
                    drainage_type, exudate_amount, exudate_type,
                    signs_of_infection, pain_level, treatments_provided, clinician_assessment AS notes
             FROM wound_assessments
             WHERE wound_id = ?
             ORDER BY assessment_date ASC, assessment_id ASC"
        );
        $stmt2->bind_param("i", $wound_id);
        $stmt2->execute();
        $assessments = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        // Numeric conversion
        foreach ($assessments as &$a) {
            foreach (['length_cm','width_cm','depth_cm','area_cm2','volume_cm3','pain_level'] as $col) {
                if (isset($a[$col])) $a[$col] = $a[$col] !== null ? floatval($a[$col]) : null;
            }
        }
        unset($a);

        $latest = !empty($assessments) ? end($assessments) : null;

        // ── Wound images ──
        $stmt3 = $conn->prepare(
            "SELECT image_id, assessment_id, appointment_id,
                    image_path, image_type, uploaded_at
             FROM wound_images
             WHERE wound_id = ?
             ORDER BY uploaded_at DESC
             LIMIT 20"
        );
        $stmt3->bind_param("i", $wound_id);
        $stmt3->execute();
        $imgs_raw = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();

        $images = array_map(function ($img) use ($base_url) {
            $img['url'] = $base_url . ltrim($img['image_path'], '/');
            // Check if file actually exists on this server
            $abs = dirname(dirname(__FILE__)) . '/' . ltrim($img['image_path'], '/');
            $img['file_exists'] = file_exists($abs);
            return $img;
        }, $imgs_raw);

        $wounds[] = [
            'wound_id'          => $wound_id,
            'location'          => $w['location'],
            'wound_type'        => $w['wound_type'],
            'diagnosis'         => $w['diagnosis'],
            'status'            => $w['status'],
            'date_onset'        => $w['date_onset'],
            'latest_assessment' => $latest,
            'assessments'       => $assessments,
            'images'            => $images,
        ];
    }

    echo json_encode(['success' => true, 'data' => ['wounds' => $wounds]]);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';

    if ($action === 'upload_photo') {
        $wound_id      = intval($body['wound_id']      ?? 0);
        $patient_id    = intval($body['patient_id']    ?? 0);
        $appointment_id = intval($body['appointment_id'] ?? 0) ?: null;
        $image_data    = $body['image_data']  ?? '';  // base64
        $image_ext     = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $body['image_ext'] ?? 'jpg'));

        if (!$wound_id || !$patient_id || !$image_data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'wound_id, patient_id, image_data required.']);
            exit;
        }

        // Strip base64 header if present (data:image/jpeg;base64,...)
        if (strpos($image_data, ',') !== false) {
            $image_data = explode(',', $image_data, 2)[1];
        }

        $decoded = base64_decode($image_data);
        if (!$decoded) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid image data.']);
            exit;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'heic'];
        if (!in_array($image_ext, $allowed)) $image_ext = 'jpg';

        $upload_dir = dirname(dirname(__FILE__)) . '/uploads/wound_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename   = 'mobile_' . $patient_id . '_w' . $wound_id . '_' . uniqid() . '.' . $image_ext;
        $file_path  = $upload_dir . $filename;
        $db_path    = 'uploads/wound_images/' . $filename;

        if (!file_put_contents($file_path, $decoded)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to save image file.']);
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO wound_images (wound_id, appointment_id, image_path, image_type, uploaded_at)
             VALUES (?, ?, ?, 'photo', NOW())"
        );
        $stmt->bind_param("iis", $wound_id, $appointment_id, $db_path);
        $stmt->execute();
        $image_id = $stmt->insert_id;
        $stmt->close();

        $image_url = $base_url . $db_path;
        echo json_encode([
            'success'   => true,
            'message'   => 'Photo uploaded successfully.',
            'image_id'  => $image_id,
            'image_url' => $image_url,
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
