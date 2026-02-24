<?php
// Filename: ec/print_superbill.php
// Purpose: Renders a clean, print-optimized view of a single Superbill/Encounter for printing or PDF generation.

// Requires minimal components: db connection and API fetchers
require_once 'db_connect.php';
// Note: We avoid including header.php, sidebar.php, and footer.php for a clean print output.

// Get required IDs
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    die("Error: Invalid Patient or Appointment ID.");
}

// --- Data Fetching Logic (Requires API calls to consolidate data) ---
// Since this file should ideally fetch data directly without headers/footers,
// we call the appropriate API endpoint internally or replicate the logic.
// For simplicity and assuming API consistency, we replicate fetching needed data.

$superbill_data = null;
$patient_details = null;

// Mock function to replicate fetching Superbill data (replace with actual consolidated API call if possible)
function fetchSuperbillData($conn, $patient_id, $appointment_id) {
    // 1. Get Appointment and Clinician Details
    $sql = "SELECT a.*, u.full_name AS clinician_name, p.first_name, p.last_name, p.date_of_birth, p.patient_code
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            LEFT JOIN users u ON a.user_id = u.user_id
            WHERE a.appointment_id = ? AND a.patient_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $appointment_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();
    $stmt->close();

    if (!$appointment) return null;

    // 2. Get Services (CPT Codes)
    $sql_services = "SELECT ss.cpt_code, ss.units, cc.description
                     FROM superbill_services ss
                     JOIN cpt_codes cc ON ss.cpt_code = cc.code
                     WHERE ss.appointment_id = ?";
    $stmt_services = $conn->prepare($sql_services);
    $stmt_services->bind_param("i", $appointment_id);
    $stmt_services->execute();
    $services = $stmt_services->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_services->close();

    return [
        'appointment' => $appointment,
        'services' => $services
    ];
}

$superbill_data = fetchSuperbillData($conn, $patient_id, $appointment_id);

if (!$superbill_data) {
    die("Error: Superbill data not found for this visit.");
}

$appt = $superbill_data['appointment'];
$services = $superbill_data['services'];

$total_units = array_reduce($services, function($sum, $item) { return $sum + $item['units']; }, 0);
$date_display = date('F j, Y', strtotime($appt['appointment_date']));
$time_display = date('h:i A', strtotime($appt['appointment_date']));
$is_finalized = $appt['finalized_at'] !== null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Superbill - <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?> (<?php echo $date_display; ?>)</title>
    <!-- Use Tailwind CDN just for minimal styling and print utilities -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            color: #1f2937;
            padding: 0;
        }
        /* Hide non-print elements */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .page-break {
                page-break-before: always;
            }
        }
        .header-band {
            background-color: #eef2ff; /* indigo-50 */
        }
    </style>
</head>
<body class="p-6">

<div class="max-w-4xl mx-auto">
    <div class="flex justify-end mb-4 no-print">
        <button onclick="window.print()" class="bg-indigo-600 text-white px-4 py-2 rounded-md shadow-md hover:bg-indigo-700">
            <i class="fas fa-print mr-2"></i> Print Superbill
        </button>
    </div>

    <!-- Document Header / Title -->
    <div class="header-band p-5 rounded-lg border border-indigo-200 mb-6 shadow-sm">
        <h1 class="text-2xl font-extrabold text-indigo-800 mb-1">SUPERBILL / ENCOUNTER SUMMARY</h1>
        <p class="text-sm text-gray-600">Generated: <?php echo date('Y-m-d H:i'); ?></p>
    </div>

    <!-- Patient and Visit Details -->
    <div class="grid grid-cols-2 gap-4 border border-gray-300 rounded-lg p-4 mb-6">
        <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Patient Information</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']); ?></p>
            <p><strong>DOB:</strong> <?php echo htmlspecialchars($appt['date_of_birth']); ?></p>
            <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($appt['patient_code']); ?></p>
        </div>
        <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-2">Visit Information</h2>
            <p><strong>Date:</strong> <?php echo $date_display; ?> (<?php echo $time_display; ?>)</p>
            <p><strong>Clinician:</strong> <?php echo htmlspecialchars($appt['clinician_name'] ?: 'N/A'); ?></p>
            <p><strong>Status:</strong> <span class="<?php echo $is_finalized ? 'text-green-600' : 'text-orange-500'; ?> font-bold"><?php echo $is_finalized ? 'Finalized' : 'Draft'; ?></span></p>
        </div>
    </div>

    <!-- Services / CPT Codes Table -->
    <h2 class="text-xl font-semibold text-gray-800 mb-3 border-b pb-1">Billed Services (CPT Codes)</h2>

    <?php if (!empty($services)): ?>
        <div class="overflow-x-auto rounded-lg border border-gray-300">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    <th class="py-3 px-4 w-1/5">CPT Code</th>
                    <th class="py-3 px-4 w-3/5">Description</th>
                    <th class="py-3 px-4 text-center w-1/5">Units</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($services as $service): ?>
                    <tr class="text-sm">
                        <td class="py-3 px-4 font-semibold text-gray-900"><?php echo htmlspecialchars($service['cpt_code']); ?></td>
                        <td class="py-3 px-4 text-gray-700"><?php echo htmlspecialchars($service['description']); ?></td>
                        <td class="py-3 px-4 text-center text-gray-800"><?php echo htmlspecialchars($service['units']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr class="bg-gray-50">
                    <td colspan="2" class="py-3 px-4 text-right text-base font-bold text-gray-700">Total Units:</td>
                    <td class="py-3 px-4 text-center text-base font-extrabold text-indigo-700"><?php echo $total_units; ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php else: ?>
        <p class="text-center text-gray-500 p-4 border rounded-lg">No services or codes were recorded for this visit.</p>
    <?php endif; ?>

    <!-- Footer Information / Signature Placeholder -->
    <div class="mt-12 pt-6 border-t border-gray-300 text-sm">
        <p class="mb-2">This document serves as a record of services provided and billed for the specified patient and encounter date.</p>
        <p class="text-xs text-gray-500">Note: Financial values (fees) are omitted. This document is for coding and record keeping purposes only.</p>

        <div class="mt-8 grid grid-cols-2 gap-8">
            <div>
                <p class="text-gray-900 font-medium">Provider Signature:</p>
                <div class="mt-8 border-b border-gray-400 w-3/4"></div>
            </div>
        </div>
    </div>

</div>
</body>
</html>