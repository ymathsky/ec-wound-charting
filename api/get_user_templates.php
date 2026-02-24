<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing user_id']);
    exit;
}

$user_id = intval($_GET['user_id']);
$category = isset($_GET['category']) ? $_GET['category'] : null;

$sql = "SELECT template_id, title, content, category, created_at FROM user_templates WHERE user_id = ?";
if ($category) {
    $sql .= " AND category = ?";
}
$sql .= " ORDER BY title ASC";

$stmt = $conn->prepare($sql);

if ($category) {
    $stmt->bind_param("is", $user_id, $category);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$templates = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode(['success' => true, 'templates' => $templates]);

$stmt->close();
$conn->close();
?>