<?php
// Filename: api/create_user.php
// Description: API endpoint to handle user creation via POST request.

header('Content-Type: application/json');
require_once '../db_connect.php'; // Assuming db_connect.php is one level up
require_once '../audit_log_function.php'; // For logging the new user creation

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// Read and decode the JSON payload
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

// Server-side input validation (ensuring keys match the frontend's 'userData' object)
$required_fields = ['full_name', 'email', 'password', 'role'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        // Return the specific message the frontend checks for
        echo json_encode(["success" => false, "message" => "Incomplete user data. All fields are required."]);
        exit();
    }
}

$full_name = $data['full_name'];
$email = $data['email'];
$plain_password = $data['password'];
$role = $data['role'];

// 1. Validate Email Format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["success" => false, "message" => "Invalid email format provided."]);
    exit();
}

// 2. Hash the Password (CRITICAL for security)
$password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

// 3. Prepare SQL statement to check for duplicate user email
$sql_check = "SELECT user_id FROM users WHERE email = ?";
$stmt_check = $conn->prepare($sql_check);
if (!$stmt_check) {
    // Database preparation failed
    echo json_encode(["success" => false, "message" => "Database check error: " . $conn->error]);
    exit();
}
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $stmt_check->close();
    echo json_encode(["success" => false, "message" => "A user with this email already exists."]);
    exit();
}
$stmt_check->close();

// 4. Prepare SQL statement for insertion
$sql_insert = "INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'active')";
$stmt_insert = $conn->prepare($sql_insert);

if (!$stmt_insert) {
    // Database preparation failed
    echo json_encode(["success" => false, "message" => "Database insert error: " . $conn->error]);
    exit();
}

// 5. Bind parameters and execute
$stmt_insert->bind_param("ssss", $full_name, $email, $password_hash, $role);

if ($stmt_insert->execute()) {
    $new_user_id = $conn->insert_id;
    $stmt_insert->close();

    // Log successful creation
    // Assuming the current session user (admin) is performing the action
    $admin_id = $_SESSION['ec_user_id'] ?? 0;
    $admin_name = $_SESSION['ec_full_name'] ?? 'System Admin';
    log_audit($conn, $admin_id, $admin_name, 'CREATE', 'user', $new_user_id, "New user '$full_name' created with role '$role'.");

    echo json_encode(["success" => true, "message" => "User created successfully!", "user_id" => $new_user_id]);
} else {
    $error_message = "Execution failed: " . $stmt_insert->error;
    $stmt_insert->close();
    echo json_encode(["success" => false, "message" => $error_message]);
}

// The database connection from db_connect.php will close automatically if not explicitly closed.
?>
