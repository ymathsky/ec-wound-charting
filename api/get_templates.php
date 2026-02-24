<?php
session_start();
require_once '../db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['ec_user_id'];
$section_type = isset($_GET['section_type']) ? $_GET['section_type'] : null;

$sql = "SELECT id, section_type, template_name, template_content FROM clinician_templates WHERE user_id = ?";
$params = ["i", $user_id];

if ($section_type) {
    $sql .= " AND section_type = ?";
    $params[0] .= "s";
    $params[] = $section_type;
}

$sql .= " ORDER BY template_name ASC";

$stmt = $conn->prepare($sql);
// Dynamic binding
$bind_names[] = $params[0];
for ($i = 1; $i < count($params); $i++) {
    $bind_name = 'bind' . $i;
    $$bind_name = $params[$i];
    $bind_names[] = &$$bind_name;
}
call_user_func_array(array($stmt, 'bind_param'), $bind_names);

$stmt->execute();
$result = $stmt->get_result();

$templates = [];
while ($row = $result->fetch_assoc()) {
    $templates[] = $row;
}

echo json_encode(['success' => true, 'templates' => $templates]);

$stmt->close();
$conn->close();
?>
