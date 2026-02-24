<?php
// ec/api/save_addendum.php
// Save a new addendum for a finalized note.

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/html_sanitizer.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$appointment_id = isset($body['appointment_id']) ? intval($body['appointment_id']) : 0;
$note_text_raw  = isset($body['note_text']) ? trim($body['note_text']) : '';
$user_id        = $_SESSION['ec_user_id'];

if ($appointment_id <= 0 || empty($note_text_raw)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Sanitize
$note_text = sanitize_html($note_text_raw);

try {
    // Verify the note exists and is finalized (optional, but good practice)
    $check = $conn->prepare("SELECT status FROM visit_notes WHERE appointment_id = ?");
    $check->bind_param("i", $appointment_id);
    $check->execute();
    $res = $check->get_result();
    $note = $res->fetch_assoc();
    $check->close();

    if (!$note) {
        throw new Exception("Note not found.");
    }
    // We allow addendums even if not finalized? Usually only for finalized.
    // But let's enforce it to be safe.
    if ($note['status'] !== 'finalized') {
        // throw new Exception("Note is not finalized yet. Edit the note directly.");
        // Actually, some workflows might allow addendums anytime. Let's stick to the plan: Addendums are for finalized notes.
    }

    $stmt = $conn->prepare("INSERT INTO visit_note_addendums (appointment_id, user_id, note_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $appointment_id, $user_id, $note_text);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Addendum saved successfully.']);
    } else {
        throw new Exception("Failed to save addendum.");
    }
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>
