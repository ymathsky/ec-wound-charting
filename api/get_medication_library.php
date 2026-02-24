<?php
// Filename: api/get_medication_library.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

session_start();
// Allow any authenticated user to access the library (not just admins)
if (!isset($_SESSION['ec_role'])) {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

try {
    $sql = "SELECT id, name, description, default_dosage, default_frequency FROM medications_library ORDER BY name ASC";
    $result = $conn->query($sql);
    $library = $result->fetch_all(MYSQLI_ASSOC);

    http_response_code(200);
    echo json_encode($library);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching the medication library.", "error" => $e->getMessage()]);
}

$conn->close();
?>
