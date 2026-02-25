<?php
/**
 * api/mobile_icd.php
 * GET ?query=  → searches icd10_codes table, returns up to 30 matches
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php';

$query = trim($_GET['query'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$like = '%' . $query . '%';
$stmt = $conn->prepare(
    "SELECT icd10_code AS code, description
     FROM icd10_codes
     WHERE icd10_code LIKE ? OR description LIKE ?
     ORDER BY CASE WHEN icd10_code LIKE ? THEN 0 ELSE 1 END, icd10_code ASC
     LIMIT 30"
);
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'results' => $rows]);
