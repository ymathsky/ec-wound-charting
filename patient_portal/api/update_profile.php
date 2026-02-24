<?php
// Filename: ec/patient_portal/api/update_profile.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../../db_connect.php';

if (!isset($_SESSION['portal_patient_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit();
}

$patient_id = $_SESSION['portal_patient_id'];
$input = json_decode(file_get_contents("php://input"), true);

// Validate inputs (Basic sanitization)
$contact_number = isset($input['contact_number']) ? strip_tags(trim($input['contact_number'])) : '';
$email = isset($input['email']) ? strip_tags(trim($input['email'])) : '';
$address = isset($input['address']) ? strip_tags(trim($input['address'])) : '';
$ec_name = isset($input['emergency_contact_name']) ? strip_tags(trim($input['emergency_contact_name'])) : '';
$ec_rel = isset($input['emergency_contact_relationship']) ? strip_tags(trim($input['emergency_contact_relationship'])) : '';
$ec_phone = isset($input['emergency_contact_phone']) ? strip_tags(trim($input['emergency_contact_phone'])) : '';

if (empty($contact_number)) {
    http_response_code(400);
    echo json_encode(["message" => "Phone number is required."]);
    exit();
}

try {
    $sql = "UPDATE patients SET 
            contact_number = ?, 
            email = ?, 
            address = ?, 
            emergency_contact_name = ?, 
            emergency_contact_relationship = ?, 
            emergency_contact_phone = ?,
            last_updated_by = NULL
            WHERE patient_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $contact_number, $email, $address, $ec_name, $ec_rel, $ec_phone, $patient_id);

    if ($stmt->execute()) {
        echo json_encode(["message" => "Profile updated successfully."]);
    } else {
        throw new Exception("Database update failed.");
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Server error updating profile."]);
}

$conn->close();
?>