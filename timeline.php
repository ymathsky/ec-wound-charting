<?php
// Filename: timeline.php
session_start();
require_once 'db_connect.php';

// Auth Check
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Role Check
$allowed_roles = ['admin', 'clinician', 'scheduler'];
if (!in_array($_SESSION['ec_role'], $allowed_roles)) {
    header("Location: dashboard.php");
    exit();
}

// Check if user is allowed to ADD appointments (Admin/Scheduler only)
$can_add_appointment = in_array($_SESSION['ec_role'], ['admin', 'scheduler']);

require_once 'templates/header.php';
?>

    <div class="flex h-screen bg-gray-100 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="list-checks" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Appointment Timeline
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">A comprehensive chronological view of appointments.</p>
                </div>

                <!-- Quick Actions (Hidden for Clinicians) -->
                <?php if ($can_add_appointment): ?>
                    <div class="flex space-x-3">
                        <a href="add_appointment.php" data-tab-title="New Appointment" data-tab-icon="calendar-plus" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg flex items-center transition shadow-sm">
                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                            New Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

                <!-- Filters & Controls -->
                <div class="bg-white p-4 rounded-xl shadow-md border border-gray-200 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-1 gap-4">
                        <div class="w-full md:w-1/3">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Search Patient</label>
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-gray-400"></i>
                                <!-- Input remains the primary search mechanism, but we validate against the filtered patient list -->
                                <input type="text" id="searchTimeline" placeholder="Name or ID..." class="pl-9 w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <div class="w-full md:w-1/4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                            <select id="statusFilter" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 bg-white">
                                <option value="all">All Statuses</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="Confirmed">Confirmed</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                                <option value="No-show">No-show</option>
                            </select>
                        </div>
                        <div class="w-full md:w-1/4">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date</label>
                            <input type="date" id="dateFilter" class="w-full border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div>
                        <button id="resetFilters" class="text-sm text-gray-500 hover:text-indigo-600 underline">Reset Filters</button>
                    </div>
                </div>

                <!-- Timeline Table -->
                <div class="bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Patient</th>
                                <?php if ($_SESSION['ec_role'] === 'admin' || $_SESSION['ec_role'] === 'scheduler'): ?>
                                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Clinician</th>
                                <?php endif; ?>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Visit Type</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody id="timelineBody" class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                    <div class="flex justify-center items-center mb-2">
                                        <i data-lucide="loader-2" class="w-6 h-6 animate-spin text-indigo-600"></i>
                                    </div>
                                    Loading timeline data...
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination/Footer info -->
                    <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 text-sm text-gray-500 flex justify-between items-center">
                        <span id="recordCount">0 records found</span>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const timelineBody = document.getElementById('timelineBody');
            const searchInput = document.getElementById('searchTimeline');
            const statusFilter = document.getElementById('statusFilter');
            const dateFilter = document.getElementById('dateFilter');
            const resetBtn = document.getElementById('resetFilters');
            const recordCount = document.getElementById('recordCount');

            let allAppointments = [];
            let searchablePatientIds = new Set(); // NEW: Stores valid patient IDs for the search filter
            const userRole = "<?php echo $_SESSION['ec_role']; ?>";

            // --- 1. Fetch Patient List (Securely Filtered by API) ---
            async function fetchPatientList() {
                try {
                    const response = await fetch('api/get_patients_for_timeline.php');
                    const result = await response.json();

                    if (result.success && result.patients) {
                        result.patients.forEach(p => searchablePatientIds.add(p.patient_id));
                    }
                } catch (error) {
                    console.error('Error fetching patient list for search filter:', error);
                }
            }

            // --- 2. Fetch Timeline Data (Securely Filtered by API) ---
            async function fetchTimelineData() {
                // Load patient list first
                await fetchPatientList();

                try {
                    const response = await fetch('api/get_timeline_data.php');
                    const result = await response.json();

                    if (result.success) {
                        // The API ensures this data is already limited by clinician assignment
                        allAppointments = result.data;
                        renderTable(allAppointments);
                    } else {
                        timelineBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Error: ${result.message}</td></tr>`;
                    }
                } catch (error) {
                    console.error('Error fetching timeline:', error);
                    timelineBody.innerHTML = `<tr><td colspan="6" class="px-6 py-4 text-center text-red-500">Network Error. Please try again.</td></tr>`;
                }
            }

            // --- 3. Render Table (No changes needed here) ---
            function renderTable(data) {
                if (data.length === 0) {
                    timelineBody.innerHTML = `<tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">No appointments found.</td></tr>`;
                    recordCount.textContent = "0 records found";
                    return;
                }

                timelineBody.innerHTML = data.map(appt => {
                    // Status Styling
                    let statusClass = "bg-gray-100 text-gray-800";
                    if (appt.status === 'Confirmed') statusClass = "bg-green-100 text-green-800";
                    if (appt.status === 'Completed') statusClass = "bg-blue-100 text-blue-800";
                    if (appt.status === 'Cancelled' || appt.status === 'No-show') statusClass = "bg-red-100 text-red-800";
                    if (appt.status === 'Scheduled') statusClass = "bg-yellow-100 text-yellow-800";

                    // Clinician Column (Conditional)
                    const clinicianCol = (userRole === 'admin' || userRole === 'scheduler')
                        ? `<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">${appt.clinician_name || '<span class="text-gray-400 italic">Unassigned</span>'}</td>`
                        : '';

                    return `
                <tr class="hover:bg-gray-50 transition duration-150">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${appt.formatted_date}</div>
                        <div class="text-xs text-gray-500">${appt.formatted_time}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-indigo-600 hover:text-indigo-900">
                            <a href="patient_profile.php?id=${appt.patient_id}">${appt.patient_last}, ${appt.patient_first}</a>
                        </div>
                        <div class="text-xs text-gray-500 font-mono">${appt.patient_code}</div>
                    </td>
                    ${clinicianCol}
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        ${appt.appointment_type || 'Standard Visit'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                            ${appt.status}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="visit_vitals.php?appointment_id=${appt.appointment_id}&patient_id=${appt.patient_id}" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Open Visit">
                            <i data-lucide="folder-open" class="w-5 h-5 inline"></i>
                        </a>
                    </td>
                </tr>
            `;
                }).join('');

                recordCount.textContent = `${data.length} records found`;

                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }

            // --- 4. Filter Logic (Updated to use security principles) ---
            function filterData() {
                const term = searchInput.value.toLowerCase().trim();
                const status = statusFilter.value;
                const date = dateFilter.value; // Format: YYYY-MM-DD

                // Filter the already restricted list of appointments (`allAppointments`)
                const filtered = allAppointments.filter(item => {

                    // 4a. Patient Search Filter (Term must match patient data AND be part of the authorized patient list)
                    let matchesSearch = true;
                    if (term.length > 0) {
                        const termMatch =
                            (item.patient_first && item.patient_first.toLowerCase().includes(term)) ||
                            (item.patient_last && item.patient_last.toLowerCase().includes(term)) ||
                            (item.patient_code && item.patient_code.toLowerCase().includes(term));

                        // If the user is an admin/scheduler, we only need termMatch.
                        // If the user is a clinician, the API already restricted the list, so termMatch is sufficient.
                        matchesSearch = termMatch;
                    }

                    // 4b. Status Filter (Case-sensitive check as statuses are capitalized in render and filter options)
                    const matchesStatus = status === 'all' || (item.status && item.status === status);

                    // 4c. Date Filter
                    let matchesDate = true;
                    if (date) {
                        const itemDatePart = item.appointment_date.split(' ')[0];
                        matchesDate = itemDatePart === date;
                    }

                    return matchesSearch && matchesStatus && matchesDate;
                });

                renderTable(filtered);
            }

            // 5. Event Listeners
            searchInput.addEventListener('input', filterData);
            statusFilter.addEventListener('change', filterData);
            dateFilter.addEventListener('change', filterData);
            resetBtn.addEventListener('click', () => {
                searchInput.value = '';
                statusFilter.value = 'all';
                dateFilter.value = '';
                renderTable(allAppointments);
            });

            // Initialize
            fetchTimelineData();
        });
    </script>

<?php require_once 'templates/footer.php'; ?>