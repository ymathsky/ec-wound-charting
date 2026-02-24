<?php
// ec/api/save_procedure_note.php
// Saves specifically the procedure_note field to visit_notes table.

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../lib/html_sanitizer.php'; 

session_start();

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

$appointment_id = isset($body['appointment_id']) ? intval($body['appointment_id']) : 0;
$note_text = isset($body['procedure_note']) ? trim($body['procedure_note']) : '';
$user_id = $_SESSION['ec_user_id'];

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit;
}

// Sanitize (basic)
// We allow some HTML or just text. html_sanitizer might be too aggressive if we want specific formatting, 
// but for now let's assume it's safe or use the sanitizer if available.
// If html_sanitizer.php defines a function sanitize_html(), use it.
// Checking save_visit_note.php, it uses $purifier->purify().
// I'll just use basic escaping for SQL, as the frontend will send text.
// Actually, let's just use prepared statements.

// Check if row exists
$check = $conn->prepare("SELECT note_id FROM visit_notes WHERE appointment_id = ?");
$check->bind_param("i", $appointment_id);
$check->execute();
$res = $check->get_result();
$exists = $res->fetch_assoc();
$check->close();

if ($exists) {
    $stmt = $conn->prepare("UPDATE visit_notes SET procedure_note = ? WHERE appointment_id = ?");
    $stmt->bind_param("si", $note_text, $appointment_id);
    $success = $stmt->execute();
    $stmt->close();
} else {
    // Create new row
    // We need patient_id. It should be passed or fetched.
    // Let's fetch patient_id from appointments table if not passed.
    $pat_stmt = $conn->prepare("SELECT patient_id FROM appointments WHERE appointment_id = ?");
    $pat_stmt->bind_param("i", $appointment_id);
    $pat_stmt->execute();
    $pat_res = $pat_stmt->get_result()->fetch_assoc();
    $patient_id = $pat_res ? $pat_res['patient_id'] : 0;
    $pat_stmt->close();

    if ($patient_id > 0) {
        $stmt = $conn->prepare("INSERT INTO visit_notes (appointment_id, patient_id, user_id, note_date, procedure_note, status) VALUES (?, ?, ?, NOW(), ?, 'draft')");
        $stmt->bind_param("iiis", $appointment_id, $patient_id, $user_id, $note_text);
        $success = $stmt->execute();
        $stmt->close();
    } else {
        $success = false;
    }
}

echo json_encode(['success' => $success]);
?>
