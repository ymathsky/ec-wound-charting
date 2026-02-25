<?php
/**
 * api/mobile_ai_soap.php
 * POST { section, patient_id, appointment_id, chief_complaint, current_content }
 *   → returns AI-generated draft for the requested SOAP section
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php';

$user_id = intval($mobile_user['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$body           = json_decode(file_get_contents('php://input'), true) ?? [];
$section        = trim($body['section']        ?? '');
$patient_id     = intval($body['patient_id']   ?? 0);
$appointment_id = intval($body['appointment_id'] ?? 0);
$chief_complaint = trim($body['chief_complaint'] ?? '');
$current_content = trim($body['current_content'] ?? '');

if (!$section || !$patient_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'section and patient_id required']);
    exit;
}

// ── Gather clinical context ────────────────────────────────────────────────
$context = '';

// Patient basics
$stmt = $conn->prepare(
    "SELECT first_name, last_name, date_of_birth, gender, allergies, past_medical_history
     FROM patients WHERE patient_id = ?"
);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($patient) {
    $age = date_diff(date_create($patient['date_of_birth'] ?? 'today'), date_create('today'))->y;
    $context .= "Patient: {$patient['first_name']} {$patient['last_name']}, {$age}y/o {$patient['gender']}.\n";
    if ($patient['allergies'])            $context .= "Allergies: {$patient['allergies']}\n";
    if ($patient['past_medical_history']) $context .= "PMH: {$patient['past_medical_history']}\n";
}

// Chief complaint
if ($chief_complaint) {
    $context .= "Chief Complaint: $chief_complaint\n";
}

// HPI (if appointment given)
if ($appointment_id) {
    $stmt = $conn->prepare("SELECT * FROM patient_hpi WHERE appointment_id = ? LIMIT 1");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $hpi = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($hpi) $context .= "HPI data: " . json_encode($hpi) . "\n";
}

// Latest vitals
$stmt = $conn->prepare(
    "SELECT blood_pressure, heart_rate, respiratory_rate, temperature_celsius,
            oxygen_saturation, weight_kg
     FROM patient_vitals WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1"
);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($vitals) {
    $vparts = [];
    if ($vitals['blood_pressure'])     $vparts[] = "BP {$vitals['blood_pressure']}";
    if ($vitals['heart_rate'])         $vparts[] = "HR {$vitals['heart_rate']} bpm";
    if ($vitals['respiratory_rate'])   $vparts[] = "RR {$vitals['respiratory_rate']}";
    if ($vitals['temperature_celsius']) $vparts[] = "Temp {$vitals['temperature_celsius']}°C";
    if ($vitals['oxygen_saturation'])  $vparts[] = "SpO2 {$vitals['oxygen_saturation']}%";
    if ($vitals['weight_kg'])          $vparts[] = "Weight {$vitals['weight_kg']} kg";
    if ($vparts) $context .= "Vitals: " . implode(', ', $vparts) . "\n";
}

// Latest wound assessments
if ($appointment_id) {
    $stmt = $conn->prepare(
        "SELECT wa.assessment_date, w.location, w.wound_type,
                wa.length_cm, wa.width_cm, wa.depth_cm,
                wa.drainage_type, wa.signs_of_infection, wa.treatments_provided
         FROM wound_assessments wa
         JOIN wounds w ON w.wound_id = wa.wound_id
         WHERE wa.appointment_id = ?"
    );
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $wounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if ($wounds) $context .= "Wound Assessments: " . json_encode($wounds) . "\n";
}

// ── Build section-specific prompt ─────────────────────────────────────────
$prompts = [
    'chief_complaint' => "Write a concise one-sentence chief complaint for a wound care clinical note. Start with 'Patient presents for...'. Use the context below. Return plain text only, no markdown.\n\nContext:\n$context",
    'subjective'      => "Write a professional Subjective (S) section for a wound care SOAP note. Include patient-reported symptoms, pain level, and wound history. Be clinical and concise (3–5 sentences). Plain text only.\n\nContext:\n$context",
    'objective'       => "Write the Objective (O) section for a wound care SOAP note. Include vital signs, wound measurements, wound appearance, drainage, and infection signs based on the data below. Clinical format, 3–6 sentences. Plain text only.\n\nContext:\n$context",
    'assessment'      => "Write the Assessment (A) section for a wound care SOAP note. Provide a clinical impression, wound status (improved/stable/worsening), and relevant diagnoses. 2–4 sentences, clinical tone. Plain text only.\n\nContext:\n$context",
    'plan'            => "Write the Plan (P) section for a wound care SOAP note. Include wound care orders, dressing changes, referrals, follow-up schedule, and patient education. Numbered list format where appropriate. Plain text only.\n\nContext:\n$context",
];

$prompt = $prompts[$section] ?? "Write a professional {$section} section for a clinical wound care note. Plain text only.\n\nContext:\n$context";

if ($current_content) {
    $prompt .= "\n\nThe clinician has already typed: \"$current_content\" — refine and continue this.";
}

// ── Call Gemini ─────────────────────────────────────────────────────────
$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
$url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

$payload = [
    'contents'         => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 512],
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $json  = json_decode($response, true);
    $text  = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Strip AI markdown artifacts
    $text  = preg_replace('/\*\*|__|#{1,3}\s?/', '', $text);
    $text  = preg_replace('/\*([^*]+)\*/', '$1', $text);
    echo json_encode(['success' => true, 'draft' => trim($text), 'section' => $section]);
} else {
    $err = json_decode($response, true)['error']['message'] ?? "HTTP $http_code";
    echo json_encode(['success' => false, 'message' => "AI error: $err"]);
}
