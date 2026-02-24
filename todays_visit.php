<?php
// Filename: todays_visit.php

// Set the default timezone to avoid date discrepancies, especially for "today's" date
require_once 'templates/header.php';
require_once 'db_connect.php';
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Role Check - Ensure user is authorized to view the calendar (Admin, Scheduler, Clinician)
$allowed_view_roles = ['admin', 'clinician', 'scheduler', 'facility'];
if (!in_array($_SESSION['ec_role'], $allowed_view_roles)) {
    header("Location: dashboard.php");
    exit();
}

// CRITICAL: Determine if the user can ADD appointments
$can_schedule = in_array($_SESSION['ec_role'], ['admin', 'scheduler']);
?>

    <!-- Include Lucide Icons for UI enhancement -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- NEW: Custom Styles for Statuses and Modal -->
    <style>
        /* Highlight for the active checked-in patient */
        .checked-in-row {
            background-color: #f3e8ff; /* A light purple */
        }
        /* Status Badge Colors */
        .status-scheduled { background-color: #dbeafe; color: #1d4ed8; }
        .status-confirmed { background-color: #ccfbf1; color: #0f766e; }
        .status-checked-in { background-color: #f3e8ff; color: #7e22ce; }
        .status-completed { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        .status-no-show { background-color: #e5e7eb; color: #374151; }

        /* Custom Modal */
        .modal-backdrop {
            transition: opacity 0.2s ease-in-out;
        }
    </style>

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4">
                            <i data-lucide="menu" class="w-6 h-6"></i>
                        </button>
                        <i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Today's Visits
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Appointments scheduled for <?php echo date("F j, Y"); ?>.</p>
                </div>
                <?php if ($can_schedule): ?>
                    <a href="add_appointment.php" data-tab-title="New Appointment" data-tab-icon="calendar-plus" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center transition transform hover:scale-105 shadow-md">
                        <i data-lucide="calendar-plus" class="w-5 h-5 mr-2"></i>
                        Add New Appointment
                    </a>
                <?php endif; ?>
            </header>
            <!-- END: UPDATED HEADER -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <!-- Page message container -->
                <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>
                <div id="appointments-container" class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Visit Progress Dashboard -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-indigo-50 p-4 rounded-lg border border-indigo-100 flex flex-col items-center justify-center shadow-sm">
                            <span class="text-2xl font-bold text-indigo-700" id="stat-total">0</span>
                            <span class="text-xs font-medium text-indigo-500 uppercase tracking-wider">Total Visits</span>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg border border-green-100 flex flex-col items-center justify-center shadow-sm">
                            <span class="text-2xl font-bold text-green-700" id="stat-completed">0</span>
                            <span class="text-xs font-medium text-green-500 uppercase tracking-wider">Completed</span>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg border border-purple-100 flex flex-col items-center justify-center shadow-sm">
                            <span class="text-2xl font-bold text-purple-700" id="stat-checkedin">0</span>
                            <span class="text-xs font-medium text-purple-500 uppercase tracking-wider">In Progress</span>
                        </div>
                        <div class="bg-blue-50 p-4 rounded-lg border border-blue-100 flex flex-col items-center justify-center shadow-sm">
                            <span class="text-2xl font-bold text-blue-700" id="stat-scheduled">0</span>
                            <span class="text-xs font-medium text-blue-500 uppercase tracking-wider">Pending</span>
                        </div>
                    </div>

                    <!-- Search & Filters Toolbar -->
                    <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4 border-b pb-4">
                        <!-- Search -->
                        <div class="relative w-full md:w-1/3">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                <i data-lucide="search" class="w-5 h-5 text-gray-400"></i>
                            </span>
                            <input type="text" id="searchInput" placeholder="Search patient name or ID..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 transition">
                        </div>
                        
                        <!-- Filters -->
                        <div class="flex flex-wrap gap-2" id="filterContainer">
                            <button class="filter-btn px-3 py-1.5 rounded-full text-sm font-medium bg-indigo-600 text-white shadow-sm transition transform hover:scale-105" data-filter="All">All</button>
                            <button class="filter-btn px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition" data-filter="Scheduled">Scheduled</button>
                            <button class="filter-btn px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition" data-filter="Checked-in">Checked In</button>
                            <button class="filter-btn px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition" data-filter="Completed">Completed</button>
                            <button class="filter-btn px-3 py-1.5 rounded-full text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition" data-filter="Cancelled">Cancelled</button>
                        </div>
                    </div>

                    <!-- Table Wrapper -->
                    <div id="appointments-table-wrapper">
                        <!-- Loading state -->
                        <div id="loading" class="text-center">
                            <div class="spinner"></div>
                            <p class="mt-2 text-gray-600">Loading appointments...</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- NEW: Confirmation Modal -->
    <div id="statusConfirmationModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-sm w-full">
            <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Confirm Status Change</h3>
            <p class="mt-2 text-sm text-gray-600" id="modalMessage">Are you sure you want to mark this appointment as 'Cancelled'?</p>
            <div class="mt-6 flex justify-end space-x-3">
                <button id="cancelStatusChangeBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                <button id="confirmStatusChangeBtn" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 font-semibold">Confirm</button>
            </div>
        </div>
    </div>

    <!-- NEW: Patient Preview Modal -->
    <div id="patientPreviewModal" class="modal-backdrop fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full relative">
            <button onclick="closePatientModal()" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <span class="text-2xl font-bold text-indigo-600" id="preview-initials">JD</span>
                </div>
                <h3 class="text-xl font-bold text-gray-900" id="preview-name">John Doe</h3>
                <p class="text-sm text-gray-500" id="preview-id">ID: EC0123</p>
            </div>
            
            <div class="space-y-4">
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <i data-lucide="phone" class="w-5 h-5 text-gray-400 mr-3"></i>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold">Contact Number</p>
                        <p class="text-gray-800 font-medium" id="preview-phone">N/A</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <i data-lucide="calendar" class="w-5 h-5 text-gray-400 mr-3"></i>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold">Date of Birth</p>
                        <p class="text-gray-800 font-medium" id="preview-dob">N/A</p>
                    </div>
                </div>
                <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                    <i data-lucide="map-pin" class="w-5 h-5 text-gray-400 mr-3"></i>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-semibold">Address</p>
                        <p class="text-gray-800 font-medium" id="preview-address">N/A</p>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <a href="#" id="preview-profile-link" class="block w-full bg-indigo-600 text-white text-center py-2 rounded-lg font-semibold hover:bg-indigo-700 transition">
                    View Full Profile
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons(); // Initialize icons
            const tableContainer = document.getElementById('appointments-table-wrapper');
            const loadingDiv = document.getElementById('loading');
            const userRole = '<?php echo $_SESSION['ec_role']; ?>';
            const pageMessage = document.getElementById('page-message');

            // Search & Filter Elements
            const searchInput = document.getElementById('searchInput');
            const filterButtons = document.querySelectorAll('.filter-btn');
            let allAppointments = [];
            let currentFilter = 'All';
            let currentSearch = '';

            // NEW: Modal elements
            const statusModal = document.getElementById('statusConfirmationModal');
            const modalMessage = document.getElementById('modalMessage');
            const cancelStatusBtn = document.getElementById('cancelStatusChangeBtn');
            const confirmStatusBtn = document.getElementById('confirmStatusChangeBtn');
            let statusChangeData = {
                id: null,
                status: '',
                selectElement: null
            };

            function showPageMessage(message, type) {
                pageMessage.textContent = message;
                pageMessage.className = 'p-3 mb-4 rounded-md';
                if (type === 'error') {
                    pageMessage.classList.add('bg-red-100', 'text-red-800');
                } else {
                    pageMessage.classList.add('bg-green-100', 'text-green-800');
                }
                pageMessage.classList.remove('hidden');
                setTimeout(() => pageMessage.classList.add('hidden'), 4000);
            }

            async function fetchAppointments() {
                try {
                    const response = await fetch('api/get_appointments.php');
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to fetch data.');
                    }
                    const appointments = await response.json();
                    allAppointments = appointments; // Store globally
                    updateDashboardStats(allAppointments); // Update stats
                    applyFilters(); // Render with current filters
                } catch (error) {
                    loadingDiv.innerHTML = `<p class="text-red-600 font-semibold">Error: ${error.message}</p>`;
                }
            }

            function updateDashboardStats(appointments) {
                const total = appointments.length;
                const completed = appointments.filter(a => a.status.toLowerCase() === 'completed').length;
                const checkedIn = appointments.filter(a => a.status.toLowerCase() === 'checked-in').length;
                const scheduled = appointments.filter(a => a.status.toLowerCase() === 'scheduled' || a.status.toLowerCase() === 'confirmed').length;

                document.getElementById('stat-total').textContent = total;
                document.getElementById('stat-completed').textContent = completed;
                document.getElementById('stat-checkedin').textContent = checkedIn;
                document.getElementById('stat-scheduled').textContent = scheduled;
            }

            function applyFilters() {
                let filtered = allAppointments;

                // 1. Filter by Status
                if (currentFilter !== 'All') {
                    filtered = filtered.filter(appt => appt.status === currentFilter);
                }

                // 2. Filter by Search (Name or ID)
                if (currentSearch) {
                    const term = currentSearch.toLowerCase();
                    filtered = filtered.filter(appt => 
                        (appt.first_name + ' ' + appt.last_name).toLowerCase().includes(term) ||
                        appt.patient_code.toLowerCase().includes(term)
                    );
                }

                renderAppointments(filtered);
            }

            // Event Listeners for Search & Filter
            searchInput.addEventListener('input', (e) => {
                currentSearch = e.target.value.trim();
                applyFilters();
            });

            filterButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Update UI
                    filterButtons.forEach(b => {
                        b.classList.remove('bg-indigo-600', 'text-white', 'shadow-sm', 'transform', 'scale-105');
                        b.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    btn.classList.remove('bg-gray-100', 'text-gray-600');
                    btn.classList.add('bg-indigo-600', 'text-white', 'shadow-sm', 'transform', 'scale-105');

                    // Update Logic
                    currentFilter = btn.getAttribute('data-filter');
                    applyFilters();
                });
            });

            // Helper to calculate wait time
            function getWaitTime(checkInTimeStr) {
                if (!checkInTimeStr) return '';
                // Parse MySQL datetime (YYYY-MM-DD HH:MM:SS)
                // Note: Safari/iOS might need 'T' separator, but Chrome/FF are usually fine.
                // Safer to replace space with T
                const checkIn = new Date(checkInTimeStr.replace(' ', 'T'));
                const now = new Date();
                const diffMs = now - checkIn;
                const diffMins = Math.floor(diffMs / 60000);
                
                if (diffMins < 0) return '0m'; 
                
                const hours = Math.floor(diffMins / 60);
                const mins = diffMins % 60;
                
                if (hours > 0) return `${hours}h ${mins}m`;
                return `${mins}m`;
            }

            function renderAppointments(appointments) {
                // Clear loading if present
                if (document.getElementById('loading')) {
                    document.getElementById('loading').remove();
                }

                if (appointments.length === 0) {
                    tableContainer.innerHTML = '<div class="flex flex-col items-center justify-center py-12 text-gray-500"><i data-lucide="calendar-off" class="w-12 h-12 mb-3 text-gray-300"></i><p>No appointments found matching your criteria.</p></div>';
                    return;
                }

                // --- Desktop Table View ---
                const tableRows = appointments.map(appt => {
                    const appointmentTime = new Date(appt.appointment_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    // Status Logic
                    let statusClass = 'status-scheduled';
                    let rowClass = '';
                    const cleanStatus = appt.status.toLowerCase().replace('-', '');
                    if (cleanStatus === 'confirmed') statusClass = 'status-confirmed';
                    else if (cleanStatus === 'checkedin') {
                        statusClass = 'status-checked-in';
                        rowClass = 'checked-in-row';
                    }
                    else if (cleanStatus === 'completed') statusClass = 'status-completed';
                    else if (cleanStatus === 'cancelled') statusClass = 'status-cancelled';
                    else if (cleanStatus === 'noshow') statusClass = 'status-no-show';

                    // Wait Time
                    let waitTimeHtml = '';
                    if (cleanStatus === 'checkedin' && appt.check_in_time) {
                        const waitTime = getWaitTime(appt.check_in_time);
                        waitTimeHtml = `<div class="text-xs text-purple-700 font-bold mt-1 flex items-center justify-center"><i data-lucide="clock" class="w-3 h-3 mr-1"></i>Waiting: ${waitTime}</div>`;
                    }

                    const isSigned = appt.is_signed == 1;
                    const isDropdownDisabled = userRole === 'facility' || isSigned;
                    const disabledAttr = isDropdownDisabled ? 'disabled' : '';
                    const lockIcon = isSigned ? '<i data-lucide="lock" class="w-4 h-4 text-gray-400 ml-2" title="Visit Signed & Locked"></i>' : '';

                    const statuses = ['Scheduled', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'No-show'];
                    let statusDropdown = `
                    <div class="flex items-center">
                        <select data-appointment-id="${appt.appointment_id}" class="status-select form-input bg-white text-sm p-1 w-32 mt-2 md:mt-0 md:ml-2 border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 ${isDropdownDisabled ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''}" ${disabledAttr}>
                            ${statuses.map(s => `<option value="${s}" ${s === appt.status ? 'selected' : ''}>${s}</option>`).join('')}
                        </select>
                        ${lockIcon}
                    </div>`;

                    const statusLower = appt.status.toLowerCase();
                    const shouldHideStartBtn = ['cancelled', 'no-show'].includes(statusLower);
                    const isCompleted = statusLower === 'completed';
                    
                    let startVisitBtnHtml = '';
                    if (!shouldHideStartBtn) {
                        const btnClasses = !isCompleted
                            ? 'bg-green-600 hover:bg-green-700 text-white shadow-sm'
                            : 'bg-gray-300 text-gray-500 cursor-not-allowed pointer-events-none';
                        const btnAttrs = !isCompleted ? '' : 'tabindex="-1" aria-disabled="true"';
                        
                        startVisitBtnHtml = `
                            <button onclick="openVisitModeModal(${appt.patient_id}, ${appt.appointment_id}, ${appt.user_id})"
                               class="${btnClasses} font-bold py-1.5 px-3 rounded-md text-sm transition text-center whitespace-nowrap" ${btnAttrs}>
                               Start Visit
                            </button>`;
                    }

                    let actionButton = '';
                    if (userRole === 'admin' || userRole === 'clinician') {
                        actionButton = `
                        <div class="flex flex-col md:flex-row md:items-center gap-2">
                            ${startVisitBtnHtml}
                            ${statusDropdown}
                        </div>`;
                    } else {
                        actionButton = `
                        <div class="flex flex-col md:flex-row md:items-center">
                            <a href="patient_profile.php?id=${appt.patient_id}" class="text-blue-600 hover:text-blue-800 font-semibold text-sm">View Profile</a>
                            ${statusDropdown}
                        </div>`;
                    }

                    return `
                    <tr class="table-row ${rowClass} hover:bg-gray-50 transition-colors">
                        <td class="table-cell font-mono text-sm text-gray-500">${appt.patient_code}</td>
                        <td class="table-cell">
                            <button onclick="openPatientModal(${appt.patient_id})" class="text-gray-900 font-bold hover:text-indigo-600 hover:underline focus:outline-none text-left text-base">
                                ${appt.first_name} ${appt.last_name}
                            </button>
                        </td>
                        <td class="table-cell text-gray-600 font-medium">${appointmentTime}</td>
                        <td class="table-cell text-sm text-gray-600">${appt.clinician_name || 'N/A'}</td>
                        <td class="table-cell text-center">
                            <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                ${appt.status}
                            </span>
                            ${waitTimeHtml}
                        </td>
                        <td class="table-cell">
                            ${actionButton}
                        </td>
                    </tr>`;
                }).join('');

                // --- Mobile Card View ---
                const mobileCards = appointments.map(appt => {
                    const appointmentTime = new Date(appt.appointment_date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    
                    // Status Logic (Same as desktop)
                    let statusClass = 'bg-blue-100 text-blue-800';
                    const cleanStatus = appt.status.toLowerCase().replace('-', '');
                    if (cleanStatus === 'confirmed') statusClass = 'bg-teal-100 text-teal-800';
                    else if (cleanStatus === 'checkedin') statusClass = 'bg-purple-100 text-purple-800';
                    else if (cleanStatus === 'completed') statusClass = 'bg-green-100 text-green-800';
                    else if (cleanStatus === 'cancelled') statusClass = 'bg-red-100 text-red-800';
                    else if (cleanStatus === 'noshow') statusClass = 'bg-gray-200 text-gray-800';

                    const isSigned = appt.is_signed == 1;
                    const isDropdownDisabled = userRole === 'facility' || isSigned;
                    const disabledAttr = isDropdownDisabled ? 'disabled' : '';
                    
                    const statuses = ['Scheduled', 'Confirmed', 'Checked-in', 'Completed', 'Cancelled', 'No-show'];
                    
                    // Mobile Status Select
                    let statusSelect = `
                        <select data-appointment-id="${appt.appointment_id}" class="status-select form-select block w-full mt-1 text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 ${isDropdownDisabled ? 'bg-gray-100 text-gray-500' : ''}" ${disabledAttr}>
                            ${statuses.map(s => `<option value="${s}" ${s === appt.status ? 'selected' : ''}>${s}</option>`).join('')}
                        </select>
                    `;

                    const statusLower = appt.status.toLowerCase();
                    const shouldHideStartBtn = ['cancelled', 'no-show'].includes(statusLower);
                    const isCompleted = statusLower === 'completed';
                    
                    let startBtn = '';
                    if (!shouldHideStartBtn) {
                        const btnClasses = !isCompleted
                            ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                            : 'bg-gray-100 text-gray-400 cursor-not-allowed';
                        
                        startBtn = `
                            <button onclick="openVisitModeModal(${appt.patient_id}, ${appt.appointment_id}, ${appt.user_id})" 
                                class="flex-1 ${btnClasses} font-semibold py-2 px-4 rounded-lg text-sm shadow-sm flex items-center justify-center transition-colors" ${isCompleted ? 'disabled' : ''}>
                                <i data-lucide="play-circle" class="w-4 h-4 mr-2"></i> Start Visit
                            </button>
                        `;
                    }

                    return `
                    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 mb-3">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center">
                                <div class="bg-indigo-50 p-2 rounded-lg mr-3">
                                    <i data-lucide="clock" class="w-5 h-5 text-indigo-600"></i>
                                </div>
                                <div>
                                    <p class="text-lg font-bold text-gray-900 leading-tight">${appointmentTime}</p>
                                    <p class="text-xs text-gray-500 font-mono">${appt.patient_code}</p>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase tracking-wide ${statusClass}">
                                ${appt.status}
                            </span>
                        </div>
                        
                        <div class="mb-4">
                            <button onclick="openPatientModal(${appt.patient_id})" class="text-lg font-bold text-gray-900 hover:text-indigo-600 text-left w-full flex items-center">
                                ${appt.first_name} ${appt.last_name}
                                <i data-lucide="chevron-right" class="w-4 h-4 ml-1 text-gray-400"></i>
                            </button>
                            <p class="text-sm text-gray-500 mt-1 flex items-center">
                                <i data-lucide="user-md" class="w-3 h-3 mr-1"></i> ${appt.clinician_name || 'Unassigned'}
                            </p>
                        </div>

                        <div class="flex flex-col gap-3">
                            ${startBtn}
                            <div class="relative">
                                ${statusSelect}
                            </div>
                        </div>
                    </div>
                    `;
                }).join('');

                tableContainer.innerHTML = `
                    <!-- Desktop Table -->
                    <div class="hidden md:block overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clinician</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                ${tableRows}
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-4">
                        ${mobileCards}
                    </div>
                `;
                
                // Re-initialize icons for new content
                lucide.createIcons();
            }

            // NEW: Refactored status update logic
            async function updateStatus(appointmentId, newStatus, selectElement) {
                if (selectElement) {
                    selectElement.disabled = true; // Disable dropdown during save
                }

                try {
                    const response = await fetch('api/update_appointment_status.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            appointment_id: appointmentId,
                            status: newStatus
                        })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);

                    showPageMessage(`Appointment ${appointmentId} status updated to ${newStatus}.`, 'success');
                    fetchAppointments(); // Refresh the entire list to show new colors/disabled states

                } catch (error) {
                    showPageMessage(`Error: ${error.message}`, 'error');
                    if (selectElement) {
                        selectElement.disabled = false; // Re-enable on failure
                    }
                }
            }

            // NEW: Event listener for status changes with confirmation step
            // Note: We attach to the main container because tableContainer is re-rendered
            document.getElementById('appointments-container').addEventListener('change', async function(e) {
                if (e.target.classList.contains('status-select')) {
                    const select = e.target;
                    const appointmentId = select.dataset.appointmentId;
                    const newStatus = select.value;

                    if (newStatus === 'Cancelled' || newStatus === 'No-show') {
                        // Show confirmation modal
                        statusChangeData.id = appointmentId;
                        statusChangeData.status = newStatus;
                        statusChangeData.selectElement = select;
                        modalMessage.textContent = `Are you sure you want to mark this appointment as '${newStatus}'?`;
                        statusModal.classList.remove('hidden');
                        statusModal.classList.add('flex');

                        // Revert dropdown temporarily
                        const originalStatus = [...select.options].find(opt => opt.selected && opt.value !== newStatus)?.value || 'Scheduled';
                        const appointmentRow = select.closest('tr');
                        const originalStatusFromBadge = appointmentRow.querySelector('span[class*="status-"]').textContent.trim();
                        select.value = originalStatusFromBadge;

                    } else {
                        // Update immediately
                        updateStatus(appointmentId, newStatus, select);
                    }
                }
            });

            // NEW: Modal button listeners
            cancelStatusBtn.addEventListener('click', () => {
                statusModal.classList.add('hidden');
                statusModal.classList.remove('flex');
                // Re-enable the select element if it exists
                if(statusChangeData.selectElement) {
                    statusChangeData.selectElement.disabled = false;
                }
            });

            confirmStatusBtn.addEventListener('click', () => {
                const { id, status, selectElement } = statusChangeData;
                if (id && status) {
                    updateStatus(id, status, selectElement);
                }
                statusModal.classList.add('hidden');
                statusModal.classList.remove('flex');
            });

            // NEW: Patient Preview Modal Logic
            window.openPatientModal = function(patientId) {
                const patient = allAppointments.find(p => p.patient_id == patientId);
                if (!patient) return;

                // Populate Modal Data
                document.getElementById('preview-initials').textContent = 
                    (patient.first_name[0] + patient.last_name[0]).toUpperCase();
                document.getElementById('preview-name').textContent = `${patient.first_name} ${patient.last_name}`;
                document.getElementById('preview-id').textContent = `ID: ${patient.patient_code}`;
                
                document.getElementById('preview-phone').textContent = patient.contact_number || 'N/A';
                
                // Format DOB
                let dobDisplay = 'N/A';
                if (patient.date_of_birth) {
                    const dobDate = new Date(patient.date_of_birth);
                    dobDisplay = dobDate.toLocaleDateString();
                    // Calculate Age
                    const ageDifMs = Date.now() - dobDate.getTime();
                    const ageDate = new Date(ageDifMs); 
                    const age = Math.abs(ageDate.getUTCFullYear() - 1970);
                    dobDisplay += ` (${age} yrs)`;
                }
                document.getElementById('preview-dob').textContent = dobDisplay;
                
                document.getElementById('preview-address').textContent = patient.address || 'N/A';
                document.getElementById('preview-profile-link').href = `patient_profile.php?id=${patient.patient_id}`;

                // Show Modal
                const modal = document.getElementById('patientPreviewModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            };

            window.closePatientModal = function() {
                const modal = document.getElementById('patientPreviewModal');
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            };

            // Close modal when clicking outside
            document.getElementById('patientPreviewModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    window.closePatientModal();
                }
            });

            fetchAppointments();
        });
    </script>

<?php
require_once 'templates/visit_mode_modal.php';
require_once 'templates/footer.php';
?>