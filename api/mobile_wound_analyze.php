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

// ── Expert system persona ────────────────────────────────────────────────
$system_instruction =
    "You are a board-certified Wound, Ostomy, and Continence Nurse (WOCN) and fellowship-trained wound care physician with 20+ years of clinical experience managing acute and chronic wounds. "
  . "You apply the TIME wound bed preparation framework (Tissue, Infection/Inflammation, Moisture balance, Edge advancement) and follow NPUAP/EPUAP/PPPIA guidelines. "
  . "Your assessments are precise, quantitative, and immediately actionable for clinical documentation. "
  . "You never hallucinate measurements — you always reason from visible anatomical cues. "
  . "When confidence is Low, you say so clearly and flag the limitations.";

// ── Detailed clinical prompt ─────────────────────────────────────────────
$prompt =
    "Perform a comprehensive clinical wound assessment on this photograph.\n\n"
  . "PATIENT CONTEXT:\n"
  . "  • Wound type:           {$wound_type}\n"
  . "  • Anatomical location:  {$location}\n\n"
  . "MEASUREMENT METHODOLOGY (apply in order):\n"
  . "  1. Identify anatomical landmarks (fingernail ≈1.5 cm wide, finger ≈2 cm, palm ≈9 cm).\n"
  . "  2. Length = longest axis aligned head-to-foot (12 o'clock → 6 o'clock).\n"
  . "  3. Width  = perpendicular to length (9 o'clock → 3 o'clock).\n"
  . "  4. Depth  = estimate from shadows/undermining; use 0.1 if clearly superficial.\n"
  . "  5. Area   = length × width (ellipse approximation: multiply by π/4 if round).\n"
  . "  6. Never default to round numbers unless the wound truly is that size.\n\n"
  . "TISSUE CLASSIFICATION (TIME framework):\n"
  . "  • Granulation (red/pink, beefy, moist)\n"
  . "  • Slough (yellow/tan, moist, soft, fibrinous — non-viable)\n"
  . "  • Necrotic/Eschar (black/brown, dry or wet — non-viable)\n"
  . "  • Epithelial (pale pink, shiny, migrating from edges)\n"
  . "  Percentages MUST sum to exactly 100.\n\n"
  . "INFECTION ASSESSMENT:\n"
  . "  Check for: perilesional erythema >2 cm, warmth, induration, purulent exudate, malodor, "
  . "  rapidly increasing wound size, friable/bleeding granulation, cellulitis, crepitus.\n\n"
  . "URGENCY FLAGS — list any that apply:\n"
  . "  Exposed bone or tendon, suspected osteomyelitis, necrotising fasciitis, "
  . "  rapid tissue destruction, suspected malignancy (irregular rolled edges, friable mass), "
  . "  extensive necrosis >50%, signs of systemic sepsis.\n\n"
  . "Return ONLY a valid JSON object (no markdown, no explanation) with EXACTLY this structure:\n"
  . "{\n"
  . "  \"length_cm\": <float>,\n"
  . "  \"width_cm\": <float>,\n"
  . "  \"depth_cm\": <float>,\n"
  . "  \"area_cm2\": <float>,\n"
  . "  \"tissue\": {\"granulation\":<int>,\"slough\":<int>,\"necrotic\":<int>,\"epithelial\":<int>},\n"
  . "  \"healing_stage\": \"<Inflammatory|Proliferative|Remodeling|Stalled|Chronic>\",\n"
  . "  \"exudate_amount\": \"<None|Scant|Minimal|Moderate|Heavy>\",\n"
  . "  \"exudate_type\": \"<None|Serous|Serosanguineous|Sanguineous|Purulent>\",\n"
  . "  \"periwound_condition\": \"<concise clinical description>\",\n"
  . "  \"odor_present\": \"<No|Yes>\",\n"
  . "  \"wound_bed\": \"<detailed description of wound bed composition and appearance>\",\n"
  . "  \"edges\": \"<well-defined|irregular|undermined|rolled/epibolic|attached|detached — describe>\",\n"
  . "  \"infection_signs\": [<array of strings — any present signs; empty [] if none>],\n"
  . "  \"urgency_flags\": [<array of strings — critical findings requiring immediate attention; empty [] if none>],\n"
  . "  \"treatment_suggestions\": [<array of 3-5 evidence-based, specific treatment strings>],\n"
  . "  \"clinical_summary\": \"<3-4 sentence summary covering status, trajectory, TIME assessment, and priorities>\",\n"
  . "  \"confidence\": \"<Low|Medium|High>\"\n"
  . "}\n\n"
  . "RULES:\n"
  . "1. tissue percentages must sum to exactly 100\n"
  . "2. infection_signs and urgency_flags are JSON arrays (can be [])\n"
  . "3. treatment_suggestions must have exactly 3-5 items\n"
  . "4. healing_stage must be one of the 5 listed values\n"
  . "5. If image quality is poor, state limitations in clinical_summary and set confidence to Low";

$payload = [
    'system_instruction' => [
        'parts' => [['text' => $system_instruction]],
    ],
    'contents' => [[
        'parts' => [
            ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $image_data]],
            ['text' => $prompt],
        ],
    ]],
    'generationConfig' => [
        'temperature'      => 0.15,
        'maxOutputTokens'  => 1500,
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

// Ensure new array fields are always arrays (not null/string)
foreach (['infection_signs', 'urgency_flags', 'treatment_suggestions'] as $field) {
    if (!isset($analysis[$field]) || !is_array($analysis[$field])) {
        $analysis[$field] = [];
    }
}

// Ensure healing_stage has a value
if (empty($analysis['healing_stage'])) {
    $analysis['healing_stage'] = 'Unknown';
}

echo json_encode(['success' => true, 'analysis' => $analysis]);
