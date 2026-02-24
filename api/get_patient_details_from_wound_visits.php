<?php
// Filename: api/get_patient_details_from_wound_visits.php
// This API is specifically for the visit_wounds.php page.
// It fetches patient details AND checks if wounds have been assessed for the given appointment.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db_connect.php';

$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// This API requires an appointment_id to function correctly
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid Patient ID or Appointment ID."));
    exit();
}

try {
    // --- Fetch Patient Details ---
    // *** MODIFICATION: Changed 'f.name' to 'f.full_name' ***
    $sql = "SELECT p.*, f.full_name as facility_name 
            FROM patients p 
            LEFT JOIN users f ON p.facility_id = f.user_id 
            WHERE p.patient_id = ? 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Patient not found."));
        exit();
    }

    $patient_details = $result->fetch_assoc();
    $stmt->close();

    // --- Fetch Wounds ---
    // This query now uses EXISTS, which is cleaner, faster, and
    // completely avoids the problematic GROUP BY clause.
    $wounds_sql = "
        SELECT 
            w.*,
            -- This correlated subquery gets the latest dimensions
            (SELECT CONCAT('L:', wa.length_cm, ' W:', wa.width_cm, ' D:', wa.depth_cm) 
             FROM wound_assessments wa 
             WHERE wa.wound_id = w.wound_id 
             ORDER BY wa.assessment_date DESC, wa.assessment_id DESC 
             LIMIT 1) as latest_dimensions,
            
            -- This correlated subquery uses EXISTS to check for an assessment.
            -- It returns 1 (true) or 0 (false).
            EXISTS (
                SELECT 1 
                FROM wound_assessments wa_visit 
                WHERE wa_visit.wound_id = w.wound_id 
                AND wa_visit.appointment_id = ?
            ) AS assessed_for_this_visit
        FROM 
            wounds w
        WHERE 
            w.patient_id = ?
    ";

    $wounds_stmt = $conn->prepare($wounds_sql);
    // Bind the appointment_id first, then patient_id
    $wounds_stmt->bind_param("ii", $appointment_id, $patient_id);
    $wounds_stmt->execute();

    // Check for errors after execute
    if ($wounds_stmt->error) {
        throw new Exception("Wound query failed: " . $wounds_stmt->error);
    }

    $wounds_result = $wounds_stmt->get_result();
    $wounds = [];

    while ($wound = $wounds_result->fetch_assoc()) {
        // Convert assessed_for_this_visit (which is 0 or 1) to a proper boolean
        $wound['assessed_for_this_visit'] = (bool)$wound['assessed_for_this_visit'];
        $wounds[] = $wound;
    }
    $wounds_stmt->close();

    $patient_details['wounds'] = $wounds;

    // --- Fetch Medications (Simplified for brevity) ---
    $med_sql = "SELECT * FROM patient_medications WHERE patient_id = ? AND status = 'Active'";
    $med_stmt = $conn->prepare($med_sql);
    $med_stmt->bind_param("i", $patient_id);
    $med_stmt->execute();
    $med_result = $med_stmt->get_result();
    $medications = $med_result->fetch_all(MYSQLI_ASSOC);
    $patient_details['medications'] = $medications;
    $med_stmt->close();

    // --- Fetch Recent Vitals (Simplified) ---
    $vitals_sql = "SELECT * FROM patient_vitals WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1";
    $vitals_stmt = $conn->prepare($vitals_sql);
    $vitals_stmt->bind_param("i", $patient_id);
    $vitals_stmt->execute();
    $vitals_result = $vitals_stmt->get_result();
    $patient_details['vitals'] = $vitals_result->fetch_assoc();
    $vitals_stmt->close();

    http_response_code(200);
    echo json_encode(array("details" => $patient_details));

} catch (Exception $e) {
    http_response_code(500);
    // Send back the actual error message for debugging
    echo json_encode(array("message" => "Server error.", "error" => $e->getMessage()));
}

$conn->close();
?>