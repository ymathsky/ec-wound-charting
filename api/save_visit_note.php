<?php
// ec/api/save_visit_note.php
// Save (final) visit note — sanitizes HTML and persists to visit_notes.
// UPDATED: Added Session Security, Transactions, and Input Validation.
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start(); // Start session to access logged-in user data

header('Content-Type: application/json; charset=utf-8');

// includes
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/html_sanitizer.php'; // Ensure this exists

// --- SECURITY CHECK ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please log in.']);
    exit;
}

// Robust request body parsing
$raw = file_get_contents('php://input');
$body = null;
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $body = $decoded;
    }
}
if ($body === null && !empty($_POST)) $body = $_POST;

$appointment_id = isset($body['appointment_id']) ? intval($body['appointment_id']) : 0;
$patient_id     = isset($body['patient_id']) ? intval($body['patient_id']) : 0;
// Use session user_id if not provided or invalid in body, but allow body to override if needed (e.g. admin)
// For safety, we default to the session user if the body user_id is 0/null.
$user_id_input  = !empty($body['user_id']) ? intval($body['user_id']) : 0;
$user_id        = $user_id_input > 0 ? $user_id_input : $_SESSION['ec_user_id'];

if ($appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment_id']);
    exit;
}

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient_id']);
    exit;
}

// Get raw note fields
$chief_complaint_raw = $body['chief_complaint'] ?? '';
$subjective_raw      = $body['subjective']      ?? '';
$objective_raw       = $body['objective']       ?? '';
$assessment_raw      = $body['assessment']      ?? '';
$plan_raw            = $body['plan']            ?? '';
$lab_orders_raw      = $body['lab_orders']      ?? '';
$imaging_orders_raw  = $body['imaging_orders']  ?? '';
$nurse_orders_raw    = $body['skilled_nurse_orders'] ?? '';

// --- NEW: Signature Data ---
$signature_data = $body['signature_data'] ?? null; // Base64 string
$is_signed      = !empty($signature_data) ? 1 : 0;
$signed_at      = $is_signed ? date('Y-m-d H:i:s') : null;

// Sanitize HTML
$chief_complaint_clean = sanitize_html($chief_complaint_raw);
$subjective_clean      = sanitize_html($subjective_raw);
$objective_clean       = sanitize_html($objective_raw);
$assessment_clean      = sanitize_html($assessment_raw);
$plan_clean            = sanitize_html($plan_raw);
$lab_orders_clean      = sanitize_html($lab_orders_raw);
$imaging_orders_clean  = sanitize_html($imaging_orders_raw);
$nurse_orders_clean    = sanitize_html($nurse_orders_raw);

try {
    // Start Transaction
    $conn->begin_transaction();

    // Check if row exists and check status
    $check = $conn->prepare("SELECT note_id, status FROM visit_notes WHERE appointment_id = ?");
    $check->bind_param("i", $appointment_id);
    $check->execute();
    $res = $check->get_result();
    $exists = $res->fetch_assoc();
    $check->close();

    // BLOCK EDIT IF FINALIZED
    if ($exists && $exists['status'] === 'finalized') {
        throw new Exception("This note has been finalized and cannot be edited. Please add an addendum.");
    }

    if ($exists) {
        // UPDATE
        // We construct the query dynamically to only update signature if provided
        $sql = "UPDATE visit_notes SET 
                chief_complaint = ?, 
                subjective = ?, 
                objective = ?, 
                assessment = ?, 
                plan = ?,
                lab_orders = ?,
                imaging_orders = ?,
                skilled_nurse_orders = ?";

        $params = [
            $chief_complaint_clean, 
            $subjective_clean, 
            $objective_clean, 
            $assessment_clean, 
            $plan_clean,
            $lab_orders_clean,
            $imaging_orders_clean,
            $nurse_orders_clean
        ];
        $types = "ssssssss";

        if ($is_signed) {
            $sql .= ", signature_data = ?, signed_at = ?, is_signed = 1, status = 'finalized', finalized_at = NOW(), finalized_by = ?";
            $params[] = $signature_data;
            $params[] = $signed_at;
            $params[] = $user_id;
            $types .= "ssi";
        }

        $sql .= " WHERE appointment_id = ?";
        $params[] = $appointment_id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

    } else {
        // INSERT
        // Added patient_id to columns and values
        $status = $is_signed ? 'finalized' : 'draft';
        $finalized_at = $is_signed ? date('Y-m-d H:i:s') : null;
        $finalized_by = $is_signed ? $user_id : null;

        $sql = "INSERT INTO visit_notes 
                (appointment_id, patient_id, user_id, note_date, chief_complaint, subjective, objective, assessment, plan, lab_orders, imaging_orders, skilled_nurse_orders, signature_data, signed_at, is_signed, status, finalized_at, finalized_by)
                VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        // Corrected bind_param type string: 17 variables
        // i (appt), i (pt), i (user), s (cc), s (subj), s (obj), s (assess), s (plan), s (lab), s (img), s (nurse), s (sig), s (signed_at), i (is_signed), s (status), s (finalized_at), i (finalized_by)
        $stmt->bind_param("iiissssssssssisii",
            $appointment_id,
            $patient_id,
            $user_id,
            $chief_complaint_clean,
            $subjective_clean,
            $objective_clean,
            $assessment_clean,
            $plan_clean,
            $lab_orders_clean,
            $imaging_orders_clean,
            $nurse_orders_clean,
            $signature_data,
            $signed_at,
            $is_signed,
            $status,
            $finalized_at,
            $finalized_by
        );
        $stmt->execute();
        $stmt->close();
    }

    // Remove draft if saved successfully
    if ($user_id > 0) {
        $del = $conn->prepare("DELETE FROM visit_drafts WHERE appointment_id = ? AND user_id = ?");
        $del->bind_param('ii', $appointment_id, $user_id);
        $del->execute();
        $del->close();
    }

    // Update appointment status to 'completed' if signed
    if ($is_signed) {
        $updAppt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
        $updAppt->bind_param('i', $appointment_id);
        $updAppt->execute();
        $updAppt->close();
    }

    // Commit Transaction
    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Note saved successfully.']);

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);

    error_log("Error in save_visit_note.php: " . $e->getMessage() . " on line " . $e->getLine());
}
$conn->close();
?>