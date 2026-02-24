<?php
// Filename: api/get_live_report.php
// Purpose: Returns the HTML content for the live report view, similar to visit_report.php but for AJAX injection.

require_once '../db_connect.php';

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-4 text-red-600'>Invalid Patient or Appointment ID.</div>";
    exit();
}

// --- Initialize data variables ---
$report_data = [];
$vitals_data = [];
$hpi_narrative_data = [];
$note_data = [];
$assessments_data = [];
$diagnoses_data = [];
$medications_data = [];

try {
    // 1. Fetch Comprehensive Report Data (Patient, Appointment, Clinician, Facility)
    $report_sql = "SELECT 
                        p.first_name, p.last_name, p.patient_code, p.date_of_birth, p.gender,
                        p.allergies, p.past_medical_history, p.social_history,
                        a.appointment_date,
                        u.full_name as clinician_name, u.credentials as clinician_credentials, u.role as clinician_role,
                        u_assigned.full_name as assigned_clinician_name, u_assigned.credentials as assigned_clinician_credentials, u_assigned.role as assigned_clinician_role,
                        fac.full_name as facility_name, fac.email as facility_email
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.patient_id
                    LEFT JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN users u_assigned ON p.primary_user_id = u_assigned.user_id
                    LEFT JOIN users fac ON p.facility_id = fac.user_id AND fac.role = 'facility'
                    WHERE a.appointment_id = ?";
    $stmt = $conn->prepare($report_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. Fetch Vitals
    $vitals_sql = "SELECT * FROM patient_vitals WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($vitals_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $vitals_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 2. Fetch HPI Narrative
    $hpi_sql = "SELECT * FROM visit_hpi_narratives WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($hpi_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $hpi_narrative_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 3. Fetch Note Data
    $note_sql = "SELECT chief_complaint, subjective, objective, assessment, plan, procedure_note, live_note FROM visit_notes WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($note_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $note_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // CHECK FOR LIVE NOTE DRAFT FIRST
    // If the live_note column has content, it represents the most current "Draft" state of the editor.
    // We should return this instead of regenerating from components, to preserve manual edits/images.
    // Ensure we ignore "empty" HTML like <br> or <p></p> by stripping tags for the check.
    $draft_content = $note_data['live_note'];
    
    // Decode entities to catch &nbsp; which strip_tags misses
    $decoded_draft = html_entity_decode($draft_content);
    $draft_plain = trim(strip_tags($decoded_draft));
    
    // Remove non-breaking spaces and zero-width spaces that might persist
    $draft_plain = preg_replace('/[\x00-\x1F\x7F\xA0]/u', '', $draft_plain);
    $draft_plain = trim($draft_plain);

    // Also check if it contains images provided that text might be empty
    $has_image = (stripos($draft_content, '<img') !== false);

    // Require at least a few meaningful characters to consider it a valid manual draft
    // This prevents stuck "blank" states where hidden formatting prevents the fresh data from showing.
    if (!empty($draft_content) && (strlen($draft_plain) > 2 || $has_image)) {
        echo $draft_content;
        exit();
    }

    // 4. Fetch Wound Assessments
    // Fetch all assessments for this appointment, ordered by date/creation descending
    $assessments_sql = "SELECT wa.*, w.location, w.wound_type FROM wound_assessments wa JOIN wounds w ON wa.wound_id = w.wound_id WHERE wa.appointment_id = ? ORDER BY w.location ASC, wa.assessment_type ASC";
    $stmt = $conn->prepare($assessments_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $assessments_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 5. Fetch Diagnoses
    $diag_sql = "SELECT icd10_code, description FROM visit_diagnoses WHERE appointment_id = ?";
    $stmt = $conn->prepare($diag_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $diagnoses_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 6. Fetch Medications
    $med_sql = "SELECT drug_name, dosage, frequency, route FROM patient_medications WHERE patient_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($med_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $medications_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 7. Fetch Wound Images
    $images_sql = "SELECT * FROM wound_images WHERE appointment_id = ?";
    $stmt = $conn->prepare($images_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $images_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

} catch (Exception $e) {
    echo "<div class='p-4 text-red-600'>Error loading data: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit();
}

// --- Helper Functions ---
function render_text($text) {
    return !empty($text) ? nl2br(htmlspecialchars($text)) : '<span class="text-gray-400 italic">Not documented</span>';
}

function render_clinical_text($text) {
    if (empty($text)) return '';
    $text = preg_replace('/<\?xml.*?\?>/s', '', $text);
    if ($text != strip_tags($text)) {
        return $text; 
    } else {
        return nl2br(htmlspecialchars($text));
    }
}

function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age . " yrs";
}

function calculateAgeAtDOS($dob, $dos) {
    if (!$dob || !$dos) return 'N/A';
    $birthDate = new DateTime($dob);
    $serviceDate = new DateTime($dos);
    $age = $birthDate->diff($serviceDate)->y;
    return $age . " yrs";
}

// --- Page Variables ---
$patient_name = $report_data ? strtoupper("{$report_data['first_name']} {$report_data['last_name']}") : 'N/A';
$patient_dob = $report_data['date_of_birth'] ? date('m/d/Y', strtotime($report_data['date_of_birth'])) : 'N/A';
$patient_prn = $report_data['patient_code'] ?? 'N/A';
$patient_gender = $report_data['gender'] ?? 'N/A';
$patient_age_now = calculateAge($report_data['date_of_birth']);

$date_of_service_obj = $report_data ? new DateTime($report_data['appointment_date']) : new DateTime();
$date_of_service = $date_of_service_obj->format('m/d/y');
$time_of_service = $date_of_service_obj->format('h:i A');
$age_at_dos = calculateAgeAtDOS($report_data['date_of_birth'], $report_data['appointment_date']);

$clinician_name = !empty($report_data['clinician_name']) 
    ? strtoupper($report_data['clinician_name']) 
    : (!empty($report_data['assigned_clinician_name']) ? strtoupper($report_data['assigned_clinician_name']) : 'UNASSIGNED');

$clinician_creds = !empty($report_data['clinician_credentials']) 
    ? $report_data['clinician_credentials'] 
    : (!empty($report_data['assigned_clinician_credentials']) ? $report_data['assigned_clinician_credentials'] : '');

$clinician_full = $clinician_name . ($clinician_creds ? " " . $clinician_creds : "");

$clinician_role = !empty($report_data['clinician_role']) 
    ? strtoupper($report_data['clinician_role']) 
    : (!empty($report_data['assigned_clinician_role']) ? strtoupper($report_data['assigned_clinician_role']) : 'CLINICIAN');

$facility_name = $report_data['facility_name'] ? strtoupper($report_data['facility_name']) : 'EXPERT CARE';
$facility_phone = $report_data['facility_phone'] ?? '(555) 123-4567';
$facility_address = $report_data['facility_address'] ?? '123 Medical Center Dr';
?>

<div class="bg-white shadow-sm rounded-lg p-8 max-w-4xl mx-auto border border-gray-200 font-sans text-lg">
    
    <!-- Top Banner -->
    <div class="text-center text-sm mb-4 border-b border-gray-200 pb-2">
        Encounter - Home Visit Date of service: <?php echo $date_of_service; ?> Patient: <?php echo $patient_name; ?> DOB: <?php echo $patient_dob; ?> PRN: <?php echo $patient_prn; ?>
    </div>

    <!-- Header -->
    <div class="flex justify-between border-b-2 border-black pb-2 mb-4">
        <div class="w-1/3">
            <h3 class="text-sm font-bold text-gray-600 uppercase mb-0.5">PATIENT</h3>
            <div class="text-base font-bold"><?php echo $patient_name; ?></div>
            <div class="text-sm">DOB: <?php echo $patient_dob; ?></div>
            <div class="text-sm">AGE: <?php echo $patient_age_now; ?></div>
            <div class="text-sm">SEX: <?php echo $patient_gender; ?></div>
            <div class="text-sm">PRN: <?php echo $patient_prn; ?></div>
        </div>
        <div class="w-1/3">
            <h3 class="text-sm font-bold text-gray-600 uppercase mb-0.5">FACILITY</h3>
            <div class="text-base font-bold"><?php echo $facility_name; ?></div>
            <div class="text-sm"><?php echo $facility_phone; ?></div>
            <div class="text-sm"><?php echo $facility_address; ?></div>
        </div>
        <div class="w-1/3">
            <h3 class="text-sm font-bold text-gray-600 uppercase mb-0.5">ENCOUNTER</h3>
            <div class="text-sm">Home Visit</div>
            <div class="text-sm">SOAP Note</div>
            <div class="text-sm">SEEN BY: <span class="font-bold"><?php echo $clinician_full; ?></span></div>
            <div class="text-sm"><?php echo $clinician_role; ?></div>
            <div class="text-sm">DATE: <?php echo $date_of_service; ?></div>
            <div class="text-sm">AGE AT DOS: <?php echo $age_at_dos; ?></div>
        </div>
    </div>

    <!-- Chief Complaint -->
    <div class="mb-4">
        <h3 class="text-base font-bold uppercase mb-1">Chief complaint</h3>
        <div class="text-base"><?php echo !empty($note_data['chief_complaint']) ? render_clinical_text($note_data['chief_complaint']) : 'Follow up wound care visit.'; ?></div>
    </div>

    <!-- Vitals -->
    <div class="mb-4">
        <table class="w-full text-sm border-collapse mb-2">
            <thead>
                <tr class="bg-gray-100">
                    <th class="text-left border-b border-black p-1 font-bold">Vitals for this encounter</th>
                    <th class="text-center border-b border-black p-1 font-bold">Temperature</th>
                    <th class="text-center border-b border-black p-1 font-bold">Pulse</th>
                    <th class="text-center border-b border-black p-1 font-bold">Respiratory rate</th>
                    <th class="text-center border-b border-black p-1 font-bold">O2 Saturation</th>
                    <th class="text-center border-b border-black p-1 font-bold">Blood pressure</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vitals_data): 
                    $temp_f = $vitals_data['temperature_celsius'] ? number_format(($vitals_data['temperature_celsius'] * 9/5) + 32, 1) . " °F" : '';
                ?>
                <tr>
                    <td class="border-b border-gray-200 p-1">
                        <?php echo $date_of_service; ?><br>
                        <?php echo $time_of_service; ?>
                    </td>
                    <td class="border-b border-gray-200 p-1 text-center"><?php echo $temp_f; ?></td>
                    <td class="border-b border-gray-200 p-1 text-center"><?php echo $vitals_data['heart_rate'] ? $vitals_data['heart_rate'] . " bpm" : ''; ?></td>
                    <td class="border-b border-gray-200 p-1 text-center"><?php echo $vitals_data['respiratory_rate'] ? $vitals_data['respiratory_rate'] . " bpm" : ''; ?></td>
                    <td class="border-b border-gray-200 p-1 text-center"><?php echo $vitals_data['oxygen_saturation'] ? $vitals_data['oxygen_saturation'] . " %" : ''; ?></td>
                    <td class="border-b border-gray-200 p-1 text-center"><?php echo $vitals_data['blood_pressure'] ? $vitals_data['blood_pressure'] . " mmHg" : ''; ?></td>
                </tr>
                <?php else: ?>
                <tr><td colspan="6" class="text-center italic p-2 border-b border-gray-200">No vitals recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Allergies -->
    <div class="mb-4">
        <div class="bg-gray-200 p-1 font-bold border-b border-gray-300 text-base">Drug Allergies</div>
        <div class="p-1 border-b border-gray-100 text-sm mb-2">
            Was medication allergy reconciliation completed?<br>
            <span class="font-bold">Yes, reconciliation performed</span>
        </div>
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left border-b border-black p-1 font-bold w-2/5">Active</th>
                    <th class="text-left border-b border-black p-1 font-bold w-2/5">SEVERITY/REACTIONS</th>
                    <th class="text-left border-b border-black p-1 font-bold w-1/5">ONSET</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $allergies_text = $report_data['allergies'];
                if (empty($allergies_text) || stripos($allergies_text, 'no known') !== false) {
                    echo "<tr><td colspan='3' class='p-1 border-b border-gray-100'>Patient has no known drug allergies</td></tr>";
                } else {
                    echo "<tr><td class='p-1 border-b border-gray-100'>" . htmlspecialchars($allergies_text) . "</td><td class='p-1 border-b border-gray-100'></td><td class='p-1 border-b border-gray-100'></td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Past Medical History -->
    <h2 class="text-lg font-bold bg-gray-200 px-2 py-1 mt-4 mb-2 border-b border-gray-300">Past medical history</h2>
    <div class="mb-2">
        <h3 class="text-sm font-bold uppercase mb-1">MAJOR EVENTS</h3>
        <div class="text-sm"><?php 
            $pmh = $report_data['past_medical_history'];
            $pmh_json = json_decode($pmh, true);
            echo !empty($pmh_json['conditions']) ? render_clinical_text($pmh_json['conditions']) : render_clinical_text($pmh);
        ?></div>
    </div>
    <div class="mb-2">
        <h3 class="text-sm font-bold uppercase mb-1">SOCIAL HISTORY</h3>
        <div class="text-sm"><?php echo !empty($report_data['social_history']) ? render_clinical_text($report_data['social_history']) : 'No social history recorded.'; ?></div>
    </div>

    <!-- Subjective -->
    <h2 class="text-lg font-bold bg-gray-200 px-2 py-1 mt-4 mb-2 border-b border-gray-300">Subjective</h2>
    <div class="mb-2">
        <?php 
        // Check for consolidated Subjective first (AI generated)
        if (!empty($note_data['subjective'])) {
            echo '<div class="text-sm">' . render_clinical_text($note_data['subjective']) . '</div>';
        } else {
            // Fallback to separate HPI if main subjective is empty
            if (!empty($hpi_narrative_data['narrative_text'])) {
                echo '<div class="text-sm"><span class="font-bold">History of present illness:</span> ' . render_clinical_text($hpi_narrative_data['narrative_text']) . '</div>';
            } else {
                echo '<div class="text-sm text-gray-400 italic">No subjective data documented.</div>';
            }
        }
        ?>
    </div>

    <!-- Objective -->
    <h2 class="text-lg font-bold bg-gray-200 px-2 py-1 mt-4 mb-2 border-b border-gray-300">Objective</h2>
    <div class="mb-2">
        <div class="text-sm"><?php echo !empty($note_data['objective']) ? render_clinical_text($note_data['objective']) : 'Physical exam findings noted below.'; ?></div>
    </div>

    <!-- Assessment -->
    <h2 class="text-lg font-bold bg-gray-200 px-2 py-1 mt-4 mb-2 border-b border-gray-300">Assessment</h2>
    <div class="mb-2">
        <div class="text-sm"><?php echo !empty($note_data['assessment']) ? render_clinical_text($note_data['assessment']) : ''; ?></div>
    </div>

    <?php if (!empty($assessments_data)): ?>
        <h3 class="text-sm font-bold uppercase mb-2">Wound Assessments</h3>
        <div class="space-y-4 mb-4">
            <?php foreach ($assessments_data as $wa): 
                // Find images for this assessment
                $wa_images = array_filter($images_data, function($img) use ($wa) {
                    return $img['assessment_id'] == $wa['assessment_id'];
                });
            ?>
                <div class="pl-2 border-l-4 border-gray-200 mb-4">
                    <div class="flex justify-between items-start mb-1">
                        <div>
                            <span class="font-bold text-base"><?php echo htmlspecialchars($wa['location']); ?></span>
                            <span class="text-sm text-gray-500 ml-2">(<?php echo htmlspecialchars($wa['wound_type']); ?>)</span>
                        </div>
                        <?php if(!empty($wa['assessment_type']) && $wa['assessment_type'] !== 'Regular'): ?>
                            <span class="text-xs font-bold text-white bg-gray-600 px-1.5 py-0.5 rounded uppercase"><?php echo htmlspecialchars($wa['assessment_type']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex gap-4">
                        <!-- Details Column -->
                        <div class="flex-1 text-sm">
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                <div class="flex"><span class="font-bold w-24">Dimensions:</span> <span><?php echo "{$wa['length_cm']} x {$wa['width_cm']} x {$wa['depth_cm']} cm"; ?></span></div>
                                <div class="flex"><span class="font-bold w-24">Area:</span> <span><?php echo number_format($wa['length_cm'] * $wa['width_cm'], 2); ?> cm²</span></div>
                                <div class="flex"><span class="font-bold w-24">Tissue:</span> <span><?php 
                                    $t = [];
                                    if($wa['granulation_percent']) $t[] = "Gran: {$wa['granulation_percent']}%";
                                    if($wa['slough_percent']) $t[] = "Slough: {$wa['slough_percent']}%";
                                    if($wa['eschar_percent']) $t[] = "Eschar: {$wa['eschar_percent']}%";
                                    echo implode(', ', $t) ?: 'Not specified';
                                ?></span></div>
                                <div class="flex"><span class="font-bold w-24">Drainage:</span> <span><?php echo htmlspecialchars($wa['exudate_amount'] . ' ' . $wa['exudate_type']); ?></span></div>
                                <div class="flex"><span class="font-bold w-24">Pain:</span> <span><?php echo htmlspecialchars($wa['pain_level']); ?>/10</span></div>
                            </div>
                        </div>

                        <!-- Image Column -->
                        <?php if (!empty($wa_images)): ?>
                        <div class="w-24 flex-shrink-0 flex flex-col gap-2">
                            <?php foreach ($wa_images as $img): ?>
                                <div class="border border-gray-300 bg-white p-0.5 cursor-pointer hover:ring-2 hover:ring-blue-500 transition-all">
                                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" 
                                         class="w-full h-auto block" 
                                         alt="Wound Image"
                                         data-location="<?php echo htmlspecialchars($wa['location']); ?>"
                                         data-type="<?php echo htmlspecialchars($wa['wound_type']); ?>"
                                         data-length="<?php echo htmlspecialchars($wa['length_cm']); ?>"
                                         data-width="<?php echo htmlspecialchars($wa['width_cm']); ?>"
                                         data-depth="<?php echo htmlspecialchars($wa['depth_cm']); ?>"
                                         data-granulation="<?php echo htmlspecialchars($wa['granulation_percent']); ?>"
                                         data-slough="<?php echo htmlspecialchars($wa['slough_percent']); ?>"
                                         data-eschar="<?php echo htmlspecialchars($wa['eschar_percent']); ?>"
                                         data-epithelial="<?php echo htmlspecialchars($wa['epithelialization_percent']); ?>"
                                         data-drainage-amt="<?php echo htmlspecialchars($wa['exudate_amount']); ?>"
                                         data-drainage-type="<?php echo htmlspecialchars($wa['drainage_type']); ?>"
                                         data-odor="<?php echo htmlspecialchars($wa['odor_present']); ?>"
                                         data-pain="<?php echo htmlspecialchars($wa['pain_level']); ?>"
                                    >
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($diagnoses_data)): ?>
        <div class="mb-4">
            <h3 class="text-sm font-bold uppercase mb-1">Diagnoses</h3>
            <ul class="list-none pl-0 text-sm text-gray-700">
                <?php foreach ($diagnoses_data as $diag): ?>
                    <li class="mb-1"><span class="font-bold"><?php echo htmlspecialchars($diag['icd10_code']); ?></span> - <?php echo htmlspecialchars($diag['description']); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Plan -->
    <h2 class="text-lg font-bold bg-gray-200 px-2 py-1 mt-4 mb-2 border-b border-gray-300">Plan</h2>
    <div class="mb-2">
        <div class="text-sm"><?php echo !empty($note_data['plan']) ? render_clinical_text($note_data['plan']) : ''; ?></div>
    </div>
    <?php if (!empty($medications_data)): ?>
        <div class="mb-4">
            <h3 class="text-sm font-bold uppercase mb-1">Medications</h3>
            <ul class="list-disc list-inside text-sm text-gray-700">
                <?php foreach ($medications_data as $med): ?>
                    <li><?php echo htmlspecialchars("{$med['drug_name']} {$med['dosage']} {$med['frequency']} via {$med['route']}"); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

</div>
