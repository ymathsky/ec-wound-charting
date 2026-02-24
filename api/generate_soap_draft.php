<?php
// Filename: api/generate_soap_draft.php
// Purpose: Generates a specific SOAP section draft using Gemini based on available patient data.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);

$section = $input['section'] ?? '';
$patient_id = $input['patient_id'] ?? 0;
$appointment_id = $input['appointment_id'] ?? 0;
$current_content = $input['current_content'] ?? '';

if (!$section || !$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

// 1. Fetch Context Data (Vitals, HPI, Wounds)
// We reuse the logic to fetch clinical data to give the AI context
$context = "";

// Fetch HPI
$stmt = $conn->prepare("SELECT * FROM patient_hpi WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
if($row = $res->fetch_assoc()) {
    $context .= "HPI: " . json_encode($row) . "\n";
}
$stmt->close();

// Fetch Wounds
$stmt = $conn->prepare("SELECT * FROM wound_assessments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$wounds = [];
while($row = $stmt->get_result()->fetch_assoc()) {
    $wounds[] = $row;
}
if(!empty($wounds)) {
    $context .= "Wound Assessments: " . json_encode($wounds) . "\n";
}
$stmt->close();

// 2. Construct Prompt based on Section
$prompt = "";
if ($section === 'subjective') {
    $prompt = "Draft a professional Subjective note for a wound care visit. Use the HPI data provided. Tone: Clinical, concise. Start directly with 'Patient presents for...'. Context: $context";
} elseif ($section === 'assessment') {
    $prompt = "Draft a professional Assessment summary. Summarize the wound status (improved/stalled/deteriorated) based on the measurements provided. Context: $context";
} else {
    // Generic fallback
    $prompt = "Draft a medical note paragraph for the section '$section'. Context: $context";
}

if (!empty($current_content)) {
    $prompt .= "\nUser has already typed: '$current_content'. Continue or refine this thought.";
}

// 3. Call Gemini API
$api_key = GEMINI_API_KEY;
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-05-20:generateContent?key=' . $api_key;

$payload = [
    'contents' => [['parts' => [['text' => $prompt]]]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    // Clean up markdown if AI returns it
    $text = str_replace(['**', '##'], '', $text);
    echo json_encode(['success' => true, 'draft' => trim($text)]);
} else {
    echo json_encode(['success' => false, 'message' => 'AI Error: ' . $http_code]);
}
?>