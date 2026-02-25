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

    if ($action === 'create') {
        $patient_id  = intval($body['patient_id']   ?? 0);
        $wound_type  = trim($body['wound_type']  ?? '');
        $location    = trim($body['location']    ?? '');
        $status      = trim($body['status']      ?? 'Active');
        $date_onset  = trim($body['date_onset']  ?? date('Y-m-d'));
        $diagnosis   = trim($body['diagnosis']   ?? '');

        if (!$patient_id || !$wound_type || !$location) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'patient_id, wound_type, and location are required.']);
            exit;
        }

        $allowed_statuses = ['Active', 'Healed', 'Inactive'];
        if (!in_array($status, $allowed_statuses)) $status = 'Active';

        $diag_val = $diagnosis ?: null;
        $stmt = $conn->prepare(
            "INSERT INTO wounds (patient_id, wound_type, location, status, date_onset, diagnosis, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("isssss", $patient_id, $wound_type, $location, $status, $date_onset, $diag_val);
        $stmt->execute();
        $wound_id = $stmt->insert_id;
        $stmt->close();

        echo json_encode([
            'success'  => true,
            'message'  => 'Wound created successfully.',
            'wound_id' => $wound_id,
        ]);
        exit;
    }

    if ($action === 'save_assessment') {
        $wound_id   = intval($body['wound_id']   ?? 0);
        $patient_id_a = intval($body['patient_id'] ?? 0);
        if (!$wound_id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'wound_id required.']);
            exit;
        }
        $adate     = trim($body['assessment_date']    ?? date('Y-m-d'));
        $len       = !empty($body['length_cm'])   ? round(floatval($body['length_cm']),  2) : null;
        $wid       = !empty($body['width_cm'])    ? round(floatval($body['width_cm']),   2) : null;
        $dep       = !empty($body['depth_cm'])    ? round(floatval($body['depth_cm']),   2) : null;
        $area      = !empty($body['area_cm2'])    ? round(floatval($body['area_cm2']),   2) : null;
        $gran      = isset($body['granulation_percent']) ? intval($body['granulation_percent']) : null;
        $slou      = isset($body['slough_percent'])      ? intval($body['slough_percent'])      : null;
        $necr      = isset($body['necrotic_percent'])    ? intval($body['necrotic_percent'])    : null;
        $epit      = isset($body['epithelial_percent'])  ? intval($body['epithelial_percent'])  : null;
        $exu_amt   = trim($body['exudate_amount']       ?? '') ?: null;
        $exu_type  = trim($body['exudate_type']         ?? '') ?: null;
        $periwound = trim($body['periwound_condition']  ?? '') ?: null;
        $odor      = trim($body['odor_present']         ?? 'No');

        // Build infection signs string for signs_of_infection column
        $infect_arr = $body['infection_signs'] ?? [];
        $infect_str = is_array($infect_arr) && count($infect_arr) ? implode('; ', $infect_arr) : null;

        // Build treatments string combining treatment_suggestions
        $tx_arr  = $body['treatment_suggestions'] ?? [];
        $tx_str  = is_array($tx_arr) && count($tx_arr)
                    ? implode("\n", array_map(fn($t, $i) => ($i+1).". $t", $tx_arr, array_keys($tx_arr)))
                    : null;

        // Compose clinician_assessment from wound_bed, edges, healing_stage, confidence
        $healing = trim($body['healing_stage'] ?? '');
        $bed_notes = trim(implode("\n", array_filter([
            $body['wound_bed']  ?? '',
            'Edges: '      . ($body['edges']       ?? ''),
            'Periwound: '  . ($body['periwound_condition'] ?? ''),
            $healing ? "Healing Stage: $healing" : '',
            'AI Confidence: ' . ($body['confidence'] ?? 'Medium'),
        ]))) ?: null;
        $summary = trim($body['clinical_summary'] ?? '') ?: null;

        $stmt = $conn->prepare(
            "INSERT INTO wound_assessments
               (wound_id, patient_id, assessment_date,
                length_cm, width_cm, depth_cm, area_cm2,
                granulation_percent, slough_percent, eschar_percent, epithelialization_percent,
                exudate_amount, exudate_type, drainage_type,
                periwound_condition, odor_present,
                signs_of_infection, treatments_provided,
                clinician_assessment, clinician_plan, assessment_type, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'Mobile AI',NOW())"
        );
        if (!$stmt) {
            error_log("save_assessment prepare error: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
            exit;
        }
        // Type string: ii=wound/patient, s=date, dddd=measurements, iiii=tissue%, sssssssss=strings (9)
        $stmt->bind_param(
            "iisddddiiiisssssssss",
            $wound_id, $patient_id_a, $adate,
            $len, $wid, $dep, $area,
            $gran, $slou, $necr, $epit,
            $exu_amt, $exu_type, $exu_type,
            $periwound, $odor,
            $infect_str, $tx_str,
            $bed_notes, $summary
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            error_log("save_assessment execute error: $err");
            echo json_encode(['success' => false, 'message' => "DB error: $err"]);
            exit;
        }
        $new_id = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['success' => true, 'assessment_id' => $new_id, 'message' => 'Assessment saved.']);
        exit;
    }

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
