<?php
// Filename: visit_wounds.php

// --- FIX: Suppress non-fatal PHP errors that can break JS ---
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

require_once 'templates/header.php';
require_once 'db_connect.php';
// --- Get IDs from URL ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// --- CHECK VISIT STATUS ---
require_once 'visit_status_check.php';
?>

    <!-- CSS for searchable dropdown, wound map, and new UI enhancements -->
    <style>
        /* Style for the searchable select dropdown */
        .custom-select-container {
            position: relative;
        }
        .custom-select-list {
            position: absolute;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #D1D5DB; /* gray-300 */
            border-top: none;
            /* Needs to be higher than the modal's z-index (40) */
            z-index: 50;
            border-radius: 0 0 0.375rem 0.375rem; /* rounded-b-md */
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); /* shadow-lg */
        }
        .custom-select-item {
            padding: 0.5rem 0.75rem; /* py-2 px-3 */
            cursor: pointer;
            font-size: 0.875rem; /* text-sm */
        }
        .custom-select-item:hover {
            background-color: #F3F4F6; /* gray-100 */
        }
        .custom-select-item strong {
            font-weight: 600;
            color: #4F46E5; /* indigo-600 */
        }

        /* Wound Map Modal */
        .map-container {
            position: relative;
            max-width: 400px; /* Reduced from 600px */
            margin: 0 auto;
        }
        .map-container img {
            width: 100%;
            height: auto;
        }
        /* --- FIX: Added styles for hotspots --- */
        .map-hotspot {
            position: absolute;
            width: 16px;
            height: 16px;
            background-color: rgba(239, 68, 68, 0.7); /* red-500 with opacity */
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 50%;
            cursor: pointer;
            transform: translate(-50%, -50%); /* Center the hotspot */
            transition: all 0.2s ease;
            box-shadow: 0 0 8px rgba(239, 68, 68, 0.7);
        }
        .map-hotspot:hover {
            transform: translate(-50%, -50%) scale(1.3);
            background-color: rgba(239, 68, 68, 0.9); /* red-500 */
        }
        .map-hotspot::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #1f2937; /* gray-800 */
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.2s;
            z-index: 100;
        }
        .map-hotspot:hover::after {
            opacity: 1;
            visibility: visible;
        }

        /* Pulsing animation for hotspots */
        .pulse-hotspot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }

        /* Quick-Change Status Dropdown */
        .status-select-badge {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            /* Simple SVG arrow icon */
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236B7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            padding-right: 2rem; /* Make space for the arrow */
        }
    </style>

    <div class="flex h-screen bg-gray-100">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">

            <!-- *** THIS IS THE DESKTOP HEADER (from the original file) *** -->
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <div class="flex items-center">
                    <!-- Mobile Hamburger Menu Button -->
                    <button onclick="openSidebar()" class="md:hidden mr-4 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i data-lucide="menu" class="w-8 h-8"></i>
                    </button>
                    <div>
                        <h1 id="patient-name-header" class="text-2xl font-bold text-gray-800">Loading Patient...</h1>
                        <p id="patient-dob-subheader" class="text-sm text-gray-600">Step 4 of 5: Wound Management</p>
                    </div>
                </div>
                <div class="hidden lg:flex items-center space-x-4">
                    <a href="visit_hpi.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>&user_id=<?php echo $user_id; ?>"
                       class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md hover:bg-gray-300 transition inline-flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-1.5"></i>
                        Back: HPI
                    </a>
                    <a href="visit_diagnosis.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>&user_id=<?php echo $user_id; ?>"
                       class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition inline-flex items-center">
                        Next: Diagnosis
                        <i data-lucide="arrow-right" class="w-4 h-4 ml-1.5"></i>
                    </a>
                </div>
            </header>

            <!-- *** NEW: Include the scrolling visit sub-menu *** -->
            <!-- This will appear on both mobile and desktop -->
            <?php require_once 'templates/visit_submenu.php'; ?>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-4 sm:p-6">
                <!-- Page-level message container -->
                <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>

                <div class="flex flex-col lg:flex-row gap-6 h-full">
                    <!-- Left Column: Main Wound Content -->
                    <div class="w-full lg:w-2/3">
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4 pb-4 border-b">
                                <h3 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                                    <i data-lucide="list" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Patient Wound List
                                </h3>

                                <button id="openAddWoundModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md text-sm transition inline-flex items-center shadow-sm">
                                    <i data-lucide="plus" class="w-4 h-4 mr-1.5"></i>
                                    Add New Wound
                                </button>
                            </div>
                            <div id="wounds-list-container" class="overflow-x-auto">
                                <!-- Loading Spinner -->
                                <div class="flex justify-center items-center h-48">
                                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Auto-Narrative Panel -->
                    <div class="w-full lg:w-1/3">
                        <!-- *** MODIFICATION: Made sticky only on large screens *** -->
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
                                    <input type="checkbox" id="toggle-notes" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2">Notes</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="toggle-graft" class="narrative-toggle-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="ml-2">Graft</span>
                                </label>
                            </div>
                            <!-- Narrative Loading Spinner -->
                            <div id="narrative-spinner" class="flex justify-center items-center h-48">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                            </div>
                            <div id="auto-narrative-content" class="text-sm space-y-4" style="display: none;">
                                <p class="text-gray-500 text-center py-6">Load patient data to generate narrative...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Wound Modal -->
    <!-- *** MODIFICATION: Modal is now full-screen on mobile, modal-in-center on desktop *** -->
    <div id="addWoundModal" class="fixed inset-0 bg-black bg-opacity-50 hidden p-0 sm:p-4 sm:items-center sm:justify-center z-40">
        <div class="bg-white p-6 w-full h-full sm:h-auto sm:w-full sm:max-w-lg sm:rounded-lg sm:shadow-xl">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Register New Wound</h3>
                <button id="closeModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="modal-message" class="hidden p-3 my-3 rounded-md"></div>
            <!-- FIX: Added id="addWoundForm" -->
            <form id="addWoundForm">
                <input type="hidden" name="patient_id" id="modal_patient_id" value="<?php echo $patient_id; ?>">
                <div class="space-y-4">

                    <div>
                        <label for="location_search" class="form-label">Wound Location</label>
                        <div class="flex items-center space-x-2">
                            <div class="custom-select-container w-full">
                                <input type="text" id="location_search" placeholder="Type or select a location..." class="form-input w-full" autocomplete="off">
                                <input type="hidden" name="location" id="location" required>
                                <div id="location_list_container" class="custom-select-list hidden"></div>
                            </div>
                            <!-- *** FIX 1: Reverted to icon as requested.
                                FIX 2: Changed 'teal' to 'blue' as teal is not in default Tailwind.
                            *** -->
                            <button type="button" id="openWoundMapBtn" class="bg-blue-500 hover:bg-blue-600 text-white p-2 rounded-md text-sm flex-shrink-0" title="Select from Map">
                                <i data-lucide="map" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="wound_type_search" class="form-label">Wound Type</label>
                        <div class="custom-select-container">
                            <input type="text" id="wound_type_search" placeholder="Type or select a wound type..." class="form-input" autocomplete="off">
                            <input type="hidden" name="wound_type" id="wound_type" required>
                            <div id="wound_type_list_container" class="custom-select-list hidden"></div>
                        </div>
                    </div>

                    <div>
                        <label for="date_onset" class="form-label">Date of Onset</label>
                        <input type="date" name="date_onset" id="date_onset" required class="form-input">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold inline-flex items-center shadow-sm">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                        Save Wound
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Wound Map Modal -->
    <!-- *** MODIFICATION: Modal is now full-screen on mobile, modal-in-center on desktop *** -->
    <div id="woundMapModal" class="fixed inset-0 bg-black bg-opacity-60 hidden p-0 sm:p-4 sm:items-center sm:justify-center z-50">
        <div class="bg-white w-full h-full flex flex-col sm:h-auto sm:max-h-[85vh] sm:max-w-2xl sm:rounded-lg sm:shadow-xl">
            <div class="p-6 flex-shrink-0">
                <div class="flex justify-between items-center border-b pb-3">
                    <h3 class="text-xl font-semibold text-gray-800 inline-flex items-center">
                        <i data-lucide="map" class="w-5 h-5 mr-2 text-indigo-600"></i>
                        Select Wound Location
                    </h3>
                    <button id="closeWoundMapBtn" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
                </div>
                <!-- Tabs -->
                <div class="border-b border-gray-200 mt-4">
                    <nav class="-mb-px flex space-x-4" aria-label="Tabs">
                        <button class="map-tab bg-indigo-600 text-white whitespace-nowrap py-2 px-4 border-b-2 font-medium text-sm rounded-t-md" data-target="anterior-map">
                            Anterior
                        </button>
                        <button class="map-tab bg-white text-gray-600 hover:text-gray-800 whitespace-nowrap py-2 px-4 border-b-2 font-medium text-sm rounded-t-md" data-target="posterior-map">
                            Posterior
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Map Panes -->
            <div class="flex-grow overflow-y-auto px-6 pb-6">
                <!-- Anterior Map Pane -->
                <div id="anterior-map" class="map-pane">
                    <div class="map-container">
                        <img src="https://placehold.co/400x600/f3f4f6/ccc?text=Anterior+View" alt="Anterior body map">
                        <!-- Anterior Hotspots -->
                        <div class="map-hotspot pulse-hotspot" style="top: 8%; left: 50%;" data-location="Head/Scalp" data-tooltip="Head/Scalp"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 11%; left: 50%;" data-location="Face" data-tooltip="Face"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 15%; left: 50%;" data-location="Neck" data-tooltip="Neck"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 22%; left: 40%;" data-location="Chest (Left)" data-tooltip="Chest (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 22%; left: 60%;" data-location="Chest (Right)" data-tooltip="Chest (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 28%; left: 30%;" data-location="Arm (Left Upper)" data-tooltip="Arm (Left Upper)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 28%; left: 70%;" data-location="Arm (Right Upper)" data-tooltip="Arm (Right Upper)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 30%; left: 42%;" data-location="Abdomen (Left Upper Quadrant)" data-tooltip="Abdomen (LUQ)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 36%; left: 42%;" data-location="Abdomen (Left Lower Quadrant)" data-tooltip="Abdomen (LLQ)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 45%; left: 44%;" data-location="Groin (Left)" data-tooltip="Groin (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 45%; left: 56%;" data-location="Groin (Right)" data-tooltip="Groin (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 55%; left: 40%;" data-location="Thigh (Left Anterior)" data-tooltip="Thigh (Left Anterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 55%; left: 60%;" data-location="Thigh (Right Anterior)" data-tooltip="Thigh (Right Anterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 68%; left: 40%;" data-location="Knee (Left)" data-tooltip="Knee (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 68%; left: 60%;" data-location="Knee (Right)" data-tooltip="Knee (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 78%; left: 40%;" data-location="Shin (Left)" data-tooltip="Shin (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 78%; left: 60%;" data-location="Shin (Right)" data-tooltip="Shin (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 88%; left: 40%;" data-location="Ankle (Left)" data-tooltip="Ankle (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 88%; left: 60%;" data-location="Ankle (Right)" data-tooltip="Ankle (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 95%; left: 38%;" data-location="Foot (Left Dorsum)" data-tooltip="Foot (Left Dorsum)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 95%; left: 62%;" data-location="Foot (Right Dorsum)" data-tooltip="Foot (Right Dorsum)"></div>
                    </div>
                </div>

                <!-- Posterior Map Pane -->
                <div id="posterior-map" class="map-pane hidden">
                    <div class="map-container">
                        <img src="https://placehold.co/400x600/f3f4f6/ccc?text=Posterior+View" alt="Posterior body map">
                        <!-- Posterior Hotspots -->
                        <div class="map-hotspot pulse-hotspot" style="top: 8%; left: 50%;" data-location="Head/Scalp (Posterior)" data-tooltip="Head/Scalp (Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 15%; left: 50%;" data-location="Neck (Posterior)" data-tooltip="Neck (Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 20%; left: 35%;" data-location="Shoulder (Left)" data-tooltip="Shoulder (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 20%; left: 65%;" data-location="Shoulder (Right)" data-tooltip="Shoulder (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 25%; left: 50%;" data-location="Back (Upper)" data-tooltip="Back (Upper)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 30%; left: 28%;" data-location="Elbow (Left)" data-tooltip="Elbow (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 30%; left: 72%;" data-location="Elbow (Right)" data-tooltip="Elbow (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 33%; left: 50%;" data-location="Back (Mid)" data-tooltip="Back (Mid)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 40%; left: 50%;" data-location="Back (Lower)" data-tooltip="Back (Lower)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 46%; left: 50%;" data-location="Coccyx/Sacrum" data-tooltip="Coccyx/Sacrum"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 50%; left: 40%;" data-location="Buttock (Left)" data-tooltip="Buttock (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 50%; left: 60%;" data-location="Buttock (Right)" data-tooltip="Buttock (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 60%; left: 40%;" data-location="Thigh (Left Posterior)" data-tooltip="Thigh (Left Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 60%; left: 60%;" data-location="Thigh (Right Posterior)" data-tooltip="Thigh (Right Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 70%; left: 40%;" data-location="Knee (Left Posterior)" data-tooltip="Knee (Left Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 70%; left: 60%;" data-location="Knee (Right Posterior)" data-tooltip="Knee (Right Posterior)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 80%; left: 40%;" data-location="Calf (Left)" data-tooltip="Calf (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 80%; left: 60%;" data-location="Calf (Right)" data-tooltip="Calf (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 90%; left: 40%;" data-location="Heel (Left)" data-tooltip="Heel (Left)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 90%; left: 60%;" data-location="Heel (Right)" data-tooltip="Heel (Right)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 96%; left: 38%;" data-location="Foot (Left Plantar)" data-tooltip="Foot (Left Plantar)"></div>
                        <div class="map-hotspot pulse-hotspot" style="top: 96%; left: 62%;" data-location="Foot (Right Plantar)" data-tooltip="Foot (Right Plantar)"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <script>
        // Define global objects required by auto_narrative.js
        window.currentVitalsData = {};
        window.currentHpiData = {};
        window.currentWoundsData = [];
        window.currentMedicationsData = []; // NEW: For medication narrative

        function copyToClipboard(text) {
            const tempInput = document.createElement('textarea');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }

        function generateHpiNarrativeSentence(hpiData) { return "HPI data generation deferred to auto_narrative.js"; }
        function generateWoundNarrative(woundsData) { return "Wound narrative generation deferred to auto_narrative.js"; }
    </script>

    <script src="auto_narrative.js"></script>

    <!-- Pass PHP data to our external JS file -->
    <script>
        // *** CRITICAL FIX: Use json_encode to prevent JS syntax errors ***
        // This stops the "Unexpected token ';'" error which breaks all JS.
        window.visitData = {
            patientId: <?php echo json_encode($patient_id, JSON_HEX_TAG); ?>,
            appointmentId: <?php echo json_encode($appointment_id, JSON_HEX_TAG); ?>,
            userId: <?php echo json_encode($user_id, JSON_HEX_TAG); ?>
        };
    </script>

    <!-- Load the main logic file for this page -->
    <!-- FIX: Added 'defer' to ensure JS runs after HTML is parsed -->
    <script src="visit_wounds.js" defer></script>

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

        // 2. Hide "Add Wound" button immediately
        const addBtn = document.getElementById('openAddWoundModalBtn');
        if (addBtn) addBtn.style.display = 'none';

        // 3. Function to lock an element
        function lockElement(el) {
            // Skip navigation and sidebar
            if (el.closest('nav') || el.closest('#sidebar') || el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn') return;
            
            // Skip "Back" and "Next" buttons
            if (el.innerText.includes('Back') || el.innerText.includes('Next')) return;

            // Skip "Copy" buttons
            if (el.id === 'copyNarrativeBtn') return;

            // Handle "Assess" buttons
            if (el.tagName === 'A') {
                // Check for "Assess" (Create new) vs "View/Edit" (Existing)
                if (el.innerText.includes('Assess')) {
                    // No assessment exists. Since visit is signed, we cannot create one.
                    el.style.pointerEvents = 'none';
                    el.style.opacity = '0.5';
                    el.style.cursor = 'not-allowed';
                    el.title = 'Visit is signed. Cannot add new assessment.';
                    return;
                }
                if (el.innerText.includes('View/Edit')) {
                    // Assessment exists. Allow viewing, but update label.
                    el.innerHTML = el.innerHTML.replace('View/Edit', 'View');
                    return;
                }
            }
            
            // Disable inputs, selects, textareas, buttons
            if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(el.tagName)) {
                el.disabled = true;
                el.classList.add('opacity-60', 'cursor-not-allowed');
            }
            
            // Disable specific links that act as buttons (like "Delete" or "Edit")
            // In visit_wounds.js, there isn't a delete button visible in the list usually, but if there is:
            if (el.tagName === 'A' && (el.classList.contains('text-red-600') || el.innerText.includes('Delete'))) {
                el.style.pointerEvents = 'none';
                el.style.opacity = '0.5';
            }
        }

        // 4. Initial Lock
        document.querySelectorAll('*').forEach(lockElement);

        // 5. MutationObserver for dynamic content (Wound List)
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) { // Element node
                        lockElement(node);
                        node.querySelectorAll('*').forEach(lockElement);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
</script>
<?php endif; ?>
<?php
require_once 'templates/footer.php';
?>