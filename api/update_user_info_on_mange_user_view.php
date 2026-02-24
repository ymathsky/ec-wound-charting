<?php
// ec/api/update_user_info_on_mange_user_view.php
// Description: API endpoint to update an existing user's details (name, email, phone, role, status)
// when submitted from the Manage System Users page modal.
// NOTE: Now correctly handles raw JSON payload from the client.

// Disable display errors to prevent HTML output from PHP warnings/errors,
// ensuring only the final JSON is sent for API consumption.
ini_set('display_errors', 0);
error_reporting(0);

// Set response header to JSON immediately
header('Content-Type: application/json');

// Ensure this is only accessible via POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$conn = null;
$stmt = null; // Initialize $stmt for cleanup

try {
    // 1. Read and decode raw JSON input
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        http_response_code(400);
        throw new Exception('Invalid JSON payload received.');
    }

    // 2. Include the database connection and verify connection object
    require_once '../db_connect.php';

    if (!isset($conn) || $conn->connect_error) {
        error_log('Database connection failed in update_user_info_on_mange_user_view.php: ' . ($conn->connect_error ?? 'Connection object not set.'));
        throw new Exception('Database connection failed.');
    }

    // Start transaction to ensure data integrity
    $conn->begin_transaction();

    // 3. Collect and sanitize input data from JSON ($data)

    // User ID Extraction: CRITICAL FIX - read from $data array
    $user_id = (int)($data['user_id'] ?? 0);

    // Extract fields explicitly sent by the new JS client snippet
    $full_name = trim($data['full_name'] ?? '');
    $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $role = trim($data['role'] ?? '');

    // Optional fields
    $new_password = $data['new_password'] ?? null;

    // NOTE: The client JS snippet does not send phone_number or status/is_active,
    // so we can only update the fields it provides.
    // If you need to update phone_number/status, those fields MUST be added to the JS client payload.

    // 4. Validation
    if ($user_id <= 0) {
        throw new Exception('Invalid user ID provided.');
    }
    if (empty($full_name) || !$email || empty($role)) {
        throw new Exception('Missing required user fields (Full Name, Email, or Role).');
    }

    // 5. Build Dynamic SQL Statement based on data received
    $fields = [];
    $types = '';
    $params = [];

    // Base fields
    $fields[] = "full_name = ?";
    $types .= "s";
    $params[] = $full_name;

    $fields[] = "email = ?";
    $types .= "s";
    $params[] = $email;

    $fields[] = "role = ?";
    $types .= "s";
    $params[] = $role;

    // Handle Optional Password Update
    if (!empty($new_password)) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $fields[] = "password_hash = ?";
        $types .= "s";
        $params[] = $password_hash;
    }

    // CRITICAL: If you need to update phone_number or status, they must be added here
    // by reading them from the $data array, but they are MISSING from the client JS payload.

    if (empty($fields)) {
        throw new Exception('No valid fields provided for update.');
    }

    // Finalize SQL Query
    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?";
    $types .= "i"; // Add integer type for user_id
    $params[] = $user_id; // Add user_id as the final parameter

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("SQL Prepare Failed for user update: " . $conn->error);
        throw new Exception("SQL prepare failed: " . $conn->error);
    }

    // Bind parameters dynamically
    $stmt->bind_param($types, ...$params);

    // 6. Execute the statement
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $conn->commit();
            $response = [
                'success' => true,
                'message' => 'User profile updated successfully.',
                'user_id' => $user_id
            ];
        } else {
            // Check if user exists but no changes were made (keeping this check for robustness)
            $check_sql = "SELECT user_id FROM users WHERE user_id = ?";
            $check_stmt = $conn->prepare($check_sql);

            if (!$check_stmt) {
                error_log("SQL Prepare Failed for check: " . $conn->error);
                throw new Exception("Internal check failed: " . $conn->error);
            }

            $check_stmt->bind_param("i", $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();

            if ($check_result->num_rows === 0) {
                throw new Exception('User ID does not exist.');
            } else {
                $conn->commit();
                $response = [
                    'success' => true,
                    'message' => 'User profile updated successfully, but no changes were applied (data was identical).',
                    'user_id' => $user_id
                ];
            }
            $check_stmt->close();
        }
    } else {
        error_log("Database execution failed: " . $stmt->error);
        throw new Exception('Database execution failed: ' . $stmt->error);
    }

} catch (Exception $e) {
    // 7. Handle errors and rollback transaction
    if ($conn && $conn->ping()) {
        $conn->rollback();
    }

    $statusCode = 400; // Default to Bad Request
    if (strpos($e->getMessage(), 'Database connection failed') !== false) {
        $statusCode = 500;
    }

    http_response_code($statusCode);
    error_log("API Error in update_user_info: " . $e->getMessage());

    $response = [
        'success' => false,
        'message' => 'Update failed. ' . $e->getMessage()
    ];
} finally {
    // 8. Close connection and output response
    if ($stmt) $stmt->close();
    if ($conn && $conn->ping()) {
        $conn->close();
    }
    echo json_encode($response);
}
?>