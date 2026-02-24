<?php
// api/manage_global_settings.php
// Handles CRUD operations for organization-wide visit note settings.

session_start();
header('Content-Type: application/json');

// Check for authentication and Admin role
if (!isset($_SESSION['ec_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}
// Check if the user is an Admin
$is_admin = (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'Admin');

// Include DB connection
require_once '../db_connect.php';

// Function to handle database response output
function respond($success, $message, $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Check request method and action
$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    // --- FETCH GLOBAL SETTINGS ---

    $sql = "SELECT default_template, required_sections, max_length, mandate_clinical_suggestions FROM global_note_settings WHERE id = 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        // Convert TINYINT to PHP boolean/integer
        $settings['mandate_clinical_suggestions'] = (int)$settings['mandate_clinical_suggestions'];
        respond(true, 'Global settings retrieved.', $settings);
    } else {
        // Return defaults if the single row doesn't exist (e.g., initial load failure)
        respond(true, 'Using default global settings.', [
            'default_template' => 'Comprehensive SOAP Note',
            'required_sections' => 'Subjective, Objective, Assessment, Plan',
            'max_length' => 5000,
            'mandate_clinical_suggestions' => 0, // Default to optional
        ]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    // --- SAVE GLOBAL SETTINGS (ADMIN ONLY) ---

    if (!$is_admin) {
        respond(false, 'Access denied. Only Admins can modify global settings.');
    }

    $default_template = trim($_POST['default_template'] ?? '');
    $required_sections = trim($_POST['required_sections'] ?? '');
    $max_length = (int)($_POST['max_length'] ?? 0);
    $mandate_clinical_suggestions = (int)($_POST['mandate_clinical_suggestions'] ?? 0);

    // Basic input validation
    if (empty($default_template) || empty($required_sections) || $max_length <= 0) {
        respond(false, 'Invalid input provided for required fields.');
    }

    // Use prepared statement for the upsert logic
    $sql = "
        INSERT INTO global_note_settings (id, default_template, required_sections, max_length, mandate_clinical_suggestions)
        VALUES (1, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            default_template = VALUES(default_template),
            required_sections = VALUES(required_sections),
            max_length = VALUES(max_length),
            mandate_clinical_suggestions = VALUES(mandate_clinical_suggestions),
            updated_at = CURRENT_TIMESTAMP
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respond(false, 'Database preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("ssii", $default_template, $required_sections, $max_length, $mandate_clinical_suggestions);

    if ($stmt->execute()) {
        respond(true, 'Global settings updated successfully.');
    } else {
        respond(false, 'Database error during update: ' . $stmt->error);
    }

    $stmt->close();

} else {
    respond(false, 'Invalid request or action.');
}

$conn->close();
?>