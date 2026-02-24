<?php
// Filename: api/get_all_cpt_codes_flat.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// This endpoint is for admin management, so a role check is appropriate.
session_start();
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}


try {
    $sql = "SELECT id, code, description, category, fee FROM cpt_codes ORDER BY code ASC";
    $result = $conn->query($sql);
    $codes = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($codes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching CPT codes.", "error" => $e->getMessage()]);
}

$conn->close();
?>
