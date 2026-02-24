<?php
// Filename: api/get_facilities.php

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // Fetch all users with the role 'facility' to act as facilities
    $sql = "SELECT user_id as facility_id, full_name as name FROM users WHERE role = 'facility' AND status = 'active' ORDER BY name ASC";
    $result = $conn->query($sql);

    if ($result === false) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $facilities = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($facilities);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching facilities.", "error" => $e->getMessage()]);
}

$conn->close();
?>
