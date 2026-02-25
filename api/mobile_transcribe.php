<?php
/**
 * api/mobile_transcribe.php
 * POST { audio_data (base64), audio_mime }
 *   → returns transcribed text via Gemini multimodal audio
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST only']);
    exit;
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$audio_data = $body['audio_data'] ?? '';
$audio_mime = $body['audio_mime'] ?? 'audio/m4a';

if (!$audio_data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'audio_data required']);
    exit;
}

// Strip data URI prefix if present
if (strpos($audio_data, ',') !== false) {
    [$header, $audio_data] = explode(',', $audio_data, 2);
    if (preg_match('/data:([^;]+);base64/', $header, $m)) {
        $audio_mime = $m[1];
    }
}

// Allowed audio MIME types Gemini supports
$allowed_mimes = ['audio/wav','audio/mp3','audio/mpeg','audio/ogg','audio/m4a',
                  'audio/aac','audio/flac','audio/webm','audio/aiff'];
if (!in_array(strtolower($audio_mime), $allowed_mimes)) {
    $audio_mime = 'audio/m4a';
}

$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
$url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$api_key";

$payload = [
    'contents' => [[
        'parts' => [
            [
                'inline_data' => [
                    'mime_type' => $audio_mime,
                    'data'      => $audio_data,
                ]
            ],
            ['text' => 'Transcribe this medical dictation exactly as spoken. Return only the transcribed text with no preamble, formatting, or commentary.']
        ]
    ]],
    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1024],
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 30,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    echo json_encode(['success' => true, 'transcript' => trim($text)]);
} else {
    $err = json_decode($response, true)['error']['message'] ?? "HTTP $http_code";
    error_log("mobile_transcribe error: $err\n$response");
    echo json_encode(['success' => false, 'message' => "Transcription error: $err"]);
}
