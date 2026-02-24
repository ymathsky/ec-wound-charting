<?php
// Filename: api/get_superbill_data.php
// Purpose: Fetch current superbill services for an appointment, joining with CPT descriptions.

session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['ec_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID']);
    exit;
}

try {
    // UPDATED: JOIN with cpt_codes to fetch description
    // We use LEFT JOIN in case a manual code was entered that doesn't exist in the standard list
    $sql = "SELECT s.appointment_id, s.cpt_code, s.units, s.linked_diagnosis_ids, c.description 
            FROM superbill_services s
            LEFT JOIN cpt_codes c ON s.cpt_code = c.code
            WHERE s.appointment_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        // Fallback description if not found in DB
        if (empty($row['description'])) {
            $row['description'] = "Manual Entry / Description not found";
        }
        $services[] = $row;
    }

    echo json_encode(['success' => true, 'services' => $services]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>