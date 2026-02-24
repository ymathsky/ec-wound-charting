<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$patient_id        = isset($data['patient_id']) ? intval($data['patient_id']) : 0;
$comm_type         = isset($data['communication_type']) ? trim($data['communication_type']) : '';
$parties_involved  = isset($data['parties_involved']) ? trim($data['parties_involved']) : null;
$subject           = isset($data['subject']) ? trim($data['subject']) : '';
$note_body         = isset($data['note_body']) ? trim($data['note_body']) : '';
$follow_up_needed  = isset($data['follow_up_needed']) ? intval($data['follow_up_needed']) : 0;
$follow_up_action  = isset($data['follow_up_action']) ? trim($data['follow_up_action']) : null;
$appointment_id    = isset($data['appointment_id']) ? intval($data['appointment_id']) : null;
$user_id           = $_SESSION['ec_user_id'];

if (!$patient_id || empty($comm_type) || empty($subject) || empty($note_body)) {
    echo json_encode(["success" => false, "message" => "Missing required fields."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO communication_log 
    (patient_id, user_id, appointment_id, communication_type, parties_involved, subject, note_body, follow_up_needed, follow_up_action)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    // SECURITY: Never expose raw DB error to the client
    error_log("save_communication_log prepare failed: " . $conn->error);
    echo json_encode(["success" => false, "message" => "A server error occurred. Please try again."]);
    exit;
}

// iiissssis: patient_id(i), user_id(i), appointment_id(i), comm_type(s), parties_involved(s), subject(s), note_body(s), follow_up_needed(i), follow_up_action(s)
$stmt->bind_param("iiissssis",
    $patient_id,
    $user_id,
    $appointment_id,
    $comm_type,
    $parties_involved,
    $subject,
    $note_body,
    $follow_up_needed,
    $follow_up_action
);

if ($stmt->execute()) {
    $new_id = $conn->insert_id;
    $stmt->close();

    // Return the newly created log entry for instant UI update
    $row_stmt = $conn->prepare("SELECT cl.*, u.full_name as logged_by 
        FROM communication_log cl 
        LEFT JOIN users u ON cl.user_id = u.user_id 
        WHERE cl.log_id = ?");
    $row_stmt->bind_param("i", $new_id);
    $row_stmt->execute();
    $result = $row_stmt->get_result();
    $new_log = $result->fetch_assoc();
    $row_stmt->close();

    echo json_encode(["success" => true, "message" => "Communication logged.", "log" => $new_log]);
} else {
    echo json_encode(["success" => false, "message" => "DB error: " . $stmt->error]);
    $stmt->close();
}
