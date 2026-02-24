<?php
session_start();
// Filename: wound_assessment.php
// UPDATED: Added "Generate LMN" button in header.

require_once 'templates/header.php';

$wound_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Get clinician name for attestation
$clinician_name = ($_SESSION['first_name'] ?? 'Clinician') . ' ' . ($_SESSION['last_name'] ?? 'Name');
$clinician_id = $_SESSION['user_id'] ?? 0;

// --- CHECK VISIT STATUS ---
require_once 'db_connect.php';
require_once 'visit_status_check.php';

if ($wound_id <= 0) {
    echo "<div class='p-8'>Invalid Wound ID.</div>";
    require_once 'templates/footer.php';
    exit();
}
?>
    <!-- Fabric.js library for canvas interaction -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.1/fabric.min.js"></script>

    <style>
        /* Cockpit Layout Styles */
        .cockpit-container {
            height: 100%;
            overflow: hidden;
        }
        .scroll-panel {
            height: 100%;
            overflow-y: auto;
            scrollbar-width: thin;
        }
        .scroll-panel::-webkit-scrollbar {
            width: 6px;
        }
        .scroll-panel::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .scroll-panel::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 3px;
        }
        
        /* Custom Scrollbar for other elements */
        .custom-scrollbar {
            scrollbar-width: thin;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 3px;
        }

        /* Smart Command Highlight Animation */
        @keyframes highlightPulse {
            0% { background-color: #fef3c7; transform: scale(1.02); } /* yellow-100 */
            50% { background-color: #fde68a; } /* yellow-200 */
            100% { background-color: transparent; transform: scale(1); }
        }
        .smart-update-flash {
            animation: highlightPulse 1.5s ease-out;
        }
        
        /* Button Group / Chips */
        .btn-group-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        /* Tooltip for FAB */
        .tooltip-left::before {
            content: attr(data-tooltip);
            position: absolute;
            right: 110%;
            top: 50%;
            transform: translateY(-50%);
            background-color: #1f2937;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .tooltip-left:hover::before {
            opacity: 1;
        }
        .btn-option {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s;
            flex: 1;
            text-align: center;
            min-width: 60px;
        }
        .btn-option:hover {
            background-color: #f3f4f6;
        }
        .btn-option.active {
            background-color: #4f46e5; /* Indigo 600 */
            color: white;
            border-color: #4f46e5;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        
        /* Section Headers */
        .section-header {
            display: flex;
            align-items: center;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
            color: #111827;
            font-weight: 700;
            font-size: 1.125rem;
            margin-top: 2rem;
        }
        .section-header:first-of-type {
            margin-top: 0;
        }
        .section-icon {
            margin-right: 0.5rem;
            color: #4f46e5;
        }

        /* Existing Styles */
        .image-gallery-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
        }
        .image-gallery-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        .modal {
            transition: opacity 0.25s ease;
        }
        .canvas-container {
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            cursor: crosshair !important;
        }
        .ai-spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }
        .spinner-small {
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-top: 2px solid #4f46e5;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .assessment-disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        #aiMeasurementCanvas {
            max-width: 100%;
            height: auto;
            border: 1px solid #ccc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        select[multiple] {
            height: auto;
            padding-right: 1rem;
        }
        #viewAssessmentModal .detail-label {
            font-weight: 600;
            color: #4b5563;
        }
        #viewAssessmentModal .detail-value {
            color: #1f2937;
            margin-left: 0.5rem;
        }
        
        /* Link disabled state styles */
        a.disabled {
            pointer-events: none;
            opacity: 0.6;
            background-color: #9ca3af; /* gray-400 */
            cursor: not-allowed;
        }

        /* Floating Alert Styles */
        #floating-alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none; /* Allow clicks through the container */
        }
        
        .floating-alert {
            min-width: 300px;
            max-width: 450px;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: flex-start;
            animation: slideInRight 0.3s ease-out forwards;
            pointer-events: auto; /* Re-enable clicks on alerts */
            opacity: 0;
            transform: translateX(100%);
            border-left: 4px solid transparent;
        }
        
        .floating-alert.success { background-color: #ecfdf5; border-left-color: #10b981; color: #065f46; }
        .floating-alert.error { background-color: #fef2f2; border-left-color: #ef4444; color: #991b1b; }
        .floating-alert.info { background-color: #eff6ff; border-left-color: #3b82f6; color: #1e40af; }
        
        .floating-alert.fade-out {
            animation: fadeOutRight 0.3s ease-in forwards;
        }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes fadeOutRight {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(100%); }
        }
    </style>

    <div id="floating-alert-container"></div>

    <!-- VOICE ASSISTANT OVERLAY -->
    <div id="voice-status-container" class="fixed top-24 left-1/2 transform -translate-x-1/2 bg-white border border-gray-200 shadow-2xl rounded-xl p-4 z-50 hidden w-96 flex flex-col items-center gap-3 transition-all">
        <div id="voice-drag-handle" class="flex items-center justify-between w-full border-b pb-2 cursor-move select-none" title="Drag to move">
            <h3 class="font-bold text-gray-800 flex items-center pointer-events-none">
                <svg id="voice-mic-icon" class="w-5 h-5 mr-2 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>
                Wound Assistant
            </h3>
            <button id="closeVoiceBtn" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        <div class="text-center w-full">
            <p id="voice-status-text" class="text-lg font-medium text-indigo-600">Initializing...</p>
            <p id="voice-transcript-text" class="text-sm text-gray-500 italic mt-1 min-h-[1.25rem]">...</p>
        </div>
        <div class="flex gap-2 w-full mt-2">
            <button id="voiceSkipBtn" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2 rounded text-sm font-medium">Skip</button>
            <button id="voiceStopBtn" class="flex-1 bg-red-50 hover:bg-red-100 text-red-600 py-2 rounded text-sm font-medium">Stop</button>
        </div>
    </div>

    <!-- SMART COMMAND OVERLAY -->
    <div id="smart-command-container" class="fixed top-24 right-10 bg-white border border-blue-200 shadow-2xl rounded-xl p-4 z-50 hidden w-80 flex flex-col items-center gap-3 transition-all">
        <div id="smart-drag-handle" class="flex items-center justify-between w-full border-b pb-2 cursor-move select-none" title="Drag to move">
            <h3 class="font-bold text-blue-900 flex items-center pointer-events-none">
                <svg class="w-5 h-5 mr-2 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
                Smart Voice Entry
            </h3>
            <button id="closeSmartBtn" class="text-gray-400 hover:text-gray-600 cursor-pointer">&times;</button>
        </div>
        
        <div class="flex flex-col items-center justify-center text-center w-full">
            <button type="button" id="smart_mic_btn" class="group relative bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300 mb-3">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                </svg>
            </button>

            <p class="text-xs text-gray-500 mb-2">Say: <span class="text-blue-700 italic">"Length 1.5, Width 2.3"</span></p>

            <div class="w-full relative">
                <input type="text" id="smart_command_input" class="w-full text-center border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 py-2 px-3 text-sm bg-gray-50" placeholder="Transcript...">
                <button type="button" id="execute_command_btn" class="absolute right-1 top-1 bottom-1 bg-white border border-gray-200 hover:bg-gray-100 text-gray-600 rounded px-2 text-xs font-bold transition">Apply</button>
            </div>
            <div id="command_feedback" class="hidden mt-2 font-medium text-xs text-green-600"></div>
        </div>
    </div>

    <div class="flex h-screen bg-gray-100">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <div class="flex items-center">
                    <!-- Mobile Menu Button -->
                    <button onclick="openSidebar()" class="md:hidden mr-4 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i data-lucide="menu" class="w-8 h-8"></i>
                    </button>
                    <div>
                        <h1 id="wound-header" class="text-2xl font-bold text-gray-800">Loading Wound Details...</h1>
                    <p id="patient-name-subheader" class="text-sm text-gray-600">Track assessment history and progress.</p>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Delete Wound Button -->
                    <button type="button" id="deleteWoundBtn" class="bg-red-100 text-red-700 hover:bg-red-200 font-bold py-2 px-3 rounded-md transition flex items-center justify-center shadow-sm mr-2" title="Delete Wound">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>

                    <a href="visit_wounds.php?id=<?php echo $_GET['id'];  ?>&appointment_id=<?php echo $_GET['appointment_id']; ?>&patient_id=<?php echo $_GET['patient_id']; ?>&user_id=<?php echo $_GET['user_id']; ?>"  class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md hover:bg-gray-300 transition">
                        Back
                    </a>

                    <!-- *** NEW: LMN Generator Button *** -->
                    <a href="api/generate_lmn.php?patient_id=<?php echo $_GET['patient_id']; ?>&wound_id=<?php echo $wound_id; ?>"
                       target="_blank"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center justify-center shadow-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Gen. LMN
                    </a>

                    <!-- Apply Graft Link -->
                    <a id="applyGraftBtn" href="shoreline_skin_graft_checklist.php?id=<?php echo $_GET['id'];  ?>&appointment_id=<?php echo $_GET['appointment_id']; ?>&patient_id=<?php echo $_GET['patient_id']; ?>&user_id=<?php echo $_GET['user_id']; ?>"  class="disabled bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center justify-center shadow-sm">
                        Apply Graft
                    </a>
                </div>
            </header>

            <!-- Submenu / Navigation Bar -->
            <div class="bg-white border-b border-gray-200 px-6 py-2 flex items-center space-x-6 shadow-sm z-10">
                <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Wound Records:</span>
                
                <button type="button" id="tabBtn-assessment" class="flex items-center text-indigo-600 font-bold border-b-2 border-indigo-600 transition text-sm py-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    Current Assessment
                </button>

                <div class="h-4 w-px bg-gray-300"></div>

                <button type="button" id="tabBtn-history" class="flex items-center text-gray-600 hover:text-indigo-600 font-medium transition text-sm py-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Assessment History
                </button>
                
                <div class="h-4 w-px bg-gray-300"></div>
                
                <button type="button" id="tabBtn-gallery" class="flex items-center text-gray-600 hover:text-purple-600 font-medium transition text-sm py-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    Photo Gallery
                </button>
            </div>

            <main class="flex-1 overflow-y-auto lg:overflow-hidden bg-gray-100 p-4">
                <!-- TAB 1: Assessment (Cockpit) -->
                <div id="tab-assessment" class="h-full">
                    <div class="grid grid-cols-12 gap-6 h-auto lg:h-full cockpit-container">
                        
                        <!-- LEFT PANEL: Visuals & Tools (Sticky/Fixed) -->
                        <div class="col-span-12 lg:col-span-5 xl:col-span-4 flex flex-col gap-4 h-auto lg:h-full lg:overflow-y-auto pr-2 pb-10 custom-scrollbar">
                            
                            <!-- 1. Image Capture Card -->
                            <div class="bg-white p-4 rounded-lg shadow-md">
                                <h3 class="font-bold text-gray-700 mb-3 flex items-center">
                                    <svg class="h-5 w-5 mr-2 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    Wound Photo
                                </h3>
                                
                                <!-- Image Preview Area -->
                                <div id="imagePreview" class="w-full h-64 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center mb-4 overflow-hidden relative group">
                                    <span class="text-gray-400 text-sm">No image selected</span>
                                    <img id="preview-img" src="" class="hidden w-full h-full object-contain">
                                    
                                    <!-- Overlay Actions (Edit/Delete) -->
                                    <div id="image-overlay" class="absolute inset-0 bg-black bg-opacity-50 hidden items-center justify-center space-x-4 transition-opacity opacity-0 group-hover:opacity-100">
                                        <button type="button" id="openAnnotationModalBtn" class="text-white hover:text-yellow-400 transition" title="Annotate">
                                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Image Type Selection -->
                                <div class="mb-3">
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Image Type</label>
                                    <select id="image_type" class="w-full form-input text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <option value="Pre-debridement" selected>Pre-debridement</option>
                                        <option value="Post-Debridement">Post-Debridement</option>
                                        <option value="Post-Graft">Post-Graft</option>
                                        <option value="Weekly Progress">Weekly Progress</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>

                                <!-- Upload Controls -->
                                <div id="capture-controls" class="grid grid-cols-2 gap-3 mb-4">
                                    <button type="button" id="toggleFileBtn" class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                        <svg class="h-5 w-5 mr-2 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                        Upload
                                    </button>
                                    <button type="button" id="toggleCameraBtn" class="flex items-center justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none">
                                        <svg class="h-5 w-5 mr-2 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        Camera
                                    </button>
                                </div>

                                <!-- Hidden Inputs -->
                                <input type="file" id="wound_photo" name="wound_photo" accept="image/*" style="opacity: 0; position: absolute; z-index: -1; width: 1px; height: 1px;">

                                <!-- Status Messages -->
                                <div id="capture-status" class="hidden text-sm text-gray-600 mb-2 text-center font-medium"></div>
                                <div id="upload-message" class="hidden text-sm mb-2 text-center"></div>
                                
                                <!-- Camera Container (Hidden by default) -->
                                <div id="camera-container" class="hidden mb-4 relative bg-black rounded-lg overflow-hidden">
                                    <video id="camera-stream" autoplay playsinline class="w-full h-48 object-cover"></video>
                                    <button type="button" id="captureBtn" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-white rounded-full p-3 shadow-lg hover:bg-gray-100">
                                        <div class="w-4 h-4 bg-red-600 rounded-full"></div>
                                    </button>
                                    <button type="button" id="closeCameraBtn" class="absolute top-2 right-2 text-white bg-black bg-opacity-50 rounded-full p-1 hover:bg-opacity-75">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <!-- Advanced Tools -->
                                <div class="space-y-2 border-t pt-4">
                                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Analysis Tools</p>
                                    
                                    <button type="button" id="openAIMeasureModalBtn" class="w-full flex items-center justify-between px-4 py-2 bg-indigo-50 text-indigo-700 rounded-md hover:bg-indigo-100 transition text-sm font-medium">
                                        <span class="flex items-center"><svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg> AI Measurement</span>
                                        <span class="text-xs bg-indigo-200 px-2 py-0.5 rounded-full">Auto</span>
                                    </button>

                                    <button type="button" id="openManualMeasureModalBtn" class="w-full flex items-center justify-between px-4 py-2 bg-gray-50 text-gray-700 rounded-md hover:bg-gray-100 transition text-sm font-medium">
                                        <span class="flex items-center"><svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg> Manual Measure</span>
                                    </button>
                                    
                                    <!-- Voice Measure Button -->
                                    <button type="button" id="startVoiceMeasureBtn" class="w-full flex items-center justify-between px-4 py-2 bg-rose-50 text-rose-700 rounded-md hover:bg-rose-100 transition text-sm font-medium">
                                        <span class="flex items-center"><svg class="h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg> Voice Measure</span>
                                    </button>
                                </div>
                            </div>

                    </div>

                    <!-- RIGHT PANEL: Assessment Form (Scrollable) -->
                    <div class="col-span-12 lg:col-span-7 xl:col-span-8 h-auto lg:h-full lg:overflow-y-auto pb-20 scroll-panel pr-2">
                        <div id="assessment-card" class="bg-white p-6 rounded-lg shadow-lg assessment-disabled min-h-full">
                            
                            <!-- Form Header -->
                            <div class="flex justify-between items-center mb-6 border-b pb-4 sticky top-0 bg-white z-10">
                                <div>
                                    <h2 class="text-2xl font-bold text-gray-800">Assessment Details</h2>
                                    <p class="text-sm text-gray-500">Complete all fields below.</p>
                                </div>
                                <button type="button" id="copyLastAssessmentBtn" class="hidden bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 font-bold py-2 px-4 rounded-full transition flex items-center shadow-sm text-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" /></svg>
                                    Copy Previous
                                </button>
                            </div>

                            <!-- Smart Voice Command Section -->
                            <div class="mb-6 bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border border-blue-100 shadow-sm flex items-center justify-between">
                                <div>
                                    <label class="text-sm font-bold text-blue-900 uppercase tracking-wide block mb-1">Smart Voice Entry</label>
                                    <p class="text-xs text-gray-600">Say: <span class="text-blue-700 italic">"Length 1.5, Width 2.3"</span> or <span class="text-blue-700 italic">"Undermining yes, location 9 oclock 2.5cm"</span></p>
                                </div>
                                <button type="button" id="open_smart_voice_btn" class="bg-blue-600 hover:bg-blue-700 text-white rounded-full p-3 shadow-md transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-blue-300 flex items-center">
                                    <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                                    </svg>
                                    <span class="font-bold text-sm">Start Voice</span>
                                </button>
                            </div>
                            
                            <div id="form-message" class="hidden p-3 my-3 rounded-md"></div>
                            
                            <form id="assessmentForm" class="space-y-6">
                                <!-- Hidden fields -->
                                <input type="hidden" name="wound_id" value="<?php echo $wound_id; ?>">
                                <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                                <input type="hidden" name="assessment_id" id="assessment_form_id" value="">
                                <input type="hidden" id="clinician_name" value="<?php echo htmlspecialchars($clinician_name, ENT_QUOTES); ?>">
                                <input type="hidden" id="clinician_id" value="<?php echo htmlspecialchars($clinician_id, ENT_QUOTES); ?>">
                                <input type="hidden" name="patient_id" id="patient_id" value="">
                                <input type="hidden" name="wound_type" id="wound_type_hidden">
                                <input type="hidden" name="wound_location" id="wound_location_hidden">

                                <!-- 1. Vitals & Pain -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="form-label">Assessment Date</label>
                                        <input type="date" name="assessment_date" id="assessment_date" required class="form-input" value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Pain Level (0-10)</label>
                                        <input type="hidden" name="pain_level" id="pain_level">
                                        <div class="btn-group-container" id="pain_level_group">
                                            <?php for($i=0; $i<=10; $i++): ?>
                                                <button type="button" class="btn-option flex-1" data-value="<?php echo $i; ?>"><?php echo $i; ?></button>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. Measurements Section -->
                                <div class="section-header">
                                    <svg class="h-6 w-6 section-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                    Measurements
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                    <div class="grid grid-cols-3 gap-4 mb-4">
                                        <div><label class="form-label text-xs uppercase text-gray-500">Length (cm)</label><input type="number" step="any" name="length_cm" id="length_cm" class="form-input font-bold text-lg"></div>
                                        <div><label class="form-label text-xs uppercase text-gray-500">Width (cm)</label><input type="number" step="any" name="width_cm" id="width_cm" class="form-input font-bold text-lg"></div>
                                        <div><label class="form-label text-xs uppercase text-gray-500">Depth (cm)</label><input type="number" step="0.1" name="depth_cm" id="depth_cm" class="form-input font-bold text-lg"></div>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <!-- Tunneling -->
                                        <div>
                                            <label class="form-label">Tunneling?</label>
                                            <input type="hidden" name="tunneling_present" id="tunneling_present" value="No">
                                            <div class="btn-group-container mb-2" id="tunneling_present_group">
                                                <button type="button" class="btn-option active" data-value="No">No</button>
                                                <button type="button" class="btn-option" data-value="Yes">Yes</button>
                                            </div>
                                            <div id="tunneling_details_container" class="hidden space-y-2 bg-white p-2 rounded border">
                                                <div id="tunneling_locations"></div>
                                                <button type="button" id="addTunnelingLocation" class="text-xs text-blue-600 font-bold uppercase tracking-wide">+ Add Location</button>
                                            </div>
                                        </div>

                                        <!-- Undermining -->
                                        <div>
                                            <label class="form-label">Undermining?</label>
                                            <input type="hidden" name="undermining_present" id="undermining_present" value="No">
                                            <div class="btn-group-container mb-2" id="undermining_present_group">
                                                <button type="button" class="btn-option active" data-value="No">No</button>
                                                <button type="button" class="btn-option" data-value="Yes">Yes</button>
                                            </div>
                                            <div id="undermining_details_container" class="hidden space-y-2 bg-white p-2 rounded border">
                                                <div id="undermining_locations"></div>
                                                <button type="button" id="addUnderminingLocation" class="text-xs text-blue-600 font-bold uppercase tracking-wide">+ Add Location</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Tissue & Characteristics -->
                                <div class="section-header">
                                    <svg class="h-6 w-6 section-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                    Tissue & Characteristics
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Tissue Percentages -->
                                    <div class="col-span-full">
                                        <label class="form-label mb-2">Tissue Composition (%)</label>
                                        <div class="grid grid-cols-4 gap-2">
                                            <div><label class="text-xs text-gray-500 block text-center">Granulation</label><input type="number" name="granulation_percent" id="granulation_percent" class="form-input text-center" placeholder="0"></div>
                                            <div><label class="text-xs text-gray-500 block text-center">Slough</label><input type="number" name="slough_percent" id="slough_percent" class="form-input text-center" placeholder="0"></div>
                                            <div><label class="text-xs text-gray-500 block text-center">Eschar</label><input type="number" name="eschar_percent" id="eschar_percent" class="form-input text-center" placeholder="0"></div>
                                            <div><label class="text-xs text-gray-500 block text-center">Epithelial</label><input type="number" name="epithelialization_percent" id="epithelialization_percent" class="form-input text-center" placeholder="0"></div>
                                        </div>
                                    </div>

                                    <!-- Granulation Details (Conditional) -->
                                    <div id="granulation_details" class="col-span-full hidden bg-indigo-50 p-3 rounded border border-indigo-100">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="form-label text-xs">Color</label>
                                                <select name="granulation_color" id="granulation_color" class="form-input bg-white text-sm"><option value="">Select</option><option>Red</option><option>Pale</option><option>Pink</option></select>
                                            </div>
                                            <div>
                                                <label class="form-label text-xs">Coverage</label>
                                                <select name="granulation_coverage" id="granulation_coverage" class="form-input bg-white text-sm"><option value="">Select</option><option value="<25%">< 25%</option><option value=">50%">> 50%</option><option value=">75%">> 75%</option><option value="100%">100%</option></select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Drainage -->
                                    <div class="col-span-full">
                                        <label class="form-label">Drainage Amount</label>
                                        <input type="hidden" name="exudate_amount" id="exudate_amount">
                                        <div class="btn-group-container" id="exudate_amount_group">
                                            <button type="button" class="btn-option" data-value="None">None</button>
                                            <button type="button" class="btn-option" data-value="Scant">Scant</button>
                                            <button type="button" class="btn-option" data-value="Small">Small</button>
                                            <button type="button" class="btn-option" data-value="Moderate">Mod</button>
                                            <button type="button" class="btn-option" data-value="Large">Large</button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="form-label">Drainage Type</label>
                                        <select name="drainage_type" id="drainage_type" class="form-input bg-white"><option value="">None</option><option>Serous</option><option>Purulent</option><option>Serosanguineous</option><option>Clear</option></select>
                                    </div>

                                    <div>
                                        <label class="form-label">Odor?</label>
                                        <input type="hidden" name="odor_present" id="odor_present" value="No">
                                        <div class="btn-group-container" id="odor_present_group">
                                            <button type="button" class="btn-option active" data-value="No">No</button>
                                            <button type="button" class="btn-option" data-value="Yes">Yes</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Infection & Periwound -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                    <div>
                                        <label class="form-label">Periwound Condition</label>
                                        <select name="periwound_condition[]" id="periwound_condition" class="form-input bg-white h-24" multiple>
                                            <option>Intact</option><option>Macerated</option><option>Erythema</option><option>Edema</option><option>Indurated</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label text-red-700">Infection Signs</label>
                                        <select name="signs_of_infection[]" id="signs_of_infection" class="form-input bg-white h-24 border-red-200" multiple>
                                            <option>Redness</option><option>Swelling</option><option>Warmth</option><option>Increased Pain</option><option>Purulent Drainage</option><option>Osteomyelitis</option><option>Cellulitis</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Exposed Structures -->
                                <div class="mt-4 bg-red-50 p-3 rounded border border-red-100" id="exposed_structures_container">
                                    <label class="form-label text-red-800 font-bold mb-2 block">Exposed Structures</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="None" class="form-checkbox text-gray-600 h-5 w-5">
                                            <span class="text-sm text-gray-700">None</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Bone" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Bone</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Tendon" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Tendon</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Ligament" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Ligament</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Muscle" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Muscle</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Fascia" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Fascia</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Hardware" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Hardware</span>
                                        </label>
                                        <label class="inline-flex items-center space-x-2 cursor-pointer">
                                            <input type="checkbox" name="exposed_structures[]" value="Joint Capsule" class="form-checkbox text-red-600 h-5 w-5">
                                            <span class="text-sm font-bold text-red-700">Joint Capsule</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- 5. Debridement -->
                                <div class="section-header">
                                    <svg class="h-6 w-6 section-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z" /></svg>
                                    Debridement
                                </div>
                                <div>
                                    <label class="form-label">Debridement Performed?</label>
                                    <input type="hidden" name="debridement_performed" id="debridement_performed" value="No">
                                    <div class="btn-group-container mb-4" id="debridement_performed_group">
                                        <button type="button" class="btn-option active" data-value="No">No</button>
                                        <button type="button" class="btn-option" data-value="Yes">Yes</button>
                                    </div>
                                    
                                    <div id="debridement_details" class="hidden bg-gray-50 p-4 rounded border">
                                        <div class="mb-3">
                                            <label class="form-label">Debridement Type</label>
                                            <div class="btn-group-container">
                                                <!-- We can use JS to make this a single select group too, but for now let's keep select or make it buttons -->
                                                <!-- Let's stick to select for type as there are 4 options -->
                                                <select name="debridement_type" id="debridement_type" class="form-input bg-white">
                                                    <option value="">Select Type</option><option>Sharp</option><option>Mechanical</option><option>Enzymatic</option><option>Autolytic</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="form-label">Pre-Debridement Notes</label>
                                            <textarea name="pre_debridement_notes" id="pre_debridement_notes" rows="2" class="form-input" placeholder="Describe tissue state before debridement..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. Plan & Notes -->
                                <div class="section-header">
                                    <svg class="h-6 w-6 section-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
                                    Plan & Notes
                                </div>
                                
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="form-label">Treatment Plan</label>
                                            <button type="button" id="generatePlanBtn" class="text-xs bg-indigo-100 text-indigo-700 font-bold py-1 px-2 rounded-full hover:bg-indigo-200 transition">Auto-Gen</button>
                                        </div>
                                        <textarea name="treatments_provided" id="treatments_provided" rows="4" class="form-input" placeholder="Cleanse, Dress, Offload..."></textarea>
                                    </div>
                                    
                                    <!-- Risk Factors (Collapsed by default or simplified) -->
                                    <div class="bg-gray-50 p-3 rounded border">
                                        <div class="flex justify-between items-center cursor-pointer" onclick="document.getElementById('risk-container').classList.toggle('hidden')">
                                            <span class="text-sm font-bold text-gray-600">Risk Factors & Nutrition (Click to Expand)</span>
                                            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                        </div>
                                        <div id="risk-container" class="hidden mt-3 space-y-3">
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <label class="text-xs font-bold text-gray-500 uppercase">Risk Factors</label>
                                                    <select class="quick-text-selector text-xs border-gray-300 rounded p-1" data-target="risk_factors">
                                                        <option value="">+ Quick Add</option>
                                                        <option value="Diabetes">Diabetes</option>
                                                        <option value="Neuropathy">Neuropathy</option>
                                                        <option value="PVD/PAD">PVD/PAD</option>
                                                        <option value="Smoking">Smoking</option>
                                                        <option value="Obesity">Obesity</option>
                                                        <option value="Bedbound / Immobility">Bedbound / Immobility</option>
                                                        <option value="Incontinence">Incontinence</option>
                                                        <option value="Steroid Use">Steroid Use</option>
                                                    </select>
                                                </div>
                                                <textarea name="risk_factors" id="risk_factors" rows="2" class="form-input text-sm" placeholder="Risk Factors..."></textarea>
                                            </div>
                                            
                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <label class="text-xs font-bold text-gray-500 uppercase">Nutritional Status</label>
                                                    <select class="quick-text-selector text-xs border-gray-300 rounded p-1" data-target="nutritional_status">
                                                        <option value="">+ Quick Add</option>
                                                        <option value="Intake Good (>75% meals)">Intake Good (>75%)</option>
                                                        <option value="Intake Fair (50-75% meals)">Intake Fair (50-75%)</option>
                                                        <option value="Intake Poor (<50% meals)">Intake Poor (<50%)</option>
                                                        <option value="Supplements Provided">Supplements Provided</option>
                                                        <option value="Tube Feeding">Tube Feeding</option>
                                                    </select>
                                                </div>
                                                <textarea name="nutritional_status" id="nutritional_status" rows="2" class="form-input text-sm" placeholder="Nutrition..."></textarea>
                                            </div>

                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <label class="text-xs font-bold text-gray-500 uppercase">Medical Necessity</label>
                                                    <select class="quick-text-selector text-xs border-gray-300 rounded p-1" data-target="medical_necessity">
                                                        <option value="">+ Quick Add</option>
                                                        <option value="Debridement of necrotic tissue">Debridement of necrotic tissue</option>
                                                        <option value="Control of infection/bioburden">Control of infection/bioburden</option>
                                                        <option value="Management of exudate">Management of exudate</option>
                                                        <option value="Promotion of granulation">Promotion of granulation</option>
                                                        <option value="Preparation for graft">Preparation for graft</option>
                                                    </select>
                                                </div>
                                                <textarea name="medical_necessity" id="medical_necessity" rows="2" class="form-input text-sm" placeholder="Reason for treatment..."></textarea>
                                            </div>

                                            <div>
                                                <div class="flex justify-between items-center mb-1">
                                                    <label class="text-xs font-bold text-gray-500 uppercase">Edema / DVT</label>
                                                    <select class="quick-text-selector text-xs border-gray-300 rounded p-1" data-target="dvt_edema_notes">
                                                        <option value="">+ Quick Add</option>
                                                        <option value="No edema">No edema</option>
                                                        <option value="1+ Pitting">1+ Pitting</option>
                                                        <option value="2+ Pitting">2+ Pitting</option>
                                                        <option value="3+ Pitting">3+ Pitting</option>
                                                        <option value="Non-pitting">Non-pitting</option>
                                                        <option value="Generalized">Generalized</option>
                                                        <option value="Periwound only">Periwound only</option>
                                                    </select>
                                                </div>
                                                <textarea name="dvt_edema_notes" id="dvt_edema_notes" rows="2" class="form-input text-sm" placeholder="Edema details..."></textarea>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="text-xs block mb-1 font-bold text-gray-500">Braden Score</label>
                                                    <div class="flex space-x-2">
                                                        <input type="number" name="braden_score" id="braden_score" class="form-input text-sm flex-grow" placeholder="6-23">
                                                        <button type="button" id="openBradenCalcBtn" class="px-3 py-1 bg-indigo-100 text-indigo-700 rounded text-xs font-bold hover:bg-indigo-200 border border-indigo-200">Calc</button>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="text-xs block mb-1 font-bold text-gray-500">PUSH Score</label>
                                                    <div class="flex space-x-2">
                                                        <input type="number" name="push_score" id="push_score" class="form-input text-sm bg-gray-100 flex-grow" readonly placeholder="0-17">
                                                        <button type="button" id="openPushCalcBtn" class="px-3 py-1 bg-green-100 text-green-700 rounded text-xs font-bold hover:bg-green-200 border border-green-200">Calc</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Finalize Button -->
                                <div class="pt-6 pb-10">
                                    <button type="submit" id="saveAssessmentBtn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-lg shadow-lg transition text-lg flex items-center justify-center">
                                        <svg class="h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        Finalize Assessment
                                    </button>
                                    <div class="text-center mt-2">
                                        <span id="autosave-status" class="text-xs text-gray-500 italic"></span>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>
                </div>

                <!-- TAB 2: History -->
                <div id="tab-history" class="hidden h-full overflow-y-auto p-6 bg-white rounded-lg shadow-md">
                    <div id="assessment-history-container" class="overflow-y-auto flex-grow custom-scrollbar">
                        <!-- Content injected by JS -->
                    </div>
                </div>

                <!-- TAB 3: Gallery -->
                <div id="tab-gallery" class="hidden h-full overflow-y-auto p-6 bg-white rounded-lg shadow-md">
                    <div id="image-gallery" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 overflow-y-auto flex-grow custom-scrollbar p-2">
                        <!-- Content injected by JS -->
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Assessment Modal -->
    <div id="viewAssessmentModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-2xl w-full max-h-full overflow-y-auto">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Assessment Details</h3>
                <button id="closeViewModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div id="viewAssessmentContent" class="space-y-4"></div>
        </div>
    </div>

    <!-- AI Measurement Modal -->
    <div id="aiMeasurementModal" class="modal fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full h-[90vh] flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-900 px-6 py-4 flex justify-between items-center border-b border-gray-800">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-indigo-600 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">AI-Powered Analysis</h3>
                        <p class="text-xs text-gray-400">Automated wound measurement & tissue classification</p>
                    </div>
                </div>
                <button id="closeAIMeasureModalBtn" class="text-gray-400 hover:text-white transition p-2 rounded-full hover:bg-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Content -->
            <div id="ai-modal-content" class="flex-grow flex flex-col lg:flex-row overflow-hidden bg-gray-50">
                <!-- Canvas Area -->
                <div id="ai-canvas-wrapper" class="lg:w-3/4 h-full flex items-center justify-center bg-gray-100 relative border-r border-gray-200">
                    <div class="absolute top-4 left-4 z-10 bg-white/90 backdrop-blur px-3 py-1.5 rounded-full shadow-sm text-xs font-medium text-gray-600 border border-gray-200">
                        Original Image Preview
                    </div>
                    <canvas id="aiCanvas"></canvas>
                </div>

                <!-- Sidebar -->
                <div class="w-full lg:w-1/4 flex flex-col bg-white border-l border-gray-200 shadow-lg z-10">
                    <div class="p-6 flex-grow overflow-y-auto space-y-6">
                        
                        <!-- Instructions -->
                        <div class="bg-blue-50 border border-blue-100 rounded-lg p-4">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p id="ai-modal-instructions" class="text-sm text-blue-800">
                                    Ensure the wound is clearly visible. Add orientation marker if needed before running analysis.
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="space-y-3">
                            <button id="addAIHeadArrowBtn" type="button" class="w-full flex items-center justify-center px-4 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 hover:border-gray-400 transition shadow-sm group">
                                <svg class="w-5 h-5 mr-2 text-gray-400 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                                Add Orientation Marker
                            </button>

                            <form id="aiMeasureForm">
                                <input type="hidden" name="wound_id" value="<?php echo $wound_id; ?>">
                                <button type="submit" id="aiMeasureSubmitBtn" class="w-full flex items-center justify-center px-4 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-lg hover:from-indigo-700 hover:to-purple-700 transition shadow-md transform hover:-translate-y-0.5" disabled>
                                    <svg class="w-5 h-5 mr-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path></svg>
                                    Run AI Analysis
                                </button>
                            </form>
                        </div>

                        <!-- Results -->
                        <div id="ai-results" class="hidden space-y-4 animate-fade-in-up">
                            <div class="flex items-center justify-between">
                                <h4 class="font-bold text-gray-800">Analysis Results</h4>
                                <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-bold rounded-full">Completed</span>
                            </div>
                            
                            <!-- Dimensions Grid -->
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span class="text-xs text-gray-500 uppercase tracking-wider">Length</span>
                                    <div id="aiResLength" class="text-lg font-bold text-gray-900">N/A</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span class="text-xs text-gray-500 uppercase tracking-wider">Width</span>
                                    <div id="aiResWidth" class="text-lg font-bold text-gray-900">N/A</div>
                                </div>
                                <div class="col-span-2 bg-indigo-50 p-3 rounded-lg border border-indigo-100 text-center">
                                    <span class="text-xs text-indigo-600 uppercase tracking-wider font-bold">Surface Area</span>
                                    <div id="aiResArea" class="text-2xl font-bold text-indigo-700">N/A</div>
                                </div>
                            </div>

                            <!-- Tissue Stats -->
                            <div class="space-y-2">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Granulation</span>
                                    <span id="aiResGranulation" class="font-bold text-gray-900">N/A</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-red-500 h-1.5 rounded-full" style="width: 0%"></div>
                                </div>
                                
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Slough</span>
                                    <span id="aiResSlough" class="font-bold text-gray-900">N/A</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-yellow-400 h-1.5 rounded-full" style="width: 0%"></div>
                                </div>

                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-gray-600">Eschar</span>
                                    <span id="aiResEschar" class="font-bold text-gray-900">N/A</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-black h-1.5 rounded-full" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <div class="bg-red-50 p-3 rounded-lg border border-red-100 flex justify-between items-center">
                                <span class="text-sm font-medium text-red-800">Infection Risk</span>
                                <span id="aiResInfection" class="font-bold text-red-700">N/A</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="p-6 border-t border-gray-200 bg-gray-50">
                        <button id="useAIMeasurementsBtn" class="w-full flex items-center justify-center px-4 py-3 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Apply Measurements
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Measurement Modal -->
    <div id="manualMeasurementModal" class="modal fixed inset-0 bg-black bg-opacity-80 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full h-[90vh] flex flex-col overflow-hidden">
            <!-- Header -->
            <div class="bg-gray-900 px-6 py-4 flex justify-between items-center border-b border-gray-800">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-blue-600 rounded-lg">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white">Interactive Measurement</h3>
                        <p class="text-xs text-gray-400">Manual calibration & measurement tools</p>
                    </div>
                </div>
                <button id="closeManualModalBtn" class="text-gray-400 hover:text-white transition p-2 rounded-full hover:bg-gray-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-grow flex flex-col lg:flex-row overflow-hidden bg-gray-50">
                <!-- Canvas Area -->
                <div id="canvas-wrapper" class="lg:w-3/4 h-full flex items-center justify-center bg-gray-100 relative border-r border-gray-200">
                    <div class="absolute top-4 left-4 z-10 bg-white/90 backdrop-blur px-3 py-1.5 rounded-full shadow-sm text-xs font-medium text-gray-600 border border-gray-200">
                        Interactive Canvas
                    </div>
                    <canvas id="measurementCanvas"></canvas>
                </div>

                <!-- Sidebar -->
                <div class="w-full lg:w-1/4 flex flex-col bg-white border-l border-gray-200 shadow-lg z-10">
                    <div class="p-6 flex-grow overflow-y-auto space-y-6">
                        
                        <!-- Instructions -->
                        <div id="instruction-text" class="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800">
                            <span class="font-bold block mb-1">Step 1:</span> Click 'Set Scale' and draw a line on a ruler in the image to calibrate.
                        </div>

                        <!-- Tools -->
                        <div class="space-y-4">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Calibration & Tools</h4>
                            
                            <button id="setScaleBtn" class="w-full flex items-center justify-between px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 hover:border-indigo-500 hover:text-indigo-600 transition shadow-sm group">
                                <span>Set Scale</span>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            </button>

                            <button id="drawAreaBtn" class="w-full flex items-center justify-between px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 hover:border-indigo-500 hover:text-indigo-600 transition shadow-sm group" disabled>
                                <span>Free-hand Draw</span>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                            </button>

                            <button id="addManualHeadArrowBtn" class="w-full flex items-center justify-between px-4 py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 hover:border-indigo-500 hover:text-indigo-600 transition shadow-sm group">
                                <span>Add Orientation</span>
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
                            </button>
                            
                            <button id="clearBtn" class="w-full flex items-center justify-center px-4 py-2 bg-red-50 text-red-600 font-medium rounded-lg hover:bg-red-100 transition text-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Clear Canvas
                            </button>
                        </div>

                        <!-- Results -->
                        <div id="results" class="space-y-3 pt-4 border-t border-gray-100">
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Measurements</h4>
                            
                            <div class="grid grid-cols-2 gap-3">
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span class="text-xs text-gray-500 uppercase tracking-wider">Length</span>
                                    <div id="resLength" class="text-lg font-bold text-gray-900">N/A</div>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg border border-gray-200 text-center">
                                    <span class="text-xs text-gray-500 uppercase tracking-wider">Width</span>
                                    <div id="resWidth" class="text-lg font-bold text-gray-900">N/A</div>
                                </div>
                                <div class="col-span-2 bg-blue-50 p-3 rounded-lg border border-blue-100 text-center">
                                    <span class="text-xs text-blue-600 uppercase tracking-wider font-bold">Surface Area</span>
                                    <div id="resArea" class="text-2xl font-bold text-blue-700">N/A</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="p-6 border-t border-gray-200 bg-gray-50">
                        <button id="useMeasurementsBtn" class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Apply Measurements
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PUSH Score Calculator Modal -->
    <div id="pushCalcModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-lg w-full max-h-full overflow-y-auto">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">PUSH Tool 3.0 Calculator</h3>
                <button id="closePushCalcBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            
            <div class="space-y-6">
                <!-- Sub-score 1: Surface Area -->
                <div class="bg-gray-50 p-3 rounded border">
                    <h4 class="font-bold text-gray-700 text-sm mb-2">1. Surface Area (Length x Width)</h4>
                    <div class="flex justify-between text-xs text-gray-500 mb-2">
                        <span>Current: <span id="pushCalcAreaVal" class="font-mono font-bold text-black">0</span> cm²</span>
                        <span class="font-bold text-indigo-600">Score: <span id="pushScoreArea">0</span></span>
                    </div>
                    <select id="pushSelectArea" class="form-input text-sm">
                        <option value="0">0 cm² (Score: 0)</option>
                        <option value="1">< 0.3 cm² (Score: 1)</option>
                        <option value="2">0.3 - 0.6 cm² (Score: 2)</option>
                        <option value="3">0.7 - 1.0 cm² (Score: 3)</option>
                        <option value="4">1.1 - 2.0 cm² (Score: 4)</option>
                        <option value="5">2.1 - 3.0 cm² (Score: 5)</option>
                        <option value="6">3.1 - 4.0 cm² (Score: 6)</option>
                        <option value="7">4.1 - 8.0 cm² (Score: 7)</option>
                        <option value="8">8.1 - 12.0 cm² (Score: 8)</option>
                        <option value="9">12.1 - 24.0 cm² (Score: 9)</option>
                        <option value="10">> 24.0 cm² (Score: 10)</option>
                    </select>
                </div>

                <!-- Sub-score 2: Exudate Amount -->
                <div class="bg-gray-50 p-3 rounded border">
                    <h4 class="font-bold text-gray-700 text-sm mb-2">2. Exudate Amount</h4>
                    <div class="flex justify-between text-xs text-gray-500 mb-2">
                        <span>Current: <span id="pushCalcExudateVal" class="font-mono font-bold text-black">-</span></span>
                        <span class="font-bold text-indigo-600">Score: <span id="pushScoreExudate">0</span></span>
                    </div>
                    <select id="pushSelectExudate" class="form-input text-sm">
                        <option value="0">None (Score: 0)</option>
                        <option value="1">Light (Score: 1)</option>
                        <option value="2">Moderate (Score: 2)</option>
                        <option value="3">Heavy (Score: 3)</option>
                    </select>
                </div>

                <!-- Sub-score 3: Tissue Type -->
                <div class="bg-gray-50 p-3 rounded border">
                    <h4 class="font-bold text-gray-700 text-sm mb-2">3. Tissue Type</h4>
                    <div class="flex justify-between text-xs text-gray-500 mb-2">
                        <span>Detected: <span id="pushCalcTissueVal" class="font-mono font-bold text-black">-</span></span>
                        <span class="font-bold text-indigo-600">Score: <span id="pushScoreTissue">0</span></span>
                    </div>
                    <select id="pushSelectTissue" class="form-input text-sm">
                        <option value="0">Closed/Resurfaced (Score: 0)</option>
                        <option value="1">Epithelial Tissue (Score: 1)</option>
                        <option value="2">Granulation Tissue (Score: 2)</option>
                        <option value="3">Slough (Score: 3)</option>
                        <option value="4">Necrotic Tissue/Eschar (Score: 4)</option>
                    </select>
                </div>

                <!-- Total -->
                <div class="flex justify-between items-center border-t pt-4">
                    <div class="text-lg font-bold text-gray-800">Total PUSH Score:</div>
                    <div id="pushTotalDisplay" class="text-3xl font-bold text-indigo-700">0</div>
                </div>

                <button type="button" id="applyPushScoreBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-md transition shadow-md">
                    Apply Score to Assessment
                </button>
            </div>
        </div>
    </div>

    <!-- Braden Score Calculator Modal -->
    <div id="bradenCalcModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-lg w-full max-h-full overflow-y-auto">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Braden Scale Calculator</h3>
                <button id="closeBradenCalcBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            
            <div class="space-y-4">
                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">1. Sensory Perception</label>
                    <select id="bradenSensory" class="form-input text-sm w-full">
                        <option value="1">1. Completely Limited</option>
                        <option value="2">2. Very Limited</option>
                        <option value="3">3. Slightly Limited</option>
                        <option value="4" selected>4. No Impairment</option>
                    </select>
                </div>

                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">2. Moisture</label>
                    <select id="bradenMoisture" class="form-input text-sm w-full">
                        <option value="1">1. Constantly Moist</option>
                        <option value="2">2. Very Moist</option>
                        <option value="3">3. Occasionally Moist</option>
                        <option value="4" selected>4. Rarely Moist</option>
                    </select>
                </div>

                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">3. Activity</label>
                    <select id="bradenActivity" class="form-input text-sm w-full">
                        <option value="1">1. Bedfast</option>
                        <option value="2">2. Chairfast</option>
                        <option value="3">3. Walks Occasionally</option>
                        <option value="4" selected>4. Walks Frequently</option>
                    </select>
                </div>

                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">4. Mobility</label>
                    <select id="bradenMobility" class="form-input text-sm w-full">
                        <option value="1">1. Completely Immobile</option>
                        <option value="2">2. Very Limited</option>
                        <option value="3">3. Slightly Limited</option>
                        <option value="4" selected>4. No Limitation</option>
                    </select>
                </div>

                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">5. Nutrition</label>
                    <select id="bradenNutrition" class="form-input text-sm w-full">
                        <option value="1">1. Very Poor</option>
                        <option value="2">2. Probably Inadequate</option>
                        <option value="3">3. Adequate</option>
                        <option value="4" selected>4. Excellent</option>
                    </select>
                </div>

                <div class="bg-gray-50 p-3 rounded border">
                    <label class="block text-xs font-bold text-gray-700 mb-1">6. Friction & Shear</label>
                    <select id="bradenFriction" class="form-input text-sm w-full">
                        <option value="1">1. Problem</option>
                        <option value="2">2. Potential Problem</option>
                        <option value="3" selected>3. No Apparent Problem</option>
                    </select>
                </div>

                <!-- Total -->
                <div class="flex justify-between items-center border-t pt-4">
                    <div>
                        <div class="text-lg font-bold text-gray-800">Total Braden Score:</div>
                        <div id="bradenRiskLabel" class="text-xs font-bold text-gray-500">No Risk</div>
                    </div>
                    <div id="bradenTotalDisplay" class="text-3xl font-bold text-indigo-700">23</div>
                </div>

                <button type="button" id="applyBradenScoreBtn" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-4 rounded-md transition shadow-md">
                    Apply Score to Assessment
                </button>
            </div>
        </div>
    </div>

    <!-- Image Annotation Modal -->
    <div id="annotationModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-6xl w-full h-full flex flex-col">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 class="text-xl font-semibold text-gray-800">Annotate Wound Image</h3>
                <button id="closeAnnotationModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <div class="flex-grow flex flex-col md:flex-row gap-4 overflow-hidden">
                <div id="annotation-canvas-wrapper" class="md:w-3/4 h-full flex items-center justify-center bg-gray-200 rounded-lg overflow-hidden relative">
                    <canvas id="annotationCanvas"></canvas>
                </div>
                <div class="md:w-1/4 flex flex-col space-y-4">
                    <div class="bg-gray-50 p-4 rounded-lg border">
                        <h4 class="font-bold text-lg mb-2">Tools</h4>
                        <p class="text-sm text-gray-600 mb-3">Select a color to draw on the image.</p>
                        
                        <div class="grid grid-cols-2 gap-2 mb-4">
                            <button class="annotation-color-btn w-full h-10 rounded border-2 border-transparent hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 bg-red-500 text-white text-xs font-bold" data-color="red">Tunneling</button>
                            <button class="annotation-color-btn w-full h-10 rounded border-2 border-transparent hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-400 bg-yellow-400 text-black text-xs font-bold" data-color="yellow">Slough</button>
                            <button class="annotation-color-btn w-full h-10 rounded border-2 border-transparent hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 bg-green-500 text-white text-xs font-bold" data-color="green">Granulation</button>
                            <button class="annotation-color-btn w-full h-10 rounded border-2 border-transparent hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-black bg-black text-white text-xs font-bold" data-color="black">Margins</button>
                        </div>

                        <div class="mb-4">
                            <label class="text-xs font-bold text-gray-700">Brush Size</label>
                            <input type="range" id="annotationBrushSize" min="1" max="20" value="3" class="w-full">
                        </div>

                        <button id="addHeadArrowBtn" class="w-full bg-indigo-100 text-indigo-800 font-bold py-2 px-4 rounded-md hover:bg-indigo-200 transition mb-2 flex items-center justify-center border border-indigo-300">
                            <svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                            Add 'Head' Orientation
                        </button>

                        <button id="undoAnnotationBtn" class="w-full bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md hover:bg-gray-300 transition mb-2">
                            Undo Last
                        </button>
                        <button id="clearAnnotationBtn" class="w-full bg-red-100 text-red-800 font-bold py-2 px-4 rounded-md hover:bg-red-200 transition">
                            Clear All
                        </button>
                    </div>
                    
                    <div class="flex-grow"></div> <!-- Spacer -->

                    <button id="saveAnnotationBtn" class="w-full bg-blue-600 text-white font-bold py-3 px-4 rounded-md hover:bg-blue-700 transition shadow-lg">
                        Save Annotated Image
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Menu -->
    <div id="fab-menu" class="fixed bottom-6 right-6 z-50 flex flex-col-reverse items-center gap-3">
        <!-- Main Toggle Button -->
        <button id="fab-toggle" class="w-14 h-14 bg-indigo-600 text-white rounded-full shadow-lg flex items-center justify-center hover:bg-indigo-700 transition-transform transform hover:scale-110 focus:outline-none">
            <svg id="fab-icon-menu" class="w-8 h-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" /></svg>
            <svg id="fab-icon-close" class="w-8 h-8 hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <!-- Action Buttons (Hidden by default) -->
        <div id="fab-actions" class="flex flex-col-reverse gap-3 hidden">
            
            <!-- Save -->
            <button id="fab-save" class="w-12 h-12 bg-green-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-green-700 transition tooltip-left relative" data-tooltip="Save Assessment">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
            </button>

            <!-- Navigation: Assessment History -->
            <a href="visit_notes_history.php?patient_id=<?php echo $patient_id ?? 0; ?>" class="w-12 h-12 bg-purple-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-purple-700 transition tooltip-left relative" data-tooltip="Assessment History">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </a>

            <!-- Navigation: Photo Gallery -->
            <a href="patient_profile.php?id=<?php echo $patient_id ?? 0; ?>&view=gallery" class="w-12 h-12 bg-pink-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-pink-700 transition tooltip-left relative" data-tooltip="Photo Gallery">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
            </a>

            <!-- Navigation: Wound Management -->
            <a href="visit_wounds.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>&user_id=<?php echo $user_id; ?>" class="w-12 h-12 bg-orange-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-orange-700 transition tooltip-left relative" data-tooltip="Wound Management">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
            </a>

            <!-- Navigation: Current Assessment (Reload) -->
            <a href="wound_assessment.php?id=<?php echo $wound_id; ?>&appointment_id=<?php echo $appointment_id; ?>&user_id=<?php echo $user_id; ?>" class="w-12 h-12 bg-teal-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-teal-700 transition tooltip-left relative" data-tooltip="Current Assessment">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>
            </a>

            <!-- Voice Guide -->
            <button id="fab-voice-guide" class="w-12 h-12 bg-indigo-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-indigo-700 transition tooltip-left relative" data-tooltip="Voice Commands Guide">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </button>

            <!-- Smart Voice FAB -->
            <button id="fab-smart-voice" class="w-12 h-12 bg-purple-600 text-white rounded-full shadow-md flex items-center justify-center hover:bg-purple-700 transition tooltip-left relative" data-tooltip="Smart Voice Entry">
                <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
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

    <!-- Generic Prompt Modal -->
    <div id="promptModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full transform transition-all scale-100">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 bg-blue-100 rounded-full p-2 mr-3">
                    <svg class="h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </div>
                <h3 id="promptModalTitle" class="text-xl font-bold text-gray-800">Input Required</h3>
            </div>
            <p id="promptMessage" class="text-gray-600 mb-4 ml-11">Please enter a value:</p>
            
            <div class="ml-11 mb-6">
                <input type="text" id="promptInput" class="w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="">
            </div>

            <div class="flex justify-end space-x-3">
                <button id="cancelPromptBtn" class="px-4 py-2 bg-gray-200 text-gray-800 font-medium rounded hover:bg-gray-300 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">Cancel</button>
                <button id="confirmPromptBtn" class="px-4 py-2 bg-blue-600 text-white font-bold rounded hover:bg-blue-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">OK</button>
            </div>
        </div>
    </div>

    <!-- Generic Confirmation Modal -->
    <div id="confirmationModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-md w-full transform transition-all scale-100">
            <div class="flex items-center mb-4">
                <div class="flex-shrink-0 bg-red-100 rounded-full p-2 mr-3">
                    <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 id="confirmationModalTitle" class="text-xl font-bold text-gray-800">Confirm Action</h3>
            </div>
            <p id="confirmationMessage" class="text-gray-600 mb-6 ml-11">Are you sure?</p>
            <div class="flex justify-end space-x-3">
                <button id="cancelConfirmBtn" class="px-4 py-2 bg-gray-200 text-gray-800 font-medium rounded hover:bg-gray-300 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400">Cancel</button>
                <button id="confirmActionBtn" class="px-4 py-2 bg-red-600 text-white font-bold rounded hover:bg-red-700 transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Voice Commands Guide Modal -->
    <div id="voiceGuideModal" class="modal fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl p-6 max-w-2xl w-full transform transition-all scale-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 border-b pb-2">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="mic" class="w-6 h-6 mr-2 text-blue-600"></i>
                    Smart Voice Commands
                </h3>
                <button id="closeVoiceGuideBtn" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            
            <div class="space-y-6 text-sm text-gray-700">
                <p class="italic text-gray-500">Tap the microphone icon and speak naturally. You can say multiple commands at once.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Measurements -->
                    <div>
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Measurements</h4>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>"Length 2.5"</li>
                            <li>"Width 1.8 cm"</li>
                            <li>"Depth 0.5"</li>
                        </ul>
                    </div>

                    <!-- Characteristics -->
                    <div>
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Characteristics</h4>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>"Tunneling yes, location 3 o'clock 1.5cm"</li>
                            <li>"Undermining yes, location 9 o'clock 2cm"</li>
                            <li>"Pain level 4" (0-10)</li>
                        </ul>
                    </div>

                    <!-- Tissue % -->
                    <div>
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Tissue Composition</h4>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>"Granulation 80%"</li>
                            <li>"Slough 20%"</li>
                            <li>"Eschar 0%"</li>
                            <li>"Epithelial 10%"</li>
                        </ul>
                    </div>

                    <!-- Drainage -->
                    <div>
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Drainage & Odor</h4>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>"Moderate serous drainage"</li>
                            <li>"Scant purulent drainage"</li>
                            <li>"Odor present" / "No odor"</li>
                        </ul>
                    </div>

                    <!-- Debridement -->
                    <div>
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Debridement</h4>
                        <ul class="list-disc pl-5 space-y-1">
                            <li>"Debridement performed"</li>
                            <li>"Sharp debridement"</li>
                            <li>"Mechanical debridement"</li>
                            <li>"Enzymatic debridement"</li>
                        </ul>
                    </div>
                </div>

                    <!-- Navigation -->
                    <div class="col-span-1 md:col-span-2">
                        <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Navigation</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <ul class="list-disc pl-5 space-y-1">
                                <li>"Go to Assessment History"</li>
                                <li>"Open Photo Gallery"</li>
                            </ul>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>"Navigate to Wound Management"</li>
                                <li>"Go to Current Assessment"</li>
                            </ul>
                        </div>
                    </div>

                <!-- Advanced -->
                <div>
                    <h4 class="font-bold text-indigo-700 mb-2 border-b border-indigo-100">Conditions & Infection</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <span class="font-semibold block text-xs uppercase text-gray-500">Periwound</span>
                            <p>"Intact", "Macerated", "Erythema", "Edema", "Indurated"</p>
                        </div>
                        <div>
                            <span class="font-semibold block text-xs uppercase text-gray-500">Infection Signs</span>
                            <p>"Redness", "Swelling", "Warmth", "Increased Pain", "Purulent Drainage"</p>
                        </div>
                    </div>
                </div>

                <div class="bg-blue-50 p-3 rounded-md border border-blue-100">
                    <span class="font-bold text-blue-800">Example:</span>
                    <p class="text-blue-700">"Length 2.5, width 1.5, depth 0.2, no tunneling, moderate serous drainage, periwound is macerated."</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <button id="closeVoiceGuideBtnBottom" class="px-4 py-2 bg-blue-600 text-white font-bold rounded hover:bg-blue-700 transition">Got it</button>
            </div>
        </div>
    </div>

    <!-- Scale Input Modal (Removed) -->

    <script>
        // Define global flag for read-only mode
        window.isVisitSigned = <?php echo (isset($is_visit_signed) && $is_visit_signed) ? 'true' : 'false'; ?>;
    </script>

    <!-- NOTE: manual_measurement_logic.js must define a global object named ManualMeasurement -->
    <script src="wound_assessment_logic.js"></script>
    <script src="manual_measurement_logic.js"></script>
    <script src="js/voice_wound_assessment_logic.js"></script>
    <!-- Smart Command Logic is now in footer.php -->
    <!-- <script src="js/smart_command_logic.js"></script> -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // SmartCommandParser is auto-initialized globally
            // new SmartCommandParser('wound');
        });
    </script>
    <script>
        // FAB Logic
        document.addEventListener('DOMContentLoaded', () => {
            const fabToggle = document.getElementById('fab-toggle');
            const fabActions = document.getElementById('fab-actions');
            const iconMenu = document.getElementById('fab-icon-menu');
            const iconClose = document.getElementById('fab-icon-close');

            if(fabToggle) {
                fabToggle.addEventListener('click', () => {
                    fabActions.classList.toggle('hidden');
                    iconMenu.classList.toggle('hidden');
                    iconClose.classList.toggle('hidden');
                });
            }

            // Voice FAB -> Trigger existing mic button
            document.getElementById('fab-voice')?.addEventListener('click', () => {
                document.getElementById('smart_mic_btn')?.click();
                // Close menu after click
                fabToggle.click();
            });

            // Voice Guide FAB
            const voiceGuideModal = document.getElementById('voiceGuideModal');
            const closeVoiceGuideBtn = document.getElementById('closeVoiceGuideBtn');
            const closeVoiceGuideBtnBottom = document.getElementById('closeVoiceGuideBtnBottom');

            document.getElementById('fab-voice-guide')?.addEventListener('click', () => {
                voiceGuideModal.classList.remove('hidden');
                voiceGuideModal.classList.add('flex');
                fabToggle.click(); // Close FAB menu
            });

            const closeGuide = () => {
                voiceGuideModal.classList.add('hidden');
                voiceGuideModal.classList.remove('flex');
            };

            closeVoiceGuideBtn?.addEventListener('click', closeGuide);
            closeVoiceGuideBtnBottom?.addEventListener('click', closeGuide);
            
            // Close on outside click
            voiceGuideModal?.addEventListener('click', (e) => {
                if (e.target === voiceGuideModal) closeGuide();
            });

            // Save FAB -> Trigger existing save button
            document.getElementById('fab-save')?.addEventListener('click', () => {
                const saveBtn = document.getElementById('saveAssessmentBtn'); 
                if(saveBtn) saveBtn.click();
                fabToggle.click();
            });

            // Scroll Top
            document.getElementById('fab-top')?.addEventListener('click', () => {
                const scrollPanel = document.querySelector('.scroll-panel'); // The right panel
                if(scrollPanel) scrollPanel.scrollTo({ top: 0, behavior: 'smooth' });
                 fabToggle.click();
            });
        });

        // Initialize Voice Assistant
        document.addEventListener('DOMContentLoaded', () => {
            const voiceAssistant = new VoiceWoundAssistant({
                fields: [
                    { id: 'length_cm', label: 'Length (cm)', prompt: 'What is the length in centimeters?', type: 'number' },
                    { id: 'width_cm', label: 'Width (cm)', prompt: 'What is the width in centimeters?', type: 'number' },
                    { id: 'depth_cm', label: 'Depth (cm)', prompt: 'What is the depth in centimeters?', type: 'number' },
                    { id: 'tunneling_present', label: 'Tunneling?', prompt: 'Is tunneling present? Yes or No.', type: 'button_group', options: ['Yes', 'No'] },
                    { id: 'undermining_present', label: 'Undermining?', prompt: 'Is undermining present? Yes or No.', type: 'button_group', options: ['Yes', 'No'] },
                    { id: 'pain_level', label: 'Pain Level (0-10)', prompt: 'What is the pain level from 0 to 10?', type: 'button_group', options: ['0','1','2','3','4','5','6','7','8','9','10'] },
                    
                    // Tissue Composition
                    { id: 'granulation_percent', label: 'Granulation %', prompt: 'What is the granulation percentage?', type: 'number' },
                    { id: 'slough_percent', label: 'Slough %', prompt: 'What is the slough percentage?', type: 'number' },
                    { id: 'eschar_percent', label: 'Eschar %', prompt: 'What is the eschar percentage?', type: 'number' },
                    { id: 'epithelialization_percent', label: 'Epithelial %', prompt: 'What is the epithelial percentage?', type: 'number' },
                    
                    // Drainage
                    { id: 'exudate_amount', label: 'Drainage Amount', prompt: 'What is the drainage amount? None, Scant, Small, Moderate, or Large.', type: 'button_group', options: ['None', 'Scant', 'Small', 'Moderate', 'Large'] },
                    { id: 'drainage_type', label: 'Drainage Type', prompt: 'What is the drainage type? Serous, Purulent, Serosanguineous, or Clear.', type: 'select', options: ['None', 'Serous', 'Purulent', 'Serosanguineous', 'Clear'] },
                    { id: 'odor_present', label: 'Odor?', prompt: 'Is odor present? Yes or No.', type: 'button_group', options: ['Yes', 'No'] },
                    
                    // Multi-Selects
                    { id: 'periwound_condition', label: 'Periwound Condition', prompt: 'Describe the periwound condition. Intact, Macerated, Erythema, Edema, or Indurated.', type: 'multi_select', options: ['Intact', 'Macerated', 'Erythema', 'Edema', 'Indurated'] },
                    { id: 'signs_of_infection', label: 'Signs of Infection', prompt: 'Are there signs of infection? Redness, Swelling, Warmth, Pain, Purulent Drainage.', type: 'multi_select', options: ['Redness', 'Swelling', 'Warmth', 'Increased Pain', 'Purulent Drainage', 'Osteomyelitis', 'Cellulitis'] },
                    { id: 'exposed_structures_container', label: 'Exposed Structures', prompt: 'Are there exposed structures? Bone, Tendon, Muscle, etc.', type: 'checkbox_group', options: ['None', 'Bone', 'Tendon', 'Ligament', 'Muscle', 'Fascia', 'Hardware', 'Joint Capsule'] },
                    
                    // Debridement
                    { id: 'debridement_performed', label: 'Debridement Performed?', prompt: 'Was debridement performed? Yes or No.', type: 'button_group', options: ['Yes', 'No'] }
                ]
            });

            const startBtn = document.getElementById('startVoiceMeasureBtn');
            if (startBtn) {
                startBtn.addEventListener('click', () => voiceAssistant.start());
            }

            // Connect FAB Voice Button
            const fabVoice = document.getElementById('fab-voice');
            if (fabVoice) {
                fabVoice.addEventListener('click', () => voiceAssistant.start());
            }
            
            document.getElementById('closeVoiceBtn').addEventListener('click', () => voiceAssistant.stop());
            document.getElementById('voiceStopBtn').addEventListener('click', () => voiceAssistant.stop());
            document.getElementById('voiceSkipBtn').addEventListener('click', () => {
                voiceAssistant.speak("Skipping.");
                voiceAssistant.currentIndex++;
                voiceAssistant.askCurrentField();
            });
        });
    </script>

<?php if (isset($is_visit_signed) && $is_visit_signed): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Visit is signed. Enabling Read-Only Mode for Assessment.');

        // 1. Visual Banner
        const mainContainer = document.querySelector('main > div');
        if (mainContainer) {
            const banner = document.createElement('div');
            banner.className = 'w-full bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm rounded-r-md flex items-center justify-between col-span-full';
            banner.innerHTML = `
                <div class="flex items-center">
                    <i data-lucide="lock" class="w-6 h-6 mr-3 text-red-500"></i>
                    <div>
                        <p class="font-bold text-lg">Visit Finalized & Signed</p>
                        <p class="text-sm">This assessment is read-only.</p>
                    </div>
                </div>
                <span class="text-xs font-mono bg-red-100 px-2 py-1 rounded text-red-800">Signed on <?php echo date('M d, Y H:i', strtotime($signed_at_date)); ?></span>
            `;
            mainContainer.parentNode.insertBefore(banner, mainContainer);
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // 2. Hide Action Buttons
        const hideIds = [
            'toggleFileBtn', 'toggleCameraBtn', 
            'openAIMeasureModalBtn', 'openManualMeasureModalBtn', 'openAnnotationModalBtn',
            'copyLastAssessmentBtn', 'generatePlanBtn', 'saveAssessmentBtn',
            'addTunnelingLocation', 'addUnderminingLocation', 'openPushCalcBtn',
            'applyGraftBtn'
        ];
        hideIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });

        // 3. Lock Function
        function lockElement(el) {
            // Skip navigation
            if (el.closest('nav') || el.closest('#sidebar') || el.innerText.includes('Back') || el.innerText.includes('Gen. LMN')) return;
            
            // Allow "View Assessment" buttons in history/gallery
            if (el.classList.contains('view-assessment-btn')) return;

            // Disable inputs
            if (['INPUT', 'SELECT', 'TEXTAREA', 'BUTTON'].includes(el.tagName)) {
                el.disabled = true;
                el.classList.add('opacity-60', 'cursor-not-allowed');
            }
            
            // Disable checkboxes specifically
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.disabled = true;
            }
        }

        // 4. Initial Lock
        document.querySelectorAll('*').forEach(lockElement);

        // 5. MutationObserver
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        lockElement(node);
                        node.querySelectorAll('*').forEach(lockElement);
                    }
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
        // 6. Disable Canvas Interaction (Fabric.js)
        const canvases = document.querySelectorAll('.canvas-container');
        canvases.forEach(c => {
            c.style.pointerEvents = 'none';
        });
    });
</script>
<?php endif; ?>

<?php require_once 'templates/footer.php'; ?>