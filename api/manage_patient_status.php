<?php
// Filename: api/manage_patient_status.php
// Description: Handles requests to change a patient's status (on_going/done/etc.).

// CRITICAL FIX: Start output buffering immediately to prevent header errors
ob_start();

session_start();
// Set JSON header for output. This should be the FIRST header sent.
header('Content-Type: application/json');

// Function to handle clean exit and JSON output
function exit_with_json($success, $message, $http_code = 200) {
    // Suppress all buffered output that might contain HTML/stray characters
    ob_end_clean();
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Include necessary files (source of premature output is often here)
require_once '../db_connect.php';
require_once '../audit_log_function.php';

// Check for user authentication
if (!isset($_SESSION['ec_user_id'])) {
    exit_with_json(false, 'Authentication required.', 401);
}

$user_id = $_SESSION['ec_user_id'];
$user_name = $_SESSION['ec_full_name'];

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit_with_json(false, 'Invalid request method.', 405);
}

// --- FIX START: Read JSON data from the request body ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data === null) {
    log_audit($conn, $user_id, $user_name, 'ERROR', 'patient_status', null, 'Failed to decode JSON input for status update.');
    exit_with_json(false, 'Invalid JSON input.', 400);
}

// Extract and validate data
$patient_id = $data['patient_id'] ?? null;
$new_status = $data['status'] ?? null;

if (empty($patient_id) || empty($new_status)) {
    log_audit($conn, $user_id, $user_name, 'ERROR', 'patient_status', $patient_id, 'Missing patient_id or status parameter.');
    exit_with_json(false, 'Missing required data (patient ID or status).', 400); // 400 Bad Request
}

$patient_id_safe = (int) $patient_id;
$new_status_safe = $conn->real_escape_string($new_status);

// Validation against known ENUM values (optional, but good practice if DB ENUM isn't active)
// REMOVED 'active'
$allowed_statuses = ['on_going', 'done', 'new', 'discharged'];
if (!in_array(strtolower($new_status_safe), $allowed_statuses)) {
    log_audit($conn, $user_id, $user_name, 'WARNING', 'patient_status', $patient_id_safe, "Attempted to use invalid status: '$new_status_safe'");
    exit_with_json(false, 'Invalid patient status provided.', 400);
}
// --- FIX END ---


// SQL Update Statement
$sql = "UPDATE patients SET status = ?, last_updated_by = ? WHERE patient_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    // Database prepare error
    $db_error = $conn->error;
    log_audit($conn, $user_id, $user_name, 'ERROR', 'patient', $patient_id_safe, "DB Prepare Error (Status Update): " . $db_error);
    exit_with_json(false, "Database prepare error: " . $db_error, 500);
}

$stmt->bind_param("sii", $new_status_safe, $user_id, $patient_id_safe);

if ($stmt->execute()) {
    // Success - check if any rows were actually updated
    if ($stmt->affected_rows > 0) {
        $action_message = ($new_status === 'done') ? 'marked as Done Treatment' : 'status updated';
        log_audit($conn, $user_id, $user_name, 'PATIENT_STATUS_UPDATE', 'patient', $patient_id_safe, "Patient #$patient_id_safe status changed to '$new_status'.");

        exit_with_json(true, "Patient successfully $action_message.");
    } else {
        // No rows updated (patient not found or status already matched)
        exit_with_json(false, 'Patient not found or status already set.', 404);
    }
} else {
    // Database execution error
    $db_error = $stmt->error;
    log_audit($conn, $user_id, $user_name, 'ERROR', 'patient', $patient_id_safe, "DB Execute Error (Status Update): " . $db_error);
    exit_with_json(false, "Database execution error: " . $db_error, 500);
}

// Connection will auto-close.