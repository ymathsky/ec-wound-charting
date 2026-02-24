<?php
// Filename: api/test_add_wound_logic.php
// Run this to test the AI extraction and DB insert logic without voice.

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db_connect.php';

// Mock Data
$patient_id = 1; // Ensure this patient exists
$transcript = "Add a wound to the left heel. It is a stage 3 pressure injury.";

echo "Testing with transcript: '$transcript'\n";

// --- AI Logic (Copied from ai_add_wound.php) ---
$api_key = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');

// Check for Vertex AI Configuration
$use_vertex = false;
$vertex_config_path = __DIR__ . '/google_cloud_config.php';

if (file_exists($vertex_config_path)) {
    require_once $vertex_config_path;
    if (defined('GC_PROJECT_ID') && GC_PROJECT_ID !== 'your-project-id-here' && defined('GC_SERVICE_ACCOUNT_JSON') && file_exists(GC_SERVICE_ACCOUNT_JSON)) {
        $use_vertex = true;
        require_once __DIR__ . '/GoogleAuth.php';
        echo "Using Vertex AI.\n";
    } else {
        echo "Vertex AI configured but not ready (check ID or JSON file).\n";
    }
} else {
    echo "Vertex AI config not found.\n";
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
        echo "Vertex Auth Error: " . $e->getMessage() . "\n";
        exit;
    }
} else {
    if (empty($api_key)) {
        echo "AI service is not configured (Missing API Key).\n";
        exit;
    }
    // Using a stable model
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    echo "Using Gemini API Key ($api_url)\n";
    
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
    echo "Curl error: " . curl_error($ch) . "\n";
    curl_close($ch);
    exit;
}
curl_close($ch);

echo "Raw Response: " . substr($response, 0, 500) . "...\n";

$result = json_decode($response, true);

// Extract JSON from Gemini response
if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $raw_text = $result['candidates'][0]['content']['parts'][0]['text'];
    // Clean up markdown code blocks if present
    $raw_text = str_replace(['```json', '```'], '', $raw_text);
    $extracted = json_decode(trim($raw_text), true);

    if (isset($extracted['error'])) {
        echo "AI returned error: " . $extracted['error'] . "\n";
        exit;
    }

    if ($extracted && isset($extracted['location'])) {
        echo "Extracted Data: " . print_r($extracted, true) . "\n";
        
        // Insert into Database
        $sql = "INSERT INTO wounds (patient_id, location, wound_type, diagnosis, date_onset, status) VALUES (?, ?, ?, ?, ?, 'Active')";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            echo "Prepare failed: " . $conn->error . "\n";
            exit;
        }

        $location = $extracted['location'];
        $wound_type = $extracted['wound_type'] ?? 'Other';
        $diagnosis = $extracted['diagnosis'] ?? '';
        $date_onset = $extracted['date_onset'];

        $stmt->bind_param("issss", $patient_id, $location, $wound_type, $diagnosis, $date_onset);

        if ($stmt->execute()) {
            echo "SUCCESS: Wound added to DB (ID: " . $stmt->insert_id . ")\n";
        } else {
            echo "Execute failed: " . $stmt->error . "\n";
        }
        $stmt->close();
    } else {
        echo "Failed to parse AI response or missing location.\n";
        echo "Debug Text: $raw_text\n";
    }
} else {
    echo "Invalid AI response structure.\n";
}
?>
