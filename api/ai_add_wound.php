<?php
// Filename: api/ai_add_wound.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../db_connect.php';

// Disable error display to prevent HTML injection
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(200);
    echo json_encode(["success" => false, "message" => "Server Error: $errstr"]);
    exit;
});

$json_input = file_get_contents("php://input");
$data = json_decode($json_input);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
    exit;
}

if (empty($data->patient_id) || empty($data->transcript)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Missing patient_id or transcript"]);
    exit;
}

$patient_id = intval($data->patient_id);
$transcript = $data->transcript;
$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');

// Check for Vertex AI Configuration
$use_vertex = false;
$vertex_config_path = __DIR__ . '/google_cloud_config.php';

if (file_exists($vertex_config_path)) {
    require_once $vertex_config_path;
    if (defined('GC_PROJECT_ID') && GC_PROJECT_ID !== 'your-project-id-here' && defined('GC_SERVICE_ACCOUNT_JSON') && file_exists(GC_SERVICE_ACCOUNT_JSON)) {
        $use_vertex = true;
        require_once __DIR__ . '/GoogleAuth.php';
    }
}

// Prompt for Gemini
$today = date('Y-m-d');
$prompt = <<<EOT
You are a medical assistant. Extract wound details from this text: "$transcript".
Return ONLY a valid JSON object (no markdown formatting) with these keys:
- location (string, e.g., 'Right Heel', 'Sacrum')
- wound_type (string, e.g., 'Pressure Injury', 'Venous Ulcer', 'Surgical', 'Traumatic')
- diagnosis (string, e.g., 'Stage 3 Pressure Injury', 'Diabetic Foot Ulcer')
- date_onset (string, YYYY-MM-DD format. If not specified, assume today: $today)

If the text does not describe a new wound, return {"error": "No wound description found"}.
EOT;

if ($use_vertex) {
    try {
        $auth = new GoogleAuth(GC_SERVICE_ACCOUNT_JSON);
        $accessToken = $auth->getAccessToken();
        
        $api_url = "https://" . GC_LOCATION . "-aiplatform.googleapis.com/v1/projects/" . GC_PROJECT_ID . "/locations/" . GC_LOCATION . "/publishers/google/models/" . GC_VERTEX_MODEL . ":generateContent";
        
        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.1
            ]
        ];
        
        $headers = [
            "Authorization: Bearer $accessToken",
            "Content-Type: application/json"
        ];

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => "Vertex Auth Error: " . $e->getMessage()]);
        exit;
    }
} else {
    if (empty($api_key)) {
        echo json_encode(["success" => false, "message" => "AI service is not configured."]);
        exit;
    }
    // Using a stable model
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ]
    ];
    $headers = ['Content-Type: application/json'];
}

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For XAMPP
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo json_encode(["success" => false, "message" => "Curl error: " . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

$result = json_decode($response, true);

// Extract JSON from Gemini response
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $raw_text = $result['candidates'][0]['content']['parts'][0]['text'];
    // Clean up markdown code blocks if present
    $raw_text = str_replace(['```json', '```'], '', $raw_text);
    $extracted = json_decode(trim($raw_text), true);

    if (isset($extracted['error'])) {
        echo json_encode(["success" => false, "message" => $extracted['error']]);
        exit;
    }

    if ($extracted && isset($extracted['location'])) {
        // Insert into Database
        $sql = "INSERT INTO wounds (patient_id, location, wound_type, diagnosis, date_onset, status) VALUES (?, ?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        
        $location = $extracted['location'];
        $wound_type = $extracted['wound_type'] ?? 'Other';
        $diagnosis = $extracted['diagnosis'] ?? '';
        $date_onset = $extracted['date_onset'];

        $stmt->bind_param("issss", $patient_id, $location, $wound_type, $diagnosis, $date_onset);

        if ($stmt->execute()) {
            echo json_encode([
                "success" => true, 
                "message" => "Wound added successfully: $location ($wound_type)",
                "data" => $extracted
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(["success" => false, "message" => "Failed to parse AI response", "debug" => $raw_text]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid AI response", "debug" => $result]);
}
?>