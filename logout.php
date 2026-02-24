<?php
// Filename: logout.php

// CRITICAL FIX: Start output buffering to prevent "Headers already sent" errors
// This captures all output (including stray whitespace) before the redirect header is sent.
if (ob_get_level() === 0) ob_start();

session_start();
// Include connection and audit log *after* starting session
require_once 'db_connect.php';
require_once 'audit_log_function.php';

// --- AUDIT LOG ---
if (isset($_SESSION['ec_user_id'])) {
    $user_id = $_SESSION['ec_user_id'];
    $user_name = $_SESSION['ec_full_name'];

    // Update last_active_at to NULL to mark as offline immediately
    $update_sql = "UPDATE users SET last_active_at = NULL WHERE user_id = ?";
    $stmt = $conn->prepare($update_sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Log the user out action before destroying the session
    log_audit($conn, $user_id, $user_name, 'LOGOUT', 'user', $user_id, "User '$user_name' logged out.");
}
// --- END AUDIT LOG ---

// Unset all of the session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to login page
header("Location: login.php");

// CRITICAL FIX: End buffering and exit to ensure the header is processed immediately
ob_end_flush();
exit();
?>
