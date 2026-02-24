<?php
// Filename: ec/patient_portal/api/send_message.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

if (!isset($_SESSION['portal_patient_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['subject']) || empty($input['body'])) {
    http_response_code(400);
    echo json_encode(["message" => "Subject and Message are required."]);
    exit();
}

$patient_id = $_SESSION['portal_patient_id'];
$subject = strip_tags($input['subject']);
$body = strip_tags($input['body']);

try {
    $sql = "INSERT INTO patient_messages (patient_id, direction, subject, body) VALUES (?, 'inbound', ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $patient_id, $subject, $body);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Message sent."]);
    } else {
        throw new Exception("Database insert failed.");
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error."]);
}

$conn->close();
?>