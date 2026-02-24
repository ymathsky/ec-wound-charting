<?php
// Filename: visit_hpi.php
// UPDATED: Added auto-regeneration of AI narrative upon successful autosave.

session_start();

require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/db_connect.php';

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
// THIS IS THE CLINICIAN'S ID, crucial for fetching the correct questionnaire
$user_id = (isset($_GET['user_id']) && intval($_GET['user_id']) > 0) ? intval($_GET['user_id']) : (isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0);


if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

if ($user_id <= 0) {
    echo "<div class='p-8'>Invalid User ID or session expired. Please <a href='login.php' class='text-blue-600 underline'>log in</a> again.</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- CHECK VISIT STATUS ---
require_once 'visit_status_check.php';

// Define Previous step link (assuming Vitals is the step before HPI)
$previous_step_url = "visit_vitals.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";

// Define Next step link (Wounds)
$next_step_url = "visit_wounds.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";

?>

    <style>
        .form-section {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-section:last-child { border-bottom: none; margin-bottom: 0; }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
        }

        /* --- NEW STYLES for Wound Context List --- */
        .wound-context-list {
            margin-top: 0.75rem;
            padding-left: 1rem;
            border-left: 3px solid #e5e7eb; /* Light gray border */
            space-y: 0.75rem;
        }
        .wound-context-input-block {
            margin-top: 0.75rem;
        }
        /* Style for text, select */
        .wound-context-input-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .wound-context-input-row label {
            font-size: 0.9rem; /* 14px */
            font-medium: 500;
            color: #374151; /* gray-700 */
            flex-shrink: 0;
        }
        .wound-context-input-row label.general-label {
            color: #1d4ed8; /* blue-700 */
        }
        .wound-context-input-row .input-wrapper {
            flex-grow: 1;
            max-width: 60%; /* Prevents inputs from being too wide */
        }
        /* Style for radio, checkbox */
        .wound-context-input-column label {
            font-size: 0.9rem; /* 14px */
            font-medium: 500;
            color: #374151; /* gray-700 */
            margin-bottom: 0.5rem;
            display: block;
        }
        .wound-context-input-column label.general-label {
            color: #1d4ed8; /* blue-700 */
        }

        /* (All other styles from your original file: #autosave-message, #mobile-fab-container, etc.) */
        /* Base styles */
        .form-button { min-height: 48px; }

        /* FLOATING ALERT STYLES (Standardized) */
        #autosave-message-container {
            position: fixed;
            bottom: 6rem; /* Above the FAB */
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 400px;
            z-index: 100; /* Ensure it's above everything */
            pointer-events: none; /* Allows clicks to pass through when hidden */
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

        /* FAB Styling */
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
            #mobile-fab-container {
                display: none !important;
            }
        }
        .fab-nav-button {
            height: 56px !important;
            padding: 0.75rem 0.5rem !important;
        }

        /* Autosave Status Indicator */
        #autosave-status {
            background-color: #10b981; /* Green */
            color: white;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 48px;
            border-radius: 0.5rem;
            opacity: 0.9;
            transition: opacity 0.3s;
        }
        #autosave-status.saving {
            background-color: #fbbf24; /* Yellow */
            color: #1f2937;
        }
        #autosave-status-mobile-container {
            width: 100%;
        }

        /* Desktop action row styling */
        @media (min-width: 1024px) {
            #hpi-form-actions {
                /* grid-column: span 4 / span 4; */
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                padding-top: 1rem;
            }
            #autosave-status-desktop-container {
                flex-grow: 1;
                max-width: 200px;
                min-width: 150px;
            }
        }
    </style>

    <!-- FLOATING ALERT CONTAINER (Standardized ID) -->
    <div id="autosave-message-container">
        <div id="autosave-message" class="p-3 my-3 rounded-lg text-sm text-center shadow-lg"></div>
    </div>

    <div class="flex h-screen bg-gray-100">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once __DIR__ . '/templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- HEADER (Standardized) -->
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md flex-shrink-0">
                <div class="flex items-center min-w-0 flex-grow">
                    <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4 flex-shrink-0">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div class="min-w-0 mr-4 flex-shrink">
                        <h1 id="patient-name-header" class="text-xl font-bold text-gray-800 truncate">Loading Patient...</h1>
                        <p id="patient-dob-subheader" class="text-xs text-gray-600">Step 2 of 6: History of Present Illness (Autosaved)</p>
                    </div>
                </div>

                <!-- NEW: Clone Last Visit Button -->
                <div class="hidden md:block">
                    <button type="button" id="cloneLastVisitBtn" class="bg-white border border-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg hover:bg-gray-50 transition duration-150 ease-in-out shadow-sm flex items-center text-sm" title="Copy HPI from the previous visit">
                        <i data-lucide="copy-plus" class="w-4 h-4 mr-2 text-blue-500"></i>
                        Clone Last Visit
                    </button>
                </div>
            </header>

            <!-- VISIT SUBMENU: WRAPPED IN STICKY CONTAINER -->
            <div class="sticky top-0 z-30">
                <?php require_once __DIR__ . '/templates/visit_submenu.php'; ?>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 relative">
                <!-- Toggle Button for Narrative Panel -->
                <button id="narrative-toggle-btn" class="fixed right-0 top-1/2 transform -translate-y-1/2 bg-blue-600 text-white p-2 rounded-l-md shadow-lg z-50 hidden hover:bg-blue-700 transition" onclick="toggleNarrativePanel()" title="Show Auto-Narrative">
                    <i data-lucide="chevron-left" class="w-6 h-6"></i>
                </button>

                <div class="flex gap-6 h-full">
                    <!-- Left Column: Main HPI Content -->
                    <div id="hpi-main-content" class="w-full lg:mr-96 transition-all duration-300">
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="text-xl font-semibold mb-4 text-gray-800">History of Present Illness (HPI)</h3>

                            <!-- === NEW: ACTIVE WOUNDS SUMMARY (Collapsible) === -->
                            <div id="active-wounds-section" class="form-section border rounded-lg mb-6 overflow-hidden bg-white shadow-sm">
                                <div id="active-wounds-header" class="flex justify-between items-center p-4 bg-blue-50 cursor-pointer hover:bg-blue-100 transition select-none border-b border-blue-100">
                                    <h4 class="text-lg font-bold text-blue-800 flex items-center">
                                        <i data-lucide="activity" class="w-5 h-5 mr-2"></i> Active Wound Summary
                                    </h4>
                                    <i data-lucide="chevron-down" class="w-5 h-5 text-blue-500 transition-transform duration-200 transform"></i>
                                </div>
                                <div id="active-wounds-content" class="p-4 bg-white">
                                    <div id="active-wounds-list" class="text-gray-500">
                                        <div class="flex items-center text-sm">
                                            <i data-lucide="loader-2" class="w-5 h-5 animate-spin inline-block mr-2"></i>
                                            Loading active wounds...
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- === END: NEW SECTION === -->


                            <form id="hpiForm" class="space-y-6">
                                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">

                                <!--
                                =======================================================
                                === DYNAMIC HPI FORM CONTAINER ========================
                                =======================================================
                                -->
                                <div class="flex justify-end mb-2 space-x-2">
                                    <button type="button" id="expandAllBtn" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center">
                                        <i data-lucide="maximize-2" class="w-3 h-3 mr-1"></i> Expand All
                                    </button>
                                    <span class="text-gray-300">|</span>
                                    <button type="button" id="collapseAllBtn" class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center">
                                        <i data-lucide="minimize-2" class="w-3 h-3 mr-1"></i> Collapse All
                                    </button>
                                </div>

                                <div id="dynamic-hpi-form-container" class="space-y-6">
                                    <div class="p-8 text-center text-gray-500">
                                        <i data-lucide="loader-2" class="w-8 h-8 animate-spin inline-block mb-2"></i>
                                        <p>Loading HPI Questionnaire...</p>
                                    </div>
                                </div>
                                <!-- =======================================================
                                === END DYNAMIC HPI FORM CONTAINER ====================
                                =======================================================
                                -->

                                <!-- DESKTOP ACTION ROW (Standardized) -->
                                <div id="hpi-form-actions" class="pt-4 hidden md:flex">
                                    <a href="<?php echo $previous_step_url; ?>"
                                       class="h-12 bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md transition flex items-center justify-center text-sm min-w-[150px]">
                                        &larr; Prev: Vitals
                                    </a>

                                    <div id="autosave-status-desktop-container" class="h-12 hidden lg:flex items-center justify-center text-sm font-bold">
                                        <div id="autosave-status-desktop" class="h-full w-full flex items-center justify-center text-sm font-bold bg-green-500 text-white rounded-md transition">
                                            <i data-lucide="check" class="w-5 h-5 mr-1"></i> Autosaved
                                        </div>
                                    </div>

                                    <a href="<?php echo $next_step_url; ?>"
                                       class="h-12 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center justify-center text-sm min-w-[150px]">
                                        Next: Wounds &rarr;
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Floating Narrative Panel -->
                    <div id="narrative-panel" class="fixed right-6 top-36 bottom-6 w-80 lg:w-96 bg-white rounded-lg shadow-2xl border border-gray-200 z-40 flex flex-col transition-transform duration-300 transform translate-x-0">
                        <!-- Panel Header -->
                        <div class="flex justify-between items-center p-3 border-b bg-gray-50 rounded-t-lg select-none">
                            <h3 class="font-bold text-gray-700 flex items-center">
                                <i data-lucide="bot" class="w-5 h-5 mr-2 text-indigo-600"></i> Auto-Narrative
                            </h3>
                            <button onclick="toggleNarrativePanel()" class="text-gray-400 hover:text-gray-600 transition" title="Minimize">
                                <i data-lucide="minimize-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                        
                        <!-- Scrollable Content -->
                        <div class="flex-1 overflow-y-auto p-4">
                            <!-- Action Buttons Toolbar -->
                            <div class="grid grid-cols-3 gap-2 mb-4">
                                <button id="aiSaveBtn" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-2 rounded shadow-sm text-xs flex items-center justify-center transition opacity-50 cursor-not-allowed h-9" title="Save AI HPI to Final Note" disabled>
                                    <i data-lucide="save" class="w-4 h-4 mr-1.5"></i>Save
                                </button>
                                <button id="aiRegenerateBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-2 rounded shadow-sm text-xs flex items-center justify-center transition h-9" title="Reconstruct HPI with AI">
                                    <i data-lucide="wand-2" class="w-4 h-4 mr-1.5"></i>Regen
                                </button>
                                <button id="copyNarrativeBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-2 rounded shadow-sm text-xs flex items-center justify-center transition h-9" title="Copy to Clipboard">
                                    <i data-lucide="copy" class="w-4 h-4 mr-1.5"></i>Copy
                                </button>
                            </div>

                            <!-- AI Style Selector -->
                            <div class="mb-4 flex items-center space-x-2 bg-gray-50 p-2 rounded border border-gray-200">
                                <label for="aiStyleSelect" class="text-sm font-semibold text-gray-700"><i data-lucide="palette" class="w-4 h-4 inline mr-1"></i>Style:</label>
                                <select id="aiStyleSelect" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 flex-grow">
                                    <option value="standard">Standard Medical (Default)</option>
                                    <option value="detailed">Detailed & Comprehensive</option>
                                    <option value="brief">Brief / Bulleted</option>
                                    <option value="patient">Patient-Friendly</option>
                                </select>
                            </div>

                            <!-- Quick Guide (Collapsible) -->
                            <div class="mb-4 border border-blue-100 rounded-md overflow-hidden">
                                <button type="button" onclick="const el = document.getElementById('manual-content'); el.classList.toggle('hidden'); this.querySelector('.chevron').classList.toggle('rotate-180');" class="w-full flex justify-between items-center p-2 bg-blue-50 text-xs font-bold text-blue-700 hover:bg-blue-100 transition">
                                    <span><i data-lucide="info" class="w-3 h-3 inline mr-1"></i> Quick Guide</span>
                                    <i data-lucide="chevron-down" class="w-3 h-3 chevron transition-transform duration-200"></i>
                                </button>
                                <div id="manual-content" class="hidden p-3 bg-white text-xs text-gray-600 space-y-2 border-t border-blue-100">
                                    <ol class="list-decimal list-inside space-y-1 ml-1">
                                        <li><strong>Answer Questions:</strong> Fill out the HPI form on the left.</li>
                                        <li><strong>Choose Style:</strong> Select a narrative style above.</li>
                                        <li><strong>Regenerate:</strong> Click "Regen" to update the text.</li>
                                        <li><strong>Save:</strong> Click "Save" to add to the final note.</li>
                                    </ol>
                                </div>
                            </div>

                            <div id="auto-narrative-content" class="text-sm space-y-4">
                                <p id="narrative-placeholder" class="text-gray-500 text-center py-6">Load patient data to generate narrative...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- FLOATING ACTION BUTTON (FAB) CONTAINER (Standardized) -->
    <div id="mobile-fab-container" class="md:hidden">
        <div id="autosave-status-mobile-container" class="w-full">
            <div id="autosave-status" class="h-12 w-full rounded-md text-sm">
                <i data-lucide="check" class="w-5 h-5 mr-1"></i> Autosaved
            </div>
        </div>
        <div class="flex space-x-2 w-full">
            <a href="<?php echo $previous_step_url; ?>"
               class="flex-1 bg-gray-200 text-gray-800 font-bold py-2 px-1 rounded-md transition form-button fab-nav-button flex items-center justify-center text-sm">
                &larr; Prev
            </a>
            <a href="<?php echo $next_step_url; ?>"
               class="flex-1 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-1 rounded-md transition form-button fab-nav-button flex items-center justify-center text-sm">
                Next &rarr;
            </a>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- MODIFIED: Loading new HPI-specific narrative script -->
    <script src="auto_narrative_hpi.js"></script>
    <script src="js/hpi_dictation.js"></script>
    <script type="module" src="autosave_manager.js"></script>

    <script type="module">
        import { initAutosaveManager, attachAutosaveListeners, showAutosaveMessage } from './autosave_manager.js';

        // --- GLOBAL DATA (Visible to auto_narrative.js) ---
        window.currentVitalsData = {};
        window.currentHpiData = {}; // This will be populated with { narrative_key: value }
        window.globalWoundsData = [];
        window.quillEditors = {};

        // --- NEW: Make IDs globally accessible for JS modules ---
        window.patientId = <?php echo $patient_id; ?>;
        window.appointmentId = <?php echo $appointment_id; ?>;
        window.clinicianId = <?php echo $user_id; ?>; // The clinician conducting the visit

        // --- DOM REFERENCES ---
        let hpiForm, nameHeader, autoNarrativeContent, copyNarrativeBtn;

        /**
         * Reads window.globalWoundsData and displays active wounds in the summary box.
         */
        function displayActiveWounds() {
            const container = document.getElementById('active-wounds-list');
            if (!container) return;

            const wounds = window.globalWoundsData || [];
            const activeWounds = wounds.filter(w => w.status === 'Active');

            if (activeWounds.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-500 italic">No active wounds on file for this patient.</p>';
                return;
            }

            let html = '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';
            activeWounds.forEach(wound => {
                // Extract details or set defaults
                const dimensions = wound.latest_dimensions || 'No measurements';
                const exudate = wound.latest_exudate_summary || 'No exudate info';
                const periwound = wound.latest_periwound || 'No periwound info';
                const onset = wound.date_onset || 'Unknown';

                html += `
                    <div class="border border-blue-100 rounded-lg p-3 bg-blue-50/30 hover:bg-blue-50 transition shadow-sm">
                        <div class="flex justify-between items-start mb-2 border-b border-blue-100 pb-2">
                            <span class="font-bold text-blue-800 text-sm uppercase tracking-wide">${wound.location}</span>
                            <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">${wound.wound_type}</span>
                        </div>
                        <div class="space-y-1 text-xs text-gray-600">
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-500">Onset:</span>
                                <span>${onset}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-500">Dimensions:</span>
                                <span class="font-mono text-gray-700">${dimensions}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-500">Exudate:</span>
                                <span>${exudate}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium text-gray-500">Periwound:</span>
                                <span>${periwound}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        /**
         * --- NEW: createInputHTML ---
         * Helper function to generate *only* the HTML for the input part of a question.
         * The form builder will handle the main question label.
         */
        function createInputHTML(q, wound_id) {
            const woundKey = wound_id ? wound_id : 'NULL';
            const inputName = `q_${q.question_id}_${woundKey}`;
            const inputId = inputName;

            // Narrative key only goes on the "General" (NULL) inputs
            const narrativeKeyData = (woundKey === 'NULL' && q.narrative_key)
                ? `data-narrative-key="${q.narrative_key}"`
                : '';

            let inputHtml = '';

            switch (q.question_type) {
                case 'text':
                    inputHtml = `
                        <div class="relative mt-1">
                            <input type="text" name="${inputName}" id="${inputId}" ${narrativeKeyData} class="block w-full border-gray-300 rounded-md shadow-sm p-2 pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600" onclick="toggleHpiDictation(document.getElementById('${inputId}'), this)">
                                <i data-lucide="mic" class="w-5 h-5"></i>
                            </button>
                        </div>`;
                    break;

                case 'textarea':
                    inputHtml = `
                        <div class="relative mt-1">
                            <textarea name="${inputName}" id="${inputId}" ${narrativeKeyData} rows="3" class="block w-full border-gray-300 rounded-md shadow-sm p-2 pr-10"></textarea>
                            <button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600" onclick="toggleHpiDictation(document.getElementById('${inputId}'), this)">
                                <i data-lucide="mic" class="w-5 h-5"></i>
                            </button>
                        </div>`;
                    break;

                case 'select':
                    let options = JSON.parse(q.options || '[]').map(opt => `<option value="${opt}">${opt}</option>`).join('');
                    inputHtml = `<select name="${inputName}" id="${inputId}" ${narrativeKeyData} class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                                    <option value="">-- Select --</option>
                                    ${options}
                                  </select>`;
                    break;

                case 'radio':
                    let radioOptions = JSON.parse(q.options || '[]').map((opt, i) =>
                        `<label class="flex items-center"><input type="radio" name="${inputName}" id="${inputId}_${i}" value="${opt}" ${narrativeKeyData} class="h-4 w-4 border-gray-300 text-blue-600 mr-2">${opt}</label>`
                    ).join('');
                    inputHtml = `<div class="mt-2 flex space-x-4">${radioOptions}</div>`;
                    break;

                case 'checkbox':
                    let checkOptions = JSON.parse(q.options || '[]').map((opt, i) =>
                        `<label class="flex items-center"><input type="checkbox" name="${inputName}[]" id="${inputId}_${i}" value="${opt}" ${narrativeKeyData} class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">${opt}</label>`
                    ).join('');
                    inputHtml = `<div class="mt-2 checkbox-grid">${checkOptions}</div>`;
                    break;
            }
            return inputHtml;
        }

        /**
         * --- NEW: createWoundContextInput ---
         * Helper function to generate the repeating row for wound-linkable questions.
         */
        function createWoundContextInput(q, wound_id, wound_label) {
            const woundKey = wound_id ? wound_id : 'NULL';
            const inputName = `q_${q.question_id}_${woundKey}`;
            const inputId = inputName;

            // Narrative key only goes on the "General" (NULL) inputs
            const narrativeKeyData = (woundKey === 'NULL' && q.narrative_key)
                ? `data-narrative-key="${q.narrative_key}"`
                : '';

            let inputHtml = '';
            const labelHtml = `<label for="${inputId}" class="wound-context-label ${woundKey === 'NULL' ? 'general-label' : ''}">${wound_label}:</label>`;

            // Check if radio or checkbox, which need a different layout
            if (q.question_type === 'radio' || q.question_type === 'checkbox') {
                const options = JSON.parse(q.options || '[]').map((opt, i) => {
                    const optionId = `${inputId}_${i}`;
                    const optionName = q.question_type === 'checkbox' ? `${inputName}[]` : inputName;
                    return `<label class="flex items-center">
                                <input type="${q.question_type}" name="${optionName}" id="${optionId}" value="${opt}" ${narrativeKeyData} class="h-4 w-4 border-gray-300 text-blue-600 mr-2">
                                ${opt}
                            </label>`;
                }).join('');

                const gridClass = q.question_type === 'checkbox' ? 'checkbox-grid' : 'flex space-x-4';

                inputHtml = `
                    <div class="wound-context-input-column mt-3">
                        ${labelHtml}
                        <div class="mt-2 ${gridClass}">${options}</div>
                    </div>`;
            } else {
                // Text, Textarea, Select
                let fieldHtml = '';
                if (q.question_type === 'text') {
                    fieldHtml = `
                        <div class="relative w-full">
                            <input type="text" name="${inputName}" id="${inputId}" ${narrativeKeyData} class="block w-full border-gray-300 rounded-md shadow-sm p-2 pr-10">
                            <button type="button" class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600" onclick="toggleHpiDictation(document.getElementById('${inputId}'), this)">
                                <i data-lucide="mic" class="w-4 h-4"></i>
                            </button>
                        </div>`;
                } else if (q.question_type === 'textarea') {
                    fieldHtml = `
                        <div class="relative w-full">
                            <textarea name="${inputName}" id="${inputId}" ${narrativeKeyData} rows="2" class="block w-full border-gray-300 rounded-md shadow-sm p-2 pr-10"></textarea>
                            <button type="button" class="absolute top-2 right-2 text-gray-400 hover:text-gray-600" onclick="toggleHpiDictation(document.getElementById('${inputId}'), this)">
                                <i data-lucide="mic" class="w-4 h-4"></i>
                            </button>
                        </div>`;
                } else if (q.question_type === 'select') {
                    let options = JSON.parse(q.options || '[]').map(opt => `<option value="${opt}">${opt}</option>`).join('');
                    fieldHtml = `<select name="${inputName}" id="${inputId}" ${narrativeKeyData} class="block w-full border-gray-300 rounded-md shadow-sm p-2">
                                    <option value="">-- Select --</option>
                                    ${options}
                                 </select>`;
                }

                inputHtml = `
                    <div class="wound-context-input-row">
                        ${labelHtml}
                        <div class="input-wrapper">${fieldHtml}</div>
                    </div>`;
            }
            return inputHtml;
        }


        /**
         * Builds the HPI form dynamically from fetched questions.
         */
        function buildDynamicForm(questions) {
            const container = document.getElementById('dynamic-hpi-form-container');
            if (!container) return;

            container.innerHTML = '';
            let currentCategory = '';
            let currentSectionContent = null;

            // Get active wounds to build the context list
            const activeWounds = (window.globalWoundsData || []).filter(w => w.status === 'Active');
            const woundContexts = [
                { wound_id: null, label: 'General HPI' }, // Always have a "General" option
                ...activeWounds.map(w => ({
                    wound_id: w.wound_id,
                    label: `${w.location} (${w.wound_type})`
                }))
            ];

            questions.forEach(q => {
                // Group questions by category
                if (q.category !== currentCategory) {
                    currentCategory = q.category;
                    
                    // Create Section Container
                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'form-section border rounded-lg mb-4 overflow-hidden bg-white shadow-sm';
                    
                    // Create Header (Clickable)
                    const headerDiv = document.createElement('div');
                    headerDiv.className = 'flex justify-between items-center p-4 bg-gray-50 cursor-pointer hover:bg-gray-100 transition select-none hpi-section-header';
                    headerDiv.innerHTML = `
                        <h2 class="text-lg font-bold text-gray-700">${q.category}</h2>
                        <i data-lucide="chevron-down" class="w-5 h-5 text-gray-500 transition-transform duration-200 transform"></i>
                    `;
                    
                    // Create Content Container
                    const contentDiv = document.createElement('div');
                    contentDiv.className = 'p-4 border-t border-gray-100 hpi-section-content'; // Visible by default
                    
                    // Toggle Logic
                    headerDiv.addEventListener('click', () => {
                        contentDiv.classList.toggle('hidden');
                        const icon = headerDiv.querySelector('i');
                        if (contentDiv.classList.contains('hidden')) {
                            icon.style.transform = 'rotate(-90deg)';
                        } else {
                            icon.style.transform = 'rotate(0deg)';
                        }
                    });

                    sectionDiv.appendChild(headerDiv);
                    sectionDiv.appendChild(contentDiv);
                    container.appendChild(sectionDiv);
                    
                    currentSectionContent = contentDiv;
                }

                const questionWrapper = document.createElement('div');
                questionWrapper.className = 'mb-4';

                if (q.allow_wound_link == 1 && activeWounds.length > 0) {
                    // --- WOUND-LINKABLE QUESTION ---
                    // 1. Add the main question label once
                    const mainLabel = document.createElement('label');
                    mainLabel.className = 'block text-sm font-medium text-gray-900';
                    mainLabel.textContent = q.question_text;
                    questionWrapper.appendChild(mainLabel);

                    // 2. Loop through contexts and create a block for each
                    const contextListWrapper = document.createElement('div');
                    contextListWrapper.className = 'wound-context-list';

                    woundContexts.forEach(wound => {
                        const contextHTML = createWoundContextInput(q, wound.wound_id, wound.label);
                        const contextBlock = document.createElement('div');
                        contextBlock.innerHTML = contextHTML;
                        contextListWrapper.appendChild(contextBlock);
                    });
                    questionWrapper.appendChild(contextListWrapper);

                } else {
                    // --- STANDARD QUESTION ---
                    // This question only renders once
                    const inputId = `q_${q.question_id}_NULL`;
                    const mainLabel = document.createElement('label');
                    mainLabel.className = 'block text-sm font-medium text-gray-700';
                    mainLabel.htmlFor = inputId;
                    mainLabel.textContent = q.question_text;
                    questionWrapper.appendChild(mainLabel);

                    const inputHTML = createInputHTML(q, null);
                    const inputWrapper = document.createElement('div');
                    inputWrapper.innerHTML = inputHTML;
                    questionWrapper.appendChild(inputWrapper);
                }

                if (currentSectionContent) {
                    currentSectionContent.appendChild(questionWrapper);
                } else {
                    // Fallback if no category (shouldn't happen with proper data)
                    container.appendChild(questionWrapper);
                }
            });

            // Re-attach autosave listeners AFTER the form is built
            // MODIFICATION: Added empty onHpiInputChange to prevent error
            attachAutosaveListeners(hpiForm, submitHpiForm, onHpiInputChange);
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
            
            // Setup Expand/Collapse All Buttons
            setupExpandCollapseButtons();
            
            // Setup Active Wounds Toggle
            const woundsHeader = document.getElementById('active-wounds-header');
            const woundsContent = document.getElementById('active-wounds-content');
            if (woundsHeader && woundsContent) {
                woundsHeader.addEventListener('click', () => {
                    woundsContent.classList.toggle('hidden');
                    const icon = woundsHeader.querySelector('i[data-lucide="chevron-down"]');
                    if (icon) {
                        icon.style.transform = woundsContent.classList.contains('hidden') ? 'rotate(-90deg)' : 'rotate(0deg)';
                    }
                });
            }
        }

        function setupExpandCollapseButtons() {
            const expandBtn = document.getElementById('expandAllBtn');
            const collapseBtn = document.getElementById('collapseAllBtn');
            
            if (expandBtn) {
                expandBtn.onclick = () => {
                    document.querySelectorAll('.hpi-section-content').forEach(el => el.classList.remove('hidden'));
                    document.querySelectorAll('.hpi-section-header i').forEach(icon => icon.style.transform = 'rotate(0deg)');
                };
            }
            
            if (collapseBtn) {
                collapseBtn.onclick = () => {
                    document.querySelectorAll('.hpi-section-content').forEach(el => el.classList.add('hidden'));
                    document.querySelectorAll('.hpi-section-header i').forEach(icon => icon.style.transform = 'rotate(-90deg)');
                };
            }
        }

        /**
         * Populates the dynamic form with data.
         * @param {object} answers (e.g., {"123_NULL": {answer_value: "Worsening"}, "123_45": {answer_value: "Improving"}})
         */
        const populateForm = (answers) => {
            if (!hpiForm || !answers) return;

            for (const composite_key in answers) {
                if (answers.hasOwnProperty(composite_key)) {
                    const value = answers[composite_key].answer_value;

                    // Input name is "q_" + composite_key
                    const inputName = `q_${composite_key}`; // e.g., "q_123_NULL"
                    const arrayName = `${inputName}[]`;     // e.g., "q_123_NULL[]"

                    const elements = hpiForm.elements[inputName];
                    const arrayElements = hpiForm.elements[arrayName]; // For checkboxes

                    if (arrayElements && arrayElements.length > 0) { // Checkboxes
                        const values = (typeof value === 'string' && value) ? value.split(', ') : [];
                        Array.from(arrayElements).forEach(checkbox => {
                            if (values.includes(checkbox.value)) {
                                checkbox.checked = true;
                            }
                        });
                    } else if (elements) { // Radio, Select, Text
                        if (elements.length && elements[0].type === 'radio') {
                            const radioToSelect = Array.from(elements).find(rb => rb.value === value);
                            if (radioToSelect) radioToSelect.checked = true;
                        } else {
                            elements.value = value;
                        }
                    }
                }
            }
        };


        /**
         * Fetches all data required for this page (Patient, Vitals, Wounds, HPI Questions, HPI Answers)
         */
        async function fetchInitialData() {
            try {
                // --- FIX: Add cache-busting parameter ---
                const cacheBuster = new Date().getTime();

                // 1. Fetch Patient Details and Wounds
                const patientResponse = await fetch(`api/get_patient_details.php?id=${window.patientId}&_=${cacheBuster}`);
                if (!patientResponse.ok) throw new Error('Failed to fetch patient details');
                const data = await patientResponse.json();

                const patient = data.details;
                nameHeader.textContent = `${patient.first_name} ${patient.last_name}`;
                window.globalWoundsData = data.wounds;
                displayActiveWounds();

                // 2. Fetch Vitals (for narrative panel)
                const vitalsResponse = await fetch(`api/get_vitals.php?patient_id=${window.patientId}&appointment_id=${window.appointmentId}&_=${cacheBuster}`);
                if(vitalsResponse.ok) {
                    const vitalsResult = await vitalsResponse.json();
                    if(vitalsResult) {
                        window.currentVitalsData = vitalsResult;
                    }
                }

                // 3. Fetch HPI Questions (NEW)
                const questionsResponse = await fetch(`api/get_hpi_questions.php?active=true&user_id=${window.clinicianId}&_=${cacheBuster}`);
                const questionsResult = await questionsResponse.json();
                if (questionsResult.success) {
                    // This *must* run after wounds are fetched
                    buildDynamicForm(questionsResult.questions);
                } else {
                    throw new Error('Failed to load HPI questionnaire.');
                }

                // 4. Fetch HPI Answers (MODIFIED) - *Must run AFTER buildDynamicForm*
                const hpiResponse = await fetch(`api/get_hpi_data.php?appointment_id=${window.appointmentId}&_=${cacheBuster}`);
                if (hpiResponse.ok) {
                    const hpiResult = await hpiResponse.json();
                    if (hpiResult.success && hpiResult.data) {
                        window.currentHpiData = hpiResult.data.answers_for_narrative; // Still used by AI
                        populateForm(hpiResult.data.answers_by_key); // Use new object key
                    }
                }

                // 5. Update the narrative panel
                // MODIFICATION: Removed call to window.updateAutoNarrative()
                // Instead, we will call the AI and other generators directly.
                if (typeof window.fetchAiHpiNarrative === 'function') {
                    // This is the new AI call on page load
                    window.fetchAiHpiNarrative();
                }
                if (typeof window.updateAutoNarrative === 'function') {
                    // This will now only load Vitals and Wounds (since HPI is handled by the AI)
                    setTimeout(window.updateAutoNarrative, 100);
                }

            } catch (error) {
                nameHeader.textContent = 'Error Loading Patient';
                document.getElementById('dynamic-hpi-form-container').innerHTML = `<p class="p-8 text-center text-red-500">Error loading questionnaire: ${error.message}</p>`;
                document.getElementById('active-wounds-list').innerHTML = '<p class="text-sm text-red-500">Could not load active wounds.</p>';
                console.error("Initialization Error:", error);
                showAutosaveMessage(`Error loading patient data: ${error.message}`, 'error');
            }
        }

        /**
         * Gathers form data into the composite key format for saving.
         * e.g., { "123_NULL": "Answer", "123_45": "Wound Answer" }
         */
        function getHpiDataForSave() {
            const formData = new FormData(hpiForm);
            const data = {
                patient_id: window.patientId,
                appointment_id: window.appointmentId,
                answers: {} // This is where the answers {composite_key: value} will go
            };

            for (let [key, value] of formData.entries()) {
                if (key.startsWith('q_')) { // Only get our dynamic question inputs

                    // key is "q_{question_id}_{wound_id}" or "q_{question_id}_{wound_id}[]"
                    const isCheckbox = key.endsWith('[]');
                    const cleanKey = key.substring(2).replace('[]', ''); // e.g., "123_NULL" or "123_45"

                    if (isCheckbox) {
                        if (!data.answers[cleanKey]) {
                            data.answers[cleanKey] = [];
                        }
                        data.answers[cleanKey].push(value);
                    } else { // Other inputs
                        data.answers[cleanKey] = value;
                    }
                }
            }
            return data;
        }

        /**
         * REMOVED: The getHpiDataForNarrative() function is no longer needed
         * as the "dumb" template is being replaced.
         */

        /**
         * NEW: Gathers ALL HPI data for the AI, using question text as the key.
         * This IGNORES the narrative_key and sends everything.
         * This focuses ONLY on "General HPI" (NULL wound_id) inputs.
         */
        window.getAllHpiDataForAI = function() {
            const aiData = {};
            if (!hpiForm) return aiData;

            const elements = hpiForm.elements;
            const checkboxGroups = {};

            for (let i = 0; i < elements.length; i++) {
                const el = elements[i];

                // Only process inputs for "General HPI" (those with _NULL)
                if (!el.name.startsWith('q_') || !el.name.includes('_NULL')) {
                    continue;
                }

                // Find the main question label
                let questionText = '';
                // Find the nearest parent wrapper for the question
                const questionWrapper = el.closest('.mb-4, .wound-context-input-column, .wound-context-input-row');

                if (questionWrapper) {
                    // Find the label. This logic is a bit more robust to find the correct one.
                    let label = questionWrapper.querySelector('label.block'); // Standard
                    if (!label) {
                        // For wound-context blocks (General HPI only)
                        const generalLabel = questionWrapper.querySelector('label.general-label');
                        if(generalLabel) {
                            // This is a wound-linkable question, get the main label from its parent
                            const parentSection = generalLabel.closest('.mb-4');
                            if(parentSection) {
                                label = parentSection.querySelector('label.block');
                            }
                        }
                    }

                    if (label) questionText = label.textContent.trim();
                }

                if (!questionText) continue; // Skip if no label

                // Handle different input types
                if (el.type === 'textarea' || el.type === 'text' || el.type === 'select-one') {
                    if (el.value) {
                        aiData[questionText] = el.value;
                    }
                } else if (el.type === 'radio') {
                    if (el.checked && el.value) {
                        aiData[questionText] = el.value;
                    }
                } else if (el.type === 'checkbox') {
                    if (el.checked && el.value) {
                        if (!checkboxGroups[questionText]) {
                            checkboxGroups[questionText] = [];
                        }
                        checkboxGroups[questionText].push(el.value);
                    }
                }
            }

            // Combine checkbox values
            for (const question in checkboxGroups) {
                aiData[question] = checkboxGroups[question].join(', ');
            }

            return aiData;
        }


        /**
         * Core submission logic, executed by autosave.
         */
        async function submitHpiForm(showNotification) {
            const dataToSave = getHpiDataForSave();

            // MODIFICATION: Removed real-time update for "dumb" template
            // window.currentHpiData = getHpiDataForNarrative();
            // if (typeof window.updateAutoNarrative === 'function') {
            //     window.updateAutoNarrative();
            // }

            try {
                const response = await fetch('api/save_hpi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dataToSave)
                });

                const responseBody = await response.text();
                let result;

                try {
                    result = JSON.parse(responseBody);
                } catch (e) {
                    throw new Error(responseBody.substring(0, 500) || "Server error: Invalid response");
                }

                if (result.success) {
                    // Re-fetch narrative data to be 100% in sync
                    // --- FIX: Add cache-busting parameter ---
                    const cacheBuster = new Date().getTime();
                    const hpiResponse = await fetch(`api/get_hpi_data.php?appointment_id=${window.appointmentId}&_=${cacheBuster}`);
                    // --- END FIX ---
                    const hpiResult = await hpiResponse.json();
                    if (hpiResult.success && hpiResult.data) {
                        // This data is still needed for the AI, just not the "dumb" template
                        window.currentHpiData = hpiResult.data.answers_for_narrative;
                    }

                    // === NEW: AUTO REGENERATE AI NARRATIVE ===
                    if (typeof window.fetchAiHpiNarrative === 'function') {
                        window.fetchAiHpiNarrative();
                    }
                    // ==========================================

                    return true; // Success for autosave manager
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }

            } catch (error) {
                showAutosaveMessage(`Save error: ${error.message}`, 'error');
                console.error('Save error:', error);
                return false; // Failure for autosave manager
            }
        }

        /**
         * FIXED: This function must exist for autosave_manager.js
         * We leave it empty because we don't want real-time narrative updates.
         */
        function onHpiInputChange() {
            // Do nothing. This just prevents an error.
        }

        // --- NARRATIVE PANEL TOGGLE ---
        window.toggleNarrativePanel = function() {
            const panel = document.getElementById('narrative-panel');
            const mainContent = document.getElementById('hpi-main-content');
            const toggleBtn = document.getElementById('narrative-toggle-btn');
            
            if (panel.classList.contains('translate-x-full')) {
                // Show Panel
                panel.classList.remove('translate-x-full');
                panel.classList.add('translate-x-0');
                
                // Adjust Main Content
                mainContent.classList.add('lg:mr-96');
                
                // Hide Toggle Button
                toggleBtn.classList.add('hidden');
            } else {
                // Hide Panel
                panel.classList.remove('translate-x-0');
                panel.classList.add('translate-x-full');
                
                // Adjust Main Content
                mainContent.classList.remove('lg:mr-96');
                
                // Show Toggle Button
                toggleBtn.classList.remove('hidden');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            hpiForm = document.getElementById('hpiForm');
            nameHeader = document.getElementById('patient-name-header');
            autoNarrativeContent = document.getElementById('auto-narrative-content');
            copyNarrativeBtn = document.getElementById('copyNarrativeBtn');

            initAutosaveManager();

            // MODIFICATION: Added onHpiInputChange back to prevent error
            attachAutosaveListeners(hpiForm, submitHpiForm, onHpiInputChange);

            hpiForm.addEventListener('submit', (e) => e.preventDefault());

            // --- CLONE LAST VISIT LOGIC ---
            const cloneBtn = document.getElementById('cloneLastVisitBtn');
            const cloneModal = document.getElementById('cloneConfirmationModal');
            const confirmCloneBtn = document.getElementById('confirmCloneBtn');
            const cancelCloneBtn = document.getElementById('cancelCloneBtn');
            
            // Undo Logic Variables
            let previousHpiState = null;
            const undoToast = document.getElementById('undo-toast');
            const undoCloneBtn = document.getElementById('undoCloneBtn');
            const dismissUndoBtn = document.getElementById('dismissUndoBtn');

            function captureCurrentFormState() {
                const savedData = getHpiDataForSave().answers;
                const formattedState = {};
                for (const key in savedData) {
                    let val = savedData[key];
                    if (Array.isArray(val)) val = val.join(', ');
                    formattedState[key] = { answer_value: val };
                }
                return formattedState;
            }

            async function cloneLastVisitHpi() {
                if(cloneBtn) cloneBtn.disabled = true;
                showAutosaveMessage('Fetching previous HPI data...', 'info');

                try {
                    // Capture state before overwriting
                    previousHpiState = captureCurrentFormState();

                    const response = await fetch(`api/get_last_visit_hpi.php?patient_id=${window.patientId}&current_appointment_id=${window.appointmentId}`);
                    const json = await response.json();

                    if (json.success && json.data && json.data.answers_by_key) {
                        // Populate the form
                        populateForm(json.data.answers_by_key);
                        
                        // Trigger autosave to persist the cloned data
                        await submitHpiForm();
                        
                        showAutosaveMessage(`HPI cloned from ${json.data.source_date}`, 'success');

                        // Show Undo Toast
                        if (undoToast) {
                            undoToast.classList.remove('hidden');
                            undoToast.classList.add('flex');
                            // Auto-hide after 10 seconds
                            setTimeout(() => {
                                undoToast.classList.add('hidden');
                                undoToast.classList.remove('flex');
                            }, 10000);
                        }

                    } else {
                        showAutosaveMessage(json.message || 'No previous HPI data found.', 'warning');
                    }
                } catch (e) {
                    console.error("Clone error:", e);
                    showAutosaveMessage('Failed to clone HPI.', 'error');
                } finally {
                    if(cloneBtn) cloneBtn.disabled = false;
                }
            }

            if (cloneBtn) {
                cloneBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (cloneModal) {
                        cloneModal.classList.remove('hidden');
                        cloneModal.classList.add('flex');
                    }
                });
            }

            if (confirmCloneBtn) {
                confirmCloneBtn.addEventListener('click', () => {
                    if (cloneModal) {
                        cloneModal.classList.add('hidden');
                        cloneModal.classList.remove('flex');
                    }
                    cloneLastVisitHpi();
                });
            }

            if (cancelCloneBtn) {
                cancelCloneBtn.addEventListener('click', () => {
                    if (cloneModal) {
                        cloneModal.classList.add('hidden');
                        cloneModal.classList.remove('flex');
                    }
                });
            }

            // Undo Event Listeners
            if (undoCloneBtn) {
                undoCloneBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    if (previousHpiState) {
                        populateForm(previousHpiState);
                        await submitHpiForm();
                        showAutosaveMessage('Restored previous HPI state.', 'success');
                        if (undoToast) {
                            undoToast.classList.add('hidden');
                            undoToast.classList.remove('flex');
                        }
                    }
                });
            }

            if (dismissUndoBtn) {
                dismissUndoBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (undoToast) {
                        undoToast.classList.add('hidden');
                        undoToast.classList.remove('flex');
                    }
                });
            }
            // --- END CLONE LOGIC ---

            copyNarrativeBtn.addEventListener('click', () => {
                let narrativeText = '';
                autoNarrativeContent.querySelectorAll('.narrative-section').forEach(section => {
                    const header = section.querySelector('.narrative-section-header span');
                    // Find the prose content, which might be a p or ul/li
                    const contentElement = section.querySelector('.narrative-section-content > div.prose');
                    if (header && contentElement && contentElement.textContent.trim().length > 0 && !contentElement.textContent.includes('No data')) {
                        narrativeText += `--- ${header.textContent.toUpperCase()} ---\n`;
                        let cleanText = contentElement.innerText.trim().replace(/\s\s+/g, ' ');
                        narrativeText += cleanText + '\n\n';
                    }
                });


                if (narrativeText.trim().length === 0) {
                    showAutosaveMessage('No narrative content to copy.', 'error');
                    return;
                }

                const copyToClipboard = (text) => {
                    const tempInput = document.createElement('textarea');
                    tempInput.value = text;
                    tempInput.style.position = 'fixed';
                    tempInput.style.left = '-9999px';
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand('copy');
                    document.body.removeChild(tempInput);
                    return true;
                };

                if (copyToClipboard(narrativeText.trim())) {
                    showAutosaveMessage('Full Auto-Narrative copied to clipboard!', 'info');
                }
            });
            // Initial load
            fetchInitialData();
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>

    <!-- Undo Toast Notification -->
    <div id="undo-toast" class="fixed bottom-24 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-4 z-50 hidden transition-all duration-300">
        <span><i data-lucide="info" class="inline w-4 h-4 mr-2 text-blue-400"></i> HPI cloned from previous visit.</span>
        <button id="undoCloneBtn" class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-bold py-1 px-3 rounded border border-gray-600 transition">
            Undo
        </button>
        <button id="dismissUndoBtn" class="text-gray-400 hover:text-white">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <!-- Clone Confirmation Modal (Tailwind) -->
    <div id="cloneConfirmationModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

        <!-- Modal Panel -->
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full mx-4 z-10">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <!-- Warning Icon -->
                        <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Overwrite Current HPI?
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to replace the current HPI answers with data from the last visit?
                            </p>
                            <p class="text-sm text-blue-600 font-bold mt-2">
                                You can undo this action immediately after.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" id="confirmCloneBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Yes, Overwrite
                </button>
                <button type="button" id="cancelCloneBtn" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>

<?php if (isset($is_visit_signed) && $is_visit_signed): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Visit is signed. Enabling Read-Only Mode.');
        
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

        // Helper function to disable inputs
        function disableInputs(scope) {
            const formElements = scope.querySelectorAll('input, textarea, select, button');
            formElements.forEach(el => {
                // Skip navigation links/buttons
                if (el.tagName === 'A' || el.closest('nav') || el.innerText.includes('Next') || el.innerText.includes('Prev') || el.innerText.includes('Back')) {
                    return;
                }
                // Skip sidebar toggle
                if (el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn') return;
                
                // Skip copy buttons
                if (el.id === 'copyNarrativeBtn') return;

                el.disabled = true;
                el.classList.add('opacity-60', 'cursor-not-allowed');
            });
        }

        // 2. Disable Inputs (Initial Load)
        disableInputs(document);

        // 3. Watch for Dynamic Content (HPI Form Builder)
        const dynamicContainer = document.getElementById('dynamic-hpi-form-container');
        if (dynamicContainer) {
            const observer = new MutationObserver((mutations) => {
                // When nodes are added, re-run disable logic
                disableInputs(dynamicContainer);
            });
            observer.observe(dynamicContainer, { childList: true, subtree: true });
        }

        // 4. Stop Autosave
        if (window.Autosave) {
            console.log('Stopping Autosave...');
            window.Autosave.stop();
        }
        // Retry stopping autosave after a delay just in case it initializes late
        setTimeout(() => {
            if (window.Autosave) window.Autosave.stop();
        }, 2000);
        
        // 5. Hide specific action buttons
        const hideIds = ['aiRegenerateBtn', 'aiSaveBtn', 'cloneLastVisitBtn', 'expandAllBtn', 'collapseAllBtn'];
        hideIds.forEach(id => {
            const btn = document.getElementById(id);
            if(btn) btn.style.display = 'none';
        });
    });
</script>
<?php endif; ?>
<?php
require_once 'templates/footer.php';
?>