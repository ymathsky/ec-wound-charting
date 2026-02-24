<?php
// Filename: api/suggest_diagnosis_ai.php
// Suggests ICD-10 codes based on HPI and Wound data using Gemini AI

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($appointment_id <= 0 || $patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing ID parameters']);
    exit();
}

// 1. Fetch Patient Demographics
$patient_info = "Unknown Patient";
$stmt = $conn->prepare("SELECT date_of_birth, gender FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $dob = new DateTime($row['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    $patient_info = "{$age}-year-old {$row['gender']}";
}
$stmt->close();

// 2. Fetch HPI Narrative
$hpi_text = "No HPI recorded.";
// UPDATED: Corrected table name to visit_hpi_narratives and column to narrative_text
$stmt = $conn->prepare("SELECT narrative_text FROM visit_hpi_narratives WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $hpi_text = $row['narrative_text'];
}
$stmt->close();

// 3. Fetch Active Wounds & Assessments
$wounds_text = "";
// UPDATED: Corrected column names to match DB schema (length_cm, width_cm, etc.)
$w_sql = "SELECT w.location, w.wound_type, wa.length_cm, wa.width_cm, wa.depth_cm, wa.exudate_amount 
          FROM wounds w 
          LEFT JOIN wound_assessments wa ON w.wound_id = wa.wound_id AND wa.appointment_id = ?
          WHERE w.patient_id = ? AND w.status = 'Active'";
$stmt = $conn->prepare($w_sql);
$stmt->bind_param("ii", $appointment_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$wounds_data = [];
while ($row = $res->fetch_assoc()) {
    $desc = "{$row['location']} ({$row['wound_type']})";
    if ($row['length_cm']) {
        $desc .= " - {$row['length_cm']}x{$row['width_cm']}x{$row['depth_cm']}cm, Drainage: {$row['exudate_amount']}";
    }
    $wounds_data[] = $desc;
}
$stmt->close();

if (!empty($wounds_data)) {
    $wounds_text = implode("; ", $wounds_data);
} else {
    $wounds_text = "No active wounds recorded.";
}

// 4. Construct Prompt
$system_prompt = "You are an expert medical coder.
Analyze the following patient data and suggest 3-5 relevant ICD-10 codes.

PATIENT: {$patient_info}
HPI: {$hpi_text}
WOUNDS: {$wounds_text}

INSTRUCTIONS:
1. Suggest codes that are medically justified by the text.
2. Prioritize specific codes (e.g., E11.621) over unspecified ones.
3. Return ONLY a JSON array. Do not include markdown formatting like ```json.
4. Format: [{\"code\": \"X00.0\", \"description\": \"Short desc\", \"reason\": \"Why this matches\"}]
";

// 5. Call Gemini API
$apiKey = getenv('GEMINI_API_KEY') ?: '';

// Mock response if no key (for dev environment safety)
if (empty($apiKey)) {
    $mock_response = [
        ['code' => 'E11.621', 'description' => 'Type 2 diabetes mellitus with foot ulcer', 'reason' => 'Patient has diabetes and a foot ulcer mentioned in wounds.'],
        ['code' => 'I87.2', 'description' => 'Venous insufficiency (chronic) (peripheral)', 'reason' => 'HPI mentions swelling and discoloration.'],
        ['code' => 'L97.519', 'description' => 'Non-pressure chronic ulcer of other part of right foot with unspecified severity', 'reason' => 'Matches wound location on right foot.']
    ];
    echo json_encode(['success' => true, 'suggestions' => $mock_response]);
    exit();
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key={$apiKey}";
$request_body = [
    "contents" => [
        ["parts" => [["text" => $system_prompt]]]
    ],
    "generationConfig" => [
        "responseMimeType" => "application/json"
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
// Fix for local XAMPP SSL issues
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code === 200) {
    $decoded = json_decode($response, true);
    $raw_text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
    
    // Clean potential markdown
    $raw_text = str_replace(['```json', '```'], '', $raw_text);
    
    // Attempt to decode JSON
    $suggestions = json_decode($raw_text, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($suggestions)) {
        echo json_encode(['success' => true, 'suggestions' => $suggestions]);
    } else {
        // Fallback if AI returns bad JSON
        error_log("AI JSON Parse Error: " . $raw_text);
        echo json_encode(['success' => false, 'message' => 'AI returned invalid format.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => "AI Error: {$http_code} - {$curl_error}"]);
}
?>