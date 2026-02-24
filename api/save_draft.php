<?php
// ec/api/save_draft.php
// Save/update a visit draft in the visit_drafts table (DB-backed).
// Accepts JSON body or form POST. Returns JSON.
//
// Requires: ec/db_connect.php (defines $conn mysqli)

header('Content-Type: application/json; charset=utf-8');

$baseInclude = __DIR__ . '/../db_connect.php';
if (!file_exists($baseInclude)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server config not found: db_connect.php']);
    exit;
}
require_once $baseInclude;

// Read raw body
$raw = file_get_contents('php://input');
$body = null;

// Try JSON first
if ($raw) {
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $body = $decoded;
    }
}

// Fallback to $_POST if not JSON
if ($body === null && !empty($_POST)) {
    $body = $_POST;
}

// Fallback: try parse_str of raw (in case form-encoded raw)
if ($body === null && $raw) {
    parse_str($raw, $parsed);
    if (!empty($parsed)) $body = $parsed;
}

// Final fallback: empty array
if ($body === null) $body = [];

// Normalize values
$appointment_id = isset($body['appointment_id']) ? (int)$body['appointment_id'] : (isset($body['appointmentId']) ? (int)$body['appointmentId'] : 0);
$user_id        = isset($body['user_id']) ? (int)$body['user_id'] : (isset($body['userId']) ? (int)$body['userId'] : null);
$metadata       = $body['metadata'] ?? null;
$payload        = $body['payload'] ?? null;

// If metadata/payload are strings, attempt to json_decode them
if (is_string($metadata)) {
    $m = json_decode($metadata, true);
    if (json_last_error() === JSON_ERROR_NONE) $metadata = $m;
}
if (is_string($payload)) {
    $p = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE) $payload = $p;
}

// If payload absent, fallback to saving the entire $body (useful for backwards compatibility)
$draft_obj = $payload ?? $body;

// Ensure appointment_id present
if (!$appointment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing appointment_id', 'received' => $body]);
    exit;
}

// JSON-encode draft_data for storage
$draft_json = json_encode($draft_obj, JSON_UNESCAPED_UNICODE);

// autosave version (if supplied)
$autosave_version = 0;
if (is_array($metadata) && isset($metadata['clientDraftVersion'])) {
    $autosave_version = (int)$metadata['clientDraftVersion'];
}

// Prepare ON DUPLICATE KEY UPSERT
// The visit_drafts table should have UNIQUE(appointment_id, user_id)
$sql = "INSERT INTO visit_drafts (appointment_id, user_id, draft_data, autosave_version, last_saved_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            draft_data = VALUES(draft_data),
            autosave_version = VALUES(autosave_version),
            last_saved_at = NOW()";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB prepare failed', 'error' => $conn->error]);
    exit;
}

// Bind parameters (user_id may be null)
if ($user_id === null) {
    // use NULL for user_id
    $stmt->bind_param('isss', $appointment_id, $user_id, $draft_json, $autosave_version);
    // But mysqli bind_param treats null as empty string; instead use dynamic binding via ssi? We'll handle with explicit types:
    $user_id_param = null;
    $stmt->bind_param('isss', $appointment_id, $user_id_param, $draft_json, $autosave_version);
} else {
    $stmt->bind_param('iisi', $appointment_id, $user_id, $draft_json, $autosave_version);
}

$executed = $stmt->execute();
if (!$executed) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB execute failed', 'error' => $stmt->error]);
    $stmt->close();
    exit;
}

// Determine draft_id (either last insert id or select existing)
$draft_id = $stmt->insert_id;
if (!$draft_id) {
    // Possibly an update happened; fetch draft_id via SELECT
    $stmt->close();
    $q = $conn->prepare("SELECT draft_id FROM visit_drafts WHERE appointment_id = ? AND user_id <=> ? LIMIT 1");
    $q->bind_param('ii', $appointment_id, $user_id);
    $q->execute();
    $r = $q->get_result();
    $row = $r->fetch_assoc();
    $draft_id = $row['draft_id'] ?? null;
    $q->close();
} else {
    $stmt->close();
}

// Return success
echo json_encode([
    'success' => true,
    'draft_id' => $draft_id,
    'appointment_id' => $appointment_id,
    'user_id' => $user_id,
    'autosave_version' => $autosave_version
]);
exit;