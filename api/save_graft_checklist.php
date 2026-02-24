<?php
// Filename: api/save_graft_checklist.php
// Purpose: Save Shoreline Skin Graft Checklist data (extended for Audit Compliance).
// UPDATED: Fixed Session Authentication check to look for 'ec_user_id' or 'ec_role'.

header("Content-Type: application/json");
require_once '../db_connect.php';

// Ensure session is started to access $_SESSION variables
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check (Fixed) ---
// Check for ec_user_id (common in your app) OR user_id OR ec_role
$is_logged_in = isset($_SESSION['ec_user_id']) || isset($_SESSION['user_id']) || isset($_SESSION['ec_role']);

if (!$is_logged_in) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Session not found.']);
    exit;
}

// --- 2. Get Input Data ---
$input = json_decode(file_get_contents("php://input"), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
    exit;
}

$appointment_id = intval($input['appointment_id'] ?? 0);
$wound_id = intval($input['wound_id'] ?? 0);

if ($appointment_id <= 0 || $wound_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing Appointment or Wound ID.']);
    exit;
}

// --- 3. Prepare Fields ---
// Map all fields including new Audit Checklist items
$fields = [
    // General & Exclusion
    'graft_check_no_infection' => intval($input['graft_check_no_infection'] ?? 0),
    'graft_check_osteo' => intval($input['graft_check_osteo'] ?? 0),
    'graft_check_vasculitis' => intval($input['graft_check_vasculitis'] ?? 0),
    'graft_check_charcot' => intval($input['graft_check_charcot'] ?? 0),
    'graft_check_smoking' => intval($input['graft_check_smoking'] ?? 0),

    // Conservative Care
    'graft_conservative_treatments' => $input['graft_conservative_treatments'] ?? '',
    'graft_conservative_duration' => $input['graft_conservative_duration'] ?? '',

    // Wound Doc
    'graft_check_size_requirement' => intval($input['graft_check_size_requirement'] ?? 0),
    'graft_check_no_necrotic' => intval($input['graft_check_no_necrotic'] ?? 0),
    'graft_check_bone' => intval($input['graft_check_bone'] ?? 0),
    'graft_wound_thickness' => $input['graft_wound_thickness'] ?? '',
    'graft_check_debridement_photos' => intval($input['graft_check_debridement_photos'] ?? 0),
    'graft_check_historical_photos' => intval($input['graft_check_historical_photos'] ?? 0),

    // Product Info
    'graft_product_name' => $input['graft_product_name'] ?? '',
    'graft_treatment_goals' => $input['graft_treatment_goals'] ?? '',
    'graft_serial_number' => $input['graft_serial_number'] ?? '',
    'graft_lot_number' => $input['graft_lot_number'] ?? '',
    'graft_expiry_date' => !empty($input['graft_expiry_date']) ? $input['graft_expiry_date'] : null,

    // Application & Billing
    'graft_application_number' => intval($input['graft_application_number'] ?? 0),
    'graft_sqcm_used' => floatval($input['graft_sqcm_used'] ?? 0.0),
    'graft_sqcm_discarded' => floatval($input['graft_sqcm_discarded'] ?? 0.0),
    'graft_check_jw_modifier' => intval($input['graft_check_jw_modifier'] ?? 0),
    'graft_cpt_code' => $input['graft_cpt_code'] ?? '',
    'graft_q_code' => $input['graft_q_code'] ?? '',
    'graft_discard_justification' => $input['graft_discard_justification'] ?? '',

    // New fields from UI update
    'graft_check_meds' => intval($input['graft_check_meds'] ?? 0),
    'graft_check_dx' => intval($input['graft_check_dx'] ?? 0),
    'graft_check_offloading' => intval($input['graft_check_offloading'] ?? 0),
    'graft_check_compression' => intval($input['graft_check_compression'] ?? 0),
    'graft_abi_result' => $input['graft_abi_result'] ?? '',
    'graft_vlu_duplex' => $input['graft_vlu_duplex'] ?? '',
    'graft_a1c' => $input['graft_a1c'] ?? '',
    'graft_check_location' => intval($input['graft_check_location'] ?? 0)
];

try {
    // Check if assessment exists
    $check_stmt = $conn->prepare("SELECT assessment_id FROM wound_assessments WHERE appointment_id = ? AND wound_id = ?");
    $check_stmt->bind_param("ii", $appointment_id, $wound_id);
    $check_stmt->execute();
    $check_res = $check_stmt->get_result();

    if ($check_res->num_rows > 0) {
        // UPDATE
        $row = $check_res->fetch_assoc();
        $assessment_id = $row['assessment_id'];

        $sql = "UPDATE wound_assessments SET 
                graft_check_no_infection=?, graft_check_osteo=?, graft_check_vasculitis=?, graft_check_charcot=?, graft_check_smoking=?,
                graft_conservative_treatments=?, graft_conservative_duration=?,
                graft_check_size_requirement=?, graft_check_no_necrotic=?, graft_check_bone=?, graft_wound_thickness=?,
                graft_check_debridement_photos=?, graft_check_historical_photos=?,
                graft_product_name=?, graft_treatment_goals=?, graft_serial_number=?, graft_lot_number=?, graft_expiry_date=?,
                graft_application_number=?, graft_sqcm_used=?, graft_sqcm_discarded=?, graft_check_jw_modifier=?,
                graft_cpt_code=?, graft_q_code=?, graft_discard_justification=?,
                graft_check_meds=?, graft_check_dx=?, graft_check_offloading=?, graft_check_compression=?, 
                graft_abi_result=?, graft_vlu_duplex=?, graft_a1c=?, graft_check_location=?
                WHERE assessment_id = ?";

        $stmt = $conn->prepare($sql);
        // Count: 25 original + 8 new = 33 params + 1 ID = 34 types
        // types: iiiiisssiissssssssiddisssiiiissssi
        $types = "iiiiisssiissssssssiddisssiiiissssi";

        $stmt->bind_param(
            $types,
            $fields['graft_check_no_infection'], $fields['graft_check_osteo'], $fields['graft_check_vasculitis'], $fields['graft_check_charcot'], $fields['graft_check_smoking'],
            $fields['graft_conservative_treatments'], $fields['graft_conservative_duration'],
            $fields['graft_check_size_requirement'], $fields['graft_check_no_necrotic'], $fields['graft_check_bone'], $fields['graft_wound_thickness'],
            $fields['graft_check_debridement_photos'], $fields['graft_check_historical_photos'],
            $fields['graft_product_name'], $fields['graft_treatment_goals'], $fields['graft_serial_number'], $fields['graft_lot_number'], $fields['graft_expiry_date'],
            $fields['graft_application_number'], $fields['graft_sqcm_used'], $fields['graft_sqcm_discarded'], $fields['graft_check_jw_modifier'],
            $fields['graft_cpt_code'], $fields['graft_q_code'], $fields['graft_discard_justification'],
            $fields['graft_check_meds'], $fields['graft_check_dx'], $fields['graft_check_offloading'], $fields['graft_check_compression'],
            $fields['graft_abi_result'], $fields['graft_vlu_duplex'], $fields['graft_a1c'], $fields['graft_check_location'],
            $assessment_id
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Audit checklist updated successfully.']);
        } else {
            throw new Exception("Update failed: " . $stmt->error);
        }
        $stmt->close();

    } else {
        // INSERT
        $date = date('Y-m-d');
        $sql = "INSERT INTO wound_assessments (
                appointment_id, wound_id, assessment_date,
                graft_check_no_infection, graft_check_osteo, graft_check_vasculitis, graft_check_charcot, graft_check_smoking,
                graft_conservative_treatments, graft_conservative_duration,
                graft_check_size_requirement, graft_check_no_necrotic, graft_check_bone, graft_wound_thickness,
                graft_check_debridement_photos, graft_check_historical_photos,
                graft_product_name, graft_treatment_goals, graft_serial_number, graft_lot_number, graft_expiry_date,
                graft_application_number, graft_sqcm_used, graft_sqcm_discarded, graft_check_jw_modifier,
                graft_cpt_code, graft_q_code, graft_discard_justification,
                graft_check_meds, graft_check_dx, graft_check_offloading, graft_check_compression, 
                graft_abi_result, graft_vlu_duplex, graft_a1c, graft_check_location
            ) VALUES (
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, 
                ?, ?, ?, ?
            )";

        $stmt = $conn->prepare($sql);
        // types: iis + 33 fields = iisiiiiisssiissssssssiddisssiiiissss
        $types = "iisiiiiisssiissssssssiddisssiiiissss";

        $stmt->bind_param(
            $types,
            $appointment_id, $wound_id, $date,
            $fields['graft_check_no_infection'], $fields['graft_check_osteo'], $fields['graft_check_vasculitis'], $fields['graft_check_charcot'], $fields['graft_check_smoking'],
            $fields['graft_conservative_treatments'], $fields['graft_conservative_duration'],
            $fields['graft_check_size_requirement'], $fields['graft_check_no_necrotic'], $fields['graft_check_bone'], $fields['graft_wound_thickness'],
            $fields['graft_check_debridement_photos'], $fields['graft_check_historical_photos'],
            $fields['graft_product_name'], $fields['graft_treatment_goals'], $fields['graft_serial_number'], $fields['graft_lot_number'], $fields['graft_expiry_date'],
            $fields['graft_application_number'], $fields['graft_sqcm_used'], $fields['graft_sqcm_discarded'], $fields['graft_check_jw_modifier'],
            $fields['graft_cpt_code'], $fields['graft_q_code'], $fields['graft_discard_justification'],
            $fields['graft_check_meds'], $fields['graft_check_dx'], $fields['graft_check_offloading'], $fields['graft_check_compression'],
            $fields['graft_abi_result'], $fields['graft_vlu_duplex'], $fields['graft_a1c'], $fields['graft_check_location']
        );

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Audit checklist created successfully.']);
        } else {
            throw new Exception("Insert failed: " . $stmt->error);
        }
        $stmt->close();
    }
    $check_stmt->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}

$conn->close();
?>