<?php
/**
 * api/mobile_vitals.php
 * GET  ?patient_id=&appointment_id=  → latest vitals for a patient
 * POST { patient_id, appointment_id, blood_pressure, heart_rate,
 *        respiratory_rate, temperature_celsius, oxygen_saturation,
 *        weight_kg, height_cm }        → upsert vitals
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php';

$user_id = intval($mobile_user['user_id']);

// ── GET ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patient_id     = intval($_GET['patient_id']     ?? 0);
    $appointment_id = intval($_GET['appointment_id'] ?? 0);

    if (!$patient_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id required']);
        exit;
    }

    // Try appointment-specific first, then fall back to latest
    $vitals = null;
    if ($appointment_id) {
        $stmt = $conn->prepare(
            "SELECT * FROM patient_vitals
             WHERE patient_id = ? AND appointment_id = ?
             LIMIT 1"
        );
        $stmt->bind_param("ii", $patient_id, $appointment_id);
        $stmt->execute();
        $vitals = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$vitals) {
        $stmt = $conn->prepare(
            "SELECT * FROM patient_vitals
             WHERE patient_id = ?
             ORDER BY visit_date DESC, vitals_id DESC
             LIMIT 1"
        );
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $vitals = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Numeric cast
    if ($vitals) {
        foreach (['heart_rate','respiratory_rate','oxygen_saturation'] as $col) {
            if (isset($vitals[$col])) $vitals[$col] = $vitals[$col] !== null ? intval($vitals[$col]) : null;
        }
        foreach (['temperature_celsius','weight_kg','height_cm','bmi'] as $col) {
            if (isset($vitals[$col])) $vitals[$col] = $vitals[$col] !== null ? floatval($vitals[$col]) : null;
        }
    }

    echo json_encode(['success' => true, 'vitals' => $vitals]);
    exit;
}

// ── POST ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body           = json_decode(file_get_contents('php://input'), true) ?? [];
    $patient_id     = intval($body['patient_id']     ?? 0);
    $appointment_id = intval($body['appointment_id'] ?? 0) ?: null;

    if (!$patient_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'patient_id required']);
        exit;
    }

    $bp      = trim($body['blood_pressure']      ?? '') ?: null;
    $hr      = isset($body['heart_rate'])         && $body['heart_rate'] !== '' ? intval($body['heart_rate'])          : null;
    $rr      = isset($body['respiratory_rate'])   && $body['respiratory_rate'] !== '' ? intval($body['respiratory_rate'])   : null;
    $temp    = isset($body['temperature_celsius']) && $body['temperature_celsius'] !== '' ? floatval($body['temperature_celsius']) : null;
    $spo2    = isset($body['oxygen_saturation'])  && $body['oxygen_saturation'] !== '' ? intval($body['oxygen_saturation'])  : null;
    $weight  = isset($body['weight_kg'])          && $body['weight_kg'] !== '' ? floatval($body['weight_kg'])          : null;
    $height  = isset($body['height_cm'])          && $body['height_cm'] !== '' ? floatval($body['height_cm'])          : null;

    // Auto-calc BMI
    $bmi = null;
    if ($weight && $height && $height > 0) {
        $bmi = round($weight / (($height / 100) ** 2), 1);
    }

    $stmt = $conn->prepare(
        "INSERT INTO patient_vitals
             (patient_id, appointment_id, visit_date, blood_pressure, heart_rate,
              respiratory_rate, temperature_celsius, oxygen_saturation,
              weight_kg, height_cm, bmi, created_at)
         VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE
              blood_pressure     = VALUES(blood_pressure),
              heart_rate         = VALUES(heart_rate),
              respiratory_rate   = VALUES(respiratory_rate),
              temperature_celsius = VALUES(temperature_celsius),
              oxygen_saturation  = VALUES(oxygen_saturation),
              weight_kg          = VALUES(weight_kg),
              height_cm          = VALUES(height_cm),
              bmi                = VALUES(bmi)"
    );

    if ($stmt === false) {
        // appointment_id column may not exist — fall back without it
        $stmt = $conn->prepare(
            "INSERT INTO patient_vitals
                 (patient_id, visit_date, blood_pressure, heart_rate,
                  respiratory_rate, temperature_celsius, oxygen_saturation,
                  weight_kg, height_cm, bmi, created_at)
             VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                  blood_pressure     = VALUES(blood_pressure),
                  heart_rate         = VALUES(heart_rate),
                  respiratory_rate   = VALUES(respiratory_rate),
                  temperature_celsius = VALUES(temperature_celsius),
                  oxygen_saturation  = VALUES(oxygen_saturation),
                  weight_kg          = VALUES(weight_kg),
                  height_cm          = VALUES(height_cm),
                  bmi                = VALUES(bmi)"
        );
        $stmt->bind_param("isiiiddiid", $patient_id, $bp, $hr, $rr, $temp, $spo2, $weight, $height, $bmi);
    } else {
        $stmt->bind_param("iisiiiddiid", $patient_id, $appointment_id, $bp, $hr, $rr, $temp, $spo2, $weight, $height, $bmi);
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Vitals saved.', 'bmi' => $bmi]);
    } else {
        error_log('mobile_vitals error: ' . $stmt->error);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save vitals: ' . $stmt->error]);
    }
    $stmt->close();
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
