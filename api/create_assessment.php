<?php
// Filename: api/create_assessment.php
// UPDATED: Added new assessment fields (Risk, Nutrition, Scores, etc.)

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
session_start();

require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized."));
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// --- START OF FIX: VALIDATION & DATA EXTRACTION ---
// Add validation for all required IDs
if (empty($data->wound_id) || empty($data->patient_id) || empty($data->appointment_id) || empty($data->assessment_date)) {
    http_response_code(400);
    echo json_encode(array("message" => "Wound ID, Patient ID, Appointment ID, and Assessment Date are required."));
    exit();
}

// Get required IDs
$assessment_id = isset($data->assessment_id) ? intval($data->assessment_id) : null;
$wound_id = intval($data->wound_id);
$patient_id = intval($data->patient_id);
$appointment_id = intval($data->appointment_id);
$assessment_date = htmlspecialchars(strip_tags($data->assessment_date));

// --- Authorization Check ---
if ($_SESSION['ec_role'] === 'facility') {
    $sql_check = "SELECT p.facility_id 
                  FROM wounds w 
                  JOIN patients p ON w.patient_id = p.patient_id 
                  WHERE w.wound_id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $wound_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $check_data = $result_check->fetch_assoc();
    $stmt_check->close();

    if (!$check_data || $check_data['facility_id'] != $_SESSION['ec_user_id']) {
        http_response_code(403);
        echo json_encode(array("message" => "Forbidden. You do not have access to this wound."));
        exit();
    }
}

$is_update = !empty($assessment_id);

// --- Extract and Pre-process Data ---
// Helper function for sanitization
function sanitize($input) {
    return $input !== null ? htmlspecialchars(strip_tags($input)) : null;
}

// Numeric fields (using ?? null to safely handle undefined properties)
$length_cm = $data->length_cm ?? null;
$width_cm = $data->width_cm ?? null;
$depth_cm = $data->depth_cm ?? null;
$granulation_percent = $data->granulation_percent ?? null;
$slough_percent = $data->slough_percent ?? null;
$pain_level = isset($data->pain_level) ? intval($data->pain_level) : null;

// String fields (using ?? null to safely handle undefined properties)
$granulation_color = sanitize($data->granulation_color ?? null);
$granulation_coverage = sanitize($data->granulation_coverage ?? null);
$drainage_type = sanitize($data->drainage_type ?? null);
$exudate_amount = sanitize($data->exudate_amount ?? null);
$odor_present = sanitize($data->odor_present ?? 'No');
$debridement_performed = sanitize($data->debridement_performed ?? 'No');
$debridement_type = sanitize($data->debridement_type ?? null);
$treatments_provided = sanitize($data->treatments_provided ?? null);
$tunneling_present = sanitize($data->tunneling_present ?? 'No');
$undermining_present = sanitize($data->undermining_present ?? 'No');

// New Fields Extraction
$risk_factors = sanitize($data->risk_factors ?? null);
$nutritional_status = sanitize($data->nutritional_status ?? null);
$braden_score = isset($data->braden_score) ? intval($data->braden_score) : null;
$push_score = isset($data->push_score) ? intval($data->push_score) : null;
$pre_debridement_notes = sanitize($data->pre_debridement_notes ?? null);
$medical_necessity = sanitize($data->medical_necessity ?? null);
$dvt_edema_notes = sanitize($data->dvt_edema_notes ?? null);

// JSON/Array fields (converted to JSON string)
// Note: We don't strip tags from JSON encoded strings as it might break structure, 
// but we should ensure the *values* inside are safe if they are user input. 
// For now, assuming these are structured data from select/checkboxes.
$periwound_condition = !empty($data->periwound_condition) ? json_encode($data->periwound_condition) : null;
$signs_of_infection = !empty($data->signs_of_infection) ? json_encode($data->signs_of_infection) : null;
$tunneling_locations = !empty($data->tunneling_locations) ? json_encode($data->tunneling_locations) : null;
$undermining_locations = !empty($data->undermining_locations) ? json_encode($data->undermining_locations) : null;
$exposed_structures = !empty($data->exposed_structures) ? json_encode($data->exposed_structures) : null;

// --- END OF DATA EXTRACTION ---

if ($is_update) {
    $sql = "UPDATE wound_assessments SET 
        assessment_date = ?, 
        pain_level = ?,
        length_cm = ?, 
        width_cm = ?, 
        depth_cm = ?,
        granulation_percent = ?, 
        slough_percent = ?,
        granulation_color = ?, 
        granulation_coverage = ?,
        drainage_type = ?, 
        exudate_amount = ?, 
        odor_present = ?,
        periwound_condition = ?, 
        signs_of_infection = ?,
        debridement_performed = ?, 
        debridement_type = ?, 
        treatments_provided = ?,
        tunneling_present = ?,
        tunneling_locations = ?,
        undermining_present = ?,
        undermining_locations = ?,
        exposed_structures = ?,
        risk_factors = ?,
        nutritional_status = ?,
        braden_score = ?,
        push_score = ?,
        pre_debridement_notes = ?,
        medical_necessity = ?,
        dvt_edema_notes = ?
        WHERE assessment_id = ?";
} else {
    $sql = "INSERT INTO wound_assessments (
        wound_id, patient_id, appointment_id, assessment_date, 
        pain_level,
        length_cm, width_cm, depth_cm, 
        granulation_percent, slough_percent, granulation_color, granulation_coverage,
        drainage_type, exudate_amount, odor_present,
        periwound_condition, signs_of_infection,
        debridement_performed, debridement_type, treatments_provided,
        tunneling_present, tunneling_locations, undermining_present, undermining_locations,
        exposed_structures, risk_factors, nutritional_status, braden_score, push_score,
        pre_debridement_notes, medical_necessity, dvt_edema_notes
    ) VALUES (
        ?, ?, ?, ?, 
        ?,
        ?, ?, ?, 
        ?, ?, ?, ?, 
        ?, ?, ?, 
        ?, ?, 
        ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?
    )";
}

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    echo json_encode(array("message" => "Database prepare statement failed: " . $conn->error));
    exit();
}


if ($is_update) {
    // 29 parameters: 1s (date) + 5d (measurements) + 14s (old strings) + 8 (new fields: 3s, 2i, 3s) + 1i (id)
    // Old types: sdddddssssssssssssss (20)
    // New types: sssiisss (8)
    // Total types: sdddddsssssssssssssssssiisssi
    
    $types = "sidddddsssssssssssssssssiisssi";
    $vars = [
        $assessment_date,
        $pain_level,
        $length_cm, $width_cm, $depth_cm,
        $granulation_percent, $slough_percent,
        $granulation_color, $granulation_coverage,
        $drainage_type, $exudate_amount, $odor_present,
        $periwound_condition, $signs_of_infection,
        $debridement_performed, $debridement_type, $treatments_provided,
        $tunneling_present, $tunneling_locations,
        $undermining_present, $undermining_locations,
        $exposed_structures, $risk_factors, $nutritional_status,
        $braden_score, $push_score,
        $pre_debridement_notes, $medical_necessity, $dvt_edema_notes,
        $assessment_id
    ];
} else {
    // 31 parameters: 3i (IDs) + 1s (Date) + 5d (measurements) + 14s (old strings) + 8 (new fields)
    // Old types: iiisdddddssssssssssssss (23)
    // New types: sssiisss (8)
    // Total types: iiisdddddsssssssssssssssssiisss
    
    $types = "iiisidddddsssssssssssssssssiisss";

    $vars = [
        $wound_id, $patient_id, $appointment_id, $assessment_date,
        $pain_level,
        $length_cm, $width_cm, $depth_cm,
        $granulation_percent, $slough_percent, $granulation_color, $granulation_coverage,
        $drainage_type, $exudate_amount, $odor_present,
        $periwound_condition, $signs_of_infection,
        $debridement_performed, $debridement_type, $treatments_provided,
        $tunneling_present, $tunneling_locations, $undermining_present, $undermining_locations,
        $exposed_structures, $risk_factors, $nutritional_status,
        $braden_score, $push_score,
        $pre_debridement_notes, $medical_necessity, $dvt_edema_notes
    ];
}

// Dynamically call bind_param using variable argument list
$stmt->bind_param($types, ...$vars);


if ($stmt->execute()) {
    $new_assessment_id = $is_update ? $assessment_id : $conn->insert_id;

    http_response_code(200);
    echo json_encode(array(
        "success" => true,
        "message" => "Assessment " . ($is_update ? "updated" : "created") . " successfully.",
        "assessment_id" => $new_assessment_id
    ));
} else {
    http_response_code(500);
    echo json_encode(array("message" => "Assessment save failed: " . $stmt->error));
}

$stmt->close();
$conn->close();

?>