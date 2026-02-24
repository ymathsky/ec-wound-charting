<?php
/**
 * api/mobile_today_visits.php
 * GET → today's appointments (with patient + visit note status)
 * Headers: Authorization: Bearer <token>
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php'; // sets $mobile_user

$user_id = intval($mobile_user['user_id']);
$role    = $mobile_user['role'];

$where  = "WHERE DATE(a.appointment_date) = CURDATE()";
$params = [];
$types  = '';

if ($role === 'clinician') {
    $where  .= " AND a.user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}

$sql = "SELECT
            a.appointment_id,
            a.patient_id,
            p.first_name,
            p.last_name,
            a.appointment_type,
            a.appointment_date,
            a.status,
            a.is_locked,
            vn.note_id,
            vn.is_signed
        FROM appointments a
        JOIN patients p ON a.patient_id = p.patient_id
        LEFT JOIN visit_notes vn ON vn.appointment_id = a.appointment_id
        $where
        AND a.status NOT IN ('Cancelled', 'No-show')
        ORDER BY a.appointment_date ASC";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'SQL prepare error: ' . $conn->error]);
    exit;
}
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'SQL execute error: ' . $stmt->error]);
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'appointment_id'   => intval($row['appointment_id']),
        'patient_id'       => intval($row['patient_id']),
        'patient_name'     => trim($row['first_name'] . ' ' . $row['last_name']),
        'appointment_type' => $row['appointment_type'] ?? '',
        'appointment_date' => $row['appointment_date'],
        'status'           => $row['status'],
        'is_locked'        => (bool)$row['is_locked'],
        'note_id'          => $row['note_id'] ? intval($row['note_id']) : null,
        'is_signed'        => (bool)$row['is_signed'],
    ];
}
$stmt->close();

echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
