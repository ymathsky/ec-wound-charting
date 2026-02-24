<?php
// Filename: api/ai_companion.php

// 1. Initialize Output Buffering and Error Handling IMMEDIATELY
ob_start(); // Capture any stray output (warnings, notices, HTML)
ini_set('display_errors', 0); // Do not print errors to stdout
error_reporting(E_ALL); // Report all errors internally

// Custom error handler to catch warnings/notices and return JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    $logMsg = date('Y-m-d H:i:s') . " Error ($errno): $errstr in $errfile:$errline\n";
    file_put_contents(__DIR__ . '/error_log.txt', $logMsg, FILE_APPEND);
    
    // Don't exit, just log. Let the script continue if possible, or let shutdown function handle fatal.
    // If we want to stop on warnings, we can exit here.
    // For now, let's just log warnings and let execution proceed if possible.
    return true; 
});

// Handle fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        $logMsg = date('Y-m-d H:i:s') . " Fatal Error: {$error['message']} in {$error['file']}:{$error['line']}\n";
        file_put_contents(__DIR__ . '/error_log.txt', $logMsg, FILE_APPEND);

        // Clear any buffered output (HTML error pages etc)
        if (ob_get_length()) ob_clean();
        
        http_response_code(200); // Return 200 so frontend can parse the JSON
        header("Content-Type: application/json; charset=UTF-8");
        echo json_encode(["success" => false, "message" => "Fatal Error: " . $error['message']]);
        exit;
    }
    // Flush buffer if no error
    ob_end_flush();
});

// Increase execution time for long AI requests
set_time_limit(300);

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../db_connect.php';
require_once 'vertex_auth.php';

function makeCurlRequest($url, $payload, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) return ['error' => $error];
    return json_decode($response, true);
}

function generateContent($payload) {
    $vertexAuth = new VertexAuth();
    $projectId = $vertexAuth->getProjectId();
    
    if ($projectId) {
        // Use Vertex AI
        try {
            $accessToken = $vertexAuth->getAccessToken();
            $location = 'us-central1';
            $modelId = 'gemini-2.0-flash';
            $url = "https://$location-aiplatform.googleapis.com/v1/projects/$projectId/locations/$location/publishers/google/models/$modelId:generateContent";
            
            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ];
            
            return makeCurlRequest($url, $payload, $headers);
        } catch (Exception $e) {
            return ['error' => "Vertex Auth Error: " . $e->getMessage()];
        }
    } else {
        // Use Gemini API Key
        $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : getenv('GEMINI_API_KEY');
        if (!$apiKey) return ['error' => 'No API Key or Service Account found.'];
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey";
        $headers = ['Content-Type: application/json'];
        
        return makeCurlRequest($url, $payload, $headers);
    }
}

// (Error handlers moved to top of file)

if (!extension_loaded('curl')) {
    echo json_encode(["success" => false, "message" => "cURL extension is not enabled on this server."]);
    exit;
}

$json_input = file_get_contents("php://input");
$data = json_decode($json_input);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid JSON input.", "debug" => json_last_error_msg()]);
    exit;
}

// Determine Action & Common Params
$action = isset($data->action) ? $data->action : 'chat';
$patient_id = isset($data->patient_id) ? intval($data->patient_id) : 0;
$appointment_id = isset($data->appointment_id) ? intval($data->appointment_id) : 0;
$user_id = (isset($data->user_id) && intval($data->user_id) > 0) ? intval($data->user_id) : null;

// --- TRANSCRIBE AUDIO ACTION ---
if ($action === 'transcribe_audio') {
    $audioData = isset($data->audio) ? $data->audio : '';
    $mimeType = isset($data->mimeType) ? $data->mimeType : 'audio/webm';
    
    // DEBUG LOGGING
    file_put_contents(__DIR__ . '/debug_audio_log.txt', date('Y-m-d H:i:s') . " - Received audio. Length: " . strlen($audioData) . " Mime: $mimeType\n", FILE_APPEND);

    if (empty($audioData)) {
        echo json_encode(["success" => false, "message" => "No audio data provided."]);
        exit;
    }

    $prompt = "Transcribe the following audio exactly as spoken. Do not add any commentary or markdown formatting. Just the text.";
    
    $payload = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inline_data" => [
                            "mime_type" => $mimeType,
                            "data" => $audioData
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.2,
            "maxOutputTokens" => 2048
        ]
    ];
    
    $response = generateContent($payload);
    
    // DEBUG LOGGING
    file_put_contents(__DIR__ . '/debug_audio_log.txt', date('Y-m-d H:i:s') . " - Response: " . json_encode($response) . "\n", FILE_APPEND);

    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        echo json_encode(['success' => true, 'text' => $response['candidates'][0]['content']['parts'][0]['text']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to transcribe audio.', 'debug' => $response]);
    }
    exit;
}

// --- SAVE DRAFT ACTION ---
if ($action === 'save_draft') {
    if (!isset($data->draft_data)) {
        echo json_encode(["success" => false, "message" => "No draft data provided."]);
        exit;
    }
    
    $live_note_content = isset($data->draft_data->live_note) ? $data->draft_data->live_note : '';
    // Fix: user_id might be 0 or null. If 0, convert to NULL to avoid FK constraint violation (fk_note_user)
    $user_id_safe = (isset($user_id) && intval($user_id) > 0) ? intval($user_id) : null;

    // Use atomic INSERT ... ON DUPLICATE KEY UPDATE to prevent race conditions 
    // with concurrent AI processing which might also be creating the record.
    $sql_upsert = "INSERT INTO visit_notes (appointment_id, patient_id, user_id, live_note, note_date) 
                   VALUES (?, ?, ?, ?, NOW()) 
                   ON DUPLICATE KEY UPDATE live_note = VALUES(live_note)";
                   
    $stmt_upsert = $conn->prepare($sql_upsert);
    if ($stmt_upsert) {
        $stmt_upsert->bind_param("iiis", $appointment_id, $patient_id, $user_id_safe, $live_note_content);
        if ($stmt_upsert->execute()) {
            echo json_encode(["success" => true, "message" => "Draft saved to visit_notes."]);
        } else {
             // Log error for debugging
            $error = $stmt_upsert->error;
            error_log("Save Draft DB Error: " . $error);
            echo json_encode(["success" => false, "message" => "Database error during save.", "error" => $error]);
        }
        $stmt_upsert->close();
    } else {
        echo json_encode(["success" => false, "message" => "Failed to prepare statement."]);
    }
    exit;
}

// --- SAVE VITALS ACTION ---
if ($action === 'save_vitals') {
    $vitals = isset($data->vitals) ? $data->vitals : [];
    
    if (empty($vitals)) {
        echo json_encode(["success" => false, "message" => "No vitals data."]);
        exit;
    }

    // Helper functions for conversion
    function toKg($lbs) { return $lbs * 0.453592; }
    function toCm($inches) { return $inches * 2.54; }
    function toCelsius($fahrenheit) { return ($fahrenheit - 32) * 5/9; }

    // 1. Fetch existing vitals to merge updates (since we might only get partial data)
    $sql_get = "SELECT * FROM patient_vitals WHERE appointment_id = ?";
    $stmt_get = $conn->prepare($sql_get);
    $stmt_get->bind_param("i", $appointment_id);
    $stmt_get->execute();
    $existing = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    // 2. Prepare values (Merge new with existing or default to null)
    // Note: DB stores Metric, but input is Imperial.
    
    // Height
    $height_in = isset($vitals->height_in) ? floatval($vitals->height_in) : null;
    $height_cm = $height_in ? round(toCm($height_in), 2) : ($existing['height_cm'] ?? null);
    // If we didn't get new height but have existing cm, convert back to inches for BMI calc if needed
    if (!$height_in && $height_cm) $height_in = $height_cm / 2.54;

    // Weight
    $weight_lbs = isset($vitals->weight_lbs) ? floatval($vitals->weight_lbs) : null;
    $weight_kg = $weight_lbs ? round(toKg($weight_lbs), 2) : ($existing['weight_kg'] ?? null);
    if (!$weight_lbs && $weight_kg) $weight_lbs = $weight_kg / 0.453592;

    // Temp
    $temp_f = isset($vitals->temperature_f) ? floatval($vitals->temperature_f) : null;
    $temp_c = $temp_f ? round(toCelsius($temp_f), 2) : ($existing['temperature_celsius'] ?? null);

    // BP
    $bp = isset($vitals->blood_pressure) ? $vitals->blood_pressure : ($existing['blood_pressure'] ?? null);
    // Handle split BP if provided
    if (isset($vitals->bp_systolic) && isset($vitals->bp_diastolic)) {
        $bp = $vitals->bp_systolic . '/' . $vitals->bp_diastolic;
    }

    // Other fields
    $hr = isset($vitals->heart_rate) ? intval($vitals->heart_rate) : ($existing['heart_rate'] ?? null);
    $rr = isset($vitals->respiratory_rate) ? intval($vitals->respiratory_rate) : ($existing['respiratory_rate'] ?? null);
    $o2 = isset($vitals->oxygen_saturation) ? intval($vitals->oxygen_saturation) : ($existing['oxygen_saturation'] ?? null);

    // BMI Calculation
    $bmi = ($existing['bmi'] ?? null);
    if ($height_in > 0 && $weight_lbs > 0) {
        $bmi = round(($weight_lbs / ($height_in * $height_in)) * 703, 1);
    }

    // 3. Save to DB (REPLACE INTO to handle upsert)
    $sql = "REPLACE INTO patient_vitals 
            (patient_id, appointment_id, visit_date, height_cm, weight_kg, bmi, blood_pressure, heart_rate, respiratory_rate, temperature_celsius, oxygen_saturation) 
            VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "DB Prepare Error: " . $conn->error]);
        exit;
    }

    // Fix parameters to match type definition "iidddsiidi" (10 chars, 10 args)
    // i i d d d s i i d i (Wait, count is 10)
    // 1. patient_id (i)
    // 2. appointment_id (i)
    // 3. height_cm (d)
    // 4. weight_kg (d)
    // 5. bmi (d)
    // 6. bp (s)
    // 7. hr (i)
    // 8. rr (i) (Wait, type string says 'i', variable is $rr)
    // 9. temp_c (d)
    // 10. o2 (i)
    
    // There was a mismatch in previous versions. Let's ensure types are cast correctly.
    $rr_int = $rr !== null ? intval($rr) : null;
    $o2_int = $o2 !== null ? intval($o2) : null;
    $hr_int = $hr !== null ? intval($hr) : null;

    $stmt->bind_param(
        "iidddsiidi",
        $patient_id,
        $appointment_id,
        $height_cm,
        $weight_kg,
        $bmi,
        $bp,
        $hr_int,
        $rr_int,
        $temp_c,
        $o2_int
    );

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Vitals saved."]);
    } else {
        echo json_encode(["success" => false, "message" => "DB Error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// --- SAVE CHAT MESSAGE ACTION ---
if ($action === 'save_chat_message') {
    $message = isset($data->message) ? trim($data->message) : '';
    $image_path = isset($data->image_path) ? trim($data->image_path) : null;
    $sender = isset($data->sender) ? $data->sender : 'user'; // 'user' or 'ai'

    if (empty($message) && empty($image_path)) {
        echo json_encode(["success" => false, "message" => "Empty message."]);
        exit;
    }

    $sql = "INSERT INTO visit_ai_messages (patient_id, appointment_id, sender, message, image_path, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iisss", $patient_id, $appointment_id, $sender, $message, $image_path);
    
    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Message saved."]);
    } else {
        echo json_encode(["success" => false, "message" => "DB Error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// --- PARSE DRAFT ACTION (For Review Modal) ---
if ($action === 'parse_draft') {
    $note_html = isset($data->note_html) ? $data->note_html : '';
    
    if (empty($note_html)) {
        echo json_encode(["success" => false, "message" => "No note content provided."]);
        exit;
    }

    // 1. Extract SOAP Sections from HTML
    $sections = ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''];
    $current_section = null;
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $note_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    foreach ($dom->childNodes as $node) {
        if ($node->nodeName === 'xml') continue;

        if ($node->nodeName === 'h2') {
            $header = strtolower(trim($node->textContent));
            if (strpos($header, 'subjective') !== false) $current_section = 'subjective';
            elseif (strpos($header, 'objective') !== false) $current_section = 'objective';
            elseif (strpos($header, 'assessment') !== false) $current_section = 'assessment';
            elseif (strpos($header, 'plan') !== false) $current_section = 'plan';
            else $current_section = null; 
        } elseif ($current_section && isset($sections[$current_section])) {
            $sections[$current_section] .= $dom->saveHTML($node);
        } else {
            $sections['subjective'] .= $dom->saveHTML($node);
        }
    }
    
    foreach ($sections as $k => $v) $sections[$k] = trim($v);
    if (empty($sections['subjective']) && empty($sections['objective']) && empty($sections['assessment']) && empty($sections['plan'])) {
        $sections['subjective'] = $note_html;
    }

    // 1.5 Manual Extraction of Key Fields (Fallback/Priority)
    $manual_extracted = [
        'chief_complaint' => null,
        'hpi' => null,
        'ros' => null
    ];
    
    $xpath = new DOMXPath($dom);
    
    // Helper to extract text from a node's siblings or content
    // Logic: 
    // 1. If the node text (normalized) is just the header (e.g. "Chief Complaint:"), grab siblings.
    // 2. If the node text contains the value (e.g. "Chief Complaint: Pain in leg"), extract the value.
    
    $field_configs = [
        'chief_complaint' => ['chief complaint', 'reason for visit'],
        'hpi' => ['history of present illness', 'hpi'],
        'ros' => ['review of systems', 'ros']
    ];

    $search_tags = ['h2', 'h3', 'h4', 'strong', 'b', 'span', 'p', 'div'];

    foreach ($field_configs as $field_key => $search_phrases) {
        // Construct XPath to search for any of the phrases in any of the tags
        $xpath_queries = [];
        foreach ($search_tags as $tag) {
            foreach ($search_phrases as $phrase) {
                // XPath 1.0 Case Insensitive Translate
                $phrase_cleaned = strtolower($phrase);
                // We convert the text content to lowercase and check if it contains the phrase
                $xpath_queries[] = "//{$tag}[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$phrase_cleaned}')]";
            }
        }
        
        // Try all queries for this field
        foreach ($xpath_queries as $query) {
            $nodes = $xpath->query($query);
            foreach ($nodes as $node) {
                $nodeText = trim(strip_tags($dom->saveHTML($node)));
                $nodeTextLower = strtolower($nodeText);
                
                // Which phrase matched?
                $matched_phrase = '';
                foreach ($search_phrases as $ph) {
                    if (strpos($nodeTextLower, strtolower($ph)) !== false) {
                        $matched_phrase = $ph;
                        break;
                    }
                }
                
                if (!$matched_phrase) continue;

                // Check if this is a "Header Only" node (e.g., "Chief Complaint" or "Chief Complaint:")
                // Remove non-alphanumeric chars from end to check
                $clean_node_text = preg_replace('/[^a-zA-Z0-9]+$/', '', $nodeTextLower);
                $clean_phrase = preg_replace('/[^a-zA-Z0-9]+$/', '', strtolower($matched_phrase));
                
                $content = '';
                
                if ($clean_node_text === $clean_phrase) {
                    // It's a header. Get siblings.
                    $sibling = $node->nextSibling;
                    while ($sibling) {
                        // Stop if we hit another header-like element
                        if ($sibling->nodeType === XML_ELEMENT_NODE && in_array(strtolower($sibling->nodeName), ['h2', 'h3', 'h4'])) {
                             // Double check it's not just a bold tag inside a sentence
                             break;
                        }
                        // Stop if we hit a node that looks like another field header (e.g. "HPI:")
                        $siblingText = trim($sibling->textContent);
                        if (!empty($siblingText) && (preg_match('/^(hist|ros|chief|plan|assess|object|subject)/i', $siblingText))) {
                            // simplistic check to avoid overrun
                        }

                        $content .= $dom->saveHTML($sibling);
                        $sibling = $sibling->nextSibling;
                    }
                } else {
                    // The content is inline. E.g. "Chief Complaint: My leg hurts"
                    // We need to strip the header part.
                    // Regex to replace the phrase + optional colon/whitespace at start
                    $pattern = '/^' . preg_quote($matched_phrase, '/') . '[:\-\s]*/i';
                    $content = preg_replace($pattern, '', $nodeText);
                    
                    // Note: If the node was complex HTML, we might lose formatting here by using nodeText.
                    // But usually inline headers are simple text or spans. 
                    // Let's stick to text extraction for inline for safety.
                }

                $content = trim(strip_tags($content));
                // Cleanup leading colons/hyphens again just in case
                $content = ltrim($content, ":- \t\n\r\0\x0B");

                if (!empty($content) && strlen($content) > 2) {
                    $manual_extracted[$field_key] = $content;
                    break 2; // Found a valid value for this field, move to next field
                }
            }
        }
    }

    // --- 1.6 Additional Manual Extraction: Vitals, Meds, Diagnoses ---
    
    // Vitals Patterns (Regex on the full text)
    $text_content = strip_tags($note_html);
    $manual_vitals = [];
    
    // BP
    if (preg_match('/(?:BP|Blood Pressure)[:\s]+(\d{2,3}[\/]\d{2,3})/i', $text_content, $m) || preg_match('/\b(\d{2,3}[\/]\d{2,3})\s*(?:mmHg)?\b/', $text_content, $m)) {
        $manual_vitals['blood_pressure'] = $m[1];
    }
    // HR
    if (preg_match('/(?:HR|Heart Rate|Pulse)[:\s]+(\d{2,3})/i', $text_content, $m) || preg_match('/\b(\d{2,3})\s*bpm\b/i', $text_content, $m)) {
        $manual_vitals['heart_rate'] = $m[1];
    }
    // RR
    if (preg_match('/(?:RR|Resp|Respiratory)[:\s]+(\d{1,2})/i', $text_content, $m)) {
        $manual_vitals['respiratory_rate'] = $m[1];
    }
    // Temp
    if (preg_match('/(?:Temp|Temperature)[:\s]+(\d{2,3}\.?\d?)/i', $text_content, $m) || preg_match('/\b(\d{2,3}\.?\d?)\s*[°]?[Ff]\b/', $text_content, $m)) {
        $manual_vitals['temperature_f'] = $m[1];
    }
    // O2
    if (preg_match('/(?:SpO2|O2|Oxygen)[:\s]+(\d{2,3})/i', $text_content, $m) || preg_match('/\b(\d{2,3})\s*%\b/', $text_content, $m)) {
        // filter out unlikely values for O2 (e.g. 100% wound healing) if confused, but % usually implies O2 in vitals context or wound size
        if ((int)$m[1] > 80 && (int)$m[1] <= 100) $manual_vitals['oxygen_saturation'] = $m[1];
    }
    // Weight
    if (preg_match('/(?:Weight|Wt)[:\s]+(\d{2,3}\.?\d?)/i', $text_content, $m) || preg_match('/\b(\d{2,3}\.?\d?)\s*(?:lbs|pounds)\b/i', $text_content, $m)) {
        $manual_vitals['weight_lbs'] = $m[1];
    }
    // Height
    if (preg_match('/(?:Height|Ht)[:\s]+(\d{2,3}\.?\d?)/i', $text_content, $m) || preg_match('/\b(\d{2,3}\.?\d?)\s*(?:in|inches)\b/i', $text_content, $m)) {
        $manual_vitals['height_in'] = $m[1];
    }

    // List Extractors for Meds/Diagnoses
    // We look for headers, then parse lines.
    $list_fields = [
        'medications' => ['medications', 'current medications', 'meds', 'plan'], 
        'diagnoses' => ['diagnoses', 'assessment', 'impression']
    ];
    
    $manual_lists = ['medications' => [], 'diagnoses' => []];

    foreach ($list_fields as $key => $headers) {
        foreach ($headers as $header) {
            // Find the header node
            $query = "//h2[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$header')] | //h3[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$header')] | //strong[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$header')]";
            $nodes = $xpath->query($query);
            
            if ($nodes->length > 0) {
                // We found a section. Let's look for Lists (UL/OL) or lines.
                $headerNode = $nodes->item(0);
                $sibling = $headerNode->nextSibling;
                $items = [];
                
                // Scan only a bit forward
                $scan_limit = 10; 
                $scanned = 0;
                
                while ($sibling && $scanned < $scan_limit) {
                    if ($sibling->nodeType === XML_ELEMENT_NODE) {
                        $tagName = strtolower($sibling->nodeName);
                        // Stop at next section
                        if (in_array($tagName, ['h2', 'h3'])) break;
                        
                        if ($tagName === 'ul' || $tagName === 'ol') {
                            // Extract LIs
                            foreach ($sibling->childNodes as $li) {
                                if (strtolower($li->nodeName) === 'li') {
                                    $txt = trim($li->textContent);
                                    if ($txt) $items[] = $txt;
                                }
                            }
                        } elseif ($tagName === 'p' || $tagName === 'div') {
                            // Check for newline separated content or simplistic mapping
                            $lines = explode("\n", trim($sibling->textContent));
                            foreach ($lines as $line) {
                                $line = trim($line);
                                // remove numbering "1. " or "- "
                                $clean = preg_replace('/^[\d\-\.\s]+/', '', $line);
                                if (!empty($clean) && strlen($clean) > 3) $items[] = $clean;
                            }
                        }
                    }
                    $sibling = $sibling->nextSibling;
                    $scanned++;
                }

                if (!empty($items)) {
                    // Map to expected objects
                    foreach ($items as $item) {
                        if ($key === 'medications') {
                            // Try to parse "Drug 10mg bid"
                             // Very naive parsing, but better than nothing
                             $manual_lists['medications'][] = [
                                 'drug_name' => $item, 
                                 'dosage' => '', 
                                 'frequency' => '', 
                                 'route' => '', 
                                 'status' => 'Active'
                             ];
                        } elseif ($key === 'diagnoses') {
                             $manual_lists['diagnoses'][] = [
                                 'icd10_code' => '', // Hard to regex
                                 'description' => $item
                             ];
                        }
                    }
                    if (!empty($manual_lists[$key])) break; // Found matches for this key
                }
            }
        }
    }

    // 2. AI Extraction for Structured Data
    $extraction_prompt = "You are a medical data extraction assistant. Your job is to extract clinical data from the provided text, which may be a mix of structured headers and unstructured dictation.

    CRITICAL INSTRUCTION:
    - If the input is unstructured narrative (e.g. \"Patient has a 2x2 ulcer on left leg\"), you MUST infer and extract the wound details.
    - Consolidate wound information found in different sections (e.g. Location in HPI, Dimensions in Objective).
    - Do NOT split a single wound into multiple entries.
    - If dimensions are found (e.g. 3x2x0.1), assign them reliably to the corresponding wound location.

    Return a VALID JSON object with these keys:
    - 'chief_complaint': string or null
    - 'hpi': string or null (narrative history)
    - 'ros': string or null
    - 'diagnoses': Array of objects { 'icd10_code': '...', 'description': '...' }.
    - 'medications': Array of objects { 'drug_name': '...', 'dosage': '...', 'frequency': '...', 'route': '...', 'status': 'Active' }.
    - 'wounds': Array of objects { 
        'location': 'Specific body location (e.g. Left Lower Leg, Right Heel)', 
        'type': 'Wound type (e.g. Pressure Ulcer, Diabetic Ulcer, Surgical)', 
        'length_cm': number (float), 
        'width_cm': number (float), 
        'depth_cm': number (float), 
        'pain_level': number (0-10), 
        'drainage_type': 'Serous, Sanguineous, Purulent, etc.', 
        'exudate_amount': 'Scant, Moderate, Heavy, etc.', 
        'odor_present': 'Yes' or 'No' 
      }.
    - 'vitals': Object { 'blood_pressure': '...', 'heart_rate': 0, 'respiratory_rate': 0, 'oxygen_saturation': 0, 'temperature_f': 0.0, 'weight_lbs': 0.0, 'height_in': 0.0 }.
    - 'procedure': Object { 'narrative': 'Full procedure note narrative' } if a procedure was performed.

    Clinical Note to Parse:
    " . strip_tags(str_replace(['</div>', '</p>', '</h2>', '</h1>', '<br>', '<br/>'], "\n\n", $note_html));

    $parsed_data = [];
    
    $payload = [
        "contents" => [["parts" => [["text" => $extraction_prompt]]]],
        "generationConfig" => ["responseMimeType" => "application/json"]
    ];
    
    $result = generateContent($payload);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_text = $result['candidates'][0]['content']['parts'][0]['text'];
        
        // Debug Log
        file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . " [ParseDraft] Raw AI Response: " . substr($raw_text, 0, 500) . "...\n", FILE_APPEND);

        // Robust JSON Extraction
        if (preg_match('/\{[\s\S]*\}/', $raw_text, $matches)) {
            $parsed_data = json_decode($matches[0], true);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE || !$parsed_data) {
            $raw_text = str_replace(['```json', '```'], '', $raw_text);
            $parsed_data = json_decode($raw_text, true);
        }

        if (!$parsed_data) {
             file_put_contents(__DIR__ . '/error_log.txt', date('Y-m-d H:i:s') . " [ParseDraft] JSON Decode Failed: " . json_last_error_msg() . "\n", FILE_APPEND);
        }
    }

    // Merge extracted data with sections
    if (!is_array($parsed_data)) $parsed_data = [];
    foreach ($manual_extracted as $k => $v) {
        if (!empty($v) && empty($parsed_data[$k])) {
            $parsed_data[$k] = $v;
        }
    }
    
    // Merge Manual Vitals
    if (empty($parsed_data['vitals'])) $parsed_data['vitals'] = [];
    foreach ($manual_vitals as $k => $v) {
        if (empty($parsed_data['vitals'][$k])) {
            $parsed_data['vitals'][$k] = $v;
        }
    }

    // Merge Manual Lists
    foreach (['medications', 'diagnoses'] as $key) {
        if (empty($parsed_data[$key]) && !empty($manual_lists[$key])) {
            $parsed_data[$key] = $manual_lists[$key];
        }
    }

    // --- 1.7 Additional Manual Extraction: Wounds & Procedure ---
    $manual_wounds = [];
    $manual_procedure = null;

    $extract_section_text = function($dom, $headers) {
        $xpath = new DOMXPath($dom);
        foreach ($headers as $header) {
            $query = "//h2[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$header}')] | //h3[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$header}')] | //h4[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$header}')] | //strong[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$header}')] | //b[contains(translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '{$header}')]";
            $nodes = $xpath->query($query);
            if ($nodes->length > 0) {
                $node = $nodes->item(0);
                $content = '';
                $sibling = $node->nextSibling;
                $scanned = 0;
                while ($sibling && $scanned < 30) {
                    if ($sibling->nodeType === XML_ELEMENT_NODE) {
                        $tag = strtolower($sibling->nodeName);
                        if (in_array($tag, ['h2', 'h3', 'h4'])) break;
                        $content .= " " . $sibling->textContent;
                    }
                    $sibling = $sibling->nextSibling;
                    $scanned++;
                }
                $content = trim(preg_replace('/\s+/', ' ', $content));
                if (!empty($content)) return $content;
            }
        }
        return '';
    };

    $wound_text = $extract_section_text($dom, ['wound assessment', 'wound assessments', 'wounds']);
    if (!empty($wound_text)) {
        // Split into possible wound blocks
        $chunks = preg_split('/\bWound\s*#?\d+\b/i', $wound_text);
        if (!$chunks || count($chunks) === 0) $chunks = [$wound_text];

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (strlen($chunk) < 5) continue;

            $wound = [
                'location' => '',
                'type' => '',
                'length_cm' => '',
                'width_cm' => '',
                'depth_cm' => '',
                'pain_level' => '',
                'drainage_type' => '',
                'exudate_amount' => '',
                'odor_present' => ''
            ];

            // Location
            if (preg_match('/wound\s*(?:on|to|at|of)?\s*([a-zA-Z0-9\s\-\,\/]+?)(?:\s|,|\.|measures|measure|with|$)/i', $chunk, $m)) {
                $wound['location'] = trim($m[1]);
            }

            // Type
            if (preg_match('/(?:type|wound type|ulcer)[:\s]+([a-zA-Z\s\-\/]+)/i', $chunk, $m)) {
                $wound['type'] = trim($m[1]);
            }

            // Dimensions (L x W x D)
            if (preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(?:[x×]\s*(\d+(?:\.\d+)?))?\s*(cm|mm|in|inch|inches)?/i', $chunk, $m)) {
                $length = (float)$m[1];
                $width = (float)$m[2];
                $depth = isset($m[3]) && $m[3] !== '' ? (float)$m[3] : null;
                $unit = isset($m[4]) ? strtolower($m[4]) : 'cm';

                if (in_array($unit, ['in', 'inch', 'inches'])) {
                    $length *= 2.54;
                    $width *= 2.54;
                    if ($depth !== null) $depth *= 2.54;
                } elseif ($unit === 'mm') {
                    $length /= 10;
                    $width /= 10;
                    if ($depth !== null) $depth /= 10;
                }

                $wound['length_cm'] = round($length, 2);
                $wound['width_cm'] = round($width, 2);
                if ($depth !== null) $wound['depth_cm'] = round($depth, 2);
            }

            // Drainage / Exudate
            if (preg_match('/(?:drainage|exudate)[:\s]+([a-zA-Z\s\-]+)/i', $chunk, $m)) {
                $wound['drainage_type'] = trim($m[1]);
            }
            if (preg_match('/exudate\s*amount[:\s]+([a-zA-Z\s\-]+)/i', $chunk, $m)) {
                $wound['exudate_amount'] = trim($m[1]);
            }

            // Pain
            if (preg_match('/pain[:\s]+(\d{1,2})/i', $chunk, $m)) {
                $wound['pain_level'] = $m[1];
            }

            // Odor
            if (preg_match('/odor[:\s]+(present|absent|yes|no)/i', $chunk, $m)) {
                $odor = strtolower($m[1]);
                $wound['odor_present'] = in_array($odor, ['present', 'yes']) ? 'Yes' : 'No';
            }

            $manual_wounds[] = $wound;
        }
    }

    $procedure_text = $extract_section_text($dom, ['procedure note', 'procedure', 'debridement']);
    if (!empty($procedure_text)) {
        $manual_procedure = ['narrative' => trim($procedure_text)];
    }

    // Fallback: Extract wounds/procedure from plain text if no section headers found
    if (empty($manual_wounds)) {
        $blocks = preg_split('/\n\s*\n/', $text_content);
        foreach ($blocks as $block) {
            $block = trim($block);
            if (strlen($block) < 5) continue;

            // Only consider blocks that look like a wound entry
            if (!preg_match('/\b(wound|ulcer|location|drainage|exudate|pain)\b/i', $block)) continue;

            $wound = [
                'location' => '',
                'type' => '',
                'length_cm' => '',
                'width_cm' => '',
                'depth_cm' => '',
                'pain_level' => '',
                'drainage_type' => '',
                'exudate_amount' => '',
                'odor_present' => ''
            ];

            // Regex to stop at next potential label or end of block
            // Labels: Type, Location, L (cm), W (cm), D (cm), Drainage, Exudate, Pain, Odor
            $stop_pattern = '(?=\s*(?:Type|Location|L\s*\(cm\)|W\s*\(cm\)|D\s*\(cm\)|Drainage|Exudate|Pain|Odor|Indication|\s*Onset|\s*Dimensions|\s*Tunneling|\s*Undermining|\s*Tissue|\s*POST\-DEBRIDEMENT)|\s*$|$)';

            if (empty($wound['location']) && preg_match('/\b(?:WOUND|Location|Site)[:\s]+([^:\n\r]*?)(?:' . $stop_pattern . ')/is', $block, $m)) {
                $loc_cand = trim($m[1], " \t\n\r\0\x0B,-");
                // Remove unwanted text if it includes "of care" or "of service"
                if (stripos($loc_cand, 'of care') === false && stripos($loc_cand, 'of service') === false && strlen($loc_cand) < 100) {
                     $wound['location'] = $loc_cand;
                }
            }

            // Fix for "Location: of the ulcer..." issue
            if (!empty($wound['location']) && stripos($wound['location'], 'of the ulcer') !== false) {
                 $wound['location'] = ''; // Reset if garbage
            }
            
            // Try to extract from WOUND: LEFT LOWER LEG (ULCER) format
             if (empty($wound['location']) && preg_match('/WOUND:\s*([^\(\)\n\r]+?)\s*\((\w+)\)/i', $block, $m)) {
                $wound['location'] = trim($m[1]);
                $wound['type'] = trim($m[2]);
            }
            
            if (empty($wound['type']) && preg_match('/\bType[:\s]+(.*?)(?:' . $stop_pattern . ')/is', $block, $m)) {
                $wound['type'] = trim($m[1], " \t\n\r\0\x0B,-");
            }

            // Dimensions labels
            if (preg_match('/\bL\s*\(cm\)[:\s]+(\d+(?:\.\d+)?)/i', $block, $m)) {
                $wound['length_cm'] = $m[1];
            }
            if (preg_match('/\bW\s*\(cm\)[:\s]+(\d+(?:\.\d+)?)/i', $block, $m)) {
                $wound['width_cm'] = $m[1];
            }
            if (preg_match('/\bD\s*\(cm\)[:\s]+(\d+(?:\.\d+)?)/i', $block, $m)) {
                $wound['depth_cm'] = $m[1];
            }

            // Fallback dimensions pattern
            if (empty($wound['length_cm']) || empty($wound['width_cm'])) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)\s*(?:[x×]\s*(\d+(?:\.\d+)?))?\s*(cm|mm|in|inch|inches)?/i', $block, $m, PREG_OFFSET_CAPTURE)) {
                    $length = (float)$m[1][0];
                    $width = (float)$m[2][0];
                    $depth = isset($m[3][0]) && $m[3][0] !== '' ? (float)$m[3][0] : null;
                    $unit = isset($m[4][0]) ? strtolower($m[4][0]) : 'cm'; // $m[4] might be missing
                    
                    // Conversion logic
                    if (in_array($unit, ['in', 'inch', 'inches'])) {
                        $length *= 2.54;
                        $width *= 2.54;
                        if ($depth !== null) $depth *= 2.54;
                    } elseif ($unit === 'mm') {
                        $length /= 10;
                        $width /= 10;
                        if ($depth !== null) $depth /= 10;
                    }

                    $wound['length_cm'] = round($length, 2);
                    $wound['width_cm'] = round($width, 2);
                    if ($depth !== null) $wound['depth_cm'] = round($depth, 2);

                    // SEARCH FOR LOCATION / TYPE near dimensions if not found yet
                    if (empty($wound['location'])) {
                        $dim_offset = $m[0][1];
                        // Look backwards 150 chars for Location strings
                        $pre_text = substr($block, max(0, $dim_offset - 150), min(150, $dim_offset));
                        
                        // Pattern: "Left lower leg ulcer"
                        // Capture: (Left ... leg) (ulcer)
                        if (preg_match('/((?:left|right|l|r|bilateral)[\w\s]{1,30}?(?:leg|foot|toe|ankle|heel|calf|thigh|knee|hip|buttock|sacrum|trochanter|ischium|arm|hand|finger|head|face|neck|abdomen|chest|back))[\s\W]{1,10}(ulcer|wound|lesion|abrasion|laceration|incision)/i', $pre_text, $loc_m)) {
                            $wound['location'] = trim($loc_m[1]);
                            if (empty($wound['type'])) $wound['type'] = ucfirst(trim($loc_m[2]));
                        } 
                        // Pattern: "Ulcer on left leg"
                        elseif (preg_match('/(ulcer|wound|lesion|abrasion|laceration|incision)[\s\w]{1,10}(?:on|to|of)\s+((?:the\s+)?(?:left|right|l|r|bilateral)[\w\s]{1,30}?)/i', $pre_text, $loc_m)) {
                             if (empty($wound['type'])) $wound['type'] = ucfirst(trim($loc_m[1]));
                             $wound['location'] = trim($loc_m[2]);
                        }
                    }
                }
            }
            
            // Try to extract location if still empty (maybe dimensions weren't found or were far away)
            if (empty($wound['location'])) {
                 if (preg_match('/((?:left|right|l|r|bilateral)[\w\s]{1,30}?(?:leg|foot|toe|ankle|heel|calf|thigh|knee|hip|buttock|sacrum|trochanter|ischium|arm|hand|finger|head|face|neck|abdomen|chest|back))[\s\W]{1,10}(ulcer|wound|lesion|abrasion|laceration|incision)/i', $block, $loc_m)) {
                    $wound['location'] = trim($loc_m[1]);
                    if (empty($wound['type'])) $wound['type'] = ucfirst(trim($loc_m[2]));
                }
            }

            // NARRATIVE FIELD EXTRACTION (If labels failed)
            // Drainage / Exudate (e.g. "moderate drainage", "scant serous exudate")
            if (empty($wound['drainage_type']) && empty($wound['exudate_amount'])) {
                if (preg_match('/(scant|small|moderate|large|heavy|copious|purulent|serous|serosanguineous|bloody)\s+(drainage|exudate)/i', $block, $m)) {
                     $wound['exudate_amount'] = ucfirst($m[1]);
                     $wound['drainage_type'] = ucfirst($m[2] === 'drainage' ? 'Serous' : $m[2]); // Default to serous if just "drainage" unless specific
                     if (stripos($block, 'purulent') !== false) $wound['drainage_type'] = 'Purulent';
                     if (stripos($block, 'serous') !== false) $wound['drainage_type'] = 'Serous';
                     if (stripos($block, 'sanguineous') !== false) $wound['drainage_type'] = 'Sanguineous';
                }
            }

            // Pain (e.g. "pain 4/10" or "pain four out of 10")
            if (empty($wound['pain_level'])) {
                 if (preg_match('/pain\s*(?:is|was)?\s*(?:\:\s*)?([0-9]|10|one|two|three|four|five|six|seven|eight|nine|ten)(?:\s*\/\s*10|\s*out of\s*10)/i', $block, $m)) {
                    $val = strtolower($m[1]);
                    $nums = ['one'=>1, 'two'=>2, 'three'=>3, 'four'=>4, 'five'=>5, 'six'=>6, 'seven'=>7, 'eight'=>8, 'nine'=>9, 'ten'=>10];
                    $wound['pain_level'] = isset($nums[$val]) ? $nums[$val] : (int)$val;
                 }
            }

            // Odor 
            if (empty($wound['odor_present'])) {
                if (preg_match('/(no|mal|foul|strong)\s*odor/i', $block, $m)) {
                    $wound['odor_present'] = strtolower($m[1]) === 'no' ? 'No' : 'Yes';
                }
            }

            if (preg_match('/\bDrainage[:\s]+(.*?)(?:' . $stop_pattern . ')/is', $block, $m)) {
                $wound['drainage_type'] = trim($m[1], " \t\n\r\0\x0B,-");
            }
            if (preg_match('/\bExudate\s*Amount[:\s]+(.*?)(?:' . $stop_pattern . ')/is', $block, $m)) {
                $wound['exudate_amount'] = trim($m[1], " \t\n\r\0\x0B,-");
            }
            if (preg_match('/\bPain\s*\(0\-10\)[:\s]+(\d{1,2})/i', $block, $m)) {
                $wound['pain_level'] = $m[1];
            }
            if (preg_match('/\bOdor[:\s]+(present|absent|yes|no)/i', $block, $m)) {
                $odor = strtolower($m[1]);
                $wound['odor_present'] = in_array($odor, ['present', 'yes']) ? 'Yes' : 'No';
            }

            // Only keep wound if it has location or dimensions
            $has_location = !empty($wound['location']);
            $has_dimensions = !empty($wound['length_cm']) || !empty($wound['width_cm']) || !empty($wound['depth_cm']);
            if ($has_location || $has_dimensions) {
                $manual_wounds[] = $wound;
            }
        }
    }

    if (empty($manual_procedure)) {
        foreach (preg_split('/\r\n|\r|\n/', $text_content) as $line) {
            $line = trim($line);
            if (preg_match('/\b(procedure note|procedure|debridement|graft)\b/i', $line)) {
                $manual_procedure = ['narrative' => $line];
                break;
            }
        }
    }

    // Merge Manual Wounds / Procedure
    // SMART EXTRACTION STRATEGY:
    // If AI found wounds, USE THEM.
    // If AI found NOTHING (empty), and manual found something, use manual.
    // DO NOT MERGE if AI has data, because manual regex might produce duplicates/artifacts.
    
    if (empty($parsed_data['wounds']) && !empty($manual_wounds)) {
        $parsed_data['wounds'] = $manual_wounds;
    }
    // If AI has wounds, we check if they are "valid". If they are just empty objects, maybe we should fallback?
    // But assuming the Prompt is robust, we trust AI.
    
    // HOWEVER, if Manual has MORE detailed dimensions than AI, we might want to enrich AI data.
    // For now, let's stick to "AI or Manual" to avoid duplication.
    
    if (empty($parsed_data['procedure']) && !empty($manual_procedure)) {
        $parsed_data['procedure'] = $manual_procedure;
    }

    $response_data = [
        'soap' => $sections,
        'extracted' => $parsed_data
    ];

    echo json_encode(["success" => true, "data" => $response_data]);
    exit;
}

// --- FINALIZE VISIT ACTION ---
if ($action === 'finalize_visit') {
    $note_html = isset($data->note_html) ? $data->note_html : '';
    
    if (empty($note_html)) {
        echo json_encode(["success" => false, "message" => "No note content provided."]);
        exit;
    }

    // Parse HTML to extract SOAP sections
    // We assume the AI generates <h2>Header</h2>... content based on the new template
    $sections = ['subjective' => '', 'objective' => '', 'assessment' => '', 'plan' => ''];
    $current_section = null;
    
    $dom = new DOMDocument();
    // Suppress warnings for malformed HTML
    libxml_use_internal_errors(true);
    // Hack to handle utf-8 correctly without deprecated mb_convert_encoding('HTML-ENTITIES')
    // We prepend an XML encoding declaration.
    $dom->loadHTML('<?xml encoding="UTF-8">' . $note_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    foreach ($dom->childNodes as $node) {
        // Skip the XML declaration node if it appears
        if ($node->nodeName === 'xml') continue;

        // Check for H2 (Main Sections) or H3 (Chief Complaint which goes to Subjective usually, but let's be flexible)
        if ($node->nodeName === 'h2') {
            $header = strtolower(trim($node->textContent));
            if (strpos($header, 'subjective') !== false) $current_section = 'subjective';
            elseif (strpos($header, 'objective') !== false) $current_section = 'objective';
            elseif (strpos($header, 'assessment') !== false) $current_section = 'assessment';
            elseif (strpos($header, 'plan') !== false) $current_section = 'plan';
            else $current_section = null; 
        } 
        // Special case: Chief Complaint is often H3 but belongs in Subjective if no H2 Subjective seen yet?
        // Actually, in our template, Chief Complaint comes BEFORE H2 Subjective. 
        // Let's assign anything before the first H2 to 'subjective' (or a 'preamble' that we prepend to subjective).
        
        elseif ($current_section && isset($sections[$current_section])) {
            $sections[$current_section] .= $dom->saveHTML($node);
        } else {
            // Content before any main header (like Chief Complaint or Vitals if they appear before H2 Subjective)
            // We'll append this to Subjective for now, or Objective if it's Vitals?
            // In the template: Chief Complaint (H3) -> Vitals (Div) -> H2 Subjective.
            // So CC and Vitals are "pre-section".
            // Let's put them in Subjective by default if no section is set.
            $sections['subjective'] .= $dom->saveHTML($node);
        }
    }
    
    // Clean up
    foreach ($sections as $k => $v) {
        $sections[$k] = trim($v);
    }

    // Fallback: If parsing failed (e.g. no H3 tags), put everything in Subjective
    if (empty($sections['subjective']) && empty($sections['objective']) && empty($sections['assessment']) && empty($sections['plan'])) {
        $sections['subjective'] = $note_html;
    }

    // Save to visit_notes
    $sql_note = "INSERT INTO visit_notes (appointment_id, patient_id, subjective, objective, assessment, plan, note_date)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            subjective = VALUES(subjective),
            objective = VALUES(objective),
            assessment = VALUES(assessment),
            plan = VALUES(plan)";
    
    $stmt = $conn->prepare($sql_note);
    $stmt->bind_param("iissss", $appointment_id, $patient_id, $sections['subjective'], $sections['objective'], $sections['assessment'], $sections['plan']);
    
    if ($stmt->execute()) {
        // --- SMART FETCH: Extract Structured Data ---
        $extraction_prompt = "You are a medical data extraction assistant. Extract structured data from the following clinical note into a valid JSON object.
        
        Return JSON with these keys:
        - 'chief_complaint': Extract the chief complaint text.
        - 'hpi': Extract the History of Present Illness narrative.
        - 'ros': Extract the Review of Systems narrative.
        - 'diagnoses': Array of objects { 'icd10_code': '...', 'description': '...' }. Map to ICD-10 codes where possible.
        - 'medications': Array of objects { 'drug_name': '...', 'dosage': '...', 'frequency': '...', 'route': '...', 'status': 'Active' }.
        - 'wounds': Array of objects { 'location': '...', 'type': '...', 'length_cm': 0.0, 'width_cm': 0.0, 'depth_cm': 0.0, 'pain_level': 0, 'drainage_type': '...', 'exudate_amount': '...', 'odor_present': 'Yes/No' }.
        - 'vitals': Object { 'blood_pressure': '...', 'heart_rate': 0, 'respiratory_rate': 0, 'oxygen_saturation': 0, 'temperature_f': 0.0, 'weight_lbs': 0.0, 'height_in': 0.0 }.
        - 'referrals': Array of objects { 'specialty': '...', 'reason': '...', 'priority': 'Routine/Urgent' }.

        If a field is not found, omit it or set to null.
        
        Clinical Note:
        " . strip_tags(str_replace(['</div>', '</p>', '</h2>', '</h1>', '<br>', '<br/>'], "\n", $note_html));

        $payload = [
            "contents" => [["parts" => [["text" => $extraction_prompt]]]],
            "generationConfig" => ["responseMimeType" => "application/json"]
        ];
        
        $result = generateContent($payload);
        $parsed = null;
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $raw_text = $result['candidates'][0]['content']['parts'][0]['text'];
            
            // Robust JSON Extraction
            if (preg_match('/\{[\s\S]*\}/', $raw_text, $matches)) {
                $parsed = json_decode($matches[0], true);
            }
            
            if (json_last_error() !== JSON_ERROR_NONE || !$parsed) {
                $raw_text = str_replace(['```json', '```'], '', $raw_text);
                $parsed = json_decode($raw_text, true);
            }
        }

        if ($parsed) {
                // 1.5 Save Diagnoses
                if (isset($parsed['diagnoses']) && is_array($parsed['diagnoses'])) {
                    foreach ($parsed['diagnoses'] as $diag) {
                        $icd = $diag['icd10_code'] ?? '';
                        $desc = $diag['description'] ?? '';
                        if (empty($icd)) continue;
                        $sql_check_diag = "SELECT visit_diagnosis_id FROM visit_diagnoses WHERE appointment_id = ? AND icd10_code = ?";
                        $stmt_cd = $conn->prepare($sql_check_diag);
                        $stmt_cd->bind_param("is", $appointment_id, $icd);
                        $stmt_cd->execute();
                        if (!$stmt_cd->get_result()->fetch_assoc()) {
                            $sql_ins_diag = "INSERT INTO visit_diagnoses (appointment_id, patient_id, icd10_code, description, created_at) VALUES (?, ?, ?, ?, NOW())";
                            $stmt_id = $conn->prepare($sql_ins_diag);
                            $stmt_id->bind_param("iiss", $appointment_id, $patient_id, $icd, $desc);
                            $stmt_id->execute();
                            $stmt_id->close();
                        }
                        $stmt_cd->close();
                    }
                }

                // 1.6 Save Medications
                if (isset($parsed['medications']) && is_array($parsed['medications'])) {
                    foreach ($parsed['medications'] as $med) {
                        $name = $med['drug_name'] ?? '';
                        $dose = $med['dosage'] ?? '';
                        $freq = $med['frequency'] ?? '';
                        $route = $med['route'] ?? '';
                        $status = $med['status'] ?? 'Active';
                        if (empty($name)) continue;
                        $sql_check_med = "SELECT medication_id FROM patient_medications WHERE patient_id = ? AND drug_name = ? AND status = 'Active'";
                        $stmt_cm = $conn->prepare($sql_check_med);
                        $stmt_cm->bind_param("is", $patient_id, $name);
                        $stmt_cm->execute();
                        if (!$stmt_cm->get_result()->fetch_assoc()) {
                            $sql_ins_med = "INSERT INTO patient_medications (patient_id, drug_name, dosage, frequency, route, status, start_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())";
                            $stmt_im = $conn->prepare($sql_ins_med);
                            $stmt_im->bind_param("isssss", $patient_id, $name, $dose, $freq, $route, $status);
                            $stmt_im->execute();
                            $stmt_im->close();
                        }
                        $stmt_cm->close();
                    }
                }

                // 2. Update Vitals
                if (isset($parsed['vitals']) && is_array($parsed['vitals'])) {
                    $v = $parsed['vitals'];
                    $bp = $v['blood_pressure'] ?? null;
                    $hr = $v['heart_rate'] ?? null;
                    $rr = $v['respiratory_rate'] ?? null;
                    $o2 = $v['oxygen_saturation'] ?? null;
                    $weight_kg = $v['weight_kg'] ?? ($v['weight_lbs'] ? $v['weight_lbs'] * 0.453592 : null);
                    $height_cm = $v['height_cm'] ?? ($v['height_in'] ? $v['height_in'] * 2.54 : null);
                    $temp_c = $v['temperature_c'] ?? ($v['temperature_f'] ? ($v['temperature_f'] - 32) * 5/9 : null);
                    $bmi = ($weight_kg && $height_cm) ? $weight_kg / (($height_cm/100) * ($height_cm/100)) : null;

                    $sql_vitals = "INSERT INTO patient_vitals (patient_id, appointment_id, visit_date, blood_pressure, heart_rate, respiratory_rate, oxygen_saturation, weight_kg, height_cm, temperature_celsius, bmi) VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE visit_date = NOW(), blood_pressure = COALESCE(VALUES(blood_pressure), blood_pressure), heart_rate = COALESCE(VALUES(heart_rate), heart_rate), respiratory_rate = COALESCE(VALUES(respiratory_rate), respiratory_rate), oxygen_saturation = COALESCE(VALUES(oxygen_saturation), oxygen_saturation), weight_kg = COALESCE(VALUES(weight_kg), weight_kg), height_cm = COALESCE(VALUES(height_cm), height_cm), temperature_celsius = COALESCE(VALUES(temperature_celsius), temperature_celsius), bmi = COALESCE(VALUES(bmi), bmi)";
                    $stmt_v = $conn->prepare($sql_vitals);
                    $stmt_v->bind_param("iisiiidddd", $patient_id, $appointment_id, $bp, $hr, $rr, $o2, $weight_kg, $height_cm, $temp_c, $bmi);
                    $stmt_v->execute();
                    $stmt_v->close();
                }

                // 3. Update Wounds
                if (isset($parsed['wounds']) && is_array($parsed['wounds'])) {
                    foreach ($parsed['wounds'] as $w) {
                        $loc = $w['location'] ?? null;
                        $type = $w['type'] ?? 'Other';
                        if (!$loc) continue;
                        
                        $wound_id = 0;
                        $sql_find = "SELECT wound_id FROM wounds WHERE patient_id = ? AND location = ? LIMIT 1";
                        $stmt_find = $conn->prepare($sql_find);
                        $stmt_find->bind_param("is", $patient_id, $loc);
                        $stmt_find->execute();
                        $res_find = $stmt_find->get_result();
                        if ($row = $res_find->fetch_assoc()) {
                            $wound_id = $row['wound_id'];
                        } else {
                            $sql_create = "INSERT INTO wounds (patient_id, location, wound_type, date_onset, status) VALUES (?, ?, ?, CURDATE(), 'Active')";
                            $stmt_create = $conn->prepare($sql_create);
                            $stmt_create->bind_param("iss", $patient_id, $loc, $type);
                            if ($stmt_create->execute()) $wound_id = $stmt_create->insert_id;
                            $stmt_create->close();
                        }
                        $stmt_find->close();

                        if ($wound_id > 0) {
                            $len = $w['length_cm'] ?? null;
                            $wid = $w['width_cm'] ?? null;
                            $dep = $w['depth_cm'] ?? null;
                            $pain = $w['pain_level'] ?? null;
                            $drainage = $w['drainage_type'] ?? null;
                            $exudate = $w['exudate_amount'] ?? null;
                            $odor = $w['odor_present'] ?? 'No';

                            $sql_assess_check = "SELECT assessment_id FROM wound_assessments WHERE wound_id = ? AND appointment_id = ?";
                            $stmt_ac = $conn->prepare($sql_assess_check);
                            $stmt_ac->bind_param("ii", $wound_id, $appointment_id);
                            $stmt_ac->execute();
                            if ($stmt_ac->get_result()->fetch_assoc()) {
                                // Update existing assessment
                                $sql_upd = "UPDATE wound_assessments SET length_cm = COALESCE(?, length_cm), width_cm = COALESCE(?, width_cm), depth_cm = COALESCE(?, depth_cm), pain_level = COALESCE(?, pain_level), drainage_type = COALESCE(?, drainage_type), exudate_amount = COALESCE(?, exudate_amount), odor_present = COALESCE(?, odor_present), assessment_date = NOW() WHERE wound_id = ? AND appointment_id = ?";
                                $stmt_u = $conn->prepare($sql_upd);
                                $stmt_u->bind_param("dddisssii", $len, $wid, $dep, $pain, $drainage, $exudate, $odor, $wound_id, $appointment_id);
                                $stmt_u->execute();
                                $stmt_u->close();
                            } else {
                                // Insert new assessment
                                $sql_ins = "INSERT INTO wound_assessments (wound_id, patient_id, appointment_id, assessment_date, length_cm, width_cm, depth_cm, pain_level, drainage_type, exudate_amount, odor_present) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)";
                                $stmt_i = $conn->prepare($sql_ins);
                                $stmt_i->bind_param("iiidddisss", $wound_id, $patient_id, $appointment_id, $len, $wid, $dep, $pain, $drainage, $exudate, $odor);
                                $stmt_i->execute();
                                $stmt_i->close();
                            }
                            $stmt_ac->close();
                        }
                    }
                }

                // 4. Save Referrals
                if (isset($parsed['referrals']) && is_array($parsed['referrals'])) {
                    foreach ($parsed['referrals'] as $ref) {
                        $specialty = $ref['specialty'] ?? '';
                        $reason = $ref['reason'] ?? '';
                        $priority = $ref['priority'] ?? 'Routine';
                        
                        if (empty($specialty)) continue;
                        
                        // Map priority to enum
                        if (stripos($priority, 'urgent') !== false) $priority = 'Urgent';
                        elseif (stripos($priority, 'stat') !== false) $priority = 'Stat';
                        else $priority = 'Routine';

                        $order_name = "Referral to " . $specialty;
                        if (!empty($reason)) $order_name .= " for " . $reason;

                        // Check for duplicate order today
                        $sql_check_ord = "SELECT order_id FROM patient_orders WHERE patient_id = ? AND order_type = 'Consult' AND order_name = ? AND DATE(created_at) = CURDATE()";
                        $stmt_co = $conn->prepare($sql_check_ord);
                        $stmt_co->bind_param("is", $patient_id, $order_name);
                        $stmt_co->execute();
                        if (!$stmt_co->get_result()->fetch_assoc()) {
                            $sql_ins_ord = "INSERT INTO patient_orders (patient_id, appointment_id, user_id, order_type, order_name, priority, status, created_at) VALUES (?, ?, ?, 'Consult', ?, ?, 'Ordered', NOW())";
                            $stmt_io = $conn->prepare($sql_ins_ord);
                            $stmt_io->bind_param("iiiss", $patient_id, $appointment_id, $user_id, $order_name, $priority);
                            $stmt_io->execute();
                            $stmt_io->close();
                        }
                        $stmt_co->close();
                    }
                }
            }

        echo json_encode([
            "success" => true, 
            "message" => "Visit finalized, note saved, and structured data updated.",
            "data" => [
                "soap" => $sections,
                "extracted" => $parsed
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

// --- PROCESS NARRATIVE ACTION ---
if ($action === 'process_narrative') {
    if (!isset($data->narrative) || trim($data->narrative) === '') {
        echo json_encode(["success" => false, "message" => "Narrative text is required."]);
        exit;
    }

    $narrative = is_string($data->narrative) ? $data->narrative : json_encode($data->narrative);
    $image_data = isset($data->image_data) ? $data->image_data : null;
    $mime_type = isset($data->mime_type) ? $data->mime_type : null;
    // $data->context can be a rich object (from visit_narrative.php) or a plain string.
    // It is processed into $history_context below. Here we only need a short label string.
    $context = (isset($data->context) && is_string($data->context)) ? $data->context : 'Wound Care Visit';
    
    // Standard Lists for AI Normalization
    $wound_locations_list = implode(", ", [
        "Head/Scalp", "Face", "Neck", "Chest (Left)", "Chest (Right)", "Arm (Left Upper)", "Arm (Right Upper)",
        "Abdomen (Left Upper Quadrant)", "Abdomen (Left Lower Quadrant)", "Groin (Left)", "Groin (Right)",
        "Thigh (Left Anterior)", "Thigh (Right Anterior)", "Knee (Left)", "Knee (Right)", "Shin (Left)", "Shin (Right)",
        "Ankle (Left)", "Ankle (Right)", "Foot (Left Dorsum)", "Foot (Right Dorsum)",
        "Head/Scalp (Posterior)", "Neck (Posterior)", "Shoulder (Left)", "Shoulder (Right)",
        "Back (Upper)", "Elbow (Left)", "Elbow (Right)", "Back (Mid)", "Back (Lower)",
        "Coccyx/Sacrum", "Buttock (Left)", "Buttock (Right)", "Thigh (Left Posterior)", "Thigh (Right Posterior)",
        "Knee (Left Posterior)", "Knee (Right Posterior)", "Calf (Left)", "Calf (Right)",
        "Heel (Left)", "Heel (Right)", "Foot (Left Plantar)", "Foot (Right Plantar)"
    ]);

    $wound_types_list = implode(", ", [
        "Arterial Ulcer", "Burn", "Diabetic Foot Ulcer", "Fungating Wound", "Incontinence Associated Dermatitis (IAD)",
        "Kennedy Terminal Ulcer", "Malignant Wound", "Moisture Associated Skin Damage (MASD)", "Pressure Injury (HAPU)",
        "Pressure Injury (Community Acquired)", "Pressure Injury (Unstageable)", "Surgical Dehiscence", "Surgical Site Infection",
        "Skin Tear", "Traumatic Wound", "Venous Stasis Ulcer", "Other"
    ]);

    // --- Fetch Historical Context ---
    $history_context = "";

    // 0. Use Frontend Context if available (New Intelligence Layer)
    $frontend_context = isset($data->context) ? $data->context : null;
    if ($frontend_context && is_object($frontend_context)) {
        $age = isset($frontend_context->patient_age) ? $frontend_context->patient_age : '?';
        $gender = isset($frontend_context->patient_gender) ? $frontend_context->patient_gender : '?';
        $history_context .= "### PATIENT SNAPSHOT:\n";
        $history_context .= "- Demographics: $age year old $gender.\n";

        if (!empty($frontend_context->active_wounds)) {
            $history_context .= "- Active Wounds on File:\n";
            foreach ($frontend_context->active_wounds as $w) {
                $loc = is_object($w) ? $w->location : $w['location'];
                $wtype = is_object($w) ? $w->wound_type : $w['wound_type'];
                $onset = is_object($w) ? $w->date_onset : $w['date_onset'];
                $history_context .= "  * $loc ($wtype, Onset: $onset)\n";
            }
        }
        if (!empty($frontend_context->active_meds)) {
            $history_context .= "- Active Medications:\n";
            foreach ($frontend_context->active_meds as $m) {
                $dname = is_object($m) ? $m->drug_name : $m['drug_name'];
                $ddose = is_object($m) ? $m->dosage : $m['dosage'];
                $dfreq = is_object($m) ? $m->frequency : $m['frequency'];
                $history_context .= "  * $dname $ddose $dfreq\n";
            }
        }
        if (!empty($frontend_context->past_visits)) {
            $history_context .= "- Previous 5 Visits:\n";
            foreach ($frontend_context->past_visits as $pv) {
                $vdate = is_object($pv) ? $pv->visit_date : $pv['visit_date'];
                $vcc = is_object($pv) ? $pv->chief_complaint : $pv['chief_complaint'];
                $history_context .= "  * $vdate: $vcc\n";
            }
        }
        $history_context .= "\n";
    }
    
    // 1. Previous Visit Note
    $sql_prev_note = "SELECT note_date, subjective, assessment, plan FROM visit_notes 
                      WHERE patient_id = ? AND appointment_id != ? 
                      ORDER BY note_date DESC LIMIT 1";
    $stmt_prev = $conn->prepare($sql_prev_note);
    $stmt_prev->bind_param("ii", $patient_id, $appointment_id);
    $stmt_prev->execute();
    $res_prev = $stmt_prev->get_result();
    if ($prev_note = $res_prev->fetch_assoc()) {
        $history_context .= "PREVIOUS VISIT ({$prev_note['note_date']}):\n";
        $history_context .= "Subjective: " . substr($prev_note['subjective'], 0, 300) . "...\n";
        $history_context .= "Assessment: " . substr($prev_note['assessment'], 0, 300) . "...\n";
        $history_context .= "Plan: " . substr($prev_note['plan'], 0, 300) . "...\n\n";
    }
    $stmt_prev->close();

    // 2. Previous Wound Measurements
    $sql_prev_wounds = "SELECT w.location, wa.length_cm, wa.width_cm, wa.depth_cm, wa.assessment_date 
                        FROM wound_assessments wa
                        JOIN wounds w ON wa.wound_id = w.wound_id
                        WHERE wa.patient_id = ? AND wa.appointment_id != ?
                        AND wa.assessment_date = (
                            SELECT MAX(assessment_date) FROM wound_assessments wa2 
                            WHERE wa2.wound_id = wa.wound_id AND wa2.appointment_id != ?
                        )";
    $stmt_pw = $conn->prepare($sql_prev_wounds);
    $stmt_pw->bind_param("iii", $patient_id, $appointment_id, $appointment_id);
    $stmt_pw->execute();
    $res_pw = $stmt_pw->get_result();
    if ($res_pw->num_rows > 0) {
        $history_context .= "PREVIOUS WOUND MEASUREMENTS:\n";
        while ($pw = $res_pw->fetch_assoc()) {
            $history_context .= "- {$pw['location']}: {$pw['length_cm']}x{$pw['width_cm']}x{$pw['depth_cm']} cm ({$pw['assessment_date']})\n";
        }
        $history_context .= "\n";
    }
    $stmt_pw->close();

    // Construct Prompt for SOAP Extraction AND Vitals AND Wounds AND Procedures AND Diagnosis AND Medications AND HPI/ROS
    $system_instruction = "You are an expert medical scribe and clinical documentation specialist. Your task is to convert the following unstructured clinician dictation into a comprehensive, professional, and VERY DETAILED SOAP note. You must also extract vital signs, wound assessments, procedures, diagnoses, and medications.

    CRITICAL INSTRUCTION: The user EXPLICITLY REQUESTS a 'long and detailed narrative'.
    - EXPAND on every provided detail. Turn short phrases into full, complex sentences.
    - HPI SECTION: Start with 'Patient is a [Age]-year-old [Gender] with a history of [Conditions] presenting for...' and tell a complete chronological story of the wound/condition. 
    - ROS SECTION: Generate a FULL Review of Systems. If not explicitly dictated, assume and document 'denies' for all major systems (General, HEENT, CV, Resp, GI, GU, MSK, Neuro, Psych, Skin) based on the context of a stable outpatient visit. DO NOT leave this short.
    - ASSESSMENT SECTION: Write a thoughtful clinical synthesis. Compare current findings to previous visits (if provided). Discuss trajectory (improving vs stalled), compliance, variables affecting healing, and rationale for the plan.
    - PLAN SECTION: Be specific and verbose about wound care instructions, offloading, nutrition, and follow-up.

    You may be provided with an image of a wound. If an image is provided:
    - Analyze the image to estimate wound dimensions (length x width) if a ruler is visible or context allows.
    - Identify the tissue type (granulation, slough, eschar, epithelial).
    - Assess the wound edges and surrounding skin.
    - Use the visual information to validate or supplement the dictated notes.
    
    You are provided with HISTORICAL CONTEXT from the patient's previous visit. 
    - Use this context to generate COMPARATIVE STATEMENTS in the 'Assessment' section.
    - If the current dictation contradicts the history, note it.

    Return ONLY a valid JSON object with the following keys:
    - chief_complaint (string): A concise statement of the reason for the visit.
    - hpi (string): A VERY LONG, detailed narrative of the History of Present Illness.
    - ros (string): A comprehensive, multi-system Review of Systems narrative (e.g., 'Constitutional: Denies fever/chills. CV: Denies chest pain...').
    - subjective (string): Any other subjective information.
    - objective (string): A detailed narrative of objective findings.
    - assessment (string): A thorough, LONG, DETAILED narrative clinical synthesis.
    - plan (string): A detailed care plan.
    - vitals (object, optional):
        - blood_pressure (string)
        - heart_rate (int)
        - respiratory_rate (int)
        - temperature_f (float)
        - temperature_c (float)
        - oxygen_saturation (int)
        - height_in (float)
        - height_cm (float)
        - weight_lbs (float)
        - weight_kg (float)
        - pain_level (int)
    - wounds (array of objects, optional):
        - location (string, MUST be one of: $wound_locations_list)
        - type (string, MUST be one of: $wound_types_list)
        - assessment_type (string, optional): 'Pre-Debridement' or 'Post-Debridement'. If the user says 'Pre', 'Before', or 'Start', assume 'Pre-Debridement'. If 'Post', 'After', or 'Finish', assume 'Post-Debridement'. Default to 'Post-Debridement' if unclear.
        - length_cm (float) - Use Post-Debridement dimensions if available, otherwise use the single set provided.
        - width_cm (float)
        - depth_cm (float)
        - pre_debridement_measurements (string, optional) - If the user dictates \"Before\" or \"Pre-debridement\" dimensions, extract them here as a string (e.g., \"1.3x1.3x0.5 cm\").
        - pain_level (int)
        - drainage_type (string: Serous, Sanguineous, Serosanguineous, Purulent, None)
        - exudate_amount (string: None, Scant, Small, Moderate, Large)
        - odor_present (string: Yes, No)
    - procedure (object, optional, ONLY if a debridement or surgical procedure is mentioned):
        - type (string)
        - location (string)
        - dimensions (string)
        - depth (string)
        - instrument (string)
        - narrative (string)
    - diagnoses (array of objects, optional):
        - icd10_code (string, e.g., 'L97.411')
        - description (string)
    - medications (array of objects, optional):
        - drug_name (string)
        - dosage (string)
        - frequency (string)
        - route (string)
        - status (string: Active, Discontinued)
    - referrals (array of objects, optional):
        - specialty (string)
        - reason (string)
        - priority (string: Routine, Urgent, Stat)
    
    For Wounds:
    - Map the spoken location to the closest standard location from the provided list.
    - Map the spoken type to the closest standard type.
    
    For Diagnoses:
    - Infer the most appropriate ICD-10 code based on the description if not explicitly stated.
    
    Do not include any markdown formatting (like ```json). Just the raw JSON string.
    Ensure the medical terminology is correct and the tone is professional.";

    $user_prompt = "CONTEXT: This is a $context assessment.\n\nHISTORICAL CONTEXT:\n$history_context\n\nCURRENT DICTATION: \"$narrative\"";

    // Construct Parts
    $parts = [];
    $parts[] = ["text" => $user_prompt];

    if ($image_data && $mime_type) {
        $parts[] = [
            "inlineData" => [
                "mimeType" => $mime_type,
                "data" => $image_data
            ]
        ];
    }

    $payload = [
        "contents" => [
            [
                "role" => "user",
                "parts" => $parts
            ]
        ],
        "systemInstruction" => [
            "parts" => [
                ["text" => $system_instruction]
            ]
        ]
    ];

    $result = generateContent($payload);

    if (isset($result['error'])) {
        $errMsg = is_array($result['error']) ? json_encode($result['error']) : $result['error'];
        echo json_encode(["success" => false, "message" => "AI Error: " . $errMsg]);
        exit;
    }

    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $raw_text = trim($result['candidates'][0]['content']['parts'][0]['text']);
        
        // Robust JSON Extraction
        $parsed = null;
        if (preg_match('/\{[\s\S]*\}/', $raw_text, $matches)) {
            $parsed = json_decode($matches[0], true);
        }
        
        if (json_last_error() !== JSON_ERROR_NONE || !$parsed) {
            $clean_json = str_replace(['```json', '```'], '', $raw_text);
            $parsed = json_decode($clean_json, true);
        }

        if (json_last_error() === JSON_ERROR_NONE && $parsed) {
            // Return data for review instead of saving immediately
            echo json_encode([
                "success" => true, 
                "review_data" => $parsed
            ]);
        } else {
            echo json_encode(["success" => false, "message" => "AI returned invalid JSON.", "debug" => $raw_text]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "AI failed to generate content.", "debug" => $result]);
    }
    exit;
}

// --- CONFIRM & SAVE ACTION ---
if ($action === 'confirm_save') {
    if (!isset($data->review_data)) {
        echo json_encode(["success" => false, "message" => "No data to save."]);
        exit;
    }

    // Use the data passed from the frontend (which might have been edited)
    $parsed = json_decode(json_encode($data->review_data), true);

    // 1. Update Visit Notes
    $cc = $parsed['chief_complaint'] ?? '';
    
    // Combine HPI, ROS, and Subjective
    $hpi = $parsed['hpi'] ?? '';
    $ros = $parsed['ros'] ?? '';
    $subj_base = $parsed['subjective'] ?? '';
    
    $subj = "";
    if (!empty($hpi)) $subj .= "HPI:\n$hpi\n\n";
    if (!empty($ros)) $subj .= "Review of Systems:\n$ros\n\n";
    $subj .= $subj_base;
    $subj = trim($subj);

    $obj = $parsed['objective'] ?? '';
    $assess = $parsed['assessment'] ?? '';
    $plan = $parsed['plan'] ?? '';
    
    // Handle Procedure Note
    $proc_note = '';
    if (isset($parsed['procedure']) && is_array($parsed['procedure'])) {
        $proc_note = $parsed['procedure']['narrative'] ?? '';
        if (!empty($proc_note)) {
             // Append to assessment as requested
             $assess .= "\n\nProcedure Performed: " . $proc_note;
        }
    }

    $sql_note = "INSERT INTO visit_notes (appointment_id, patient_id, user_id, chief_complaint, subjective, objective, assessment, plan, procedure_note, note_date, status, is_signed, signed_at, finalized_at, finalized_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'finalized', 1, NOW(), NOW(), ?)
            ON DUPLICATE KEY UPDATE
            chief_complaint = VALUES(chief_complaint),
            subjective = VALUES(subjective),
            objective = VALUES(objective),
            assessment = VALUES(assessment),
            plan = VALUES(plan),
            procedure_note = VALUES(procedure_note),
            status = 'finalized',
            is_signed = 1,
            signed_at = NOW(),
            finalized_at = NOW(),
            finalized_by = VALUES(finalized_by)";
    
    $stmt = $conn->prepare($sql_note);
    // appointment_id(i), patient_id(i), user_id(i), cc(s), subj(s), obj(s), assess(s), plan(s), proc_note(s), user_id(i)
    $stmt->bind_param("iiissssssi", $appointment_id, $patient_id, $user_id, $cc, $subj, $obj, $assess, $plan, $proc_note, $user_id);
    $stmt->execute();
    $stmt->close();

    // 1.5 Save Diagnoses
    if (isset($parsed['diagnoses']) && is_array($parsed['diagnoses'])) {
        foreach ($parsed['diagnoses'] as $diag) {
            $icd = $diag['icd10_code'] ?? '';
            $desc = $diag['description'] ?? '';
            if (empty($icd)) continue;

            // Check if exists
            $sql_check_diag = "SELECT visit_diagnosis_id FROM visit_diagnoses WHERE appointment_id = ? AND icd10_code = ?";
            $stmt_cd = $conn->prepare($sql_check_diag);
            $stmt_cd->bind_param("is", $appointment_id, $icd);
            $stmt_cd->execute();
            $res_cd = $stmt_cd->get_result();
            
            if (!$res_cd->fetch_assoc()) {
                $sql_ins_diag = "INSERT INTO visit_diagnoses (appointment_id, patient_id, icd10_code, description, created_at) VALUES (?, ?, ?, ?, NOW())";
                $stmt_id = $conn->prepare($sql_ins_diag);
                $stmt_id->bind_param("iiss", $appointment_id, $patient_id, $icd, $desc);
                $stmt_id->execute();
                $stmt_id->close();
            }
            $stmt_cd->close();
        }
    }

    // 1.6 Save Medications
    if (isset($parsed['medications']) && is_array($parsed['medications'])) {
        foreach ($parsed['medications'] as $med) {
            $name = $med['drug_name'] ?? '';
            $dose = $med['dosage'] ?? '';
            $freq = $med['frequency'] ?? '';
            $route = $med['route'] ?? '';
            $status = $med['status'] ?? 'Active';
            
            if (empty($name)) continue;

            // Check if exists (simple check by name for this patient)
            $sql_check_med = "SELECT medication_id FROM patient_medications WHERE patient_id = ? AND drug_name = ? AND status = 'Active'";
            $stmt_cm = $conn->prepare($sql_check_med);
            $stmt_cm->bind_param("is", $patient_id, $name);
            $stmt_cm->execute();
            $res_cm = $stmt_cm->get_result();
            
            if (!$res_cm->fetch_assoc()) {
                $sql_ins_med = "INSERT INTO patient_medications (patient_id, drug_name, dosage, frequency, route, status, start_date) VALUES (?, ?, ?, ?, ?, ?, CURDATE())";
                $stmt_im = $conn->prepare($sql_ins_med);
                $stmt_im->bind_param("isssss", $patient_id, $name, $dose, $freq, $route, $status);
                $stmt_im->execute();
                $stmt_im->close();
            }
            $stmt_cm->close();
        }
    }

    // 2. Update Vitals (if present)
    if (isset($parsed['vitals']) && is_array($parsed['vitals'])) {
        $v = $parsed['vitals'];
        
        // Extract and Normalize
        $bp = $v['blood_pressure'] ?? null;
        $hr = $v['heart_rate'] ?? null;
        $rr = $v['respiratory_rate'] ?? null;
        $o2 = $v['oxygen_saturation'] ?? null;
        
        // Handle Units (Prefer Metric for DB, but calculate if missing)
        $weight_kg = $v['weight_kg'] ?? null;
        if (!$weight_kg && isset($v['weight_lbs'])) {
            $weight_kg = $v['weight_lbs'] * 0.453592;
        }

        $height_cm = $v['height_cm'] ?? null;
        if (!$height_cm && isset($v['height_in'])) {
            $height_cm = $v['height_in'] * 2.54;
        }

        $temp_c = $v['temperature_c'] ?? null;
        if (!$temp_c && isset($v['temperature_f'])) {
            $temp_c = ($v['temperature_f'] - 32) * 5/9;
        }

        // Calculate BMI if possible
        $bmi = null;
        if ($weight_kg && $height_cm) {
            $height_m = $height_cm / 100;
            $bmi = $weight_kg / ($height_m * $height_m);
        }

        // Insert/Update Vitals
        $sql_vitals = "INSERT INTO patient_vitals 
                        (patient_id, appointment_id, visit_date, blood_pressure, heart_rate, respiratory_rate, oxygen_saturation, weight_kg, height_cm, temperature_celsius, bmi)
                        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                        visit_date = NOW(),
                        blood_pressure = COALESCE(VALUES(blood_pressure), blood_pressure),
                        heart_rate = COALESCE(VALUES(heart_rate), heart_rate),
                        respiratory_rate = COALESCE(VALUES(respiratory_rate), respiratory_rate),
                        oxygen_saturation = COALESCE(VALUES(oxygen_saturation), oxygen_saturation),
                        weight_kg = COALESCE(VALUES(weight_kg), weight_kg),
                        height_cm = COALESCE(VALUES(height_cm), height_cm),
                        temperature_celsius = COALESCE(VALUES(temperature_celsius), temperature_celsius),
                        bmi = COALESCE(VALUES(bmi), bmi)";
        
        $stmt_v = $conn->prepare($sql_vitals);
        $stmt_v->bind_param("iisiiidddd", 
            $patient_id, 
            $appointment_id, 
            $bp, 
            $hr, 
            $rr, 
            $o2, 
            $weight_kg, 
            $height_cm, 
            $temp_c, 
            $bmi
        );
        $stmt_v->execute();
        $stmt_v->close();
    }

    // 3. Update Wounds (if present)
    $detected_wounds_map = []; // Map wound_id => assessment_id
    if (isset($parsed['wounds']) && is_array($parsed['wounds'])) {
        foreach ($parsed['wounds'] as $w) {
            $loc = $w['location'] ?? null;
            $type = $w['type'] ?? 'Other';
            
            if (!$loc) continue; // Skip if no location

            // A. Find or Create Wound
            $wound_id = 0;
            
            // Check if wound exists for this patient at this location
            $sql_find = "SELECT wound_id FROM wounds WHERE patient_id = ? AND location = ? LIMIT 1";
            $stmt_find = $conn->prepare($sql_find);
            $stmt_find->bind_param("is", $patient_id, $loc);
            $stmt_find->execute();
            $res_find = $stmt_find->get_result();
            
            if ($row = $res_find->fetch_assoc()) {
                $wound_id = $row['wound_id'];
            } else {
                // Create new wound
                $sql_create = "INSERT INTO wounds (patient_id, location, wound_type, date_onset, status) VALUES (?, ?, ?, CURDATE(), 'Active')";
                $stmt_create = $conn->prepare($sql_create);
                $stmt_create->bind_param("iss", $patient_id, $loc, $type);
                if ($stmt_create->execute()) {
                    $wound_id = $stmt_create->insert_id;
                }
                $stmt_create->close();
            }
            $stmt_find->close();

            // B. Insert Assessment
            if ($wound_id > 0) {
                $len = $w['length_cm'] ?? null;
                $wid = $w['width_cm'] ?? null;
                $dep = $w['depth_cm'] ?? null;
                $pain = $w['pain_level'] ?? null;
                $drainage = $w['drainage_type'] ?? null;
                $exudate = $w['exudate_amount'] ?? null;
                $odor = $w['odor_present'] ?? 'No';
                $pre_measurements = $w['pre_debridement_measurements'] ?? null;


                // Determine Assessment Type logic:
                // 1. If explicit assessment_type is provided in the JSON from AI (e.g. user said "Pre-Debridement"), use it.
                // 2. Fallback to global user_image_type if set.
                // 3. Default to Post-Debridement.
                
                $assess_type = 'Post-Debridement'; // Default
                
                // Allow AI to override if it extracted a type from the "update X assessment" command
                if (isset($w['assessment_type']) && !empty($w['assessment_type'])) {
                    // Normalize
                    if (stripos($w['assessment_type'], 'pre') !== false) $assess_type = 'Pre-Debridement';
                    else if (stripos($w['assessment_type'], 'post') !== false) $assess_type = 'Post-Debridement';
                } else {
                     // Check context provided by image
                    global $user_image_type;
                    if (isset($user_image_type) && ($user_image_type === 'Pre-Debridement' || $user_image_type === 'Post-Debridement')) {
                        $assess_type = $user_image_type;
                    }
                }

                $assessment_id = 0;

                // Check if assessment exists for this visit/wound AND type
                $sql_assess_check = "SELECT assessment_id FROM wound_assessments WHERE wound_id = ? AND appointment_id = ? AND assessment_type = ?";
                $stmt_ac = $conn->prepare($sql_assess_check);
                $stmt_ac->bind_param("iis", $wound_id, $appointment_id, $assess_type);
                $stmt_ac->execute();
                $res_ac = $stmt_ac->get_result();
                
                if ($row_ac = $res_ac->fetch_assoc()) {
                    // Update - COALESCE allows partial updates!
                    $assessment_id = $row_ac['assessment_id'];
                    $sql_upd_assess = "UPDATE wound_assessments SET 
                        length_cm = COALESCE(?, length_cm),
                        width_cm = COALESCE(?, width_cm),
                        depth_cm = COALESCE(?, depth_cm),
                        pain_level = COALESCE(?, pain_level),
                        drainage_type = COALESCE(?, drainage_type),
                        exudate_amount = COALESCE(?, exudate_amount),
                        odor_present = COALESCE(?, odor_present),
                        pre_debridement_notes = CASE WHEN ? IS NOT NULL THEN CONCAT(COALESCE(pre_debridement_notes, ''), CHAR(10), 'Pre-Debridement Dims: ', ?) ELSE pre_debridement_notes END,
                        assessment_date = NOW()
                        WHERE assessment_id = ?";
                    $stmt_ua = $conn->prepare($sql_upd_assess);
                    $stmt_ua->bind_param("dddisssssi", $len, $wid, $dep, $pain, $drainage, $exudate, $odor, $pre_measurements, $pre_measurements, $assessment_id);
                    $stmt_ua->execute();
                    $stmt_ua->close();
                } else {
                    // Only insert if we have some data (don't create empty assessments usually)
                    // ... but here we might want to create it if it's the first time mentioned
                    $sql_ins_assess = "INSERT INTO wound_assessments (wound_id, patient_id, appointment_id, assessment_date, length_cm, width_cm, depth_cm, pain_level, drainage_type, exudate_amount, odor_present, pre_debridement_notes, assessment_type)
                        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_ia = $conn->prepare($sql_ins_assess);
                    $pre_note_val = $pre_measurements ? "Pre-Debridement Dims: " . $pre_measurements : null;
                    $stmt_ia->bind_param("iiidddisssss", $wound_id, $patient_id, $appointment_id, $len, $wid, $dep, $pain, $drainage, $exudate, $odor, $pre_note_val, $assess_type);
                    if ($stmt_ia->execute()) {
                        $assessment_id = $stmt_ia->insert_id;
                    }
                    $stmt_ia->close();
                }
                $stmt_ac->close();
                
                if ($assessment_id > 0) {
                    $detected_wounds_map[$wound_id] = $assessment_id;
                }
            }
        }
    }

    // 3.5 Save Referrals
    if (isset($parsed['referrals']) && is_array($parsed['referrals'])) {
        foreach ($parsed['referrals'] as $ref) {
            $specialty = $ref['specialty'] ?? '';
            $reason = $ref['reason'] ?? '';
            $priority = $ref['priority'] ?? 'Routine';
            
            if (empty($specialty)) continue;
            
            // Map priority to enum
            if (stripos($priority, 'urgent') !== false) $priority = 'Urgent';
            elseif (stripos($priority, 'stat') !== false) $priority = 'Stat';
            else $priority = 'Routine';

            $order_name = "Referral to " . $specialty;
            if (!empty($reason)) $order_name .= " for " . $reason;

            // Check for duplicate order today
            $sql_check_ord = "SELECT order_id FROM patient_orders WHERE patient_id = ? AND order_type = 'Consult' AND order_name = ? AND DATE(created_at) = CURDATE()";
            $stmt_co = $conn->prepare($sql_check_ord);
            $stmt_co->bind_param("is", $patient_id, $order_name);
            $stmt_co->execute();
            if (!$stmt_co->get_result()->fetch_assoc()) {
                $sql_ins_ord = "INSERT INTO patient_orders (patient_id, appointment_id, user_id, order_type, order_name, priority, status, created_at) VALUES (?, ?, ?, 'Consult', ?, ?, 'Ordered', NOW())";
                $stmt_io = $conn->prepare($sql_ins_ord);
                $stmt_io->bind_param("iiiss", $patient_id, $appointment_id, $user_id, $order_name, $priority);
                $stmt_io->execute();
                $stmt_io->close();
            }
            $stmt_co->close();
        }
    }

    // 4. Save Images (if present)
    $images_to_process = [];
    if (isset($data->images) && is_array($data->images)) {
        $images_to_process = $data->images;
    } elseif (isset($data->image_data) && !empty($data->image_data)) {
        // Backward compatibility
        $images_to_process[] = (object)[
            'base64' => $data->image_data,
            'mime' => $data->mime_type,
            'type' => isset($data->image_type) ? $data->image_type : null
        ];
    }

    foreach ($images_to_process as $img_obj) {
        if (!isset($img_obj->base64) || empty($img_obj->base64)) continue;
        
        $img_data = base64_decode($img_obj->base64);
        if ($img_data !== false) {
            $ext = 'jpg';
            if (isset($img_obj->mime) && $img_obj->mime === 'image/png') $ext = 'png';
            if (isset($img_obj->mime) && $img_obj->mime === 'image/jpeg') $ext = 'jpg';
            
            $filename = "ai_vision_{$patient_id}_" . time() . "_" . uniqid() . ".{$ext}";
            $upload_dir = '../uploads/patient_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_path = $upload_dir . $filename;
            $db_path = 'uploads/patient_documents/' . $filename; // Relative path for DB
            
            if (file_put_contents($file_path, $img_data)) {
                // Insert into patient_documents
                $doc_type = 'AI Analysis Image';
                $sql_doc = "INSERT INTO patient_documents (patient_id, user_id, file_name, file_path, document_type, upload_date) 
                            VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt_doc = $conn->prepare($sql_doc);
                $stmt_doc->bind_param("iisss", $patient_id, $user_id, $filename, $db_path, $doc_type);
                $stmt_doc->execute();
                $stmt_doc->close();

                // --- NEW: Also link to Wound Gallery if wounds were detected ---
                if (!empty($detected_wounds_map)) {
                    // Use the first detected wound for now
                    $target_wound_id = array_key_first($detected_wounds_map);
                    $target_assessment_id = $detected_wounds_map[$target_wound_id];
                    
                    $img_type = isset($img_obj->type) && !empty($img_obj->type) ? $img_obj->type : 'AI Capture';
                    
                    if ($img_type === 'AI Capture' && isset($context) && ($context === 'Pre-Debridement' || $context === 'Post-Debridement')) {
                        $img_type = $context;
                    }

                    $sql_wimg = "INSERT INTO wound_images (wound_id, image_path, image_type, uploaded_at, appointment_id, assessment_id) 
                                 VALUES (?, ?, ?, NOW(), ?, ?)";
                    $stmt_wimg = $conn->prepare($sql_wimg);
                    $stmt_wimg->bind_param("issii", $target_wound_id, $db_path, $img_type, $appointment_id, $target_assessment_id);
                    $stmt_wimg->execute();
                    $stmt_wimg->close();
                }
            }
        }
    }
    
    echo json_encode(["success" => true, "message" => "Data confirmed and saved."]);
    exit;
}

if (!isset($data->transcript) || trim($data->transcript) === '') {
    // If no text but image is present, set a default prompt to trigger analysis
    if ((isset($data->image_data) && !empty($data->image_data)) || (isset($data->images) && !empty($data->images))) {
        $transcript = "Please analyze this image comprehensively. Describe the wound, estimate dimensions, tissue type, and any other clinical findings relevant for a wound care assessment.";
    } else {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Transcript or Image is required."]);
        exit;
    }
} else {
    $transcript = $data->transcript;
}

$mode = isset($data->mode) ? $data->mode : 'chat';
$current_note = isset($data->current_note) ? $data->current_note : '';

// --- AUTO-SWITCH TO FULL VISIT MODE FOR IMAGES ---
// If an image is provided, we nearly ALWAYS want structured extraction (wound dims, tissue, etc).
// So specific logic: if image detected, treat as 'full_visit' to trigger the JSON extraction logic.
if ((isset($data->image_data) && !empty($data->image_data)) || (isset($data->images) && !empty($data->images))) {
    // Check if the user specifically asked for just a chat or simple Q&A.
    // If simplistic (e.g. "what is this?"), maybe just chat.
    // But for this use case (EMR), let's default to full extraction logic to catch the wound data.
    $mode = 'full_visit';
}

// --- NEW: Save User Message & Image ---
$user_image_path = null;
if (isset($data->image_data) && !empty($data->image_data)) {
    $img_data = base64_decode($data->image_data);
    if ($img_data !== false) {
        $ext = 'jpg';
        if (isset($data->mime_type) && $data->mime_type === 'image/png') $ext = 'png';
        
        $filename = "ai_chat_{$patient_id}_" . time() . "_" . uniqid() . ".{$ext}";
        $upload_dir = '../uploads/patient_documents/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_path = $upload_dir . $filename;
        $db_path = 'uploads/patient_documents/' . $filename;
        
        if (file_put_contents($file_path, $img_data)) {
            $user_image_path = $db_path;
            
            // Also save to patient_documents for record
            $doc_type = 'AI Chat Image';
            $sql_doc = "INSERT INTO patient_documents (patient_id, user_id, file_name, file_path, document_type, upload_date) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt_doc = $conn->prepare($sql_doc);
            if ($stmt_doc) {
                $stmt_doc->bind_param("iisss", $patient_id, $user_id, $filename, $db_path, $doc_type);
                $stmt_doc->execute();
                $stmt_doc->close();
            }
        }
    }
}

// Make $user_image_path global so it can be accessed inside the wound loop
global $user_image_path;
$user_image_type = isset($data->image_type) ? $data->image_type : 'AI Analysis';
global $user_image_type;

$user_selected_wound_id = isset($data->wound_id) ? intval($data->wound_id) : 0;
global $user_selected_wound_id;

// Save User Message to History
$sender = 'user';
$sql_hist = "INSERT INTO visit_ai_messages (patient_id, appointment_id, sender, message, image_path) VALUES (?, ?, ?, ?, ?)";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("iisss", $patient_id, $appointment_id, $sender, $transcript, $user_image_path);
$stmt_hist->execute();
$stmt_hist->close();

// --- Gather Context ---
$context_text = "";

// 0. Use Frontend Context if available (New Intelligence Layer)
$frontend_context = isset($data->context) ? $data->context : null;
if ($frontend_context && (is_object($frontend_context) || is_array($frontend_context))) {
    // Cast to object if array
    $fc = is_array($frontend_context) ? (object)$frontend_context : $frontend_context;
    
    $age = isset($fc->patient_age) ? $fc->patient_age : '?';
    $gender = isset($fc->patient_gender) ? $fc->patient_gender : '?';
    $context_text .= "### PATIENT SNAPSHOT (From Frontend):\n";
    $context_text .= "- Demographics: $age year old $gender.\n";

    if (!empty($fc->active_wounds)) {
        $context_text .= "- Active Wounds on File:\n";
        foreach ($fc->active_wounds as $w) {
            $w = is_array($w) ? (object)$w : $w;
            $loc = isset($w->location) ? $w->location : '?';
            $wtype = isset($w->wound_type) ? $w->wound_type : '?';
            $onset = isset($w->date_onset) ? $w->date_onset : '?';
            $context_text .= "  * $loc ($wtype, Onset: $onset)\n";
        }
    }
    if (!empty($fc->active_meds)) {
        $context_text .= "- Active Medications:\n";
        foreach ($fc->active_meds as $m) {
            $m = is_array($m) ? (object)$m : $m;
            $dname = isset($m->drug_name) ? $m->drug_name : '?';
            $ddose = isset($m->dosage) ? $m->dosage : '';
            $dfreq = isset($m->frequency) ? $m->frequency : '';
            $context_text .= "  * $dname $ddose $dfreq\n";
        }
    }
    if (!empty($fc->past_visits)) {
        $context_text .= "- Previous Visits:\n";
        foreach ($fc->past_visits as $pv) {
            $pv = is_array($pv) ? (object)$pv : $pv;
            $vdate = isset($pv->visit_date) ? $pv->visit_date : '?';
            $vcc = isset($pv->chief_complaint) ? $pv->chief_complaint : '?';
            $context_text .= "  * $vdate: $vcc\n";
        }
    }
    $context_text .= "\n";
}

if ($patient_id > 0) {
    // 1. Demographics
    $sql_pt = "SELECT first_name, last_name, date_of_birth, gender, allergies, past_medical_history FROM patients WHERE patient_id = ?";
    $stmt = $conn->prepare($sql_pt);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res_pt = $stmt->get_result();
    if ($pt = $res_pt->fetch_assoc()) {
        $dob = $pt['date_of_birth'];
        $age = "Unknown";
        if ($dob && $dob !== '0000-00-00') {
            try {
                $bday = new DateTime($dob);
                $today = new DateTime('today');
                $age = $bday->diff($today)->y;
            } catch (Exception $e) {
                // Ignore date errors
            }
        }
        
        $context_text .= "Patient: {$pt['first_name']} {$pt['last_name']} (Age: $age, Gender: {$pt['gender']}).\n";
        $context_text .= "Allergies: " . ($pt['allergies'] ?: 'None') . ".\n";
        $context_text .= "History: " . ($pt['past_medical_history'] ?: 'None') . ".\n";
    }
    $stmt->close();

    // 2. Latest Vitals
    $sql_vitals = "SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql_vitals);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res_vitals = $stmt->get_result();
    if ($vitals = $res_vitals->fetch_assoc()) {
        // Handle potential missing keys or different column names
        $bp = $vitals['blood_pressure'] ?? 'N/A';
        $hr = $vitals['heart_rate'] ?? 'N/A';
        
        // Temperature (Handle C to F conversion if stored as C)
        $temp = 'N/A';
        if (isset($vitals['temperature_f'])) {
            $temp = $vitals['temperature_f'] . "F";
        } elseif (isset($vitals['temperature_celsius'])) {
            // Convert C to F for display context
            $c = floatval($vitals['temperature_celsius']);
            if ($c > 0) {
                $f = ($c * 9/5) + 32;
                $temp = round($f, 1) . "F";
            }
        }

        $context_text .= "Latest Vitals ({$vitals['visit_date']}): BP {$bp}, HR {$hr}, Temp {$temp}.\n";
    }
    $stmt->close();
}

if ($appointment_id > 0) {
    // 3. Current Visit HPI
    $sql_hpi = "SELECT * FROM patient_hpi WHERE appointment_id = ?";
    $stmt = $conn->prepare($sql_hpi);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $res_hpi = $stmt->get_result();
    if ($hpi = $res_hpi->fetch_assoc()) {
        // Filter out empty fields to save tokens
        $hpi_summary = [];
        foreach ($hpi as $k => $v) {
            if (!empty($v) && !in_array($k, ['hpi_id', 'appointment_id', 'patient_id', 'created_at'])) {
                $hpi_summary[] = "$k: $v";
            }
        }
        if (!empty($hpi_summary)) {
            $context_text .= "Current Visit HPI: " . implode(", ", $hpi_summary) . ".\n";
        }
    }
    $stmt->close();
}

if ($patient_id > 0) {
    // 4. Active Medications
    $sql_meds = "SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($sql_meds);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res_meds = $stmt->get_result();
    $meds_list = [];
    while ($row = $res_meds->fetch_assoc()) {
        $meds_list[] = "{$row['drug_name']} {$row['dosage']} ({$row['frequency']})";
    }
    if (!empty($meds_list)) {
        $context_text .= "Active Medications: " . implode(", ", $meds_list) . ".\n";
    }
    $stmt->close();

    // 5. Past Visit Notes (Last 3)
    // Exclude current appointment if possible
    $sql_notes = "SELECT note_date, chief_complaint, assessment, plan FROM visit_notes WHERE patient_id = ? AND appointment_id != ? ORDER BY note_date DESC LIMIT 3";
    $stmt = $conn->prepare($sql_notes);
    $stmt->bind_param("ii", $patient_id, $appointment_id);
    $stmt->execute();
    $res_notes = $stmt->get_result();
    if ($res_notes->num_rows > 0) {
        $context_text .= "Past Visits:\n";
        while ($row = $res_notes->fetch_assoc()) {
            $date = date('Y-m-d', strtotime($row['note_date']));
            $context_text .= "- $date: CC: {$row['chief_complaint']}. Assessment: {$row['assessment']}. Plan: {$row['plan']}\n";
        }
    }
    $stmt->close();

    // 6. Wound History Summary with Latest Measurements
    $sql_wounds = "
        SELECT 
            w.wound_id, w.location, w.wound_type, w.status, w.date_onset,
            wa.length_cm, wa.width_cm, wa.depth_cm, wa.assessment_date
        FROM wounds w
        LEFT JOIN (
            SELECT wound_id, length_cm, width_cm, depth_cm, assessment_date
            FROM wound_assessments
            WHERE (wound_id, assessment_date) IN (
                SELECT wound_id, MAX(assessment_date)
                FROM wound_assessments
                GROUP BY wound_id
            )
        ) wa ON w.wound_id = wa.wound_id
        WHERE w.patient_id = ?";
        
    $stmt = $conn->prepare($sql_wounds);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res_wounds = $stmt->get_result();
    $wounds_list = [];
    while ($row = $res_wounds->fetch_assoc()) {
        $dims = "No measurements";
        if ($row['length_cm'] !== null) {
            $dims = "{$row['length_cm']}x{$row['width_cm']}x{$row['depth_cm']} cm (on {$row['assessment_date']})";
        }
        $wounds_list[] = "{$row['location']} ({$row['wound_type']}, Status: {$row['status']}, Latest: $dims)";
    }
    if (!empty($wounds_list)) {
        $context_text .= "Wound History: " . implode("; ", $wounds_list) . ".\n";
    }
    $stmt->close();

    // 7. Recent Documents
    $sql_docs = "SELECT document_type, upload_date FROM patient_documents WHERE patient_id = ? ORDER BY upload_date DESC LIMIT 5";
    $stmt = $conn->prepare($sql_docs);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res_docs = $stmt->get_result();
    $docs_list = [];
    while ($row = $res_docs->fetch_assoc()) {
        $docs_list[] = "{$row['document_type']} ({$row['upload_date']})";
    }
    if (!empty($docs_list)) {
        $context_text .= "Recent Documents: " . implode(", ", $docs_list) . ".\n";
    }
    $stmt->close();

    // 8. Current Visit Chat History (CRITICAL for Live Note)
    $sql_chat = "SELECT sender, message FROM visit_ai_messages WHERE appointment_id = ? ORDER BY created_at ASC";
    $stmt_chat = $conn->prepare($sql_chat);
    $stmt_chat->bind_param("i", $appointment_id);
    $stmt_chat->execute();
    $res_chat = $stmt_chat->get_result();
    $chat_transcript = [];
    while ($row = $res_chat->fetch_assoc()) {
        $role = ($row['sender'] === 'user') ? 'Clinician' : 'AI';
        // Skip the current message if it's the last one to avoid duplication in prompt? 
        // Actually, providing full context is better.
        $chat_transcript[] = "$role: \"{$row['message']}\"";
    }
    $stmt_chat->close();
    
    if (!empty($chat_transcript)) {
        $context_text .= "\nTRANSCRIPT OF CURRENT VISIT SO FAR:\n" . implode("\n", $chat_transcript) . "\n";
    }
}

$system_instruction = "You are a helpful, friendly medical AI companion for a clinician. 
You are conversing with the clinician while they treat a patient.
Your goal is to be supportive, answer questions about the patient based on the provided context, or engage in brief professional small talk.
Keep your responses concise (1-2 sentences) and natural, as they will be spoken aloud via Text-to-Speech.
Do not list data unless asked. If the user greets you, greet them back warmly.
If the user asks a medical question, answer based on general knowledge but remind them to verify.

IMPORTANT: You CANNOT perform actions like adding wounds, ordering meds, or changing records directly. 
If the user asks you to 'add a wound' or 'order labs', politely explain that you cannot do that yet, or ask them to use the specific voice command keywords.
Do NOT say 'I have added the wound' if you cannot do it.

Context provided below:\n" . $context_text;

if ($mode === 'full_visit') {
    $current_note_text = !empty($current_note) ? "\n\nCURRENT LIVE NOTE DRAFT (Use this as the base and UPDATE it):\n" . $current_note . "\n\n" : "";
    $focused_wound_text = ($user_selected_wound_id > 0) ? "\n\nFOCUSED WOUND: The user is assessing a specific wound. Ensure data is linked to this location.\n" : "";
    $user_image_text = ($user_image_path) ? "\n\nUSER UPLOADED IMAGE: The user has uploaded an image. Analyze it and include findings in the 'objective' or 'wounds' section of 'extracted_data'.\n" : "";

    $system_instruction = <<<EOT
You are an expert AI medical assistant (similar to ChatGPT) and clinical scribe.
You are assisting a clinician during a patient visit.

Your behavior should adapt to the user's input:
1. DICTATION/CONVERSATION: If the user is speaking to the patient or dictating findings, capture the data into the 'extracted_data' and keep your 'reply' brief and professional (e.g., 'Vitals and wound details recorded.').
2. DIRECT PROMPTS: 
   - If the user asks to generate a section of the note (e.g., "Suggest a plan", "Write the HPI", "Summarize history"), generate the DETAILED content in the corresponding 'extracted_data' field (e.g., 'plan', 'hpi') and keep the 'reply' brief (e.g., "I've added a suggested plan to the note.").
   - If the user asks a general medical question unrelated to the note structure (e.g., "What is the dose of Amoxicillin?"), answer fully in the 'reply'.

$current_note_text
$focused_wound_text
$user_image_text

You may be provided with an image. Analyze it (dimensions, tissue type, etc.) and include findings in the 'extracted_data'.

Your task is THREE-FOLD:
1. reply: The text response to the user. Keep it brief if you are updating the note. Only provide long explanations if specifically asked a general knowledge question.
2. extracted_data: Generate or update a structured medical note (SOAP format).
   - **GOAL:** GENERATE THE MOST DETAILED, COMPREHENSIVE MEDICAL NOTE POSSIBLE. The user wants a "MAXIMALIST" note. Do not summarize. EXPAND every section to multiple paragraphs if possible.
   - **HPI:** Write a detailed chronological account, including risk factors, social determinants, and context.
   - **ROS:** AUTO-GENERATE a full 14-point Review of Systems (Constitutional, Eyes, ENT, CV, Resp, GI, GU, MSK, Skin, Neuro, Psych, Endo, Heme/Lymph, All/Imm). Infer 'denies' for all non-relevant symptoms to create a complete record.
   - **Objective:** AUTO-GENERATE a complete head-to-toe Physical Exam description (General, HEENT, Neck, CV, Lungs, Abdomen, Extremities, Neuro, Skin). Describe the wound in minute detail.
   - **Assessment:** Provide a multi-paragraph clinical synthesis. Discuss differential diagnoses, wound progress, prognosis, and justification for the plan.
   - **Plan:** detailed step-by-step instructions, patient education provided, and red flags discussed.
3. clinical_insights: Generate 1-3 brief, high-value clinical suggestions based on the data (e.g., "Consider nutrition consult for poor healing", "Flag for sepsis risk due to tachycardia", "Suggest offloading boot").

CRITICAL: You must output a valid JSON object containing the extracted clinical data.

JSON STRUCTURE:
{
    "reply": "The response to the user (Brief for dictation, Detailed for prompts).",
    "extracted_data": {
        "chief_complaint": "String",
        "hpi": "String (Extensive, Multi-paragraph Narrative)",
        "ros": "String (Full 14-point System Review)",
        "subjective": "String",
        "objective": "String (Complete Head-to-Toe Exam & Detailed Wound Description)",
        "assessment": "String (Extensive Clinical Discussion & Synthesis)",
        "plan": "String (Comprehensive Care Plan, Education, Follow-up)",
        "vitals": { ... },
        "wounds": [ 
            {
                "location": "Atomic Location (e.g. 'Right Heel')",
                "type": "Wound Type",
                "assessment_type": "'Pre-Debridement' or 'Post-Debridement'. If user says 'Pre', 'Before', or 'Start' of visit, map to Pre-Debridement. If 'Post', 'After', or 'Finish', map to Post-Debridement.",
                "length_cm": 0.0,
                "width_cm": 0.0,
                "depth_cm": 0.0,
                "granulation_percent": 0,
                "slough_percent": 0,
                "eschar_percent": 0,
                "pain_level": 0,
                "drainage_amount": "None/Scant/Moderate/Heavy",
                "drainage_type": "Serous/Serosanguinous/Purulent",
                "tunneling_present": "No",
                "undermining_present": "No",
                "periwound_condition": "Intact/Erythema/Macerated",
                "debridement_performed": "Yes/No",
                "debridement_type": "Sharp/Mechanical/Enzymatic/Autolytic",
                "debridement_implements": "Curet/Scalpel/Scissors",
                "debridement_tolerance": "Well tolerated/Patient complained of pain",
                "debridement_closure": "Hemostasis achieved with pressure/silver nitrate",
                "procedure_notes": "Brief details for this specific wound"
            }
        ],
        "procedure": {
            "narrative": "FULL PROCEDURE NOTE based on standard template. MUST include these bold headers: **Location of care**, **Indication**, **Medication reconciliation today**, **Infection screen at time of grafting**, **Consent and pre-procedure safety**, **Anesthesia and preparation**, **Debridement details**, **Final depth reached**, **Pre-debridement dimensions**. CRITICAL: You MUST populate 'Pre-debridement dimensions' with the actual Length x Width x Depth values from the 'wounds' array if available. Do NOT write 'To be determined'."
        },
        "medications": [ ... ],
        "diagnoses": [ {"code": "ICD10 Code (or null)", "description": "Diagnosis Name", "notes": "Optional notes"} ]
    },
    "clinical_insights": [ "Insight 1", "Insight 2" ],
    "thought_process": [ "Step 1: Analyzed user input...", "Step 2: Extracted vitals...", "Step 3: Checked for red flags..." ]
}

CRITICAL INSTRUCTIONS:
1. Extract ALL clinical data mentioned.
2. If an image is provided, YOU MUST ESTIMATE MEASUREMENTS and populate the 'wounds' array with numerical values for 'length_cm', 'width_cm', 'depth_cm', and tissue percentages. DO NOT leave them null or zero if an image is visible.
3. If the user provides a case summary and asks for a note, generate a LONG, COMPLETE narrative in the 'hpi', 'ros', 'objective', 'assessment', and 'plan' fields of 'extracted_data'.
4. Do NOT return HTML in the JSON values (except for the image tag in structured_note if needed, but here we use extracted_data).
5. The 'reply' is what the user sees in the chat. Make it helpful.

Context provided below:
EOT;
    $system_instruction .= "\n" . $context_text;
}

// --- Call AI Service ---

// Construct Parts
$parts = [];
$user_prompt_text = "Clinician says: \"$transcript\"";
if (isset($data->image_data) && !empty($data->image_data)) {
    $img_tag_type = isset($data->image_type) ? $data->image_type : '';
    $user_prompt_text .= "\n\n[SYSTEM INSTRUCTION: An image was attached. You MUST analyze it. Estimate wound dimensions (LxWxD), tissue type (granulation/slough/eschar %), and drainage. Fill in the 'wounds' array in 'extracted_data' with these specific values.";
    
    if (!empty($img_tag_type) && ($img_tag_type === 'Pre-Debridement' || $img_tag_type === 'Post-Debridement')) {
        $user_prompt_text .= "\n\nCRITICAL CONTEXT: The user explicitly labeled this image as '$img_tag_type'. You MUST set 'assessment_type' to '$img_tag_type' in the wounds extraction logic.";
    } else {
        $user_prompt_text .= "\n\nCRITICAL: Determine if this is 'Pre-Debridement' or 'Post-Debridement' based on the conversation history. If the user recently mentioned 'Pre-Debridement', 'Before', or 'Start', set 'assessment_type' to 'Pre-Debridement'. If unclear, check the wound appearance (necrotic = likely pre, clean/bleeding = likely post). Default to 'Post-Debridement' only if no cues exist.";
    }
    
    $user_prompt_text .= "]";
}
$parts[] = ["text" => $user_prompt_text];

if (isset($data->image_data) && !empty($data->image_data)) {
    $parts[] = [
        "inlineData" => [
            "mimeType" => $data->mime_type ?? 'image/jpeg',
            "data" => $data->image_data
        ]
    ];
}

$payload = [
    "contents" => [
        [
            "role" => "user",
            "parts" => $parts
        ]
    ],
    "systemInstruction" => [
        "parts" => [
            ["text" => $system_instruction]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 8192
    ]
];

$result = generateContent($payload);

if (isset($result['error'])) {
    echo json_encode(["success" => false, "message" => "AI Error: " . $result['error']]);
    exit;
}

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502); // Bad Gateway
    echo json_encode(["success" => false, "message" => "Invalid response from AI service.", "debug" => $response]);
    exit;
}

if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    $raw_text = trim($result['candidates'][0]['content']['parts'][0]['text']);
    
    // DEBUG: Log raw response to file
    file_put_contents(__DIR__ . '/debug_ai_response.txt', $raw_text);
    
    $reply_text = $raw_text;
    $structured_note = '';
    
    if ($mode === 'full_visit') {
        // Try to parse JSON from the AI response
        $parsed = null;
        
        // 1. Try to extract JSON block using Regex (most robust)
        if (preg_match('/\{[\s\S]*\}/', $raw_text, $matches)) {
            $json_candidate = $matches[0];
            $parsed = json_decode($json_candidate, true);
            
            // 2. Fallback: Regex Extraction if JSON decode fails
            if (json_last_error() !== JSON_ERROR_NONE) {
                $parsed = [];
                // Helper to extract JSON string values: "key": "value"
                // Matches "key" : "..." (handling escaped quotes)
                
                // Extract reply
                if (preg_match('/"reply"\s*:\s*"(.*?)(?<!\\\\)"/s', $json_candidate, $m)) {
                    // Manually unescape JSON string
                    $parsed['reply'] = json_decode('"' . $m[1] . '"');
                }
                
                // Extract structured_note
                if (preg_match('/"structured_note"\s*:\s*"(.*?)(?<!\\\\)"/s', $json_candidate, $m)) {
                    $parsed['structured_note'] = json_decode('"' . $m[1] . '"');
                }
                
                // Extract insight
                if (preg_match('/"insight"\s*:\s*"(.*?)(?<!\\\\)"/s', $json_candidate, $m)) {
                    $parsed['insight'] = json_decode('"' . $m[1] . '"');
                }
                
                // If json_decode on the fragment failed (e.g. bad escapes), try raw strip
                if (empty($parsed['reply']) && preg_match('/"reply"\s*:\s*"(.*?)(?<!\\\\)"/s', $json_candidate, $m)) {
                     $parsed['reply'] = stripslashes($m[1]);
                }
            }
        }
        
        // 3. Fallback: Simple string replacement if regex failed or returned invalid JSON
        if ((!$parsed || empty($parsed)) && json_last_error() !== JSON_ERROR_NONE) {
            $clean_json = str_replace(['```json', '```'], '', $raw_text);
            $parsed = json_decode($clean_json, true);
        }
        
        if (($parsed && isset($parsed['reply'])) || (json_last_error() === JSON_ERROR_NONE && isset($parsed['reply']))) {
            $reply_text = $parsed['reply'];
            $extracted = isset($parsed['extracted_data']) ? $parsed['extracted_data'] : [];
            $insight_text = isset($parsed['insight']) ? $parsed['insight'] : '';
            $clinical_insights = isset($parsed['clinical_insights']) ? $parsed['clinical_insights'] : [];
            $thought_process = isset($parsed['thought_process']) ? $parsed['thought_process'] : [];
            
            // --- SAVE EXTRACTED DATA TO DB ---
            if (!empty($extracted)) {
                try {
                    // 1. Update Visit Notes (Narratives)
                    $cc = $extracted['chief_complaint'] ?? '';
                    $hpi = $extracted['hpi'] ?? '';
                    $ros = $extracted['ros'] ?? '';
                    $subj = $extracted['subjective'] ?? '';
                    $obj = $extracted['objective'] ?? '';
                    $assess = $extracted['assessment'] ?? '';
                    $plan = $extracted['plan'] ?? '';

                    // [NEW] Procedure Narrative
                    $proc_narrative = '';
                    if (isset($extracted['procedure']) && is_array($extracted['procedure'])) {
                        $proc_narrative = $extracted['procedure']['narrative'] ?? '';
                    }

                    // Combine HPI/ROS into Subjective if needed
                    $full_subjective = "";
                    if($hpi) $full_subjective .= "HPI: $hpi\n\n";
                    if($ros) $full_subjective .= "ROS: $ros\n\n";
                    $full_subjective .= $subj;
                    
                    // Ensure user_id is not null (fallback to 0 or handle gracefully)
                    $safe_user_id = $user_id ?? 0;

                    // --- SAVE VITALS, WOUNDS, DIAGNOSES (Moved to top so Note Generation can see them) ---
                    // 2. Save Vitals
                    if (isset($extracted['vitals']) && is_array($extracted['vitals'])) {
                        $v = $extracted['vitals'];
                        $bp = $v['blood_pressure'] ?? null;
                        $hr = $v['heart_rate'] ?? null;
                        $rr = $v['respiratory_rate'] ?? null;
                        $o2 = $v['oxygen_saturation'] ?? null;
                        $temp = $v['temperature_celsius'] ?? null;
                        // Convert Fahrenheit to Celsius if provided
                        if ($temp === null && isset($v['temperature_fahrenheit'])) {
                            $f = floatval($v['temperature_fahrenheit']);
                            $temp = ($f - 32) * 5/9;
                        }
                        $wt = $v['weight_kg'] ?? null;
                        $ht = $v['height_cm'] ?? null;
                        
                        $sql_vitals = "INSERT INTO patient_vitals 
                                        (patient_id, appointment_id, visit_date, blood_pressure, heart_rate, respiratory_rate, oxygen_saturation, temperature_celsius, weight_kg, height_cm)
                                        VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE
                                        blood_pressure = COALESCE(VALUES(blood_pressure), blood_pressure),
                                        heart_rate = COALESCE(VALUES(heart_rate), heart_rate),
                                        respiratory_rate = COALESCE(VALUES(respiratory_rate), respiratory_rate),
                                        oxygen_saturation = COALESCE(VALUES(oxygen_saturation), oxygen_saturation),
                                        temperature_celsius = COALESCE(VALUES(temperature_celsius), temperature_celsius),
                                        weight_kg = COALESCE(VALUES(weight_kg), weight_kg),
                                        height_cm = COALESCE(VALUES(height_cm), height_cm)";
                        $stmt_v = $conn->prepare($sql_vitals);
                        if ($stmt_v) {
                            $stmt_v->bind_param("iisiiiddd", $patient_id, $appointment_id, $bp, $hr, $rr, $o2, $temp, $wt, $ht);
                            $stmt_v->execute();
                            $stmt_v->close();
                        }
                    }

                    // 3. Save Wounds
                    if (isset($extracted['wounds']) && is_array($extracted['wounds'])) {
                        file_put_contents(__DIR__ . '/debug_wounds_log.txt', "\n--- New Wound Save Batch ---\n", FILE_APPEND);
                        foreach ($extracted['wounds'] as $w) {
                            $loc = $w['location'] ?? null;
                            file_put_contents(__DIR__ . '/debug_wounds_log.txt', "Processing Wound: " . json_encode($w) . "\n", FILE_APPEND);
                            
                            if (!$loc) continue;
                            
                            // Find/Create Wound
                            $wound_id = 0;
                            $sql_find = "SELECT wound_id FROM wounds WHERE patient_id = ? AND location = ? LIMIT 1";
                            $stmt_f = $conn->prepare($sql_find);
                            if ($stmt_f) {
                                $stmt_f->bind_param("is", $patient_id, $loc);
                                $stmt_f->execute();
                                $res_f = $stmt_f->get_result();
                                if ($row = $res_f->fetch_assoc()) {
                                    $wound_id = $row['wound_id'];
                                } else {
                                    $type = $w['type'] ?? 'Other';
                                    $sql_ins_w = "INSERT INTO wounds (patient_id, location, wound_type, status, date_onset) VALUES (?, ?, ?, 'Active', CURDATE())";
                                    $stmt_iw = $conn->prepare($sql_ins_w);
                                    if ($stmt_iw) {
                                        $stmt_iw->bind_param("iss", $patient_id, $loc, $type);
                                        $stmt_iw->execute();
                                        $wound_id = $stmt_iw->insert_id;
                                        $stmt_iw->close();
                                    }
                                }
                                $stmt_f->close();
                            }

                            // Insert or Update Assessment
                            if ($wound_id > 0) {
                                $len = $w['length_cm'] ?? null;
                                $wid = $w['width_cm'] ?? null;
                                $dep = $w['depth_cm'] ?? null;
                                $pain = $w['pain_level'] ?? null;
                                
                                // New Fields (Tissue & Characteristics)
                                $gran = $w['granulation_percent'] ?? null;
                                $slough = $w['slough_percent'] ?? null;
                                $eschar = $w['eschar_percent'] ?? null;
                                $exudate_amt = $w['drainage_amount'] ?? null;
                                $drainage_type = $w['drainage_type'] ?? null;
                                $odor = isset($w['odor']) ? (($w['odor']=='Yes' || $w['odor']===true)?'Yes':'No') : null;
                                // Simple mapping for present/not
                                $tunnel = $w['tunneling_present'] ?? null;
                                $under = $w['undermining_present'] ?? null;
                                $peri = $w['periwound_condition'] ?? null;

                                // Procedure Fields
                                $debride_p = isset($w['debridement_performed']) && ($w['debridement_performed'] === 'Yes' || $w['debridement_performed'] === true) ? 'Yes' : 'No';
                                $debride_t = $w['debridement_type'] ?? null;
                                $treatments = $w['procedure_notes'] ?? null;
                                
                                // Determine Assessment Type logic:
                                // 1. If explicit assessment_type is provided in the JSON from AI (e.g. user said "Pre-Debridement"), use it.
                                // 2. Fallback to global user_image_type if set.
                                // 3. Default to Post-Debridement.
                                
                                $assess_type = 'Post-Debridement'; // Default
                                
                                // Allow AI to override if it extracted a type from the "update X assessment" command
                                if (isset($w['assessment_type']) && !empty($w['assessment_type'])) {
                                    // Normalize
                                    if (stripos($w['assessment_type'], 'pre') !== false) $assess_type = 'Pre-Debridement';
                                    else if (stripos($w['assessment_type'], 'post') !== false) $assess_type = 'Post-Debridement';
                                } else {
                                    // 2. Transcript Analysis (Fallback if AI missed it but user was explicit)
                                    global $transcript; 
                                    $t_lower = strtolower($transcript ?? '');
                                    
                                    if (strpos($t_lower, 'pre-debridement') !== false || 
                                        strpos($t_lower, 'before debridement') !== false || 
                                        strpos($t_lower, 'pre debridement') !== false ||
                                        strpos($t_lower, 'start of the visit') !== false ||
                                        strpos($t_lower, 'initial assessment') !== false) {
                                        $assess_type = 'Pre-Debridement';
                                    }
                                    
                                    // 3. Image Context (Last resort)
                                    global $user_image_type;
                                    if (isset($user_image_type) && ($user_image_type === 'Pre-Debridement' || $user_image_type === 'Post-Debridement')) {
                                        // Only override if transcript didn't specify otherwise
                                        if (strpos($t_lower, 'pre-debridement') === false && strpos($t_lower, 'pre debridement') === false) {
                                            $assess_type = $user_image_type;
                                        }
                                    }
                                }

                                $assessment_id = 0;
                                
                                // Check for existing assessment for this wound in this appointment AND type
                                $sql_check = "SELECT assessment_id FROM wound_assessments WHERE wound_id = ? AND appointment_id = ? AND assessment_type = ?";
                                $stmt_check = $conn->prepare($sql_check);
                                if ($stmt_check) {
                                    $stmt_check->bind_param("iis", $wound_id, $appointment_id, $assess_type);
                                    $stmt_check->execute();
                                    $res_check = $stmt_check->get_result();
                                    if ($row_check = $res_check->fetch_assoc()) {
                                        $assessment_id = $row_check['assessment_id'];
                                        // Update existing
                                        $sql_upd = "UPDATE wound_assessments SET 
                                            length_cm = COALESCE(?, length_cm), 
                                            width_cm = COALESCE(?, width_cm), 
                                            depth_cm = COALESCE(?, depth_cm), 
                                            pain_level = COALESCE(?, pain_level),
                                            granulation_percent = COALESCE(?, granulation_percent),
                                            slough_percent = COALESCE(?, slough_percent),
                                            eschar_percent = COALESCE(?, eschar_percent),
                                            exudate_amount = COALESCE(?, exudate_amount),
                                            drainage_type = COALESCE(?, drainage_type),
                                            tunneling_present = COALESCE(?, tunneling_present),
                                            undermining_present = COALESCE(?, undermining_present),
                                            periwound_condition = COALESCE(?, periwound_condition),
                                            debridement_performed = COALESCE(?, debridement_performed),
                                            debridement_type = COALESCE(?, debridement_type),
                                            treatments_provided = COALESCE(?, treatments_provided),
                                            odor_present = COALESCE(?, odor_present),
                                            assessment_date = NOW() 
                                            WHERE assessment_id = ?";
                                        $stmt_upd = $conn->prepare($sql_upd);
                                        if ($stmt_upd) {
                                            // Fixed type string: exudate (8) is string(s), odor (16) is string(s)
                                            // ddd (dims), i (pain), i (gran), i (slough), i (eschar), s (exudate), s (drainage), s (tunnel), s (under), s (peri), s (deb_p), s (deb_t), s (treat), s (odor), i (id)
                                            $stmt_upd->bind_param("dddiiiisssssssssi", $len, $wid, $dep, $pain, $gran, $slough, $eschar, $exudate_amt, $drainage_type, $tunnel, $under, $peri, $debride_p, $debride_t, $treatments, $odor, $assessment_id);
                                            $stmt_upd->execute();
                                            $stmt_upd->close();
                                        }
                                    } else {
                                        // Insert new
                                        $sql_wa = "INSERT INTO wound_assessments (wound_id, appointment_id, assessment_date, length_cm, width_cm, depth_cm, pain_level, assessment_type, 
                                                   granulation_percent, slough_percent, eschar_percent, exudate_amount, drainage_type, tunneling_present, undermining_present, periwound_condition,
                                                   debridement_performed, debridement_type, treatments_provided, odor_present)
                                                VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                        $stmt_wa = $conn->prepare($sql_wa);
                                        if ($stmt_wa) {
                                            // Fixed type string: exudate (11) is s
                                            // i (w_id), i (a_id), ddd (dims), i (pain), s (type), i (gran), i (slough), i (eschar), s (exudate), s (drainage), s (tunnel), s (under), s (peri), s (deb_p), s (deb_t), s (treat), s (odor)
                                            $stmt_wa->bind_param("iidddisiiisssssssss", $wound_id, $appointment_id, $len, $wid, $dep, $pain, $assess_type, $gran, $slough, $eschar, $exudate_amt, $drainage_type, $tunnel, $under, $peri, $debride_p, $debride_t, $treatments, $odor);
                                            $stmt_wa->execute();
                                            $assessment_id = $stmt_wa->insert_id;
                                            $stmt_wa->close();
                                        }
                                    }
                                    $stmt_check->close();
                                }

                                // --- NEW: Link Uploaded Image to this Assessment ---
                                // If we have a user_image_path from this request, link it to the new assessment
                                global $user_image_path; // Ensure we can access the path set earlier
                                global $user_selected_wound_id;

                                file_put_contents(__DIR__ . '/debug_wounds_log.txt', "WoundID: $wound_id, AssessID: $assessment_id, ImgPath: $user_image_path, UserWoundID: $user_selected_wound_id\n", FILE_APPEND);

                                // Only link if no specific wound selected OR if it matches the selected wound
                                $should_link_image = true;
                                if ($user_selected_wound_id > 0 && $user_selected_wound_id != $wound_id) {
                                    $should_link_image = false;
                                }

                                if ($user_image_path && $assessment_id > 0 && $should_link_image) {
                                    // Check if this image is already in wound_images (it might be if uploaded via upload_wound_photo.php)
                                    // But here we are talking about the image sent via chat payload
                                    
                                    // Insert into wound_images
                                    $img_type = isset($GLOBALS['user_image_type']) ? $GLOBALS['user_image_type'] : 'AI Analysis';

                                    // Sync image type with the assessment type we actually decided on for this wound
                                    if ($img_type === 'AI Analysis' || $img_type === 'AI Capture') {
                                        if ($assess_type === 'Pre-Debridement') $img_type = 'Pre-Debridement';
                                        if ($assess_type === 'Post-Debridement') $img_type = 'Post-Debridement';
                                    }
                                    $sql_wimg = "INSERT INTO wound_images (wound_id, assessment_id, appointment_id, image_path, image_type, uploaded_at) 
                                                 VALUES (?, ?, ?, ?, ?, NOW())";
                                    $stmt_wimg = $conn->prepare($sql_wimg);
                                    if ($stmt_wimg) {
                                        $stmt_wimg->bind_param("iiiss", $wound_id, $assessment_id, $appointment_id, $user_image_path, $img_type);
                                        $stmt_wimg->execute();
                                        file_put_contents(__DIR__ . '/debug_wounds_log.txt', "Linked Image! ID: " . $stmt_wimg->insert_id . "\n", FILE_APPEND);
                                        $stmt_wimg->close();
                                    } else {
                                        file_put_contents(__DIR__ . '/debug_wounds_log.txt', "Image Link Prepare Failed: " . $conn->error . "\n", FILE_APPEND);
                                    }
                                } else {
                                     file_put_contents(__DIR__ . '/debug_wounds_log.txt', "Skipping Image Link. ShouldLink: " . ($should_link_image?'Y':'N') . "\n", FILE_APPEND);
                                }
                            }
                        }
                    }

                    // 4. Save Diagnoses
                    if (isset($extracted['diagnoses']) && is_array($extracted['diagnoses'])) {
                        foreach ($extracted['diagnoses'] as $diag) {
                            $code = $diag['code'] ?? null;
                            $desc = $diag['description'] ?? null;
                            $notes = $diag['notes'] ?? null;
                            
                            if (!$desc) continue; // Need at least a description

                            // If no code, try to find it in master list by description (fuzzy match?)
                            // For now, if no code, we might skip or insert with a placeholder if schema allows.
                            // Schema for visit_diagnoses: icd10_code is likely required.
                            // Let's assume AI provides it or we use 'UNC' (Uncoded).
                            if (!$code) $code = 'UNC';

                            // Check if already exists for this visit
                            $sql_check_diag = "SELECT visit_diagnosis_id FROM visit_diagnoses WHERE appointment_id = ? AND (icd10_code = ? OR description = ?)";
                            $stmt_cd = $conn->prepare($sql_check_diag);
                            if ($stmt_cd) {
                                $stmt_cd->bind_param("iss", $appointment_id, $code, $desc);
                                $stmt_cd->execute();
                                $stmt_cd->store_result();
                                
                                if ($stmt_cd->num_rows == 0) {
                                    // Insert
                                    $sql_ins_diag = "INSERT INTO visit_diagnoses (appointment_id, patient_id, icd10_code, description, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)";
                                    $stmt_id = $conn->prepare($sql_ins_diag);
                                    if ($stmt_id) {
                                        $stmt_id->bind_param("iisssi", $appointment_id, $patient_id, $code, $desc, $notes, $safe_user_id);
                                        $stmt_id->execute();
                                        $stmt_id->close();
                                    }
                                }
                                $stmt_cd->close();
                            }
                        }
                    }

                    // --- CONSTRUCT UPDATED LIVE NOTE ---
                    // Since we are updating the structured fields, we MUST also update the 'live_note' blob
                    // so the frontend reflects the changes immediately.
                    
                    // 1. Fetch existing data first (to merge if AI returned partials)
                    $current_sql = "SELECT chief_complaint, subjective, objective, assessment, plan, procedure_note FROM visit_notes WHERE appointment_id = ?";
                    $stmt_curr = $conn->prepare($current_sql);
                    $stmt_curr->bind_param("i", $appointment_id);
                    $stmt_curr->execute();
                    $curr_row = $stmt_curr->get_result()->fetch_assoc();
                    $stmt_curr->close();
                    
                    if (!$curr_row) $curr_row = ['chief_complaint'=>'','subjective'=>'','objective'=>'','assessment'=>'','plan'=>'','procedure_note'=>''];

                    // [NEW] Fetch Appointment Images for Live Note inclusion
                    $appt_images_html = [];
                    $sql_get_imgs = "SELECT image_path 
                                     FROM wound_images 
                                     WHERE appointment_id = ? 
                                     ORDER BY uploaded_at ASC";
                    $stmt_gimgs = $conn->prepare($sql_get_imgs);
                    if ($stmt_gimgs) {
                       $stmt_gimgs->bind_param("i", $appointment_id);
                       $stmt_gimgs->execute();
                       $res_gimgs = $stmt_gimgs->get_result();
                       while ($rw_img = $res_gimgs->fetch_assoc()) {
                           $appt_images_html[] = $rw_img['image_path'];
                       }
                       $stmt_gimgs->close();
                    }

                    // 2. Merge New Data
                    $final_cc = !empty($cc) ? $cc : $curr_row['chief_complaint'];
                    $final_subj = !empty($full_subjective) ? $full_subjective : $curr_row['subjective'];
                    $final_obj = !empty($obj) ? $obj : $curr_row['objective'];
                    $final_assess = !empty($assess) ? $assess : $curr_row['assessment'];
                    $final_plan = !empty($plan) ? $plan : $curr_row['plan'];
                    $final_proc_note = !empty($proc_narrative) ? $proc_narrative : ($curr_row['procedure_note'] ?? '');

                    // 3. Build HTML
                    // Style definition to match visit_report.php (inline to ensure it renders everywhere)
                    // INCREASED FONT SIZES as per user request
                    $h2_style = 'style="font-size: 18px; background-color: #e5e5e5; padding: 5px 10px; border-bottom: 1px solid #ccc; font-weight: bold; margin-top: 15px; margin-bottom: 5px; color: #333;"';

                    $new_live_note = "";
                    if ($final_cc) $new_live_note .= "<h2 $h2_style>Chief Complaint</h2><p>" . nl2br(htmlspecialchars($final_cc)) . "</p>";

                    // [NEW KEY FEATURE] Separate components for cleaner layout
                    // Assuming $hpi, $ros, $subj were extracted earlier in this scope.
                    // If they are present, use them for distinct sections.
                    $has_distinct_subjective_parts = (!empty($hpi) || !empty($ros) || ((!empty($subj) && $subj !== $final_subj)));

                    if ($has_distinct_subjective_parts) {
                         if (!empty($hpi)) $new_live_note .= "<h2 $h2_style>History of Present Illness</h2><p>" . nl2br(htmlspecialchars($hpi)) . "</p>";
                         if (!empty($ros)) $new_live_note .= "<h2 $h2_style>Review of Systems</h2><p>" . nl2br(htmlspecialchars($ros)) . "</p>";
                         if (!empty($subj)) $new_live_note .= "<h2 $h2_style>Subjective</h2><p>" . nl2br(htmlspecialchars($subj)) . "</p>";
                    } else {
                        // Fallback: If no distinct parts (merged string or old data), try intelligent formatting
                        if ($final_subj) {
                             $fmt_subj = htmlspecialchars($final_subj);
                             // Attempt to break out Headers if markers are embedded
                             if (strpos($fmt_subj, 'HPI:') !== false) {
                                  // Regex split? Or simple replacement?
                                  // Simple replacement is safer provided we close/open tags correctly
                                  // Note: The previous logic of <h2>Subjective</h2><p> ... </p> wrapped everything.
                                  // Here we want to separate them.
                                  
                                  // First: HPI usually starts the block.
                                  // Check if it starts with 'HPI:'
                                  if (preg_match('/^\s*HPI:/', $fmt_subj)) {
                                      // Remove HPI marker, add proper header
                                      $fmt_subj = preg_replace('/^\s*HPI:/', '', $fmt_subj);
                                      $fmt_subj = "<h2 $h2_style>History of Present Illness</h2><p>" . $fmt_subj;
                                  } else {
                                      // Start with Subjective Header
                                      $fmt_subj = "<h2 $h2_style>Subjective</h2><p>" . $fmt_subj;
                                  }
                                  
                                  // Replace internal ROS: marker
                                  // Use str_replace carefully. \nROS: or <br>ROS:
                                  // Since we are raw string here, it's likely \n or space.
                                  // Replace "ROS:" with end-p, start-h2, start-p
                                  $fmt_subj = preg_replace('/\s*ROS:/', "</p><h2 $h2_style>Review of Systems</h2><p>", $fmt_subj);
                                  
                                  // Close final tag
                                  $fmt_subj .= "</p>";
                                  $new_live_note .= nl2br($fmt_subj); // nl2br applied to the text parts
                                  
                                  // Fix double <p> or weird tags if nl2br messes up constructed tags
                                  // Actually nl2br converts \n to <br>. It ignores HTML tags.
                                  // But applying nl2br AFTER injecting HTML tags is risky if tags have newlines.
                                  // Let's stick to the simplest fallback: Just use Subjective Header if layout fails.
                             } else {
                                 // Standard Subjective
                                 $new_live_note .= "<h2 $h2_style>Subjective</h2><p>" . nl2br($fmt_subj) . "</p>";
                             }
                        }
                    }

                    if ($final_obj) $new_live_note .= "<h2 $h2_style>Objective</h2><p>" . nl2br(htmlspecialchars($final_obj)) . "</p>";

                    // [NEW] Generate Structured Wound Assessment HTML
                    $wound_html = "";
                    $sql_wa_full = "SELECT wa.*, w.location, w.wound_type, w.date_onset
                                   FROM wound_assessments wa
                                   JOIN wounds w ON wa.wound_id = w.wound_id
                                   WHERE wa.appointment_id = ?
                                   ORDER BY w.location ASC, wa.assessment_id ASC";
                    $stmt_waf = $conn->prepare($sql_wa_full);
                    if ($stmt_waf) {
                        $stmt_waf->bind_param("i", $appointment_id);
                        $stmt_waf->execute();
                        $res_waf = $stmt_waf->get_result();
                        
                        while ($wa = $res_waf->fetch_assoc()) {
                            // Link Image to this assessment (Get Latest)
                            $wa_img = "";
                            $sql_img = "SELECT image_path FROM wound_images WHERE assessment_id = ? ORDER BY uploaded_at DESC LIMIT 1";
                            $stmt_img = $conn->prepare($sql_img);
                            $stmt_img->bind_param("i", $wa['assessment_id']);
                            $stmt_img->execute();
                            $res_img = $stmt_img->get_result();
                            if ($r_img = $res_img->fetch_assoc()) {
                                $wa_img = $r_img['image_path'];
                            }
                            $stmt_img->close();

                            // Logic for Tissue text
                            $tissue_parts = [];
                            if (isset($wa['granulation_percent'])) $tissue_parts[] = $wa['granulation_percent'] . "% Granulation";
                            if (isset($wa['slough_percent'])) $tissue_parts[] = $wa['slough_percent'] . "% Slough";
                            if (isset($wa['eschar_percent'])) $tissue_parts[] = $wa['eschar_percent'] . "% Eschar";
                            $tissue_text = implode(", ", $tissue_parts);
                            if (empty($tissue_text)) $tissue_text = "Not recorded";

                            // Logic for Tunneling/Under
                            $tunnel_text = $wa['tunneling_present'] ?? 'No';
                            if ($tunnel_text === 'Yes' && !empty($wa['tunneling_locations'])) $tunnel_text .= " at " . $wa['tunneling_locations'];

                            $under_text = $wa['undermining_present'] ?? 'No';
                            if ($under_text === 'Yes' && !empty($wa['undermining_locations'])) $under_text .= " at " . $wa['undermining_locations'];

                            // Build Card
                            $wound_html .= '<div style="margin-top: 20px; border-left: 5px solid #333; padding-left: 15px; margin-bottom: 30px;">';
                            
                            // 1. Main Header
                            $wound_html .= '<div style="font-weight: bold; font-size: 16px; margin-bottom: 15px; color: #333; font-family: sans-serif;">';
                            $wound_html .= 'WOUND: ' . strtoupper(htmlspecialchars($wa['location'])) . ' <span style="font-weight:normal; color:#555;">(' . strtoupper(htmlspecialchars($wa['wound_type'])) . ')</span>';
                            $wound_html .= '<div style="font-weight: normal; font-size: 13px; color: #666; margin-top:2px;">Onset: ' . htmlspecialchars($wa['date_onset']) . '</div>';
                            $wound_html .= '</div>';
                            
                            // 2. Assessment Box (Orange/Clean Theme)
                            $wound_html .= '<div style="border: 1px solid #fdba74; border-radius: 6px; overflow: hidden; font-family: sans-serif;">';
                            
                            // Box Header
                            $type_label = ($wa['assessment_type'] ?? '') == 'Post-Debridement' ? 'POST-DEBRIDEMENT ASSESSMENT' : 'PRE-DEBRIDEMENT ASSESSMENT';
                            $header_bg = '#fff7ed'; // light orange background
                            $header_col = '#ea580c'; // orange text
                            
                            $wound_html .= '<div style="background: '.$header_bg.'; color: '.$header_col.'; font-weight: bold; padding: 8px 12px; font-size: 13px; border-bottom: 1px solid #fdba74; letter-spacing: 0.5px;">'.$type_label.'</div>';
                            
                            // Body (Flex)
                            $wound_html .= '<div style="padding: 12px; display: flex; gap: 20px; font-size: 14px; background: #fff;">';
                            
                            // Left Col: Data
                            $wound_html .= '<div style="flex: 1;">';
                            
                            // Helper for rows
                            $row = function($label, $val, $color='#c2410c') {
                                return "<div style='display:flex; margin-bottom: 5px; align-items: baseline;'><div style='width: 140px; font-weight: bold; color: $color; flex-shrink:0;'>$label:</div><div style='flex:1; color: #333;'>$val</div></div>";
                            };

                            // Dimensions
                            $l = $wa['length_cm'] ?? 0; $w = $wa['width_cm'] ?? 0; $d = $wa['depth_cm'] ?? 0;
                            $area = number_format($l * $w, 2);
                            $dims = "$l x $w x $d cm (Area: $area cm²)";
                            
                            $wound_html .= $row("Dimensions", $dims);
                            $wound_html .= $row("Tunneling", $tunnel_text);
                            $wound_html .= $row("Undermining", $under_text);
                            $wound_html .= $row("Tissue composition", $tissue_text);
                            
                            if (!empty($wa['exposed_structures'])) $wound_html .= $row("Exposed Structures", $wa['exposed_structures']);
                            if (!empty($wa['drainage_amount']) || !empty($wa['drainage_type'])) $wound_html .= $row("Drainage", ($wa['drainage_amount']??''). ' ' . ($wa['drainage_type']??''));
                            if (!empty($wa['odor_present'])) $wound_html .= $row("Odor", $wa['odor_present']);
                            
                            $wound_html .= $row("Pain Level", ($wa['pain_level'] ?? 0) . "/10");
                            
                            $wound_html .= '</div>'; // End Left Col
                            
                            // Right Col: Image
                            if ($wa_img) {
                                $wound_html .= '<div style="width: 220px; flex-shrink: 0; text-align: center;">';
                                $wound_html .= '<div style="border: 1px solid #e5e7eb; padding: 5px; background: #fff; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">';
                                $wound_html .= '<div style="font-size: 12px; font-weight: bold; color: #ea580c; margin-bottom: 5px; text-transform: uppercase;">'.$type_label.'</div>';
                                $wound_html .= '<img src="'.$wa_img.'" style="width: 100%; height: auto; display: block; border-radius: 2px;">';
                                $wound_html .= '</div>';
                                $wound_html .= '</div>';
                            }
                            
                            $wound_html .= '</div>'; // End Body Flex
                            
                            // [NEW] Procedure / Debridement Section
                            if ( ($wa['debridement_performed'] === 'Yes') || !empty($wa['treatments_provided']) ) {
                                $wound_html .= '<div style="border-top: 1px dashed #e5e7eb; padding: 12px; background: #fdf2f8; font-size: 14px;">';
                                $wound_html .= '<div style="font-weight: bold; color: #be185d; margin-bottom: 4px;">PROCEDURE / INTERVENTION:</div>';
                                
                                $proc_parts = [];
                                if ($wa['debridement_performed'] === 'Yes') {
                                    $d_type = $wa['debridement_type'] ? " ($wa[debridement_type])" : "";
                                    $proc_parts[] = "Debridement performed$d_type.";
                                }
                                if (!empty($wa['treatments_provided'])) {
                                    $proc_parts[] = $wa['treatments_provided'];
                                }
                                
                                $wound_html .= '<div style="color: #444;">' . implode(" ", $proc_parts) . '</div>';
                                $wound_html .= '</div>';
                            }
                            
                            $wound_html .= '</div>'; // End Assessment Box
                            
                            $wound_html .= '</div>'; // End Card
                        }
                        $stmt_waf->close();
                    }
                    
                    if ($final_assess || $wound_html) {
                        $new_live_note .= "<h2 $h2_style>Assessment</h2>";
                        // If there is summary text, render it first
                        if ($final_assess) $new_live_note .= "<p>" . nl2br(htmlspecialchars($final_assess)) . "</p>";
                        // Then render the structured wound cards
                        if ($wound_html) $new_live_note .= $wound_html;
                    }

                    if ($final_proc_note) {
                        // Render Markdown bold (**text**) as HTML bold (<b>text</b>) since the AI Prompt requests bold headers
                        $fmt_proc = htmlspecialchars($final_proc_note);
                        $fmt_proc = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $fmt_proc);
                        $new_live_note .= "<h2 $h2_style>Procedure Note</h2><p>" . nl2br($fmt_proc) . "</p>";
                    }

                    if ($final_plan) $new_live_note .= "<h2 $h2_style>Plan</h2><p>" . nl2br(htmlspecialchars($final_plan)) . "</p>";

                    $sql_note = "INSERT INTO visit_notes (appointment_id, patient_id, user_id, chief_complaint, subjective, objective, assessment, plan, procedure_note, live_note, note_date)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE
                            chief_complaint = VALUES(chief_complaint),
                            subjective = VALUES(subjective),
                            objective = VALUES(objective),
                            assessment = VALUES(assessment),
                            plan = VALUES(plan),
                            procedure_note = VALUES(procedure_note),
                            live_note = VALUES(live_note)";
                    
                    $stmt = $conn->prepare($sql_note);
                    if ($stmt) {
                        $stmt->bind_param("iiisssssss", $appointment_id, $patient_id, $safe_user_id, $final_cc, $final_subj, $final_obj, $final_assess, $final_plan, $final_proc_note, $new_live_note);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // (DB Update logic moved to Line 1835)

                } catch (Exception $e) {
                    // Log error but don't break the JSON response
                    error_log("DB Save Error in AI Companion: " . $e->getMessage());
                }
            }
        } else {
            // Parsing failed. 
            // Do NOT show raw JSON to user. Show a generic message and log the error.
            $reply_text = "I processed that, but I'm having trouble formatting the response. The note should be updated shortly.";
            // Log the raw text for debugging
            error_log("AI JSON Parse Error: " . json_last_error_msg() . " | Raw: " . $raw_text);
            
            // DEBUG: Also write to file
            file_put_contents(__DIR__ . '/debug_ai_parse_error.txt', $raw_text);
        }
    }
    
    // Save AI Reply to History
    $sender = 'ai';
    $sql_hist = "INSERT INTO visit_ai_messages (patient_id, appointment_id, sender, message) VALUES (?, ?, ?, ?)";
    $stmt_hist = $conn->prepare($sql_hist);
    $stmt_hist->bind_param("iiss", $patient_id, $appointment_id, $sender, $reply_text);
    $stmt_hist->execute();
    $stmt_hist->close();

    if ($mode === 'full_visit') {
        $response_payload = [
            "success" => true, 
            "reply" => $reply_text,
            "structured_note" => $structured_note,
            "insight" => $insight_text ?? '',
            "extracted_data" => $extracted,
            "clinical_insights" => $clinical_insights ?? [],
            "thought_process" => $thought_process ?? []
        ];

        // Attach Live Note HTML if generated
        // We rely on $new_live_note variable from the SQL Update block above
        if (isset($new_live_note) && !empty($new_live_note)) {
            $response_payload['live_note_html'] = $new_live_note;
        } else {
             // Fallback: If for some reason $new_live_note wasn't set but we did a full visit update,
             // fetch the latest from DB to ensure frontend is in sync.
             $sql_fetch_latest = "SELECT live_note FROM visit_notes WHERE appointment_id = ?";
             $stmt_fl = $conn->prepare($sql_fetch_latest);
             if ($stmt_fl) {
                 $stmt_fl->bind_param("i", $appointment_id);
                 $stmt_fl->execute();
                 $res_fl = $stmt_fl->get_result();
                 if ($row_fl = $res_fl->fetch_assoc()) {
                     $response_payload['live_note_html'] = $row_fl['live_note'];
                 }
                 $stmt_fl->close();
             }
        }

        echo json_encode($response_payload);
    } else {
        // Even in chat mode, let's include the live_note_html just in case something changed in the background?
        // No, keep chat mode lightweight.
        echo json_encode(["success" => true, "reply" => $reply_text]);
    }
} else {
    // Fallback
    echo json_encode(["success" => false, "message" => "I didn't catch that.", "debug" => $result]);
}
?>