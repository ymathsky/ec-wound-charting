<?php
// Filename: api/get_patient_billing_history.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

session_start();
// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}


$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID."]);
    exit();
}

try {
    // 1. Fetch all appointments with superbill services for the patient
    $sql = "SELECT 
                a.appointment_id,
                a.appointment_date,
                u.full_name as clinician_name,
                ss.cpt_code,
                ss.units,
                cc.description
            FROM appointments a
            JOIN superbill_services ss ON a.appointment_id = ss.appointment_id
            JOIN cpt_codes cc ON ss.cpt_code = cc.code
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.patient_id = ?
            ORDER BY a.appointment_date DESC, ss.cpt_code ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $services = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 2. Group the results by appointment_id
    $history = [];
    foreach ($services as $service) {
        $appointment_id = $service['appointment_id'];

        if (!isset($history[$appointment_id])) {
            $history[$appointment_id] = [
                'appointment' => [
                    'appointment_id' => $appointment_id,
                    'appointment_date' => $service['appointment_date'],
                    'clinician_name' => $service['clinician_name']
                ],
                'services' => []
            ];
        }

        $history[$appointment_id]['services'][] = [
            'cpt_code' => $service['cpt_code'],
            'description' => $service['description'],
            'units' => $service['units']
        ];
    }


    http_response_code(200);
    echo json_encode($history);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching billing history.", "error" => $e->getMessage()]);
}

$conn->close();
?>
