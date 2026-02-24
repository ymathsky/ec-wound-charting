<?php
// Filename: api/get_users.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // Fetch users who are clinicians and are active
    $sql = "SELECT user_id, full_name FROM users WHERE role = 'clinician' AND status = 'active' ORDER BY full_name ASC";
    $result = $conn->query($sql);
    $users = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($users);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching users.", "error" => $e->getMessage()]);
}

$conn->close(); // <<< FIX: Connection explicitly closed
?>

