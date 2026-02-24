<?php
// Filename: api/create_patient.php
// Description: Handles POST request to create a new patient record with minimal, essential fields.

// Start output buffering to prevent premature output/header corruption
ob_start();

session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db_connect.php';
require_once '../audit_log_function.php'; // Assuming this file exists and contains log_audit

// Function to handle clean exit and JSON output
function exit_with_json($success, $message, $http_code = 200) {
    ob_end_clean();
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Function to safely convert empty strings or '0' to NULL (for database foreign keys/optional fields)
function sanitize_optional_field($value) {
    // If value is explicitly null, an empty string, or 0 (which may come from unselected dropdowns)
    if ($value === null || $value === "" || $value === "0") {
        return null;
    }
    return $value;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit_with_json(false, 'Invalid request method.', 405);
}

// Read JSON data from the request body
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

if ($data === null) {
    exit_with_json(false, 'Invalid JSON input.', 400);
}

// --- 1. Validation of Required Fields (Essential Patient Demographics) ---
$required_fields = ['first_name', 'last_name', 'date_of_birth', 'gender'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        exit_with_json(false, "Missing required field: " . str_replace('_', ' ', $field), 400);
    }
}

// --- 2. Data Preparation and Sanitization (Only including fields from the streamlined modal) ---
$first_name = trim($data['first_name']);
$last_name = trim($data['last_name']);
$date_of_birth = $data['date_of_birth'];
$gender = $data['gender'];
$contact_number = $data['contact_number'] ?? null;
$email = $data['email'] ?? null;
$address = $data['address'] ?? null;

// Convert integer fields (foreign keys) that might be empty strings to NULL
$primary_user_id = sanitize_optional_field($data['primary_user_id'] ?? null);
$facility_id = sanitize_optional_field($data['facility_id'] ?? null);

// Default Status (Uses 'new' as the initial state)
$status = 'new';

// --- 3. Generate Patient Code (assuming autoincrement logic for naming) ---
try {
    $code_result = $conn->query("SELECT MAX(patient_id) as max_id FROM patients");
    $next_id = 1;
    if ($code_result && $code_result->num_rows > 0) {
        $row = $code_result->fetch_assoc();
        // Use the next available ID number
        $next_id = ($row['max_id'] ?? 0) + 1;
    }
    // Format the patient code (e.g., EC0015)
    $patient_code = 'EC' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
} catch (Exception $e) {
    // Fallback if code generation fails
    $patient_code = 'EC-' . time();
}

// --- 4. Prepare SQL Statement (11 columns total) ---
$sql = "INSERT INTO patients (
            patient_code, first_name, last_name, date_of_birth, gender, status, 
            primary_user_id, facility_id, contact_number, email, address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 11 columns/placeholders

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $db_error = $conn->error;
    log_audit($conn, $_SESSION['ec_user_id'] ?? null, $_SESSION['ec_full_name'] ?? 'System', 'ERROR', 'patient', null, "DB Prepare Error (Create Patient): " . $db_error);
    exit_with_json(false, "Database error preparing statement: " . $db_error, 500);
}

// Bind Parameters (11 parameters total: 9 strings/chars, 2 integers for FKs)
// Format string: ssssssiisss (s=string, i=integer)
$stmt->bind_param(
    "sssssiissss",
    $patient_code, $first_name, $last_name, $date_of_birth, $gender, $status,
    $primary_user_id, $facility_id, $contact_number, $email, $address
);

// --- 5. Execute and Respond ---
if ($stmt->execute()) {
    $new_patient_id = $conn->insert_id;

    // Log success
    $user_info = $_SESSION['ec_full_name'] ?? 'System';
    log_audit($conn, $_SESSION['ec_user_id'] ?? null, $user_info, 'PATIENT_CREATE', 'patient', $new_patient_id, "New patient '$first_name $last_name' created successfully (Code: $patient_code).");

    exit_with_json(true, "Patient $first_name $last_name successfully registered (Code: $patient_code).", 201);
} else {
    // Database execution error
    $db_error = $stmt->error;
    log_audit($conn, $_SESSION['ec_user_id'] ?? null, $_SESSION['ec_full_name'] ?? 'System', 'ERROR', 'patient', $new_patient_id ?? null, "DB Execute Error (Create Patient): " . $db_error);
    exit_with_json(false, "Database execution error: " . $db_error, 500);
}
// Connection closes automatically.
?>