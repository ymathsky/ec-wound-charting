<?php
/**
 * api/mobile_wound_analyze.php
 * POST { image_data (base64 JPEG), wound_type, location }
 *   → Gemini Vision wound analysis: dimensions, tissue composition, clinical summary
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
$image_data = $body['image_data'] ?? '';
$wound_type = trim($body['wound_type'] ?? 'wound');
$location   = trim($body['location']   ?? 'unspecified');

if (!$image_data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'image_data required']);
    exit;
}

// Strip data URI prefix if present
if (strpos($image_data, ',') !== false) {
    [, $image_data] = explode(',', $image_data, 2);
}

$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
$url     = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$api_key}";

$prompt = "You are a clinical wound care specialist AI analyzing a wound photograph.\n"
        . "Wound type: {$wound_type}\n"
        . "Location: {$location}\n\n"
        . "Analyze this wound image and return ONLY a valid JSON object (no markdown, no explanation) with this exact structure:\n"
        . "{\n"
        . "  \"length_cm\": <estimated longest dimension in cm, float>,\n"
        . "  \"width_cm\": <estimated perpendicular width in cm, float>,\n"
        . "  \"depth_cm\": <estimated depth in cm, float, use 0.2 if unclear>,\n"
        . "  \"area_cm2\": <length * width, float>,\n"
        . "  \"tissue\": {\n"
        . "    \"granulation\": <percent 0-100, int>,\n"
        . "    \"slough\": <percent 0-100, int>,\n"
        . "    \"necrotic\": <percent 0-100, int>,\n"
        . "    \"epithelial\": <percent 0-100, int>\n"
        . "  },\n"
        . "  \"exudate_amount\": \"<None|Scant|Minimal|Moderate|Heavy>\",\n"
        . "  \"exudate_type\": \"<Serous|Serosanguineous|Sanguineous|Purulent|None>\",\n"
        . "  \"periwound_condition\": \"<brief description>\",\n"
        . "  \"odor_present\": \"<No|Yes>\",\n"
        . "  \"wound_bed\": \"<clinical description of wound bed>\",\n"
        . "  \"edges\": \"<wound edge description>\",\n"
        . "  \"clinical_summary\": \"<2-3 sentence clinical assessment and recommendations>\",\n"
        . "  \"confidence\": \"<Low|Medium|High>\"\n"
        . "}\n"
        . "Tissue percentages must sum to exactly 100. Base size estimates on visible anatomical landmarks.";

$payload = [
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $image_data]],
            ['text' => $prompt],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.1,
        'maxOutputTokens'  => 900,
        'responseMimeType' => 'application/json',
    ],
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 45,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $err = json_decode($response, true)['error']['message'] ?? "HTTP {$http_code}";
    error_log("mobile_wound_analyze error: {$err}\n{$response}");
    echo json_encode(['success' => false, 'message' => "AI analysis failed: {$err}"]);
    exit;
}

$resp_json = json_decode($response, true);
$raw_text  = trim($resp_json['candidates'][0]['content']['parts'][0]['text'] ?? '');

// Strip code-fences if model wraps anyway
if (str_starts_with($raw_text, '```')) {
    $raw_text = preg_replace('/^```[a-z]*\n?/', '', $raw_text);
    $raw_text = rtrim($raw_text, " \n`");
}

$analysis = json_decode($raw_text, true);
if (!is_array($analysis)) {
    error_log("mobile_wound_analyze parse fail: {$raw_text}");
    echo json_encode(['success' => false, 'message' => 'AI returned unparseable response. Please try again.']);
    exit;
}

// Normalise tissue percentages to exactly 100
$t     = $analysis['tissue'] ?? [];
$total = (int)($t['granulation'] ?? 0) + (int)($t['slough'] ?? 0)
       + (int)($t['necrotic'] ?? 0)    + (int)($t['epithelial'] ?? 0);
if ($total > 0 && $total !== 100) {
    $f = 100.0 / $total;
    $g = (int)round(($t['granulation'] ?? 0) * $f);
    $s = (int)round(($t['slough']      ?? 0) * $f);
    $n = (int)round(($t['necrotic']    ?? 0) * $f);
    $e = 100 - $g - $s - $n;
    $analysis['tissue'] = ['granulation' => $g, 'slough' => $s, 'necrotic' => $n, 'epithelial' => max(0, $e)];
}

echo json_encode(['success' => true, 'analysis' => $analysis]);
