<?php
// Filename: visit_vitals.php
// UPDATED: Now uses centralized Autosave logic from ec/autosave_manager.js.
// FIX: Corrected script loading to rely on GLOBAL functions in auto_narrative.js, resolving module import SyntaxErrors.
// FIX: Ensured global data variables (currentVitalsData, etc.) are explicitly attached to the window object
//      so that auto_narrative.js can access them.
// FIX: Corrected unit conversion handling to prevent incremental data drift (double conversion bug).
//      The client now sends Imperial values (in/lbs/°F) to the API, and the API performs the conversion to Metric.

// --- FIX: Set a default timezone to ensure date() returns the correct value ---
require_once 'db_connect.php';
// -----------------------------------------------------------------------------

require_once 'templates/header.php';

// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0; // The logged-in user or assigned clinician

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- CHECK VISIT STATUS ---
require_once 'visit_status_check.php';

// Define Next step link (HPI)
$next_step_url = "visit_hpi.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";
?>

    <style>
        /* FAB Styling - MODIFIED FOR CLEANER MOBILE LOOK */
        #mobile-fab-container {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 1rem;
            /* Changed background-color to transparent or minimal to let the buttons define the look */
            background-color: transparent;
            /* Reduced shadow to focus only on the buttons/status */
            box-shadow: 0 -2px 5px rgba(0, 0, 0, 0.05);
            z-index: 50;
            display: flex; /* Added flex to structure the content */
            flex-direction: column;
            gap: 0.5rem; /* Space between status and button */
        }
        @media (min-width: 768px) {
            #mobile-fab-container {
                display: none !important;
            }
        }
        .form-button { min-height: 48px; }
        .fab-nav-button {
            /* Standard accessibility height for touch targets */
            height: 56px !important;
            padding: 0.75rem 0.5rem !important;
        }

        /* NEW FLOATING ALERT STYLES - STANDARDIZED ID */
        #autosave-message-container {
            position: fixed;
            bottom: 6rem; /* Positioned above the FAB */
            left: 50%;
            transform: translateX(-50%);
            width: 90%;
            max-width: 400px;
            z-index: 100; /* Ensure it's above everything */
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

        /* NEW: Autosave Status Indicator for FAB */
        /* Applied flex and full height styles here to ensure the internal status text fills the container */
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
            color: #1f2937; /* Dark text for contrast */
        }
        /* Ensure the mobile status container uses full width */
        #autosave-status-mobile-container {
            width: 100%;
        }

        /* NEW DESKTOP LAYOUT STYLES (To make buttons align nicely) */
        @media (min-width: 1024px) {
            /* Ensure the Vitals form buttons take up the full row of the 4-column grid */
            #vitals-form-actions {
                grid-column: span 4 / span 4;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                padding-top: 1rem; /* Ensure spacing from inputs above */
            }
            /* Style for the Autosave Status element on desktop */
            #autosave-status-desktop-container {
                flex-grow: 1; /* Allow it to take up space between buttons */
                max-width: 200px; /* Optional: limit width */
                min-width: 150px;
            }
        }
    </style>

    <!-- NEW FLOATING ALERT CONTAINER - STANDARDIZED ID -->
    <div id="autosave-message-container">
        <div id="autosave-message" class="p-3 my-3 rounded-lg text-sm text-center shadow-lg"></div>
    </div>
    
    <!-- VOICE ASSISTANT OVERLAY REMOVED -->

    <div class="flex h-screen bg-gray-100">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- MODIFIED HEADER FOR MOBILE - HIDES ACTION BUTTONS ON SMALL SCREENS -->
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md flex-shrink-0">
                <div class="flex items-center">
                    <!-- Mobile Hamburger Menu Button (MD:HIDDEN means it shows only on mobile) -->
                    <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div>
                        <h1 id="patient-name-header" class="text-2xl font-bold text-gray-800">Loading Patient...</h1>
                        <p id="patient-dob-subheader" class="text-sm text-gray-600">Step 1 of 5: Record Vitals (Autosaved)</p>
                    </div>
                </div>
                <!-- HIDDEN ON MOBILE: Navigation buttons removed to clean up desktop bar -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Navigation buttons are now removed from the top bar for a cleaner desktop look. -->
                </div>
            </header>

            <!-- VISIT SUBMENU: WRAPPED IN STICKY CONTAINER FOR MOBILE VISIBILITY -->
            <div class="sticky top-0 z-30">
                <?php require_once 'templates/visit_submenu.php'; ?>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="flex flex-col lg:flex-row gap-6 h-full">
                    <!-- Left Column: Main Vitals Content -->
                    <div class="w-full lg:w-2/3">
                        <div class="bg-white rounded-lg shadow-lg p-6" id="visit-workflow-form">
                            <!-- Vitals Tab Content (Previously inline in visit_patient.php) -->
                            <div id="vitals-content" class="tab-pane active-pane">
                                <h3 class="text-xl font-semibold mb-4 text-gray-800">Record Vitals</h3>
                                
                                <!-- Smart Voice Command Section -->
                                <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 p-5 rounded-xl border border-blue-100 shadow-sm">
                                    <div class="flex flex-col items-center justify-center text-center">
                                        <label class="text-sm font-bold text-blue-900 uppercase tracking-wide mb-3">Smart Voice Entry</label>
                                        
                                        <button type="button" id="smart_mic_btn" class="group relative bg-blue-600 hover:bg-blue-700 text-white rounded-full p-5 shadow-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                                            <!-- Mic Icon -->
                                            <svg class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                            </svg>
                                        </button>

                                        <p class="mt-3 text-sm font-medium text-gray-600">Tap and say: <span class="text-blue-700 italic">"BP 120/80, Pulse 72, Temp 98.6"</span></p>

                                        <!-- Transcript / Manual Input -->
                                        <div class="w-full max-w-lg mt-4 relative">
                                            <input type="text" id="smart_command_input" class="w-full text-center border-gray-300 rounded-full shadow-sm focus:ring-blue-500 focus:border-blue-500 py-2 px-4 text-sm bg-white" placeholder="Transcript will appear here...">
                                            <button type="button" id="execute_command_btn" class="absolute right-1 top-1 bottom-1 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-full px-3 text-xs font-bold transition">Apply</button>
                                        </div>
                                        <div id="command_feedback" class="hidden mt-2 font-medium text-sm"></div>
                                    </div>
                                </div>

                                <!-- REMOVED the old vitals-message div from here -->
                                <form id="vitalsForm" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                                    <!-- NEW: Hidden input for appointment ID -->
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                                    <!-- REMOVED PHP DATE. JS will insert the current date/time on submit. -->
                                    <input type="hidden" id="visit_date_input" name="visit_date" value="">
                                    <div>
                                        <!-- UPDATED: Name to reflect imperial unit used in form -->
                                        <label for="height_in" class="form-label">Height (in)</label>
                                        <!-- UPDATED: ID/Name to height_in -->
                                        <input type="number" step="0.1" id="height_in" name="height_in" class="form-input" autofocus>
                                    </div>
                                    <div>
                                        <!-- UPDATED: Name to reflect imperial unit used in form -->
                                        <label for="weight_lbs" class="form-label">Weight (lbs)</label>
                                        <!-- UPDATED: ID/Name to weight_lbs -->
                                        <input type="number" step="0.1" id="weight_lbs" name="weight_lbs" class="form-input">
                                    </div>
                                    <div>
                                        <label for="bmi" class="form-label">BMI</label>
                                        <!-- BMI remains read-only and is calculated client-side -->
                                        <input type="text" id="bmi" name="bmi" class="form-input bg-gray-100" readonly>
                                    </div>
                                    <div>
                                        <label for="blood_pressure" class="form-label">Blood Pressure</label>
                                        <input type="text" id="blood_pressure" name="blood_pressure" class="form-input" placeholder="e.g., 120/80">
                                    </div>
                                    <div>
                                        <label for="heart_rate" class="form-label">Heart Rate (bpm)</label>
                                        <!-- Added numeric constraints -->
                                        <input type="number" id="heart_rate" name="heart_rate" class="form-input" min="1" max="300">
                                    </div>
                                    <div>
                                        <label for="respiratory_rate" class="form-label">Respiratory Rate</label>
                                        <!-- Added numeric constraints -->
                                        <input type="number" id="respiratory_rate" name="respiratory_rate" class="form-input" min="1" max="100">
                                    </div>
                                    <div>
                                        <!-- UPDATED: Name to reflect imperial unit used in form -->
                                        <label for="temperature_f" class="form-label">Temperature (°F)</label>
                                        <!-- UPDATED: ID/Name to temperature_f -->
                                        <input type="number" step="0.1" id="temperature_f" name="temperature_f" class="form-input" min="90" max="110">
                                    </div>
                                    <div>
                                        <label for="oxygen_saturation" class="form-label">Oxygen Saturation (%)</label>
                                        <!-- Added numeric constraints -->
                                        <input type="number" id="oxygen_saturation" name="oxygen_saturation" class="form-input" min="0" max="100">
                                    </div>

                                    <!-- NEW DESKTOP ACTION ROW (lg:grid-column-span-4 ensures it spans all four columns on large screens) -->
                                    <div id="vitals-form-actions" class="sm:col-span-2 lg:col-span-4 flex justify-between gap-4">

                                        <!-- 1. Copy Vitals Narrative Button (Left aligned) -->
                                        <div class="flex gap-2">
                                            <button type="button" id="copyVitalsBtn" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center form-button h-12">
                                                Copy Vitals Narrative
                                            </button>
                                            <button type="button" id="startVoiceBtn" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center form-button h-12">
                                                <i data-lucide="mic" class="w-5 h-5 mr-2"></i> Voice Mode
                                            </button>
                                        </div>

                                        <!-- 2. Autosave Status Indicator (Center aligned, hidden on mobile) -->
                                        <div id="autosave-status-desktop-container" class="h-12 hidden lg:flex items-center justify-center text-sm font-bold">
                                            <div id="autosave-status-desktop" class="h-full w-full flex items-center justify-center text-sm font-bold bg-green-500 text-white rounded-md transition">
                                                <i data-lucide="check" class="w-5 h-5 mr-1"></i> Autosaved
                                            </div>
                                        </div>

                                        <!-- 3. Next Step Button (Right aligned, hidden on mobile) -->
                                        <a href="<?php echo $next_step_url; ?>"
                                           class="h-12 bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center justify-center text-sm hidden lg:flex min-w-[150px]">
                                            Next: HPI &rarr;
                                        </a>
                                    </div>

                                </form>

                                <!-- Vitals History Section -->
                                <div class="mt-8 pt-6 border-t border-gray-200">
                                    <h3 class="text-xl font-semibold mb-4 text-gray-800">Vitals History (All Visits)</h3>
                                    <div id="vitals-history-container" class="overflow-x-auto">
                                        <p class="text-center text-gray-500 py-8">Loading vitals history...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Auto-Narrative Panel -->
                    <div class="w-full lg:w-1/3">
                        <div class="bg-white rounded-lg shadow-lg p-4 sticky top-0">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h2 class="text-xl font-bold text-gray-800">Auto-Narrative</h2>
                                <button id="copyNarrativeBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 rounded-md text-sm flex items-center transition form-button h-10">
                                    <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                    Edit & Copy
                                </button>
                            </div>

                            <!-- Toggle Checkboxes -->
                            <div id="narrative-toggle-group" class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-700 mb-4 border-b pb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-hpi" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600" checked>
                                    <span class="ml-2">HPI</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-vitals" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600" checked>
                                    <span class="ml-2">Vitals</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-wound" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600" checked>
                                    <span class="ml-2">Wound</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-notes" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600">
                                    <span class="ml-2">Notes</span>
                                </label>
                                <!-- Note: 'Graft' toggle included for future functionality based on user's image -->
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-graft" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600">
                                    <span class="ml-2">Graft</span>
                                </label>
                            </div>

                            <!-- Narrative Output Area -->
                            <div id="auto-narrative-content" class="text-sm space-y-4">
                                <p class="text-gray-500 text-center py-6">Load patient data to generate narrative...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- FLOATING ACTION BUTTON (FAB) CONTAINER -->
    <div id="fab-menu" class="fixed bottom-6 right-6 z-50 flex flex-col-reverse items-center gap-3">
        <!-- Main Toggle Button -->
        <button id="fab-toggle" class="w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-transform transform hover:scale-110 focus:outline-none">
            <svg id="fab-icon-menu" class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
            <svg id="fab-icon-close" class="w-8 h-8 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <!-- Action Buttons (Hidden by default) -->
        <div id="fab-actions" class="flex flex-col-reverse gap-3 hidden">
            
            <!-- Save (Submits the form) -->
            <button id="fab-save" class="w-12 h-12 bg-green-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-green-700 transition tooltip-left relative" data-tooltip="Save Vitals">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
            </button>

            <!-- Voice -->
            <button id="fab-voice" class="w-12 h-12 bg-blue-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-blue-700 transition tooltip-left relative" data-tooltip="Voice Command">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>
            </button>

            <!-- Scroll Top -->
            <button id="fab-top" class="w-12 h-12 bg-gray-500 text-white rounded-full shadow-md flex items-center justify-center hover:bg-gray-600 transition tooltip-left relative" data-tooltip="Scroll to Top">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
            </button>
        </div>
    </div>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- FIX: Load auto_narrative.js as a standard script to expose global functions -->
    <script src="auto_narrative.js"></script>
    <script src="js/smart_command_logic.js"></script>
    <!-- Autosave Manager must remain a module to encapsulate its state -->
    <script type="module" src="autosave_manager.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Smart Command Parser is now auto-initialized globally
            // new SmartCommandParser('vitals');

            // FAB Logic
            const fabToggle = document.getElementById('fab-toggle');
            const fabActions = document.getElementById('fab-actions');
            const fabIconMenu = document.getElementById('fab-icon-menu');
            const fabIconClose = document.getElementById('fab-icon-close');

            if (fabToggle && fabActions) {
                fabToggle.addEventListener('click', () => {
                    fabActions.classList.toggle('hidden');
                    fabIconMenu.classList.toggle('hidden');
                    fabIconClose.classList.toggle('hidden');
                });
            }

            // Voice FAB -> Trigger existing mic button
            document.getElementById('fab-voice')?.addEventListener('click', () => {
                document.getElementById('smart_mic_btn')?.click();
                // Close menu after click
                if (fabToggle) fabToggle.click();
            });

            // Save FAB -> Trigger autosave or form submit
            document.getElementById('fab-save')?.addEventListener('click', () => {
                // Trigger autosave if available
                if (window.Autosave && window.Autosave.save) {
                    window.Autosave.save();
                    // Show feedback
                    const status = document.getElementById('autosave-status-desktop');
                    if (status) {
                        const originalText = status.innerHTML;
                        status.innerHTML = '<i data-lucide="check" class="w-5 h-5 mr-1"></i> Saved!';
                        status.classList.add('bg-green-600');
                        setTimeout(() => {
                            status.innerHTML = originalText;
                            status.classList.remove('bg-green-600');
                        }, 2000);
                    }
                } else {
                    // Fallback to form submit if needed, but this page seems to rely on autosave
                    // Or maybe just show a "Saved" toast
                    alert("Vitals are autosaved.");
                }
                if (fabToggle) fabToggle.click();
            });

            // Scroll Top
            document.getElementById('fab-top')?.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
                if (fabToggle) fabToggle.click();
            });
        });
    </script>

    <script type="module">
        import { initAutosaveManager, attachAutosaveListeners, showAutosaveMessage } from './autosave_manager.js';
        // REMOVED: import { updateAutoNarrative, generateVitalsNarrativeSentence } from './auto_narrative.js';
        // These functions are now accessed globally (implicitly on window)

        // FIX: Declare the shared data variables explicitly on the window object
        // so they are visible to the globally loaded auto_narrative.js script.
        window.currentVitalsData = {};
        window.currentHpiData = {};
        window.globalWoundsData = {};
        window.quillEditors = {};

        // --- GLOBAL VARIABLES (Accessible to all functions) ---
        const patientId = <?php echo $patient_id; ?>;
        const appointmentId = <?php echo $appointment_id; ?>;

        // --- Unit Conversion Constants & Helpers (Metric DB -> Imperial UI) ---
        const KG_TO_LBS = 2.20462;
        const C_TO_F_MULTIPLIER = 9 / 5;
        const C_TO_F_OFFSET = 32;
        const CM_TO_INCH_FACTOR = 0.393701; // 1 / 2.54

        function kgToLbs(kg) {
            if (!kg) return null;
            return parseFloat(kg) * KG_TO_LBS;
        }
        function cToF(c) {
            if (!c) return null;
            return (parseFloat(c) * C_TO_F_MULTIPLIER) + C_TO_F_OFFSET;
        }
        function cmToInches(cm) {
            if (!cm) return null;
            return parseFloat(cm) * CM_TO_INCH_FACTOR;
        }

        // Helper to format Date object into YYYY-MM-DD format for the API
        function formatDateToApi(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        // --- DOM Element References (Initialized later in DOMContentLoaded) ---
        let vitalsForm, heightInput, weightInput, bmiInput, bloodPressureInput, tempInput, vitalsHistoryContainer, nameHeader;


        // ====================================================================
        // === UTILITY FUNCTIONS ==============================================
        // ====================================================================

        /**
         * Cross-browser function to copy text to the clipboard.
         */
        function copyToClipboard(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                document.body.removeChild(textArea);
                return successful;
            } catch (err) {
                console.error('Fallback: Oops, unable to copy', err);
                document.body.removeChild(textArea);
                return false;
            }
        }

        /**
         * Cleans the Blood Pressure input.
         */
        function filterBloodPressure() {
            let value = bloodPressureInput.value;
            let cursor = bloodPressureInput.selectionStart;

            let cleanValue = value.replace(/[^0-9/]/g, '');
            let parts = cleanValue.split('/');

            parts[0] = parts[0].substring(0, 3);

            if (parts.length === 1 && parts[0].length > 3) {
                parts[0] = parts[0].substring(0, 3);
                cleanValue = parts[0] + '/' + parts[0].substring(3);
                parts = cleanValue.split('/');
            }

            if (parts.length > 1) {
                parts[1] = parts[1].substring(0, 3);
            }

            cleanValue = parts.join('/');
            bloodPressureInput.value = cleanValue;

            const diff = value.length - cleanValue.length;
            bloodPressureInput.selectionStart = cursor - diff;
            bloodPressureInput.selectionEnd = cursor - diff;
        }

        /**
         * Extracts Vitals Narrative (now relies on global function).
         */
        function getVitalsNarrative() {
            // FIX: Call the globally available function directly, passing global data
            if (typeof window.generateVitalsNarrativeSentence === 'function') {
                const narrativeHtml = window.generateVitalsNarrativeSentence(window.currentVitalsData);
                return narrativeHtml.replace(/<[^>]*>?/gm, '').trim().replace(/\s\s+/g, ' ');
            }
            return "Vitals narrative generation function is unavailable.";
        }

        // ====================================================================
        // === CORE APPLICATION FUNCTIONS (Relies on global references) =======
        // ====================================================================

        /**
         * Core submission logic for Vitals, executed by autosave.
         */
        async function submitVitalsForm(showNotification) {
            if (vitalsForm === null) return false;

            const visitDateInput = document.getElementById('visit_date_input');
            const now = new Date();
            visitDateInput.value = formatDateToApi(now);

            const formData = new FormData(vitalsForm);
            const data = Object.fromEntries(formData.entries());
            data.bmi = bmiInput.value;

            try {
                const response = await fetch('api/create_vitals.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();

                if (!response.ok) {
                    showAutosaveMessage(`Autosave Error: ${result.message || 'Server error occurred.'}`, 'error');
                    return false;
                }

                // Call the globally accessible fetch functions
                await fetchLatestVitals(); // This calls updateAutoNarrative
                fetchVitalsHistory();

                return true;

            } catch (error) {
                console.error(`Autosave failed: ${error.message}`);
                showAutosaveMessage(`Autosave Error: ${error.message}`, 'error');
                return false;
            }
        }

        /**
         * Calculates BMI. This is the onInputChangeCallback.
         */
        function calculateBMI() {
            const heightInches = parseFloat(heightInput.value);
            const weightLbs = parseFloat(weightInput.value);

            if (heightInches > 0 && weightLbs > 0) {
                // Calculation: (weight_lbs / (height_in * height_in)) * 703
                const bmi = (weightLbs / (heightInches * heightInches)) * 703;
                bmiInput.value = bmi.toFixed(1);
            } else {
                bmiInput.value = '';
            }
            // Update global data and narrative immediately after BMI calculation
            window.currentVitalsData = {
                height_cm: heightInput.value ? (parseFloat(heightInput.value) / CM_TO_INCH_FACTOR) : null,
                weight_kg: weightInput.value ? (parseFloat(weightInput.value) / KG_TO_LBS) : null,
                bmi: bmiInput.value,
                // These inputs don't have conversion functions, so we can use their imperial values temporarily
                blood_pressure: bloodPressureInput.value,
                heart_rate: document.getElementById('heart_rate').value,
                respiratory_rate: document.getElementById('respiratory_rate').value,
                temperature_celsius: tempInput.value ? (parseFloat(tempInput.value) - 32) * 5/9 : null,
                oxygen_saturation: document.getElementById('oxygen_saturation').value
            };
            if (typeof window.updateAutoNarrative === 'function') {
                window.updateAutoNarrative();
            }
        }

        /**
         * Fetches and displays the latest Vitals data FOR THE CURRENT APPOINTMENT.
         */
        async function fetchLatestVitals() {
            try {
                const response = await fetch(`api/get_vitals.php?patient_id=${patientId}&appointment_id=${appointmentId}`);
                if (!response.ok) return;
                const vitals = await response.json();

                if (vitals) {
                    // FIX: Update window property
                    window.currentVitalsData = vitals;
                    vitalsForm.reset();

                    // --- UNIT CONVERSION (Metric from DB -> Imperial for UI) ---
                    const heightInches = vitals.height_cm ? cmToInches(vitals.height_cm) : null;
                    const weightLbs = vitals.weight_kg ? kgToLbs(vitals.weight_kg) : null;
                    const tempF = vitals.temperature_celsius ? cToF(vitals.temperature_celsius) : null;

                    // Populate form fields using the CONVERTED Imperial values
                    document.getElementById('height_in').value = heightInches !== null ? heightInches.toFixed(1) : '';
                    document.getElementById('weight_lbs').value = weightLbs !== null ? weightLbs.toFixed(1) : '';
                    document.getElementById('temperature_f').value = tempF !== null ? tempF.toFixed(1) : '';

                    // Populate non-converted fields
                    document.getElementById('blood_pressure').value = vitals.blood_pressure || '';
                    document.getElementById('heart_rate').value = vitals.heart_rate || '';
                    document.getElementById('respiratory_rate').value = vitals.respiratory_rate || '';
                    document.getElementById('oxygen_saturation').value = vitals.oxygen_saturation || '';

                    calculateBMI();
                } else {
                    vitalsForm.reset();
                    bmiInput.value = '';
                    window.currentVitalsData = {}; // FIX: Update window property
                }
                // FIX: Call the globally available function directly
                if (typeof window.updateAutoNarrative === 'function') {
                    window.updateAutoNarrative();
                }
            } catch (error) {
                console.error("Could not fetch latest vitals:", error);
            }
        }

        /**
         * Fetches and renders Vitals History (Table).
         */
        async function fetchVitalsHistory() {
            if (!vitalsHistoryContainer) return;
            vitalsHistoryContainer.innerHTML = '<p class="text-center text-gray-500 py-8">Loading vitals history...</p>';
            try {
                const response = await fetch(`api/get_vitals.php?patient_id=${patientId}&history=true`);
                if (!response.ok) throw new Error('Failed to fetch vitals history.');

                let vitalsHistory = await response.json();
                if (vitalsHistory && !Array.isArray(vitalsHistory)) { vitalsHistory = [vitalsHistory]; }

                renderVitalsHistory(vitalsHistory || []);
            } catch (error) {
                vitalsHistoryContainer.innerHTML = `<p class="text-red-500 py-8">Error loading vitals history: ${error.message}</p>`;
            }
        }

        /**
         * Renders the history table (Assuming full implementation exists in the original codebase)
         */
        function renderVitalsHistory(vitals) {
            const validVitals = vitals.filter(v => v);
            if (!validVitals || validVitals.length === 0) {
                vitalsHistoryContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No past vitals records found for this patient.</p>';
                return;
            }

            const tableRows = validVitals.map(v => {
                const heightInches = cmToInches(v.height_cm);
                const weightLbs = kgToLbs(v.weight_kg);
                const tempF = cToF(v.temperature_celsius);

                // Assuming formatDateTime function exists somewhere or using simplified string split
                const displayDateTime = v.visit_date ? v.visit_date.split(' ')[0] : '-';

                return `
                <tr class="border-b border-gray-200 hover:bg-gray-50 text-sm">
                    <td class="px-4 py-3 font-medium">${displayDateTime}</td>
                    <td class="px-4 py-3">${heightInches ? heightInches.toFixed(1) + ' in' : '-'}</td>
                    <td class="px-4 py-3">${weightLbs ? weightLbs.toFixed(1) + ' lbs' : '-'}</td>
                    <td class="px-4 py-3 font-semibold">${v.bmi || '-'}</td>
                    <td class="px-4 py-3">${v.blood_pressure || '-'}</td>
                    <td class="px-4 py-3">${v.heart_rate || '-'} bpm</td>
                    <td class="px-4 py-3">${v.respiratory_rate || '-'}</td>
                    <td class="px-4 py-3">${tempF ? tempF.toFixed(1) + ' °F' : '-'}</td>
                    <td class="px-4 py-3">${v.oxygen_saturation ? v.oxygen_saturation + ' %' : '-'}</td>
                </tr>
                `;
            }).join('');

            vitalsHistoryContainer.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Date & Time</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Height (in)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Weight (lbs)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">BMI</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">BP</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">HR</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">RR</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Temp (°F)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">O2 Sat</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>
            `;
        }


        /**
         * Initializes all data fetching and listeners.
         */
        async function fetchInitialData() {
            try {
                const patientResponse = await fetch(`api/get_patient_details.php?id=${patientId}`);
                if (!patientResponse.ok) throw new Error('Failed to fetch patient details');
                const data = await patientResponse.json();

                const patient = data.details;
                nameHeader.textContent = `${patient.first_name} ${patient.last_name}`;
                // FIX: Update window property
                window.globalWoundsData = data.wounds;

                const hpiResponse = await fetch(`api/get_hpi_data.php?appointment_id=${appointmentId}`);
                if(hpiResponse.ok) {
                    const hpiResult = await hpiResponse.json();
                    if(hpiResult.success && hpiResult.data) {
                        // FIX: Update window property
                        window.currentHpiData = hpiResult.data;
                    }
                }

                await fetchLatestVitals();

                fetchVitalsHistory();

                // FIX: Call the globally available function directly
                if (typeof window.updateAutoNarrative === 'function') {
                    window.updateAutoNarrative();
                }

            } catch (error) {
                nameHeader.textContent = 'Error Loading Patient';
                console.error("Initialization Error:", error);
            }
        }
        // ====================================================================


        document.addEventListener('DOMContentLoaded', function() {
            // --- Initialize global DOM element references ---
            vitalsForm = document.getElementById('vitalsForm');
            heightInput = document.getElementById('height_in');
            weightInput = document.getElementById('weight_lbs');
            tempInput = document.getElementById('temperature_f');
            bmiInput = document.getElementById('bmi');
            bloodPressureInput = document.getElementById('blood_pressure');
            vitalsHistoryContainer = document.getElementById('vitals-history-container');
            nameHeader = document.getElementById('patient-name-header');

            const copyVitalsBtn = document.getElementById('copyVitalsBtn');
            const copyNarrativeBtn = document.getElementById('copyNarrativeBtn');
            const autoNarrativeContent = document.getElementById('auto-narrative-content');

            // 1. INITIALIZE AUTOSAVE MANAGER
            initAutosaveManager();

            // 2. ATTACH AUTOSAVE LISTENERS
            // calculateBMI is the onInputChangeCallback.
            // filterBloodPressure is the inputFilterCallback.
            attachAutosaveListeners(vitalsForm, submitVitalsForm, calculateBMI, filterBloodPressure);


            // --- Other Initialization ---

            // Prevent default form submission; handled by custom functions
            vitalsForm.addEventListener('submit', (e) => e.preventDefault());

            // Copy Vitals Narrative
            copyVitalsBtn.addEventListener('click', () => {
                const narrative = getVitalsNarrative();
                if (copyToClipboard(narrative)) {
                    showAutosaveMessage('Vitals narrative copied to clipboard!', 'info');
                } else {
                    showAutosaveMessage('Copy function unavailable.', 'error');
                }
            });

            // Copy Full Narrative
            copyNarrativeBtn.addEventListener('click', () => {
                let narrativeText = '';
                autoNarrativeContent.querySelectorAll('.narrative-section').forEach(section => {
                    const header = section.querySelector('.narrative-section-header span').textContent;
                    const contentElement = section.querySelector('.narrative-section-content > div.prose');
                    if (contentElement && contentElement.textContent.trim().length > 0) {
                        narrativeText += `--- ${header.toUpperCase()} ---\n`;
                        let cleanText = contentElement.textContent.trim().replace(/\s\s+/g, ' ');
                        narrativeText += cleanText + '\n\n';
                    }
                });

                if (narrativeText.trim().length === 0) {
                    showAutosaveMessage('No content in the Auto-Narrative panel to copy.', 'error');
                    return;
                }
                if (copyToClipboard(narrativeText.trim())) {
                    showAutosaveMessage('Full Auto-Narrative copied to clipboard!', 'info');
                } else {
                    showAutosaveMessage('Copy function unavailable.', 'error');
                }
            });

            // Hook up the "Voice Mode" button in the form actions to the Smart Mic
            const startVoiceBtn = document.getElementById('startVoiceBtn');
            if (startVoiceBtn) {
                startVoiceBtn.addEventListener('click', () => {
                    const smartMic = document.getElementById('smart_mic_btn');
                    if (smartMic) {
                        smartMic.click();
                        smartMic.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }

            fetchInitialData();

            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>

<?php if (isset($is_visit_signed) && $is_visit_signed): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Visit is signed. Enabling Read-Only Mode.');
        
        // 1. Visual Indicator
        const mainContainer = document.querySelector('main > div'); // Target the inner container
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
            // Insert before the first child of the main flex container
            mainContainer.parentNode.insertBefore(banner, mainContainer);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // 2. Disable Inputs
        const formElements = document.querySelectorAll('input, textarea, select, button');
        formElements.forEach(el => {
            // Skip navigation links/buttons (A tags are not selected by querySelectorAll above, but buttons might be nav)
            if (el.tagName === 'A' || el.closest('nav') || el.innerText.includes('Next') || el.innerText.includes('Prev') || el.innerText.includes('Back')) {
                return;
            }
            // Skip sidebar toggle
            if (el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn') return;
            
            // Skip copy buttons as they are useful even in read-only
            if (el.id === 'copyVitalsBtn' || el.id === 'copyNarrativeBtn') return;

            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
        });

        // 3. Stop Autosave
        if (window.Autosave) {
            console.log('Stopping Autosave...');
            window.Autosave.stop();
        }
    });
</script>
<?php endif; ?>
<?php
require_once 'templates/footer.php';
?>