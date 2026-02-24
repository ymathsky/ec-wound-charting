<?php
// Filename: patient_profile.php

require_once 'templates/header.php';
require_once 'templates/visit_mode_modal.php';

// Get user role to conditionally show/hide elements
$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Redirect if invalid ID
if ($patient_id <= 0) {
    header("Location: view_patients.php");
    exit();
}
?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <link rel="stylesheet" href="css/patient_profile.css">
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
            max-width: 400px;
            margin: 0 auto;
        }
        .map-container img {
            width: 100%;
            height: auto;
        }
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
        .pulse-hotspot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex flex-col md:flex-row justify-between items-start md:items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100 space-y-4 md:space-y-0">
                <div class="w-full md:w-auto">
                    <h1 id="patient-name-header" class="text-2xl md:text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="user" class="w-6 h-6 md:w-7 md:h-7 mr-3 text-indigo-600 flex-shrink-0"></i>
                        <span class="truncate">Loading Profile...</span>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1 ml-9 md:ml-10">Patient details and wound management.</p>
                </div>
                <div id="quick-actions-container" class="w-full md:w-auto"></div>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>
                <div id="profile-container" class="space-y-6">
                    <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                </div>
            </main>
        </div>
    </div>

    <!-- Log Communication Modal -->
    <div id="logCommunicationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Log Communication</h3>
                <button id="closeCommModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="comm-modal-message" class="hidden p-3 my-3 rounded-md"></div>
            <form id="logCommunicationForm">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="space-y-4">
                    <div>
                        <label for="communication_type" class="form-label">Type</label>
                        <select name="communication_type" id="communication_type" required class="form-input bg-white">
                            <option value="Phone (Out)">Phone Call (Outgoing)</option>
                            <option value="Phone (In)">Phone Call (Incoming)</option>
                            <option value="Secure Message">Secure Message</option>
                            <option value="Internal Note">Internal Note</option>
                        </select>
                    </div>
                    <div>
                        <label for="parties_involved" class="form-label">Parties Involved (e.g., "Patient", "Home Health")</label>
                        <input type="text" name="parties_involved" id="parties_involved" class="form-input">
                    </div>
                    <div>
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" name="subject" id="subject" required class="form-input">
                    </div>
                    <div>
                        <label for="note_body" class="form-label">Note</label>
                        <textarea name="note_body" id="note_body" required class="form-input" rows="4"></textarea>
                    </div>
                    <div class="flex items-start space-x-3">
                        <input type="checkbox" name="follow_up_needed" id="follow_up_needed" class="mt-1 h-4 w-4 text-blue-600 rounded border-gray-300">
                        <label for="follow_up_needed" class="text-sm text-gray-700 font-medium cursor-pointer">Follow-up Required</label>
                    </div>
                    <div id="follow-up-action-wrapper" class="hidden">
                        <label for="follow_up_action" class="form-label">Follow-up Action</label>
                        <input type="text" name="follow_up_action" id="follow_up_action" class="form-input" placeholder="e.g., Call patient back in 48 hours">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelCommModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Save Log</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div id="uploadDocumentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Upload New Document</h3>
                <button id="closeUploadModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="upload-modal-message" class="hidden p-3 my-3 rounded-md"></div>
            <form id="uploadDocumentForm" enctype="multipart/form-data">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <div class="space-y-4">
                    <div>
                        <label for="document_file" class="form-label">Document File</label>
                        <input type="file" name="document_file" id="document_file" required class="form-input p-2.5">
                    </div>
                    <div>
                        <label for="document_type" class="form-label">Document Type (e.g., "Lab Result", "Referral")</label>
                        <input type="text" name="document_type" id="document_type" required class="form-input" value="General">
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelUploadModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Insurance Management Modal -->
    <div id="insuranceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 id="insuranceModalTitle" class="text-xl font-semibold text-gray-800">Add New Insurance Policy</h3>
                <button id="closeInsuranceModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="insurance-modal-message" class="hidden p-3 my-3 rounded-md"></div>
            <form id="insuranceForm">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="insurance_id" id="modal_insurance_id" value="">
                <div class="space-y-4">
                    <div class="custom-select-container">
                        <label for="provider_name_search" class="form-label">Provider Name</label>
                        <input type="text" id="provider_name_search" placeholder="Search or select a provider..." class="form-input" autocomplete="off">
                        <input type="hidden" name="provider_name" id="provider_name" required>
                        <div id="provider_list_container" class="custom-select-list hidden"></div>
                    </div>
                    <div>
                        <label for="policy_number" class="form-label">Policy Number</label>
                        <input type="text" name="policy_number" id="policy_number" required class="form-input">
                    </div>
                    <div>
                        <label for="group_number" class="form-label">Group Number</label>
                        <input type="text" name="group_number" id="group_number" class="form-input">
                    </div>
                    <div>
                        <label for="priority" class="form-label">Priority</label>
                        <select name="priority" id="priority" required class="form-input bg-white">
                            <option value="Primary">Primary</option>
                            <option value="Secondary">Secondary</option>
                            <option value="Tertiary">Tertiary</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelInsuranceModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Save Policy</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Wound Modal -->
    <div id="addWoundModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-40 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Register New Wound</h3>
                <button id="closeModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="modal-message" class="hidden p-3 my-3 rounded-md"></div>
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
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Save Wound</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Wound Map Modal -->
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

    <!-- View Latest Wound Details Modal -->
    <div id="viewWoundModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full flex flex-col max-h-[90vh]">
            <div class="p-4 md:p-6 flex-shrink-0 border-b flex justify-between items-center">
                <div>
                    <h3 id="viewWoundTitle" class="text-xl md:text-2xl font-bold text-gray-800">Wound Details</h3>
                    <p id="viewWoundSubtitle" class="text-sm text-gray-500 mt-1">Latest Assessment</p>
                </div>
                <button id="closeViewWoundModalBtn" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div class="flex-grow overflow-y-auto p-4 md:p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="bg-gray-100 rounded-lg overflow-hidden border border-gray-200 flex items-center justify-center min-h-[300px]">
                            <img id="viewWoundImage" src="" alt="Wound Image" class="max-w-full max-h-[500px] object-contain hidden">
                            <div id="viewWoundImagePlaceholder" class="text-gray-400 flex flex-col items-center">
                                <i data-lucide="image-off" class="w-12 h-12 mb-2"></i>
                                <span>No image available</span>
                            </div>
                        </div>
                        <p id="viewWoundImageDate" class="text-xs text-center text-gray-500 italic"></p>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Assessment Date</h4>
                            <p id="viewWoundDate" class="text-lg font-medium text-gray-900">--</p>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Latest Measurements</h4>
                            <div class="bg-blue-50 p-4 rounded-md border border-blue-100">
                                <p id="viewWoundDimensions" class="text-lg text-blue-900 font-semibold">--</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Clinician Assessment</h4>
                            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                                <p id="viewWoundAssessment" class="text-gray-700 whitespace-pre-wrap">No assessment text available.</p>
                            </div>
                        </div>
                        <div>
                            <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">Exudate & Periwound</h4>
                            <p class="text-sm text-gray-700"><span class="font-semibold">Exudate:</span> <span id="viewWoundExudate">--</span></p>
                            <p class="text-sm text-gray-700 mt-1"><span class="font-semibold">Periwound:</span> <span id="viewWoundPeriwound">--</span></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-6 flex-shrink-0 border-t bg-gray-50 flex justify-end">
                <button type="button" id="closeViewWoundModalBtnBottom" class="bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-50 font-semibold shadow-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- View Assessment Details Modal -->
    <div id="viewAssessmentModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full flex flex-col max-h-[90vh]">
            <div class="p-4 md:p-6 flex-shrink-0 border-b flex justify-between items-center">
                <div>
                    <h3 id="viewAssessmentTitle" class="text-xl md:text-2xl font-bold text-gray-800">Assessment Details</h3>
                    <p id="viewAssessmentSubtitle" class="text-sm text-gray-500 mt-1">Review of a specific assessment</p>
                </div>
                <button id="closeViewAssessmentModalBtn" class="text-gray-500 hover:text-gray-800 text-3xl">&times;</button>
            </div>
            <div id="viewAssessmentContent" class="flex-grow overflow-y-auto p-4 md:p-6">
                <!-- Content will be loaded dynamically -->
                <div class="flex justify-center items-center h-full">
                    <div class="spinner"></div>
                </div>
            </div>
            <div class="p-4 md:p-6 flex-shrink-0 border-t bg-gray-50 flex justify-end">
                <button type="button" id="closeViewAssessmentModalBtnBottom" class="bg-white border border-gray-300 text-gray-700 px-6 py-2 rounded-md hover:bg-gray-50 font-semibold shadow-sm">Close</button>
            </div>
        </div>
    </div>

    <!-- Wound Progress Chart Modal -->
    <div id="progressChartModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-4 md:p-6 max-w-3xl w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 id="chart-modal-title" class="text-lg md:text-xl font-semibold text-gray-800">Wound Healing Progress</h3>
                <button id="closeChartModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="chart-container" class="relative h-64 md:h-96">
                <canvas id="woundProgressChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
            <div class="text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                    <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Delete Wound?</h3>
                <p class="text-sm text-gray-500 mb-6">Are you sure you want to delete this wound? This action cannot be undone.</p>
                <div class="flex justify-center space-x-3">
                    <button id="cancelDeleteBtn" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">Cancel</button>
                    <button id="confirmDeleteBtn" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 font-semibold focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.patientId = <?php echo isset($_GET['id']) ? intval($_GET['id']) : 0; ?>;
        window.userRole = '<?php echo isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : ''; ?>';
        window.userId = <?php echo isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 'null'; ?>;

        // Toggle follow-up action field based on checkbox
        document.addEventListener('DOMContentLoaded', () => {
            const cb = document.getElementById('follow_up_needed');
            const wrapper = document.getElementById('follow-up-action-wrapper');
            if (cb && wrapper) {
                cb.addEventListener('change', () => {
                    wrapper.classList.toggle('hidden', !cb.checked);
                });
            }
        });
    </script>
    <script src="patient_profile_logic.js"></script>

<?php
require_once 'templates/footer.php';
?>