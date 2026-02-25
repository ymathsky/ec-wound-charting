<?php
/**
 * api/mobile_templates.php
 * GET ?section_type=  → returns clinician's templates (optionally filtered by section)
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once 'mobile_middleware.php';

$user_id      = intval($mobile_user['user_id']);
$section_type = trim($_GET['section_type'] ?? '');

if ($section_type) {
    $stmt = $conn->prepare(
        "SELECT id, section_type, template_name, template_content
         FROM clinician_templates
         WHERE user_id = ? AND section_type = ?
         ORDER BY template_name ASC"
    );
    $stmt->bind_param("is", $user_id, $section_type);
} else {
    $stmt = $conn->prepare(
        "SELECT id, section_type, template_name, template_content
         FROM clinician_templates
         WHERE user_id = ?
         ORDER BY section_type ASC, template_name ASC"
    );
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$templates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success' => true, 'templates' => $templates]);
