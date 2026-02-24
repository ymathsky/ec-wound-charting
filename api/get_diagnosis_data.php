<?php
// Filename: api/get_diagnosis_data.php
// Fetch diagnoses for the current appointment AND historical diagnoses
// UPDATED: Added error handling for SQL preparation failures (prevents 500 errors)
// UPDATED: Now fetches Linked Wound Name for history items

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'data' => [], 'history' => [], 'message' => 'Invalid Appointment ID']);
    exit();
}

try {
    // 1. Fetch CURRENT Visit Diagnoses
    $sqlCurrent = "SELECT 
                vd.visit_diagnosis_id,
                vd.icd10_code,
                vd.description,
                vd.is_primary,
                vd.wound_id,
                vd.notes,
                w.location AS wound_location,
                w.wound_type
            FROM visit_diagnoses vd
            LEFT JOIN wounds w ON vd.wound_id = w.wound_id
            WHERE vd.appointment_id = ?
            ORDER BY vd.is_primary DESC, vd.created_at ASC";

    $stmt = $conn->prepare($sqlCurrent);

    if (!$stmt) {
        throw new Exception("Database Query Failed (Current): " . $conn->error);
    }

    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $currentData = [];
    while ($row = $result->fetch_assoc()) {
        $currentData[] = $row;
    }
    $stmt->close();

    // 2. Fetch HISTORICAL Diagnoses (Unique list from past visits)
    // UPDATED: Now joins with wounds table to get location name
    $historyData = [];
    if ($patient_id > 0) {
        $sqlHistory = "SELECT 
                    vd.icd10_code,
                    vd.description,
                    vd.wound_id,
                    w.location AS wound_location,
                    w.wound_type,
                    MAX(vd.created_at) as last_used_date
                FROM visit_diagnoses vd
                INNER JOIN appointments a ON vd.appointment_id = a.appointment_id
                LEFT JOIN wounds w ON vd.wound_id = w.wound_id
                WHERE vd.patient_id = ? 
                  AND vd.appointment_id != ? -- Exclude current appointment
                GROUP BY vd.icd10_code, vd.description, vd.wound_id -- Group by wound_id too so same code on different wounds shows up
                ORDER BY last_used_date DESC";

        $stmtH = $conn->prepare($sqlHistory);

        if (!$stmtH) {
            throw new Exception("Database Query Failed (History): " . $conn->error);
        }

        $stmtH->bind_param("ii", $patient_id, $appointment_id);
        $stmtH->execute();
        $resH = $stmtH->get_result();

        while ($rowH = $resH->fetch_assoc()) {
            $historyData[] = $rowH;
        }
        $stmtH->close();
    }

    echo json_encode([
        'success' => true,
        'data' => $currentData,
        'history' => $historyData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>