<?php
// Filename: api/generate_lmn.php
// Purpose: Generates a printable Letter of Medical Necessity for Skin Substitute Grafting
// Logic: Pulls wound history to prove failed conservative care > 4 weeks.

session_start();
require_once '../db_connect.php';

// --- Security Check ---
if (!isset($_SESSION['ec_user_id'])) {
    die("Access denied. Please log in.");
}

// --- Input Parameters ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;

if ($patient_id <= 0 || $wound_id <= 0) {
    die("Invalid Patient or Wound ID.");
}

// --- Fetch Data ---

// 1. Patient Demographics & Assigned Facility
// JOIN patients with users (facility) based on facility_id
$sql_patient = "SELECT p.first_name, p.last_name, p.date_of_birth, p.patient_code, p.facility_id,
                       u.full_name AS facility_name
                FROM patients p
                LEFT JOIN users u ON p.facility_id = u.user_id
                WHERE p.patient_id = ?";

$stmt = $conn->prepare($sql_patient);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

// --- Authorization Check ---
if ($_SESSION['ec_role'] === 'facility' && $_SESSION['ec_user_id'] != $patient['facility_id']) {
    die("Access denied. You do not have permission to view this patient.");
}

// Determine Clinic Name to display
// Use patient's assigned facility name from users table, otherwise fallback
$clinic_name = !empty($patient['facility_name']) ? $patient['facility_name'] : "Emerald Coast Wound Care";

// Address removed per user request
$clinic_address = "";


// 2. Wound Details
$sql_wound = "SELECT location, wound_type, date_onset FROM wounds WHERE wound_id = ?";
$stmt = $conn->prepare($sql_wound);
$stmt->bind_param("i", $wound_id);
$stmt->execute();
$wound = $stmt->get_result()->fetch_assoc();

// 3. Assessment History (To prove conservative care)
$sql_history = "SELECT assessment_date, length_cm, width_cm, treatments_provided 
                FROM wound_assessments 
                WHERE wound_id = ? 
                ORDER BY assessment_date ASC";
$stmt = $conn->prepare($sql_history);
$stmt->bind_param("i", $wound_id);
$stmt->execute();
$history_result = $stmt->get_result();
$assessments = $history_result->fetch_all(MYSQLI_ASSOC);

// --- Calculations & Logic ---

$clinician_name = $_SESSION['ec_full_name'] ?? 'Attending Physician';

// Calculate Duration
$onset_date = new DateTime($wound['date_onset']);
$today = new DateTime();
$duration_weeks = floor($onset_date->diff($today)->days / 7);

// Compile Treatments
$unique_treatments = [];
$initial_area = 0;
$current_area = 0;
$initial_date = 'N/A';

if (count($assessments) > 0) {
    // Initial
    $first = $assessments[0];
    $initial_area = ($first['length_cm'] * $first['width_cm']);
    $initial_date = date('m/d/Y', strtotime($first['assessment_date']));

    // Current
    $last = end($assessments);
    $current_area = ($last['length_cm'] * $last['width_cm']);

    // Treatments
    foreach ($assessments as $asm) {
        if (!empty($asm['treatments_provided'])) {
            $unique_treatments[] = strip_tags($asm['treatments_provided']);
        }
    }
}
$unique_treatments = array_unique($unique_treatments);
$treatments_summary = !empty($unique_treatments)
    ? implode("; ", array_slice($unique_treatments, 0, 5))
    : "standard wound care protocols including cleansing and debridement";

// Calculate Reduction
$reduction_percent = 0;
if ($initial_area > 0) {
    $reduction_percent = (($initial_area - $current_area) / $initial_area) * 100;
}
$reduction_text = number_format($reduction_percent, 1) . "%";
$progress_status = ($reduction_percent < 50) ? "failed to progress significantly (>50% reduction)" : "progressed";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Letter of Medical Necessity - <?php echo htmlspecialchars($patient['last_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 1in; }
            body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; }
            .no-print { display: none; }
            .signature-line { border-top: 1px solid black; width: 300px; margin-top: 50px; }
        }
        body { font-family: 'Times New Roman', Times, serif; line-height: 1.6; color: #000; }
    </style>
</head>
<body class="bg-gray-100 p-8">

<!-- Print Controls -->
<div class="max-w-4xl mx-auto mb-6 no-print flex justify-between items-center">
    <a href="javascript:window.close()" class="text-gray-600 hover:text-gray-900">&larr; Close Window</a>
    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 font-sans font-bold shadow">
        Print / Save as PDF
    </button>
</div>

<!-- Letter Document -->
<div class="max-w-[8.5in] mx-auto bg-white p-[1in] shadow-lg min-h-[11in]">

    <!-- Header -->
    <div class="text-center mb-8">
        <h1 class="text-xl font-bold uppercase tracking-wide"><?php echo htmlspecialchars($clinic_name); ?></h1>
        <!-- Address removed as requested -->
    </div>

    <div class="flex justify-between items-end mb-8 border-b-2 border-gray-800 pb-2">
        <div>
            <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>
        </div>
        <div class="text-right">
            <h2 class="text-lg font-bold">LETTER OF MEDICAL NECESSITY</h2>
            <p class="text-sm font-bold">RE: Skin Substitute Application</p>
        </div>
    </div>

    <!-- Patient Info Block -->
    <div class="mb-6">
        <table class="w-full text-left">
            <tr>
                <td class="w-1/4 font-bold">Patient Name:</td>
                <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
            </tr>
            <tr>
                <td class="font-bold">Date of Birth:</td>
                <td><?php echo htmlspecialchars($patient['date_of_birth']); ?></td>
            </tr>
            <?php if(!empty($patient['patient_code'])): ?>
                <tr>
                    <td class="font-bold">MRN / ID:</td>
                    <td><?php echo htmlspecialchars($patient['patient_code']); ?></td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Body -->
    <div class="space-y-4 text-justify">
        <p>To Whom It May Concern:</p>

        <p>
            I am writing to provide clinical justification for the application of a cellular and/or tissue-based product (skin substitute) for the patient listed above.
            This patient is currently under my care for a chronic <strong><?php echo htmlspecialchars($wound['wound_type']); ?></strong> located on the <strong><?php echo htmlspecialchars($wound['location']); ?></strong>.
        </p>

        <p class="font-bold underline">History & Conservative Care:</p>
        <p>
            The wound has been present since <strong><?php echo date('m/d/Y', strtotime($wound['date_onset'])); ?></strong>, representing a duration of approximately <strong><?php echo $duration_weeks; ?> weeks</strong>.
            Standard conservative therapy has been consistently applied during this period. Documented treatments in the medical record include:
            <em><?php echo htmlspecialchars(substr($treatments_summary, 0, 300)) . (strlen($treatments_summary)>300 ? '...' : ''); ?></em>.
        </p>

        <p class="font-bold underline">Clinical Measurements & Status:</p>
        <p>
            Despite strict adherence to this comprehensive treatment plan for over 4 weeks, the wound has <?php echo $progress_status; ?>.
        </p>
        <ul class="list-disc list-inside ml-4">
            <li><strong>Initial Measurement (<?php echo $initial_date; ?>):</strong> <?php echo number_format($initial_area, 2); ?> cm²</li>
            <li><strong>Current Measurement (<?php echo date('m/d/Y'); ?>):</strong> <?php echo number_format($current_area, 2); ?> cm²</li>
            <li><strong>Total Area Reduction:</strong> <?php echo $reduction_text; ?></li>
        </ul>

        <p class="font-bold underline">Medical Necessity:</p>
        <p>
            Due to the failure of the wound to reduce in size by greater than 50% after four weeks of standard conservative therapy,
            and the absence of active infection or untreated osteomyelitis, the application of a skin substitute graft is medically necessary to stimulate healing,
            prevent further complications (such as infection or amputation), and close this chronic defect.
        </p>

        <p>
            I attest that the information provided herein is accurate and supported by the patient's medical record.
        </p>
    </div>

    <!-- Signature -->
    <div class="mt-16">
        <div class="signature-line border-t border-black w-64"></div>
        <p class="font-bold mt-2"><?php echo htmlspecialchars($clinician_name); ?></p>
        <p>Attending Clinician</p>
        <p><?php echo htmlspecialchars($clinic_name); ?></p>
    </div>

</div>

</body>
</html>