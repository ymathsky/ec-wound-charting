<?php
// Filename: visit_summary.php
// Purpose: Screen-friendly summary of the visit, structured exactly like the Visit Report.
// Updated: Added "Edit Mode" to directly modify clinical data.

require_once 'templates/header.php';
require_once 'db_connect.php';

// Check database connection
if ($conn->connect_error) {
    echo '<div class="p-4 bg-red-100 border border-red-400 text-red-700 m-4 rounded">
            <strong class="font-bold">Database Error:</strong>
            <span class="block sm:inline">Unable to connect to MySQL.</span>
          </div>';
    require_once 'templates/footer.php';
    exit;
}

// --- Quill Rich Text Editor ---
echo '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
echo '<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>';
echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js"></script>';
echo '<style>
    .ql-editor { font-size: 1.125rem; line-height: 1.75; min-height: 150px; } 
    .ql-container { font-family: inherit; height: auto !important; }
    .ql-toolbar { position: sticky; top: 0; z-index: 10; background: white; }
    /* Diff Styles */
    ins { background-color: #dcfce7; text-decoration: none; color: #166534; }
    del { background-color: #fee2e2; color: #991b1b; }
</style>';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician', 'facility'])) {
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 p-8'>Access Denied.</div></div>";
    require_once 'templates/footer.php';
    exit();
}
$user_role = $_SESSION['ec_role'];

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 p-8'>Invalid Patient or Appointment ID.</div></div>";
    require_once 'templates/footer.php';
    exit();
}

// --- Initialize data variables ---
$report_data = [];
$vitals_data = [];
$hpi_data = [];
$hpi_narrative_data = [];
$note_data = [];
$assessments_data = [];
$images_data = [];
$diagnoses_data = [];
$procedures_data = [];
$medications_data = [];
$addendums_data = [];

// --- Fetch all necessary data ---
try {
    // 1. Fetch Comprehensive Report Data
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

    // 2. Fetch Vitals
    $vitals_sql = "SELECT * FROM patient_vitals WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($vitals_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $vitals_data = $stmt->get_result()->fetch_assoc();
    if (!$vitals_data) $vitals_data = [];
    $stmt->close();

    // 3. Fetch HPI Narrative
    $hpi_sql = "SELECT * FROM visit_hpi_narratives WHERE appointment_id = ? LIMIT 1";
    $stmt = $conn->prepare($hpi_sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $hpi_narrative_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 4. Fetch Final Note
    $note_sql = "SELECT chief_complaint, subjective, objective, assessment, plan, procedure_note, lab_orders, imaging_orders, skilled_nurse_orders, signature_data, created_at, status, signed_at FROM visit_notes WHERE appointment_id = ? LIMIT 1";
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

    // 7. Fetch Diagnoses (Current Visit)
    $diag_sql = "SELECT icd10_code, description, is_primary, notes 
                 FROM visit_diagnoses 
                 WHERE appointment_id = ? 
                 ORDER BY is_primary DESC, icd10_code ASC";
    $diagnoses_data = [];
    $stmt = $conn->prepare($diag_sql);
    if ($stmt) {
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $diagnoses_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 7b. Fetch All Historical Diagnoses for Patient (for Orders)
    $all_patient_diagnoses = [];
    $all_diag_sql = "SELECT DISTINCT icd10_code, description 
                     FROM visit_diagnoses 
                     WHERE patient_id = ? 
                     ORDER BY created_at DESC";
    $stmt = $conn->prepare($all_diag_sql);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $all_patient_diagnoses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 8. Fetch Procedures (Superbill)
    $procedures_data = [];
    $proc_sql = "SELECT s.cpt_code, s.units, c.description
                 FROM superbill_services s
                 LEFT JOIN cpt_codes c ON s.cpt_code = c.code
                 WHERE s.appointment_id = ?";
    $stmt = $conn->prepare($proc_sql);
    if ($stmt) {
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $procedures_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 9. Fetch Active Medications
    $medications_data = [];
    $med_sql = "SELECT drug_name, dosage, frequency, status, start_date 
                FROM patient_medications 
                WHERE patient_id = ? AND status = 'Active'";
    $stmt = $conn->prepare($med_sql);
    if ($stmt) {
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $medications_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

    // 10. Fetch Addendums
    $addendums_data = [];
    $addendum_sql = "SELECT a.*, u.full_name as author_name, u.credentials as author_creds 
                     FROM visit_note_addendums a 
                     LEFT JOIN users u ON a.user_id = u.user_id 
                     WHERE a.appointment_id = ? 
                     ORDER BY a.created_at ASC";
    $stmt = $conn->prepare($addendum_sql);
    if ($stmt) {
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $addendums_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }

} catch (Exception $e) {
    // Handle error
}

// --- Helper Functions ---
function render_editable_text($text, $type, $field) {
    global $note_data;
    $is_finalized = isset($note_data['status']) && $note_data['status'] === 'finalized';

    // Remove XML declaration if present
    $text_clean = preg_replace('/<\?xml.*?\?>/s', '', $text);
    
    if (empty($text_clean) || trim($text_clean) === '') {
        $display_text = '<span class="text-gray-400 italic">None recorded</span>';
    } else {
        // Check if text contains HTML tags
        if ($text_clean != strip_tags($text_clean)) {
            // It has HTML, render as is (assuming trusted content)
            $display_text = $text_clean;
        } else {
            // Plain text, convert newlines and escape
            $display_text = nl2br(htmlspecialchars($text_clean));
        }
    }
    
    if ($is_finalized) {
        return "<div class='p-2 -ml-2'>$display_text</div>";
    }

    $extra_buttons = '';
    if ($field === 'subjective' || $field === 'objective') {
        $extra_buttons .= "
        <button class='insert-wnl-btn px-3 py-1 text-sm text-blue-600 hover:text-blue-800 bg-blue-50 border border-blue-200 rounded shadow-sm flex items-center mr-2' onclick='insertWNL(this, \"$field\")'>
            <i data-lucide='check-circle-2' class='w-3 h-3 mr-1'></i> Insert Normal
        </button>";
    }

    // Quick Insert Button
    $quick_insert_sections = ['subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'];
    if (in_array($field, $quick_insert_sections)) {
        $extra_buttons .= "
        <button type='button' class='quick-insert-btn px-3 py-1 text-sm text-indigo-600 hover:text-indigo-800 bg-indigo-50 border border-indigo-200 rounded shadow-sm flex items-center mr-auto' data-section='$field'>
            <i data-lucide='list-plus' class='w-3 h-3 mr-1'></i> Quick Insert
        </button>";
    }

    return "
    <div class='editable-container group relative' data-type='$type' data-field='$field'>
        <!-- View Mode -->
        <div class='editable-view p-2 -ml-2 rounded border border-transparent group-hover:border-gray-200 transition-colors relative'>
            $display_text
            <!-- Edit Button (Visible on mobile, Hover Only on Desktop) -->
            <button class='edit-btn absolute top-0 right-0 p-1 bg-white border border-gray-200 rounded shadow-sm text-blue-600 opacity-100 md:opacity-0 md:group-hover:opacity-100 transition-opacity hover:bg-blue-50 z-10' title='Edit Section'>
                <i data-lucide='edit-2' class='w-4 h-4'></i> <span class='text-xs font-medium ml-1 hidden sm:inline'>Edit</span>
            </button>
        </div>

        <!-- Edit Mode -->
        <div class='editable-input hidden bg-white text-gray-900 border rounded-md shadow-sm mt-2'>
            <div class='quill-editor'>" . $text_clean . "</div>
            <div class='flex flex-col md:flex-row justify-end space-y-2 md:space-y-0 md:space-x-2 p-2 bg-gray-50 border-t rounded-b-md'>
                <div class='flex flex-wrap gap-2 mb-2 md:mb-0 mr-auto'>
                    $extra_buttons
                </div>
                <div class='flex space-x-2'>
                    <button class='cancel-btn px-3 py-1 text-sm text-gray-600 hover:text-gray-800 bg-white border border-gray-300 rounded shadow-sm flex-1 md:flex-none justify-center'>Cancel</button>
                    <button class='save-btn px-3 py-1 text-sm text-white bg-blue-600 hover:bg-blue-700 rounded shadow-sm flex items-center flex-1 md:flex-none justify-center'>
                        <i data-lucide='save' class='w-3 h-3 mr-1'></i> Save
                    </button>
                </div>
            </div>
        </div>
    </div>";
}

function calculateAge($dob) {
    if (!$dob) return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    return $birthDate->diff($today)->y . " yrs";
}

function format_json_list($json) {
    if (empty($json)) return 'None';
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

// --- Page Variables ---
$patient_name = $report_data ? "{$report_data['first_name']} {$report_data['last_name']}" : 'N/A';
$dob = $report_data['date_of_birth'] ? date('m/d/Y', strtotime($report_data['date_of_birth'])) : 'N/A';
$age = calculateAge($report_data['date_of_birth']);
$dos = $report_data['appointment_date'] ? date('m/d/Y', strtotime($report_data['appointment_date'])) : 'N/A';
$clinician = $report_data['clinician_name'] ?? 'Unassigned';

?>

<!-- Include Lucide Icons -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<div class="flex h-screen bg-gray-50 font-sans">
    <?php require_once 'templates/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Header -->
        <header class="w-full bg-white p-4 flex flex-col md:flex-row justify-between items-center shadow-sm border-b border-gray-200 z-10 gap-4">
            <div class="flex items-center w-full md:w-auto">
                <!-- Mobile Menu Button -->
                <button onclick="openSidebar()" class="md:hidden mr-3 text-gray-600 hover:text-gray-900 focus:outline-none">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                
                <div class="flex flex-col">
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800 flex items-center">
                        <i data-lucide="file-text" class="w-6 h-6 md:w-8 md:h-8 mr-2 md:mr-3 text-indigo-600"></i>
                        <span class="hidden md:inline">Visit Note (Simplified Mode)</span>
                        <span class="md:hidden">Visit Note</span>
                    </h1>
                    <!-- Mobile Only Patient Info -->
                    <div class="md:hidden text-xs text-gray-500 mt-1 ml-8">
                        <?php echo $patient_name; ?> • <?php echo $dos; ?>
                    </div>
                </div>

                <!-- Desktop Patient Info -->
                <div class="hidden md:block ml-6 border-l pl-6">
                    <p class="text-sm text-gray-500">Patient</p>
                    <p class="font-semibold text-gray-900"><?php echo $patient_name; ?> (<?php echo $age; ?>)</p>
                </div>
                <div class="hidden md:block ml-6 border-l pl-6">
                    <p class="text-sm text-gray-500">Date of Service</p>
                    <p class="font-semibold text-gray-900"><?php echo $dos; ?></p>
                </div>
            </div>
            
            <div class="flex space-x-2 w-full md:w-auto justify-end">
                <a href="visit_notes.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>" class="flex-1 md:flex-none justify-center flex items-center px-3 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors shadow-sm text-sm">
                    <i data-lucide="arrow-right-circle" class="w-4 h-4 mr-2 text-blue-600"></i> <span class="hidden sm:inline">Advanced Mode</span><span class="sm:hidden">Advanced</span>
                </a>
                <a href="visit_report.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>" target="_blank" class="flex-none flex items-center px-3 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors shadow-sm text-sm">
                    <i data-lucide="printer" class="w-4 h-4 md:mr-2"></i> <span class="hidden md:inline">Print</span>
                </a>
            </div>
        </header>

        <!-- Sticky Navigation Bar -->
        <div class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm px-4 md:px-6 py-2 flex space-x-4 overflow-x-auto">
            <a href="#section-vitals" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Vitals</a>
            <a href="#section-subjective" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Subjective</a>
            <a href="#section-objective" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Objective</a>
            <a href="#section-assessment" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Assessment</a>
            <a href="#section-wounds" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Wounds</a>
            <a href="#section-plan" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Plan</a>
            <a href="#section-orders" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Orders</a>
            <a href="#section-medications" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Medications</a>
            <a href="#section-signatures" class="text-sm font-medium text-gray-600 hover:text-indigo-600 whitespace-nowrap">Signatures</a>
        </div>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6" id="summary-content">
            <div class="max-w-5xl mx-auto space-y-6 md:space-y-8">
                
                <!-- 1. Header Info -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Patient</h3>
                            <p class="font-bold text-gray-900"><?php echo $patient_name; ?></p>
                            <p class="text-sm text-gray-600">DOB: <?php echo $dob; ?> (<?php echo $age; ?>)</p>
                            <p class="text-sm text-gray-600">MRN: <?php echo $report_data['patient_code'] ?? 'N/A'; ?></p>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Facility</h3>
                            <p class="font-bold text-gray-900"><?php echo $report_data['facility_name'] ?? 'Expert Care'; ?></p>
                            <p class="text-sm text-gray-600"><?php echo $report_data['facility_email'] ?? ''; ?></p>
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Encounter</h3>
                            <p class="font-bold text-gray-900">Home Visit - SOAP Note</p>
                            <p class="text-sm text-gray-600">Seen by: <?php echo $clinician; ?></p>
                            <p class="text-sm text-gray-600">Date: <?php echo $dos; ?></p>
                        </div>
                    </div>
                </div>

                <!-- 2. Chief Complaint -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Chief Complaint</h2>
                    <?php echo render_editable_text($note_data['chief_complaint'] ?? '', 'visit_notes', 'chief_complaint'); ?>
                </div>

                <!-- 3. Vitals -->
                <div id="section-vitals" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-5 border-b pb-2">Vitals</h2>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <!-- BP -->
                        <div class="bg-blue-50 p-3 rounded-lg text-center editable-container" data-type="vitals" data-field="blood_pressure">
                            <span class="block text-xs text-blue-600 font-medium">BP</span>
                            <div class="editable-view text-lg font-bold text-blue-900"><?php echo $vitals_data['blood_pressure'] ?? '--/--'; ?></div>
                            <input type="text" class="editable-input hidden w-full text-center border rounded px-1 text-sm" value="<?php echo htmlspecialchars($vitals_data['blood_pressure'] ?? ''); ?>">
                        </div>
                        <!-- HR -->
                        <div class="bg-red-50 p-3 rounded-lg text-center editable-container" data-type="vitals" data-field="heart_rate">
                            <span class="block text-xs text-red-600 font-medium">HR</span>
                            <div class="editable-view text-lg font-bold text-red-900"><?php echo $vitals_data['heart_rate'] ?? '--'; ?> <span class="text-xs">bpm</span></div>
                            <input type="number" class="editable-input hidden w-full text-center border rounded px-1 text-sm" value="<?php echo htmlspecialchars($vitals_data['heart_rate'] ?? ''); ?>">
                        </div>
                        <!-- Resp -->
                        <div class="bg-green-50 p-3 rounded-lg text-center editable-container" data-type="vitals" data-field="respiratory_rate">
                            <span class="block text-xs text-green-600 font-medium">Resp</span>
                            <div class="editable-view text-lg font-bold text-green-900"><?php echo $vitals_data['respiratory_rate'] ?? '--'; ?> <span class="text-xs">bpm</span></div>
                            <input type="number" class="editable-input hidden w-full text-center border rounded px-1 text-sm" value="<?php echo htmlspecialchars($vitals_data['respiratory_rate'] ?? ''); ?>">
                        </div>
                        <!-- Temp -->
                        <div class="bg-orange-50 p-3 rounded-lg text-center editable-container" data-type="vitals" data-field="temperature_celsius" data-transform="f_to_c">
                            <span class="block text-xs text-orange-600 font-medium">Temp</span>
                            <?php 
                                $temp_c = $vitals_data['temperature_celsius'] ?? null;
                                $temp_f = ($temp_c !== null && $temp_c !== '') ? number_format(($temp_c * 9/5) + 32, 1) : '';
                            ?>
                            <div class="editable-view text-lg font-bold text-orange-900">
                                <?php echo $temp_f ?: '--'; ?> <span class="text-xs">°F</span>
                            </div>
                            <input type="number" step="0.1" class="editable-input hidden w-full text-center border rounded px-1 text-sm" value="<?php echo $temp_f; ?>">
                        </div>
                        <!-- SpO2 -->
                        <div class="bg-purple-50 p-3 rounded-lg text-center editable-container" data-type="vitals" data-field="oxygen_saturation">
                            <span class="block text-xs text-purple-600 font-medium">SpO2</span>
                            <div class="editable-view text-lg font-bold text-purple-900"><?php echo $vitals_data['oxygen_saturation'] ?? '--'; ?> <span class="text-xs">%</span></div>
                            <input type="number" class="editable-input hidden w-full text-center border rounded px-1 text-sm" value="<?php echo htmlspecialchars($vitals_data['oxygen_saturation'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- 4. Allergies (Read Only for now) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Allergies</h2>
                    <div class="text-red-600 font-medium">
                        <?php echo !empty($report_data['allergies']) ? htmlspecialchars($report_data['allergies']) : 'No Known Drug Allergies'; ?>
                    </div>
                </div>

                <!-- 5. Past Medical History (Read Only) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Past Medical History</h2>
                    <div class="text-gray-700">
                        <?php 
                            $pmh = $report_data['past_medical_history'];
                            $pmh_json = json_decode($pmh, true);
                            echo !empty($pmh_json['conditions']) ? nl2br(htmlspecialchars($pmh_json['conditions'])) : nl2br(htmlspecialchars($pmh));
                        ?>
                    </div>
                </div>

                <!-- 6. Social History (Read Only) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Social History</h2>
                    <div class="text-gray-700"><?php echo nl2br(htmlspecialchars($report_data['social_history'])); ?></div>
                </div>

                <!-- 7. Subjective -->
                <div id="section-subjective" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Subjective</h2>
                    
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-1">History of Present Illness</h4>
                        <?php echo render_editable_text($hpi_narrative_data['narrative_text'] ?? '', 'hpi', 'narrative_text'); ?>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-1">Review of Systems</h4>
                        <?php echo render_editable_text($note_data['subjective'] ?? '', 'visit_notes', 'subjective'); ?>
                    </div>
                </div>

                <!-- 8. Objective -->
                <div id="section-objective" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Objective</h2>
                    <?php echo render_editable_text($note_data['objective'] ?? '', 'visit_notes', 'objective'); ?>
                </div>

                <!-- 9. Assessment -->
                <div id="section-assessment" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Assessment</h2>
                    <?php echo render_editable_text($note_data['assessment'] ?? '', 'visit_notes', 'assessment'); ?>
                </div>

                <!-- 10. Wound Assessments (Read Only - Complex Data) -->
                <?php if (!empty($assessments_data)): ?>
                <div id="section-wounds" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-5 border-b pb-2">Wound Assessments</h2>
                    <div class="space-y-6">
                        <?php 
                        $wounds_grouped = [];
                        foreach ($assessments_data as $asm) {
                            $wounds_grouped[$asm['wound_id']][] = $asm;
                        }
                        foreach ($wounds_grouped as $wound_id => $asms): 
                            $first_asm = $asms[0];
                        ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden">
                            <div class="bg-indigo-50 px-4 py-2 border-b border-indigo-100 flex justify-between items-center">
                                <h3 class="font-bold text-indigo-800"><?php echo htmlspecialchars($first_asm['location']); ?></h3>
                                <span class="text-xs text-indigo-600 bg-white px-2 py-1 rounded border border-indigo-100"><?php echo htmlspecialchars($first_asm['wound_type']); ?></span>
                            </div>
                            <div class="p-4">
                                <?php foreach ($asms as $asm): 
                                    $this_asm_images = array_filter($images_data, function($img) use ($asm) {
                                        return $img['assessment_id'] == $asm['assessment_id'];
                                    });
                                ?>
                                <div class="mb-6 last:mb-0">
                                    <div class="flex justify-between items-start mb-2">
                                        <div class="text-sm">
                                            <span class="font-bold text-gray-700">Dimensions:</span> 
                                            <?php echo "{$asm['length_cm']} x {$asm['width_cm']} x {$asm['depth_cm']} cm"; ?>
                                        </div>
                                        <?php if ($asm['debridement_performed'] === 'Yes'): ?>
                                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold">Debridement Performed</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600 mb-3">
                                        <div><span class="font-medium">Tissue:</span> <?php echo "{$asm['granulation_percent']}% Gran, {$asm['slough_percent']}% Slough"; ?></div>
                                        <div><span class="font-medium">Drainage:</span> <?php echo "{$asm['exudate_amount']} {$asm['drainage_type']}"; ?></div>
                                        <div><span class="font-medium">Peri-wound:</span> <?php echo format_json_list($asm['periwound_condition']); ?></div>
                                        <div><span class="font-medium">Infection:</span> <?php echo format_json_list($asm['signs_of_infection']); ?></div>
                                        <div><span class="font-medium">Tunneling:</span> <?php echo format_location_details($asm['tunneling_present'] ?? 'No', $asm['tunneling_locations'] ?? ''); ?></div>
                                        <div><span class="font-medium">Undermining:</span> <?php echo format_location_details($asm['undermining_present'] ?? 'No', $asm['undermining_locations'] ?? ''); ?></div>
                                    </div>

                                    <?php if (!empty($this_asm_images)): ?>
                                    <div class="flex gap-2 overflow-x-auto pb-2">
                                        <?php foreach ($this_asm_images as $img): ?>
                                        <div class="flex-shrink-0 w-32 h-32 border rounded bg-gray-100 relative group">
                                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full h-full object-cover rounded" alt="Wound">
                                            <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-[10px] p-1 truncate text-center">
                                                <?php echo htmlspecialchars($img['image_type']); ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 11. Procedure Note -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Procedure Note</h2>
                    <?php echo render_editable_text($note_data['procedure_note'] ?? '', 'visit_notes', 'procedure_note'); ?>
                </div>

                <!-- 12. Diagnoses (Read Only) -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Diagnoses</h2>
                    <ul class="space-y-2">
                        <?php foreach ($diagnoses_data as $dx): ?>
                        <li class="text-sm border-b border-gray-50 last:border-0 pb-2 last:pb-0">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($dx['icd10_code']); ?></div>
                            <div class="text-gray-600"><?php echo htmlspecialchars($dx['description']); ?></div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($diagnoses_data)) echo '<li class="text-sm text-gray-400 italic">No diagnoses recorded.</li>'; ?>
                    </ul>
                </div>

                <!-- 13. Plan -->
                <div id="section-plan" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 md:p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Plan</h2>
                    
                    <!-- Wound Specific Plans (Read Only) -->
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
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-2">Wound Specific Treatment Plans</h4>
                        <?php foreach ($assessments_data as $asm): ?>
                            <?php if (!empty($asm['treatments_provided'])): ?>
                            <div class="mb-2 pl-3 border-l-2 border-indigo-200">
                                <p class="font-bold text-xs text-indigo-700 mb-1">Plan for <?php echo htmlspecialchars($asm['location']); ?>:</p>
                                <div class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($asm['treatments_provided'])); ?></div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- General Plan -->
                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-1">General Plan</h4>
                        <?php echo render_editable_text($note_data['plan'] ?? '', 'visit_notes', 'plan'); ?>
                    </div>
                </div>

                <!-- 14. Orders -->
                <div id="section-orders" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 scroll-mt-24">
                    <div class="flex justify-between items-center mb-3 border-b pb-2">
                        <h2 class="text-2xl font-bold text-gray-800">Orders</h2>
                        <div class="flex space-x-2">
                            <a href="print_order.php?appointment_id=<?php echo $appointment_id; ?>&type=lab_orders" target="_blank" class="text-sm bg-white border border-gray-300 text-gray-700 px-3 py-1 rounded hover:bg-gray-50 transition flex items-center">
                                <i data-lucide="printer" class="w-4 h-4 mr-1"></i> Print Labs
                            </a>
                            <button onclick="openOrderModal()" class="text-sm bg-indigo-600 text-white px-3 py-1 rounded hover:bg-indigo-700 transition flex items-center">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-1"></i> Add Order
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-1">Skilled Nurse Orders</h4>
                        <?php echo render_editable_text($note_data['skilled_nurse_orders'] ?? '', 'visit_notes', 'skilled_nurse_orders'); ?>
                    </div>

                    <div class="mb-4">
                        <h4 class="text-sm font-bold text-gray-700 mb-1">Lab Orders</h4>
                        <?php echo render_editable_text($note_data['lab_orders'] ?? '', 'visit_notes', 'lab_orders'); ?>
                    </div>

                    <div>
                        <h4 class="text-sm font-bold text-gray-700 mb-1">Imaging Orders</h4>
                        <?php echo render_editable_text($note_data['imaging_orders'] ?? '', 'visit_notes', 'imaging_orders'); ?>
                    </div>
                </div>

                <!-- 15. Medications (Read Only) -->
                <div id="section-medications" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Medications</h2>
                    <ul class="space-y-2">
                        <?php foreach ($medications_data as $med): ?>
                        <li class="text-sm border-b border-gray-50 last:border-0 pb-2 last:pb-0">
                            <div class="font-medium text-gray-900"><?php echo htmlspecialchars($med['drug_name']); ?></div>
                            <div class="text-gray-600 text-xs"><?php echo htmlspecialchars($med['dosage'] . ' ' . $med['frequency']); ?></div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($medications_data)) echo '<li class="text-sm text-gray-400 italic">No medications recorded.</li>'; ?>
                    </ul>
                </div>

                <!-- 16. Signatures -->
                <div id="section-signatures" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-800 mb-4 border-b pb-2">Signatures</h2>
                    
                    <?php if (isset($note_data['status']) && $note_data['status'] === 'finalized'): ?>
                        <div class="mb-4">
                            <p class="text-sm text-gray-600 mb-1">
                                Signed by <?php echo htmlspecialchars($clinician); ?> 
                                on <?php echo date('m/d/Y h:i A', strtotime($note_data['signed_at'] ?? $note_data['created_at'])); ?>
                            </p>
                            <?php if (!empty($note_data['signature_data'])): ?>
                                <img src="<?php echo $note_data['signature_data']; ?>" alt="Clinician Signature" class="border border-gray-200 rounded p-2 bg-white max-w-xs">
                            <?php else: ?>
                                <p class="text-gray-500 italic text-sm">Electronically Signed</p>
                            <?php endif; ?>
                            <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded text-green-800 text-sm font-medium flex items-center">
                                <i data-lucide="lock" class="w-4 h-4 mr-2"></i> Note Finalized
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <p class="text-sm text-gray-700 font-medium mb-2">Sign Note:</p>
                            <div class="signature-wrapper border border-gray-300 rounded shadow-inner bg-white mx-auto mb-3" style="width: 100%; max-width: 500px; height: 150px; position: relative;">
                                <canvas id="signature-pad" class="w-full h-full cursor-crosshair" width="500" height="150"></canvas>
                            </div>
                            <div class="flex justify-between items-center">
                                <button type="button" id="clear-signature" class="text-sm text-red-600 hover:text-red-800 underline flex items-center">
                                    <i data-lucide="eraser" class="w-3 h-3 mr-1"></i> Clear
                                </button>
                                <button type="button" id="save-signature-btn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded shadow flex items-center text-sm">
                                    <i data-lucide="pen-tool" class="w-4 h-4 mr-2"></i> Sign & Finalize
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 17. Addendums -->
                <?php if (!empty($addendums_data)): ?>
                <div class="bg-yellow-50 rounded-xl shadow-sm border border-yellow-200 p-6">
                    <h2 class="text-2xl font-bold text-yellow-800 mb-4 border-b border-yellow-200 pb-2">Addendums</h2>
                    <?php foreach ($addendums_data as $addendum): ?>
                    <div class="bg-white p-4 rounded-lg border border-yellow-100 mb-3 last:mb-0">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-sm text-gray-800"><?php echo htmlspecialchars($addendum['author_name']); ?></span>
                            <span class="text-xs text-gray-500"><?php echo date('m/d/Y h:i A', strtotime($addendum['created_at'])); ?></span>
                        </div>
                        <div class="text-sm text-gray-700">
                            <?php 
                                $a_text = $addendum['note_text'];
                                // Remove XML declaration if present
                                $a_text = preg_replace('/<\?xml.*?\?>/s', '', $a_text);
                                // Render HTML if present, otherwise escape
                                echo (strip_tags($a_text) !== $a_text) ? $a_text : nl2br(htmlspecialchars($a_text)); 
                            ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<!-- Toast Notification -->
<style>
    #toast-notification {
        visibility: hidden;
        opacity: 0;
        transform: translateY(20px);
        transition: opacity 0.3s, transform 0.3s;
        z-index: 9999;
    }
    #toast-notification.show {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
    }
</style>
<div id="toast-notification" class="fixed bottom-5 right-5 pointer-events-none">
    <div class="bg-gray-800 text-white px-6 py-3 rounded-lg shadow-lg flex items-center space-x-3">
        <i data-lucide="check-circle" class="w-5 h-5 text-green-400"></i>
        <span class="font-medium">Changes saved successfully</span>
    </div>
</div>

<!-- AI Review Modal -->
<div id="ai-review-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Review AI Suggestions</h3>
                <button onclick="closeAiModal()" class="text-gray-400 hover:text-gray-500">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <div class="mt-4 max-h-96 overflow-y-auto">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 mb-2">Proposed Changes:</p>
                        <div id="ai-diff-content" class="p-4 bg-gray-50 rounded border text-base leading-relaxed whitespace-pre-wrap font-sans"></div>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3 pt-4 border-t">
                <button onclick="closeAiModal()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 font-medium">
                    Discard
                </button>
                <button id="ai-accept-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 font-medium flex items-center">
                    <i data-lucide="check" class="w-4 h-4 mr-2"></i> Accept Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    const quillInstances = new Map();

    // Event Delegation for Edit Buttons
    document.addEventListener('click', function(e) {
        // Handle Edit Button Click
        const editBtn = e.target.closest('.edit-btn');
        if (editBtn) {
            const container = editBtn.closest('.editable-container');
            startEdit(container);
        }

        // Handle Cancel Button Click
        const cancelBtn = e.target.closest('.cancel-btn');
        if (cancelBtn) {
            const container = cancelBtn.closest('.editable-container');
            cancelEdit(container);
        }

        // Handle Save Button Click
        const saveBtn = e.target.closest('.save-btn');
        if (saveBtn) {
            const container = saveBtn.closest('.editable-container');
            saveSection(container);
        }
    });

    let recognition;
    let currentQuill;
    let currentButton;

    function setupRecognition() {
        if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) return null;
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const rec = new SpeechRecognition();
        rec.continuous = true;
        rec.interimResults = false;
        rec.lang = 'en-US';

        rec.onresult = (event) => {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    transcript += event.results[i][0].transcript;
                }
            }
            
            if (transcript && currentQuill) {
                const range = currentQuill.getSelection(true);
                if (range) {
                    // Insert text with a trailing space
                    currentQuill.insertText(range.index, transcript + ' ');
                    currentQuill.setSelection(range.index + transcript.length + 1);
                }
            }
        };

        rec.onerror = (event) => {
            console.error("Speech recognition error", event.error);
            stopDictation();
        };

        rec.onend = () => {
            // Auto-restart if it wasn't manually stopped? 
            // For now, let's just let it stop to avoid infinite loops if there's an error.
            if (currentButton && currentButton.classList.contains('listening')) {
                 stopDictation();
            }
        };
        
        return rec;
    }

    function toggleDictation(quill, button) {
        if (!recognition) {
            recognition = setupRecognition();
            if (!recognition) {
                alert("Voice dictation is not supported in this browser. Please use Chrome, Edge, or Safari.");
                return;
            }
        }

        if (currentButton === button && button.classList.contains('listening')) {
            stopDictation();
        } else {
            if (currentButton) stopDictation();
            
            currentQuill = quill;
            currentButton = button;
            
            try {
                recognition.start();
                button.classList.add('listening', 'text-red-600');
                button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 2v20"/><path d="M6 12h16"/><path d="M22 2v20"/></svg>'; // Stop icon (roughly) or just keep mic and animate
                // Better: Mic Off icon or pulsing Mic
                button.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="red" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="animate-pulse"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>';
            } catch (e) {
                console.error(e);
            }
        }
    }

    function stopDictation() {
        if (recognition) recognition.stop();
        if (currentButton) {
            currentButton.classList.remove('listening', 'text-red-600');
            // Reset to Mic Icon
            currentButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>';
            currentButton = null;
            currentQuill = null;
        }
    }

    // AI Modal Logic
    function closeAiModal() {
        document.getElementById('ai-review-modal').classList.add('hidden');
    }

    async function rewriteWithAI(quill, button) {
        const originalIcon = button.innerHTML;
        
        // Get text to rewrite (selection or full text)
        const range = quill.getSelection();
        let textToRewrite = '';
        let isSelection = false;

        if (range && range.length > 0) {
            textToRewrite = quill.getText(range.index, range.length);
            isSelection = true;
        } else {
            textToRewrite = quill.getText();
        }

        if (!textToRewrite.trim()) {
            alert("Please type some text first.");
            return;
        }

        // Show loading state
        button.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin text-indigo-600"></i>';
        lucide.createIcons();
        button.disabled = true;

        try {
            const response = await fetch('api/ai_rewrite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: textToRewrite })
            });

            const result = await response.json();

            if (result.success) {
                const rewrittenText = result.rewritten_text;
                
                // Compute Diff
                const dmp = new diff_match_patch();
                const diffs = dmp.diff_main(textToRewrite, rewrittenText);
                dmp.diff_cleanupSemantic(diffs);
                const html = dmp.diff_prettyHtml(diffs);

                // Show Modal
                const modal = document.getElementById('ai-review-modal');
                const content = document.getElementById('ai-diff-content');
                const acceptBtn = document.getElementById('ai-accept-btn');

                content.innerHTML = html;
                modal.classList.remove('hidden');

                // Set Accept Action
                acceptBtn.onclick = function() {
                    if (isSelection) {
                        quill.deleteText(range.index, range.length);
                        quill.insertText(range.index, rewrittenText);
                    } else {
                        quill.setText(rewrittenText);
                    }
                    showToast('Text rewritten professionally');
                    closeAiModal();
                };

            } else {
                alert('AI Error: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error(error);
            alert('Network error while contacting AI.');
        } finally {
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    }

    function startEdit(container) {
        const view = container.querySelector('.editable-view');
        const inputWrapper = container.querySelector('.editable-input');
        
        if (view && inputWrapper) {
            view.classList.add('hidden');
            inputWrapper.classList.remove('hidden');

            // Initialize Quill if this is a rich text field
            const quillElement = inputWrapper.querySelector('.quill-editor');
            if (quillElement && !quillInstances.has(container)) {
                const quill = new Quill(quillElement, {
                    theme: 'snow',
                    modules: {
                        toolbar: {
                            container: [
                                ['bold', 'italic', 'underline'],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['clean'],
                                ['undo', 'redo'],
                                ['mic'], // Custom Voice Dictation Button
                                ['ai-magic'], // Custom AI Button
                                ['save-template', 'load-template'] // Custom Template Buttons
                            ],
                            handlers: {
                                'undo': function() {
                                    this.quill.history.undo();
                                },
                                'redo': function() {
                                    this.quill.history.redo();
                                },
                                'mic': function() {
                                    const btn = this.container.querySelector('.ql-mic');
                                    toggleDictation(this.quill, btn);
                                },
                                'ai-magic': function() {
                                    const btn = this.container.querySelector('.ql-ai-magic');
                                    rewriteWithAI(this.quill, btn);
                                },
                                'save-template': function() {
                                    openSaveTemplateModal(this.quill, container);
                                },
                                'load-template': function() {
                                    openLoadTemplateModal(this.quill, container);
                                }
                            }
                        },
                        keyboard: {
                            bindings: {
                                save: {
                                    key: 'S',
                                    shortKey: true, // Ctrl on Windows, Cmd on Mac
                                    handler: function(range, context) {
                                        saveSection(container);
                                        return false; // Prevent default browser save
                                    }
                                }
                            }
                        }
                    }
                });

                // Set as active immediately
                window.activeQuill = quill;
                
                // Update active quill on selection change
                quill.on('selection-change', function(range, oldRange, source) {
                    if (range) {
                        window.activeQuill = quill;
                    }
                });

                // Inject Icon into the Custom Mic Button
                const micBtn = inputWrapper.querySelector('.ql-mic');
                if (micBtn) {
                    micBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>';
                    micBtn.title = "Voice Dictation (Click to Speak)";
                    micBtn.style.width = "24px"; 
                }

                // Inject Icon into Undo Button
                const undoBtn = inputWrapper.querySelector('.ql-undo');
                if (undoBtn) {
                    undoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>';
                    undoBtn.title = "Undo (Ctrl+Z)";
                    undoBtn.style.width = "24px";
                }

                // Inject Icon into Redo Button
                const redoBtn = inputWrapper.querySelector('.ql-redo');
                if (redoBtn) {
                    redoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>';
                    redoBtn.title = "Redo (Ctrl+Y)";
                    redoBtn.style.width = "24px";
                }

                // Inject Icon into the Custom AI Button
                const aiBtn = inputWrapper.querySelector('.ql-ai-magic');
                if (aiBtn) {
                    aiBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="indigo" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M9 3v4"/><path d="M3 5h4"/><path d="M3 9h4"/></svg>';
                    aiBtn.title = "Make Professional (AI Rewrite)";
                    aiBtn.style.width = "24px";
                }

                // Inject Icon into Save Template Button
                const saveTplBtn = inputWrapper.querySelector('.ql-save-template');
                if (saveTplBtn) {
                    saveTplBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
                    saveTplBtn.title = "Save as Template";
                    saveTplBtn.style.width = "24px";
                }

                // Inject Icon into Load Template Button
                const loadTplBtn = inputWrapper.querySelector('.ql-load-template');
                if (loadTplBtn) {
                    loadTplBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
                    loadTplBtn.title = "Load Template";
                    loadTplBtn.style.width = "24px";
                }

                quillInstances.set(container, quill);
            } else if (quillInstances.has(container)) {
                window.activeQuill = quillInstances.get(container);
            }
        }
    }

    function cancelEdit(container) {
        const view = container.querySelector('.editable-view');
        const inputWrapper = container.querySelector('.editable-input');
        
        if (view && inputWrapper) {
            view.classList.remove('hidden');
            inputWrapper.classList.add('hidden');
        }
    }

    function showToast(message = 'Changes saved successfully') {
        const toast = document.getElementById('toast-notification');
        if (!toast) return;

        const toastText = toast.querySelector('span');
        if (toastText) {
            toastText.textContent = message;
        }
        
        // Add class to show
        toast.classList.add('show');
        
        setTimeout(() => {
            // Remove class to hide
            toast.classList.remove('show');
        }, 3000);
    }

    async function saveSection(container) {
        const saveBtn = container.querySelector('.save-btn');
        const originalBtnContent = saveBtn.innerHTML;
        
        const type = container.dataset.type;
        const field = container.dataset.field;
        let value = '';

        if (quillInstances.has(container)) {
            // Get data from Quill
            const quill = quillInstances.get(container);
            // Check if empty (Quill leaves <p><br></p> for empty)
            if (quill.getText().trim().length === 0) {
                value = '';
            } else {
                value = quill.root.innerHTML;
            }
        } else {
            // Standard input (if any left, though we mostly use Quill now)
            const input = container.querySelector('input.editable-input');
            if (input) {
                value = input.value;
            }
        }

        // Prepare data payload
        const data = {
            appointment_id: <?php echo $appointment_id; ?>,
            patient_id: <?php echo $patient_id; ?>,
            visit_notes: {},
            vitals: {},
            hpi: {}
        };

        if (type === 'visit_notes') {
            data.visit_notes[field] = value;
        } else if (type === 'vitals') {
            data.vitals[field] = value;
        } else if (type === 'hpi') {
            data.hpi[field] = value;
        }

        // Show loading state
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i data-lucide="loader" class="w-3 h-3 mr-1 animate-spin"></i> Saving...';
        lucide.createIcons();

        try {
            const response = await fetch('api/update_visit_summary.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            // Check if response is OK
            if (!response.ok) {
                throw new Error(`Server returned ${response.status} ${response.statusText}`);
            }

            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid JSON. Check console for details.');
            }

            if (result.success) {
                // Update UI without reload
                const view = container.querySelector('.editable-view');
                const inputWrapper = container.querySelector('.editable-input');
                
                if (quillInstances.has(container)) {
                    // Handle Rich Text Fields
                    let displayText = value;
                    // Check for empty Quill content
                    const textContent = value.replace(/<[^>]*>/g, '').trim();
                    if (!textContent && value.includes('<')) {
                         displayText = '<span class="text-gray-400 italic">None recorded</span>';
                    } else if (!value) {
                         displayText = '<span class="text-gray-400 italic">None recorded</span>';
                    }
                    
                    // Re-construct view with edit button
                    const editBtnHtml = `<button class='edit-btn absolute top-0 right-0 p-1 bg-white border border-gray-200 rounded shadow-sm text-blue-600 opacity-0 group-hover:opacity-100 transition-opacity hover:bg-blue-50 z-10' title='Edit Section'>
                        <i data-lucide='edit-2' class='w-4 h-4'></i> <span class='text-xs font-medium ml-1 hidden sm:inline'>Edit</span>
                    </button>`;
                    
                    view.innerHTML = displayText + editBtnHtml;
                    
                    view.classList.remove('hidden');
                    inputWrapper.classList.add('hidden');
                    
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnContent;
                    lucide.createIcons();
                    
                    // Show Success Toast
                    showToast();
                } else {
                    // Handle Standard Inputs (Vitals) - Fallback to reload to ensure units/formatting are correct
                    window.location.reload();
                }
            } else {
                alert('Error saving data: ' + (result.error || 'Unknown error'));
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnContent;
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error: ' + error.message);
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalBtnContent;
            lucide.createIcons();
        }
    }

    // WNL Templates
    const WNL_TEMPLATES = {
        subjective: `<p><strong>Constitutional:</strong> Good appetite.</p><p><strong>HEENT:</strong> No eye pain and discharge. No nasal congestion, no ear pain and discharge</p><p><strong>Allergy/immune:</strong> No known allergies</p><p><strong>Cardio/Vascular:</strong> No report of chest pain and palpitation</p><p><strong>Respiratory:</strong> (+) History of smoking, no shortness of breath and no cough</p><p><strong>Endocrine:</strong> denies excess thirst</p><p><strong>GI:</strong> Denies abdominal or stomach discomfort. Occasional constipation.</p><p><strong>GU:</strong> Functional urinary incontinence.</p><p><strong>Musculoskeletal:</strong> Limited range of motion and confined to bed.</p><p><strong>Neuro:</strong> No headache and no report of loss consciousness</p><p><strong>Psych:</strong> No behavioral changes, good sleep, and no suicidal ideation</p><p><strong>Skin:</strong> </p>`,
        objective: `<p><strong>General/constitutional:</strong> well nourished, alert and not distress</p><p><strong>Eyes:</strong> Pupils are equally round and reactive to light.</p><p><strong>ENT/Mouth:</strong> No ear discharge, no nasal congestion, no tonsillar swelling.</p><p><strong>Cardio/vascular:</strong> Normal heart rate and no gallops or murmurs</p><p><strong>Respiratory:</strong> normal breathing, no crackles or</p><p><strong>Lymph:</strong> Normal on both axilla, groin and neck</p><p><strong>Psych:</strong> alert, oriented and no behavioral changes</p><p><strong>GI:</strong> soft abdomen, and non tender abdomen.</p><p><strong>Skin & Subcutaneous Tissue:</strong> </p>`
    };

    function insertWNL(btn, field) {
        const container = btn.closest('.editable-container');
        if (quillInstances.has(container)) {
            const quill = quillInstances.get(container);
            const template = WNL_TEMPLATES[field];
            if (template) {
                // Append to the end
                const length = quill.getLength();
                quill.clipboard.dangerouslyPasteHTML(length, template);
            }
        }
    }

    // --- Template Management ---
    let currentQuillForTemplate = null;
    let currentContainerForTemplate = null;

    function openSaveTemplateModal(quill, container) {
        currentQuillForTemplate = quill;
        currentContainerForTemplate = container;
        document.getElementById('save-template-modal').classList.remove('hidden');
        document.getElementById('template-name-input').value = '';
        document.getElementById('template-name-input').focus();
    }

    function closeSaveTemplateModal() {
        document.getElementById('save-template-modal').classList.add('hidden');
        currentQuillForTemplate = null;
        currentContainerForTemplate = null;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const confirmBtn = document.getElementById('confirm-save-template-btn');
        if (confirmBtn) {
            confirmBtn.onclick = async function() {
                const name = document.getElementById('template-name-input').value.trim();
                if (!name) {
                    alert('Please enter a template name.');
                    return;
                }

                const content = currentQuillForTemplate.root.innerHTML;
                const section = currentContainerForTemplate.dataset.field; // e.g., 'subjective'

                try {
                    const response = await fetch('api/save_template.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            section_type: section,
                            template_name: name,
                            template_content: content
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        alert('Template saved successfully!');
                        showToast('Template saved successfully');
                        closeSaveTemplateModal();
                    } else {
                        alert('Error saving template: ' + result.error);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Network error');
                }
            };
        }
    });

    function openLoadTemplateModal(quill, container) {
        currentQuillForTemplate = quill;
        currentContainerForTemplate = container;
        document.getElementById('load-template-modal').classList.remove('hidden');
        loadTemplates(container.dataset.field);
    }

    function closeLoadTemplateModal() {
        document.getElementById('load-template-modal').classList.add('hidden');
        currentQuillForTemplate = null;
        currentContainerForTemplate = null;
    }

    async function loadTemplates(section) {
        const list = document.getElementById('template-list');
        list.innerHTML = '<p class="text-gray-500 text-sm italic">Loading...</p>';

        try {
            const response = await fetch(`api/get_templates.php?section_type=${section}`);
            const result = await response.json();

            if (result.success) {
                if (result.templates.length === 0) {
                    list.innerHTML = '<p class="text-gray-500 text-sm italic">No templates found for this section.</p>';
                    return;
                }

                list.innerHTML = '';
                result.templates.forEach(tpl => {
                    const div = document.createElement('div');
                    div.className = 'flex justify-between items-center p-2 hover:bg-gray-100 rounded cursor-pointer border-b last:border-0';
                    div.innerHTML = `
                        <span class="font-medium text-gray-800">${tpl.template_name}</span>
                        <button class="text-red-500 hover:text-red-700 p-1" title="Delete" onclick="event.stopPropagation(); deleteTemplate(${tpl.id}, '${section}')">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    `;
                    div.onclick = () => {
                        currentQuillForTemplate.clipboard.dangerouslyPasteHTML(currentQuillForTemplate.getLength(), tpl.template_content);
                        closeLoadTemplateModal();
                    };
                    list.appendChild(div);
                });
                lucide.createIcons();
            } else {
                list.innerHTML = '<p class="text-red-500 text-sm">Error loading templates.</p>';
            }
        } catch (e) {
            console.error(e);
            list.innerHTML = '<p class="text-red-500 text-sm">Network error.</p>';
        }
    }

    async function deleteTemplate(id, section) {
        if (!confirm('Are you sure you want to delete this template?')) return;

        try {
            const response = await fetch('api/delete_template.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template_id: id })
            });
            const result = await response.json();
            if (result.success) {
                loadTemplates(section); // Reload list
            } else {
                alert('Error deleting template: ' + result.error);
            }
        } catch (e) {
            console.error(e);
            alert('Network error');
        }
    }

    // --- Signature Pad Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        const canvas = document.getElementById('signature-pad');
        const clearButton = document.getElementById('clear-signature');
        const saveSigBtn = document.getElementById('save-signature-btn');

        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        let lastX = 0;
        let lastY = 0;

        function getMousePos(canvas, evt) {
            const rect = canvas.getBoundingClientRect();
            const scaleX = canvas.width / rect.width;
            const scaleY = canvas.height / rect.height;
            return {
                x: (evt.clientX - rect.left) * scaleX,
                y: (evt.clientY - rect.top) * scaleY
            };
        }

        function startDrawing(e) {
            isDrawing = true;
            const pos = getMousePos(canvas, e.touches ? e.touches[0] : e);
            lastX = pos.x;
            lastY = pos.y;
        }

        function draw(e) {
            if (!isDrawing) return;
            e.preventDefault();
            const pos = getMousePos(canvas, e.touches ? e.touches[0] : e);
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.lineTo(pos.x, pos.y);
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.stroke();
            lastX = pos.x;
            lastY = pos.y;
        }

        function stopDrawing() {
            isDrawing = false;
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        if (clearButton) {
            clearButton.addEventListener('click', function(e) {
                e.preventDefault();
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            });
        }

        if (saveSigBtn) {
            saveSigBtn.addEventListener('click', async function() {
                const dataUrl = canvas.toDataURL('image/png');
                
                const originalText = saveSigBtn.innerHTML;
                saveSigBtn.disabled = true;
                saveSigBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i> Saving...';
                lucide.createIcons();

                try {
                    const response = await fetch('api/update_visit_summary.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            appointment_id: <?php echo $appointment_id; ?>,
                            patient_id: <?php echo $patient_id; ?>,
                            signature_data: dataUrl
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        showToast('Signature saved successfully');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        alert('Error saving signature: ' + result.error);
                        saveSigBtn.disabled = false;
                        saveSigBtn.innerHTML = originalText;
                        lucide.createIcons();
                    }
                } catch (e) {
                    console.error(e);
                    alert('Network error');
                    saveSigBtn.disabled = false;
                    saveSigBtn.innerHTML = originalText;
                    lucide.createIcons();
                }
            });
        }
    });
</script>
<!-- Save Template Modal -->
<div id="save-template-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Save as Template</h3>
        <input type="text" id="template-name-input" class="w-full border rounded p-2 mb-4" placeholder="Template Name">
        <div class="flex justify-end space-x-2">
            <button onclick="closeSaveTemplateModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            <button id="confirm-save-template-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
    </div>
</div>

<!-- Load Template Modal -->
<div id="load-template-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Load Template</h3>
        <div id="template-list" class="max-h-60 overflow-y-auto mb-4 space-y-2">
            <!-- Templates will be injected here -->
            <p class="text-gray-500 text-sm italic">Loading...</p>
        </div>
        <div class="flex justify-end">
            <button onclick="closeLoadTemplateModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<!-- Checklist Modal -->
<link rel="stylesheet" type="text/css" href="css/checklist.css">
<link rel="stylesheet" href="css/quick_insert_button_colors.css">
<div id="checklistModal" class="hidden fixed inset-0 items-center justify-center z-50" aria-hidden="true" style="display:none;">
    <div id="checklistModalDialog" class="bg-white rounded-lg shadow-lg max-w-4xl w-full mx-4 transform transition-transform" role="dialog" aria-modal="true" style="max-height:90vh; overflow:auto; padding:0;">

        <!-- Dynamic Header -->
        <div class="modal-header" id="checklist-modal-header">
            <h3 id="checklist-modal-title" class="text-xl font-bold">Checklist Quick Insert</h3>
            <button id="closeChecklistModalBtn" class="text-2xl leading-none hover:opacity-70 transition-opacity">&times;</button>
        </div>

        <div class="flex flex-1 overflow-hidden" style="height: 70vh;">
            <!-- Sidebar (Categories) -->
            <div id="checklist-categories" class="w-1/3 bg-gray-50 border-r border-gray-200 overflow-y-auto p-3 space-y-1">
                <!-- Dynamic content -->
            </div>

            <!-- Main Content (Items) -->
            <div id="checklist-items" class="w-2/3 p-4 overflow-y-auto bg-white">
                <!-- Dynamic content -->
            </div>
        </div>

        <div class="modal-footer bg-white border-t border-gray-200 p-3 flex justify-end gap-3">
            <button id="closeChecklistBtn" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition">Cancel</button>
            <button id="insertChecklistBtn" class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-900 font-medium shadow-sm transition">Insert Selected</button>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div id="order-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">Add Order</h3>
            <button onclick="closeOrderModal()" class="text-gray-500 hover:text-gray-700">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order Type</label>
                <select id="order-type" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="lab_orders">Lab Order</option>
                    <option value="imaging_orders">Imaging Order</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Specific Order</label>
                <input type="text" id="order-name" list="order-suggestions" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., CBC, X-Ray Left Foot">
                <datalist id="order-suggestions">
                    <option value="CBC">
                    <option value="CMP">
                    <option value="BMP">
                    <option value="Hemoglobin A1c">
                    <option value="Lipid Panel">
                    <option value="Urinalysis">
                    <option value="Wound Culture">
                    <option value="X-Ray Left Foot">
                    <option value="X-Ray Right Foot">
                    <option value="X-Ray Left Ankle">
                    <option value="X-Ray Right Ankle">
                    <option value="MRI Left Foot">
                    <option value="MRI Right Foot">
                    <option value="Venous Doppler Lower Extremity">
                    <option value="Arterial Doppler Lower Extremity">
                </datalist>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Order Code (Optional)</label>
                    <input type="text" id="order-code" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., 85025">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specimen (Optional)</label>
                    <input type="text" id="order-specimen" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., Blood, Urine">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Diagnosis / Reason</label>
                <select id="order-diagnosis" class="w-full p-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Select Diagnosis --</option>
                    <?php 
                    // Use all historical diagnoses for the dropdown
                    $unique_diagnoses = [];
                    foreach ($all_patient_diagnoses as $dx) {
                        // Avoid duplicates if multiple visits have same diagnosis
                        $key = $dx['icd10_code'];
                        if (!isset($unique_diagnoses[$key])) {
                            $unique_diagnoses[$key] = $dx;
                        }
                    }
                    
                    foreach ($unique_diagnoses as $dx): 
                    ?>
                        <option value="<?php echo htmlspecialchars($dx['description'] . ' (' . $dx['icd10_code'] . ')'); ?>">
                            <?php echo htmlspecialchars($dx['icd10_code'] . ' - ' . $dx['description']); ?>
                        </option>
                    <?php endforeach; ?>
                    
                    <?php if (empty($unique_diagnoses)): ?>
                        <option value="" disabled>No diagnoses found for this patient</option>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex items-center">
                <input type="checkbox" id="order-stat" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                <label for="order-stat" class="ml-2 block text-sm text-gray-900">STAT (Urgent)</label>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-3">
            <button onclick="closeOrderModal()" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">Cancel</button>
            <button onclick="saveOrder()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Generate & Save</button>
        </div>
    </div>
</div>

<script>
    function openOrderModal() {
        document.getElementById('order-modal').classList.remove('hidden');
    }
    
    function closeOrderModal() {
        document.getElementById('order-modal').classList.add('hidden');
        document.getElementById('order-name').value = '';
        document.getElementById('order-diagnosis').value = '';
        document.getElementById('order-code').value = '';
        document.getElementById('order-specimen').value = '';
        document.getElementById('order-stat').checked = false;
    }
    
    async function saveOrder() {
        const type = document.getElementById('order-type').value;
        const name = document.getElementById('order-name').value;
        const diagnosis = document.getElementById('order-diagnosis').value;
        const code = document.getElementById('order-code').value;
        const specimen = document.getElementById('order-specimen').value;
        const stat = document.getElementById('order-stat').checked;
        
        if (!name || !diagnosis) {
            alert('Please fill in all fields');
            return;
        }
        
        // Structured HTML for parsing
        const newEntry = `
            <div class="order-entry border-b border-gray-100 py-2" data-code="${code}" data-name="${name}" data-dx="${diagnosis}" data-stat="${stat ? 'Yes' : 'No'}" data-specimen="${specimen}">
                <div class="flex justify-between">
                    <div>
                        <span class="font-bold">${name}</span> ${stat ? '<span class="text-red-500 font-bold text-xs uppercase ml-2">STAT</span>' : ''}
                        <div class="text-xs text-gray-500">Code: ${code || 'N/A'} | Specimen: ${specimen || 'Standard'}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm">DX: ${diagnosis}</div>
                    </div>
                </div>
            </div>`;
        
        const container = document.querySelector(`div[data-field="${type}"]`);
        if (!container) {
            alert('Error: Could not find order section');
            return;
        }
        
        let currentContent = '';
        if (quillInstances.has(container)) {
            currentContent = quillInstances.get(container).root.innerHTML;
        } else {
            // Try to get from editable-view
            const view = container.querySelector('.editable-view');
            if (view) currentContent = view.innerHTML;
        }
        
        // Clean up "None recorded" placeholder
        if (currentContent.includes('None recorded')) currentContent = '';
        
        const updatedContent = currentContent + newEntry;
        
        // Prepare payload
        const data = {
            appointment_id: <?php echo $appointment_id; ?>,
            patient_id: <?php echo $patient_id; ?>,
            visit_notes: {}
        };
        data.visit_notes[type] = updatedContent;
        
        try {
            // 1. Save to Visit Note (Text Blob)
            const response = await fetch('api/update_visit_summary.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                // Update DOM for Visit Note
                if (quillInstances.has(container)) {
                    const quill = quillInstances.get(container);
                    quill.clipboard.dangerouslyPasteHTML(quill.getLength(), newEntry);
                } else {
                    const view = container.querySelector('.editable-view');
                    if (view) view.innerHTML = updatedContent;
                    const quillEl = container.querySelector('.quill-editor');
                    if (quillEl) quillEl.innerHTML = updatedContent;
                }
                
                // 2. Create Structured Order in Patient Orders System
                const formData = new FormData();
                formData.append('action', 'create_order');
                formData.append('patient_id', <?php echo $patient_id; ?>);
                formData.append('order_type', type === 'lab_orders' ? 'Lab' : 'Imaging');
                formData.append('order_name', name + (code ? ` (Code: ${code})` : ''));
                formData.append('priority', stat ? 'Stat' : 'Routine');
                
                // We don't await this strictly to block UI, but good to fire it off
                fetch('api/manage_order.php', {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(res => {
                    if (res.success) {
                        console.log('Structured order created');
                    } else {
                        console.error('Failed to create structured order', res);
                        alert('Note saved, but failed to sync to Orders list: ' + (res.message || 'Unknown error'));
                    }
                }).catch(err => {
                    console.error('Network error creating order', err);
                    alert('Note saved, but network error syncing to Orders list.');
                });

                showToast('Order added successfully');
                closeOrderModal();
            } else {
                alert('Error saving order');
            }
        } catch (e) {
            console.error(e);
            alert('Network error');
        }
    }
</script>

<script src="js/visit_summary_checklist.js"></script>
</body>
</html>
