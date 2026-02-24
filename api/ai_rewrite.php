<?php
// Filename: api/ai_rewrite.php
// Purpose: Rewrite medical notes to be professional using Gemini AI.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../db_connect.php';

// --- API Configuration ---
$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
// Fallback if constant not defined (though it should be based on other files)
if (!$api_key) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "API Key not configured."]);
    exit();
}

// Use a standard stable model
$api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;

$data = json_decode(file_get_contents("php://input"));

if (empty($data->text)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Text input is required."]);
    exit();
}

$input_text = $data->text;

// --- Prompt Construction ---
$system_prompt = "You are an expert medical scribe. Your task is to rewrite the provided clinical notes to be professional, concise, and use standard medical terminology. 
- Fix grammar and spelling errors.
- Convert informal shorthand to professional phrasing (e.g., 'pt' -> 'patient', 'c/o' -> 'complains of').
- Maintain the original meaning exactly; do not add or hallucinate facts.
- Return ONLY the rewritten text, no conversational filler.";

$user_query = "Rewrite the following clinical note:\n\n" . $input_text;

// --- API Call ---
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
    $rewritten_text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
    
    if ($rewritten_text) {
        echo json_encode(["success" => true, "rewritten_text" => trim($rewritten_text)]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "AI returned empty response."]);
    }
} else {
    http_response_code(500);
    // Log the actual error for debugging if needed
    error_log("Gemini API Error: $http_code - $ai_response");
    echo json_encode(["success" => false, "message" => "Failed to rewrite text. HTTP Code: " . $http_code]);
}
?>