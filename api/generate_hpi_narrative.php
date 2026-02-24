<?php
// Filename: api/generate_hpi_narrative.php
// UPDATED: Stronger Prompt Engineering to force Name/Age inclusion.
// UPDATED: Better fallback logic if DB lookup fails.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// 1. Get Raw Data
$input = json_decode(file_get_contents("php://input"), true);

// We expect 'patient_id' and specific question keys
$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
$patient_name_from_js = isset($input['Patient Name']) ? trim($input['Patient Name']) : 'The patient';

// 2. Fetch Real Demographics from DB
$patient_context = "";
$demographics_found = false;

if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT date_of_birth, gender, first_name, last_name FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Calculate Age
        $dob = new DateTime($row['date_of_birth']);
        $now = new DateTime();
        $interval = $now->diff($dob);
        $age = $interval->y;

        $gender = $row['gender'];
        $full_name = $row['first_name'] . " " . $row['last_name'];

        // Construct the context string for the AI
        $patient_context = "Name: {$full_name}\nAge: {$age}\nGender: {$gender}";
        $demographics_found = true;
    }
    $stmt->close();
}

// Fallback: If DB lookup failed, use the name from JS
if (!$demographics_found) {
    $patient_context = "Name: {$patient_name_from_js}";
}

// 3. Prepare the Prompt Data
// Filter out metadata keys to leave only the clinical answers
$clinical_data = [];
$style = isset($input['style']) ? $input['style'] : 'standard'; // Default style

foreach ($input as $key => $value) {
    if ($key !== 'patient_id' && $key !== 'appointment_id' && $key !== 'Patient Name' && $key !== 'style') {
        $clinical_data[$key] = $value;
    }
}

// If no clinical data, return early
if (empty($clinical_data)) {
    // Use the calculated context for the empty message if available
    $display_name = $demographics_found ? $full_name : $patient_name_from_js;
    echo json_encode(['success' => true, 'narrative' => "{$display_name} is here for a follow-up visit. No specific HPI complaints recorded."]);
    exit();
}

// 4. Construct the Prompt
$data_string = json_encode($clinical_data);

// --- STYLE INSTRUCTIONS ---
$style_instruction = "";
switch ($style) {
    case 'detailed':
        $style_instruction = "4. Write a DETAILED and COMPREHENSIVE narrative. Elaborate on the symptoms and their context. Use complex medical terminology where appropriate.";
        break;
    case 'brief':
        $style_instruction = "4. Be VERY CONCISE. Use short sentences or bullet points for key symptoms. Focus only on the most critical information.";
        break;
    case 'patient':
        $style_instruction = "4. Write in a PATIENT-FRIENDLY tone. Use simpler language that a patient could understand, but keep it in the third person (e.g., 'Mr. Smith reports pain...'). Avoid overly dense medical jargon.";
        break;
    case 'standard':
    default:
        $style_instruction = "4. Be concise, professional, and clinical. Use standard medical terminology.";
        break;
}

// --- PROMPT ENGINEERING ---
// We split instructions to be very clear about the opening sentence.
$system_prompt = "You are a medical scribe assistant writing a 'History of Present Illness' (HPI).

PATIENT DEMOGRAPHICS:
{$patient_context}

CLINICAL DATA:
{$data_string}

INSTRUCTIONS:
1. **MANDATORY**: Start the narrative by identifying the patient by Name, Age, and Gender (e.g., 'Mr. John Doe, a 55-year-old male, presents with...').
2. Use the CLINICAL DATA to construct the rest of the paragraph.
3. Write in the third person.
{$style_instruction}
5. Do not invent symptoms not listed in the data.";

// 5. Call the Gemini API
$apiKey = getenv('GEMINI_API_KEY') ?: '';

if (empty($apiKey)) {
    // Fallback for demo/testing
    $simulated_narrative = "{$patient_context}. Presents with: " . implode(", ", array_values($clinical_data));
    echo json_encode(['success' => true, 'narrative' => $simulated_narrative]);
    exit();
}

$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key={$apiKey}";

$request_body = [
    "contents" => [
        [
            "parts" => [
                ["text" => $system_prompt]
            ]
        ]
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $decoded = json_decode($response, true);
    $narrative = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'Error generating text.';

    // Clean up markdown
    $narrative = str_replace(['**', '##'], '', $narrative);

    echo json_encode(['success' => true, 'narrative' => trim($narrative)]);
} else {
    echo json_encode(['success' => false, 'message' => "AI Error: {$http_code}"]);
}
?>