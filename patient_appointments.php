<?php
// Filename: ec/patient_appointments.php

require_once 'templates/header.php';

// Get user role for conditional rendering
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';
?>

    <!-- Include Lucide Icons for UI consistency -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE (Matches blank_page.php standard) -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div class="flex items-center">
                    <h1 id="patient-name-header" class="text-3xl font-extrabold text-gray-900 flex items-center gap-2">
                        <i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Loading Patient...
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 ml-10">Manage visits, scheduling, and history.</p>
                </div>
                <div>
                    <!-- UPDATED: Redirects to add_appointment.php as requested -->
                    <a href="add_appointment.php?patient_id=<?php echo isset($_GET['id']) ? intval($_GET['id']) : ''; ?>" data-tab-title="New Appointment" data-tab-icon="calendar-plus" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <i class="fas fa-plus mr-2"></i> New Appointment
                    </a>
                </div>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <!-- Main Content Area uses p-6/p-8 padding -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6 sm:p-8">
                <!-- UPDATED: Removed max-w-7xl and mx-auto to stretch content horizontally -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- LEFT COLUMN: Patient Snapshot -->
                    <div class="lg:col-span-4 space-y-6">
                        <div id="demographics-container" class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                            <!-- Skeleton Loader -->
                            <div class="p-6 animate-pulse">
                                <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                                <div class="space-y-2">
                                    <div class="h-3 bg-gray-100 rounded w-full"></div>
                                    <div class="h-3 bg-gray-100 rounded w-5/6"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats Card (Optional visual enhancement) -->
                        <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl shadow-lg p-6 text-white">
                            <h3 class="text-lg font-semibold mb-1">Care Continuity</h3>
                            <p class="text-blue-100 text-sm mb-4">Track visit compliance and history.</p>
                            <div class="flex justify-between items-center">
                                <div class="text-center">
                                    <span id="stat-completed" class="block text-2xl font-bold">--</span>
                                    <span class="text-xs text-blue-200 uppercase tracking-wider">Completed</span>
                                </div>
                                <div class="h-8 w-px bg-blue-400"></div>
                                <div class="text-center">
                                    <span id="stat-upcoming" class="block text-2xl font-bold">--</span>
                                    <span class="text-xs text-blue-200 uppercase tracking-wider">Upcoming</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Appointments List -->
                    <div class="lg:col-span-8">
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 min-h-[500px] flex flex-col">

                            <!-- Tabs Navigation -->
                            <div class="border-b border-gray-200 px-6 pt-4">
                                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                    <button onclick="switchTab('upcoming')" id="tab-upcoming" class="tab-btn active-tab border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Upcoming
                                    </button>
                                    <button onclick="switchTab('past')" id="tab-past" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        Past / Completed
                                    </button>
                                    <button onclick="switchTab('all')" id="tab-all" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                        All History
                                    </button>
                                </nav>
                            </div>

                            <!-- List Content -->
                            <div id="appointments-list-content" class="p-6 flex-1">
                                <div class="flex justify-center items-center h-32 text-gray-400">
                                    <i class="fas fa-spinner fa-spin text-2xl mr-3"></i> Loading...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modern Reschedule Modal -->
    <div id="rescheduleModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeRescheduleModal()"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <!-- Modal Panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-calendar-alt text-indigo-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Reschedule Appointment
                            </h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500 mb-4">
                                    Select a new date and clinician. The current appointment will be marked as cancelled and linked to this new one.
                                </p>

                                <div id="reschedule-modal-message" class="hidden mb-4 text-sm p-3 rounded"></div>

                                <form id="rescheduleForm" class="space-y-4">
                                    <input type="hidden" name="old_appointment_id" id="reschedule_old_appt_id">
                                    <input type="hidden" name="patient_id" id="reschedule_patient_id">

                                    <div>
                                        <label for="new_appointment_date" class="block text-sm font-medium text-gray-700">New Date & Time</label>
                                        <input type="datetime-local" name="appointment_date" id="new_appointment_date" required
                                               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border p-2">
                                    </div>

                                    <div>
                                        <label for="reschedule_user_id" class="block text-sm font-medium text-gray-700">Assigned Clinician</label>
                                        <select name="user_id" id="reschedule_user_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md bg-white border">
                                            <option value="0">Loading clinicians...</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label for="reschedule_notes" class="block text-sm font-medium text-gray-700">Reason / Notes</label>
                                        <textarea name="notes" id="reschedule_notes" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm border p-2" placeholder="Optional"></textarea>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" id="confirmRescheduleBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Confirm
                    </button>
                    <button type="button" onclick="closeRescheduleModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Import FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* Custom scrollbar for a cleaner look */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #c7c7c7; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        .tab-btn { transition: all 0.2s; }
        .tab-btn.active-tab { border-bottom-color: #4f46e5; color: #4f46e5; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Global State
            const patientId = <?php echo isset($_GET['id']) ? intval($_GET['id']) : 0; ?>;
            const userRole = '<?php echo $user_role; ?>';
            let allAppointments = [];
            let currentTab = 'upcoming'; // 'upcoming', 'past', 'all'

            // DOM Elements
            const listContent = document.getElementById('appointments-list-content');
            const headerName = document.getElementById('patient-name-header');
            const demographicsContainer = document.getElementById('demographics-container');
            const modal = document.getElementById('rescheduleModal');
            const clinicianSelect = document.getElementById('reschedule_user_id');
            const modalMessage = document.getElementById('reschedule-modal-message');

            // --- 1. Initialization & Data Fetching ---

            async function init() {
                if (patientId <= 0) {
                    // If invalid patient ID, immediately render error in the main area
                    document.querySelector('main .grid').innerHTML = `<div class="p-8 text-center bg-red-100 text-red-800 rounded-lg lg:col-span-12">Invalid Patient ID was provided.</div>`;
                    return;
                }

                // Parallel fetching for speed
                await Promise.all([
                    fetchPatientProfile(),
                    fetchAppointments(),
                    loadClinicians()
                ]);
            }

            // Fetch Patient Details
            async function fetchPatientProfile() {
                try {
                    const res = await fetch(`api/get_patient_profile_data.php?id=${patientId}`);
                    const data = await res.json();

                    if(data && data.details) {
                        const p = data.details;
                        // Update Header - Use the updated 3xl format
                        headerName.innerHTML = `
                        <i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        ${p.first_name} ${p.last_name}
                    `;

                        // Render Demographics Card
                        renderDemographicsCard(p);
                    }
                } catch (e) {
                    console.error("Profile fetch error", e);
                    headerName.innerHTML = `<i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i> Error Loading Appointments`;
                }
            }

            // Fetch Appointments
            async function fetchAppointments() {
                try {
                    const res = await fetch(`api/get_patient_appointments.php?patient_id=${patientId}`);
                    const data = await res.json();

                    if (Array.isArray(data)) {
                        allAppointments = data;
                        updateStats(allAppointments);
                        renderList();
                    } else {
                        allAppointments = [];
                        renderList();
                    }
                } catch (e) {
                    listContent.innerHTML = `<div class="text-red-500 text-center p-4">Failed to load appointments.</div>`;
                }
            }

            // Fetch Clinicians (Robust Fix)
            async function loadClinicians() {
                try {
                    const response = await fetch('api/get_users.php');
                    if (!response.ok) throw new Error('Failed to load clinicians');

                    let usersData = await response.json();
                    let users = [];

                    // Handle various API response structures
                    if (Array.isArray(usersData)) {
                        users = usersData;
                    } else if (usersData && Array.isArray(usersData.data)) {
                        users = usersData.data;
                    } else if (usersData && usersData.users && Array.isArray(usersData.users)) {
                        users = usersData.users;
                    } else {
                        console.warn("Unexpected user API format:", usersData);
                    }

                    // Populate Select
                    clinicianSelect.innerHTML = '<option value="0">-- Unassigned --</option>';

                    if (users.length > 0) {
                        users.forEach(user => {
                            // Optional: Filter by role if your API returns role
                            // if (user.role !== 'clinician' && user.role !== 'admin') return;

                            const option = document.createElement('option');
                            option.value = user.user_id;
                            // Handle potential different key names (e.g., full_name vs name)
                            const name = user.full_name || user.name || user.username || 'Unknown User';
                            const role = user.role ? ` (${user.role})` : '';
                            option.textContent = `${name}${role}`;
                            clinicianSelect.appendChild(option);
                        });
                    } else {
                        const option = document.createElement('option');
                        option.textContent = "No clinicians found";
                        option.disabled = true;
                        clinicianSelect.appendChild(option);
                    }

                } catch (error) {
                    console.error("Error loading clinicians:", error);
                    clinicianSelect.innerHTML = '<option value="0">Error loading list</option>';
                }
            }

            // --- 2. Rendering Logic ---

            function renderDemographicsCard(p) {
                demographicsContainer.innerHTML = `
                <div class="p-5 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-800">Patient Overview</h3>
                </div>
                <div class="p-6 space-y-4 text-sm text-gray-600">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs font-medium text-gray-400 uppercase">DOB / Age</span>
                            <span class="font-medium text-gray-900">${p.date_of_birth} (${calculateAge(p.date_of_birth)})</span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-400 uppercase">Code</span>
                            <span class="font-medium text-gray-900">${p.patient_code || 'N/A'}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-400 uppercase">Phone</span>
                            <span class="font-medium text-gray-900">${p.contact_number || '--'}</span>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-400 uppercase">Gender</span>
                            <span class="font-medium text-gray-900">${p.gender}</span>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-gray-100">
                        <span class="block text-xs font-medium text-gray-400 uppercase mb-1">Address</span>
                        <p>${p.address || 'No address on file'}</p>
                    </div>
                </div>
            `;
            }

            function renderList() {
                // Filter based on current tab
                const now = new Date();
                let filtered = [];

                if (currentTab === 'upcoming') {
                    filtered = allAppointments.filter(a => {
                        const d = new Date(a.appointment_date);
                        return d >= now && ['Scheduled', 'Confirmed', 'Checked-in', 'No-show'].includes(a.status);
                    });
                    // Sort upcoming: nearest date first
                    filtered.sort((a, b) => new Date(a.appointment_date) - new Date(b.appointment_date));
                } else if (currentTab === 'past') {
                    filtered = allAppointments.filter(a => {
                        const d = new Date(a.appointment_date);
                        return d < now || a.status === 'Completed';
                    });
                    // Sort past: most recent first
                    filtered.sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date));
                } else {
                    // All
                    filtered = [...allAppointments];
                    filtered.sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date));
                }

                if (filtered.length === 0) {
                    listContent.innerHTML = `
                    <div class="text-center py-12">
                        <div class="bg-gray-50 rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-calendar-times text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900">No appointments found</h3>
                        <p class="text-gray-500">There are no visits in this category.</p>
                    </div>
                `;
                    return;
                }

                let html = `<div class="space-y-4">`;

                filtered.forEach(appt => {
                    const dateObj = new Date(appt.appointment_date);
                    const day = dateObj.getDate();
                    const month = dateObj.toLocaleString('default', { month: 'short' });
                    const time = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    const statusStyle = getStatusStyle(appt.status);

                    // Define Actions
                    let actions = '';
                    if (appt.status === 'Completed') {
                        actions = `<a href="visit_report.php?appointment_id=${appt.appointment_id}&patient_id=${patientId}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium flex items-center"><i class="fas fa-file-medical mr-2"></i> Report</a>`;
                    } else if (['Scheduled', 'Confirmed', 'Checked-in', 'No-show'].includes(appt.status)) {
                        if (userRole === 'admin' || userRole === 'clinician') {
                            actions = `
                            <a href="visit_vitals.php?appointment_id=${appt.appointment_id}&patient_id=${patientId}&user_id=${appt.user_id}"
                               class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none mr-2">
                                <i class="fas fa-stethoscope mr-1.5"></i> Start
                            </a>
                            <button onclick="openRescheduleModal(${appt.appointment_id}, ${patientId}, ${appt.user_id || 0})"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                <i class="fas fa-clock mr-1.5"></i> Reschedule
                            </button>
                        `;
                        }
                    }

                    html += `
                    <div class="flex flex-col sm:flex-row items-start sm:items-center bg-white border border-gray-100 rounded-lg p-4 hover:shadow-md transition-shadow duration-200">
                        <!-- Date Badge -->
                        <div class="flex-shrink-0 mr-5 text-center bg-gray-50 rounded-lg p-2 min-w-[70px]">
                        <span class="block text-sm font-bold text-gray-500 uppercase">${month}</span>
                        <span class="block text-2xl font-bold text-gray-900">${day}</span>
                        </div>

                        <!-- Info -->
                        <div class="flex-grow mt-2 sm:mt-0">
                        <div class="flex items-center justify-between mb-1">
                        <h4 class="text-lg font-semibold text-gray-900">${time} - ${appt.appointment_type || 'Follow Up'}</h4>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusStyle.bg} ${statusStyle.text}">
                        ${appt.status}
                        </span>
                        </div>
                        <p class="text-sm text-gray-500 flex items-center gap-4">
                        <span class="flex items-center"><i class="fas fa-user-md mr-2 text-gray-400"></i> ${appt.clinician_name || 'Unassigned'}</span>
                        ${appt.notes ? `<span class="flex items-center text-gray-400" title="${appt.notes}"><i class="fas fa-sticky-note mr-1"></i> Note</span>` : ''}
                        </p>
                        </div>

                        <!-- Actions -->
                        <div class="mt-4 sm:mt-0 sm:ml-6 flex-shrink-0 flex items-center">
                        ${actions}
                        </div>
                        </div>
                        `;
            });

            html += `</div>`;
            listContent.innerHTML = html;
        }

        // --- 3. Helpers & Event Handlers ---

        function getStatusStyle(status) {
            switch (status) {
                case 'Completed': return { bg: 'bg-green-100', text: 'text-green-800' };
                case 'Scheduled': return { bg: 'bg-blue-100', text: 'text-blue-800' };
                case 'Cancelled': return { bg: 'bg-red-100', text: 'text-red-800' };
                case 'No-show': return { bg: 'bg-orange-100', text: 'text-orange-800' };
                case 'Confirmed': return { bg: 'bg-indigo-100', text: 'text-indigo-800' };
                default: return { bg: 'bg-gray-100', text: 'text-gray-800' };
            }
        }

        function updateStats(appointments) {
            const completed = appointments.filter(a => a.status === 'Completed').length;
            const upcoming = appointments.filter(a => ['Scheduled', 'Confirmed'].includes(a.status) && new Date(a.appointment_date) >= new Date()).length;

            document.getElementById('stat-completed').textContent = completed;
            document.getElementById('stat-upcoming').textContent = upcoming;
        }

        function calculateAge(dobString) {
            if (!dobString) return '?';
            const dob = new Date(dobString);
            const diff_ms = Date.now() - dob.getTime();
            const age_dt = new Date(diff_ms);
            return Math.abs(age_dt.getUTCFullYear() - 1970);
        }

        // Expose tab switching to global scope for HTML onclick
        window.switchTab = function(tabName) {
            currentTab = tabName;
            // Update classes
            document.querySelectorAll('.tab-btn').forEach(btn => {
                if (btn.id === `tab-${tabName}`) {
                    btn.classList.add('active-tab', 'border-indigo-500', 'text-indigo-600');
                    btn.classList.remove('border-transparent', 'text-gray-500');
                } else {
                    btn.classList.remove('active-tab', 'border-indigo-500', 'text-indigo-600');
                    btn.classList.add('border-transparent', 'text-gray-500');
                }
            });
            renderList();
        };

        // --- 4. Modal Logic ---

        window.openRescheduleModal = function(appointmentId, patientId, userId) {
            document.getElementById('reschedule_old_appt_id').value = appointmentId;
            document.getElementById('reschedule_patient_id').value = patientId;

            // IMPORTANT: Ensure userId is a string for value comparison in select
            clinicianSelect.value = userId ? String(userId) : "0";

            document.getElementById('new_appointment_date').value = '';
            document.getElementById('reschedule_notes').value = '';
            modalMessage.classList.add('hidden');
            modalMessage.classList.remove('bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800');

            modal.classList.remove('hidden');
        };

        window.closeRescheduleModal = function() {
            modal.classList.add('hidden');
        };

        document.getElementById('confirmRescheduleBtn').addEventListener('click', async function() {
            const form = document.getElementById('rescheduleForm');
            const formData = new FormData(form);
            const btn = this;

            if (!formData.get('appointment_date')) {
                showModalMessage('Please select a new date.', 'error');
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Processing...';

            try {
                const response = await fetch('api/reschedule_appointment.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed');
                }

                showModalMessage('Rescheduled successfully!', 'success');
                setTimeout(() => {
                    closeRescheduleModal();

                    // Open in new tab instead of redirect
                    if (result.new_appointment_id) {
                        const url = `add_appointment.php?appointment_id=${result.new_appointment_id}&patient_id=${patientId}`;
                        if (window.navigateInTab) {
                            window.navigateInTab(url, 'Edit Appointment', 'calendar-plus');
                        } else {
                            window.location.href = url;
                        }
                    } else {
                        fetchAppointments(); // Fallback if ID not returned
                    }
                }, 1000);

            } catch (error) {
                showModalMessage(error.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Confirm';
            }
        });

        function showModalMessage(msg, type) {
            modalMessage.textContent = msg;
            modalMessage.className = `mb-4 text-sm p-3 rounded ${type === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`;
            modalMessage.classList.remove('hidden');
        }

        // Run Init
        init();
    });
</script>

<?php
require_once 'templates/footer.php';
?>