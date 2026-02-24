<?php
// Filename: api/get_vitals.php
// UPDATED: Default mode now fetches vitals specific to the appointment_id.
// History mode still fetches all vitals for the patient_id.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0; // NEW: Get Appointment ID
$history_mode = isset($_GET['history']) && $_GET['history'] === 'true';

// Validation is now stricter for non-history mode
if ($patient_id <= 0 || ($appointment_id <= 0 && !$history_mode)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid Patient ID or missing Appointment ID for form mode."]);
    exit();
}

try {
    if ($history_mode) {
        // --- Fetch ALL Vitals for History Table (By Patient ID) ---
        // Order by date descending to show most recent first
        $sql = "SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY visit_date DESC, created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals_data = $result->fetch_all(MYSQLI_ASSOC);

        // Return the array of all records
        http_response_code(200);
        echo json_encode($vitals_data);

    } else {
        // --- Fetch Vitals for Current Form Population (By Appointment ID) ---
        // This ensures the form loads the record specific to the current visit.
        $sql = "SELECT * FROM patient_vitals WHERE patient_id = ? AND appointment_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $patient_id, $appointment_id); // BIND: patient_id and appointment_id
        $stmt->execute();
        $result = $stmt->get_result();
        $vitals_data = $result->fetch_assoc();

        // Return the single record (or null if none found for this appointment)
        http_response_code(200);
        echo json_encode($vitals_data ? $vitals_data : null);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching vitals.", "error" => $e->getMessage()]);
}
?>