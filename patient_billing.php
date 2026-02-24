<?php
// Filename: ec/patient_billing.php

require_once 'templates/header.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    // If the user is not an admin, show an access denied message and exit
    echo "<div class='flex h-screen bg-gray-50'><div class='m-auto max-w-lg bg-white p-10 rounded-xl shadow-2xl border border-red-200 text-center'>";
    echo "<h2 class='text-3xl font-bold text-red-600 mb-4'>Access Denied</h2>";
    echo "<p class='text-gray-700'>You do not have the required administrative permissions to view patient billing records.</p>";
    echo "</div></div>";
    require_once 'templates/footer.php';
    exit();
}

// Get patient_id from URL
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
?>

    <!-- Include Lucide Icons and FontAwesome for UI consistency -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE (Matches blank_page.php standard) -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 id="patient-name-header" class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="wallet" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Billing History
                    </h1>
                    <p id="patient-subheader" class="text-sm text-gray-500 mt-1 ml-10">Review of Superbills and CPT Coding per visit.</p>
                </div>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <!-- Main Content Area uses p-6/p-8 padding and stretched layout -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6 sm:p-8">

                <div class="space-y-6">

                    <!-- ROW 1: Patient Demographics Band (Full Width) -->
                    <div id="patient-demographics-band" class="w-full">
                        <!-- Skeleton Loader for Demographics -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4 animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                            <div class="space-y-2">
                                <div class="h-3 bg-gray-100 rounded w-full"></div>
                                <div class="h-3 bg-gray-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: Master List and Detail View (Constrained to vertical height) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                        <!-- COLUMN 1: Master Visit List (lg:col-span-3) -->
                        <div class="lg:col-span-3 h-full-constrained">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col h-full">
                                <h4 class="text-lg font-semibold text-gray-800 p-4 border-b sticky top-0 bg-white z-10 rounded-t-xl">Visits with Superbill</h4>
                                <div id="visit-list-container" class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-[100px]">
                                    <!-- List items injected here -->
                                    <div class="p-4 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading billing history...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMN 2: Detail View (lg:col-span-9 - Takes up remaining width) -->
                        <div id="visit-detail-panel" class="lg:col-span-9 h-full-constrained">
                            <div id="superbill-detail-content" class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 h-full overflow-y-auto flex items-center justify-center text-gray-400 italic">
                                Select a visit to view the Superbill details and edit coding.
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Superbill Viewer Modal -->
    <div id="superbillEditorModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="superbill-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeSuperbillEditor()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel (Wider max-w-5xl, high vertical usage) -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle w-full h-[95vh] max-w-5xl">
                <div class="h-full flex flex-col">
                    <!-- Header -->
                    <div class="flex justify-between items-center bg-indigo-600 text-white p-3 flex-shrink-0">
                        <h3 class="text-lg font-medium" id="superbill-title">Superbill Viewer</h3>
                        <button type="button" onclick="closeSuperbillEditor()" class="text-white hover:text-indigo-200">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>

                    <!-- Iframe Container -->
                    <div class="flex-1 min-h-0 bg-gray-100 relative">
                        <div id="superbill-loading" class="absolute inset-0 flex items-center justify-center bg-gray-100 z-10">
                            <i class="fas fa-spinner fa-spin text-4xl text-indigo-500"></i>
                            <span class="ml-3 text-indigo-700">Loading Superbill Viewer...</span>
                        </div>
                        <!-- The iframe loads the print page -->
                        <iframe id="superbill-iframe" src="" class="w-full h-full border-0" onload="hideSuperbillLoading()"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <style>
        /* Fixed Height Utility */
        .h-full-constrained {
            min-height: calc(100vh - 250px);
            max-height: calc(100vh - 250px);
        }

        /* Master List Card Styling */
        .visit-list-item {
            padding: 1rem;
            cursor: pointer;
            transition: background-color 0.15s, border-left 0.15s;
            border-left: 3px solid transparent;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .visit-list-item:hover {
            background-color: #f3f4f6; /* gray-100 */
        }
        .visit-list-item.active {
            background-color: #eef2ff; /* indigo-50 */
            border-left: 3px solid #4f46e5; /* indigo-600 */
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .visit-date-block {
            width: 45px;
            flex-shrink: 0;
            text-align: center;
            background-color: #f7f7f9;
            border-radius: 4px;
            padding: 4px 0;
            font-weight: 700;
            border: 1px solid #e5e7eb;
        }
        .visit-date-block .month {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #6b7280;
        }
        .visit-date-block .day {
            font-size: 1.1rem;
            line-height: 1;
            color: #1f2937;
        }
    </style>

    <script>
        // FIX: Define patientId in the global scope (window) so onclick handlers can see it.
        window.patientId = <?php echo $patient_id; ?>;

        // Global function used by the iframe onload event
        window.hideSuperbillLoading = function() {
            document.getElementById('superbill-loading').classList.add('hidden');
        }

        // Global function to open the Superbill Viewer Modal
        window.openSuperbillViewer = function(appointmentId) {
            const modal = document.getElementById('superbillEditorModal');
            const iframe = document.getElementById('superbill-iframe');
            const loading = document.getElementById('superbill-loading');

            // UPDATED: Point to the new print-specific page
            const url = `print_superbill.php?patient_id=${window.patientId}&appointment_id=${appointmentId}`;

            loading.classList.remove('hidden');
            iframe.src = url;

            modal.classList.remove('hidden');
        };

        window.closeSuperbillEditor = function() {
            const modal = document.getElementById('superbillEditorModal');
            const iframe = document.getElementById('superbill-iframe');

            // Clear iframe content and hide the modal
            iframe.src = '';
            modal.classList.add('hidden');
        };

        document.addEventListener('DOMContentLoaded', function() {

            const patientNameHeader = document.getElementById('patient-name-header');
            const demographicsBand = document.getElementById('patient-demographics-band');
            const visitListContainer = document.getElementById('visit-list-container');
            const detailPanel = document.getElementById('superbill-detail-content');

            let billingHistoryData = {}; // Stores data keyed by appointment_id

            function calculateAge(dobString) {
                if (!dobString) return '?';
                const dob = new Date(dobString);
                const diff_ms = Date.now() - dob.getTime();
                const age_dt = new Date(diff_ms);
                return Math.abs(age_dt.getUTCFullYear() - 1970);
            }

            async function fetchBillingHistory() {
                if (window.patientId <= 0) {
                    visitListContainer.innerHTML = `<p class="text-center text-red-500 p-4">Invalid Patient ID.</p>`;
                    return;
                }

                try {
                    // 1. Fetch patient details for demographics and header
                    const patientResponse = await fetch(`api/get_patient_profile_data.php?id=${window.patientId}`);
                    if(!patientResponse.ok) throw new Error('Failed to fetch patient details.');
                    const patientData = await patientResponse.json();
                    const p = patientData.details;

                    if (patientNameHeader) {
                        patientNameHeader.innerHTML = `<i data-lucide="wallet" class="w-7 h-7 mr-3 text-indigo-600"></i> Billing History: ${p.first_name} ${p.last_name}`;
                    }
                    renderDemographics(p);

                    // 2. Fetch billing history
                    const response = await fetch(`api/get_patient_billing_history.php?patient_id=${window.patientId}`);
                    if (!response.ok) throw new Error('Failed to fetch billing history.');
                    const history = await response.json();

                    billingHistoryData = history;

                    const sortedAppointments = Object.values(history).sort((a, b) =>
                        new Date(b.appointment.appointment_date) - new Date(a.appointment.appointment_date)
                    );

                    renderMasterList(sortedAppointments);

                    // Automatically show the latest entry (if available)
                    if (sortedAppointments.length > 0) {
                        loadVisitDetail(sortedAppointments[0].appointment.appointment_id);
                        const firstItem = document.getElementById(`list-item-${sortedAppointments[0].appointment.appointment_id}`);
                        if (firstItem) {
                            firstItem.classList.add('active');
                        }
                    } else {
                        detailPanel.innerHTML = '<div class="p-6 text-gray-500 italic">No superbills recorded for this patient.</div>';
                    }

                } catch (error) {
                    visitListContainer.innerHTML = `<p class="text-center text-red-500 p-4">Error loading billing history: ${error.message}</p>`;
                    detailPanel.innerHTML = '<div class="p-6 text-red-500">Could not load details.</div>';
                }
                lucide.createIcons();
            }

            function renderDemographics(patient) {
                demographicsBand.innerHTML = `
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 text-sm">

                        <!-- Name/DOB (Main Info) -->
                    <div class="border-r border-gray-100 pr-4">
                    <p class="font-bold text-lg text-gray-900">${patient.first_name} ${patient.last_name}</p>
                    <p class="text-xs text-gray-500">${patient.date_of_birth} (${calculateAge(patient.date_of_birth)})</p>
                    </div>

                    <!-- ID/Gender -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Patient ID / Gender</span>
                    <p class="font-medium text-gray-900">${patient.patient_code || 'N/A'} / ${patient.gender}</p>
                    </div>

                    <!-- Primary MD -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Primary Clinician</span>
                    <p class="font-medium text-indigo-600">${patient.primary_doctor_name || 'Unassigned'}</p>
                    </div>

                    <!-- Phone -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Phone</span>
                    <p class="font-medium text-gray-900">${patient.contact_number || '--'}</p>
                    </div>

                    <!-- Allergies -->
                    <div class="col-span-full md:col-span-1 border-l border-gray-100 pl-4 bg-red-50 rounded-lg">
                    <span class="block text-xs font-medium text-red-700 uppercase">Allergies</span>
                    <p class="text-sm font-semibold text-red-800">${patient.allergies || 'NKDA'}</p>
                    </div>

                    <!-- Medical History Summary -->
                    <div class="col-span-full md:col-span-1 border-l border-gray-100 pl-4 bg-gray-50 rounded-lg">
                    <span class="block text-xs font-medium text-gray-600 uppercase">PMH Summary</span>
                    <p class="text-xs font-medium text-gray-800 truncate">${patient.past_medical_history || 'N/A'}</p>
                    </div>
                    </div>
                    </div>
                    `;
        }

        function renderMasterList(appointments) {
            if (appointments.length === 0) {
                visitListContainer.innerHTML = '<p class="text-center text-gray-500 p-4">No superbills recorded.</p>';
                return;
            }

            const listHtml = appointments.map(entry => {
                const appt = entry.appointment;
                const services = entry.services;

                const dateObj = new Date(appt.appointment_date);
                const day = dateObj.getDate();
                const month = dateObj.toLocaleDateString('en-US', { month: 'short' });
                const time = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const clinicianName = appt.clinician_name || 'Unassigned';

                // Assuming 'finalized_at' exists to determine if it's finalized or draft.
                const isFinalized = appt.finalized_at !== null && appt.finalized_at !== undefined;
                const status = isFinalized ? 'Finalized' : 'Draft';
                const statusClass = isFinalized ? 'text-green-600' : 'text-orange-500';

                // Calculate total procedures (units)
                const totalUnits = services.reduce((sum, service) => sum + service.units, 0);

                return `
                    <div id="list-item-${appt.appointment_id}"
                    class="visit-list-item"
                    onclick="loadVisitDetail(${appt.appointment_id}, this)">

                    <!-- Date Block -->
                    <div class="visit-date-block">
                    <span class="month">${month}</span>
                    <span class="day">${day}</span>
                    </div>

                    <!-- Visit Summary -->
                    <div class="flex-1">
                    <p class="font-bold text-gray-900 leading-tight">${appt.appointment_type || 'Visit'}</p>
                    <p class="text-xs text-gray-500">${time}</p>
                    <p class="text-xs text-gray-600 truncate mt-1">
                    <i class="fas fa-user-md mr-1"></i> ${clinicianName}
                    </p>
                    <p class="text-xs font-semibold flex items-center mt-1">
                    <span class="${statusClass}">${status}</span>
                    <span class="text-gray-400 ml-auto">
                    ${services.length} Codes
                    </span>
                    </p>
                    </div>
                    </div>
                    `;
            }).join('');

            visitListContainer.innerHTML = listHtml;
        }

        window.loadVisitDetail = function(appointmentId, element) {
            // Highlight the active item
            document.querySelectorAll('.visit-list-item').forEach(item => item.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            }

            detailPanel.innerHTML = `<div class="p-6 flex items-center justify-center text-gray-500 h-full"><i class="fas fa-spinner fa-spin mr-2"></i> Loading Superbill...</div>`;

            const entry = billingHistoryData[appointmentId];

            if (!entry) {
                detailPanel.innerHTML = `<div class="p-6 text-red-500 h-full">Error: Superbill data not found for this appointment.</div>`;
                return;
            }

            const appt = entry.appointment;
            const services = entry.services;

            const servicesHtml = services.map(service => `
                    <tr class="text-sm border-b hover:bg-gray-50">
                    <td class="py-2 px-4 text-gray-900 font-semibold">${service.cpt_code}</td>
                    <td class="py-2 px-4 text-gray-600">${service.description}</td>
                    <td class="py-2 px-4 text-center text-gray-800">${service.units}</td>
                    </tr>
                    `).join('');

            // --- Render Detail Panel ---
            detailPanel.innerHTML = `
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 h-full overflow-y-auto">
                <div class="p-5 border-b flex justify-between items-center bg-indigo-50 rounded-t-xl sticky top-0 z-10">
                <div>
                <h2 class="text-2xl font-bold text-indigo-800">${new Date(appt.appointment_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</h2>
        <p class="text-sm text-indigo-600 mt-1">Visit Type: ${appt.appointment_type || 'N/A'}</p>
        </div>
        <!-- UPDATED BUTTON: Calls View/Print Superbill Modal with both IDs -->
        <button onclick="openSuperbillViewer(${appt.appointment_id})" type="button" class="text-white hover:bg-indigo-700 text-sm font-medium flex items-center bg-indigo-600 px-3 py-2 rounded-full shadow-md transition-colors">
            <i data-lucide="printer" class="w-4 h-4 mr-1"></i> View/Print Superbill
        </button>
        </div>

        <div class="p-5 space-y-6">

            <!-- Summary Section -->
            <div class="grid grid-cols-3 gap-4 text-center">
            <div class="detail-card">
            <p class="text-3xl font-bold text-indigo-600">${services.length}</p>
            <p class="text-sm text-gray-500">Total Codes</p>
        </div>
        <div class="detail-card">
            <p class="text-3xl font-bold text-indigo-600">${services.reduce((sum, service) => sum + service.units, 0)}</p>
            <p class="text-sm text-gray-500">Total Units</p>
        </div>
        <div class="detail-card">
            <p class="text-3xl font-bold ${appt.finalized_at ? 'text-green-600' : 'text-orange-500'}">
            ${appt.finalized_at ? 'Finalized' : 'Draft'}
            </p>
            <p class="text-xs text-gray-500">Status</p>
            </div>
            </div>

            <!-- CPT Code Table -->
            <h3 class="text-xl font-semibold text-gray-800 pt-3 border-t">CPT Codes & Services</h3>

        <div class="overflow-x-auto rounded-lg border">
            <table class="min-w-full">
            <thead>
            <tr class="bg-gray-50 text-left">
            <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">CPT Code</th>
        <th class="py-3 px-4 text-xs font-medium text-gray-500 uppercase">Description</th>
            <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase">Units</th>
            </tr>
            </thead>
            <tbody>
            ${servicesHtml}
            </tbody>
            </table>
            </div>

            <div class="pt-4 border-t">
            <h3 class="text-lg font-semibold text-gray-800">Visit Context</h3>
        <p class="text-sm text-gray-600 mt-2">Clinician: ${appt.clinician_name || 'N/A'}</p>
        <p class="text-sm text-gray-600">Appointment ID: ${appt.appointment_id}</p>
        </div>
        </div>
        </div>
        `;
            lucide.createIcons();
        }

        fetchBillingHistory();
    });
</script>

<?php
require_once 'templates/footer.php';
?>