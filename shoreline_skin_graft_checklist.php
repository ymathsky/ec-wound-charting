<?php
// Filename: shoreline_skin_graft_checklist.php
// Purpose: Dedicated page for the Shoreline Skin Graft Checklist (Audit Compliance).
// UPDATED: Added Datalist for Product Library and 'Total Product Area' input.

require_once 'templates/header.php';
require_once 'db_connect.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician'])) {
    header("Location: index.php");
    exit();
}

// --- Input Validation ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

$wound_id = 0;
if (isset($_GET['wound_id'])) {
    $wound_id = intval($_GET['wound_id']);
} elseif (isset($_GET['id'])) {
    $wound_id = intval($_GET['id']);
}

if ($patient_id <= 0 || $appointment_id <= 0 || $wound_id <= 0) {
    echo "<div class='p-8 text-red-600 font-bold'>Error: Missing required Patient, Appointment, or Wound ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- Fetch Data ---

// 1. Get Patient Info & Medical History
$stmt = $conn->prepare("SELECT first_name, last_name, date_of_birth, past_medical_history FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$patient = $res->fetch_assoc();
$patient_name = $patient ? htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) : "Unknown";
$patient_dob = $patient ? htmlspecialchars($patient['date_of_birth']) : "N/A";
$pmh = $patient && !empty($patient['past_medical_history']) ? htmlspecialchars($patient['past_medical_history']) : "No past medical history recorded.";
$stmt->close();

// 2. Get Wound Info
$stmt = $conn->prepare("SELECT location, wound_type, date_onset FROM wounds WHERE wound_id = ?");
$stmt->bind_param("i", $wound_id);
$stmt->execute();
$res = $stmt->get_result();
$wound = $res->fetch_assoc();
$wound_desc = $wound ? htmlspecialchars($wound['location'] . ' - ' . $wound['wound_type']) : "Unknown Wound";
$stmt->close();

// 3. Get Assessment Data
$stmt = $conn->prepare("SELECT * FROM wound_assessments WHERE appointment_id = ? AND wound_id = ? LIMIT 1");
$stmt->bind_param("ii", $appointment_id, $wound_id);
$stmt->execute();
$assessment_res = $stmt->get_result();
$assessment = $assessment_res->fetch_assoc();
$stmt->close();
$assessment_id = $assessment['assessment_id'] ?? 0;

// 4. Get Visit Diagnoses
$diagnoses = [];
$stmt = $conn->prepare("SELECT icd10_code, description, is_primary FROM visit_diagnoses WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$diag_res = $stmt->get_result();
while ($row = $diag_res->fetch_assoc()) {
    $diagnoses[] = $row;
}
$stmt->close();

// 5. Get Active Medications
$medications = [];
$stmt = $conn->prepare("SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$med_res = $stmt->get_result();
while ($row = $med_res->fetch_assoc()) {
    $medications[] = $row;
}
$stmt->close();

// 6. Get Wound Photo
$wound_photo_url = '';
if ($assessment_id > 0) {
    $stmt = $conn->prepare("SELECT image_path FROM wound_images WHERE assessment_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $img_res = $stmt->get_result();
    if ($row = $img_res->fetch_assoc()) {
        $wound_photo_url = $row['image_path'];
    }
    $stmt->close();
}
if (empty($wound_photo_url)) {
    $stmt = $conn->prepare("SELECT image_path FROM wound_images WHERE wound_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->bind_param("i", $wound_id);
    $stmt->execute();
    $img_res = $stmt->get_result();
    if ($row = $img_res->fetch_assoc()) {
        $wound_photo_url = $row['image_path'];
    }
    $stmt->close();
}

// Helpers
function isChecked($key, $data) { return (isset($data[$key]) && $data[$key] == 1) ? 'checked' : ''; }
function getVal($key, $data) { return isset($data[$key]) ? htmlspecialchars($data[$key]) : ''; }
?>

    <!-- Include Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-lg sticky top-0 z-10">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Shoreline Graft Audit Checklist</h1>
                    <p class="text-sm text-indigo-600 font-semibold mt-1">
                        Patient: <?php echo $patient_name; ?> (DOB: <?php echo $patient_dob; ?>) | Wound: <?php echo $wound_desc; ?>
                    </p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="visit_wounds.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>"
                       class="bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-300 transition duration-150 shadow-md flex items-center">
                        <i data-lucide="arrow-left" class="w-5 h-5 mr-2"></i>
                        Back
                    </a>
                    <a href="skin_graft_report.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>&wound_id=<?php echo $wound_id; ?>"
                       target="_blank"
                       class="bg-white text-indigo-600 border border-indigo-600 font-bold py-2 px-4 rounded-lg hover:bg-indigo-50 transition duration-150 shadow-md flex items-center">
                        <i data-lucide="printer" class="w-5 h-5 mr-2"></i>
                        Report
                    </a>
                    <button id="saveChecklistBtn" class="bg-indigo-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-indigo-700 transition duration-150 shadow-lg flex items-center transform hover:scale-105">
                        <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                        Save
                    </button>
                </div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="max-w-6xl mx-auto">
                    <div id="alert-container" class="mb-4"></div>

                    <form id="graftChecklistForm" class="space-y-6">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                        <input type="hidden" name="wound_id" value="<?php echo $wound_id; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                        <input type="hidden" name="assessment_id" value="<?php echo $assessment_id; ?>">

                        <!-- === CLINICAL CONTEXT & PHOTO PANEL === -->
                        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                            <!-- Clinical Data -->
                            <div class="lg:col-span-3 bg-gray-50 rounded-xl border border-gray-200 shadow-sm overflow-hidden h-full">
                                <div class="bg-gray-100 px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide flex items-center">
                                        <i data-lucide="clipboard-list" class="w-4 h-4 mr-2 text-gray-500"></i>
                                        Clinical Reference Data
                                    </h3>
                                    <span class="text-xs text-gray-500">Auto-fetched from Chart</span>
                                </div>
                                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6 text-sm h-full">
                                    <!-- History -->
                                    <div class="bg-white p-4 rounded border border-gray-100 shadow-sm">
                                        <h4 class="font-bold text-indigo-600 mb-2 flex items-center">
                                            <i data-lucide="history" class="w-4 h-4 mr-2"></i> Past Medical History
                                        </h4>
                                        <div class="text-gray-700 whitespace-pre-wrap h-32 overflow-y-auto pr-2 custom-scrollbar">
                                            <?php echo $pmh; ?>
                                        </div>
                                    </div>
                                    <!-- Dx -->
                                    <div class="bg-white p-4 rounded border border-gray-100 shadow-sm">
                                        <h4 class="font-bold text-indigo-600 mb-2 flex items-center">
                                            <i data-lucide="stethoscope" class="w-4 h-4 mr-2"></i> Visit Diagnoses
                                        </h4>
                                        <div class="h-32 overflow-y-auto pr-2 custom-scrollbar">
                                            <?php if (empty($diagnoses)): ?>
                                                <p class="text-gray-500 italic">No diagnoses recorded.</p>
                                            <?php else: ?>
                                                <ul class="space-y-2">
                                                    <?php foreach ($diagnoses as $dx): ?>
                                                        <li class="text-gray-700">
                                                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($dx['icd10_code']); ?></span>
                                                            <span class="block text-xs text-gray-500"><?php echo htmlspecialchars($dx['description']); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <!-- Meds -->
                                    <div class="bg-white p-4 rounded border border-gray-100 shadow-sm">
                                        <h4 class="font-bold text-indigo-600 mb-2 flex items-center">
                                            <i data-lucide="pill" class="w-4 h-4 mr-2"></i> Active Medications
                                        </h4>
                                        <div class="h-32 overflow-y-auto pr-2 custom-scrollbar">
                                            <?php if (empty($medications)): ?>
                                                <p class="text-gray-500 italic">No active medications.</p>
                                            <?php else: ?>
                                                <ul class="space-y-2">
                                                    <?php foreach ($medications as $med): ?>
                                                        <li class="text-gray-700 border-b border-gray-50 pb-1 last:border-0">
                                                            <span class="font-medium"><?php echo htmlspecialchars($med['drug_name']); ?></span>
                                                            <span class="text-xs text-gray-500 ml-1"><?php echo htmlspecialchars($med['dosage']); ?></span>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Wound Photo -->
                            <div class="lg:col-span-1 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                                <div class="bg-gray-100 px-4 py-3 border-b border-gray-200">
                                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide flex items-center">
                                        <i data-lucide="image" class="w-4 h-4 mr-2 text-gray-500"></i>
                                        Wound Photo
                                    </h3>
                                </div>
                                <div class="p-2 flex-1 flex items-center justify-center bg-gray-50">
                                    <?php if ($wound_photo_url): ?>
                                        <a href="<?php echo htmlspecialchars($wound_photo_url); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($wound_photo_url); ?>" alt="Wound Photo" class="max-h-48 object-contain rounded shadow-sm hover:opacity-90 transition-opacity">
                                        </a>
                                    <?php else: ?>
                                        <div class="text-center text-gray-400 py-8">
                                            <i data-lucide="image-off" class="w-12 h-12 mx-auto mb-2 opacity-50"></i>
                                            <p class="text-xs">No photo available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- === SECTION 1: GENERAL REQUIREMENTS === -->
                        <div class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 border-blue-600">
                            <div class="bg-blue-50 px-6 py-4 border-b border-blue-100 flex items-center">
                                <i data-lucide="file-text" class="w-6 h-6 mr-3 text-blue-600"></i>
                                <h3 class="text-lg font-bold text-gray-800">1. General Requirements & Exclusion Criteria</h3>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <h4 class="font-semibold text-sm uppercase text-gray-500 border-b pb-1 mb-2">Documentation Verification</h4>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_meds" value="1" <?php echo isChecked('graft_check_meds', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">Current Medication List Reviewed</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_dx" value="1" <?php echo isChecked('graft_check_dx', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">Diagnoses Reviewed</span></label>
                                </div>
                                <div class="space-y-3">
                                    <h4 class="font-semibold text-sm uppercase text-gray-500 border-b pb-1 mb-2">Exclusion Criteria</h4>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_no_infection" value="1" <?php echo isChecked('graft_check_no_infection', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">No Active Infection</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_osteo" value="1" <?php echo isChecked('graft_check_osteo', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">No Active Osteomyelitis</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_vasculitis" value="1" <?php echo isChecked('graft_check_vasculitis', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">No Untreated Vasculitis</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_charcot" value="1" <?php echo isChecked('graft_check_charcot', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">No Active Charcot</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_smoking" value="1" <?php echo isChecked('graft_check_smoking', $assessment); ?> class="mt-1 form-checkbox text-blue-600"><span class="text-gray-800 text-sm">Non-Smoker or Counseled</span></label>
                                </div>
                                <div class="md:col-span-2 mt-4 pt-4 border-t border-gray-100">
                                    <h4 class="font-semibold text-sm uppercase text-gray-500 mb-2">Conservative Care History</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div><label class="block text-sm font-medium text-gray-700">Conservative Treatments Attempted</label><textarea name="graft_conservative_treatments" rows="2" class="form-textarea mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm"><?php echo getVal('graft_conservative_treatments', $assessment); ?></textarea></div>
                                        <div><label class="block text-sm font-medium text-gray-700">Duration of Conservative Care</label><input type="text" name="graft_conservative_duration" value="<?php echo getVal('graft_conservative_duration', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 sm:text-sm" placeholder="e.g., 4 weeks"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- === EVIDENCE OF RESPONSE WIDGET === -->
                        <div id="healing-trajectory-widget" class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 border-teal-500">
                            <div class="bg-teal-50 px-6 py-4 border-b border-teal-100 flex items-center justify-between">
                                <div class="flex items-center">
                                    <i data-lucide="trending-down" class="w-6 h-6 mr-3 text-teal-600"></i>
                                    <h3 class="text-lg font-bold text-gray-800">Evidence of Response (Healing Trajectory)</h3>
                                </div>
                                <span id="trajectory-status-badge" class="px-3 py-1 rounded-full text-xs font-bold bg-gray-200 text-gray-600 uppercase tracking-wide">Loading...</span>
                            </div>
                            <div class="p-6">
                                <p class="text-sm text-gray-600 mb-4">
                                    The Shoreline audit requires "Evidence of Response to Prior Grafts" (e.g., decreased measurements, granulation).
                                    This table auto-calculates the trajectory from previous measurements.
                                </p>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Date</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Dimensions (LxW)</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Area (cm²)</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">% Reduction (vs Baseline)</th>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-600">Granulation %</th>
                                        </tr>
                                        </thead>
                                        <tbody id="trajectory-table-body" class="divide-y divide-gray-100">
                                        <tr><td colspan="5" class="px-4 py-4 text-center text-gray-500 italic">Loading measurement history...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-4 p-3 bg-teal-50 rounded border border-teal-100 text-sm text-teal-800">
                                    <strong>Analysis:</strong> <span id="trajectory-analysis-text">Calculating...</span>
                                </div>
                            </div>
                        </div>

                        <!-- === SECTION 2: WOUND DOCUMENTATION === -->
                        <div class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 border-orange-500">
                            <div class="bg-orange-50 px-6 py-4 border-b border-orange-100 flex items-center">
                                <i data-lucide="ruler" class="w-6 h-6 mr-3 text-orange-600"></i>
                                <h3 class="text-lg font-bold text-gray-800">2. Wound Documentation & Preparation</h3>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-3">
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_size_requirement" value="1" <?php echo isChecked('graft_check_size_requirement', $assessment); ?> class="mt-1 form-checkbox text-orange-600"><span class="text-gray-800 text-sm">Ulcer Size ≥ 1cm²</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_no_necrotic" value="1" <?php echo isChecked('graft_check_no_necrotic', $assessment); ?> class="mt-1 form-checkbox text-orange-600"><span class="text-gray-800 text-sm">Clean Granular Base</span></label>
                                    <label class="flex items-start space-x-3"><input type="checkbox" name="graft_check_bone" value="1" <?php echo isChecked('graft_check_bone', $assessment); ?> class="mt-1 form-checkbox text-orange-600"><span class="text-gray-800 text-sm">No Exposed Bone/Tendon</span></label>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Wound Thickness</label>
                                    <select name="graft_wound_thickness" class="form-select block w-full rounded-md border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm">
                                        <option value="">-- Select --</option>
                                        <option value="Partial Thickness" <?php echo (getVal('graft_wound_thickness', $assessment) === 'Partial Thickness') ? 'selected' : ''; ?>>Partial Thickness</option>
                                        <option value="Full Thickness" <?php echo (getVal('graft_wound_thickness', $assessment) === 'Full Thickness') ? 'selected' : ''; ?>>Full Thickness</option>
                                    </select>
                                    <label class="block text-sm font-medium text-gray-700 mt-3 mb-1">Documentation Photos</label>
                                    <div class="flex gap-4 text-sm text-gray-600">
                                        <label class="flex items-center"><input type="checkbox" name="graft_check_debridement_photos" value="1" <?php echo isChecked('graft_check_debridement_photos', $assessment); ?> class="mr-2"> Pre/Post Debridement</label>
                                        <label class="flex items-center"><input type="checkbox" name="graft_check_historical_photos" value="1" <?php echo isChecked('graft_check_historical_photos', $assessment); ?> class="mr-2"> Historical Progress</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- === SECTION 3: PRODUCT INFORMATION === -->
                        <div class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 border-indigo-500">
                            <div class="bg-indigo-50 px-6 py-4 border-b border-indigo-100 flex items-center">
                                <i data-lucide="package" class="w-6 h-6 mr-3 text-indigo-600"></i>
                                <h3 class="text-lg font-bold text-gray-800">3. Graft Product Information</h3>
                            </div>
                            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-3">
                                    <label class="block text-sm font-medium text-gray-700">Product Name / Choice Justification</label>

                                    <!-- UPDATED: Datalist for Product Name -->
                                    <input type="text" list="graft_products" name="graft_product_name" id="graft_product_name" value="<?php echo getVal('graft_product_name', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Start typing product name (e.g. NuShield)...">
                                    <datalist id="graft_products">
                                        <!-- Options populated by JS -->
                                    </datalist>

                                    <textarea name="graft_treatment_goals" rows="2" class="form-textarea mt-2 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Justification for product choice / change..."><?php echo getVal('graft_treatment_goals', $assessment); ?></textarea>
                                </div>
                                <div><label class="block text-sm font-medium text-gray-700">Serial Number</label><input type="text" name="graft_serial_number" value="<?php echo getVal('graft_serial_number', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                                <div><label class="block text-sm font-medium text-gray-700">Lot Number</label><input type="text" name="graft_lot_number" value="<?php echo getVal('graft_lot_number', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                                <div><label class="block text-sm font-medium text-gray-700">Expiration Date</label><input type="date" name="graft_expiry_date" value="<?php echo getVal('graft_expiry_date', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm"></div>
                            </div>
                        </div>

                        <!-- === SECTION 4: APPLICATION & BILLING === -->
                        <div class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 border-green-500">
                            <div class="bg-green-50 px-6 py-4 border-b border-green-100 flex items-center">
                                <i data-lucide="credit-card" class="w-6 h-6 mr-3 text-green-600"></i>
                                <h3 class="text-lg font-bold text-gray-800">4. Application & Billing (Coding)</h3>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Application #</label>
                                        <div class="mt-1 flex rounded-md shadow-sm">
                                            <input type="number" name="graft_application_number" value="<?php echo getVal('graft_application_number', $assessment); ?>" class="form-input flex-1 block w-full rounded-none rounded-l-md border-gray-300 sm:text-sm" placeholder="3">
                                            <span class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">of 10</span>
                                        </div>
                                    </div>

                                    <!-- NEW: Total Product Area (Helper Field) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Total Product Area (cm²)</label>
                                        <input type="number" step="0.01" id="graft_product_size" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm bg-gray-50" placeholder="e.g. 16">
                                        <p class="text-xs text-gray-400 mt-1">For calculator only</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Amount Used (cm²)</label>
                                        <input type="number" step="0.01" name="graft_sqcm_used" id="graft_sqcm_used" value="<?php echo getVal('graft_sqcm_used', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Amount Discarded (cm²)</label>
                                        <input type="number" step="0.01" name="graft_sqcm_discarded" id="graft_sqcm_discarded" value="<?php echo getVal('graft_sqcm_discarded', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm" readonly>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">CPT Code</label>
                                        <input type="text" name="graft_cpt_code" id="graft_cpt_code" value="<?php echo getVal('graft_cpt_code', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g. 15271">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Q Code</label>
                                        <input type="text" name="graft_q_code" id="graft_q_code" value="<?php echo getVal('graft_q_code', $assessment); ?>" class="form-input mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="e.g. Q41xx">
                                    </div>

                                    <div class="flex items-center pt-6">
                                        <label class="flex items-center space-x-2">
                                            <input type="checkbox" name="graft_check_jw_modifier" id="graft_check_jw_modifier" value="1" <?php echo isChecked('graft_check_jw_modifier', $assessment); ?> class="form-checkbox text-green-600 w-5 h-5">
                                            <span class="text-gray-900 font-bold">JW Modifier Applied</span>
                                        </label>
                                    </div>

                                    <div class="md:col-span-2"><label class="block text-sm font-medium text-gray-700">Justification for Discard</label><textarea name="graft_discard_justification" rows="2" class="form-textarea mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Explain why remainder was discarded..."><?php echo getVal('graft_discard_justification', $assessment); ?></textarea></div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="js/shoreline_checklist.js"></script>
    <script>
        lucide.createIcons();

        // ... Evidence Widget Script ...
        document.addEventListener('DOMContentLoaded', function() {
            // ... (Existing healing trajectory script stays here) ...
            const tableBody = document.getElementById('trajectory-table-body');
            const statusBadge = document.getElementById('trajectory-status-badge');
            const analysisText = document.getElementById('trajectory-analysis-text');
            const woundId = document.querySelector('input[name="wound_id"]').value;

            if(woundId > 0) {
                fetch(`api/get_healing_trajectory_data.php?wound_id=${woundId}`, { credentials: 'same-origin' })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success && data.history.length > 0) {
                            tableBody.innerHTML = '';
                            data.history.forEach(row => {
                                const area = (row.area_cm2) ? parseFloat(row.area_cm2).toFixed(2) : '-';
                                const baseline = parseFloat(data.history[0].area_cm2);
                                const current = parseFloat(row.area_cm2);
                                let reduction = '-';
                                if (baseline > 0 && current > 0) {
                                    const pct = ((baseline - current) / baseline) * 100;
                                    reduction = (pct > 0 ? '+' : '') + pct.toFixed(1) + '%';
                                }
                                const tr = document.createElement('tr');
                                tr.className = 'hover:bg-gray-50';
                                tr.innerHTML = `<td class="px-4 py-2 text-gray-800">${row.assessment_date}</td><td class="px-4 py-2 text-gray-600">${row.length_cm} x ${row.width_cm}</td><td class="px-4 py-2 text-gray-800 font-medium">${area}</td><td class="px-4 py-2 ${parseFloat(reduction) > 0 ? 'text-green-600 font-bold' : 'text-gray-600'}">${reduction}</td><td class="px-4 py-2 text-gray-600">${row.granulation_percent || '-'}%</td>`;
                                tableBody.appendChild(tr);
                            });
                            const status = data.analysis.status;
                            statusBadge.textContent = status;
                            if(status.includes("Improving")) {
                                statusBadge.className = "px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800 uppercase tracking-wide";
                                analysisText.className = "text-green-800";
                            } else if(status.includes("Worsening")) {
                                statusBadge.className = "px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800 uppercase tracking-wide";
                                analysisText.className = "text-red-800";
                            }
                            analysisText.textContent = `Wound status is ${status}. Baseline area was ${parseFloat(data.analysis.baseline_area).toFixed(2)} cm², current area is ${parseFloat(data.analysis.current_area).toFixed(2)} cm².`;
                        } else {
                            tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-gray-500">No historical data found for this wound.</td></tr>';
                            statusBadge.textContent = "No Data";
                            analysisText.textContent = "Insufficient data to calculate trajectory.";
                        }
                    })
                    .catch(err => { console.error("Error fetching trajectory:", err); tableBody.innerHTML = '<tr><td colspan="5" class="px-4 py-4 text-center text-red-500">Error loading data.</td></tr>'; });
            }
        });
    </script>

<?php require_once 'templates/footer.php'; ?>