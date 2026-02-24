<?php
// Filename: skin_graft_report.php
// Purpose: Printer-friendly report of the Shoreline Skin Graft Checklist compliance data.
// UPDATED: Added "Total Product Size" display to Section 4 (Calculated from Used + Discarded).

require_once 'db_connect.php';
session_start();

// --- Access Control ---
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician'])) {
    header("Location: index.php");
    exit();
}

// --- Input Validation ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0 || $wound_id <= 0) {
    die("Error: Missing required IDs for report generation.");
}

// --- Fetch Data ---

// 1. Patient & Appointment Info
$stmt = $conn->prepare("
    SELECT p.first_name, p.last_name, p.date_of_birth, p.patient_code, p.past_medical_history,
           a.appointment_date, u.full_name as clinician_name
    FROM patients p
    JOIN appointments a ON p.patient_id = a.patient_id
    LEFT JOIN users u ON a.user_id = u.user_id
    WHERE p.patient_id = ? AND a.appointment_id = ?
");
$stmt->bind_param("ii", $patient_id, $appointment_id);
$stmt->execute();
$pt_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Wound Info
$stmt = $conn->prepare("SELECT location, wound_type, date_onset FROM wounds WHERE wound_id = ?");
$stmt->bind_param("i", $wound_id);
$stmt->execute();
$wound = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Assessment & Checklist Data
$stmt = $conn->prepare("SELECT * FROM wound_assessments WHERE appointment_id = ? AND wound_id = ? LIMIT 1");
$stmt->bind_param("ii", $appointment_id, $wound_id);
$stmt->execute();
$wa = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 4. Images
$images = [];
$stmt = $conn->prepare("SELECT image_path, image_type FROM wound_images WHERE appointment_id = ? AND wound_id = ?");
$stmt->bind_param("ii", $appointment_id, $wound_id);
$stmt->execute();
$img_res = $stmt->get_result();
while($row = $img_res->fetch_assoc()) {
    $images[] = $row;
}
$stmt->close();

// 5. Diagnoses
$diagnoses = [];
$stmt = $conn->prepare("SELECT icd10_code, description, is_primary FROM visit_diagnoses WHERE appointment_id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$dx_res = $stmt->get_result();
while($row = $dx_res->fetch_assoc()) {
    $diagnoses[] = $row;
}
$stmt->close();

// 6. Medications
$medications = [];
$stmt = $conn->prepare("SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active'");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$med_res = $stmt->get_result();
while($row = $med_res->fetch_assoc()) {
    $medications[] = $row;
}
$stmt->close();


// --- HELPER FUNCTIONS ---
function isChecked($key, $data) {
    $val = isset($data[$key]) ? $data[$key] : 0;
    return ($val == 1) ? '<span class="check-box checked">☑</span>' : '<span class="check-box">☐</span>';
}
function txt($val) {
    return !empty($val) ? htmlspecialchars($val) : '<span class="text-gray-400 italic">Not documented</span>';
}
function fmtDate($dateStr) {
    if (empty($dateStr)) return '<span class="text-gray-400 italic">N/A</span>';
    return date('m/d/Y', strtotime($dateStr));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Skin Graft Usage Report - <?php echo htmlspecialchars($pt_data['last_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: white; -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
            .page-break { page-break-before: always; }
            .shadow-lg { shadow: none; }
            .border { border: 1px solid #ccc; }
            .bg-gray-50 { background-color: #f9fafb !important; }
        }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; }
        .report-container { max-width: 850px; margin: 0 auto; background: white; padding: 40px; }
        .section-header { background-color: #f3f4f6; border-bottom: 2px solid #e5e7eb; padding: 8px 12px; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; margin-top: 20px; color: #1f2937; }
        .data-row { display: flex; border-bottom: 1px solid #f0f0f0; padding: 8px 0; }
        .data-label { width: 40%; font-weight: 600; color: #555; font-size: 0.9rem; }
        .data-value { width: 60%; font-size: 0.9rem; }
        .check-box { font-size: 1.2rem; line-height: 1; margin-right: 5px; font-family: "Segoe UI Symbol", "Arial Unicode MS", sans-serif; }
        .check-box.checked { color: #16a34a; font-weight: bold; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-block { margin-top: 10px; border: 1px solid #e5e7eb; padding: 10px; border-radius: 4px; background: #fff; font-size: 0.9rem; }
        .info-block h4 { font-weight: bold; color: #4b5563; font-size: 0.8rem; text-transform: uppercase; margin-bottom: 5px; border-bottom: 1px solid #f3f4f6; padding-bottom: 4px; }
        .list-item { padding: 2px 0; border-bottom: 1px dashed #f0f0f0; }
        .list-item:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

<!-- Action Bar -->
<div class="no-print fixed top-0 left-0 right-0 bg-white shadow-md p-4 flex justify-between items-center z-50">
    <div class="font-bold text-lg text-gray-700">Skin Graft Audit Report Preview</div>
    <div class="space-x-3">
        <button onclick="window.close()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-gray-700 font-medium">Close</button>
        <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-medium shadow">Print / Save PDF</button>
    </div>
</div>

<div class="h-16 no-print"></div> <!-- Spacer -->

<div class="report-container shadow-lg my-8 rounded-sm">

    <!-- Header -->
    <div class="flex justify-between items-start border-b-2 border-gray-800 pb-4 mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 uppercase tracking-wider">Skin Graft Usage & Audit Report</h1>
            <p class="text-sm text-gray-500 mt-1">Shoreline Medical Administration Compliance Record</p>
        </div>
        <div class="text-right text-sm">
            <p><strong>Date of Service:</strong> <?php echo fmtDate($pt_data['appointment_date']); ?></p>
            <p><strong>Generated:</strong> <?php echo date('m/d/Y H:i'); ?></p>
        </div>
    </div>

    <!-- Patient & Provider Info -->
    <div class="two-col">
        <div>
            <div class="section-header">Patient Information</div>
            <div class="data-row"><span class="data-label">Name:</span> <span class="data-value"><?php echo txt($pt_data['first_name'] . ' ' . $pt_data['last_name']); ?></span></div>
            <div class="data-row"><span class="data-label">DOB:</span> <span class="data-value"><?php echo fmtDate($pt_data['date_of_birth']); ?></span></div>
            <div class="data-row"><span class="data-label">MRN/Code:</span> <span class="data-value"><?php echo txt($pt_data['patient_code']); ?></span></div>
        </div>
        <div>
            <div class="section-header">Provider & Wound Info</div>
            <div class="data-row"><span class="data-label">Clinician:</span> <span class="data-value"><?php echo txt($pt_data['clinician_name']); ?></span></div>
            <div class="data-row"><span class="data-label">Wound Site:</span> <span class="data-value"><?php echo txt($wound['location']); ?></span></div>
            <div class="data-row"><span class="data-label">Wound Type:</span> <span class="data-value"><?php echo txt($wound['wound_type']); ?></span></div>
        </div>
    </div>

    <!-- NEW SECTION: Clinical Context -->
    <div class="section-header">Clinical Context & Medical History</div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">

        <!-- PMH -->
        <div class="info-block bg-gray-50">
            <h4>Past Medical History</h4>
            <div class="text-gray-700 whitespace-pre-wrap text-xs leading-relaxed">
                <?php echo !empty($pt_data['past_medical_history']) ? htmlspecialchars($pt_data['past_medical_history']) : 'None recorded.'; ?>
            </div>
        </div>

        <!-- Diagnosis -->
        <div class="info-block bg-gray-50">
            <h4>Visit Diagnoses (ICD-10)</h4>
            <?php if (empty($diagnoses)): ?>
                <p class="text-gray-500 italic text-xs">None recorded.</p>
            <?php else: ?>
                <?php foreach ($diagnoses as $dx): ?>
                    <div class="list-item flex justify-between">
                        <span class="font-semibold text-xs"><?php echo htmlspecialchars($dx['icd10_code']); ?></span>
                        <span class="text-xs text-gray-600 truncate ml-2"><?php echo htmlspecialchars($dx['description']); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Medications -->
        <div class="info-block bg-gray-50 md:col-span-2">
            <h4>Active Medications</h4>
            <?php if (empty($medications)): ?>
                <p class="text-gray-500 italic text-xs">None recorded.</p>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-x-4">
                    <?php foreach ($medications as $med): ?>
                        <div class="list-item text-xs">
                            <span class="font-medium"><?php echo htmlspecialchars($med['drug_name']); ?></span>
                            <span class="text-gray-500 ml-1">- <?php echo htmlspecialchars($med['dosage']); ?> <?php echo htmlspecialchars($med['frequency']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>


    <!-- 1. General & Exclusion Criteria -->
    <div class="section-header">1. Exclusion Criteria & Medical Necessity</div>
    <div class="grid grid-cols-2 gap-x-8 gap-y-2 p-2 text-sm">
        <div class="flex items-center"><?php echo isChecked('graft_check_no_infection', $wa); ?> No Active Infection</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_osteo', $wa); ?> No Active Osteomyelitis</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_vasculitis', $wa); ?> No Untreated Vasculitis</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_charcot', $wa); ?> No Active Charcot</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_smoking', $wa); ?> Non-Smoker / Counseled</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_meds', $wa); ?> Meds Reviewed</div>
    </div>

    <div class="mt-3 p-3 bg-gray-50 rounded border border-gray-200 text-sm">
        <strong>Conservative Care History:</strong><br>
        <span class="text-gray-700"><?php echo txt($wa['graft_conservative_treatments']); ?></span>
        <div class="mt-1 text-xs text-gray-500">Duration: <?php echo txt($wa['graft_conservative_duration']); ?></div>
    </div>

    <!-- 2. Wound Documentation & Assessment -->
    <div class="section-header">2. Wound Assessment & Documentation</div>

    <!-- Specific Wound Assessment Data -->
    <div class="grid grid-cols-3 gap-4 p-2 text-sm mb-2 border-b border-dashed pb-2">
        <div>
            <span class="block text-gray-500 text-xs uppercase font-bold">Dimensions</span>
            <span class="font-medium">
                    <?php echo isset($wa['length_cm']) ? $wa['length_cm'].' x '.$wa['width_cm'].' x '.($wa['depth_cm'] ?? '0') : 'N/A'; ?> cm
                </span>
        </div>
        <div>
            <span class="block text-gray-500 text-xs uppercase font-bold">Area</span>
            <span class="font-medium">
                   <?php echo isset($wa['length_cm']) ? number_format($wa['length_cm'] * $wa['width_cm'], 2) : 'N/A'; ?> cm²
                </span>
        </div>
        <div>
            <span class="block text-gray-500 text-xs uppercase font-bold">Tissue Type</span>
            <span class="font-medium text-xs">
                    Granulation: <?php echo $wa['granulation_percent'] ?? '-'; ?>% | Slough: <?php echo $wa['slough_percent'] ?? '-'; ?>%
                </span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-x-8 gap-y-2 p-2 text-sm">
        <div class="flex items-center"><?php echo isChecked('graft_check_size_requirement', $wa); ?> Size ≥ 1cm²</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_no_necrotic', $wa); ?> Clean Granular Base</div>
        <div class="flex items-center"><?php echo isChecked('graft_check_bone', $wa); ?> No Exposed Bone/Tendon</div>
        <div><strong>Thickness:</strong> <?php echo txt($wa['graft_wound_thickness']); ?></div>
    </div>

    <!-- 3. Product Information -->
    <div class="section-header">3. Graft Product Details</div>
    <table class="w-full text-sm border-collapse mt-2">
        <tr>
            <td class="p-2 border bg-gray-50 font-semibold w-1/3">Product Name</td>
            <td class="p-2 border"><?php echo txt($wa['graft_product_name']); ?></td>
        </tr>
        <tr>
            <td class="p-2 border bg-gray-50 font-semibold">Serial Number</td>
            <td class="p-2 border"><?php echo txt($wa['graft_serial_number']); ?></td>
        </tr>
        <tr>
            <td class="p-2 border bg-gray-50 font-semibold">Lot Number</td>
            <td class="p-2 border"><?php echo txt($wa['graft_lot_number']); ?></td>
        </tr>
        <tr>
            <td class="p-2 border bg-gray-50 font-semibold">Expiration Date</td>
            <td class="p-2 border"><?php echo fmtDate($wa['graft_expiry_date']); ?></td>
        </tr>
    </table>

    <div class="mt-3 text-sm">
        <span class="font-semibold">Treatment Goals / Justification:</span><br>
        <p class="text-gray-700 italic border-l-2 border-gray-300 pl-2 mt-1"><?php echo txt($wa['graft_treatment_goals']); ?></p>
    </div>

    <!-- 4. Application & Billing -->
    <div class="section-header">4. Application & Coding</div>
    <div class="grid grid-cols-4 gap-4 p-2 text-sm text-center">
        <!-- NEW: Total Product Size (Calculated) -->
        <div class="border p-3 rounded bg-gray-50">
            <div class="text-gray-500 text-xs uppercase">Total Product</div>
            <div class="font-bold text-lg">
                <?php
                $total = floatval($wa['graft_sqcm_used']) + floatval($wa['graft_sqcm_discarded']);
                echo number_format($total, 2);
                ?>
            </div>
        </div>

        <div class="border p-3 rounded">
            <div class="text-gray-500 text-xs uppercase">SqCm Used</div>
            <div class="font-bold text-lg"><?php echo txt($wa['graft_sqcm_used']); ?></div>
        </div>
        <div class="border p-3 rounded">
            <div class="text-gray-500 text-xs uppercase">SqCm Discarded</div>
            <div class="font-bold text-lg"><?php echo txt($wa['graft_sqcm_discarded']); ?></div>
            <div class="text-xs mt-1"><?php echo isChecked('graft_check_jw_modifier', $wa); ?> JW Modifier</div>
        </div>
        <div class="border p-3 rounded">
            <div class="text-gray-500 text-xs uppercase">Application #</div>
            <div class="font-bold text-lg"><?php echo txt($wa['graft_application_number']); ?> <span class="text-sm font-normal text-gray-400">/ 10</span></div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 mt-2 text-sm">
        <div class="bg-gray-50 p-2 rounded border">
            <strong>CPT Code:</strong> <?php echo txt($wa['graft_cpt_code']); ?>
        </div>
        <div class="bg-gray-50 p-2 rounded border">
            <strong>Q Code:</strong> <?php echo txt($wa['graft_q_code']); ?>
        </div>
    </div>

    <?php if(!empty($wa['graft_discard_justification'])): ?>
        <div class="mt-3 text-sm">
            <span class="font-semibold text-red-600">Wastage Justification:</span>
            <p class="text-gray-700 border-l-2 border-red-200 pl-2 mt-1"><?php echo txt($wa['graft_discard_justification']); ?></p>
        </div>
    <?php endif; ?>

    <!-- 5. Photographic Evidence -->
    <div class="page-break"></div>
    <div class="section-header mt-8">5. Photographic Evidence</div>

    <div class="grid grid-cols-2 gap-6 mt-4">
        <?php if(count($images) > 0): ?>
            <?php foreach($images as $img): ?>
                <div class="border rounded p-2 text-center">
                    <div class="h-64 flex items-center justify-center bg-gray-100 overflow-hidden mb-2">
                        <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="max-h-full max-w-full object-contain">
                    </div>
                    <p class="text-xs font-bold uppercase text-gray-600"><?php echo htmlspecialchars($img['image_type'] ?: 'Wound Image'); ?></p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-2 py-8 text-center text-gray-400 border-2 border-dashed rounded">No images uploaded for this session.</div>
        <?php endif; ?>
    </div>

    <!-- Footer Signature Area -->
    <div class="mt-12 pt-8 border-t-2 border-gray-300 flex justify-between items-end">
        <div>
            <div class="w-64 border-b border-gray-400 mb-2"></div>
            <p class="text-sm font-bold">Clinician Signature</p>
            <p class="text-xs text-gray-500"><?php echo txt($pt_data['clinician_name']); ?></p>
        </div>
        <div class="text-right text-xs text-gray-400">
            Electronic Record ID: <?php echo $appointment_id; ?>-<?php echo $wound_id; ?><br>
            Generated by EC Wound Charting
        </div>
    </div>

</div>

</body>
</html>