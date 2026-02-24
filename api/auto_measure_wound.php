<?php
// Filename: api/auto_measure_wound.php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
session_start();

// CRITICAL FIX: Include db_connect.php FIRST to ensure GEMINI_API_KEY is defined
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized."));
    exit();
}

// --- Configuration ---
// Adjust based on your server's max memory/execution time if needed.
define('MAX_IMAGE_DIMENSION', 1024);

// CRITICAL FIX: Changed model to gemini-2.5-pro to enable JSON responseSchema
// gemini-2.5-flash-image-preview does not support structured output via schema.
define('MODEL_NAME', 'gemini-2.5-pro');

// URL construction now happens AFTER the key is defined
define('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . MODEL_NAME . ':generateContent?key=' . (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ''));

// --- Utility Functions ---

/**
 * Cleans the raw text output from the AI model by stripping markdown fences.
 * This is crucial because models often wrap JSON output in ```json...``` blocks.
 * @param string $text The raw text from the model.
 * @return string The cleaned JSON string.
 */
function clean_ai_output($text) {
    // Remove markdown fences: ```json, ```JSON, ```, and trim whitespace
    $text = preg_replace('/```(json|JSON)?\s*|```\s*/', '', $text);
    return trim($text);
}

function get_mime_type($file_path) {
    if (function_exists('mime_content_type')) {
        return mime_content_type($file_path);
    } elseif (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($file_path);
    }
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpg': case 'jpeg': return 'image/jpeg';
        case 'png': return 'image/png';
        default: return 'application/octet-stream';
    }
}

function resize_image($source_path, $mime_type) {
    list($width, $height) = getimagesize($source_path);

    if ($width <= MAX_IMAGE_DIMENSION && $height <= MAX_IMAGE_DIMENSION) {
        return file_get_contents($source_path);
    }

    $aspect_ratio = $width / $height;
    if ($width > $height) {
        $new_width = MAX_IMAGE_DIMENSION;
        $new_height = MAX_IMAGE_DIMENSION / $aspect_ratio;
    } else {
        $new_height = MAX_IMAGE_DIMENSION;
        $new_width = MAX_IMAGE_DIMENSION * $aspect_ratio;
    }

    $new_image = imagecreatetruecolor((int)$new_width, (int)$new_height);

    if ($mime_type == 'image/jpeg' || $mime_type == 'image/jpg') {
        $source = imagecreatefromjpeg($source_path);
        imagecopyresampled($new_image, $source, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $width, $height);
        ob_start();
        imagejpeg($new_image, null, 90);
        $resized_data = ob_get_clean();
        imagedestroy($source);
    } elseif ($mime_type == 'image/png') {
        $source = imagecreatefrompng($source_path);
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        imagecopyresampled($new_image, $source, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $width, $height);
        ob_start();
        imagepng($new_image);
        $resized_data = ob_get_clean();
        imagedestroy($source);
    } else {
        return file_get_contents($source_path); // Fallback to original data if not JPG/PNG
    }

    imagedestroy($new_image);
    return $resized_data;
}

// --- Main Execution ---

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new Exception("Method not allowed.");
    }

    if (empty($_FILES['wound_photo'])) {
        http_response_code(400);
        throw new Exception("No image file uploaded.");
    }

    $photo = $_FILES['wound_photo'];
    $tmp_name = $photo['tmp_name'];
    $mime_type = get_mime_type($tmp_name);

    if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
        http_response_code(400);
        throw new Exception("Invalid file type. Only JPEG and PNG are supported.");
    }

    // Resize and encode image
    $image_data_binary = resize_image($tmp_name, $mime_type);
    $base64_image = base64_encode($image_data_binary);

    // --- Gemini API Payload Construction ---
    $system_instruction = "You are a specialized wound image analysis AI. Your task is to analyze the provided wound image (which contains a measuring ruler or reference object) and extract structured data.
    1. Estimate the wound's **Length** and **Width** in centimeters (cm).
    2. Estimate the percentage distribution of Granulation, Slough, and Eschar tissue types in the wound bed. The sum of these three should be 100%.
    3. Determine the infection risk (Low, Moderate, High) based on visual signs (e.g., redness, edema, purulent exudate).
    4. Respond ONLY with a single JSON object.";

    $user_prompt = "Analyze this wound image. Provide the length and width measurements in centimeters, the tissue percentage distribution (Granulation, Slough, Eschar), and the infection risk. Return ONLY the JSON object.";

    // --- HANDLE ORIENTATION ARROW ---
    if (isset($_POST['has_orientation_arrow']) && $_POST['has_orientation_arrow'] === 'true') {
        $orientation_instruction = " IMPORTANT: The image contains a blue arrow labeled 'HEAD'. This indicates the patient's head direction. You MUST calculate the **Length** as the dimension parallel to this arrow (Head-to-Toe axis) and the **Width** as the dimension perpendicular to it (Horizontal axis).";
        $system_instruction .= $orientation_instruction;
        $user_prompt .= $orientation_instruction;
    } else {
        $system_instruction .= " 1. Estimate the wound's **Length (the vertical, top-to-bottom dimension)** and **Width (the horizontal, side-to-side dimension)**.";
    }

    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $user_prompt],
                    [
                        "inlineData" => [
                            "mimeType" => $mime_type,
                            "data" => $base64_image
                        ]
                    ]
                ]
            ]
        ],
        "systemInstruction" => ["parts" => [["text" => $system_instruction]]],
        "generationConfig" => [
            "responseMimeType" => "application/json",
            "responseSchema" => [
                "type" => "OBJECT",
                "properties" => [
                    "measurements" => [
                        "type" => "OBJECT",
                        "properties" => [
                            "length" => ["type" => "NUMBER"],
                            "width" => ["type" => "NUMBER"]
                        ]
                    ],
                    "tissue_types" => [
                        "type" => "OBJECT",
                        "properties" => [
                            "granulation" => ["type" => "NUMBER"],
                            "slough" => ["type" => "NUMBER"],
                            "eschar" => ["type" => "NUMBER"]
                        ]
                    ],
                    "infection_risk" => ["type" => "STRING"]
                ]
            ]
        ]
    ];

    $json_payload = json_encode($payload);

    // --- cURL API Call ---
    $ch = curl_init(GEMINI_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // CRITICAL FIX: Force cURL to use IPv4 to avoid "Bad IPv6 address" error on many local/shared hosts.
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

    $api_response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // --- Robust Response Handling ---
    if ($curl_error) {
        http_response_code(500);
        error_log("cURL Error on Gemini API call: " . $curl_error);
        throw new Exception("Network error during AI measurement: " . $curl_error);
    }

    $response_data = json_decode($api_response, true);

    if ($http_status !== 200 || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(500);
        error_log("Gemini API returned HTTP $http_status. Response: " . $api_response);

        // This attempts to extract a more specific error message from the response JSON
        $error_message = (
        isset($response_data['error']['message'])
            ? $response_data['error']['message']
            : "AI API request failed (Status $http_status). Check GEMINI_API_KEY and cURL."
        );
        throw new Exception($error_message);
    }

    // --- PARSE ENHANCED RESPONSE ---

    // Check if candidates exists and has parts
    if (!isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("Gemini API response structure invalid: " . $api_response);
        throw new Exception('AI returned an invalid response structure.');
    }

    $ai_raw_output = $response_data['candidates'][0]['content']['parts'][0]['text'];
    $ai_json_output = clean_ai_output($ai_raw_output); // Clean the output first!

    $ai_data = json_decode($ai_json_output, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($ai_data['measurements'], $ai_data['tissue_types'], $ai_data['infection_risk'])) {
        error_log("Failed to decode AI JSON or missing keys. Raw output: " . $ai_raw_output . " Cleaned output: " . $ai_json_output);
        throw new Exception('AI returned an invalid data format.');
    }

    $measurements = $ai_data['measurements'];
    $length = floatval($measurements['length']);
    $width = floatval($measurements['width']);
    $area = round($length * $width, 2); // Calculate area based on L x W

    $final_result = [
        "success" => true,
        "measurements" => [
            "length" => $length,
            "width" => $width,
            "area" => $area
        ],
        "tissue_types" => $ai_data['tissue_types'],
        "infection_risk" => $ai_data['infection_risk']
    ];

    http_response_code(200);
    echo json_encode($final_result);

} catch (Exception $e) {
    // Catch any exceptions thrown by utility functions or main logic
    if (http_response_code() === 200) {
        http_response_code(500); // Default to 500 if not set earlier
    }
    echo json_encode(array("success" => false, "message" => "An error occurred during AI measurement: " . $e->getMessage()));
}
?>