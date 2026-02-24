<?php
/**
 * api/mobile_patients.php
 * GET  → list of patients for the logged-in user
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
$search  = trim($_GET['search'] ?? '');
$limit   = min(intval($_GET['limit'] ?? 50), 100);
$offset  = intval($_GET['offset'] ?? 0);

$where  = "WHERE p.status = 'active'";
$params = [];
$types  = '';

if ($role === 'clinician') {
    $where  .= " AND p.primary_user_id = ?";
    $params[] = $user_id;
    $types   .= 'i';
}

if ($search !== '') {
    $like     = "%$search%";
    $where   .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.date_of_birth LIKE ?)";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

$sql  = "SELECT p.patient_id, p.first_name, p.last_name, p.date_of_birth, p.gender,
                p.primary_diagnosis, p.profile_image_url,
                MAX(a.appointment_date) AS last_visit
         FROM patients p
         LEFT JOIN appointments a ON a.patient_id = p.patient_id AND a.status = 'completed'
         $where
         GROUP BY p.patient_id
         ORDER BY p.last_name ASC
         LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
