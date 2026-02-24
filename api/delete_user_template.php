<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['template_id']) || !isset($data['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$template_id = intval($data['template_id']);
$user_id = intval($data['user_id']);

// Ensure user owns the template
$stmt = $conn->prepare("DELETE FROM user_templates WHERE template_id = ? AND user_id = ?");
$stmt->bind_param("ii", $template_id, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Template not found or permission denied']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>