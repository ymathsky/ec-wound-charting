<?php
// Filename: ec/api/generate_diagnosis.php
// Description: Uses the Gemini API to suggest an ICD-10 code based on wound data.

header('Content-Type: application/json');

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed.']);
    exit;
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

$wound_summary = isset($data['wound_summary']) ? $data['wound_summary'] : '';
$patient_details = isset($data['patient_details']) ? $data['patient_details'] : '';

if (empty($wound_summary)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Wound summary is required for diagnosis generation.']);
    exit;
}

// --- 1. LOAD ENVIRONMENT & API KEY ---
// We include db_connect.php because it contains the loadEnv function.
// We use __DIR__ . '/../' to go up one level from 'api' to the 'ec' root.
require_once __DIR__ . '/../db_connect.php';

$apiKey = getenv('GEMINI_API_KEY');
// -------------------------------------

// --- 2. CHECK API KEY ---
if (empty($apiKey)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'AI Suggestion is not configured. GEMINI_API_KEY is missing from the .env file.']);
    exit;
}

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key={$apiKey}";

// Set a system prompt to define the model's role and expected output structure
$systemPrompt = "You are a clinical coding specialist. Your task is to analyze the provided wound and patient context, and suggest the single most appropriate ICD-10-CM code and its description. Respond ONLY with a JSON array containing a single object that strictly adheres to the provided schema.";

// Construct the user query combining patient details and wound summary
$userQuery = "Based on the following patient context and wound assessment, suggest the most appropriate ICD-10-CM code and a concise description:\n\n";
if (!empty($patient_details)) {
    $userQuery .= "Patient Context: {$patient_details}\n\n";
}
$userQuery .= "Wound Assessment: {$wound_summary}";

// Define the required JSON schema for the response
$responseSchema = [
    'type' => 'ARRAY',
    'items' => [
        'type' => 'OBJECT',
        'properties' => [
            'icd10_code' => ['type' => 'STRING', 'description' => 'The suggested ICD-10 code, e.g., L97411'],
            'description' => ['type' => 'STRING', 'description' => 'The official description of the code, e.g., Non-pressure chronic ulcer of right heel and midfoot, limited to breakdown of skin']
        ],
        'required' => ['icd10_code', 'description']
    ]
];

// --- 3. FIX: CORRECTED PAYLOAD STRUCTURE ---
// systemInstruction and generationConfig must be top-level properties.
$payload = [
    'contents' => [['parts' => [['text' => $userQuery]]]],
    'systemInstruction' => [
        'parts' => [['text' => $systemPrompt]]
    ],
    'generationConfig' => [
        'responseMimeType' => "application/json",
        'responseSchema' => $responseSchema,
        'temperature' => 0.5 // Add some creativity control
    ]
];
// --- END FIX ---


// --- API Call with Retry Logic ---
$maxRetries = 3;
$delay = 1; // seconds

for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        // Suppress errors with @ and handle manually
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Type: application/json',
                'content' => json_encode($payload),
                'ignore_errors' => true // Necessary to read non-200 status codes
            ]
        ]);
        $response = @file_get_contents($apiUrl, false, $context);

        if ($response === false) {
            if ($attempt < $maxRetries) {
                sleep($delay);
                $delay *= 2;
                continue;
            }
            throw new Exception("API failed after {$maxRetries} attempts. Response was false.");
        }

        // Check HTTP status code
        $http_response_header_safe = $http_response_header ?? [];
        $status_line = $http_response_header_safe[0] ?? '';
        preg_match('{HTTP\/\S+\s(\d{3})}', $status_line, $match);
        $status = intval($match[1] ?? 0);

        if ($status !== 200) {
            // Don't retry on auth errors
            if ($status === 403) {
                throw new Exception("Gemini API returned HTTP 403 (Permission Denied). Check your API Key.");
            }
            if ($attempt < $maxRetries) {
                sleep($delay);
                $delay *= 2;
                continue;
            }
            throw new Exception("Gemini API returned HTTP status {$status}. Response: {$response}");
        }

        $result = json_decode($response, true);

        // Check if the model returned structured content
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $jsonText = $result['candidates'][0]['content']['parts'][0]['text'];

            // Clean and parse the resulting JSON string
            $parsedJson = json_decode($jsonText, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($parsedJson) && !empty($parsedJson)) {
                echo json_encode(['success' => true, 'data' => $parsedJson[0]]);
                exit;
            }
        }

        // If parsing failed or structure was incorrect, try again (if possible)
        if ($attempt < $maxRetries) {
            sleep($delay);
            $delay *= 2;
            continue;
        }

        throw new Exception("Model response was invalid or empty: " . substr($response, 0, 200));

    } catch (Exception $e) {
        error_log("Gemini API error on attempt {$attempt}: " . $e->getMessage());
        if ($attempt === $maxRetries) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Failed to generate diagnosis via AI: {$e->getMessage()}"]);
            exit;
        }
    }
}
?>