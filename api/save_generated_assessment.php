<?php
// Filename: api/save_generated_assessment.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
session_start();

require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized."));
    exit();
}

$data = json_decode(file_get_contents("php://input"), true);

if (empty($data['patient_id']) || empty($data['appointment_id']) || empty($data['wa_location'])) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Patient ID, Appointment ID, and Location are required."));
    exit();
}

$patient_id = intval($data['patient_id']);
$appointment_id = intval($data['appointment_id']);
$location = trim($data['wa_location']);
$type = trim($data['wa_type'] ?? 'Other');

// 1. Find or Create Wound
$wound_id = 0;
$sql_find = "SELECT wound_id FROM wounds WHERE patient_id = ? AND location = ? LIMIT 1";
$stmt_f = $conn->prepare($sql_find);
if ($stmt_f) {
    $stmt_f->bind_param("is", $patient_id, $location);
    $stmt_f->execute();
    $res_f = $stmt_f->get_result();
    if ($row = $res_f->fetch_assoc()) {
        $wound_id = $row['wound_id'];
    } else {
        // Create new wound
        $sql_ins_w = "INSERT INTO wounds (patient_id, location, wound_type, status, date_onset) VALUES (?, ?, ?, 'Active', CURDATE())";
        $stmt_iw = $conn->prepare($sql_ins_w);
        if ($stmt_iw) {
            $stmt_iw->bind_param("iss", $patient_id, $location, $type);
            $stmt_iw->execute();
            $wound_id = $stmt_iw->insert_id;
            $stmt_iw->close();
        }
    }
    $stmt_f->close();
}

if ($wound_id === 0) {
    echo json_encode(array("success" => false, "message" => "Failed to find or create wound."));
    exit();
}

// 2. Prepare Assessment Data
$length = !empty($data['wa_length']) ? floatval($data['wa_length']) : null;
$width = !empty($data['wa_width']) ? floatval($data['wa_width']) : null;
$depth = !empty($data['wa_depth']) ? floatval($data['wa_depth']) : null;

$granulation = !empty($data['wa_granulation']) ? intval($data['wa_granulation']) : null;
$slough = !empty($data['wa_slough']) ? intval($data['wa_slough']) : null;
$eschar = !empty($data['wa_eschar']) ? floatval($data['wa_eschar']) : null;
$epithelial = !empty($data['wa_epithelial']) ? floatval($data['wa_epithelial']) : null;

$drainage_amt = $data['wa_drainage_amount'] ?? null;
$drainage_type = $data['wa_drainage_type'] ?? null;
$odor = $data['wa_odor'] ?? null;
$pain = !empty($data['wa_pain']) ? intval($data['wa_pain']) : null;

$edges = $data['wa_edges'] ?? null;
$periwound = $data['wa_periwound'] ?? null; // Array or string? Frontend sends array if multiple?
// JS logic: const periwoundOpts = document.querySelectorAll('select[name="wa_periwound"] option:checked');
// const periwound = Array.from(periwoundOpts).map(opt => opt.value).join(', ');
// So it's a string.

$tunneling_loc = $data['wa_tunneling_loc'] ?? null;
$tunneling_depth = !empty($data['wa_tunneling_depth']) ? floatval($data['wa_tunneling_depth']) : null;
$undermining_loc = $data['wa_undermining_loc'] ?? null;
$undermining_depth = !empty($data['wa_undermining_depth']) ? floatval($data['wa_undermining_depth']) : null;

$tunneling_present = ($tunneling_loc || $tunneling_depth) ? 'Yes' : 'No';
$undermining_present = ($undermining_loc || $undermining_depth) ? 'Yes' : 'No';

// Combine edges and periwound into periwound_condition if needed, or store separately if columns exist.
// Table has `periwound_condition` (text).
$periwound_text = "";
if ($edges) $periwound_text .= "Edges: $edges. ";
if ($periwound) $periwound_text .= "Periwound: $periwound.";
$periwound_text = trim($periwound_text);

// 3. Insert Assessment
$sql_ins = "INSERT INTO wound_assessments (
    wound_id, patient_id, appointment_id, assessment_date,
    length_cm, width_cm, depth_cm,
    granulation_percent, slough_percent, eschar_percent, epithelialization_percent,
    exudate_amount, drainage_type, odor_present,
    pain_level,
    periwound_condition,
    tunneling_present, tunneling_locations, tunneling_cm,
    undermining_present, undermining_locations, undermining_cm
) VALUES (
    ?, ?, ?, NOW(),
    ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?,
    ?,
    ?,
    ?, ?, ?,
    ?, ?, ?
)";

$stmt = $conn->prepare($sql_ins);
if ($stmt) {
    $stmt->bind_param("iiidddddddsssisssdssd", 
        $wound_id, $patient_id, $appointment_id,
        $length, $width, $depth,
        $granulation, $slough, $eschar, $epithelial,
        $drainage_amt, $drainage_type, $odor,
        $pain,
        $periwound_text,
        $tunneling_present, $tunneling_loc, $tunneling_depth,
        $undermining_present, $undermining_loc, $undermining_depth
    );
    
    if ($stmt->execute()) {
        echo json_encode(array("success" => true, "message" => "Assessment saved successfully.", "wound_id" => $wound_id));
    } else {
        echo json_encode(array("success" => false, "message" => "Database error: " . $stmt->error));
    }
    $stmt->close();
} else {
    echo json_encode(array("success" => false, "message" => "Prepare failed: " . $conn->error));
}
?>
