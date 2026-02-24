<?php
// Filename: visit_report.php
// UPDATED: To read note data from the 'visit_notes' table instead of 'patient_notes'
// UPDATED: Included Wound Treatment Plan in the report.

// --- Setup ---
require_once 'templates/header.php'; // For session and role
require_once 'db_connect.php'; // For database connection

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician', 'facility'])) {
    echo "<div class='p-8'>Access Denied. You do not have permission to view this page.</div>";
    require_once 'templates/footer.php';
    exit();
}
$user_role = $_SESSION['ec_role'];

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- Initialize data variables ---
$report_data = [];
$vitals_data = [];
$hpi_data = []; // Structured HPI
$hpi_narrative_data = []; // Narrative HPI
$note_data = []; // SOAP note
$assessments_data = [];
$images_data = [];
$diagnoses_data = [];
$procedures_data = [];
$medications_data = [];

// --- Fetch all necessary data ---
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
                    WHERE a.appointment_id = ? AND p.patient_id = ?
                    LIMIT 1";
    $stmt = $conn->prepare($report_sql);
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
    $report_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$report_data) {
        echo "<div class='p-8'>Could not find matching report data for this patient and appointment.</div>";
        require_once 'templates/footer.php';
        exit();
    }

    // 2. Fetch Vitals
    $vitals_sql = "SELECT * FROM patient_vitals WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($vitals_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $vitals_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 3. Fetch HPI Narrative
    $hpi_sql = "SELECT * FROM visit_hpi_narratives WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($hpi_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $hpi_narrative_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 4. Fetch Final Note (CRITICAL FIX: Reading from 'visit_notes' table)
    $note_sql = "SELECT chief_complaint, subjective, objective, assessment, plan, procedure_note, lab_orders, imaging_orders, skilled_nurse_orders, signature_data, created_at FROM visit_notes WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($note_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $note_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 5. Fetch Wound Assessments
    $assessments_sql = "SELECT 
                            wa.*, 
                            w.location, w.wound_type, w.date_onset
                        FROM wound_assessments wa
                        JOIN wounds w ON wa.wound_id = w.wound_id
                        WHERE wa.appointment_id = ?
                        ORDER BY w.wound_id ASC, wa.assessment_id ASC";
    $stmt = $conn->prepare($assessments_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $assessments_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 6. Fetch Wound Images
    $images_sql = "SELECT wi.image_path, wi.image_type, wi.wound_id, wi.assessment_id
                   FROM wound_images wi
                   WHERE wi.appointment_id = ? 
                   ORDER BY wi.wound_id ASC, wi.uploaded_at ASC";
    $stmt = $conn->prepare($images_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $images_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 7. Fetch Diagnoses
    $diag_sql = "SELECT icd10_code, description, is_primary, notes 
                 FROM visit_diagnoses 
                 WHERE appointment_id = ? 
                 ORDER BY is_primary DESC, icd10_code ASC";
    $stmt = $conn->prepare($diag_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $diagnoses_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 8. Fetch Procedures (Superbill)
    $proc_sql = "SELECT s.cpt_code, s.units, c.description
                 FROM superbill_services s
                 LEFT JOIN cpt_codes c ON s.cpt_code = c.code
                 WHERE s.appointment_id = ?";
    $stmt = $conn->prepare($proc_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $procedures_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 9. Fetch Active Medications
    $med_sql = "SELECT drug_name, dosage, frequency, status, start_date 
                FROM patient_medications 
                WHERE patient_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($med_sql);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $medications_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 10. Fetch Addendums
    $addendum_sql = "SELECT a.*, u.full_name as author_name, u.credentials as author_creds 
                     FROM visit_note_addendums a 
                     LEFT JOIN users u ON a.user_id = u.user_id 
                     WHERE a.appointment_id = ? 
                     ORDER BY a.created_at ASC";
    $stmt = $conn->prepare($addendum_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $addendums_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


} catch (Exception $e) {
    echo "<div class='p-8 bg-red-100 text-red-800'>Error loading report data: " . htmlspecialchars($e->getMessage()) . "</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- Data Preparation & Formatting Functions ---

function render_clinical_text($text) {
    if (empty($text)) return '';
    
    // Remove XML declaration if present
    $text = preg_replace('/<\?xml.*?\?>/s', '', $text);
    
    // Check if text contains HTML tags
    if ($text != strip_tags($text)) {
        // It has HTML, so we assume it's safe-ish (internal app) and output raw
        return $text; 
    } else {
        // It's plain text, so escape it and add line breaks
        return nl2br(htmlspecialchars($text));
    }
}

function format_json_list($json) {
    if (empty($json)) return 'None';
    // If it's already an array (unlikely from DB fetch, but possible if pre-processed)
    if (is_array($json)) return implode(', ', $json);
    
    $data = json_decode($json, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        return implode(', ', $data);
    }
    return htmlspecialchars($json);
}

function format_location_details($present, $json) {
    if ($present !== 'Yes') return 'No';
    if (empty($json)) return 'Yes (No details)';
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) return 'Yes';
    
    $details = [];
    foreach ($data as $item) {
        $pos = $item['position'] ?? '?';
        $depth = $item['depth'] ?? '?';
        $details[] = "{$pos} o'clock ({$depth} cm)";
    }
    return 'Yes: ' . implode(', ', $details);
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

// Logic: Use appointment provider if set, otherwise use assigned clinician from patient record
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

$back_link_text = ($user_role === 'facility') ? 'Back to Appointments' : 'Back to Visit Summary';
$back_link_href = ($user_role === 'facility') ? "patient_appointments.php?id={$patient_id}" : "visit_summary.php?appointment_id={$appointment_id}&patient_id={$patient_id}";

$is_preview = isset($_GET['mode']) && $_GET['mode'] === 'preview';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Encounter Report - <?php echo $patient_name; ?></title>
    <style>
        /* Reset & Base Styles */
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.4; color: #000; margin: 0; padding: 0; background-color: #f0f0f0; }
        .page-container { width: 8.5in; min-height: 11in; margin: 20px auto; background: #fff; padding: 0.5in; box-sizing: border-box; position: relative; }
        
        /* Typography */
        h1, h2, h3, h4, h5, h6 { margin: 0; padding: 0; font-weight: bold; }
        h2 { font-size: 14px; background-color: #e5e5e5; padding: 5px 10px; margin-top: 15px; margin-bottom: 10px; border-bottom: 1px solid #ccc; }
        h3 { font-size: 12px; font-weight: bold; margin-top: 10px; margin-bottom: 5px; text-transform: uppercase; }
        p { margin: 0 0 5px 0; }
        
        /* Header */
        .report-header { display: flex; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
        .header-col { width: 32%; }
        .header-col h3 { margin-top: 0; color: #555; font-size: 10px; margin-bottom: 2px; }
        .header-data { font-weight: bold; font-size: 12px; }
        .header-sub { font-size: 11px; }
        
        /* Top Banner */
        .top-banner { text-align: center; font-size: 10px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 11px; }
        th { text-align: left; border-bottom: 1px solid #000; padding: 4px; font-weight: bold; background-color: #f9f9f9; }
        td { border-bottom: 1px solid #eee; padding: 4px; vertical-align: top; }
        
        /* Lists */
        ul { margin: 0 0 10px 20px; padding: 0; }
        li { margin-bottom: 2px; }
        
        /* Specific Sections */
        .vitals-table th, .vitals-table td { text-align: center; }
        .vitals-table th:first-child, .vitals-table td:first-child { text-align: left; }
        
        .wound-block { margin-bottom: 15px; padding-left: 10px; border-left: 3px solid #eee; }
        .wound-detail-row { display: flex; margin-bottom: 2px; }
        .wound-label { font-weight: bold; width: 180px; flex-shrink: 0; }
        .wound-value { flex-grow: 1; }
        
        .signature-block { margin-top: 30px; border-top: 1px solid #000; padding-top: 5px; width: 60%; }
        
        /* Utility */
        .bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .text-right { text-align: right; }
        .no-print { display: block; }
        
        /* Print Styles */
        @media print {
            body { background-color: #fff; }
            .page-container { margin: 0; width: 100%; box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            h2 { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; }
            th { background-color: #f9f9f9 !important; -webkit-print-color-adjust: exact; }
            .page-break { page-break-before: always; }
        }

        <?php if ($is_preview): ?>
        /* Preview Mode Overrides */
        .no-print { display: none !important; }
        body { background-color: #fff; overflow-x: hidden; } /* Prevent double scrollbars */
        .page-container { 
            width: 100%; 
            max-width: 100%; 
            margin: 0; 
            box-shadow: none; 
            padding: 20px; 
            min-height: auto;
        }
        <?php endif; ?>
    </style>
</head>
<body>

<!-- No Print Toolbar -->
<div class="no-print" style="background: #333; color: #fff; padding: 10px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100;">
    <div>
        <a href="<?php echo $back_link_href; ?>" style="color: #fff; text-decoration: none; margin-right: 20px;">&larr; <?php echo $back_link_text; ?></a>
    </div>
    <div>
        <button onclick="window.print()" style="background: #007bff; color: #fff; border: none; padding: 8px 15px; cursor: pointer; border-radius: 4px; font-weight: bold;">Print Report</button>
    </div>
</div>

<div class="page-container">
    <!-- Logo Section -->
    <div style="text-align: center; margin-bottom: 15px;">
        <img src="logo.png" alt="EC Wound Charting Logo" style="max-height: 80px; width: auto;">
    </div>

    <!-- Top Banner -->
    <div class="top-banner">
        Encounter - Home Visit Date of service: <?php echo $date_of_service; ?> Patient: <?php echo $patient_name; ?> DOB: <?php echo $patient_dob; ?> PRN: <?php echo $patient_prn; ?>
    </div>

    <!-- Header -->
    <div class="report-header">
        <div class="header-col">
            <h3>PATIENT</h3>
            <div class="header-data"><?php echo $patient_name; ?></div>
            <div class="header-sub">DOB: <?php echo $patient_dob; ?></div>
            <div class="header-sub">AGE: <?php echo $patient_age_now; ?></div>
            <div class="header-sub">SEX: <?php echo $patient_gender; ?></div>
            <div class="header-sub">PRN: <?php echo $patient_prn; ?></div>
        </div>
        <div class="header-col">
            <h3>FACILITY</h3>
            <div class="header-data"><?php echo $facility_name; ?></div>
            <div class="header-sub"><?php echo $facility_phone; ?></div>
            <div class="header-sub"><?php echo $facility_address; ?></div>
        </div>
        <div class="header-col">
            <h3>ENCOUNTER</h3>
            <div class="header-sub">Home Visit</div>
            <div class="header-sub">SOAP Note</div>
            <div class="header-sub">SEEN BY: <span class="bold"><?php echo $clinician_full; ?></span></div>
            <div class="header-sub"><?php echo $clinician_role; ?></div>
            <div class="header-sub">DATE: <?php echo $date_of_service; ?></div>
            <div class="header-sub">AGE AT DOS: <?php echo $age_at_dos; ?></div>
            <div class="header-sub" style="margin-top: 5px; font-style: italic;">Electronically signed by <?php echo $clinician_name; ?></div>
            <div class="header-sub">at <?php echo $note_data['created_at'] ? date('m/d/Y h:i a', strtotime($note_data['created_at'])) : 'N/A'; ?></div>
        </div>
    </div>

    <!-- Chief Complaint -->
    <div style="margin-bottom: 15px;">
        <h3>Chief complaint</h3>
        <div><?php echo !empty($note_data['chief_complaint']) ? render_clinical_text($note_data['chief_complaint']) : 'Follow up wound care visit.'; ?></div>
    </div>

    <!-- Vitals -->
    <div style="margin-bottom: 15px;">
        <table class="vitals-table">
            <thead>
                <tr>
                    <th style="background: #eee;">Vitals for this encounter</th>
                    <th>Temperature</th>
                    <th>Pulse</th>
                    <th>Respiratory rate</th>
                    <th>O2 Saturation</th>
                    <th>Blood pressure</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($vitals_data): 
                    $temp_f = $vitals_data['temperature_celsius'] ? number_format(($vitals_data['temperature_celsius'] * 9/5) + 32, 1) . " °F" : '';
                ?>
                <tr>
                    <td>
                        <?php echo $date_of_service; ?><br>
                        <?php echo $time_of_service; ?>
                    </td>
                    <td><?php echo $temp_f; ?></td>
                    <td><?php echo $vitals_data['heart_rate'] ? $vitals_data['heart_rate'] . " bpm" : ''; ?></td>
                    <td><?php echo $vitals_data['respiratory_rate'] ? $vitals_data['respiratory_rate'] . " bpm" : ''; ?></td>
                    <td><?php echo $vitals_data['oxygen_saturation'] ? $vitals_data['oxygen_saturation'] . " %" : ''; ?></td>
                    <td><?php echo $vitals_data['blood_pressure'] ? $vitals_data['blood_pressure'] . " mmHg" : ''; ?></td>
                </tr>
                <?php else: ?>
                <tr><td colspan="6" style="text-align: center; font-style: italic;">No vitals recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Allergies -->
    <div style="margin-bottom: 15px;">
        <div style="background: #eee; padding: 5px; font-weight: bold; border-bottom: 1px solid #ccc;">Drug Allergies</div>
        <div style="padding: 5px; border-bottom: 1px solid #eee; font-size: 11px;">
            Was medication allergy reconciliation completed?<br>
            <span class="bold">Yes, reconciliation performed</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th width="40%">Active</th>
                    <th width="40%">SEVERITY/REACTIONS</th>
                    <th width="20%">ONSET</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $allergies_text = $report_data['allergies'];
                if (empty($allergies_text) || stripos($allergies_text, 'no known') !== false) {
                    echo "<tr><td colspan='3'>Patient has no known drug allergies</td></tr>";
                } else {
                    // Simple split if comma separated, otherwise just show text
                    echo "<tr><td>" . htmlspecialchars($allergies_text) . "</td><td></td><td></td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Past Medical History -->
    <h2>Past medical history</h2>
    <div style="margin-bottom: 10px;">
        <h3>MAJOR EVENTS</h3>
        <div><?php 
            $pmh = $report_data['past_medical_history'];
            // Try to decode if JSON, else print
            $pmh_json = json_decode($pmh, true);
            echo !empty($pmh_json['conditions']) ? render_clinical_text($pmh_json['conditions']) : render_clinical_text($pmh);
        ?></div>
    </div>
    <div style="margin-bottom: 10px;">
        <h3>SOCIAL HISTORY</h3>
        <div><?php echo !empty($report_data['social_history']) ? render_clinical_text($report_data['social_history']) : 'No social history recorded.'; ?></div>
    </div>

    <!-- Subjective -->
    <h2>Subjective</h2>
    <div style="margin-bottom: 10px;">
        <div><span class="bold">History of present illness:</span> <?php echo !empty($hpi_narrative_data['narrative_text']) ? render_clinical_text($hpi_narrative_data['narrative_text']) : 'See SOAP note below.'; ?></div>
    </div>
    <div style="margin-bottom: 10px;">
        <h3>REVIEW OF SYSTEM</h3>
        <div><?php echo !empty($note_data['subjective']) ? render_clinical_text($note_data['subjective']) : 'Review of systems completed as per HPI.'; ?></div>
    </div>

    <!-- Objective -->
    <h2>Objective</h2>
    <div style="margin-bottom: 10px;">
        <div><?php echo !empty($note_data['objective']) ? render_clinical_text($note_data['objective']) : 'Physical exam findings noted below.'; ?></div>
    </div>
    
    <!-- Assessment -->
    <h2>Assessment</h2>
    <div style="margin-bottom: 15px;">
        <div><?php echo !empty($note_data['assessment']) ? render_clinical_text($note_data['assessment']) : ''; ?></div>
    </div>

    <!-- Wound Assessments Loop -->
    <?php 
    // Group assessments by wound_id
    $wounds_grouped = [];
    foreach ($assessments_data as $asm) {
        $wounds_grouped[$asm['wound_id']][] = $asm;
    }

    foreach ($wounds_grouped as $wound_id => $asms): 
        // Use the first assessment to get general wound info
        $first_asm = $asms[0];
    ?>
    <div class="wound-block" style="page-break-inside: avoid; border-left: 4px solid #333; padding-left: 15px; margin-bottom: 25px;">
        <!-- Wound Header -->
        <div style="background: #eee; padding: 5px 10px; margin-bottom: 10px; border-bottom: 1px solid #ccc;">
            <h3 style="margin: 0; color: #333;">
                Wound: <?php echo htmlspecialchars($first_asm['location']); ?> 
                <span style="font-weight: normal; font-size: 11px; margin-left: 10px;">(<?php echo htmlspecialchars($first_asm['wound_type']); ?>)</span>
            </h3>
            <div style="font-size: 10px; color: #555;">Onset: <?php echo htmlspecialchars($first_asm['date_onset']); ?></div>
        </div>

        <?php foreach ($asms as $index => $asm): 
            // Determine Assessment Label (Pre/Post) based on linked images or order
            $asm_label = "Assessment " . ($index + 1);
            $label_style = "background: #f9f9f9; color: #555;";
            
            // Check images for this specific assessment
            $this_asm_images = array_filter($images_data, function($img) use ($asm) {
                return $img['assessment_id'] == $asm['assessment_id'];
            });
            
            $has_pre = false;
            $has_post = false;
            foreach ($this_asm_images as $img) {
                if (stripos($img['image_type'], 'Pre') !== false) $has_pre = true;
                if (stripos($img['image_type'], 'Post') !== false) $has_post = true;
            }

            if ($has_pre && !$has_post) {
                $asm_label = "PRE-DEBRIDEMENT ASSESSMENT";
                $label_style = "background: #fff7ed; color: #9a3412; border: 1px solid #fdba74;"; // Orange theme
            } elseif ($has_post && !$has_pre) {
                $asm_label = "POST-DEBRIDEMENT ASSESSMENT";
                $label_style = "background: #f0fdf4; color: #166534; border: 1px solid #86efac;"; // Green theme
            } elseif ($has_pre && $has_post) {
                $asm_label = "ASSESSMENT (Pre & Post)";
            }
            
            // Fallback: If no images, maybe check debridement_performed flag?
            if (empty($this_asm_images) && $asm['debridement_performed'] === 'Yes') {
                 // If debridement was performed in this assessment record, it's likely the "Procedure" record.
                 // But usually the assessment data (dimensions) is Pre-Debridement.
            }
        ?>
        
        <div style="margin-bottom: 15px; <?php echo $label_style; ?> padding: 10px; border-radius: 4px;">
            <div style="font-weight: bold; font-size: 11px; margin-bottom: 8px; text-transform: uppercase; border-bottom: 1px solid #ddd; padding-bottom: 4px;">
                <?php echo $asm_label; ?>
            </div>

            <!-- Flex Container for Details + Images -->
            <div style="display: flex; gap: 20px; align-items: flex-start;">
                
                <!-- Left Column: Assessment Data -->
                <div style="flex: 1;">
                    <div class="wound-detail-row"><div class="wound-label">Dimensions:</div><div class="wound-value"><?php echo "{$asm['length_cm']} x {$asm['width_cm']} x {$asm['depth_cm']} cm (Area: " . number_format($asm['length_cm'] * $asm['width_cm'], 2) . " cm²)"; ?></div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Tunneling:</div><div class="wound-value"><?php echo format_location_details($asm['tunneling_present'] ?? 'No', $asm['tunneling_locations'] ?? ''); ?></div></div>
                    <div class="wound-detail-row"><div class="wound-label">Undermining:</div><div class="wound-value"><?php echo format_location_details($asm['undermining_present'] ?? 'No', $asm['undermining_locations'] ?? ''); ?></div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Tissue composition:</div><div class="wound-value"><?php echo "{$asm['granulation_percent']}% Granulation, {$asm['slough_percent']}% Slough, " . ($asm['eschar_percent'] ?? 0) . "% Eschar"; ?></div></div>
                    <?php if (!empty($asm['granulation_color'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">Granulation Detail:</div><div class="wound-value"><?php echo htmlspecialchars($asm['granulation_color']) . " (" . htmlspecialchars($asm['granulation_coverage']) . ")"; ?></div></div>
                    <?php endif; ?>
                    
                    <div class="wound-detail-row"><div class="wound-label">Exposed Structures:</div><div class="wound-value"><?php echo format_json_list($asm['exposed_structures'] ?? ''); ?></div></div>
                    
                    <?php 
                        // Check for Exposed Bone specifically to highlight it
                        $exposed_structs = $asm['exposed_structures'] ?? '';
                        $has_bone = false;
                        if (!empty($exposed_structs)) {
                            $decoded = json_decode($exposed_structs, true);
                            if (is_array($decoded) && in_array('Bone', $decoded)) {
                                $has_bone = true;
                            } elseif (strpos($exposed_structs, 'Bone') !== false) {
                                $has_bone = true;
                            }
                        }
                    ?>
                    <?php if ($has_bone): ?>
                    <div class="wound-detail-row" style="color: #b91c1c; font-weight: bold; background-color: #fef2f2; padding: 2px;"><div class="wound-label">CRITICAL FINDING:</div><div class="wound-value">EXPOSED BONE PRESENT - Monitor for Osteomyelitis</div></div>
                    <?php endif; ?>

                    <div class="wound-detail-row"><div class="wound-label">Peri wound:</div><div class="wound-value"><?php echo format_json_list($asm['periwound_condition']); ?></div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Drainage:</div><div class="wound-value"><?php echo htmlspecialchars($asm['exudate_amount']) . " amount, " . htmlspecialchars($asm['drainage_type']); ?></div></div>
                    <div class="wound-detail-row"><div class="wound-label">Odor:</div><div class="wound-value"><?php echo htmlspecialchars($asm['odor_present']); ?></div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Infection Signs:</div><div class="wound-value"><?php echo format_json_list($asm['signs_of_infection']); ?></div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Pain Level:</div><div class="wound-value"><?php echo htmlspecialchars($asm['pain_level'] ?? 'Not recorded'); ?>/10</div></div>
                    
                    <div class="wound-detail-row"><div class="wound-label">Scores:</div><div class="wound-value">Braden: <?php echo htmlspecialchars($asm['braden_score'] ?? 'N/A'); ?>, PUSH: <?php echo htmlspecialchars($asm['push_score'] ?? 'N/A'); ?></div></div>
                    
                    <?php if (!empty($asm['risk_factors'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">Risk Factors:</div><div class="wound-value"><?php echo htmlspecialchars($asm['risk_factors']); ?></div></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($asm['nutritional_status'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">Nutrition:</div><div class="wound-value"><?php echo htmlspecialchars($asm['nutritional_status']); ?></div></div>
                    <?php endif; ?>

                    <?php if (!empty($asm['pre_debridement_notes'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">Observations:</div><div class="wound-value"><?php echo htmlspecialchars($asm['pre_debridement_notes']); ?></div></div>
                    <?php endif; ?>

                    <?php if (!empty($asm['dvt_edema_notes'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">DVT/Edema:</div><div class="wound-value"><?php echo htmlspecialchars($asm['dvt_edema_notes']); ?></div></div>
                    <?php endif; ?>

                    <?php if (!empty($asm['medical_necessity'])): ?>
                    <div class="wound-detail-row"><div class="wound-label">Medical Necessity:</div><div class="wound-value"><?php echo htmlspecialchars($asm['medical_necessity']); ?></div></div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Images for THIS Assessment -->
                <?php 
                // Sort images: Pre-Debridement first, then Post-Debridement, then others
                usort($this_asm_images, function($a, $b) {
                    $typeA = $a['image_type'] ?? '';
                    $typeB = $b['image_type'] ?? '';
                    
                    $scoreA = 2;
                    if (stripos($typeA, 'Pre') !== false) $scoreA = 0;
                    elseif (stripos($typeA, 'Post') !== false) $scoreA = 1;
                    
                    $scoreB = 2;
                    if (stripos($typeB, 'Pre') !== false) $scoreB = 0;
                    elseif (stripos($typeB, 'Post') !== false) $scoreB = 1;
                    
                    return $scoreA - $scoreB;
                });

                if (!empty($this_asm_images)): 
                ?>
                <div style="width: 260px; flex-shrink: 0; display: flex; flex-direction: column; gap: 15px;">
                    <?php foreach ($this_asm_images as $img): 
                        $type = $img['image_type'] ?: 'Wound Image';
                        $label_color = '#333';
                        if (stripos($type, 'Pre') !== false) $label_color = '#b45309'; // Dark Orange
                        if (stripos($type, 'Post') !== false) $label_color = '#047857'; // Dark Green
                    ?>
                    <div style="text-align: center; border: 1px solid #ddd; padding: 5px; background: #fff; border-radius: 4px;">
                        <div style="font-size: 11px; margin-bottom: 4px; font-weight: bold; color: <?php echo $label_color; ?>; text-transform: uppercase;">
                            <?php echo htmlspecialchars($type); ?>
                        </div>
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Wound Image" style="width: 100%; height: auto; display: block; border: 1px solid #eee;">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Debridement Note Generation (Full Width) -->
            <?php if ($asm['debridement_performed'] === 'Yes'): 
                // Determine label based on type
                $deb_type_lower = strtolower($asm['debridement_type'] ?? '');
                $deb_label = 'Surgical debridement'; // Default
                
                if (strpos($deb_type_lower, 'mechanical') !== false) $deb_label = 'Mechanical debridement';
                elseif (strpos($deb_type_lower, 'autolytic') !== false) $deb_label = 'Autolytic debridement';
                elseif (strpos($deb_type_lower, 'enzymatic') !== false) $deb_label = 'Enzymatic debridement';
                elseif (strpos($deb_type_lower, 'biological') !== false) $deb_label = 'Biological debridement';
                elseif (strpos($deb_type_lower, 'maggot') !== false) $deb_label = 'Biological debridement';
                elseif (strpos($deb_type_lower, 'sharp') !== false) $deb_label = 'Sharp debridement';
            ?>
            <div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 5px;">
                <p class="bold"><?php echo $deb_label; ?> to <?php echo htmlspecialchars($asm['location']); ?>:</p>
                <?php 
                    // Use stored narrative if available, otherwise generate default
                    if (!empty($asm['debridement_narrative'])) {
                        echo '<p>' . nl2br(htmlspecialchars($asm['debridement_narrative'])) . '</p>';
                    } else {
                ?>
                <p>Discussed with patient the procedure today. The purpose of this <?php echo strtolower($deb_label); ?> is to remove dead or necrotic tissue and biofilm. 
                A <?php echo htmlspecialchars($asm['wound_type']); ?> was noted on the <?php echo htmlspecialchars($asm['location']); ?>. 
                <?php echo $deb_label; ?> of necrotic tissue was performed using <?php echo htmlspecialchars($asm['debridement_type']); ?>. 
                Patient tolerated the procedure well. Hemostasis was achieved.</p>
                <?php } ?>
            </div>
            <?php endif; ?>

            <!-- Graft Note Generation (Full Width) -->
            <?php if (!empty($asm['graft_product_name'])): ?>
            <div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 5px;">
                <p class="bold">Skin substitute application to <?php echo htmlspecialchars($asm['location']); ?>:</p>
                <p>Wound cleansed with wound cleanser and gently patted dry. Periwound protected. 
                Trimmed <?php echo htmlspecialchars($asm['graft_product_name']); ?> skin substitute to fit wound dimensions and applied per manufacturer's guidelines.
                Covered with secondary dressing. <span class="bold">Units used <?php echo htmlspecialchars($asm['graft_sqcm_used']); ?> cm²</span>.</p>
                
                <p class="bold"><?php echo htmlspecialchars($asm['graft_product_name']); ?> product details:</p>
                <ul style="list-style: none; margin-left: 0;">
                    <li>Serial: <?php echo htmlspecialchars($asm['graft_serial_number']); ?></li>
                    <li>Lot: <?php echo htmlspecialchars($asm['graft_lot_number']); ?></li>
                    <li>Exp date: <?php echo htmlspecialchars($asm['graft_expiration_date'] ?? 'N/A'); ?></li>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

    <!-- Procedure Note -->
    <?php if (!empty($note_data['procedure_note'])): ?>
    <h2>Procedure</h2>
    <div style="margin-bottom: 15px;">
        <div><?php echo render_clinical_text($note_data['procedure_note']); ?></div>
    </div>
    <?php endif; ?>

    <!-- Diagnoses -->
    <div style="margin-bottom: 15px;">
        <h3>Diagnoses attached to this encounter:</h3>
        <ul>
            <?php foreach ($diagnoses_data as $dx): ?>
            <li>
                <?php echo htmlspecialchars($dx['description']); ?> [ICD-10: <?php echo htmlspecialchars($dx['icd10_code']); ?>]
                <?php if (!empty($dx['notes'])): ?>
                    <br><span style="font-style: italic; color: #555; font-size: 11px;">Comment: <?php echo htmlspecialchars($dx['notes']); ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Plan -->
    <h2>Plan</h2>

    <?php 
    $has_wound_plans = false;
    foreach ($assessments_data as $asm) {
        if (!empty($asm['treatments_provided'])) {
            $has_wound_plans = true;
            break;
        }
    }
    
    if ($has_wound_plans): 
    ?>
    <div style="margin-bottom: 10px;">
        <h3>Wound Specific Treatment Plans:</h3>
        <?php foreach ($assessments_data as $asm): ?>
            <?php if (!empty($asm['treatments_provided'])): ?>
            <div style="margin-bottom: 8px; padding-left: 10px; border-left: 2px solid #eee;">
                <p class="bold">Plan for <?php echo htmlspecialchars($asm['location']); ?> (<?php echo htmlspecialchars($asm['wound_type']); ?>):</p>
                <div><?php echo nl2br(htmlspecialchars($asm['treatments_provided'])); ?></div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div style="margin-bottom: 10px;">
        <div><?php echo !empty($note_data['plan']) ? render_clinical_text($note_data['plan']) : ''; ?></div>
    </div>



    <div style="margin-bottom: 10px;">
        <p class="bold">I CERTIFY that based on my findings, the following services are medically necessary home health services.</p>
    </div>

    <div style="margin-bottom: 10px;">
        <h3>Orders for Skilled Nurse:</h3>
        <div><?php echo !empty($note_data['skilled_nurse_orders']) ? render_clinical_text($note_data['skilled_nurse_orders']) : 'Once a week. Monitor skin for any s/s of infection and refer accordingly. Continue to educate patient regarding diet and off loading the wound.'; ?></div>
    </div>

    <div style="margin-bottom: 10px;">
        <h3>Medications attached to this encounter:</h3>
        <?php if ($medications_data): ?>
        <ul>
            <?php foreach ($medications_data as $med): ?>
            <li><?php echo htmlspecialchars($med['drug_name']) . " " . htmlspecialchars($med['dosage']) . " " . htmlspecialchars($med['frequency']); ?> (Start: <?php echo htmlspecialchars($med['start_date']); ?>)</li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>No medications attached.</p>
        <?php endif; ?>
    </div>

    <div style="margin-bottom: 10px;">
        <h3>Orders</h3>
        <div style="background: #eee; padding: 2px 5px; font-weight: bold; font-size: 10px;">LAB ORDERS</div>
        <div><?php echo !empty($note_data['lab_orders']) ? render_clinical_text($note_data['lab_orders']) : 'No orders attached to this encounter.'; ?></div>
        <div style="background: #eee; padding: 2px 5px; font-weight: bold; font-size: 10px;">IMAGING ORDERS</div>
        <div><?php echo !empty($note_data['imaging_orders']) ? render_clinical_text($note_data['imaging_orders']) : 'No orders attached to this encounter.'; ?></div>
    </div>

    <!-- Quality of Care -->
    <h2>Quality of care</h2>
    <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
        Was diagnosis reconciliation completed?<br>
        <span class="bold">Yes, reconciliation performed</span>
    </div>
    <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
        Was medication allergy reconciliation completed?<br>
        <span class="bold">Yes, reconciliation performed</span>
    </div>
    <div style="border-bottom: 1px solid #eee; padding: 5px 0;">
        Was medication reconciliation completed?<br>
        <span class="bold">Yes, reconciliation performed</span>
    </div>

    <!-- Footer Signature -->
    <div class="signature-block">
        <p class="bold"><?php echo $clinician_full; ?></p>
        <p>Electronically signed by <?php echo $clinician_name; ?> at <?php echo $note_data['created_at'] ? date('m/d/Y h:i a', strtotime($note_data['created_at'])) : 'N/A'; ?></p>
    </div>

    <!-- Addendums -->
    <?php if (!empty($addendums_data)): ?>
    <div style="margin-top: 30px; border-top: 2px solid #000; padding-top: 15px;">
        <h2>Addendums</h2>
        <?php foreach ($addendums_data as $addendum): ?>
        <div style="margin-bottom: 15px; padding: 10px; background-color: #f9f9f9; border-left: 4px solid #555;">
            <div style="font-weight: bold; margin-bottom: 5px;">
                Addendum by <?php echo htmlspecialchars($addendum['author_name'] . ' ' . $addendum['author_creds']); ?> 
                on <?php echo date('m/d/Y h:i A', strtotime($addendum['created_at'])); ?>
            </div>
            <div><?php echo render_clinical_text($addendum['note_text']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

</body>
</html>