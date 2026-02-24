<?php
/**
 * api/mobile_middleware.php
 * Include at the top of any mobile API endpoint.
 * Verifies the Bearer JWT and populates $mobile_user.
 *
 * Usage:
 *   require_once '../db_connect.php';
 *   require_once 'mobile_middleware.php';
 *   // $mobile_user['user_id'], $mobile_user['role'] now available
 */

if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', getenv('JWT_SECRET') ?: 'ec_wound_jwt_secret_change_in_env');
}

function _mobile_base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function mobile_verify_token(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true)), '+/', '-_'), '=');
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(_mobile_base64url_decode($payload), true);
    if (!$data || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}

$_auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$_token       = null;
if (preg_match('/Bearer\s+(.+)/i', $_auth_header, $_m)) {
    $_token = $_m[1];
}

if (!$_token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: no token.']);
    exit;
}

$_claims = mobile_verify_token($_token);
if (!$_claims) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized: token invalid or expired.']);
    exit;
}

$mobile_user = $_claims['user'];
