<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['user_id']) || !isset($data['title']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$user_id = intval($data['user_id']);
$title = trim($data['title']);
$content = $data['content']; // HTML content
$category = isset($data['category']) ? trim($data['category']) : 'General';

if (empty($title) || empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Title and content cannot be empty']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO user_templates (user_id, title, content, category) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $title, $content, $category);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Template saved successfully', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>