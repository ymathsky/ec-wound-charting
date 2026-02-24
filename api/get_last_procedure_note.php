<?php
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_GET['patient_id']) || !isset($_GET['current_appointment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

$patient_id = intval($_GET['patient_id']);
$current_appt_id = intval($_GET['current_appointment_id']);

try {
    // Find the most recent finalized note for this patient, excluding current visit
    $stmt = $pdo->prepare("
        SELECT procedure_note, note_date 
        FROM visit_notes 
        WHERE patient_id = ? 
        AND appointment_id != ? 
        AND procedure_note IS NOT NULL 
        AND procedure_note != ''
        ORDER BY note_date DESC, created_at DESC 
        LIMIT 1
    ");
    
    $stmt->execute([$patient_id, $current_appt_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($note) {
        echo json_encode([
            'success' => true, 
            'note' => $note['procedure_note'],
            'date' => $note['note_date']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No previous procedure note found.']);
    }

} catch (PDOException $e) {
    error_log("Error fetching last note: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>