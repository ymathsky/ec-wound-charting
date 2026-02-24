<?php
// Filename: api/get_audit_log.php
// API endpoint to fetch audit log data. Admin-only.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Admin-only
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

try {
    // --- UPDATED: Selecting the new `user_name` column to match your database change ---
    $sql = "SELECT log_id, user_id, user_name, action, entity_type, entity_id, details, ip_address, timestamp 
            FROM audit_log 
            ORDER BY timestamp DESC 
            LIMIT 500";

    $result = $conn->query($sql);

    $logs = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    http_response_code(200);
    echo json_encode($logs);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred.", "error" => $e->getMessage()]);
}

$conn->close();
?>

