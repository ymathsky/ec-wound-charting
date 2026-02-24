<?php
// ec/api/delete_draft.php
// Delete a visit draft row for appointment_id and user_id from visit_drafts table.
// Accepts JSON body or form POST. Returns JSON.

header('Content-Type: application/json; charset=utf-8');

$baseInclude = __DIR__ . '/../db_connect.php';
if (!file_exists($baseInclude)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server config not found: db_connect.php']);
    exit;
}
require_once $baseInclude;

// Read body (JSON/form)
$raw = file_get_contents('php://input');
$data = null;
if ($raw) {
    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) $data = $d;
}
if ($data === null && !empty($_POST)) $data = $_POST;
if ($data === null) $data = $_GET;

// Normalize
$appointment_id = isset($data['appointment_id']) ? (int)$data['appointment_id'] : 0;
$user_id        = isset($data['user_id']) ? (int)$data['user_id'] : null;

if (!$appointment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id']);
    exit;
}

$sql = "DELETE FROM visit_drafts WHERE appointment_id = ? AND user_id <=> ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('ii', $appointment_id, $user_id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();

if ($deleted > 0) {
    echo json_encode(['success' => true, 'deleted' => $deleted]);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Not found']);
}
exit;