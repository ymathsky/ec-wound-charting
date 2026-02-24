<?php
// Filename: visit_narrative.php
// Description: Dictation Mode for visit charting. Allows continuous narration which is then processed by AI.

require_once 'templates/header.php';
require_once 'db_connect.php';

// Check database connection
if ($conn->connect_error) {
    echo '<div class="p-4 bg-red-100 border border-red-400 text-red-700 m-4 rounded">
            <strong class="font-bold">Database Error:</strong>
            <span class="block sm:inline">Unable to connect to MySQL. Please ensure the database server is running.</span>
            <span class="block text-xs mt-1">Details: ' . htmlspecialchars($conn->connect_error) . '</span>
          </div>';
    require_once 'templates/footer.php';
    exit;
}

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
// SECURITY: Always use session user_id — never allow user_id override via URL (IDOR risk)
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;

if ($patient_id <= 0) {
    echo '<div class="p-4 text-red-600">Invalid Patient ID</div>';
    require_once 'templates/footer.php';
    exit;
}

// Fetch basic patient info
$stmt = $conn->prepare("SELECT first_name, last_name, date_of_birth, patient_code, gender FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$patient_name = $patient ? $patient['first_name'] . ' ' . $patient['last_name'] : 'Unknown Patient';
$patient_dob = $patient && $patient['date_of_birth'] ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A';
$patient_age = $patient && $patient['date_of_birth'] ? date_diff(date_create($patient['date_of_birth']), date_create('today'))->y : 'N/A';
$patient_mrn = $patient ? $patient['patient_code'] : 'N/A';
$patient_gender = $patient ? $patient['gender'] : 'N/A';

// Fetch Past Visits
$past_visits = [];
try {
    $stmt_pv = $conn->prepare("SELECT note_id as id, note_date as visit_date, chief_complaint FROM patient_notes WHERE patient_id = ? ORDER BY note_date DESC LIMIT 5");
    if ($stmt_pv) {
        $stmt_pv->bind_param("i", $patient_id);
        $stmt_pv->execute();
        $res_pv = $stmt_pv->get_result();
        while ($row = $res_pv->fetch_assoc()) {
            $past_visits[] = $row;
        }
        $stmt_pv->close();
    }
} catch (Exception $e) {
    error_log("Failed to fetch past visits: " . $e->getMessage());
}

// Fetch Active Medications
$active_meds = [];
$stmt_meds = $conn->prepare("SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active' LIMIT 10");
if ($stmt_meds) {
    $stmt_meds->bind_param("i", $patient_id);
    $stmt_meds->execute();
    $res_meds = $stmt_meds->get_result();
    while ($row = $res_meds->fetch_assoc()) {
        $active_meds[] = $row;
    }
    $stmt_meds->close();
}

// Fetch Active Wounds
$active_wounds = [];
try {
    $stmt_wounds = $conn->prepare("SELECT wound_id, location, wound_type, date_onset FROM wounds WHERE patient_id = ? AND status = 'Active'");
    if ($stmt_wounds) {
        $stmt_wounds->bind_param("i", $patient_id);
        $stmt_wounds->execute();
        $res_wounds = $stmt_wounds->get_result();
        while ($row = $res_wounds->fetch_assoc()) {
            $active_wounds[] = $row;
        }
        $stmt_wounds->close();
    }
} catch (Exception $e) {
    // Fail silently if table doesn't exist yet
}

// Fetch draft if exists
$draft_narrative = "";
$draft_image_data = "";
$draft_mime_type = "";
$draft_saved_images = [];

$sql_draft = "SELECT draft_data FROM visit_drafts WHERE appointment_id = ?";
$stmt_d = $conn->prepare($sql_draft);
$stmt_d->bind_param("i", $appointment_id);
$stmt_d->execute();
$res_d = $stmt_d->get_result();
if ($row_d = $res_d->fetch_assoc()) {
    $d_data = json_decode($row_d['draft_data'], true);
    if (isset($d_data['narrative'])) {
        $draft_narrative = $d_data['narrative'];
    }
    if (isset($d_data['image_data'])) {
        $draft_image_data = $d_data['image_data'];
    }
    if (isset($d_data['mime_type'])) {
        $draft_mime_type = $d_data['mime_type'];
    }
    $draft_saved_images = isset($d_data['saved_images']) ? $d_data['saved_images'] : [];
}
$stmt_d->close();
?>

<style>
    /* Custom Scrollbar for cleaner UI */
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #c7c7c7;
        border-radius: 4px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
</style>

<div class="flex h-[calc(100vh-64px)] bg-gray-100 overflow-hidden">
    
    <!-- Grid Layout Container -->
    <div class="w-full max-w-[1920px] mx-auto p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 h-full pb-20 lg:pb-6">

        <!-- LEFT SIDEBAR: Patient Context -->
        <div id="col-patient" class="hidden lg:flex lg:col-span-3 flex-col gap-6 overflow-y-auto pr-2 h-full custom-scrollbar">
            
            <!-- Demographics Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                    <i data-lucide="user" class="w-5 h-5 mr-2 text-indigo-600"></i> Patient Context
                </h3>
                <div class="space-y-4 text-sm">
                    <div>
                        <span class="text-gray-500 block text-xs uppercase tracking-wide mb-1">Full Name</span>
                        <span class="font-bold text-gray-900 text-lg"><?php echo htmlspecialchars($patient_name); ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-gray-500 block text-xs uppercase tracking-wide mb-1">DOB (Age)</span>
                            <span class="font-medium text-gray-800"><?php echo $patient_dob; ?> <span class="text-gray-400">|</span> <?php echo $patient_age; ?>y</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block text-xs uppercase tracking-wide mb-1">Gender</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($patient_gender); ?></span>
                        </div>
                    </div>
                    <div>
                        <span class="text-gray-500 block text-xs uppercase tracking-wide mb-1">MRN</span>
                        <span class="font-mono text-xs text-indigo-700 bg-indigo-50 px-2 py-1 rounded inline-block border border-indigo-100"><?php echo htmlspecialchars($patient_mrn); ?></span>
                    </div>
                </div>
            </div>

            <!-- Clinical Status (Consolidated) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex-1">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                    <i data-lucide="activity" class="w-5 h-5 mr-2 text-rose-500"></i> Clinical Status
                </h3>
                
                <!-- Allergies Section -->
                <div class="mb-6">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 block">Allergies</span>
                    <div class="bg-green-50 border border-green-100 rounded-lg p-3 flex items-start">
                        <i data-lucide="check-circle" class="w-4 h-4 text-green-600 mr-2 mt-0.5 flex-shrink-0"></i>
                        <span class="text-green-800 text-sm font-medium leading-tight">No Known Drug Allergies</span>
                    </div>
                </div>

                <!-- Problems Section -->
                <div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 block">Active Problems</span>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start group">
                            <span class="w-1.5 h-1.5 bg-orange-400 rounded-full mt-1.5 mr-2 flex-shrink-0 group-hover:scale-125 transition-transform"></span>
                            <span>Type 2 Diabetes Mellitus</span>
                        </li>
                        <li class="flex items-start group">
                            <span class="w-1.5 h-1.5 bg-orange-400 rounded-full mt-1.5 mr-2 flex-shrink-0 group-hover:scale-125 transition-transform"></span>
                            <span>Chronic Kidney Disease, Stage 3</span>
                        </li>
                        <li class="flex items-start group">
                            <span class="w-1.5 h-1.5 bg-orange-400 rounded-full mt-1.5 mr-2 flex-shrink-0 group-hover:scale-125 transition-transform"></span>
                            <span>Hypertension</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- CENTER: Main Dictation Area -->
        <div id="col-main" class="flex lg:flex lg:col-span-6 flex-col h-full">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-gray-800 flex items-center">
                    <div class="bg-white p-1.5 rounded-lg shadow-sm border border-gray-200 mr-3">
                        <i data-lucide="mic" class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600"></i>
                    </div>
                    Dictation Mode
                </h1>
            </div>
            <div class="flex space-x-2 w-full sm:w-auto">
                <a href="dictation_guide.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>&user_id=<?php echo $user_id; ?>" target="_blank" class="px-3 py-2 bg-white border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 hover:text-indigo-600 transition shadow-sm flex-shrink-0" title="User Manual">
                    <i data-lucide="book-open" class="w-5 h-5"></i>
                </a>
                <a href="visit_summary.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition shadow-sm flex-1 sm:flex-none text-center">
                    Cancel
                </a>
                <button id="process-btn" class="px-5 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition shadow-md flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed ring-2 ring-offset-2 ring-transparent focus:ring-indigo-500 flex-1 sm:flex-none">
                    <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i>
                    Process
                </button>
            </div>
        </div>

        <!-- Dictation Area -->
        <div class="flex-1 bg-white rounded-xl shadow-lg border border-gray-200 flex flex-col overflow-hidden relative ring-1 ring-black/5">
            
            <!-- Tabs -->
            <div class="flex border-b border-gray-200 bg-white px-4">
                <button id="btn-tab-narrative" class="flex-1 py-4 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600 focus:outline-none transition-colors hover:bg-gray-50" onclick="switchMainTab('narrative')">
                    <i data-lucide="mic" class="w-4 h-4 inline mr-2"></i> Narrative
                </button>
                <button id="btn-tab-photo" class="flex-1 py-4 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none transition-colors hover:bg-gray-50" onclick="switchMainTab('photo')">
                    <i data-lucide="image" class="w-4 h-4 inline mr-2"></i> Photo & Annotation
                    <span id="photo-indicator" class="hidden ml-2 w-2 h-2 bg-red-500 rounded-full inline-block animate-pulse"></span>
                </button>
            </div>

            <!-- Tab: Narrative -->
            <div id="view-narrative" class="flex-1 flex flex-col relative h-full bg-white">
                <!-- Toolbar -->
                <div class="px-4 py-3 border-b border-gray-100 bg-white flex items-center justify-between flex-wrap gap-2 shadow-sm z-10">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                        <span id="status-text" class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Ready</span>
                    </div>

                     <!-- Dictation Mode Toggles -->
                    <div class="flex items-center space-x-2 border-r border-gray-200 pr-2 mr-2">
                         <label class="flex flex-col items-center cursor-pointer mr-1" title="Enable Cloud Dictation for better accuracy">
                            <input type="checkbox" id="cloud_mode_toggle" class="sr-only peer">
                            <div class="w-8 h-4 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-purple-600 relative"></div>
                            <span class="text-[10px] text-gray-500 font-medium mt-0.5">Cloud</span>
                        </label>
                    </div>
                    
                    <!-- Macros -->
                    <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar">
                        <button onclick="insertMacro('Normal Skin')" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors">+ Normal Skin</button>
                        <button onclick="insertMacro('Normal ROS')" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors">+ Normal ROS</button>
                        <button onclick="insertMacro('Wound Care Plan')" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-full hover:bg-indigo-100 border border-indigo-100 transition-colors">+ Std Wound Care</button>
                    </div>

                    <button id="clear-btn" class="text-xs font-medium text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded transition-colors">Clear</button>
                </div>

                <textarea id="narrative-text" class="flex-1 w-full p-8 text-lg text-gray-800 focus:outline-none resize-none leading-relaxed font-sans" placeholder="Click the microphone button and start speaking..."><?php echo htmlspecialchars($draft_narrative); ?></textarea>

                <!-- Floating Mic Button -->
                <div class="absolute bottom-10 right-10 z-20">
                    <span class="absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75 animate-ping hidden" id="mic-ping"></span>
                    <button id="mic-btn" class="relative w-16 h-16 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white rounded-full shadow-xl flex items-center justify-center transition-all transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-purple-300">
                        <i data-lucide="mic" class="w-7 h-7"></i>
                    </button>
                </div>
            </div>

            <!-- Tab: Photo -->
            <div id="view-photo" class="hidden flex-1 flex-col bg-gray-100 h-full overflow-y-auto custom-scrollbar">
                <div class="p-4 flex-1 flex flex-col h-full">
                    <!-- Empty State -->
                    <div id="photo-empty-state" class="<?php echo ($draft_image_data ? 'hidden' : ''); ?> flex flex-col items-center justify-center flex-1 border-2 border-dashed border-gray-300 rounded-xl bg-white min-h-[300px] m-4">
                        <div class="text-center p-8">
                            <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-lucide="camera" class="w-10 h-10 text-indigo-400"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-1">No photo uploaded</h3>
                            <p class="text-gray-500 mb-6 text-sm">Upload a wound image or take a photo to start annotating.</p>
                            
                            <input type="file" id="wound-image-input" accept="image/*" capture="environment" class="hidden">
                            <button id="camera-btn" class="px-6 py-2.5 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 shadow-md transition-all transform hover:-translate-y-0.5">
                                <i data-lucide="upload" class="w-4 h-4 inline mr-2"></i> Upload / Take Photo
                            </button>
                        </div>
                    </div>

                    <!-- Canvas Container -->
                    <div id="image-preview-container" class="<?php echo ($draft_image_data ? '' : 'hidden'); ?> flex-1 flex flex-col h-full">
                        <div class="flex flex-col items-start w-full h-full gap-3">
                            <!-- Annotation Toolbar -->
                            <div class="flex items-center justify-between w-full bg-white p-2 rounded-xl shadow-sm border border-gray-200 flex-wrap gap-2">
                                <!-- Left: Type & Tools -->
                                <div class="flex items-center gap-2">
                                    <select id="photo-type-select" class="text-xs font-medium border-gray-200 rounded-lg py-1.5 pl-2 pr-8 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 text-gray-700">
                                        <option value="Pre-debridement">Pre-debridement</option>
                                        <option value="Post-debridement">Post-debridement</option>
                                        <option value="Post-graft">Post-graft</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    
                                    <div class="h-6 w-px bg-gray-200 mx-1"></div>

                                    <div class="flex items-center bg-gray-100 rounded-lg p-1">
                                        <button id="tool-pen" class="p-1.5 bg-white text-indigo-600 rounded shadow-sm transition-all" title="Pen">
                                            <i data-lucide="pen-tool" class="w-4 h-4"></i>
                                        </button>
                                        <div class="flex space-x-1 px-2">
                                            <button class="color-btn w-4 h-4 rounded-full bg-red-500 ring-2 ring-offset-1 ring-transparent hover:ring-gray-300 transition-all" data-color="#ef4444"></button>
                                            <button class="color-btn w-4 h-4 rounded-full bg-yellow-400 ring-2 ring-offset-1 ring-transparent hover:ring-gray-300 transition-all" data-color="#facc15"></button>
                                            <button class="color-btn w-4 h-4 rounded-full bg-green-500 ring-2 ring-offset-1 ring-transparent hover:ring-gray-300 transition-all" data-color="#22c55e"></button>
                                            <button class="color-btn w-4 h-4 rounded-full bg-blue-500 ring-2 ring-offset-1 ring-transparent hover:ring-gray-300 transition-all" data-color="#3b82f6"></button>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-1">
                                        <button id="tool-undo" class="p-1.5 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded transition-colors" title="Undo">
                                            <i data-lucide="undo" class="w-4 h-4"></i>
                                        </button>
                                        <button id="tool-clear-draw" class="p-1.5 text-gray-500 hover:text-red-600 hover:bg-red-50 rounded transition-colors" title="Clear All">
                                            <i data-lucide="eraser" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Right: Actions -->
                                <div class="flex items-center gap-2">
                                    <button id="remove-image-btn" class="p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Discard">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                    <button id="btn-save-photo" class="px-4 py-1.5 bg-green-600 text-white text-xs font-bold rounded-lg shadow-sm hover:bg-green-700 flex items-center transition-colors">
                                        <i data-lucide="check" class="w-4 h-4 mr-1.5"></i> Save Photo
                                    </button>
                                </div>
                            </div>

                            <!-- Canvas Wrapper -->
                            <div class="relative flex-1 w-full border border-gray-200 shadow-inner rounded-xl overflow-hidden bg-gray-800 flex items-center justify-center">
                                <canvas id="annotation-canvas" class="block cursor-crosshair touch-none max-w-full max-h-full"></canvas>
                                <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 bg-black/60 backdrop-blur-sm text-white text-xs px-3 py-1 rounded-full pointer-events-none">
                                    Draw to highlight wound area
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Saved Photos Section -->
                    <div id="saved-photos-section" class="w-full mt-4 <?php echo (!empty($draft_saved_images) ? '' : 'hidden'); ?>">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-xs font-bold text-gray-500 uppercase flex items-center">
                                <i data-lucide="images" class="w-4 h-4 mr-1.5"></i> Saved Photos
                            </h4>
                            <span class="text-xs text-gray-400"><?php echo count($draft_saved_images); ?> photos</span>
                        </div>
                        <div id="saved-photos-strip" class="flex space-x-3 overflow-x-auto pb-2 min-h-[80px] custom-scrollbar">
                            <!-- Thumbnails injected here -->
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Instructions -->
        <div class="mt-4 text-center text-sm text-gray-400">
            <p>Speak naturally. Describe the patient's condition, vitals, wound measurements, and plan.</p>
        </div>
    </div> <!-- End Center Column -->

    <!-- RIGHT SIDEBAR: Clinical History -->
        <div id="col-history" class="hidden lg:flex lg:col-span-3 flex-col gap-6 overflow-y-auto pl-2 h-full custom-scrollbar">
            
            <!-- Past Visits Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2">
                    <i data-lucide="history" class="w-5 h-5 mr-2 text-blue-600"></i> Past Visits
                </h3>
                <?php if (count($past_visits) > 0): ?>
                    <ul class="space-y-3">
                        <?php foreach ($past_visits as $pv): ?>
                            <li>
                                <a href="visit_summary.php?appointment_id=<?php echo $pv['id']; ?>&patient_id=<?php echo $patient_id; ?>" target="_blank" class="block p-3 rounded-lg bg-gray-50 hover:bg-blue-50 border border-gray-100 hover:border-blue-200 transition-colors group">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="font-semibold text-gray-800 text-sm"><?php echo date('M d, Y', strtotime($pv['visit_date'])); ?></span>
                                        <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400 group-hover:text-blue-500"></i>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($pv['chief_complaint'] ?: 'Follow-up'); ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                        <i data-lucide="calendar-off" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                        <p class="text-sm text-gray-500 italic">No previous visits found.</p>
                    </div>
                <?php endif; ?>
                <div class="mt-4 text-center">
                    <a href="patient_chart_history.php?id=<?php echo $patient_id; ?>" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wide">View Full History</a>
                </div>
            </div>

            <!-- Active Medications Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex-1 flex flex-col min-h-0">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2 flex-shrink-0">
                    <i data-lucide="pill" class="w-5 h-5 mr-2 text-teal-600"></i> Active Medications
                </h3>
                
                <div class="overflow-y-auto custom-scrollbar pr-1 flex-1">
                    <?php if (count($active_meds) > 0): ?>
                        <ul class="space-y-2">
                            <?php foreach ($active_meds as $med): ?>
                                <li class="p-3 rounded-lg bg-gray-50 border border-gray-100 hover:border-teal-200 transition-colors group">
                                    <div class="font-semibold text-gray-800 text-sm mb-1 group-hover:text-teal-700"><?php echo htmlspecialchars($med['drug_name']); ?></div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <span class="bg-white px-1.5 py-0.5 rounded border border-gray-200 mr-2"><?php echo htmlspecialchars($med['dosage']); ?></span>
                                        <span><?php echo htmlspecialchars($med['frequency']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                            <i data-lucide="pill-off" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-sm text-gray-500 italic">No active medications.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Active Wounds Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 mt-0 flex-1 flex flex-col min-h-0">
                <h3 class="font-bold text-gray-800 mb-4 flex items-center border-b border-gray-100 pb-2 flex-shrink-0">
                    <i data-lucide="bandage" class="w-5 h-5 mr-2 text-rose-600"></i> Active Wounds
                </h3>
                
                <div class="overflow-y-auto custom-scrollbar pr-1 flex-1">
                    <?php if (count($active_wounds) > 0): ?>
                        <ul class="space-y-2">
                            <?php foreach ($active_wounds as $wound): ?>
                                <li class="p-3 rounded-lg bg-rose-50 border border-rose-100 hover:border-rose-200 transition-colors group">
                                    <div class="font-semibold text-gray-800 text-sm mb-1 group-hover:text-rose-700">
                                        <?php echo htmlspecialchars($wound['location']); ?>
                                    </div>
                                    <div class="flex items-center text-xs text-gray-500">
                                        <span class="bg-white px-1.5 py-0.5 rounded border border-rose-100 mr-2 text-rose-800 font-medium">
                                            <?php echo htmlspecialchars($wound['wound_type']); ?>
                                        </span>
                                        <?php if(!empty($wound['date_onset'])): ?>
                                        <span class="text-gray-400">Since <?php echo date('M d, Y', strtotime($wound['date_onset'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                            <i data-lucide="shield-check" class="w-8 h-8 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-sm text-gray-500 italic">No active wounds.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

    </div>
</div>

<!-- Processing Modal -->
<div id="processing-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg p-8 max-w-md w-full text-center shadow-2xl">
        <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-indigo-600 mx-auto mb-4"></div>
        <h3 class="text-xl font-bold text-gray-800 mb-2">Processing Narrative</h3>
        <p class="text-gray-600">AI is analyzing your dictation and structuring the visit note...</p>
    </div>
</div>

<!-- Review Modal -->
<div id="review-modal" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm z-50 hidden flex items-center justify-center overflow-y-auto p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl flex flex-col max-h-[92vh] overflow-hidden">

        <!-- Header -->
        <div class="px-7 py-5 flex justify-between items-center border-b border-slate-100">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-200 flex-shrink-0">
                    <i data-lucide="check-circle" class="w-5 h-5 text-white"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800 leading-tight">Review AI Extraction</h2>
                    <p class="text-xs text-slate-400 mt-0.5">Verify and edit the structured data before saving to chart.</p>
                </div>
            </div>
            <button id="close-review-btn" class="text-slate-400 hover:text-slate-600 hover:bg-slate-100 p-2 rounded-lg transition-colors">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Tabs Navigation -->
        <div class="flex gap-1 px-7 pt-4 pb-0 bg-white border-b border-slate-100">
            <button class="review-tab-btn flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-indigo-600 border-b-2 border-indigo-600 focus:outline-none transition-all rounded-t-lg -mb-px" data-target="tab-soap">
                <i data-lucide="file-text" class="w-4 h-4"></i> SOAP Note
            </button>
            <button class="review-tab-btn flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 border-b-2 border-transparent focus:outline-none transition-all rounded-t-lg -mb-px hover:bg-slate-50" data-target="tab-clinical">
                <i data-lucide="activity" class="w-4 h-4"></i> Clinical Data
            </button>
            <button class="review-tab-btn flex items-center gap-2 px-5 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 border-b-2 border-transparent focus:outline-none transition-all rounded-t-lg -mb-px hover:bg-slate-50" data-target="tab-wounds">
                <i data-lucide="target" class="w-4 h-4"></i> Wounds & Procedures
            </button>
        </div>

        <!-- Scrollable Content -->
        <div class="p-6 overflow-y-auto flex-1 bg-slate-50/60 space-y-5">

            <!-- TAB 1: SOAP Note -->
            <div id="tab-soap" class="review-tab-content space-y-5">

                <!-- Subjective & Objective card -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2.5">
                        <i data-lucide="file-text" class="w-4 h-4 text-indigo-500"></i>
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Subjective & Objective</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Chief Complaint -->
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Chief Complaint</label>
                            <input type="text" id="review-cc"
                                class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all placeholder-slate-300"
                                placeholder="e.g. Wound care assessment">
                        </div>
                        <!-- HPI & ROS -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">HPI (History of Present Illness)</label>
                                <textarea id="review-hpi" rows="6"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all leading-relaxed resize-none placeholder-slate-300"
                                    placeholder="Patient history..."></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">ROS (Review of Systems)</label>
                                <textarea id="review-ros" rows="6"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all leading-relaxed resize-none placeholder-slate-300"
                                    placeholder="System review..."></textarea>
                            </div>
                        </div>
                        <!-- Subjective other & Objective -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Subjective (Other)</label>
                                <textarea id="review-subj" rows="4"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all resize-none placeholder-slate-300"
                                    placeholder="Additional subjective info..."></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Objective</label>
                                <textarea id="review-obj" rows="4"
                                    class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all resize-none placeholder-slate-300"
                                    placeholder="Objective findings..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assessment & Plan card -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2.5">
                        <i data-lucide="clipboard-check" class="w-4 h-4 text-indigo-500"></i>
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Assessment & Plan</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Assessment</label>
                                <textarea id="review-assess" rows="7"
                                    class="w-full px-3.5 py-2.5 border border-amber-200 rounded-lg bg-amber-50/40 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-amber-400 focus:bg-white transition-all leading-relaxed resize-none placeholder-slate-300"
                                    placeholder="Clinical assessment..."></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Plan</label>
                                <textarea id="review-plan" rows="7"
                                    class="w-full px-3.5 py-2.5 border border-emerald-200 rounded-lg bg-emerald-50/40 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 focus:bg-white transition-all leading-relaxed resize-none placeholder-slate-300"
                                    placeholder="Care plan..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Clinical Data -->
            <div id="tab-clinical" class="review-tab-content hidden space-y-5">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Diagnoses -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <i data-lucide="stethoscope" class="w-4 h-4 text-indigo-500"></i>
                                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Diagnoses</h3>
                            </div>
                            <span class="text-xs font-bold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full border border-indigo-100">ICD-10</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <div id="review-diagnosis-container" class="space-y-3"></div>
                            <button onclick="addEmptyDiagnosis()"
                                class="mt-2 w-full py-2.5 border-2 border-dashed border-slate-200 rounded-lg text-slate-400 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center text-sm font-medium gap-1.5">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add Diagnosis
                            </button>
                        </div>
                    </div>

                    <!-- Medications -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <i data-lucide="pill" class="w-4 h-4 text-indigo-500"></i>
                                <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Medications</h3>
                            </div>
                            <span class="text-xs font-bold bg-indigo-50 text-indigo-600 px-2.5 py-1 rounded-full border border-indigo-100">Rx</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <div id="review-medication-container" class="space-y-3"></div>
                            <button onclick="addEmptyMedication()"
                                class="mt-2 w-full py-2.5 border-2 border-dashed border-slate-200 rounded-lg text-slate-400 hover:border-indigo-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all flex items-center justify-center text-sm font-medium gap-1.5">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add Medication
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Vitals -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2.5">
                        <i data-lucide="heart-pulse" class="w-4 h-4 text-rose-500"></i>
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Vitals</h3>
                    </div>
                    <div class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                        <?php
                        $vitals = [
                            ['id'=>'review-bp',     'label'=>'Blood Pressure',  'type'=>'text',   'placeholder'=>'120/80',  'icon'=>'heart',       'unit'=>'mmHg'],
                            ['id'=>'review-hr',     'label'=>'Heart Rate',      'type'=>'number', 'placeholder'=>'72',      'icon'=>'activity',    'unit'=>'bpm'],
                            ['id'=>'review-rr',     'label'=>'Resp. Rate',      'type'=>'number', 'placeholder'=>'16',      'icon'=>'wind',        'unit'=>'brpm'],
                            ['id'=>'review-o2',     'label'=>'O₂ Saturation',   'type'=>'number', 'placeholder'=>'98',      'icon'=>'percent',     'unit'=>'%'],
                            ['id'=>'review-temp',   'label'=>'Temperature',     'type'=>'number', 'placeholder'=>'36.6',    'icon'=>'thermometer', 'unit'=>'°C'],
                            ['id'=>'review-weight', 'label'=>'Weight',          'type'=>'number', 'placeholder'=>'70',      'icon'=>'scale',       'unit'=>'kg'],
                            ['id'=>'review-height', 'label'=>'Height',          'type'=>'number', 'placeholder'=>'170',     'icon'=>'ruler',       'unit'=>'cm'],
                        ];
                        foreach ($vitals as $v): ?>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5"><?= $v['label'] ?></label>
                            <div class="relative">
                                <input type="<?= $v['type'] ?>" id="<?= $v['id'] ?>"
                                    <?= $v['type']==='number' ? 'step="any"' : '' ?>
                                    placeholder="<?= $v['placeholder'] ?>"
                                    class="w-full pl-3.5 pr-12 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-indigo-400 focus:bg-white transition-all">
                                <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400 font-medium pointer-events-none"><?= $v['unit'] ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Wounds & Procedures -->
            <div id="tab-wounds" class="review-tab-content hidden space-y-5">

                <!-- Photo preview -->
                <div id="review-image-container" class="hidden bg-indigo-50 rounded-xl border border-indigo-100 p-5">
                    <div class="flex items-center gap-2 mb-3">
                        <i data-lucide="image" class="w-4 h-4 text-indigo-600"></i>
                        <h4 class="text-sm font-bold text-indigo-800">Photos to be Saved</h4>
                    </div>
                    <div id="review-images-list" class="flex flex-wrap gap-3"></div>
                    <p class="text-xs text-indigo-500 mt-3 flex items-start gap-1.5">
                        <i data-lucide="info" class="w-3.5 h-3.5 flex-shrink-0 mt-0.5"></i>
                        Photos will be automatically linked to the <strong class="font-semibold">first wound</strong> listed below.
                    </p>
                </div>

                <!-- Wound Assessments -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i data-lucide="target" class="w-4 h-4 text-indigo-500"></i>
                            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Wound Assessments</h3>
                        </div>
                        <span class="text-xs font-bold bg-green-50 text-green-700 px-2.5 py-1 rounded-full border border-green-100">Auto-detected</span>
                    </div>
                    <div class="p-5">
                        <div id="review-wounds-container" class="space-y-4">
                            <div class="flex flex-col items-center justify-center py-10 text-center rounded-lg border-2 border-dashed border-slate-200 bg-slate-50">
                                <i data-lucide="scan-search" class="w-8 h-8 text-slate-300 mb-2"></i>
                                <p class="text-sm text-slate-400 font-medium">No wounds detected</p>
                                <p class="text-xs text-slate-300">Dictate wound details for AI extraction</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Procedure -->
                <div id="review-procedure-section" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2.5">
                        <i data-lucide="scissors" class="w-4 h-4 text-indigo-500"></i>
                        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wide">Procedure Performed</h3>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Type</label>
                                <input type="text" id="review-proc-type" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Location</label>
                                <input type="text" id="review-proc-loc" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Dimensions</label>
                                <input type="text" id="review-proc-dims" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Depth</label>
                                <input type="text" id="review-proc-depth" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Instrument</label>
                                <input type="text" id="review-proc-inst" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Narrative Description</label>
                            <textarea id="review-proc-narrative" rows="3" class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:bg-white transition-all resize-none"></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="px-7 py-4 border-t border-slate-100 bg-white flex justify-between items-center">
            <p class="text-xs text-slate-400 flex items-center gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 text-indigo-400"></i>
                Review all tabs before saving.
            </p>
            <div class="flex items-center gap-3">
                <button id="cancel-review-btn"
                    class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                    Cancel
                </button>
                <button id="confirm-save-btn"
                    class="px-6 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-all shadow-md shadow-indigo-200 hover:shadow-lg flex items-center gap-2">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Confirm & Save to Chart
                </button>
            </div>
        </div>

    </div>
</div>



<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    lucide.createIcons();

    const micBtn = document.getElementById('mic-btn');
    const narrativeText = document.getElementById('narrative-text');
    const statusText = document.getElementById('status-text');
    const processBtn = document.getElementById('process-btn');
    const clearBtn = document.getElementById('clear-btn');
    const processingModal = document.getElementById('processing-modal');
    
    // Image Elements
    const cameraBtn = document.getElementById('camera-btn');
    const woundImageInput = document.getElementById('wound-image-input');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    const photoEmptyState = document.getElementById('photo-empty-state');
    const removeImageBtn = document.getElementById('remove-image-btn');
    const btnSavePhoto = document.getElementById('btn-save-photo');
    const savedPhotosSection = document.getElementById('saved-photos-section');
    const savedPhotosStrip = document.getElementById('saved-photos-strip');
    const photoIndicator = document.getElementById('photo-indicator');
    
    // Canvas Elements
    const canvas = document.getElementById('annotation-canvas');
    const ctx = canvas.getContext('2d');
    const toolUndo = document.getElementById('tool-undo');
    const toolClearDraw = document.getElementById('tool-clear-draw');
    const colorBtns = document.querySelectorAll('.color-btn');
    
    // State
    let savedImages = <?php echo json_encode($draft_saved_images); ?> || []; // Array of { base64, mime }
    let currentImageBase64 = <?php echo json_encode($draft_image_data); ?>;
    let currentImageMime = <?php echo json_encode($draft_mime_type); ?>;
    
    // --- Macros Logic ---
    const MACROS = {
        'Normal Skin': "Skin is warm and dry. No rashes, lesions, or erythema noted outside of the wound area. Capillary refill is less than 3 seconds. Turgor is normal.",
        'Normal ROS': "Review of Systems: Constitutional: Negative for fever or chills. Cardiovascular: Negative for chest pain. Respiratory: Negative for shortness of breath. Musculoskeletal: Negative for new joint pain.",
        'Wound Care Plan': "Cleanse wound with normal saline. Apply hydrogel to wound bed. Cover with foam dressing. Secure with tape. Change dressing every 3 days or if soiled."
    };

    window.insertMacro = function(macroName) {
        const text = MACROS[macroName];
        if (!text) return;

        const currentText = narrativeText.value;
        // Append with a newline if text exists
        if (currentText.length > 0 && !currentText.endsWith('\n')) {
            narrativeText.value = currentText + "\n" + text;
        } else {
            narrativeText.value = currentText + text;
        }
        
        // Trigger autosave
        narrativeText.dispatchEvent(new Event('input'));
        narrativeText.scrollTop = narrativeText.scrollHeight;
    };

    // Cloud Dictation State
    let isCloudMode = false;
    let mediaRecorder = null;
    let audioChunks = [];
    const cloudToggle = document.getElementById('cloud_mode_toggle');
    if (cloudToggle) {
        cloudToggle.addEventListener('change', (e) => {
            isCloudMode = e.target.checked;
            // If already recording, stop so user can restart in new mode
            if (isRecording) {
                if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                    stopCloudRecording();
                } else if (recognition) {
                    recognition.stop();
                }
            }
        });
    }

    // Drawing State
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;
    let drawColor = '#ef4444'; // Default Red
    let drawHistory = []; // Array of ImageData
    let baseImage = new Image(); // The underlying photo

    // Initialize from PHP Draft (Legacy single image support + potential array support later)
    // For now, if there's a draft image, we load it as the "current" one.
    if (currentImageBase64 && currentImageMime) {
        loadImageToCanvas(`data:${currentImageMime};base64,${currentImageBase64}`, false);
        photoIndicator.classList.remove('hidden');
    }
    
    // Initialize Saved Photos Strip
    if (savedImages.length > 0) {
        renderSavedImages();
    }

    function loadImageToCanvas(src, autoSwitch = true) {
        baseImage = new Image();
        baseImage.onload = function() {
            // Set canvas size to match image (max width constraint for UI?)
            // For high quality, keep original resolution but scale via CSS
            // Or scale down if huge. Let's limit max dimension to 1024px for performance.
            let width = baseImage.width;
            let height = baseImage.height;
            const MAX_DIM = 1024;
            
            if (width > MAX_DIM || height > MAX_DIM) {
                if (width > height) {
                    height *= MAX_DIM / width;
                    width = MAX_DIM;
                } else {
                    width *= MAX_DIM / height;
                    height = MAX_DIM;
                }
            }

            canvas.width = width;
            canvas.height = height;
            
            // CSS sizing for display (responsive)
            // canvas.style.maxWidth = '100%';
            // canvas.style.height = 'auto';
            // canvas.style.maxHeight = '400px'; // Limit height in UI

            ctx.drawImage(baseImage, 0, 0, width, height);
            saveHistory(); // Save initial state
            
            // UI Updates
            photoEmptyState.classList.add('hidden');
            imagePreviewContainer.classList.remove('hidden');
            photoIndicator.classList.remove('hidden');
            
            // Switch to photo tab to show the user
            if (autoSwitch) {
                switchMainTab('photo');
            }
        };
        baseImage.src = src;
    }

    // --- Drawing Logic ---
    function startDrawing(e) {
        isDrawing = true;
        [lastX, lastY] = getCoords(e);
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault(); // Prevent scrolling on touch
        
        const [x, y] = getCoords(e);
        
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.strokeStyle = drawColor;
        ctx.lineWidth = 5; // Thicker line for visibility
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        
        [lastX, lastY] = [x, y];
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            saveHistory();
            updateCurrentData();
        }
    }

    function getCoords(e) {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        
        let clientX, clientY;
        if (e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        } else {
            clientX = e.clientX;
            clientY = e.clientY;
        }
        
        return [
            (clientX - rect.left) * scaleX,
            (clientY - rect.top) * scaleY
        ];
    }

    function saveHistory() {
        if (drawHistory.length > 10) drawHistory.shift(); // Limit history
        drawHistory.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
    }

    function updateCurrentData() {
        // Update the global variables used by Save/Process
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        const parts = dataUrl.split(',');
        currentImageMime = 'image/jpeg';
        currentImageBase64 = parts[1];
        
        // Trigger autosave
        narrativeText.dispatchEvent(new Event('input'));
    }

    // Event Listeners for Drawing
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    canvas.addEventListener('touchstart', startDrawing, {passive: false});
    canvas.addEventListener('touchmove', draw, {passive: false});
    canvas.addEventListener('touchend', stopDrawing);

    // Toolbar Actions
    toolUndo.addEventListener('click', () => {
        if (drawHistory.length > 1) {
            drawHistory.pop(); // Remove current state
            const previousState = drawHistory[drawHistory.length - 1];
            ctx.putImageData(previousState, 0, 0);
            updateCurrentData();
        } else if (drawHistory.length === 1) {
            // Revert to base image
            ctx.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
            updateCurrentData();
        }
    });

    // Save Photo to List
    // Render initial saved images
    renderSavedImages();

    btnSavePhoto.addEventListener('click', () => {
        if (!baseImage.src && !currentImageBase64) {
            alert("No image to save.");
            return;
        }

        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        const parts = dataUrl.split(',');
        const typeSelect = document.getElementById('photo-type-select');
        
        const newImage = {
            base64: parts[1],
            mime: 'image/jpeg',
            type: typeSelect ? typeSelect.value : 'Other',
            timestamp: Date.now()
        };

        savedImages.push(newImage);
        renderSavedImages();
        
        // Clear current canvas for next photo
        removeImageBtn.click();
        
        // Trigger autosave
        narrativeText.dispatchEvent(new Event('input'));
        
        showToast("Photo saved to list");
    });

    function renderSavedImages() {
        savedPhotosStrip.innerHTML = '';
        if (savedImages.length > 0) {
            savedPhotosSection.classList.remove('hidden');
            savedImages.forEach((img, index) => {
                const div = document.createElement('div');
                div.className = 'relative flex-shrink-0 w-20 h-20 border border-gray-200 rounded bg-gray-100 group';
                div.innerHTML = `
                    <img src="data:${img.mime};base64,${img.base64}" 
                         class="w-full h-full object-cover rounded cursor-pointer hover:opacity-80 transition" 
                         onclick="editSavedImage(${index})"
                         title="Click to edit">
                    <div class="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white text-[10px] truncate px-1 text-center pointer-events-none">
                        ${img.type || 'Other'}
                    </div>
                    <button onclick="removeSavedImage(${index})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-0.5 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity z-10">
                        <i data-lucide="x" class="w-3 h-3"></i>
                    </button>
                `;
                savedPhotosStrip.appendChild(div);
            });
            lucide.createIcons();
        } else {
            savedPhotosSection.classList.add('hidden');
        }
    }

    window.editSavedImage = function(index) {
        const img = savedImages[index];
        
        // Load to canvas
        loadImageToCanvas(`data:${img.mime};base64,${img.base64}`);
        
        // Set type
        const typeSelect = document.getElementById('photo-type-select');
        if (typeSelect) typeSelect.value = img.type || 'Other';
        
        // Remove from list (so it can be re-saved as new version)
        savedImages.splice(index, 1);
        renderSavedImages();
        
        // Show UI
        imagePreviewContainer.classList.remove('hidden');
        photoEmptyState.classList.add('hidden');
        photoIndicator.classList.remove('hidden');
        
        // Switch to photo tab if not already
        switchMainTab('photo');
        
        showToast("Image loaded for editing");
    };

    window.removeSavedImage = function(index) {
        if(confirm('Remove this saved photo?')) {
            savedImages.splice(index, 1);
            renderSavedImages();
            narrativeText.dispatchEvent(new Event('input'));
        }
    };

    function showToast(msg) {
        // Simple toast implementation
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-gray-800 text-white px-4 py-2 rounded shadow-lg z-50 text-sm fade-in-up';
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    toolClearDraw.addEventListener('click', () => {
        if (confirm('Clear all annotations?')) {
            ctx.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
            saveHistory();
            updateCurrentData();
        }
    });

    colorBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            // Reset rings
            colorBtns.forEach(b => b.classList.remove('ring-2', 'ring-offset-1', 'ring-gray-300'));
            // Set active
            btn.classList.add('ring-2', 'ring-offset-1', 'ring-gray-300');
            drawColor = btn.dataset.color;
        });
    });
    
    // Review Modal Elements
    const reviewModal = document.getElementById('review-modal');
    const closeReviewBtn = document.getElementById('close-review-btn');
    const cancelReviewBtn = document.getElementById('cancel-review-btn');
    const confirmSaveBtn = document.getElementById('confirm-save-btn');

    // --- Safety Net: Local Storage & Server Autosave ---
    const STORAGE_KEY = `ec_narrative_draft_<?php echo $appointment_id; ?>`;
    const saveIndicator = document.createElement('span');
    saveIndicator.className = 'text-xs text-gray-400 ml-2 hidden';
    saveIndicator.innerHTML = '<i data-lucide="save" class="w-3 h-3 inline mr-1"></i>Draft saved';
    document.getElementById('status-text').parentNode.appendChild(saveIndicator);

    // Load Draft from LocalStorage if PHP didn't load anything
    if (!narrativeText.value.trim()) {
        const savedDraft = localStorage.getItem(STORAGE_KEY);
        if (savedDraft) {
            narrativeText.value = savedDraft;
            statusText.textContent = "Restored from local draft";
            setTimeout(() => statusText.textContent = "Ready to record", 2000);
        }
    }

    // Server Autosave Logic
    let autosaveTimeout;
    const AUTOSAVE_DELAY = 3000; // 3 seconds

    const saveDraftToServer = async () => {
        const text = narrativeText.value;
        
        // Capture current canvas if active
        let activeImage = null;
        let activeMime = null;
        let activeType = 'Other';
        
        const typeSelect = document.getElementById('photo-type-select');
        if (typeSelect) activeType = typeSelect.value;

        if (baseImage.src) {
             const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
             const parts = dataUrl.split(',');
             activeImage = parts[1];
             activeMime = 'image/jpeg';
        } else if (currentImageBase64) {
             activeImage = currentImageBase64;
             activeMime = currentImageMime;
        }

        const draftData = { 
            narrative: text,
            image_data: activeImage,
            mime_type: activeMime,
            image_type: activeType,
            saved_images: savedImages
        };

        try {
            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_draft',
                    patient_id: <?php echo $patient_id; ?>,
                    appointment_id: <?php echo $appointment_id; ?>,
                    user_id: <?php echo $user_id; ?>,
                    draft_data: draftData
                })
            });
            const res = await response.json();
            if (res.success) {
                saveIndicator.innerHTML = '<i data-lucide="cloud" class="w-3 h-3 inline mr-1"></i>Saved to cloud';
                saveIndicator.classList.remove('hidden');
                setTimeout(() => saveIndicator.classList.add('hidden'), 2000);
            }
        } catch (e) {
            console.error("Autosave failed", e);
        }
    };

    // Save Draft on Input
    narrativeText.addEventListener('input', () => {
        // Local storage (immediate)
        localStorage.setItem(STORAGE_KEY, narrativeText.value);
        
        // Server side (debounced)
        clearTimeout(autosaveTimeout);
        saveIndicator.innerHTML = '<i data-lucide="loader" class="w-3 h-3 inline mr-1 animate-spin"></i>Saving...';
        saveIndicator.classList.remove('hidden');
        
        autosaveTimeout = setTimeout(saveDraftToServer, AUTOSAVE_DELAY);
    });

    let recognition;
    let isRecording = false;
    let textAddedAsInterim = '';

    // Initialize Web Speech API
    if ('webkitSpeechRecognition' in window) {
        recognition = new webkitSpeechRecognition();
        recognition.continuous = true;
        recognition.interimResults = true;
        recognition.lang = 'en-US';

        recognition.onstart = function() {
            isRecording = true;
            textAddedAsInterim = '';
            micBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
            micBtn.classList.add('bg-red-500', 'hover:bg-red-600', 'animate-pulse');
            micBtn.innerHTML = '<i data-lucide="mic-off" class="w-8 h-8"></i>';
            lucide.createIcons();
            statusText.textContent = "Listening...";
            statusText.classList.add('text-red-500', 'font-semibold');
        };

        recognition.onend = function() {
            isRecording = false;
            textAddedAsInterim = '';
            micBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
            micBtn.classList.remove('bg-red-500', 'hover:bg-red-600', 'animate-pulse');
            micBtn.innerHTML = '<i data-lucide="mic" class="w-8 h-8"></i>';
            lucide.createIcons();
            statusText.textContent = "Ready to record";
            statusText.classList.remove('text-red-500', 'font-semibold');
        };

        recognition.onresult = function(event) {
            let interimTranscript = '';
            let finalTranscript = '';

            for (let i = event.resultIndex; i < event.results.length; ++i) {
                if (event.results[i].isFinal) {
                    let transcript = event.results[i][0].transcript;
                    let lowerTranscript = transcript.toLowerCase().trim();

                    // --- Voice Commands ---
                    // Handle "EC" (often transcribed as "easy" or "e c")
                    if (lowerTranscript.includes('ec process note') || lowerTranscript.includes('easy process note') || lowerTranscript.includes('e c process note') || lowerTranscript.includes('ec analyze') || lowerTranscript.includes('easy analyze')) {
                        showCommandFeedback('Processing Note...');
                        processBtn.click();
                        continue; // Skip appending command
                    }
                    if (lowerTranscript.includes('ec clear narrative') || lowerTranscript.includes('easy clear narrative') || lowerTranscript.includes('ec clear text') || lowerTranscript.includes('easy clear text')) {
                        showCommandFeedback('Clearing Text...');
                        clearBtn.click();
                        continue;
                    }
                    if (lowerTranscript.includes('ec stop listening') || lowerTranscript.includes('easy stop listening') || lowerTranscript.includes('ec stop recording') || lowerTranscript.includes('easy stop recording')) {
                        showCommandFeedback('Stopping Recording...');
                        recognition.stop();
                        continue;
                    }
                    
                    // Macro Voice Commands
                    if (lowerTranscript.includes('insert normal skin')) {
                        showCommandFeedback('Inserting Normal Skin Exam...');
                        insertMacro('Normal Skin');
                        continue;
                    }
                    if (lowerTranscript.includes('insert normal review') || lowerTranscript.includes('insert normal ros')) {
                        showCommandFeedback('Inserting Normal ROS...');
                        insertMacro('Normal ROS');
                        continue;
                    }
                    if (lowerTranscript.includes('insert wound care plan') || lowerTranscript.includes('insert standard plan')) {
                        showCommandFeedback('Inserting Standard Plan...');
                        insertMacro('Wound Care Plan');
                        continue;
                    }

                    finalTranscript += transcript;
                } else {
                    interimTranscript += event.results[i][0].transcript;
                }
            }

            let content = narrativeText.value;

            // 1. Remove previous interim text if it exists at the end
            if (textAddedAsInterim.length > 0 && content.endsWith(textAddedAsInterim)) {
                content = content.substring(0, content.length - textAddedAsInterim.length);
            }

            // 2. Append Final
            if (finalTranscript) {
                if (content.length > 0 && !content.endsWith(' ')) {
                    content += ' ';
                }
                content += finalTranscript;
            }

            // 3. Append Interim
            textAddedAsInterim = ''; // Reset
            if (interimTranscript) {
                if (content.length > 0 && !content.endsWith(' ')) {
                    textAddedAsInterim = ' ' + interimTranscript;
                } else {
                    textAddedAsInterim = interimTranscript;
                }
                content += textAddedAsInterim;
            }

            narrativeText.value = content;
            narrativeText.scrollTop = narrativeText.scrollHeight;
            
            // Trigger autosave
            narrativeText.dispatchEvent(new Event('input'));
        };

        recognition.onerror = function(event) {
            if (event.error === 'no-speech') {
                console.log('Speech recognition info: no speech detected.');
                return;
            }
            if (event.error === 'aborted') {
                return;
            }
            console.error('Speech recognition error', event.error);
            statusText.textContent = "Error: " + event.error;
        };
    } else {
        alert("Web Speech API is not supported in this browser. Please use Chrome.");
        micBtn.disabled = true;
    }

    function showCommandFeedback(msg) {
        const feedback = document.createElement('div');
        feedback.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-indigo-800 text-white px-6 py-3 rounded-full shadow-lg z-50 animate-bounce font-bold flex items-center';
        feedback.innerHTML = `<i data-lucide="mic" class="w-5 h-5 mr-2"></i> ${msg}`;
        document.body.appendChild(feedback);
        lucide.createIcons();
        setTimeout(() => feedback.remove(), 3000);
    }

    // Toggle Recording
    micBtn.addEventListener('click', () => {
        if (isRecording) {
            if (isCloudMode) {
                stopCloudRecording();
            } else {
                recognition.stop();
            }
        } else {
            if (isCloudMode) {
                startCloudRecording();
            } else {
                recognition.start();
            }
        }
    });

    // --- Cloud Recording Logic ---
    async function startCloudRecording() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            let options = {};
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) options = { mimeType: 'audio/webm;codecs=opus' };
            else if (MediaRecorder.isTypeSupported('audio/webm')) options = { mimeType: 'audio/webm' };
            else if (MediaRecorder.isTypeSupported('audio/mp4')) options = { mimeType: 'audio/mp4' };

            mediaRecorder = new MediaRecorder(stream, options);
            audioChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) audioChunks.push(event.data);
            };

            mediaRecorder.onstart = () => {
                isRecording = true;
                micBtn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
                micBtn.classList.add('bg-indigo-500', 'hover:bg-indigo-600', 'animate-pulse'); // Distinct color for Cloud
                micBtn.innerHTML = '<i data-lucide="cloud" class="w-8 h-8 text-white"></i>'; // Distinct icon
                lucide.createIcons();
                statusText.textContent = "Cloud Recording...";
                statusText.classList.add('text-indigo-500', 'font-semibold');
            };

            mediaRecorder.onstop = async () => {
                isRecording = false;
                micBtn.classList.add('bg-purple-600', 'hover:bg-purple-700');
                micBtn.classList.remove('bg-indigo-500', 'hover:bg-indigo-600', 'animate-pulse');
                micBtn.innerHTML = '<i data-lucide="mic" class="w-8 h-8"></i>';
                lucide.createIcons();
                statusText.textContent = "Processing Audio...";
                statusText.classList.remove('text-indigo-500', 'font-semibold');

                const audioBlob = new Blob(audioChunks, { type: options.mimeType });
                await processCloudAudio(audioBlob);
                
                stream.getTracks().forEach(track => track.stop());
                statusText.textContent = "Ready";
            };

            mediaRecorder.start();
        } catch (err) {
            console.error(err);
            alert("Microphone access denied or error initializing.");
        }
    }

    function stopCloudRecording() {
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    }

    async function processCloudAudio(audioBlob) {
        // Convert Blob to Base64
        const reader = new FileReader();
        reader.readAsDataURL(audioBlob);
        reader.onloadend = async function() {
            const base64Audio = reader.result.split(',')[1]; // Remove data URL prefix
            
            // Show UI feedback
            const originalPlaceholder = narrativeText.placeholder;
            narrativeText.placeholder = "Transcribing audio via Cloud...";
            narrativeText.disabled = true;

            try {
                const response = await fetch('api/ai_companion.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'transcribe_audio',
                        audio_data: base64Audio,
                        mime_type: audioBlob.type
                    })
                });

                const data = await response.json();
                narrativeText.disabled = false;
                narrativeText.placeholder = originalPlaceholder;

                if (data.success) {
                    const text = data.text;
                    const cleanText = text.replace('Transcribed Text:', '').trim();
                    if (cleanText) {
                        const currentVal = narrativeText.value;
                        narrativeText.value = currentVal + (currentVal ? ' ' : '') + cleanText;
                        narrativeText.dispatchEvent(new Event('input'));
                        narrativeText.scrollTop = narrativeText.scrollHeight;
                        showCommandFeedback("Transcribed!");
                    } else {
                        showCommandFeedback("No speech detected.");
                    }
                } else {
                    console.error('Transcription failed:', data);
                    alert('Transcription failed: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                narrativeText.disabled = false;
                narrativeText.placeholder = originalPlaceholder;
                console.error(err);
                alert('Network error during transcription.');
            }
        };
    }

    // Clear Text
    clearBtn.addEventListener('click', () => {
        if(confirm('Are you sure you want to clear the narrative?')) {
            narrativeText.value = '';
            localStorage.removeItem(STORAGE_KEY);
            // Clear image too
            woundImageInput.value = '';
            currentImageBase64 = null;
            currentImageMime = null;
            
            imagePreviewContainer.classList.add('hidden');
            photoEmptyState.classList.remove('hidden');
            photoIndicator.classList.add('hidden');
            
            drawHistory = [];
            
            // Trigger autosave to clear server draft
            narrativeText.dispatchEvent(new Event('input'));
        }
    });

    // --- Image Handling ---
    cameraBtn.addEventListener('click', () => woundImageInput.click());

    woundImageInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            // Basic validation
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                alert('Image is too large. Please select an image under 5MB.');
                return;
            }

            const reader = new FileReader();
            reader.onload = function(evt) {
                const result = evt.target.result; // Data URL
                loadImageToCanvas(result);
                
                // Extract Base64 and Mime (Initial)
                const parts = result.split(',');
                const mimeMatch = parts[0].match(/:(.*?);/);
                if (mimeMatch) {
                    currentImageMime = mimeMatch[1];
                    currentImageBase64 = parts[1];
                    
                    // Trigger autosave
                    narrativeText.dispatchEvent(new Event('input'));
                }
            };
            reader.readAsDataURL(file);
        }
    });

    removeImageBtn.addEventListener('click', () => {
        woundImageInput.value = '';
        currentImageBase64 = null;
        currentImageMime = null;
        
        // UI Updates
        imagePreviewContainer.classList.add('hidden');
        photoEmptyState.classList.remove('hidden');
        photoIndicator.classList.add('hidden');
        
        drawHistory = []; // Clear history
        
        // Trigger autosave
        narrativeText.dispatchEvent(new Event('input'));
    });

    // Process with AI
    processBtn.addEventListener('click', async () => {
        const text = narrativeText.value.trim();
        
        // Collect all images
        let allImages = [...savedImages];
        
        // If there is a current image on the canvas, add it too
        if (baseImage.src) {
             const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
             const parts = dataUrl.split(',');
             const typeSelect = document.getElementById('photo-type-select');
             allImages.push({
                base64: parts[1],
                mime: 'image/jpeg',
                type: typeSelect ? typeSelect.value : 'Other'
             });
        } else if (currentImageBase64) {
            const typeSelect = document.getElementById('photo-type-select');
            allImages.push({
                base64: currentImageBase64,
                mime: currentImageMime,
                type: typeSelect ? typeSelect.value : 'Other'
            });
        }

        // Allow processing if image is present even if text is empty? 
        // Let's require at least one.
        if (!text && allImages.length === 0) {
            alert('Please dictate notes or upload an image first.');
            return;
        }

        processingModal.classList.remove('hidden');

        try {
            // Prepare payload
            const payload = {
                action: 'process_narrative',
                patient_id: <?php echo $patient_id; ?>,
                appointment_id: <?php echo $appointment_id; ?>,
                user_id: <?php echo $user_id; ?>,
                narrative: text,
                images: allImages, // Send array of images
                context: {
                    patient_age: "<?php echo $patient_age; ?>",
                    patient_gender: "<?php echo $patient_gender; ?>",
                    active_meds: <?php echo json_encode($active_meds); ?>,
                    active_wounds: <?php echo json_encode($active_wounds); ?>,
                    past_visits: <?php echo json_encode($past_visits); ?>
                }
            };

            // Send to AI endpoint
            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success && data.review_data) {
                populateReviewModal(data.review_data);
                reviewModal.classList.remove('hidden');
            } else {
                alert('Error processing narrative: ' + (data.message || 'Unknown error'));
            }

        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while communicating with the AI.');
        } finally {
            processingModal.classList.add('hidden');
        }
    });

    // --- Review Modal Logic ---

    async function lookupICD(input) {
        const code = input.value.trim();
        if (code.length < 3) return;
        
        const row = input.closest('.diag-entry');
        const descInput = row.querySelector('.diag-desc');
        
        try {
            descInput.placeholder = "Looking up...";
            const response = await fetch(`api/get_icd_code_suggestions.php?query=${encodeURIComponent(code)}`);
            const res = await response.json();
            if (res.success && res.results && res.results.length > 0) {
                // Find exact match first
                const exact = res.results.find(r => r.icd10_code.toUpperCase() === code.toUpperCase());
                if (exact) {
                    descInput.value = exact.description;
                } else {
                    descInput.value = res.results[0].description;
                }
                descInput.placeholder = "Description";
            } else {
                descInput.placeholder = "Code not found in DB - Enter description manually";
            }
        } catch (e) {
            console.error("ICD Lookup failed", e);
            descInput.placeholder = "Description";
        }
    }

    // --- Tab Logic ---
    // --- Main Tab Logic ---
    window.switchMainTab = function(tabName) {
        const btnNarrative = document.getElementById('btn-tab-narrative');
        const btnPhoto = document.getElementById('btn-tab-photo');
        const viewNarrative = document.getElementById('view-narrative');
        const viewPhoto = document.getElementById('view-photo');

        if (tabName === 'narrative') {
            btnNarrative.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');
            btnNarrative.classList.remove('text-gray-500');
            btnPhoto.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600');
            btnPhoto.classList.add('text-gray-500');
            
            viewNarrative.classList.remove('hidden');
            viewPhoto.classList.add('hidden');
            viewPhoto.classList.remove('flex');
        } else {
            btnPhoto.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');
            btnPhoto.classList.remove('text-gray-500');
            btnNarrative.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600');
            btnNarrative.classList.add('text-gray-500');
            
            viewNarrative.classList.add('hidden');
            viewPhoto.classList.remove('hidden');
            viewPhoto.classList.add('flex');
            
            // Resize canvas if needed when becoming visible
            if (baseImage.src) {
                // Trigger a redraw or resize check if necessary
            }
        }
    };

    // --- Review Tab Logic ---
    document.querySelectorAll('.review-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // 1. Reset all tabs
            document.querySelectorAll('.review-tab-btn').forEach(b => {
                b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600');
                b.classList.add('text-gray-500');
            });
            document.querySelectorAll('.review-tab-content').forEach(c => c.classList.add('hidden'));

            // 2. Activate clicked tab
            btn.classList.remove('text-gray-500');
            btn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');
            
            const targetId = btn.dataset.target;
            document.getElementById(targetId).classList.remove('hidden');
        });
    });

    // --- Helper Functions for Adding Empty Rows ---
    window.addEmptyDiagnosis = function() {
        const container = document.getElementById('review-diagnosis-container');
        // Remove "No diagnoses" msg if present
        if (container.querySelector('p')) container.innerHTML = '';
        
        const html = `
            <div class="bg-gray-50 p-3 rounded border border-gray-200 diag-entry space-y-2">
                <div class="flex justify-between items-center">
                    <label class="text-xs font-bold text-gray-500 uppercase">ICD-10 Code</label>
                    <button onclick="this.closest('.diag-entry').remove()" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
                <input type="text" class="w-full p-2 border rounded text-sm diag-code focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. E11.9" onchange="lookupICD(this)">
                
                <label class="block text-xs font-bold text-gray-500 uppercase mt-2">Description</label>
                <input type="text" class="w-full p-2 border rounded text-sm diag-desc focus:ring-indigo-500 focus:border-indigo-500" placeholder="Diagnosis Description">
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        lucide.createIcons();
    };

    window.addEmptyMedication = function() {
        const container = document.getElementById('review-medication-container');
        if (container.querySelector('p')) container.innerHTML = '';

        const html = `
            <div class="bg-gray-50 p-3 rounded border border-gray-200 med-entry space-y-2">
                <div class="flex justify-between items-center">
                    <label class="text-xs font-bold text-gray-500 uppercase">Medication Name</label>
                    <button onclick="this.closest('.med-entry').remove()" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                </div>
                <input type="text" class="w-full p-2 border rounded text-sm med-name focus:ring-indigo-500 focus:border-indigo-500" placeholder="Drug Name">
                
                <div class="grid grid-cols-3 gap-2 mt-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dose</label>
                        <input type="text" class="w-full p-2 border rounded text-sm med-dose focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. 500mg">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Freq</label>
                        <input type="text" class="w-full p-2 border rounded text-sm med-freq focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. BID">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Route</label>
                        <input type="text" class="w-full p-2 border rounded text-sm med-route focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. PO">
                    </div>
                </div>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);
        lucide.createIcons();
    };

    function populateReviewModal(data) {
        // Reset Tabs to first one
        const firstTab = document.querySelector('.review-tab-btn[data-target="tab-soap"]');
        if(firstTab) firstTab.click();

        // Show Images in Review if exists
        const reviewImgContainer = document.getElementById('review-image-container');
        const reviewImagesList = document.getElementById('review-images-list');
        reviewImagesList.innerHTML = '';

        // Collect all images to show
        let imagesToShow = [...savedImages];
        if (currentImageBase64) {
            imagesToShow.push({
                base64: currentImageBase64,
                mime: currentImageMime
            });
        }

        if (imagesToShow.length > 0) {
            reviewImgContainer.classList.remove('hidden');
            imagesToShow.forEach(img => {
                const imgEl = document.createElement('img');
                imgEl.src = `data:${img.mime};base64,${img.base64}`;
                imgEl.className = 'h-24 w-auto rounded border border-indigo-200 shadow-sm bg-white object-cover';
                reviewImagesList.appendChild(imgEl);
            });
        } else {
            reviewImgContainer.classList.add('hidden');
        }

        // SOAP
        document.getElementById('review-cc').value = data.chief_complaint || '';
        document.getElementById('review-hpi').value = (data.hpi || '').replace(/<br>/g, '\n').replace(/<\/?[^>]+(>|$)/g, ""); // Strip HTML to display plain text for now, or keep if textarea supports it (it doesn't).
        // Wait, User asked for "HTML Ready". If I strip it, they lose it.
        // Let's assume they want to EDIT the raw HTML.
        document.getElementById('review-hpi').value = data.hpi || '';
        document.getElementById('review-ros').value = data.ros || '';
        document.getElementById('review-subj').value = data.subjective || '';
        document.getElementById('review-obj').value = data.objective || '';
        document.getElementById('review-assess').value = data.assessment || '';
        document.getElementById('review-plan').value = data.plan || '';

        // Diagnoses
        const diagContainer = document.getElementById('review-diagnosis-container');
        diagContainer.innerHTML = '';
        if (data.diagnoses && data.diagnoses.length > 0) {
            data.diagnoses.forEach((d, i) => {
                const html = `
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 diag-entry space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-bold text-gray-500 uppercase">ICD-10 Code</label>
                            <button onclick="this.closest('.diag-entry').remove()" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                        <input type="text" class="w-full p-2 border rounded text-sm diag-code focus:ring-indigo-500 focus:border-indigo-500" value="${d.icd10_code || ''}" placeholder="e.g. E11.9" onchange="lookupICD(this)">
                        
                        <label class="block text-xs font-bold text-gray-500 uppercase mt-2">Description</label>
                        <input type="text" class="w-full p-2 border rounded text-sm diag-desc focus:ring-indigo-500 focus:border-indigo-500" value="${d.description || ''}" placeholder="Diagnosis Description">
                    </div>`;
                diagContainer.insertAdjacentHTML('beforeend', html);
                
                // Auto-lookup if code exists but description is empty
                if (d.icd10_code && !d.description) {
                    const lastEntry = diagContainer.lastElementChild;
                    const codeInput = lastEntry.querySelector('.diag-code');
                    lookupICD(codeInput);
                }
            });
        } else {
            diagContainer.innerHTML = '<p class="text-gray-400 italic text-sm text-center py-4">No diagnoses detected.</p>';
        }

        // Medications
        const medContainer = document.getElementById('review-medication-container');
        medContainer.innerHTML = '';
        if (data.medications && data.medications.length > 0) {
            data.medications.forEach((m, i) => {
                const html = `
                    <div class="bg-gray-50 p-3 rounded border border-gray-200 med-entry space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-bold text-gray-500 uppercase">Medication Name</label>
                            <button onclick="this.closest('.med-entry').remove()" class="text-red-500 hover:text-red-700 p-1 rounded hover:bg-red-50"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </div>
                        <input type="text" class="w-full p-2 border rounded text-sm med-name focus:ring-indigo-500 focus:border-indigo-500" value="${m.drug_name || ''}" placeholder="Drug Name">
                        
                        <div class="grid grid-cols-3 gap-2 mt-2">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dose</label>
                                <input type="text" class="w-full p-2 border rounded text-sm med-dose focus:ring-indigo-500 focus:border-indigo-500" value="${m.dosage || ''}" placeholder="e.g. 500mg">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Freq</label>
                                <input type="text" class="w-full p-2 border rounded text-sm med-freq focus:ring-indigo-500 focus:border-indigo-500" value="${m.frequency || ''}" placeholder="e.g. BID">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Route</label>
                                <input type="text" class="w-full p-2 border rounded text-sm med-route focus:ring-indigo-500 focus:border-indigo-500" value="${m.route || ''}" placeholder="e.g. PO">
                            </div>
                        </div>
                    </div>`;
                medContainer.insertAdjacentHTML('beforeend', html);
            });
        } else {
            medContainer.innerHTML = '<p class="text-gray-400 italic text-sm text-center py-4">No medications detected.</p>';
        }
        
        // Re-init icons for new elements
        lucide.createIcons();

        // Vitals
        const v = data.vitals || {};
        document.getElementById('review-bp').value = v.blood_pressure || '';
        document.getElementById('review-hr').value = v.heart_rate || '';
        document.getElementById('review-rr').value = v.respiratory_rate || '';
        document.getElementById('review-o2').value = v.oxygen_saturation || '';
        
        // Handle Units for Display
        let temp = v.temperature_c;
        if (!temp && v.temperature_f) temp = ((v.temperature_f - 32) * 5/9).toFixed(1);
        document.getElementById('review-temp').value = temp || '';

        let weight = v.weight_kg;
        if (!weight && v.weight_lbs) weight = (v.weight_lbs * 0.453592).toFixed(1);
        document.getElementById('review-weight').value = weight || '';

        let height = v.height_cm;
        if (!height && v.height_in) height = (v.height_in * 2.54).toFixed(0);
        document.getElementById('review-height').value = height || '';

        // Wounds
        const wContainer = document.getElementById('review-wounds-container');
        wContainer.innerHTML = '';
        if (data.wounds && data.wounds.length > 0) {
            data.wounds.forEach((w, index) => {
                const wHtml = `
                    <div class="border border-gray-200 rounded p-4 bg-white wound-entry shadow-sm" data-index="${index}">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Location</label>
                                <input type="text" class="w-full p-2 border rounded text-sm wound-location focus:ring-indigo-500 focus:border-indigo-500" value="${w.location || ''}">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Type</label>
                                <input type="text" class="w-full p-2 border rounded text-sm wound-type focus:ring-indigo-500 focus:border-indigo-500" value="${w.type || 'Other'}">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Dimensions (LxWxD cm)</label>
                                <div class="flex space-x-2">
                                    <input type="number" step="0.1" class="w-full p-2 border rounded text-sm wound-len focus:ring-indigo-500 focus:border-indigo-500" placeholder="L" value="${w.length_cm || ''}">
                                    <input type="number" step="0.1" class="w-full p-2 border rounded text-sm wound-wid focus:ring-indigo-500 focus:border-indigo-500" placeholder="W" value="${w.width_cm || ''}">
                                    <input type="number" step="0.1" class="w-full p-2 border rounded text-sm wound-dep focus:ring-indigo-500 focus:border-indigo-500" placeholder="D" value="${w.depth_cm || ''}">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pain (0-10)</label>
                                <input type="number" class="w-full p-2 border rounded text-sm wound-pain focus:ring-indigo-500 focus:border-indigo-500" value="${w.pain_level || ''}">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Drainage</label>
                                <input type="text" class="w-full p-2 border rounded text-sm wound-drainage focus:ring-indigo-500 focus:border-indigo-500" value="${w.drainage_type || ''}">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Exudate</label>
                                <select class="w-full p-2 border rounded text-sm wound-exudate focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="None" ${w.exudate_amount === 'None' ? 'selected' : ''}>None</option>
                                    <option value="Scant" ${w.exudate_amount === 'Scant' ? 'selected' : ''}>Scant</option>
                                    <option value="Small" ${w.exudate_amount === 'Small' ? 'selected' : ''}>Small</option>
                                    <option value="Moderate" ${w.exudate_amount === 'Moderate' ? 'selected' : ''}>Moderate</option>
                                    <option value="Large" ${w.exudate_amount === 'Large' ? 'selected' : ''}>Large</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Odor</label>
                                <select class="w-full p-2 border rounded text-sm wound-odor focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="No" ${w.odor_present === 'No' ? 'selected' : ''}>No</option>
                                    <option value="Yes" ${w.odor_present === 'Yes' ? 'selected' : ''}>Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                wContainer.insertAdjacentHTML('beforeend', wHtml);
            });
        } else {
            wContainer.innerHTML = '<p class="text-gray-400 italic text-center py-4">No wounds detected.</p>';
        }

        // Procedure
        const procSection = document.getElementById('review-procedure-section');
        if (data.procedure && (data.procedure.type || data.procedure.narrative)) {
            procSection.classList.remove('hidden');
            document.getElementById('review-proc-type').value = data.procedure.type || '';
            document.getElementById('review-proc-loc').value = data.procedure.location || '';
            document.getElementById('review-proc-dims').value = data.procedure.dimensions || '';
            document.getElementById('review-proc-depth').value = data.procedure.depth || '';
            document.getElementById('review-proc-inst').value = data.procedure.instrument || '';
            document.getElementById('review-proc-narrative').value = data.procedure.narrative || '';
        } else {
            procSection.classList.add('hidden');
            document.getElementById('review-proc-type').value = '';
            document.getElementById('review-proc-loc').value = '';
            document.getElementById('review-proc-dims').value = '';
            document.getElementById('review-proc-depth').value = '';
            document.getElementById('review-proc-inst').value = '';
            document.getElementById('review-proc-narrative').value = '';
        }
    }

    function collectReviewData() {
        const data = {
            chief_complaint: document.getElementById('review-cc').value,
            hpi: document.getElementById('review-hpi').value,
            ros: document.getElementById('review-ros').value,
            subjective: document.getElementById('review-subj').value,
            objective: document.getElementById('review-obj').value,
            assessment: document.getElementById('review-assess').value,
            plan: document.getElementById('review-plan').value,
            vitals: {
                blood_pressure: document.getElementById('review-bp').value,
                heart_rate: document.getElementById('review-hr').value,
                respiratory_rate: document.getElementById('review-rr').value,
                oxygen_saturation: document.getElementById('review-o2').value,
                temperature_c: document.getElementById('review-temp').value,
                weight_kg: document.getElementById('review-weight').value,
                height_cm: document.getElementById('review-height').value
            },
            wounds: [],
            procedure: {},
            diagnoses: [],
            medications: []
        };

        // Collect Diagnoses
        document.querySelectorAll('.diag-entry').forEach(el => {
            data.diagnoses.push({
                icd10_code: el.querySelector('.diag-code').value,
                description: el.querySelector('.diag-desc').value
            });
        });

        // Collect Medications
        document.querySelectorAll('.med-entry').forEach(el => {
            data.medications.push({
                drug_name: el.querySelector('.med-name').value,
                dosage: el.querySelector('.med-dose').value,
                frequency: el.querySelector('.med-freq').value,
                route: el.querySelector('.med-route').value,
                status: 'Active'
            });
        });

        document.querySelectorAll('.wound-entry').forEach(el => {
            data.wounds.push({
                location: el.querySelector('.wound-location').value,
                type: el.querySelector('.wound-type').value,
                length_cm: el.querySelector('.wound-len').value,
                width_cm: el.querySelector('.wound-wid').value,
                depth_cm: el.querySelector('.wound-dep').value,
                pain_level: el.querySelector('.wound-pain').value,
                drainage_type: el.querySelector('.wound-drainage').value,
                exudate_amount: el.querySelector('.wound-exudate').value,
                odor_present: el.querySelector('.wound-odor').value
            });
        });

        // Procedure
        const procSection = document.getElementById('review-procedure-section');
        if (!procSection.classList.contains('hidden')) {
            data.procedure = {
                type: document.getElementById('review-proc-type').value,
                location: document.getElementById('review-proc-loc').value,
                dimensions: document.getElementById('review-proc-dims').value,
                depth: document.getElementById('review-proc-depth').value,
                instrument: document.getElementById('review-proc-inst').value,
                narrative: document.getElementById('review-proc-narrative').value
            };
        }

        return data;
    }

    // Close / Cancel
    const closeReview = () => reviewModal.classList.add('hidden');
    closeReviewBtn.addEventListener('click', closeReview);
    cancelReviewBtn.addEventListener('click', closeReview);

    // Confirm & Save
    confirmSaveBtn.addEventListener('click', async () => {
        const reviewData = collectReviewData();
        
        // Show loading state on button
        const originalBtnText = confirmSaveBtn.innerHTML;
        confirmSaveBtn.disabled = true;
        confirmSaveBtn.innerHTML = '<i data-lucide="loader-2" class="w-5 h-5 mr-2 animate-spin"></i> Saving...';

        try {
            // Collect all images to save
            let allImages = [...savedImages];
            
            // If there is a current image on the canvas, add it too
            if (baseImage.src) {
                 const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                 const parts = dataUrl.split(',');
                 const typeSelect = document.getElementById('photo-type-select');
                 allImages.push({
                    base64: parts[1],
                    mime: 'image/jpeg',
                    type: typeSelect ? typeSelect.value : 'Other'
                 });
            } else if (currentImageBase64) {
                const typeSelect = document.getElementById('photo-type-select');
                allImages.push({
                    base64: currentImageBase64,
                    mime: currentImageMime,
                    type: typeSelect ? typeSelect.value : 'Other'
                });
            }

            const payload = {
                action: 'confirm_save',
                patient_id: <?php echo $patient_id; ?>,
                appointment_id: <?php echo $appointment_id; ?>,
                user_id: <?php echo $user_id; ?>,
                review_data: reviewData,
                images: allImages
            };

            const response = await fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const resData = await response.json();

            if (resData.success) {
                // Redirect to visit_summary.php, but DO NOT clear localStorage so draft persists
                window.location.href = `visit_summary.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>&user_id=<?php echo $user_id; ?>`;
            } else {
                alert('Error saving data: ' + (resData.message || 'Unknown error'));
                confirmSaveBtn.disabled = false;
                confirmSaveBtn.innerHTML = originalBtnText;
                lucide.createIcons();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while saving.');
            confirmSaveBtn.disabled = false;
            confirmSaveBtn.innerHTML = originalBtnText;
            lucide.createIcons();
        }
    });

    // --- Mobile View Logic ---
    window.switchMobileView = function(targetId) {
        const sections = ['col-patient', 'col-main', 'col-history'];
        
        sections.forEach(id => {
            const el = document.getElementById(id);
            if (id === targetId) {
                el.classList.remove('hidden');
                el.classList.add('flex');
            } else {
                el.classList.add('hidden');
                el.classList.remove('flex');
            }
        });
        
        // Update Nav State
        document.querySelectorAll('.mobile-nav-btn').forEach(btn => {
            if(btn.dataset.target === targetId) {
                btn.classList.add('text-indigo-600');
                btn.classList.remove('text-gray-500');
            } else {
                btn.classList.remove('text-indigo-600');
                btn.classList.add('text-gray-500');
            }
        });
        
        lucide.createIcons();
    };

</script>

<!-- Mobile Navigation Bar -->
<div class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center p-2 z-50 pb-safe shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
    <button onclick="switchMobileView('col-patient')" class="mobile-nav-btn flex flex-col items-center p-2 text-gray-500 hover:text-indigo-600 transition-colors w-1/3" data-target="col-patient">
        <i data-lucide="user" class="w-5 h-5 mb-1"></i>
        <span class="text-[10px] font-bold uppercase tracking-wide">Patient</span>
    </button>
    <button onclick="switchMobileView('col-main')" class="mobile-nav-btn flex flex-col items-center p-2 text-indigo-600 w-1/3" data-target="col-main">
        <div class="bg-indigo-600 p-3 rounded-full -mt-8 border-4 border-gray-100 shadow-lg transform transition-transform active:scale-95">
            <i data-lucide="mic" class="w-6 h-6 text-white"></i>
        </div>
        <span class="text-[10px] font-bold uppercase mt-1">Dictate</span>
    </button>
    <button onclick="switchMobileView('col-history')" class="mobile-nav-btn flex flex-col items-center p-2 text-gray-500 hover:text-indigo-600 transition-colors w-1/3" data-target="col-history">
        <i data-lucide="history" class="w-5 h-5 mb-1"></i>
        <span class="text-[10px] font-bold uppercase tracking-wide">History</span>
    </button>
</div>

<?php require_once 'templates/footer.php'; ?>