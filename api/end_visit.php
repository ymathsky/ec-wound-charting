<?php
// Filename: api/end_visit.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"));

// Basic validation
if (empty($data->appointment_id) || empty($data->password)) {
    http_response_code(400);
    echo json_encode(["message" => "Appointment ID and password are required to end the visit."]);
    exit();
}

$appointment_id = intval($data->appointment_id);
// Fetch current user details from the session for electronic signature
$user_id = isset($_SESSION['ec_user_id']) ? $_SESSION['ec_user_id'] : null;
$user_full_name = isset($_SESSION['ec_full_name']) ? $_SESSION['ec_full_name'] : 'Unknown Clinician';
$submitted_password = $data->password;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["message" => "User session expired or user ID is missing."]);
    exit();
}

try {
    // 1. Verify User Password
    $sql_verify = "SELECT password_hash FROM users WHERE user_id = ? LIMIT 1";
    $stmt_verify = $conn->prepare($sql_verify);
    $stmt_verify->bind_param("i", $user_id);
    $stmt_verify->execute();
    $user_result = $stmt_verify->get_result()->fetch_assoc();
    $stmt_verify->close();

    if (!$user_result || !password_verify($submitted_password, $user_result['password_hash'])) {
        http_response_code(403);
        echo json_encode(["message" => "E-Signature failed. Incorrect password provided."]);
        $conn->close();
        exit();
    }

    // 2. Password Verified: Update the appointment status to 'Completed'
    $sql_update = "UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("i", $appointment_id);

    if ($stmt_update->execute()) {
        http_response_code(200);
        echo json_encode([
            "message" => "Visit successfully completed and electronically signed by {$user_full_name}.",
            "signed_by" => $user_full_name,
            "appointment_id" => $appointment_id
        ]);
    } else {
        throw new Exception("Database update failed: " . $conn->error);
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(["message" => "Unable to complete visit.", "error" => $e->getMessage()]);
}

$conn->close();
?>
