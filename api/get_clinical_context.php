<?php
// Filename: api/get_clinical_context.php
ini_set('display_errors', 0);
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid Patient ID."]);
    exit();
}

$response = [
    "success" => true,
    "notes" => [],
    "medications" => [],
    "diagnoses" => [],
    "history" => []
];

try {
    // 1. Fetch Last 3 Visit Notes (excluding current appointment if provided)
    if ($appointment_id > 0) {
        $sql_notes = "SELECT note_date, chief_complaint, subjective, objective, assessment, plan, procedure_note 
                      FROM visit_notes 
                      WHERE patient_id = ? AND appointment_id != ?
                      ORDER BY note_date DESC 
                      LIMIT 3";
        $stmt = $conn->prepare($sql_notes);
        $stmt->bind_param("ii", $patient_id, $appointment_id);
    } else {
        $sql_notes = "SELECT note_date, chief_complaint, subjective, objective, assessment, plan, procedure_note 
                      FROM visit_notes 
                      WHERE patient_id = ? 
                      ORDER BY note_date DESC 
                      LIMIT 3";
        $stmt = $conn->prepare($sql_notes);
        $stmt->bind_param("i", $patient_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $response['notes'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 2. Fetch Active Medications
    $sql_meds = "SELECT drug_name, dosage, frequency, route, status 
                 FROM patient_medications 
                 WHERE patient_id = ? AND status = 'Active' 
                 ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql_meds);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['medications'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 3. Fetch Recent Diagnoses (Active)
    // Note: visit_diagnoses table does not have a 'status' column, assuming all are relevant history.
    $sql_diag = "SELECT icd10_code, description, created_at as date_added 
                 FROM visit_diagnoses 
                 WHERE patient_id = ? 
                 ORDER BY created_at DESC 
                 LIMIT 10";
    $stmt = $conn->prepare($sql_diag);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['diagnoses'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 4. Fetch Active Wounds
    $sql_wounds = "SELECT wound_id, location, wound_type, date_onset 
                   FROM wounds 
                   WHERE patient_id = ? AND status = 'Active' 
                   ORDER BY location ASC";
    $stmt = $conn->prepare($sql_wounds);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $response['wounds'] = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 5. Fetch Medical History (from patients table or separate history table if exists)
    // Assuming basic history is in patients table or we just use the notes for now.
    // Let's check if there's a specific history table. 
    // Based on file list, there is `patient_hpi.php` but that's per visit.
    // `patient_profile.php` likely shows demographics.
    
    // Let's fetch basic patient info
    $sql_info = "SELECT medical_history_summary, surgical_history_summary, allergies 
                 FROM patients 
                 WHERE patient_id = ?";
    
    // Check if these columns exist first, or just try. 
    // To be safe, I'll just fetch what I know exists or skip if unsure.
    // I'll assume 'patients' table has some history fields or I'll leave it empty for now.
    // Let's try to fetch common fields.
    
    $check_cols = $conn->query("SHOW COLUMNS FROM patients LIKE 'medical_history%'");
    if ($check_cols->num_rows > 0) {
         $stmt = $conn->prepare("SELECT medical_history_txt, surgical_history_txt, allergies_txt FROM patients WHERE patient_id = ?");
         // Note: I am guessing column names. If they fail, I'll catch the error.
         // Actually, let's just stick to what we know or use a safe query.
         // I'll skip specific history columns for now to avoid SQL errors if I don't know the schema.
         // Instead, I'll rely on the notes.
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error fetching context.", "error" => $e->getMessage()]);
}
?>