<?php
// Filename: ec/api/generate_patient_summary.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Session expired."]);
    exit();
}

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$force_refresh = isset($_GET['force_refresh']) && $_GET['force_refresh'] === 'true';

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid patient ID."]);
    exit();
}

try {
    // 1. Check Cache (unless forced)
    if (!$force_refresh) {
        $stmt = $conn->prepare("SELECT summary_text, generated_at FROM patient_summaries WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Cache Hit!
            // Optional: Add logic here to check if cache is too old (e.g., > 24 hours)
            // For now, we return the cache and let the user decide to regenerate.
            echo json_encode([
                "insights" => $row['summary_text'],
                "cached" => true,
                "generated_at" => $row['generated_at']
            ]);
            $stmt->close();
            $conn->close();
            exit();
        }
        $stmt->close();
    }

    // 2. If No Cache or Forced: Gather Clinical Data for AI
    // (Fetching logic remains similar to before, abbreviated for clarity)

    // Fetch Demographics
    $pat_sql = "SELECT * FROM patients WHERE patient_id = ?";
    $stmt = $conn->prepare($pat_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Fetch Recent Wounds
    $wounds_sql = "SELECT location, wound_type, status, date_onset FROM wounds WHERE patient_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($wounds_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $wounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Fetch Recent Notes (Limit 3)
    $notes_sql = "SELECT note_date, assessment, plan FROM visit_notes WHERE patient_id = ? ORDER BY note_date DESC LIMIT 3";
    $stmt = $conn->prepare($notes_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Construct Prompt
    $prompt = "Patient: {$patient['first_name']} {$patient['last_name']} (Age: " . date_diff(date_create($patient['date_of_birth']), date_create('today'))->y . ").\n";
    $prompt .= "Active Wounds: " . count($wounds) . "\n";
    foreach($wounds as $w) {
        $prompt .= "- {$w['location']} ({$w['wound_type']})\n";
    }
    $prompt .= "Recent Clinical Notes Summary:\n";
    foreach($notes as $n) {
        $prompt .= "- {$n['note_date']}: {$n['assessment']}\n";
    }
    $prompt .= "\nBased on this data, provide a concise bulleted list of 3-5 key clinical insights regarding healing progress, risk factors, or suggested interventions. Do not use markdown formatting like bolding.";

    // 3. Call AI Service (Mocked for this example - replace with actual Curl call to OpenAI/Gemini)
    // In a real deployment, this would be your curl_exec() block.

    // simulating AI delay
    sleep(1);
    $generated_text = "- Wound on {$wounds[0]['location']} shows slow progression; consider re-evaluating offloading strategy.\n- Patient age and history suggest high risk for recurrence.\n- Recent notes indicate good adherence to dressing changes.";

    // 4. Save to Cache (Upsert)
    $upsert_sql = "INSERT INTO patient_summaries (patient_id, summary_text, generated_at) 
                   VALUES (?, ?, NOW()) 
                   ON DUPLICATE KEY UPDATE summary_text = VALUES(summary_text), generated_at = NOW()";

    $stmt = $conn->prepare($upsert_sql);
    if (!$stmt) throw new Exception("Cache save failed: " . $conn->error);

    $stmt->bind_param("is", $patient_id, $generated_text);
    $stmt->execute();
    $stmt->close();

    // 5. Return Result
    echo json_encode([
        "insights" => $generated_text,
        "cached" => false,
        "generated_at" => date("Y-m-d H:i:s")
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Error generating summary.", "error" => $e->getMessage()]);
}

$conn->close();
?>