<?php
// Filename: ec/patient_portal/api/auth.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);

if (empty($input['last_name']) || empty($input['date_of_birth'])) {
    http_response_code(400);
    echo json_encode(["message" => "Last Name and Date of Birth are required."]);
    exit();
}

$last_name = strip_tags(trim($input['last_name']));
$dob = strip_tags(trim($input['date_of_birth']));

try {
    // Find patient matching Last Name and DOB (Case insensitive last name)
    $sql = "SELECT patient_id, first_name, last_name, email FROM patients 
            WHERE LOWER(last_name) = LOWER(?) AND date_of_birth = ? LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("Database error.");

    $stmt->bind_param("ss", $last_name, $dob);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Success
        $_SESSION['portal_patient_id'] = $row['patient_id'];
        $_SESSION['portal_patient_name'] = $row['first_name'] . ' ' . $row['last_name'];

        echo json_encode(["success" => true, "redirect" => "index.php"]);
    } else {
        http_response_code(401);
        echo json_encode(["message" => "No patient record found matching these details."]);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error.", "error" => $e->getMessage()]);
}

$conn->close();
?>