<?php
// Filename: visit_medications.php
// UPDATED: Standardized with unified header, auto-narrative panel, and mobile FAB.
// Integrates logic from patient_medication.php for a full-featured visit step.
// Uses autosave_manager.js for its messaging system, but saving is MANUAL.

require_once 'templates/header.php';
require_once 'db_connect.php';

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// --- CHECK VISIT STATUS ---
require_once 'visit_status_check.php';

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// Define navigation links based on workflow: DIAGNOSIS (Step 4) -> MEDICATION (Step 5) -> PROCEDURES (Step 6) -> NOTES (Step 7)
$previous_step_url = "visit_diagnosis.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";
$next_step_url = "visit_procedure.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";
?>

    <style>
        /* Standardized Page Styles */
        .form-button { min-height: 48px; }

        /* Floating Alert Styles */
        #autosave-message-container {
            position: fixed;
            bottom: 6rem; /* Positioned above the FAB */
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 400px;
            z-index: 100;
            pointer-events: none;
        }
        #autosave-message {
            transition: opacity 0.3s ease-in-out;
            opacity: 0;
            pointer-events: none;
            text-align: center;
        }
        #autosave-message.visible {
            opacity: 1;
            pointer-events: auto;
        }

        /* Mobile FAB Styles */
        #mobile-fab-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            background-color: transparent;
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
            z-index: 50;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        @media (min-width: 768px) {
            #mobile-fab-container { display: none !important; }
        }
        .fab-nav-button {
            height: 56px !important;
            padding: 0.75rem 0.5rem !important;
        }

        /* Autosave Status Placeholder for Mobile UX consistency */
        #autosave-status {
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 48px;
            border-radius: 0.5rem;
            opacity: 0.9;
        }
        #autosave-status-mobile-container { width: 100%; }

        /* Desktop action row styling */
        @media (min-width: 1024px) {
            #meds-form-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                padding-top: 1rem;
            }
            /* FIX: Added styles for the desktop status container */
            #autosave-status-desktop-container {
                flex-grow: 1;
                max-width: 200px;
                min-width: 150px;
            }
        }
    </style>

    <!-- FLOATING MESSAGE CONTAINER (Standardized ID) -->
    <div id="autosave-message-container">
        <div id="autosave-message" class="p-3 my-3 rounded-lg text-sm text-center shadow-lg"></div>
    </div>

    <div class="flex h-screen bg-gray-100">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- *** UNIFIED, RESPONSIVE HEADER *** -->
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md flex-shrink-0">
                <div class="flex items-center">
                    <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div>
                        <h1 id="patient-name-header" class="text-2xl font-bold text-gray-800">Loading Patient...</h1>
                        <p id="patient-dob-subheader" class="text-sm text-gray-600">Step 5 of 6: Medication Management</p>
                    </div>
                </div>
            </header>

            <!-- *** STICKY VISIT SUBMENU *** -->
            <div class="sticky top-0 z-30">
                <?php require_once 'templates/visit_submenu.php'; ?>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6">
                <div class="flex flex-col lg:flex-row gap-6 h-full">

                    <!-- Left Column: Main Medication Content -->
                    <div class="w-full lg:w-2/3">
                        <div class="bg-white rounded-lg shadow-lg p-6">

                            <!-- Medication Form -->
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-800">Add/Update Medication</h3>
                                <button onclick="openOrderModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-semibold flex items-center text-sm transition shadow-sm">
                                    <i data-lucide="pill" class="w-4 h-4 mr-2"></i>
                                    Order Medication
                                </button>
                            </div>
                            <div id="medication-form-message" class="hidden p-3 mb-4 rounded-md"></div>

                            <form id="medicationForm" class="space-y-4">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <!-- FIX: Added hidden appointment_id to pass API validation -->
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                                <input type="hidden" name="medication_id" id="medication_id" value="">

                                <div>
                                    <label for="medication_name_search" class="form-label">Medication Name</label>
                                    <!--
                                        *** FIX: Changed name="medication_name" to name="drug_name" ***
                                        This now matches the validation in `api/create_medication.php`
                                    -->
                                    <input type="text" id="medication_name_search" name="drug_name" class="form-input" placeholder="Type to search library..." autocomplete="off">
                                    <div id="medication-search-results" class="hidden relative z-10 w-full bg-white border border-gray-300 rounded-b-md shadow-lg max-h-60 overflow-y-auto">
                                        <!-- Search results will be populated here -->
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="dosage" class="form-label">Dosage (e.g., 500mg)</label>
                                        <input type="text" id="dosage" name="dosage" class="form-input">
                                    </div>
                                    <div>
                                        <label for="frequency" class="form-label">Frequency (e.g., Once daily)</label>
                                        <input type="text" id="frequency" name="frequency" class="form-input">
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="date" id="start_date" name="start_date" class="form-input">
                                    </div>
                                    <div>
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="date" id="end_date" name="end_date" class="form-input">
                                    </div>
                                </div>

                                <div>
                                    <label for="status" class="form-label">Status</label>
                                    <select id="status" name="status" class="form-input">
                                        <option value="Active">Active</option>
                                        <option value="Discontinued">Discontinued</option>
                                    </select>
                                </div>

                                <div class="text-right space-x-2 pt-2">
                                    <button type="button" id="clearMedFormBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Clear Form</button>
                                    <button type="submit" id="saveMedBtn" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold inline-flex items-center shadow-sm">
                                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                        Save Medication
                                    </button>
                                </div>
                            </form>

                            <!-- Medication History List -->
                            <div class="mt-8 pt-6 border-t border-gray-200">
                                <h3 class="text-xl font-semibold mb-4 text-gray-800">Current & Past Medications</h3>
                                <div id="medication-history-container" class="overflow-x-auto">
                                    <p class="text-center text-gray-500 py-8">Loading medication history...</p>
                                </div>
                            </div>

                            <!-- DESKTOP NAVIGATION BAR -->
                            <div id="meds-form-actions" class="hidden lg:flex justify-between gap-4 mt-6 border-t pt-4">
                                <a href="<?php echo $previous_step_url; ?>"
                                   class="h-12 bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md transition flex items-center justify-center text-sm min-w-[150px]">
                                    &larr; Prev: Diagnosis
                                </a>

                                <!-- FIX: Added standardized desktop status indicator -->
                                <div id="autosave-status-desktop-container" class="h-12 hidden lg:flex items-center justify-center text-sm font-bold">
                                    <div id="autosave-status-desktop" class="h-full w-full flex items-center justify-center text-sm font-bold bg-gray-300 text-gray-700 rounded-md transition">
                                        <i data-lucide="info" class="w-5 h-5 mr-1.5"></i>
                                        Manual Save Required
                                    </div>
                                </div>

                                <a href="<?php echo $next_step_url; ?>"
                                   class="h-12 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center justify-center text-sm min-w-[150px]">
                                    Next: Procedures &rarr;
                                </a>
                            </div>

                        </div>
                    </div>

                    <!-- Right Column: Auto-Narrative Panel (Integrated) -->
                    <div class="w-full lg:w-1/3">
                        <div class="bg-white rounded-lg shadow-lg p-4 lg:sticky top-0">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h2 class="text-xl font-bold text-gray-800 inline-flex items-center">
                                    <i data-lucide="file-text" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Auto-Narrative
                                </h2>
                                <button id="copyNarrativeBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-md text-sm flex items-center transition shadow-sm">
                                    <i data-lucide="clipboard-copy" class="w-4 h-4 mr-1.5"></i>
                                    Edit & Copy
                                </button>
                            </div>
                            <!-- Toggles (Standardized) -->
                            <div id="narrative-toggle-group" class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-700 mb-4 border-b pb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-hpi" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                                    <span class="ml-2">HPI</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-vitals" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                                    <span class="ml-2">Vitals</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-wound" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                                    <span class="ml-2">Wound</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-medication" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600" checked>
                                    <span class="ml-2">Medication</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-notes" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600">
                                    <span class="ml-2">Notes</span>
                                </label>
                            </div>
                            <!-- Narrative Output Area -->
                            <div id="auto-narrative-content" class="text-sm space-y-4" style="min-height: 200px;">
                                <p class="text-gray-500 text-center py-6">Load patient data to generate narrative...</p>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- FLOATING ACTION BUTTON (FAB) CONTAINER -->
    <div id="mobile-fab-container" class="md:hidden">
        <!-- Status Indicator: This page uses manual save, so we show a static message -->
        <div id="autosave-status-mobile-container" class="w-full">
            <div id="autosave-status" class="h-12 w-full rounded-md text-sm bg-gray-300 text-gray-700 flex items-center justify-center font-bold opacity-90">
                <i data-lucide="info" class="w-5 h-5 mr-1.5"></i> Manual Save Required
            </div>
        </div>

        <!-- MOBILE NAVIGATION ROW -->
        <div class="flex space-x-2 w-full">
            <a href="<?php echo $previous_step_url; ?>"
               class="flex-1 bg-gray-200 text-gray-800 font-bold py-2 px-1 rounded-md transition fab-nav-button flex items-center justify-center text-sm">
                &larr; Prev: Diagnosis
            </a>
            <a href="<?php echo $next_step_url; ?>"
               class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-1 rounded-md transition form-button fab-nav-button flex items-center justify-center text-sm">
                Next: Procedures &rarr;
            </a>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- Load Global Data Script -->
    <script>
        // Define global placeholders for data visibility to auto_narrative.js
        window.currentVitalsData = {};
        window.currentHpiData = {};
        window.globalWoundsData = [];
        window.currentMedicationsData = []; // NEW: For medication narrative
        window.quillEditors = {};

        // Pass PHP data to JS
        window.visitData = {
            patientId: <?php echo json_encode($patient_id, JSON_HEX_TAG); ?>,
            appointmentId: <?php echo json_encode($appointment_id, JSON_HEX_TAG); ?>,
            userId: <?php echo json_encode($user_id, JSON_HEX_TAG); ?>
        };
    </script>

    <!-- Load Auto-Narrative first (defines global functions like updateAutoNarrative) -->
    <script src="auto_narrative.js"></script>
    <!-- Load Autosave Manager (for init and messaging) -->
    <script type="module" src="autosave_manager.js"></script>

    <!-- Main Page Logic -->
    <script type="module">
        import { initAutosaveManager, showAutosaveMessage } from './autosave_manager.js';

        // --- DOM Element References ---
        let nameHeader, copyNarrativeBtn, autoNarrativeContent;
        let medicationForm, medsMessage, historyContainer, medSearchInput, medSearchResults, medIdInput, saveMedBtn;
        let medicationLibrary = []; // Cache for library search

        // --- GLOBAL VARIABLES (Accessible to all functions) ---
        const patientId = window.visitData.patientId;
        const appointmentId = window.visitData.appointmentId;

        /**
         * Shows a message inside the form area (for form-specific errors)
         */
        function showFormMessage(message, type) {
            medsMessage.innerHTML = message;
            medsMessage.classList.remove('hidden', 'bg-red-100', 'text-red-700', 'bg-green-100', 'text-green-700');
            if (type === 'error') {
                medsMessage.classList.add('bg-red-100', 'text-red-700', 'p-3', 'rounded-md');
            } else {
                medsMessage.classList.add('bg-green-100', 'text-green-700', 'p-3', 'rounded-md');
            }
            medsMessage.classList.remove('hidden');
            setTimeout(() => { medsMessage.classList.add('hidden'); }, 5000);
        }

        /**
         * Fetches all necessary patient data for display and narrative generation.
         */
        async function fetchInitialData() {
            try {
                // 1. Fetch Patient Details and Wounds
                const patientResponse = await fetch(`api/get_patient_details.php?id=${patientId}`);
                if (!patientResponse.ok) throw new Error('Failed to fetch patient details');
                const data = await patientResponse.json();
                nameHeader.textContent = `${data.details.first_name} ${data.details.last_name}`;
                window.globalWoundsData = data.wounds;

                // 2. Fetch Vitals
                const vitalsResponse = await fetch(`api/get_vitals.php?patient_id=${patientId}&appointment_id=${appointmentId}`);
                if(vitalsResponse.ok) {
                    const vitalsResult = await vitalsResponse.json();
                    if(vitalsResult) { window.currentVitalsData = vitalsResult; }
                }

                // 3. Fetch HPI data
                const hpiResponse = await fetch(`api/get_hpi_data.php?appointment_id=${appointmentId}`);
                if (hpiResponse.ok) {
                    const hpiResult = await hpiResponse.json();
                    if (hpiResult.success && hpiResult.data) { window.currentHpiData = hpiResult.data; }
                }

                // 4. Fetch Medications (This page's primary data)
                // This function also updates the narrative
                await fetchMedicationHistory();

            } catch (error) {
                nameHeader.textContent = 'Error Loading Patient';
                console.error("Initialization Error:", error);
                // Use the standardized floating message for critical load errors
                showAutosaveMessage(`Error loading patient data: ${error.message}`, 'error');
            }
        }

        /**
         * Fetches and renders the patient's medication list.
         */
        async function fetchMedicationHistory() {
            historyContainer.innerHTML = '<p class="text-center text-gray-500 py-8">Loading medication history...</p>';
            try {
                const response = await fetch(`api/get_medications.php?patient_id=${patientId}`);
                if (!response.ok) throw new Error('Failed to fetch medication list.');
                const medications = await response.json();

                window.currentMedicationsData = medications; // Update global data for narrative
                renderMedicationHistory(medications);

                // Update narrative after fetching
                if (typeof window.updateAutoNarrative === 'function') {
                    window.updateAutoNarrative();
                }

            } catch (error) {
                historyContainer.innerHTML = `<p class="text-red-500 py-8">Error loading medication history: ${error.message}</p>`;
            }
        }

        /**
         * Renders the medication list into two tables: Active and Past.
         */
        function renderMedicationHistory(medications) {
            if (!medications || medications.length === 0) {
                historyContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No medications on file for this patient.</p>';
                return;
            }

            const activeMeds = medications.filter(m => m.status === 'Active');
            const pastMeds = medications.filter(m => m.status !== 'Active' && m.status !== 'Archived');
            const archivedMeds = medications.filter(m => m.status === 'Archived');

            const createTableRows = (meds, isArchived = false) => {
                return meds.map(med => {
                    let statusClass = 'bg-gray-100 text-gray-800';
                    if (med.status === 'Active') statusClass = 'bg-green-100 text-green-800';
                    else if (med.status === 'Discontinued') statusClass = 'bg-red-100 text-red-700';
                    else if (med.status === 'Archived') statusClass = 'bg-gray-200 text-gray-500 italic';

                    const startDate = med.start_date ? new Date(med.start_date).toLocaleDateString() : '-';
                    const endDate = med.end_date ? new Date(med.end_date).toLocaleDateString() : '-';

                    // Disable edit for archived records
                    const actionButton = isArchived 
                        ? `<span class="text-xs text-gray-400 italic">History</span>`
                        : `<button class="text-blue-600 hover:text-blue-800 font-medium" data-id="${med.medication_id}">Edit</button>`;

                    return `
                        <tr class="border-b border-gray-200 hover:bg-gray-50 text-sm ${isArchived ? 'bg-gray-50 text-gray-500' : ''}">
                            <td class="px-4 py-3 font-medium">${med.drug_name || '-'}</td>
                            <td class="px-4 py-3">${med.dosage || '-'}</td>
                            <td class="px-4 py-3">${med.frequency || '-'}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">
                                <div>Start: ${startDate}</div>
                                <div>End: ${endDate}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold ${statusClass}">
                                    ${med.status}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                ${actionButton}
                            </td>
                        </tr>
                    `;
                }).join('');
            };

            const createTableHTML = (title, rows, isEmpty) => {
                if (isEmpty) {
                    return `
                        <div class="mb-8">
                            <h4 class="text-lg font-bold text-gray-700 mb-3 border-b pb-2">${title}</h4>
                            <p class="text-gray-500 italic text-sm">No ${title.toLowerCase()} found.</p>
                        </div>
                    `;
                }
                return `
                    <div class="mb-8">
                        <h4 class="text-lg font-bold text-gray-700 mb-3 border-b pb-2">${title}</h4>
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Medication</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dosage</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequency</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${rows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            };

            let html = '';
            html += createTableHTML('Active Medications', createTableRows(activeMeds), activeMeds.length === 0);
            html += createTableHTML('Past / Discontinued Medications', createTableRows(pastMeds), pastMeds.length === 0);
            
            // Only show archived section if there are items
            if (archivedMeds.length > 0) {
                html += createTableHTML('Archived History (Previous Versions)', createTableRows(archivedMeds, true), false);
            }

            historyContainer.innerHTML = html;

            // Add edit listeners
            historyContainer.querySelectorAll('button[data-id]').forEach(button => {
                button.addEventListener('click', () => {
                    const medId = button.dataset.id;
                    const med = medications.find(m => m.medication_id == medId);
                    if (med) {
                        medIdInput.value = med.medication_id;
                        medSearchInput.value = med.drug_name;
                        document.getElementById('dosage').value = med.dosage;
                        document.getElementById('frequency').value = med.frequency;
                        document.getElementById('status').value = med.status;
                        document.getElementById('start_date').value = med.start_date || '';
                        document.getElementById('end_date').value = med.end_date || '';
                        
                        saveMedBtn.textContent = 'Update Medication';
                        saveMedBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Update Medication';
                        if (typeof lucide !== 'undefined') { lucide.createIcons(); }
                        medSearchInput.focus();
                        // Scroll to form
                        medicationForm.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        }

        /**
         * Fetches the medication library for the search dropdown.
         */
        async function populateMedicationLibrarySelect() {
            try {
                const response = await fetch('api/get_medication_library.php');
                if (!response.ok) return;
                medicationLibrary = await response.json();
            } catch (error) {
                console.error("Failed to load medication library:", error);
            }
        }

        /**
         * Handles the search input for medications.
         */
        function handleMedicationSearch() {
            const query = medSearchInput.value.toLowerCase();
            if (query.length < 2) {
                medSearchResults.classList.add('hidden');
                return;
            }

            const results = medicationLibrary.filter(med => med.name.toLowerCase().includes(query));
            medSearchResults.innerHTML = '';

            if (results.length > 0) {
                results.slice(0, 10).forEach(med => {
                    const item = document.createElement('div');
                    item.className = 'p-2 cursor-pointer hover:bg-gray-100';
                    item.textContent = med.name;
                    item.addEventListener('click', () => {
                        medSearchInput.value = med.name;
                        document.getElementById('dosage').value = med.default_dosage || '';
                        document.getElementById('frequency').value = med.default_frequency || '';
                        medSearchResults.classList.add('hidden');
                    });
                    medSearchResults.appendChild(item);
                });
                medSearchResults.classList.remove('hidden');
            } else {
                medSearchResults.classList.add('hidden');
            }
        }

        // --- SIG CODE EXPANSION LOGIC ---
        const SIG_CODES = {
            'bid': 'Twice Daily',
            'tid': 'Three Times Daily',
            'qid': 'Four Times Daily',
            'qd': 'Once Daily',
            'qam': 'Every Morning',
            'qpm': 'Every Evening',
            'hs': 'At Bedtime',
            'prn': 'As Needed',
            'po': 'By Mouth',
            'iv': 'Intravenous',
            'im': 'Intramuscular',
            'sc': 'Subcutaneous',
            'ac': 'Before Meals',
            'pc': 'After Meals',
            'q4h': 'Every 4 Hours',
            'q6h': 'Every 6 Hours',
            'q8h': 'Every 8 Hours',
            'q12h': 'Every 12 Hours'
        };

        function expandSigCodes(inputElement) {
            let value = inputElement.value;
            if (!value) return;

            // Split by space to handle multiple codes (e.g., "po bid")
            let words = value.split(/\s+/);
            let expanded = false;

            let newWords = words.map(word => {
                const lowerWord = word.toLowerCase().replace(/[.,;]+$/, ''); // Strip punctuation for check
                if (SIG_CODES[lowerWord]) {
                    expanded = true;
                    // Preserve punctuation if present
                    const punctuation = word.match(/[.,;]+$/) ? word.match(/[.,;]+$/)[0] : '';
                    return SIG_CODES[lowerWord] + punctuation;
                }
                return word;
            });

            if (expanded) {
                inputElement.value = newWords.join(' ');
                // Visual feedback
                inputElement.classList.add('bg-green-50', 'transition-colors', 'duration-500');
                setTimeout(() => {
                    inputElement.classList.remove('bg-green-50');
                }, 1000);
            }
        }

        // --- DOMContentLoaded Initialization ---
        document.addEventListener('DOMContentLoaded', function() {
            // --- Initialize DOM Element References ---
            nameHeader = document.getElementById('patient-name-header');
            copyNarrativeBtn = document.getElementById('copyNarrativeBtn');
            autoNarrativeContent = document.getElementById('auto-narrative-content');
            medicationForm = document.getElementById('medicationForm');
            medsMessage = document.getElementById('medication-form-message');
            historyContainer = document.getElementById('medication-history-container');
            medSearchInput = document.getElementById('medication_name_search');
            medSearchResults = document.getElementById('medication-search-results');
            medIdInput = document.getElementById('medication_id');
            saveMedBtn = document.getElementById('saveMedBtn');

            // 1. INITIALIZE AUTOSAVE MANAGER (for messaging)
            initAutosaveManager();

            // 2. Attach Form Submit Listener
            medicationForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(medicationForm);
                const data = Object.fromEntries(formData.entries());

                // --- FIX: Add client-side validation before sending ---
                if (!data.drug_name || !data.dosage || !data.frequency) {
                    showFormMessage('Error: Medication Name, Dosage, and Frequency are all required.', 'error');
                    return; // Stop the submission
                }
                // --- End of validation ---

                saveMedBtn.disabled = true;
                saveMedBtn.innerHTML = '<i data-lucide="loader" class="w-4 h-4 mr-2 animate-spin"></i> Saving...';
                if (typeof lucide !== 'undefined') { lucide.createIcons(); }

                try {
                    const response = await fetch('api/create_medication.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);

                    // Use standardized floating message for success
                    showAutosaveMessage(result.message, 'success');

                    medicationForm.reset();
                    medIdInput.value = ''; // Clear ID for new entry
                    fetchMedicationHistory(); // Refreshes list and narrative

                } catch (error) {
                    // Use local form message for errors
                    showFormMessage(`Error: ${error.message}`, 'error');
                } finally {
                    saveMedBtn.disabled = false;
                    saveMedBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Medication';
                    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
                }
            });

            // 3. Attach other listeners
            document.getElementById('clearMedFormBtn').addEventListener('click', () => {
                medicationForm.reset();
                medIdInput.value = '';
                saveMedBtn.textContent = 'Save Medication';
                saveMedBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Medication';
                if (typeof lucide !== 'undefined') { lucide.createIcons(); }
            });

            // Attach Sig Code Expansion Listeners
            const dosageInput = document.getElementById('dosage');
            const frequencyInput = document.getElementById('frequency');
            
            if (dosageInput) {
                dosageInput.addEventListener('blur', () => expandSigCodes(dosageInput));
            }
            if (frequencyInput) {
                frequencyInput.addEventListener('blur', () => expandSigCodes(frequencyInput));
            }

            medSearchInput.addEventListener('input', handleMedicationSearch);
            // Hide search results when clicking elsewhere
            document.addEventListener('click', (e) => {
                if (e.target.closest('#medication-search-results') || e.target.closest('#medication_name_search')) {
                    return; // Don't hide if clicking inside search
                }
                medSearchResults.classList.add('hidden');
            });

            copyNarrativeBtn.addEventListener('click', () => {
                let narrativeText = '';
                autoNarrativeContent.querySelectorAll('.narrative-section').forEach(section => {
                    const header = section.querySelector('.narrative-section-header span').textContent;
                    const contentElement = section.querySelector('.narrative-section-content > div.prose');
                    if (contentElement && contentElement.textContent.trim().length > 0) {
                        narrativeText += `--- ${header.toUpperCase()} ---\n`;
                        let cleanText = contentElement.innerText.trim().replace(/\s\s+/g, ' ');
                        narrativeText += cleanText + '\n\n';
                    }
                });

                if (narrativeText.trim().length === 0) {
                    showAutosaveMessage('No narrative content to copy.', 'error');
                    return;
                }

                // Local copy function
                const copyToClipboard = (text) => {
                    const tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    tempInput.style.position = 'fixed';
                    tempInput.style.left = '-9999px';
                    document.body.appendChild(tempInput);
                    Services
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    return true;
                };

                if (copyToClipboard(narrativeText.trim())) {
                    showAutosaveMessage('Full Auto-Narrative copied to clipboard!', 'info');
                }
            });

            // 4. Initial Data Load
            fetchInitialData();
            populateMedicationLibrarySelect();

            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>

<?php if (isset($is_visit_signed) && $is_visit_signed): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Visit is signed. Enabling Read-Only Mode (Dynamic).');
        
        // 1. Visual Indicator
        const mainContainer = document.querySelector('main > div');
        if (mainContainer) {
            const banner = document.createElement('div');
            banner.className = 'w-full bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm rounded-r-md flex items-center justify-between col-span-full';
            banner.innerHTML = `
                <div class="flex items-center">
                    <i data-lucide="lock" class="w-6 h-6 mr-3 text-red-500"></i>
                    <div>
                        <p class="font-bold text-lg">Visit Finalized & Signed</p>
                        <p class="text-sm">This record is read-only. No further changes can be made.</p>
                    </div>
                </div>
                <span class="text-xs font-mono bg-red-100 px-2 py-1 rounded text-red-800">Signed on <?php echo date('M d, Y H:i', strtotime($signed_at_date)); ?></span>
            `;
            mainContainer.parentNode.insertBefore(banner, mainContainer);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // 2. Disable Inputs (Static)
        const formElements = document.querySelectorAll('input, textarea, select, button');
        formElements.forEach(el => {
            // Skip navigation
            if (el.closest('nav') || el.closest('#sidebar') || el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn') return;
            if (el.innerText.includes('Next') || el.innerText.includes('Prev') || el.innerText.includes('Back')) return;
            if (el.id === 'copyNarrativeBtn') return;
            
            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
        });

        // 3. Hide specific action buttons
        const hideIds = ['saveMedBtn', 'clearMedFormBtn'];
        hideIds.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });

        // 4. CSS to hide dynamic Edit buttons in the table
        const style = document.createElement('style');
        style.innerHTML = `
            #medication-history-container button { display: none !important; }
        `;
        document.head.appendChild(style);

        // 5. MutationObserver to catch any late-loading inputs
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        const inputs = node.querySelectorAll('input, select, textarea, button');
                        inputs.forEach(el => {
                             if (el.closest('nav') || el.closest('#sidebar')) return;
                             if (el.innerText.includes('Next') || el.innerText.includes('Prev')) return;
                             el.disabled = true;
                             el.classList.add('opacity-60', 'cursor-not-allowed');
                        });
                        // Also check the node itself
                        if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(node.tagName)) {
                             node.disabled = true;
                             node.classList.add('opacity-60', 'cursor-not-allowed');
                        }
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // 6. Stop Autosave if running
        if (window.Autosave) {
            window.Autosave.stop();
        }
    });
</script>
<?php endif; ?>
<!-- ORDER MEDICATION MODAL -->
<div id="orderMedicationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
    <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full overflow-hidden transform transition-all scale-100">
        <!-- Header -->
        <div class="bg-indigo-600 p-4 flex justify-between items-center">
            <h3 class="text-lg font-bold text-white flex items-center">
                <i data-lucide="pill" class="w-5 h-5 mr-2"></i>
                Order New Medication
            </h3>
            <button onclick="closeOrderModal()" class="text-white hover:text-gray-200 focus:outline-none">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Body -->
        <div class="p-6 max-h-[80vh] overflow-y-auto">
            <form id="orderMedicationForm" class="space-y-4">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Medication Name</label>
                    <input type="text" name="medication_name" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" placeholder="e.g. Amoxicillin">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dosage</label>
                        <input type="text" name="dosage" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" placeholder="e.g. 500mg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Frequency</label>
                        <input type="text" name="frequency" required class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" placeholder="e.g. TID (3 times a day)">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Route</label>
                        <select name="route" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border">
                            <option value="Oral">Oral</option>
                            <option value="Topical">Topical</option>
                            <option value="IV">IV</option>
                            <option value="IM">IM</option>
                            <option value="SubQ">SubQ</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                        <input type="text" name="quantity" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" placeholder="e.g. 30 tabs">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Refills</label>
                        <input type="number" name="refills" min="0" value="0" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pharmacy Note / Instructions</label>
                    <textarea name="pharmacy_note" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border" placeholder="Additional instructions for the pharmacist..."></textarea>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="bg-gray-50 p-4 flex justify-end space-x-3 border-t">
            <button onclick="closeOrderModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 font-medium transition">Cancel</button>
            <button onclick="submitOrder()" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-medium transition flex items-center">
                <i data-lucide="send" class="w-4 h-4 mr-2"></i>
                Send Order
            </button>
        </div>
    </div>
</div>

<script>
    function openOrderModal() {
        const modal = document.getElementById('orderMedicationModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeOrderModal() {
        const modal = document.getElementById('orderMedicationModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('orderMedicationForm').reset();
    }

    async function submitOrder() {
        const form = document.getElementById('orderMedicationForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('api/save_medication_order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Medication ordered successfully!');
                closeOrderModal();
                // Optional: Refresh medication list if we decide to show orders there too
                // fetchMedicationHistory(); 
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An unexpected error occurred.');
        }
    }
</script>

<?php
require_once 'templates/footer.php';
?>