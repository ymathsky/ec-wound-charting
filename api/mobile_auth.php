<?php
/**
 * api/mobile_auth.php
 * Mobile JWT Authentication endpoint for EC Wound Charting app.
 * POST  { email, password }  → { success, token, user }
 * GET   (with Bearer token)  → { success, user }  (token verification)
 */
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once '../db_connect.php';
require_once '../audit_log_function.php';

// ── JWT helpers ──────────────────────────────────────────────────────────────
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'ec_wound_jwt_secret_change_in_env');
define('JWT_EXPIRY', 60 * 60 * 24 * 30); // 30 days

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}
function jwt_create(array $payload): string {
    $header  = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode($payload));
    $sig     = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$sig";
}
function jwt_verify(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (!$data || $data['exp'] < time()) return null;
    return $data;
}
function get_bearer_token(): ?string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return $m[1];
    return null;
}
// ─────────────────────────────────────────────────────────────────────────────

// GET → verify token & return user info
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = get_bearer_token();
    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No token provided.']);
        exit;
    }
    $claims = jwt_verify($token);
    if (!$claims) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Token invalid or expired.']);
        exit;
    }
    echo json_encode(['success' => true, 'user' => $claims['user']]);
    exit;
}

// POST → login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$email    = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

// Brute-force check
$client_ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$window_mins = 15;
$rate_limit  = 10;
$rate_stmt   = $conn->prepare(
    "SELECT COUNT(*) as fail_count FROM audit_log
     WHERE action = 'LOGIN_FAIL' AND ip_address = ?
     AND created_at >= NOW() - INTERVAL ? MINUTE"
);
if ($rate_stmt) {
    $rate_stmt->bind_param("si", $client_ip, $window_mins);
    $rate_stmt->execute();
    $fail_count = $rate_stmt->get_result()->fetch_assoc()['fail_count'] ?? 0;
    $rate_stmt->close();
    if ($fail_count >= $rate_limit) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => "Too many attempts. Wait $window_mins minutes."]);
        exit;
    }
}

$stmt = $conn->prepare(
    "SELECT user_id, full_name, password_hash, role, profile_image_url
     FROM users WHERE email = ? AND status = 'active' LIMIT 1"
);
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    log_audit($conn, 0, $email, 'LOGIN_FAIL', 'user', 0, "Mobile: failed login for '$email'.");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}

log_audit($conn, $user['user_id'], $user['full_name'], 'LOGIN', 'user', $user['user_id'], "Mobile login: '{$user['full_name']}'.");

$user_payload = [
    'user_id'   => $user['user_id'],
    'full_name' => $user['full_name'],
    'role'      => $user['role'],
    'avatar'    => $user['profile_image_url'],
];

$token = jwt_create([
    'sub'  => $user['user_id'],
    'iat'  => time(),
    'exp'  => time() + JWT_EXPIRY,
    'user' => $user_payload,
]);

echo json_encode([
    'success' => true,
    'token'   => $token,
    'user'    => $user_payload,
]);
