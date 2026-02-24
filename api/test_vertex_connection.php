<?php
// Filename: api/test_vertex_connection.php
// Run this script to verify your Vertex AI connection

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'google_cloud_config.php';
require_once 'GoogleAuth.php';

header("Content-Type: text/plain");

echo "Testing Vertex AI Connection...\n";
echo "Project ID: " . GC_PROJECT_ID . "\n";
echo "Key File: " . GC_SERVICE_ACCOUNT_JSON . "\n";

if (GC_PROJECT_ID === 'your-project-id-here') {
    echo "\n[ERROR] You must update 'api/google_cloud_config.php' with your actual Project ID.\n";
    exit;
}

if (!file_exists(GC_SERVICE_ACCOUNT_JSON)) {
    echo "\n[ERROR] Service Account Key file not found at: " . GC_SERVICE_ACCOUNT_JSON . "\n";
    echo "Please download your JSON key from Google Cloud Console and place it in the 'ec' root folder as 'service_account.json'.\n";
    exit;
}

try {
    echo "\n1. Authenticating...\n";
    $auth = new GoogleAuth(GC_SERVICE_ACCOUNT_JSON);
    $accessToken = $auth->getAccessToken();
    echo "[SUCCESS] Access Token retrieved.\n";
    // echo "Token: " . substr($accessToken, 0, 10) . "...\n";

    echo "\n2. Sending Request to Vertex AI (" . GC_VERTEX_MODEL . ")...\n";
    
    $url = "https://" . GC_LOCATION . "-aiplatform.googleapis.com/v1/projects/" . GC_PROJECT_ID . "/locations/" . GC_LOCATION . "/publishers/google/models/" . GC_VERTEX_MODEL . ":generateContent";
    
    $data = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => "Hello, are you working?"]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 100
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status: $httpCode\n";
    echo "Response:\n$response\n";

    if ($httpCode == 200) {
        echo "\n[SUCCESS] Vertex AI is working!\n";
    } else {
        echo "\n[FAILURE] API request failed.\n";
    }

} catch (Exception $e) {
    echo "\n[EXCEPTION] " . $e->getMessage() . "\n";
}
