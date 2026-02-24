<?php
// Filename: api/generate_treatment_plan.php

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
session_start();

require_once '../db_connect.php'; // Includes GEMINI_API_KEY

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized."));
    exit();
}

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data)) {
        throw new Exception("No assessment data provided.");
    }

    // --- Build a detailed, context-rich prompt ---
    $prompt = "You are a clinical wound care expert. Based on the following wound assessment data, generate a treatment plan in a narrative format. Do not use a numbered list. Be specific about dressing changes and products. Do not include introductory phrases like 'Based on the data provided'.\n\n";
    $prompt .= "CRITICAL INSTRUCTION: You MUST start the response with the diagnosis in this exact format: '[Wound Type] on [Wound Location]: ' followed immediately by the treatment instructions.\n";
    $prompt .= "Example: 'Stage 4 pressure ulcer on left buttock: Leave the Adaptic dressing intact for 7 days...'\n\n";
    $prompt .= "--- WOUND ASSESSMENT DATA ---\n";

    if (!empty($data['wound_type'])) $prompt .= "Wound Type: " . $data['wound_type'] . "\n";
    if (!empty($data['wound_location'])) $prompt .= "Wound Location: " . $data['wound_location'] . "\n";
    if (!empty($data['length_cm'])) $prompt .= "Dimensions: " . $data['length_cm'] . "cm (L) x " . $data['width_cm'] . "cm (W) x " . $data['depth_cm'] . "cm (D)\n";
    if (!empty($data['granulation_percent'])) $prompt .= "Tissue: " . $data['granulation_percent'] . "% Granulation, " . $data['slough_percent'] . "% Slough\n";
    if (!empty($data['drainage_type'])) $prompt .= "Drainage: " . $data['exudate_amount'] . " " . $data['drainage_type'] . "\n";
    if (!empty($data['odor_present']) && $data['odor_present'] === 'Yes') $prompt .= "Odor: Present\n";
    if (!empty($data['signs_of_infection'])) $prompt .= "Signs of Infection: " . implode(', ', $data['signs_of_infection']) . "\n";
    if (!empty($data['periwound_condition'])) $prompt .= "Periwound: " . implode(', ', $data['periwound_condition']) . "\n";
    if (!empty($data['debridement_performed']) && $data['debridement_performed'] === 'Yes') $prompt .= "Debridement Performed: Yes, " . $data['debridement_type'] . "\n";

    // --- NEW FIELDS ---
    if (!empty($data['exposed_structures'])) $prompt .= "Exposed Structures: " . implode(', ', $data['exposed_structures']) . "\n";
    if (!empty($data['risk_factors'])) $prompt .= "Risk Factors: " . $data['risk_factors'] . "\n";
    if (!empty($data['nutritional_status'])) $prompt .= "Nutritional Status: " . $data['nutritional_status'] . "\n";
    if (!empty($data['pre_debridement_notes'])) $prompt .= "Pre-Debridement Observations: " . $data['pre_debridement_notes'] . "\n";
    if (!empty($data['medical_necessity'])) $prompt .= "Medical Necessity: " . $data['medical_necessity'] . "\n";
    if (!empty($data['dvt_edema_notes'])) $prompt .= "DVT/Edema/Graft Notes: " . $data['dvt_edema_notes'] . "\n";

    $prompt .= "\n--- TREATMENT PLAN ---\n";


    // --- Call Gemini API ---
    $api_key = GEMINI_API_KEY;
    
    if (empty($api_key)) {
        throw new Exception("Gemini API Key is missing. Please ensure GEMINI_API_KEY is set in your .env file.");
    }

    // Using user-specified model
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $api_key;

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]]
    ]);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200 || $response === false) {
        $error_details = json_decode($response, true);
        $error_message = $error_details['error']['message'] ?? 'Failed to get a response from the AI model.';
        throw new Exception($error_message);
    }

    $result = json_decode($response, true);
    $treatment_plan = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'AI could not generate a plan. Please create one manually.';

    // --- Send Response ---
    http_response_code(200);
    echo json_encode(["treatment_plan" => trim($treatment_plan)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
