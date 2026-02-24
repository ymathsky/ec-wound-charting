<?php
// Filename: api/save_superbill_services.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"));

if (empty($data->appointment_id) || !isset($data->services)) {
    http_response_code(400);
    echo json_encode(["message" => "Appointment ID and services are required."]);
    exit();
}

$appointment_id = intval($data->appointment_id);

try {
    $conn->begin_transaction();

    // First, delete existing services for this appointment to prevent duplicates
    $delete_sql = "DELETE FROM superbill_services WHERE appointment_id = ?";
    $stmt_delete = $conn->prepare($delete_sql);
    $stmt_delete->bind_param("i", $appointment_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // Now, insert the new set of services
    if (!empty($data->services)) {
        $insert_sql = "INSERT INTO superbill_services (appointment_id, cpt_code, units, linked_diagnosis_ids) VALUES (?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($insert_sql);

        foreach ($data->services as $service) {
            $cpt_code = htmlspecialchars($service->cpt_code);
            $units = intval($service->units);
            $linked_dx = isset($service->linked_diagnosis_ids) ? $service->linked_diagnosis_ids : null;
            
            $stmt_insert->bind_param("isis", $appointment_id, $cpt_code, $units, $linked_dx);
            $stmt_insert->execute();
        }
        $stmt_insert->close();
    }

    $conn->commit();

    http_response_code(200);
    echo json_encode(["message" => "Procedure saved successfully."]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(["message" => "Failed to save superbill.", "error" => $e->getMessage()]);
}

$conn->close();
?>
