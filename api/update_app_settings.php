<?php
// ec/api/update_app_settings.php
// API Endpoint to update application-wide settings (e.g., timezone)

// 0. START SESSION: Crucial for accessing user role
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once('../db_connect.php'); // Include database connection

$response = ['success' => false, 'message' => ''];

// 1. Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Only POST requests are allowed.';
    echo json_encode($response);
    exit;
}

// 2. Security Check: ENSURE ONLY ADMINS CAN ACCESS
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    // Session role check failed, this was the source of your 403 error.
    http_response_code(403); // Forbidden
    $response['message'] = 'Access denied. Administrator privileges required.';
    echo json_encode($response);
    exit;
}

// 3. Check Database Connection
if ($conn->connect_error) {
    http_response_code(500);
    $response['message'] = 'Database connection failed. Cannot save settings.';
    echo json_encode($response);
    exit;
}

// 4. Validate and Sanitize Input
$setting_name = $_POST['setting_name'] ?? '';
$setting_value = $_POST[$setting_name] ?? ''; // The value is dynamic, e.g., $_POST['app_timezone']

if (empty($setting_name) || empty($setting_value)) {
    http_response_code(400);
    $response['message'] = 'Missing required setting name or value.';
    echo json_encode($response);
    exit;
}

// Specific validation for timezone
if ($setting_name === 'app_timezone') {
    if (!in_array($setting_value, DateTimeZone::listIdentifiers(DateTimeZone::ALL))) {
        http_response_code(400);
        $response['message'] = 'Invalid timezone value.';
        echo json_encode($response);
        exit;
    }
}

// 5. Save the setting to the 'settings' table (UPSERT logic: UPDATE if exists, INSERT if not)
try {
    // Escape for safe SQL insertion
    $name = $conn->real_escape_string($setting_name);
    $value = $conn->real_escape_string($setting_value);

    // SQL statement for UPSERT (MySQL-specific: INSERT... ON DUPLICATE KEY UPDATE)
    $sql = "INSERT INTO settings (setting_name, setting_value)
            VALUES ('$name', '$value')
            ON DUPLICATE KEY UPDATE
            setting_value = '$value'";

    if ($conn->query($sql)) {
        $response['success'] = true;
        $response['message'] = 'Setting successfully saved. The new timezone will be active on the next page load.';

        // OPTIONAL: Since the timezone is a global setting, we can try to re-apply it instantly
        if ($setting_name === 'app_timezone' && @date_default_timezone_set($setting_value)) {
            // Re-apply it now so the live clock immediately reflects the change
        }

    } else {
        http_response_code(500);
        $response['message'] = 'Database error: ' . $conn->error;
    }

} catch (Exception $e) {
    http_response_code(500);
    $response['message'] = 'An internal server error occurred: ' . $e->getMessage();
}

$conn->close();
echo json_encode($response);
?>