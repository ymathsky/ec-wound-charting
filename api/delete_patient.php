<?php
// Filename: api/delete_patient.php

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';
require_once '../audit_log_function.php'; // --- ADDED ---

// Role check - ensure only admins can delete
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied. You do not have permission to perform this action."]);
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if (empty($data->patient_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Patient ID is required."]);
    exit();
}

try {
    $patient_id = intval($data->patient_id);

    // --- ADDED: Get patient details for logging BEFORE deleting ---
    $patient_name = "N/A";
    $stmt_get = $conn->prepare("SELECT first_name, last_name FROM patients WHERE patient_id = ?");
    $stmt_get->bind_param("i", $patient_id);
    if ($stmt_get->execute()) {
        $result = $stmt_get->get_result();
        if ($result->num_rows > 0) {
            $patient = $result->fetch_assoc();
            $patient_name = $patient['first_name'] . ' ' . $patient['last_name'];
        }
    }
    $stmt_get->close();
    // --- END ADD ---

    $sql = "DELETE FROM patients WHERE patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // --- ADDED ---
            $user_id = $_SESSION['ec_user_id'];
            $user_name = $_SESSION['ec_full_name'];
            log_audit($user_id, $user_name, 'DELETE', 'patient', $patient_id, "Deleted patient '$patient_name' (patient_id: $patient_id)");
            // --- END ADD ---
            http_response_code(200);
            echo json_encode(["message" => "Patient deleted successfully."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Patient not found or already deleted."]);
        }
    } else {
        throw new Exception("Failed to delete patient from the database.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred.", "error" => $e->getMessage()]);
}

$conn->close();
?>

