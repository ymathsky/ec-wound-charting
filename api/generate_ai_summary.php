<?php
// Filename: api/generate_ai_summary.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../db_connect.php';

// --- API Configuration (NOW USES CONSTANT) ---
$api_key = GEMINI_API_KEY; // Use the constant from db_connect.php
$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
define('MAX_RETRIES', 3);
define('BASE_DELAY_SECONDS', 1);

$data = json_decode(file_get_contents("php://input"));

if (empty($data->patient_id) || empty($data->appointment_id)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Patient ID and Appointment ID are required."]);
    exit();
}

$patient_id = intval($data->patient_id);
$appointment_id = intval($data->appointment_id);

// --- 1. Data Collection (unchanged) ---
$clinical_data = [];

// Fetch HPI for the current appointment
$sql_hpi = "SELECT * FROM patient_hpi WHERE appointment_id = ? LIMIT 1";
$stmt = $conn->prepare($sql_hpi);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$hpi_result = $stmt->get_result();
$hpi = $hpi_result->fetch_assoc();
$clinical_data['HPI'] = $hpi ? json_encode($hpi, JSON_PRETTY_PRINT) : "No HPI data found for this visit.";
$stmt->close();

// Fetch latest Vitals
$sql_vitals = "SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY visit_date DESC, created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql_vitals);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$vitals_result = $stmt->get_result();
$vitals = $vitals_result->fetch_assoc();
$clinical_data['Vitals'] = $vitals ? json_encode($vitals, JSON_PRETTY_PRINT) : "No Vitals recorded for this patient.";
$stmt->close();

// Fetch the visit Note (or the latest one)
$sql_note = "SELECT chief_complaint, subjective, objective, assessment, plan FROM patient_notes WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql_note);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$note_result = $stmt->get_result();
$note = $note_result->fetch_assoc();
$clinical_data['VisitNote'] = $note ? json_encode($note, JSON_PRETTY_PRINT) : "No Visit Note found for this patient.";
$stmt->close();

$conn->close();

// --- 2. Prompt Construction (unchanged) ---
$system_prompt = "You are a healthcare documentation specialist. Your task is to generate a concise, professional, single-paragraph summary of the patient's clinical encounter using the provided structured data. Focus on the chief complaint, key HPI findings (especially wound status/pain), latest vitals, and the final assessment/plan. Use only medical terminology appropriate for a healthcare record and ensure the summary flows naturally as a narrative.";

$user_query = "Generate a summary based on the following patient data. This summary should integrate information from all sections into a professional narrative, strictly keeping it to one paragraph:\n\n"
    . "--- HPI Data (History of Present Illness) ---\n"
    . $clinical_data['HPI'] . "\n\n"
    . "--- Latest Vitals Data ---\n"
    . $clinical_data['Vitals'] . "\n\n"
    . "--- Latest Visit Note (SOAP) ---\n"
    . $clinical_data['VisitNote'];

// --- 3. API Call with Backoff (unchanged logic) ---
$payload = json_encode([
    'contents' => [
        [
            'parts' => [['text' => $user_query]]
        ]
    ],
    'systemInstruction' => [
        'parts' => [['text' => $system_prompt]]
    ]
]);

$summary_text = '';
$http_code = 0;

for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $ai_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $result = json_decode($ai_response, true);
        $summary_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
        break;
    }

    if ($attempt < MAX_RETRIES && ($http_code == 429 || $http_code >= 500)) {
        $delay = BASE_DELAY_SECONDS * (2 ** ($attempt - 1));
        usleep($delay * 1000000); // Sleep in microseconds
    } else {
        break;
    }
}

if ($summary_text) {
    http_response_code(200);
    echo json_encode(["success" => true, "summary" => $summary_text]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to generate AI summary. HTTP Code: " . $http_code]);
}
?>
