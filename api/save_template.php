<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['section_type']) || !isset($input['template_name']) || !isset($input['template_content'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$section_type = $conn->real_escape_string($input['section_type']);
$template_name = $conn->real_escape_string($input['template_name']);
$template_content = $conn->real_escape_string($input['template_content']);

$sql = "INSERT INTO clinician_templates (user_id, section_type, template_name, template_content) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("isss", $user_id, $section_type, $template_name, $template_content);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
