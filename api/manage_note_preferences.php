<?php
// api/manage_note_preferences.php
// Handles CRUD operations for user-specific custom note preferences.

session_start();
header('Content-Type: application/json');

// Check for authentication
if (!isset($_SESSION['ec_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

// Include DB connection
require_once '../db_connect.php';

// Function to handle database response output
function respond($success, $message, $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit();
}

// Check request method and action
$action = $_REQUEST['action'] ?? '';
$requested_user_id = $_REQUEST['user_id'] ?? null;

// Security check: Ensure the requested user_id matches the session user_id
$user_id = $_SESSION['ec_user_id'];

// Crucial Security Check: Prevents a malicious user from reading/writing
// another user's preferences by manipulating the 'user_id' parameter in the request.
if ($requested_user_id != $user_id) {
    respond(false, 'Unauthorized access to user preferences.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
    // --- FETCH CUSTOM SETTINGS ---

    // NOTE: If your user_note_preferences table doesn't have hpi_source yet,
    // you must update its schema.
    $sql = "SELECT auto_populate_vitals, auto_populate_hpi, auto_populate_meds, hpi_source FROM user_note_preferences WHERE user_id = ?";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        respond(false, 'Database preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $settings = $result->fetch_assoc();
        respond(true, 'Custom preferences retrieved.', $settings);
    } else {
        // Return default values if no record exists yet
        respond(true, 'Using default user preferences (no record found).', [
            'auto_populate_vitals' => 0,
            'auto_populate_hpi' => 0,
            'auto_populate_meds' => 0,
            'hpi_source' => 'structured', // Default HPI source
        ]);
    }
    $stmt->close();

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save') {
    // --- SAVE CUSTOM SETTINGS ---

    $vitals = (int)($_POST['auto_populate_vitals'] ?? 0);
    $hpi = (int)($_POST['auto_populate_hpi'] ?? 0);
    $meds = (int)($_POST['auto_populate_meds'] ?? 0);
    $hpi_source = trim($_POST['hpi_source'] ?? 'structured');

    // Use INSERT...ON DUPLICATE KEY UPDATE logic (upsert)
    // NOTE: This assumes hpi_source column exists in user_note_preferences
    $sql = "
        INSERT INTO user_note_preferences (user_id, auto_populate_vitals, auto_populate_hpi, auto_populate_meds, hpi_source)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            auto_populate_vitals = VALUES(auto_populate_vitals),
            auto_populate_hpi = VALUES(auto_populate_hpi),
            auto_populate_meds = VALUES(auto_populate_meds),
            hpi_source = VALUES(hpi_source)
    ";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        respond(false, 'Database preparation failed: ' . $conn->error);
    }

    $stmt->bind_param("iiiis", $user_id, $vitals, $hpi, $meds, $hpi_source);

    if ($stmt->execute()) {
        respond(true, 'Personal preferences updated successfully.');
    } else {
        respond(false, 'Database error during update: ' . $stmt->error);
    }

    $stmt->close();
} else {
    respond(false, 'Invalid request or action.');
}

$conn->close();
?>