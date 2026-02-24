<?php
// Filename: api/get_visit_bundle.php
// Purpose: Fetches ALL data required for the visit_notes.php page in a single call.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- PHP Age Calculation Helper ---
function calculateAge($dob_string) {
    if (empty($dob_string)) return 'N/A';
    try {
        $birthDate = new DateTime($dob_string);
        $today = new DateTime('today');
        if ($birthDate > $today) return '0'; // Handle future DOB
        $age = $birthDate->diff($today)->y;
        return $age . "-year-old";
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Ensure IDs are numeric and safe
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid Patient or Appointment ID."]);
    exit();
}

$response = [
    'patient' => null,
    'profile' => null,
    'visit' => [
        'generated_cc' => '',
        'hpi_narrative' => null,
        'vitals' => null,
        'active_wounds' => [],
        'wound_assessments' => [],
        'diagnoses' => [],
        'procedures' => [],
        'medications' => [],
        'saved_note' => null
    ]
];

try {
    // 1. Get Patient Demographics & Allergies
    $stmt = $conn->prepare("SELECT first_name, last_name, date_of_birth, allergies FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $patient_data = $stmt->get_result()->fetch_assoc();
    $response['patient'] = $patient_data;
    $stmt->close();

    if (!$patient_data) {
        throw new Exception("Patient not found.");
    }

    // 2. Get Patient Profile Data (PMH, Social)
    // Note: Re-querying the same table to get other fields, can be consolidated in step 1 if SQL allows.
    $stmt = $conn->prepare("SELECT past_medical_history, social_history FROM patients WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $profile_data = $stmt->get_result()->fetch_assoc();
    if ($profile_data) {
        $response['profile'] = [
            'allergies' => $patient_data['allergies'],
            'medical_history' => json_decode($profile_data['past_medical_history'], true) ?: ['conditions' => $profile_data['past_medical_history']],
            'social_history' => json_decode($profile_data['social_history'], true) ?: []
        ];
    }
    $stmt->close();

    // 2.5. Get Patient's ACTIVE Wounds (for CC generation and dropdowns)
    $stmt = $conn->prepare("SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ? AND status = 'Active'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $active_wounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $response['visit']['active_wounds'] = $active_wounds;
    $stmt->close();

    // 3. Get HPI Narrative
    $stmt = $conn->prepare("SELECT narrative_text FROM visit_hpi_narratives WHERE appointment_id = ? LIMIT 1");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $hpi_result = $stmt->get_result()->fetch_assoc();
    $response['visit']['hpi_narrative'] = $hpi_result['narrative_text'] ?? null;
    $stmt->close();

    // 4. Get Vitals
    $stmt = $conn->prepare("SELECT * FROM patient_vitals WHERE appointment_id = ? LIMIT 1");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['vitals'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 5. Get Wound Assessments
    $stmt = $conn->prepare("SELECT wa.*, w.location, w.wound_type FROM wound_assessments wa JOIN wounds w ON wa.wound_id = w.wound_id WHERE wa.appointment_id = ? ORDER BY w.wound_id");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['wound_assessments'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 6. Get Diagnoses
    $stmt = $conn->prepare("SELECT icd10_code, description, is_primary FROM visit_diagnoses WHERE appointment_id = ? ORDER BY is_primary DESC");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['diagnoses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 7. Get Procedures (from Superbill)
    $stmt = $conn->prepare("SELECT s.cpt_code, c.description, s.units FROM superbill_services s LEFT JOIN cpt_codes c ON s.cpt_code = c.code WHERE s.appointment_id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['procedures'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 8. Get Patient's Active Medications
    $stmt = $conn->prepare("SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active'");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $response['visit']['medications'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 9. Get Previously Saved Note
    $stmt = $conn->prepare("SELECT * FROM visit_notes WHERE appointment_id = ? LIMIT 1");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['saved_note'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 9.5 Get Addendums
    $stmt = $conn->prepare("SELECT a.*, u.full_name AS username, u.role FROM visit_note_addendums a LEFT JOIN users u ON a.user_id = u.user_id WHERE a.appointment_id = ? ORDER BY a.created_at ASC");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $response['visit']['addendums'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // --- 10. Generate Chief Complaint String ---
    $patient_name = trim(($patient_data['first_name'] ?? '') . ' ' . ($patient_data['last_name'] ?? 'Patient'));
    $patient_age = calculateAge($patient_data['date_of_birth'] ?? null);

    $cc_parts = [];
    $cc_parts[] = "The patient, " . htmlspecialchars($patient_name) . ", a " . htmlspecialchars($patient_age) . ", is here for a scheduled visit.";

    // Process wounds
    if (!empty($active_wounds)) {
        $wound_descs = [];
        foreach ($active_wounds as $wound) {
            $wound_descs[] = htmlspecialchars($wound['wound_type']) . " on the " . htmlspecialchars($wound['location']);
        }

        $wound_list = '';
        if (count($wound_descs) > 1) {
            $last_wound = array_pop($wound_descs);
            $wound_list = implode(', ', $wound_descs) . ', and a ' . $last_wound;
        } elseif (count($wound_descs) === 1) {
            $wound_list = $wound_descs[0];
        }
        $cc_parts[] = "Presents for evaluation of existing wounds, including: a " . $wound_list . ".";
    } else {
        $cc_parts[] = "Presents for evaluation.";
    }

    $response['visit']['generated_cc'] = implode(' ', $cc_parts);
    // --- End CC Generation ---


    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "An error occurred while fetching visit data.", "details" => $e->getMessage()]);
}

$conn->close();
?>