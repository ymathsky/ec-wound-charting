<?php
// Filename: api/verify_admin_password.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once '../db_connect.php';

// 1. Check user role and ensure they are logged in
$user_id = isset($_SESSION['ec_user_id']) ? $_SESSION['ec_user_id'] : 0;
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';

if ($user_role !== 'admin' || $user_id === 0) {
    http_response_code(403); // Forbidden
    echo json_encode(["message" => "Access denied. You must be an administrator."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

// 2. Validate input
if (empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Password is required for verification."]);
    exit();
}

try {
    // 3. Fetch admin's hashed password from the database
    $sql = "SELECT password_hash FROM users WHERE user_id = ? AND role = 'admin' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // 4. Verify the provided password against the hash
        if (password_verify($data->password, $user['password_hash'])) {
            http_response_code(200);
            echo json_encode(["success" => true, "message" => "Password verified."]);
        } else {
            http_response_code(403); // Forbidden
            echo json_encode(["success" => false, "message" => "Incorrect password."]);
        }
    } else {
        http_response_code(404); // Not Found
        echo json_encode(["message" => "Administrator account not found."]);
    }

} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(["message" => "Unable to verify password.", "error" => $e->getMessage()]);
}

$conn->close();
?>
