<?php
// Filename: view_patients.php
// Description: Main dashboard for viewing, searching, filtering, and registering patients.
// Includes Status Update Modal and New Patient Registration Modal.

session_start();
require_once 'db_connect.php';

// Redirect if user is not logged in
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure output buffering is started
if (ob_get_level() === 0) ob_start();

// Include header template
require_once 'templates/header.php';

// Determine if the current user is a Clinician or Admin (Clinicians typically only see their assigned patients)
$user_role = $_SESSION['ec_role'];
$user_id = $_SESSION['ec_user_id'];
// Roles that are permitted to view and filter ALL patients AND create new ones
$is_admin_or_scheduler = in_array($user_role, ['admin', 'scheduler', 'facility']);
?>

    <div class="flex h-screen bg-gray-100 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="users" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Patient Directory
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Manage, search, and view all registered patient records.</p>
                </div>

                <!-- Button to open Add Patient Modal (HIDDEN FOR CLINICIANS) -->
                <?php if ($is_admin_or_scheduler): ?>
                    <button id="openAddPatientModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center transition transform hover:scale-105 shadow-md">
                        <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                        Register New Patient
                    </button>
                <?php endif; ?>

            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-8">

                <div class="bg-white p-6 rounded-xl shadow-xl border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6 border-b pb-3 flex items-center">
                        <i data-lucide="stethoscope" class="w-5 h-5 mr-2 text-blue-500"></i>
                        All Registered Patients
                    </h3>

                    <!-- Patient Search and Filter -->
                    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="text" id="patientSearchInput" placeholder="Search by Name, Code, or DOB..." class="col-span-1 md:col-span-2 px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition">

                        <!-- Filter by Clinician (Only visible to Admin/Scheduler/Facility roles) -->
                        <?php if ($is_admin_or_scheduler): ?>
                            <select id="clinicianFilter" class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition bg-white">
                                <option value="all">Filter by Clinician (All)</option>
                                <!-- Clinicians populated by JavaScript -->
                            </select>
                        <?php endif; ?>

                        <!-- Filter by Status -->
                        <select id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition bg-white">
                            <option value="all" selected>All Statuses</option>
                            <option value="on_going">On Going</option>
                            <option value="done">Done</option>
                            <option value="new">New</option>
                            <option value="discharged">Discharged</option>
                        </select>
                    </div>

                    <!-- STATUS LEGEND -->
                    <div class="text-sm text-gray-600 mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="font-semibold mb-2">Status Key:</p>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <span class="inline-flex items-center">
                            <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-xs font-medium mr-2">ON GOING</span> - Patient is under active treatment.
                        </span>
                            <span class="inline-flex items-center">
                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-xs font-medium mr-2">DONE</span> - Treatment completed/wound healed.
                        </span>
                            <span class="inline-flex items-center">
                            <span class="px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-medium mr-2">NEW</span> - Patient is awaiting first appointment/assessment.
                        </span>
                            <span class="inline-flex items-center">
                            <span class="px-2 py-0.5 rounded-full bg-gray-300 text-gray-800 text-xs font-medium mr-2">DISCHARGED</span> - Patient has been formally discharged.
                        </span>
                        </div>
                    </div>
                    <!-- END STATUS LEGEND -->

                    <!-- Patient Table Container -->
                    <div class="overflow-x-auto rounded-lg shadow-inner">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/5">Patient Name (Code)</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/6">DOB</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/5">Primary Clinician</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/5">Facility</th>
                                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/12">Status</th>
                                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider w-1/12">Actions</th>
                            </tr>
                            </thead>
                            <tbody id="patientTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows populated by JavaScript -->
                            <tr><td colspan="6" class="text-center p-6 text-gray-500">Loading patients...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (Placeholder) -->
                    <div id="paginationControls" class="mt-4 flex justify-between items-center">
                        <button id="prevPageBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg disabled:opacity-50" disabled>Previous</button>
                        <span id="pageInfo" class="text-sm text-gray-600">Page 1 of 1</span>
                        <button id="nextPageBtn" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg disabled:opacity-50" disabled>Next</button>
                    </div>

                    <!-- Loading/Error Messages -->
                    <div id="patientMessage" class="text-center mt-4 text-sm text-gray-500"></div>
                </div>

            </main>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. PATIENT STATUS CHANGE MODAL -->
    <!-- ========================================================================= -->
    <div id="statusModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-sm m-4 transform transition-all">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h4 class="text-2xl font-bold text-gray-900">Update Patient Status</h4>
                <button id="closeStatusModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="statusUpdateForm">
                <input type="hidden" id="statusPatientId">
                <p id="statusPatientName" class="text-gray-700 mb-4 font-semibold"></p>
                <div id="statusMessage" class="mb-4 hidden p-3 rounded-lg text-sm"></div>

                <div>
                    <label for="newStatus" class="block text-sm font-medium text-gray-700 mb-1">Select New Status</label>
                    <!-- Valid statuses: on_going, done, new, discharged -->
                    <select name="new_status" id="newStatus" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="on_going">On Going</option>
                        <option value="done">Done</option>
                        <option value="new">New</option>
                        <option value="discharged">Discharged</option>
                    </select>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelStatusBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" id="updateStatusBtn" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                        <i data-lucide="check-circle" class="w-4 h-4 mr-2 inline-block"></i>
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- END PATIENT STATUS CHANGE MODAL -->


    <!-- ========================================================================= -->
    <!-- 2. ADD NEW PATIENT MODAL (Registration Form - Streamlined & Scrollable) -->
    <!-- ========================================================================= -->
    <div id="addPatientModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
        <!-- MODAL CONTENT CONTAINER -->
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-3xl mx-2 sm:mx-auto transform transition-all max-h-[90vh] overflow-y-auto">
            <!-- HEADER (Sticky inside the scrollable container) -->
            <div class="flex justify-between items-center border-b pb-3 mb-4 sticky top-0 bg-white z-20">
                <h4 class="text-2xl font-bold text-gray-900">Register New Patient</h4>
                <button id="closeAddPatientModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <!-- MAIN SCROLLABLE BODY -->
            <div class="relative overflow-y-auto max-h-[calc(90vh-120px)]">
                <div id="addPatientMessage" class="hidden p-3 mb-4 rounded-md sticky top-0 z-10"></div>
                <form id="addPatientForm" class="space-y-6">

                    <!-- SECTION 1. Patient Demographics (NON-COLLAPSIBLE) -->
                    <div class="bg-gray-50 rounded-lg shadow-inner p-4">
                        <h4 class="font-semibold text-lg text-gray-800 mb-4 pb-2 border-b">1. Patient Demographics & Contact</h4>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" name="first_name" id="first_name" required class="form-input w-full">
                                </div>
                                <div>
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" name="last_name" id="last_name" required class="form-input w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" required class="form-input w-full">
                                </div>
                                <div>
                                    <label for="gender" class="form-label">Gender</label>
                                    <select name="gender" id="gender" required class="form-input bg-white w-full">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" id="contact_number" class="form-input w-full">
                                </div>
                            </div>
                            <div>
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-input w-full">
                            </div>
                            <div>
                                <label for="address" class="form-label">Address</label>
                                <textarea name="address" id="address" rows="2" class="form-input w-full"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- SECTION 2. Assignments (NON-COLLAPSIBLE) -->
                    <div class="bg-gray-50 rounded-lg shadow-inner p-4">
                        <h4 class="font-semibold text-lg text-gray-800 mb-4 pb-2 border-b">2. Assignments (Clinician & Facility)</h4>
                        <div class="space-y-4">
                            <div>
                                <label for="primary_user_id" class="form-label">Assign Primary Clinician</label>
                                <select name="primary_user_id" id="primary_user_id" class="form-input bg-white w-full">
                                    <option value="">Loading clinicians...</option>
                                </select>
                            </div>
                            <div>
                                <label for="facility_id" class="form-label">Assign Facility</label>
                                <select name="facility_id" id="facility_id" class="form-input bg-white w-full">
                                    <option value="">Loading facilities...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- HIDDEN FIELDS (Non-visible, data will still be submitted) -->
                    <div class="hidden">
                        <input type="hidden" name="allergies" id="allergies" value="">
                        <input type="hidden" name="past_medical_history" id="past_medical_history" value="">
                        <input type="hidden" name="emergency_contact_name" id="emergency_contact_name" value="">
                        <input type="hidden" name="emergency_contact_relationship" id="emergency_contact_relationship" value="">
                        <input type="hidden" name="emergency_contact_phone" id="emergency_contact_phone" value="">
                        <input type="hidden" name="insurance_provider" id="insurance_provider" value="">
                        <input type="hidden" name="insurance_policy_number" id="insurance_policy_number" value="">
                        <input type="hidden" name="insurance_group_number" id="insurance_group_number" value="">
                    </div>
                </form>
            </div>

            <!-- FOOTER BUTTONS (Sticky at the bottom) -->
            <div class="pt-4 flex justify-end space-x-3 sticky bottom-0 bg-white z-20 border-t mt-4">
                <button type="button" id="cancelAddPatientBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                <button type="submit" form="addPatientForm" id="savePatientBtn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    <i data-lucide="save" class="w-4 h-4 mr-2 inline-block"></i>
                    Save Patient Record
                </button>
            </div>
        </div>
    </div>
    <!-- END ADD NEW PATIENT MODAL -->


    <!-- Ensure Lucide icons are available -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <script>
        // Configuration
        const IS_ADMIN_OR_SCHEDULER = <?php echo json_encode($is_admin_or_scheduler); ?>;
        const CURRENT_USER_ID = <?php echo json_encode($user_id); ?>;
        const ITEMS_PER_PAGE = 10;

        // Global State
        let allPatients = [];
        let currentPage = 1;
        let totalPages = 1;
        let activeClinicians = [];
        let activeFacilities = {};

        // DOM Elements - Directory
        const patientTableBody = document.getElementById('patientTableBody');
        const patientMessage = document.getElementById('patientMessage');
        const searchInput = document.getElementById('patientSearchInput');
        const statusFilter = document.getElementById('statusFilter');
        const clinicianFilter = document.getElementById('clinicianFilter'); // May be null

        // Pagination elements
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageInfo = document.getElementById('pageInfo');

        // DOM Elements - Status Modal
        const statusModal = document.getElementById('statusModal');
        const statusUpdateForm = document.getElementById('statusUpdateForm');
        const statusPatientIdInput = document.getElementById('statusPatientId');
        const statusPatientNameP = document.getElementById('statusPatientName');
        const statusNewStatusSelect = document.getElementById('newStatus');
        const statusMessageDiv = document.getElementById('statusMessage');

        // DOM Elements - Add Patient Modal
        const addPatientModal = document.getElementById('addPatientModal');
        const addPatientForm = document.getElementById('addPatientForm');
        const addPatientMessage = document.getElementById('addPatientMessage');
        const savePatientBtn = document.getElementById('savePatientBtn');
        // FIX: Safely get the open button, it might not exist for clinicians
        const openAddPatientModalBtn = document.getElementById('openAddPatientModalBtn');

        // Add Patient Form Inputs
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const dobInput = document.getElementById('date_of_birth');


        // -----------------------------------------------------------
        // UTILITY FUNCTIONS (Badges, Names, Modals)
        // -----------------------------------------------------------

        function getStatusBadge(status) {
            // Fix 1: Ensure status defaults to 'new' if null/empty, as per create_patient.php logic
            const effectiveStatus = status ? status.toLowerCase() : 'new';
            let classes = 'px-3 py-1 inline-flex text-base leading-5 font-semibold rounded-full';
            let displayText = effectiveStatus.toUpperCase().replace('_', ' ');

            switch (effectiveStatus) {
                case 'on_going':
                    classes += ' bg-green-100 text-green-800';
                    break;
                case 'discharged':
                case 'done':
                    classes += ' bg-red-100 text-red-800';
                    break;
                case 'new':
                    classes += ' bg-blue-100 text-blue-800';
                    break;
                default:
                    // This 'default' case handles any invalid status but should now rarely be hit
                    classes += ' bg-gray-300 text-gray-800';
                    displayText = 'UNKNOWN';
            }
            return `<span class="${classes}">${displayText}</span>`;
        }

        function getClinicianName(userId) {
            if (!userId) return '<span class="text-gray-400">Unassigned</span>';
            const clinician = activeClinicians.find(c => c.user_id == userId);
            return clinician ? clinician.full_name : '<span class="text-red-500">User Not Found</span>';
        }

        function getFacilityName(facilityId) {
            if (!facilityId) return '<span class="text-gray-400">N/A</span>';
            const facility = activeFacilities[facilityId];
            return facility ? facility.name : '<span class="text-red-500">Facility Not Found</span>';
        }

        // Status Modal helpers
        function showStatusModal(patientId, patientName, currentStatus) {
            statusPatientIdInput.value = patientId;
            statusPatientNameP.textContent = `Patient: ${patientName}`;
            const normalizedStatus = (currentStatus || 'on_going').toLowerCase();
            statusNewStatusSelect.value = normalizedStatus;
            statusMessageDiv.classList.add('hidden');
            statusModal.classList.remove('hidden');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function hideStatusModal() {
            statusModal.classList.add('hidden');
        }

        // Add Patient Modal helpers
        function showAddPatientModal() {
            addPatientForm.reset();
            addPatientMessage.classList.add('hidden');
            savePatientBtn.disabled = false;
            savePatientBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            addPatientModal.classList.remove('hidden');

            // Removed accordion logic, only need to ensure icons are drawn
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function hideAddPatientModal() {
            addPatientModal.classList.add('hidden');
        }

        function displayModalMessage(messageDiv, type, message) {
            messageDiv.textContent = message;
            messageDiv.classList.remove('hidden', 'bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800', 'bg-yellow-100', 'text-yellow-800');
            if (type === 'error') {
                messageDiv.classList.add('bg-red-100', 'text-red-800');
            } else if (type === 'success') {
                messageDiv.classList.add('bg-green-100', 'text-green-800');
            } else if (type === 'warning') {
                messageDiv.classList.add('bg-yellow-100', 'text-yellow-800');
            }
        }


        // -----------------------------------------------------------
        // DATA FETCHING & INITIAL POPULATION
        // -----------------------------------------------------------

        async function populateSelect(selectElement, url, placeholder, nameField, valueField) {
            if (!selectElement) return []; // Safety check if element doesn't exist

            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`Failed to fetch ${placeholder}`);
                const data = await response.json();

                selectElement.innerHTML = `<option value="">Select a ${placeholder}</option>`;
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item[valueField];
                    option.textContent = item[nameField];
                    selectElement.appendChild(option);
                });
                return data;
            } catch (error) {
                selectElement.innerHTML = `<option value="">Could not load ${placeholder}</option>`;
                return [];
            }
        }

        async function fetchInitialData() {
            try {
                // 1. Fetch Clinicians for Directory filter and Add Patient modal
                // FIX: Only try to populate dropdown if it exists (it's inside the modal)
                const clinicianSelect = document.getElementById('primary_user_id');
                const cliniciansData = await populateSelect(clinicianSelect, 'api/get_users.php', 'clinician', 'full_name', 'user_id');
                activeClinicians = cliniciansData;
                populateClinicianFilter(); // Populates directory filter

                // 2. Fetch Facilities for Directory filter and Add Patient modal
                const facilitySelect = document.getElementById('facility_id');
                const facilitiesData = await populateSelect(facilitySelect, 'api/get_facilities.php', 'facility', 'name', 'facility_id');
                facilitiesData.forEach(f => {
                    activeFacilities[f.facility_id] = f;
                });

                // 3. Fetch Patients
                await fetchPatients();

            } catch (error) {
                console.error('Error fetching initial data:', error);
                patientMessage.textContent = `Error loading initial data: ${error.message}`;
            }
        }

        /**
         * Fetches the full patient list. The API handles filtering based on user role.
         */
        async function fetchPatients() {
            patientTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-6 text-gray-500 animate-pulse">Fetching patient list...</td></tr>';
            patientMessage.textContent = '';

            try {
                const response = await fetch('api/get_patients.php');

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status} (${response.statusText})`);
                }

                const data = await response.json();

                if (data.success && data.patients) {
                    // Fix 2: Explicitly sanitize the status on arrival to prevent UNKNOWN errors
                    allPatients = data.patients.map(p => {
                        const statusLower = p.status ? p.status.toLowerCase() : 'new';
                        // If the database returns an old 'active' status, treat it as 'on_going' for display/filter
                        p.status = (statusLower === 'active' || statusLower === '') ? 'new' : statusLower;
                        return p;
                    });

                    applyFiltersAndRenderTable();
                } else {
                    patientTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-6 text-red-500">API Data Error: Received invalid data or ' + (data.message || 'database error') + '</td></tr>';
                    patientMessage.textContent = data.message || 'No patient records found.';
                }

            } catch (error) {
                console.error('Error fetching patients:', error);
                patientTableBody.innerHTML = `<tr><td colspan="6" class="text-center p-6 text-red-500">Connection Error: ${error.message}</td></tr>`;
                patientMessage.textContent = `Failed to connect or process data from API. Please check 'api/get_patients.php'.`;
            }
        }

        /**
         * Populates the Clinician filter dropdown.
         */
        function populateClinicianFilter() {
            if (!clinicianFilter) return;

            const fragment = document.createDocumentFragment();

            const allOption = document.createElement('option');
            allOption.value = 'all';
            allOption.textContent = 'Filter by Clinician (All)';
            fragment.appendChild(allOption);

            activeClinicians.forEach(clinician => {
                const option = document.createElement('option');
                option.value = clinician.user_id;
                option.textContent = clinician.full_name;
                fragment.appendChild(option);
            });

            clinicianFilter.innerHTML = '';
            clinicianFilter.appendChild(fragment);
        }


        // -----------------------------------------------------------
        // FILTERING, PAGINATION, AND RENDERING
        // -----------------------------------------------------------

        function applyFiltersAndRenderTable(page = 1) {
            const searchTerm = searchInput.value.toLowerCase();
            const statusVal = statusFilter.value;
            const clinicianVal = clinicianFilter ? clinicianFilter.value : null;

            const filteredPatients = allPatients.filter(patient => {
                const patientFullName = patient.full_name ? patient.full_name.toLowerCase() : '';
                const patientCode = patient.patient_code ? patient.patient_code.toLowerCase() : '';

                // Fix 3: Status is now pre-sanitized in fetchPatients, just use it
                let patientStatus = patient.status;

                // Search filter
                const matchesSearch = patientFullName.includes(searchTerm) ||
                    patientCode.includes(searchTerm) ||
                    (patient.date_of_birth && patient.date_of_birth.includes(searchTerm));

                // Status filter
                const matchesStatus = statusVal === 'all' || patientStatus === statusVal;

                // Clinician filter (RBAC - applies if not admin/scheduler or if filter is set)
                let matchesClinician = true;
                if (!IS_ADMIN_OR_SCHEDULER) {
                    // Clinician role: always filter by self
                    matchesClinician = patient.primary_user_id == CURRENT_USER_ID;
                } else if (clinicianFilter && clinicianVal && clinicianVal !== 'all') {
                    // Admin/Scheduler/Facility role with filter set
                    matchesClinician = patient.primary_user_id == clinicianVal;
                }
                // Note: Facility users see only their patients via the server-side RBAC (get_patients.php)

                return matchesSearch && matchesStatus && matchesClinician;
            });

            // Apply Pagination
            totalPages = Math.ceil(filteredPatients.length / ITEMS_PER_PAGE);
            currentPage = Math.min(Math.max(1, page), totalPages);

            const start = (currentPage - 1) * ITEMS_PER_PAGE;
            const end = start + ITEMS_PER_PAGE;
            const patientsOnPage = filteredPatients.slice(start, end);

            renderTable(patientsOnPage, filteredPatients.length);
            updatePaginationControls(filteredPatients.length);
        }

        function renderTable(patients, totalFilteredCount) {
            if (patients.length === 0) {
                patientTableBody.innerHTML = '<tr><td colspan="6" class="text-center p-6 text-gray-500">No patients match the current criteria.</td></tr>';
                patientMessage.textContent = `Showing 0 of ${allPatients.length} total records.`;
                return;
            }

            const rowsHtml = patients.map(patient => `
            <tr class="hover:bg-gray-50 transition duration-150" data-patient-id="${patient.patient_id}">
                <td class="px-6 py-4 whitespace-nowrap text-base font-medium text-blue-600 hover:text-blue-800">
                    <a href="patient_profile.php?id=${patient.patient_id}" title="View Patient Profile">
                        ${patient.last_name}, ${patient.first_name} <span class="text-gray-500 text-sm">(${patient.patient_code})</span>
                    </a>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-base text-gray-600">${patient.date_of_birth}</td>
                <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700">${getClinicianName(patient.primary_user_id)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-base text-gray-700">${getFacilityName(patient.facility_id)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${getStatusBadge(patient.status)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-base font-medium space-x-2">
                    <button type="button" title="Update Status" data-action="status"
                        data-patient-id="${patient.patient_id}" data-patient-name="${patient.full_name}" data-current-status="${patient.status}"
                        class="status-change-btn inline-flex items-center text-amber-600 hover:text-amber-800 p-1 rounded-full transition">
                        <i data-lucide="shuffle" class="w-5 h-5"></i>
                    </button>
                    <button type="button" title="View Chart" data-action="chart"
                        onclick="window.location.href='patient_emr.php?id=${patient.patient_id}'"
                        class="inline-flex items-center text-blue-600 hover:text-blue-800 p-1 rounded-full transition">
                        <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    </button>
                </td>
            </tr>
            `).join('');

            patientTableBody.innerHTML = rowsHtml;
            patientMessage.textContent = `Showing ${patients.length} patients on page ${currentPage}. Total filtered: ${totalFilteredCount}, Total records: ${allPatients.length}.`;

            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            attachActionListeners();
        }

        function updatePaginationControls(totalFilteredCount) {
            prevPageBtn.disabled = currentPage === 1;
            nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;

            if (totalFilteredCount === 0) {
                pageInfo.textContent = 'No pages available';
            } else {
                const startItem = (currentPage - 1) * ITEMS_PER_PAGE + 1;
                const endItem = Math.min(currentPage * ITEMS_PER_PAGE, totalFilteredCount);
                pageInfo.textContent = `Showing ${startItem}-${endItem} of ${totalFilteredCount}`;
            }
        }


        // -----------------------------------------------------------
        // EVENT LISTENERS & FORM SUBMISSIONS
        // -----------------------------------------------------------

        // Attach listeners for directory filters
        searchInput.addEventListener('input', () => applyFiltersAndRenderTable(1));
        statusFilter.addEventListener('change', () => applyFiltersAndRenderTable(1));
        if (clinicianFilter) {
            clinicianFilter.addEventListener('change', () => applyFiltersAndRenderTable(1));
        }

        // Pagination listeners
        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                applyFiltersAndRenderTable(currentPage - 1);
            }
        });

        nextPageBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                applyFiltersAndRenderTable(currentPage + 1);
            }
        });


        // Status Modal listeners
        document.getElementById('closeStatusModalBtn').addEventListener('click', hideStatusModal);
        document.getElementById('cancelStatusBtn').addEventListener('click', hideStatusModal);
        statusUpdateForm.addEventListener('submit', handleStatusUpdateSubmit);

        // Add Patient Modal listeners (FIX: Only attach if button exists)
        if (openAddPatientModalBtn) {
            openAddPatientModalBtn.addEventListener('click', showAddPatientModal);
        }
        document.getElementById('closeAddPatientModalBtn').addEventListener('click', hideAddPatientModal);
        document.getElementById('cancelAddPatientBtn').addEventListener('click', hideAddPatientModal);
        // Form submission is attached via `form="addPatientForm"` attribute on the save button now.
        addPatientForm.addEventListener('submit', handleAddPatientSubmit);

        // Removed Add Patient Accordion Logic

        function attachActionListeners() {
            // Status Change button listener
            document.querySelectorAll('.status-change-btn').forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    const patientId = button.dataset.patientId;
                    const patientName = button.dataset.patientName;
                    const currentStatus = button.dataset.currentStatus;
                    showStatusModal(patientId, patientName, currentStatus);
                });
            });
        }

        async function handleStatusUpdateSubmit(e) {
            e.preventDefault();
            const updateButton = document.getElementById('updateStatusBtn');
            updateButton.disabled = true;
            updateButton.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 inline-block animate-spin"></i> Updating...';
            lucide.createIcons();

            const patientId = statusPatientIdInput.value;
            const newStatus = statusNewStatusSelect.value;

            try {
                const response = await fetch('api/manage_patient_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ patient_id: patientId, status: newStatus })
                });
                const result = await response.json();

                if (result.success) {
                    displayModalMessage(statusMessageDiv, 'success', 'Status updated successfully! Refreshing list...');
                    // Optimistically update the patient in the local array
                    const patientIndex = allPatients.findIndex(p => p.patient_id == patientId);
                    if (patientIndex > -1) {
                        allPatients[patientIndex].status = newStatus;
                    }
                    setTimeout(() => {
                        hideStatusModal();
                        applyFiltersAndRenderTable(currentPage); // Re-render current page
                    }, 1500);
                } else {
                    displayModalMessage(statusMessageDiv, 'error', result.message || 'Failed to update status. Check API/DB logs.');
                }
            } catch (error) {
                console.error('API Error:', error);
                displayModalMessage(statusMessageDiv, 'error', 'A network error occurred while attempting to update the status.');
            } finally {
                updateButton.disabled = false;
                updateButton.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 mr-2 inline-block"></i> Update Status';
                lucide.createIcons();
            }
        }


        // --- New Patient Registration Logic ---

        // Real-time Duplicate Check
        async function checkForDuplicates() {
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const dob = dobInput.value;

            if (firstName && lastName && dob) {
                try {
                    const response = await fetch(`api/check_patient_duplicate.php?first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&date_of_birth=${encodeURIComponent(dob)}`);
                    const result = await response.json();

                    if (result.exists) {
                        displayModalMessage(addPatientMessage, 'warning', 'Warning: A patient with this name and date of birth already exists.');
                        savePatientBtn.disabled = true;
                        savePatientBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                    } else {
                        addPatientMessage.classList.add('hidden');
                        savePatientBtn.disabled = false;
                        savePatientBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    }
                } catch (error) {
                    console.error('Duplicate check failed:', error);
                    savePatientBtn.disabled = false;
                }
            } else {
                savePatientBtn.disabled = false;
                addPatientMessage.classList.add('hidden');
            }
        }

        // Attach duplicate check listener to key fields
        [firstNameInput, lastNameInput, dobInput].forEach(input => input.addEventListener('blur', checkForDuplicates));

        async function handleAddPatientSubmit(event) {
            event.preventDefault();
            savePatientBtn.disabled = true;
            savePatientBtn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 inline-block animate-spin"></i> Saving...';
            lucide.createIcons();

            // This grabs ALL fields in the form, including the hidden ones, which is correct.
            const patientData = Object.fromEntries(new FormData(addPatientForm).entries());

            try {
                const response = await fetch('api/create_patient.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(patientData)
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to save patient record.');
                }

                displayModalMessage(addPatientMessage, 'success', 'Patient record successfully created! Refreshing list...');

                // Refresh patient list in the background
                await fetchPatients();

                setTimeout(() => {
                    hideAddPatientModal();
                }, 1500);

            } catch (error) {
                displayModalMessage(addPatientMessage, 'error', `Error: ${error.message}`);
            } finally {
                savePatientBtn.disabled = false;
                savePatientBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2 inline-block"></i> Save Patient Record';
                lucide.createIcons();
            }
        }


        // -----------------------------------------------------------
        // INITIALIZATION
        // -----------------------------------------------------------

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            fetchInitialData();
        });
    </script>

<?php
// Include footer template
require_once 'templates/footer.php';
// Flush the output buffer and send content to the browser
ob_end_flush();
?>