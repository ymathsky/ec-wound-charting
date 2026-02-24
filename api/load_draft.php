<?php
// ec/api/load_draft.php
// Load a visit draft for appointment_id and user_id from visit_drafts table.
// Accepts GET or POST. Returns JSON.

header('Content-Type: application/json; charset=utf-8');

$baseInclude = __DIR__ . '/../db_connect.php';
if (!file_exists($baseInclude)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server config not found: db_connect.php']);
    exit;
}
require_once $baseInclude;

// Accept GET or POST
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : (isset($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0);
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : (isset($_POST['user_id']) ? (int)$_POST['user_id'] : null);

if (!$appointment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id']);
    exit;
}

$sql = "SELECT draft_id, appointment_id, user_id, draft_data, autosave_version, last_saved_at
        FROM visit_drafts
        WHERE appointment_id = ? AND user_id <=> ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'error' => $conn->error]);
    exit;
}
$stmt->bind_param('ii', $appointment_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No draft found']);
    exit;
}

// Try decode draft_data JSON
$draft_data = null;
if (!empty($row['draft_data'])) {
    $decoded = json_decode($row['draft_data'], true);
    $draft_data = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $row['draft_data'];
}

echo json_encode([
    'success' => true,
    'draft' => [
        'draft_id' => (int)$row['draft_id'],
        'appointment_id' => (int)$row['appointment_id'],
        'user_id' => $row['user_id'],
        'draft_data' => $draft_data,
        'autosave_version' => (int)$row['autosave_version'],
        'last_saved_at' => $row['last_saved_at']
    ]
]);
exit;