<?php
// Filename: api/save_debridement_narrative.php
// Purpose: Save the custom debridement narrative for a specific wound assessment.

require_once '../db_connect.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['assessment_id']) || !isset($input['narrative'])) {
    echo json_encode(['success' => false, 'message' => 'Missing assessment_id or narrative']);
    exit;
}

$assessment_id = intval($input['assessment_id']);
$narrative = trim($input['narrative']);

// Update the database
$stmt = $conn->prepare("UPDATE wound_assessments SET debridement_narrative = ? WHERE assessment_id = ?");
if ($stmt) {
    $stmt->bind_param("si", $narrative, $assessment_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Narrative saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
}
?>