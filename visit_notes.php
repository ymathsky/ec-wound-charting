<?php
// Filename: visit_notes.php
// UPDATED: Added "Clone Last Visit" button in header and "Insert Normal" buttons in tabs.
// These features implement the Workflow Automation enhancements.

require_once 'templates/header.php';
require_once 'db_connect.php';
require_once 'visit_status_check.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'clinician'])) {
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 flex flex-col overflow-hidden'><header class='w-full bg-white p-4 shadow-md'><h1>Access Denied</h1></header>";
    echo "<main class='flex-1 p-6'><div class='max-w-4xl mx-auto bg-white p-6 rounded-lg shadow'><h2 class='text-2xl font-bold text-red-600'>Access Denied</h2><p class='mt-4'>You do not have permission to access this area.</p></div></main></div></div>";
    require_once 'templates/footer.php';
    exit();
}

// --- Get IDs from URL and apply final HTML escaping ---
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
// SECURITY: Always use session user_id — never allow user_id override via URL (IDOR risk)
$user_id = isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0;

if ($patient_id <= 0 || $appointment_id <= 0) {
    echo "<div class='p-8'>Invalid Patient or Appointment ID.</div>";
    require_once 'templates/footer.php';
    exit();
}

// Use htmlspecialchars for safe output
$safe_patient_id = htmlspecialchars($patient_id);
$safe_appointment_id = htmlspecialchars($appointment_id);
$safe_user_id = htmlspecialchars($user_id);

// Cache buster version (timestamp) to ensure JS updates apply immediately
$ver = time();
?>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" type="text/css" href="css/checklist.css?v=<?php echo $ver; ?>">
    <link rel="stylesheet" href="css/quick_insert_button_colors.css?v=<?php echo $ver; ?>">

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex flex-col md:flex-row justify-between items-start md:items-center shadow-lg space-y-4 md:space-y-0">
                <div class="flex items-center w-full md:w-auto">
                    <!-- Mobile Menu Button -->
                    <button onclick="openSidebar()" class="md:hidden mr-4 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i data-lucide="menu" class="w-8 h-8"></i>
                    </button>
                    <div>
                        <h1 id="patient-name-header" class="text-2xl md:text-3xl font-extrabold text-gray-900">Loading Patient...</h1>
                    <p id="patient-dob-subheader" class="text-sm text-indigo-600 font-semibold mt-1">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                    <i data-lucide="file-check" class="w-4 h-4 mr-1.5"></i>
                    Visit Note (Advanced Mode)
                </span>
                    </p>
                </div>
                </div>

                <div id="header-autosave-status" class="text-sm font-medium flex items-center transition-opacity duration-500 px-4 hidden md:flex">
                    <!-- Status inserted by JS -->
                </div>

                <div class="flex flex-col md:flex-row items-stretch md:items-center space-y-2 md:space-y-0 md:space-x-4 w-full md:w-auto">
                    <!-- NEW: Clone Last Visit Button -->
                    <button type="button" id="cloneLastVisitBtn" class="bg-white border border-gray-300 text-gray-700 font-semibold py-3 md:py-2 px-4 rounded-lg hover:bg-gray-50 transition duration-150 ease-in-out shadow-sm flex items-center justify-center" title="Copy text from the previous finalized visit">
                        <i data-lucide="copy-plus" class="w-5 h-5 mr-2 text-blue-500"></i>
                        Clone Last Visit
                    </button>

                    <a href="visit_summary.php?appointment_id=<?php echo $safe_appointment_id; ?>&patient_id=<?php echo $safe_patient_id; ?>&user_id=<?php echo $safe_user_id; ?>"
                       class="bg-white border border-gray-300 text-gray-700 font-semibold py-3 md:py-2 px-4 rounded-lg hover:bg-gray-50 transition duration-150 ease-in-out shadow-sm flex items-center justify-center">
                        <i data-lucide="arrow-left-circle" class="w-5 h-5 mr-2 text-blue-600"></i>
                        Switch to Simplified Mode
                    </a>
                    <a href="visit_summary.php?appointment_id=<?php echo $safe_appointment_id; ?>&patient_id=<?php echo $safe_patient_id; ?>&user_id=<?php echo $safe_user_id; ?>"
                       class="bg-blue-600 text-white font-bold py-3 px-6 rounded-lg hover:bg-blue-700 transition duration-150 ease-in-out shadow-xl transform hover:scale-105 flex items-center justify-center"
                       id="reviewVisitBtn">
                        <i data-lucide="clipboard-check" class="w-5 h-5 mr-2"></i>
                        Review
                    </a>
                </div>
            </header>
            <!-- Sticky Tab Navigation (Replaces Submenu) -->
            <div class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
                <div class="overflow-x-auto">
                    <nav class="-mb-px flex space-x-1 px-4" aria-label="Tabs">
                        <button type="button" onclick="switchTab('chief_complaint')" id="tab-btn-chief_complaint"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="alert-circle" class="w-4 h-4 mr-2 text-yellow-600"></i>
                            Chief Complaint
                        </button>
                        <button type="button" onclick="switchTab('subjective')" id="tab-btn-subjective"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="user" class="w-4 h-4 mr-2 text-blue-600"></i>
                            Subjective
                        </button>
                        <button type="button" onclick="switchTab('objective')" id="tab-btn-objective"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="activity" class="w-4 h-4 mr-2 text-green-600"></i>
                            Objective
                        </button>
                        <button type="button" onclick="switchTab('assessment')" id="tab-btn-assessment"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="clipboard" class="w-4 h-4 mr-2 text-orange-600"></i>
                            Assessment
                        </button>
                        <button type="button" onclick="switchTab('plan')" id="tab-btn-plan"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="file-plus" class="w-4 h-4 mr-2 text-indigo-600"></i>
                            Plan
                        </button>
                        <button type="button" onclick="switchTab('orders')" id="tab-btn-orders"
                                class="tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i data-lucide="clipboard-list" class="w-4 h-4 mr-2 text-teal-600"></i>
                            Orders & Referrals
                        </button>

                        <button type="button" onclick="switchTab('finalize')" id="tab-btn-finalize"
                                class="ml-auto tab-btn group inline-flex items-center py-3 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap bg-purple-50">
                            <i data-lucide="check-square" class="w-4 h-4 mr-2 text-purple-600"></i>
                            Sign & Save
                        </button>

                        <!-- Toggle Sidebar Button -->
                        <button type="button" id="toggleSidebarBtn" class="inline-flex items-center py-2 px-3 my-1 border border-gray-300 font-medium text-sm rounded-md text-gray-700 bg-white hover:bg-gray-50 shadow-sm mx-2 self-center" title="Show/Hide Patient Info Sidebar">
                            <i data-lucide="sidebar" class="w-4 h-4 mr-2 text-gray-500"></i>
                            <span class="hidden xl:inline">Info</span>
                        </button>

                        <button type="button" id="previewNoteBtn" data-action="quick-preview" data-section="all" 
                                class="inline-flex items-center py-2 px-3 my-1 border border-transparent font-medium text-sm rounded-md text-white bg-gray-700 hover:bg-gray-800 shadow-sm mr-2 self-center">
                            <i data-lucide="eye" class="w-4 h-4 mr-2"></i>
                            Preview
                        </button>
                    </nav>
                </div>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="flex flex-col lg:flex-row gap-8 h-full">

                    <div class="w-full lg:w-2/3 transition-all duration-300 ease-in-out" id="main-charting-column">
                        <div class="bg-white rounded-xl shadow-2xl p-8" id="visit-workflow-form">

                            <h3 class="text-2xl font-bold mb-4 text-gray-800 border-b pb-3">SOAP Note Documentation</h3>
                            <!-- Preview Button moved to sticky header -->
                            <div id="note-message" class="hidden p-3 my-3 rounded-lg font-medium shadow"></div>
                             

                            <!-- Tab Navigation Moved to Top -->

                            <form id="noteForm" class="space-y-6">
                                <input type="hidden" name="patient_id" value="<?php echo $safe_patient_id; ?>">
                                <input type="hidden" name="appointment_id" value="<?php echo $safe_appointment_id; ?>">

                                <textarea name="chief_complaint" id="chief_complaint_input" class="hidden"></textarea>
                                <textarea name="subjective" id="subjective" class="hidden"></textarea>
                                <textarea name="objective" id="objective" class="hidden"></textarea>
                                <textarea name="assessment" id="assessment" class="hidden"></textarea>
                                <textarea name="plan" id="plan" class="hidden"></textarea>
                                <textarea name="lab_orders" id="lab_orders" class="hidden"></textarea>
                                <textarea name="imaging_orders" id="imaging_orders" class="hidden"></textarea>
                                <textarea name="skilled_nurse_orders" id="skilled_nurse_orders" class="hidden"></textarea>

                                <!-- 1. Chief Complaint Tab -->
                                <div id="tab-content-chief_complaint" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-yellow-500 bg-yellow-50 rounded-r-lg shadow-sm">
                                        <h3 class="text-lg font-bold text-yellow-800 mb-2">Chief Complaint (CC)</h3>
                                        <div id="chief_complaint-editor-container" class="quill-editor h-64 border-yellow-300 bg-white mt-2 rounded-md"></div>
                                        <p class="text-xs text-yellow-600 mt-2">This section is auto-generated based on intake and can be edited.</p>
                                    </div>
                                </div>

                                <!-- 2. Subjective Tab -->
                                <div id="tab-content-subjective" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-blue-500 bg-blue-50 rounded-r-lg shadow-sm">
                                        <div class="flex justify-between items-center mb-3">
                                            <h3 class="text-lg font-bold text-blue-800">Subjective (S)</h3>
                                            <div class="flex space-x-2">
                                                <!-- NEW: Insert Normal Button -->
                                                <button type="button" class="btn btn-sm bg-blue-100 text-blue-700 hover:bg-blue-200 border-blue-200 flex items-center" onclick="insertWNL('subjective')">
                                                    <i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i> Insert Normal
                                                </button>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-blue-300 text-blue-700 hover:bg-blue-50" data-section="subjective">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                        </div>

                                        <div id="subjective-editor-container" class="quill-editor h-64 border-blue-300 bg-white rounded-md mb-4"></div>

                                        <div id="auto-populated-hpi" class="auto-populated-section prose max-w-none bg-white border border-blue-200 rounded-md p-3">
                                            <p class="text-blue-500 italic">Loading HPI data...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 3. Objective Tab -->
                                <div id="tab-content-objective" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-green-500 bg-green-50 rounded-r-lg shadow-sm">
                                        <div class="flex justify-between items-center mb-3">
                                            <h3 class="text-lg font-bold text-green-800">Objective (O)</h3>
                                            <div class="flex space-x-2">
                                                <!-- NEW: Insert Normal Button -->
                                                <button type="button" class="btn btn-sm bg-green-100 text-green-700 hover:bg-green-200 border-green-200 flex items-center" onclick="insertWNL('objective')">
                                                    <i data-lucide="check-circle-2" class="w-3 h-3 mr-1"></i> Insert Normal
                                                </button>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-green-300 text-green-700 hover:bg-green-50" data-section="objective">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                        </div>

                                        <div id="objective-editor-container" class="quill-editor h-64 border-green-300 bg-white rounded-md mb-4"></div>

                                        <div id="auto-populated-vitals" class="auto-populated-section prose max-w-none bg-white border border-green-200 rounded-md p-3">
                                            <p class="text-green-500 italic">Loading Vitals data...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 4. Assessment Tab -->
                                <div id="tab-content-assessment" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-orange-500 bg-orange-50 rounded-r-lg shadow-sm">
                                        <div class="flex justify-between items-center mb-3">
                                            <h3 class="text-lg font-bold text-orange-800">Assessment (A)</h3>
                                            <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-orange-300 text-orange-700 hover:bg-orange-50" data-section="assessment">
                                                <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                            </button>
                                        </div>

                                        <div id="auto-populated-wounds" class="auto-populated-section prose max-w-none bg-white border border-orange-200 rounded-md p-3 mb-4">
                                            <p class="text-orange-500 italic">Loading Wound Assessment data...</p>
                                        </div>

                                        <div id="assessment-editor-container" class="quill-editor h-64 border-orange-300 bg-white rounded-md mb-4"></div>

                                        <div id="auto-populated-diagnoses" class="auto-populated-section prose max-w-none bg-white border border-orange-200 rounded-md p-3 mb-3">
                                            <p class="text-orange-500 italic">Loading Diagnoses data...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. Plan Tab -->
                                <div id="tab-content-plan" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-indigo-500 bg-indigo-50 rounded-r-lg shadow-sm">
                                        <div class="flex justify-between items-center mb-3">
                                            <h3 class="text-lg font-bold text-indigo-800">Plan (P)</h3>
                                            <div class="flex space-x-2">
                                                <button type="button" id="generateProcNarrativeBtn" class="btn btn-sm bg-indigo-600 hover:bg-indigo-700 text-white flex items-center text-xs px-2 py-1 rounded shadow-sm" title="Auto-generate narrative from billed procedures">
                                                    <i data-lucide="sparkles" class="w-3 h-3 mr-1"></i> Proc. Note
                                                </button>
                                                <button type="button" class="clinical-suggestions-btn btn btn-sm bg-purple-600 hover:bg-purple-700 text-white flex items-center text-xs px-2 py-1 rounded shadow-sm" data-target-section="plan">
                                                    <i data-lucide="lightbulb" class="w-3 h-3 mr-1"></i> Suggestions
                                                </button>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-indigo-300 text-indigo-700 hover:bg-indigo-50" data-section="plan">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                        </div>

                                        <div id="plan-editor-container" class="quill-editor h-64 border-indigo-300 bg-white rounded-md mb-4"></div>

                                        <div id="auto-populated-procedures" class="auto-populated-section prose max-w-none bg-white border border-indigo-200 rounded-md p-3 mb-3">
                                            <p class="text-indigo-500 italic">Loading Procedures data...</p>
                                        </div>
                                        <div id="auto-populated-medications" class="auto-populated-section prose max-w-none bg-white border border-indigo-200 rounded-md p-3 mb-3">
                                            <p class="text-indigo-500 italic">Loading Active Medications data...</p>
                                        </div>
                                        <div id="auto-populated-diagnoses-plan" class="auto-populated-section prose max-w-none bg-white border border-indigo-200 rounded-md p-3 mb-3">
                                            <p class="text-indigo-500 italic">Loading Diagnoses data...</p>
                                        </div>
                                        <div id="auto-populated-wound-plans" class="auto-populated-section prose max-w-none bg-white border border-indigo-200 rounded-md p-3">
                                            <p class="text-indigo-500 italic">Loading Wound Treatment Plans...</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. Orders & Referrals Tab -->
                                <div id="tab-content-orders" class="tab-content hidden">
                                    <div class="p-4 border-l-4 border-teal-500 bg-teal-50 rounded-r-lg shadow-sm space-y-6">
                                        
                                        <!-- Lab Orders -->
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <h3 class="text-lg font-bold text-teal-800 flex items-center">
                                                    <i data-lucide="flask-conical" class="w-5 h-5 mr-2"></i> Lab Orders
                                                </h3>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-teal-300 text-teal-700 hover:bg-teal-50" data-section="lab_orders">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                            <div id="lab_orders-editor-container" class="quill-editor h-24 border-teal-300 bg-white rounded-md"></div>
                                        </div>

                                        <!-- Imaging Orders -->
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <h3 class="text-lg font-bold text-teal-800 flex items-center">
                                                    <i data-lucide="image" class="w-5 h-5 mr-2"></i> Imaging Orders
                                                </h3>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-teal-300 text-teal-700 hover:bg-teal-50" data-section="imaging_orders">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                            <div id="imaging_orders-editor-container" class="quill-editor h-24 border-teal-300 bg-white rounded-md"></div>
                                        </div>

                                        <!-- Skilled Nurse Orders -->
                                        <div>
                                            <div class="flex justify-between items-center mb-2">
                                                <h3 class="text-lg font-bold text-teal-800 flex items-center">
                                                    <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i> Orders for Skilled Nurse
                                                </h3>
                                                <button type="button" class="quick-insert-btn btn btn-sm bg-white border border-teal-300 text-teal-700 hover:bg-teal-50" data-section="skilled_nurse_orders">
                                                    <i data-lucide="list-plus" class="w-4 h-4 mr-1 inline"></i> Quick Insert
                                                </button>
                                            </div>
                                            <div id="skilled_nurse_orders-editor-container" class="quill-editor h-24 border-teal-300 bg-white rounded-md"></div>
                                        </div>

                                    </div>
                                </div>

                                <!-- 7. Finalize Tab (Signature & Save) -->
                                <div id="tab-content-finalize" class="tab-content hidden">
                                    <div class="p-6 border-l-4 border-purple-500 bg-purple-50 rounded-r-lg shadow-sm">
                                        <h3 class="font-bold text-purple-800 text-xl mb-4">Finalize & Sign Note</h3>

                                        <div class="bg-white border border-purple-200 rounded-lg p-6 mb-6">
                                            <p class="text-sm text-gray-600 mb-2 font-medium">Clinician Signature Required:</p>
                                            <div class="signature-wrapper border border-gray-300 rounded shadow-inner bg-gray-50 mx-auto" style="width: 100%; max-width: 600px; height: 220px; position: relative;">
                                                <canvas id="signature-pad" class="w-full h-full cursor-crosshair" width="600" height="220"></canvas>
                                            </div>
                                            <input type="hidden" name="signature_data" id="signature_data">
                                            <div class="mt-2 flex justify-center">
                                                <button type="button" id="clear-signature" class="text-sm text-red-600 hover:text-red-800 underline flex items-center">
                                                    <i data-lucide="eraser" class="w-3 h-3 mr-1"></i> Clear Signature
                                                </button>
                                            </div>
                                        </div>

                                        <div class="flex flex-col md:flex-row justify-between pt-6 border-t border-purple-200 items-center gap-4">
                                           

                                            <button type="submit" id="saveNoteBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-10 rounded-lg transition duration-150 shadow-lg transform hover:scale-105 flex items-center text-lg" aria-label="Save Note">
                                                <i data-lucide="save" class="w-5 h-5 mr-2"></i>
                                                Sign & Save Note
                                            </button>
                                        </div>

                                        <!-- Addendum Section (Hidden by default, shown if finalized) -->
                                        <div id="addendum-container" class="hidden mt-8 border-t border-purple-200 pt-6">
                                            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                                                <i data-lucide="file-plus" class="w-5 h-5 mr-2 text-purple-600"></i>
                                                Addendums
                                            </h3>
                                            
                                            <div id="addendum-list" class="space-y-4 mb-6">
                                                <!-- Addendums will be injected here via JS -->
                                            </div>
                                            
                                            <div id="add-addendum-form" class="bg-yellow-50 p-4 rounded-lg border border-yellow-200 shadow-sm">
                                                <h4 class="font-bold text-yellow-800 mb-2 flex items-center">
                                                    <i data-lucide="edit" class="w-4 h-4 mr-2"></i>
                                                    Add New Addendum
                                                </h4>
                                                <p class="text-xs text-yellow-700 mb-2">This note is finalized. Any changes must be added as an addendum.</p>
                                                <div id="addendum-editor-container" class="bg-white h-32 mb-3 border border-yellow-300 rounded"></div>
                                                <div class="flex justify-end">
                                                    <button type="button" id="saveAddendumBtn" class="bg-yellow-600 text-white font-bold py-2 px-4 rounded hover:bg-yellow-700 shadow flex items-center">
                                                        <i data-lucide="save" class="w-4 h-4 mr-2"></i>
                                                        Sign & Save Addendum
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </form>

                            <div class="mt-12">
                                <h3 class="text-xl font-bold mb-4 text-gray-800 border-b pb-2 flex items-center">
                                    <i data-lucide="history" class="h-5 w-5 mr-2 text-gray-500"></i>
                                    Notes History (Archived)
                                </h3>
                                <iframe
                                        id="notes-history-iframe"
                                        src="visit_notes_history.php?patient_id=<?php echo $safe_patient_id; ?>&appointment_id=<?php echo $safe_appointment_id; ?>"
                                        class="w-full bg-gray-50 rounded-lg shadow-inner border border-gray-200"
                                        style="height: 600px; border: none; overflow-y: auto;"
                                ></iframe>
                            </div>
                        </div>
                    </div>

                    <div class="w-full lg:w-1/3 transition-all duration-300 ease-in-out" id="sidebar-column">

                        <div class="bg-white rounded-xl shadow-2xl p-6 border border-gray-100 sticky top-24">
                            <div class="flex justify-between items-center mb-4 border-b pb-3">
                                <h2 class="text-xl font-bold text-gray-800 flex items-center">
                                    <i data-lucide="user" class="h-5 w-5 mr-2 text-indigo-600"></i>
                                    Patient At-a-Glance
                                </h2>
                            </div>
                            <div id="patient-demographics-content" class="text-sm space-y-3">
                                <div>
                                    <label class="font-semibold text-gray-500 text-xs uppercase">Patient</label>
                                    <p id="demographics-name" class="font-medium text-gray-900">Loading...</p>
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-500 text-xs uppercase">DOB & Age</label>
                                    <p id="demographics-dob-age" class="font-medium text-gray-900">Loading...</p>
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-500 text-xs uppercase">Allergies</label>
                                    <p id="demographics-allergies" class="font-medium text-gray-900 bg-red-50 p-2 rounded border border-red-100">Loading...</p>
                                </div>
                                <div>
                                    <label class="font-semibold text-gray-500 text-xs uppercase">Past Medical History</label>
                                    <div id="demographics-pmh" class="font-medium text-gray-900 bg-gray-50 p-2 rounded border h-24 overflow-y-auto text-xs">
                                        <p>Loading...</p>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-4 border-t">
                                <div class="flex justify-between items-center mb-4">
                                    <h2 class="text-lg font-bold text-gray-800 flex items-center">
                                        <i data-lucide="clipboard-list" class="h-5 w-5 mr-2 text-green-600"></i>
                                        Completion Checklist
                                    </h2>
                                </div>
                                <div id="note-completion-checklist" class="text-sm space-y-3">
                                    <p class="text-gray-500 text-center py-4">Loading checklist...</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Include Modals -->
    <!-- UPDATED: Increased width to max-w-6xl and height to 90vh -->
    <div id="previewModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4 overflow-y-auto">
        <div class="modal-dialog bg-white rounded-xl shadow-2xl max-w-6xl w-full h-[90vh]" id="previewModalDialog">
            <div class="modal-header">
                <h3 id="preview-modal-title" class="text-2xl font-bold text-gray-800 flex items-center">
                    Preview Full Note
                </h3>
                <button id="closePreviewModalBtn" class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body p-0 h-full overflow-hidden">
                <!-- Removed padding and prose to allow iframe to fill space -->
                <div id="preview-modal-content" class="w-full h-full bg-gray-50">
                    <p class="text-gray-500 p-4">Loading content...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn bg-gray-200 text-gray-700 hover:bg-gray-300 mr-2" onclick="closeModal(document.getElementById('previewModal'), document.getElementById('previewModalDialog'))">Close</button>
            </div>
        </div>
    </div>

    <!-- Generic Message/Confirm Modal -->
    <div id="messageModal" class="fixed inset-0 z-[60] hidden items-center justify-center overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm" aria-hidden="true"></div>
        <div class="bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:max-w-md sm:w-full mx-4 z-10 border border-gray-200">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10" id="msg-modal-icon-bg">
                        <i data-lucide="alert-triangle" class="h-6 w-6 text-red-600" id="msg-modal-icon"></i>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-bold text-gray-900" id="msg-modal-title">
                            Title
                        </h3>
                        <div class="mt-2">
                            <div class="text-sm text-gray-500 whitespace-pre-wrap" id="msg-modal-body">
                                Message body...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                <button type="button" id="msg-modal-confirm-btn" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                    OK
                </button>
                <button type="button" id="msg-modal-cancel-btn" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors hidden">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <div id="checklistModal" class="hidden fixed inset-0 items-center justify-center z-50" aria-hidden="true" style="display:none;">
        <div id="checklistModalDialog" class="bg-white rounded-lg shadow-lg max-w-4xl w-full mx-4 transform transition-transform" role="dialog" aria-modal="true" style="max-height:90vh; overflow:auto; padding:0;">

            <!-- Dynamic Header -->
            <div class="modal-header" id="checklist-modal-header">
                <h3 id="checklist-modal-title" class="text-xl font-bold">Checklist Quick Insert</h3>
                <button id="closeChecklistModalBtn" class="text-2xl leading-none hover:opacity-70 transition-opacity">&times;</button>
            </div>

            <div class="flex flex-1 overflow-hidden" style="height: 70vh;">
                <!-- Sidebar (Categories) -->
                <div id="checklist-categories" class="w-1/3 bg-gray-50 border-r border-gray-200 overflow-y-auto p-3 space-y-1">
                    <!-- Dynamic content -->
                </div>

                <!-- Main Content (Items) -->
                <div id="checklist-items" class="w-2/3 p-4 overflow-y-auto bg-white">
                    <!-- Dynamic content -->
                </div>
            </div>

            <div class="modal-footer bg-white border-t border-gray-200 p-3 flex justify-end gap-3">
                <button id="closeChecklistBtn" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 font-medium transition">Cancel</button>
                <button id="insertChecklistBtn" class="px-4 py-2 rounded-lg bg-gray-800 text-white hover:bg-gray-900 font-medium shadow-sm transition">Insert Selected</button>
            </div>
        </div>
    </div>

    <div id="clinicalSuggestionsModal" class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-2xl max-w-3xl w-full max-h-[90vh] flex flex-col">
            <div class="flex items-center justify-between p-4 border-b">
                <h3 class="text-xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="lightbulb" class="w-5 h-5 mr-2 text-yellow-500"></i>
                    Clinical Suggestions
                </h3>
                <button id="closeSuggestionsModal" class="text-gray-500 hover:text-gray-700 text-2xl">&times;</button>
            </div>
            <div class="flex-1 overflow-hidden flex">
                <div class="w-1/3 border-r bg-gray-50 overflow-y-auto p-2" id="suggestions-sidebar"></div>
                <div class="w-2/3 p-4 overflow-y-auto" id="suggestions-content">
                    <div class="text-center text-gray-400 mt-10">Select a category to view suggestions.</div>
                </div>
            </div>
        </div>
    </div>


    <style>
        /* Quill & UI overrides */
        .ql-toolbar { border-top-left-radius: 0.375rem; border-top-right-radius: 0.375rem; border-bottom: none !important; background-color: #f9fafb; border-color: #d1d5db !important; }
        .ql-container { border-bottom-left-radius: 0.375rem; border-bottom-right-radius: 0.375rem; border: 1px solid #d1d5db !important; }
        .quill-editor { display: flex; flex-direction: column; border: 1px solid #d1d5db; border-radius: 0.375rem; min-height: 100%; }

        /* INCREASED FONT SIZE */
        .ql-editor {
            flex-grow: 1;
            min-height: 350px;
            background: #fff;
            font-size: 16px; /* Better readability */
            line-height: 1.6;
        }

        /* Ensure internal elements inherit the size */
        .ql-editor p, .ql-editor ul, .ql-editor ol, .ql-editor li {
            font-size: 16px;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        textarea.hidden { display: none !important; }

        .ai-spinner { border: 4px solid rgba(255, 255, 255, 0.3); border-top: 4px solid #fff; border-radius: 50%; width: 20px; height: 20px; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Tab Active States */
        .tab-btn.active {
            border-bottom-color: #4f46e5; /* indigo-600 */
            color: #4f46e5;
            background-color: #fff;
            border-top-left-radius: 0.375rem;
            border-top-right-radius: 0.375rem;
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            border-top: 3px solid transparent; /* Prevent layout shift */
            margin-bottom: -1px; /* Overlap border */
        }

        /* Specific Tab Colors when Active */
        #tab-btn-chief_complaint.active { border-bottom-color: #eab308; color: #854d0e; background-color: #fefce8; border-top-color: #eab308; }
        #tab-btn-subjective.active { border-bottom-color: #3b82f6; color: #1e40af; background-color: #eff6ff; border-top-color: #3b82f6; }
        #tab-btn-objective.active { border-bottom-color: #22c55e; color: #15803d; background-color: #f0fdf4; border-top-color: #22c55e; }
        #tab-btn-assessment.active { border-bottom-color: #f97316; color: #c2410c; background-color: #fff7ed; border-top-color: #f97316; }
        #tab-btn-plan.active { border-bottom-color: #6366f1; color: #4338ca; background-color: #eef2ff; border-top-color: #6366f1; }
        #tab-btn-orders.active { border-bottom-color: #0d9488; color: #0f766e; background-color: #f0fdfa; border-top-color: #0d9488; }
        #tab-btn-finalize.active { border-bottom-color: #a855f7; color: #7e22ce; background-color: #faf5ff; border-top-color: #a855f7; }

        /* Modal Styling */
        .modal-backdrop { background-color: rgba(0, 0, 0, 0.6); }
        .modal-dialog { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-width: 42rem; width: 100%; max-height: 90vh; display: flex; flex-direction: column; transform: scale(0.95); opacity: 0; transition: all 0.2s ease-out; }
        .show-modal { opacity: 1 !important; transform: scale(1) !important; }
        .modal-header { padding: 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e5e7eb; }
        .modal-close-btn { color: #9ca3af; font-size: 2.25rem; line-height: 1; transition: color 150ms ease-in-out; }
        .modal-close-btn:hover { color: #374151; }
        .modal-body { padding: 1.5rem; flex-grow: 1; overflow-y: auto; }
        .modal-footer { background-color: #f9fafb; padding: 1rem 1.5rem; border-top: 1px solid #e5e7eb; border-bottom-left-radius: 0.75rem; border-bottom-right-radius: 0.75rem; display: flex; justify-content: flex-end; }
        .modal-footer > * + * { margin-left: 0.5rem; }
        .modal-btn { font-weight: 600; padding: 0.5rem 1.25rem; border-radius: 0.5rem; transition: all 150ms ease-in-out; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); display: flex; align-items: center; border: 1px solid transparent; color: white; }

        /* Checklist Theme Styles */
        .theme-blue .modal-header { background-color: #eff6ff; border-bottom: 1px solid #dbeafe; color: #1e40af; }
        .theme-green .modal-header { background-color: #f0fdf4; border-bottom: 1px solid #dcfce7; color: #166534; }
        .theme-orange .modal-header { background-color: #fff7ed; border-bottom: 1px solid #ffedd5; color: #9a3412; }
        .theme-indigo .modal-header { background-color: #eef2ff; border-bottom: 1px solid #e0e7ff; color: #3730a3; }
        .theme-teal .modal-header { background-color: #f0fdfa; border-bottom: 1px solid #ccfbf1; color: #115e59; }

        /* Enhanced Checklist Categories (Sidebar) */
        .checklist-category {
            padding: 10px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #4b5563;
            border-left: 3px solid transparent;
        }
        .checklist-category:hover { background-color: #e5e7eb; }

        /* Active Category Styling per Theme */
        .theme-blue .checklist-category.active { background-color: #dbeafe; color: #1e40af; border-left-color: #2563eb; }
        .theme-green .checklist-category.active { background-color: #dcfce7; color: #15803d; border-left-color: #16a34a; }
        .theme-orange .checklist-category.active { background-color: #ffedd5; color: #c2410c; border-left-color: #ea580c; }
        .theme-indigo .checklist-category.active { background-color: #e0e7ff; color: #4338ca; border-left-color: #4f46e5; }
        .theme-teal .checklist-category.active { background-color: #f0fdfa; color: #0f766e; border-left-color: #0d9488; }

        /* Enhanced Checklist Items (Grid Cards) */
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); /* Auto columns */
            gap: 12px;
        }

        .checklist-item-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 12px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s ease;
            background-color: white;
        }
        .checklist-item-label:hover {
            border-color: #9ca3af;
            background-color: #f9fafb;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        /* Custom Checkbox styling */
        .checklist-item-input { opacity: 0; width: 0; height: 0; position: absolute; }
        .checklist-item-box {
            width: 20px; height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            margin-right: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
        }
        .checklist-item-box::after {
            content: '';
            width: 5px; height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg) scale(0);
            transition: transform 0.2s;
        }
        .checklist-item-input:checked + .checklist-item-box { border-color: transparent; }
        .checklist-item-input:checked + .checklist-item-box::after { transform: rotate(45deg) scale(1); }

        /* Theme Checkbox Colors */
        .theme-blue .checklist-item-input:checked + .checklist-item-box { background-color: #2563eb; }
        .theme-green .checklist-item-input:checked + .checklist-item-box { background-color: #16a34a; }
        .theme-orange .checklist-item-input:checked + .checklist-item-box { background-color: #ea580c; }
        .theme-indigo .checklist-item-input:checked + .checklist-item-box { background-color: #4f46e5; }
        .theme-teal .checklist-item-input:checked + .checklist-item-box { background-color: #0d9488; }

        /* Active Item Border */
        .theme-blue .checklist-item-input:checked ~ .checklist-item-text { color: #1e40af; font-weight: 600; }
        .theme-green .checklist-item-input:checked ~ .checklist-item-text { color: #15803d; font-weight: 600; }
        .theme-orange .checklist-item-input:checked ~ .checklist-item-text { color: #c2410c; font-weight: 600; }
        .theme-indigo .checklist-item-input:checked ~ .checklist-item-text { color: #4338ca; font-weight: 600; }
        .theme-teal .checklist-item-input:checked ~ .checklist-item-text { color: #0f766e; font-weight: 600; }

        .badge-count-active { background-color: #e5e7eb; color: #374151; padding: 2px 6px; border-radius: 999px; font-size: 0.7rem; }
        .badge-count-empty { display: none; }

        /* Dictation Recording State */
        .ql-voice.is-recording svg { fill: #fee2e2; }
        .dictation-active { border-color: #ef4444 !important; box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2); }

        /* Z-Index Fixes */
        #checklistModal, #previewModal { z-index: 10000; pointer-events: auto; }
        #checklistModal .modal-dialog, #previewModal .modal-dialog { position: relative; z-index: 10001 !important; pointer-events: auto !important; }
    </style>
    <style>
        /* Suggestion Modal Styles */
        #clinicalSuggestionsModal.open { opacity: 1; }
        .suggestion-category { display: flex; align-items: center; cursor: pointer; padding: 12px 16px; border-radius: 8px; font-weight: 500; color: #4b5563; transition: all 0.2s ease-in-out; }
        .suggestion-category:hover { background-color: #ffffff; color: #1f2937; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .suggestion-category.active { background-color: #ffffff; color: #4f46e5; border-color: #e0e7ff; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); font-weight: 600; }
        .suggestion-item { background: white; border: 1px solid #f3f4f6; padding: 16px; border-radius: 10px; margin-bottom: 12px; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; }
        .suggestion-item:hover { border-color: #c7d2fe; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05); transform: translateY(-1px); }
        .insert-action { margin-top: 12px; display: flex; align-items: center; justify-content: flex-end; opacity: 0; transform: translateY(10px); transition: all 0.2s ease-in-out; }
        .suggestion-item:hover .insert-action { opacity: 1; transform: translateY(0); }
        .btn-insert { background-color: #4f46e5; color: white; font-size: 0.75rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; display: flex; align-items: center; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.3); }
    </style>

    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>

    <script>
        window.phpVars = {
            patientId: <?php echo $safe_patient_id; ?>,
            appointmentId: <?php echo $safe_appointment_id; ?>,
            userId: <?php echo $safe_user_id; ?>
        };
        window.quillEditors = {};
        window.globalDataBundle = {};

        // INLINE FALLBACK: If external JS fails to load or is blocked, this ensures basic tab switching works.
        window.switchTab = window.switchTab || function(tabName) {
                console.log('Switching tab (fallback):', tabName);
                document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active', 'bg-gray-50'));
                const content = document.getElementById(`tab-content-${tabName}`);
                if (content) content.classList.remove('hidden');
                const btn = document.getElementById(`tab-btn-${tabName}`);
                if (btn) btn.classList.add('active');
                if (tabName !== 'finalize') window.activeChecklistSection = tabName;
            };
    </script>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js"></script>

    <script src="js/floating_alerts.js?v=<?php echo $ver; ?>"></script>
    <link rel="stylesheet" href="css/visit_notes_ui.css?v=<?php echo $ver; ?>">
    <script src="js/visit_notes_ui.js?v=<?php echo $ver; ?>" defer></script>
    <script src="js/autosave_config.js?v=<?php echo $ver; ?>"></script>
    <script src="js/autosave_manager_drafts.js?v=<?php echo $ver; ?>"></script>
    <script src="js/autosave_manager_alias.js?v=<?php echo $ver; ?>"></script>
    <script src="js/autosave_alert_adapter.js?v=<?php echo $ver; ?>"></script>
    <link rel="stylesheet" href="css/quick_insert_modal.css?v=<?php echo $ver; ?>">
    <script src="js/quick_insert_modal.js?v=<?php echo $ver; ?>" defer></script>
    <link rel="stylesheet" href="css/quick_insert_inserts.css?v=<?php echo $ver; ?>">
    <script src="js/place_quick_insert_button_in_wrappers.js?v=<?php echo $ver; ?>" defer></script>
    <link rel="stylesheet" href="css/quick_insert_modal_scroll.css?v=<?php echo $ver; ?>">
    <script src="js/quick_insert_modal_defaults.js?v=<?php echo $ver; ?>" defer></script>
    <script src="js/quick_insert_modal_clean.js?v=<?php echo $ver; ?>" defer></script>
    <script src="js/visit_notes_dictation.js?v=<?php echo $ver; ?>"></script>

    <script src="js/quill_editor_manager.js?v=<?php echo $ver; ?>"></script>
    <!-- REMOVED conflicting quill_autosave_integration.js -->
    <script src="js/draft_merge_ui.js?v=<?php echo $ver; ?>"></script>
    <!-- IMPORTANT: Added version parameter to bust cache -->
    <script src="js/visit_notes_logic.js?v=<?php echo $ver; ?>"></script>
    <script src="js/visit_signature.js?v=<?php echo $ver; ?>"></script>
    <script src="js/autosave_compat_shim.js?v=<?php echo $ver; ?>"></script>
    <script src="js/clinical_suggestions_integration.js?v=<?php echo $ver; ?>" defer></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Initialize Autosave manager (Safe Init)
            try {
                const appId = parseInt(window.phpVars.appointmentId, 10);
                const uid = parseInt(window.phpVars.userId, 10);

                if (appId > 0 && uid > 0 && typeof AutosaveManager !== 'undefined') {
                    const config = {
                        appointment_id: appId,
                        appointmentId: appId, // pass both keys to be safe
                        user_id: uid,
                        userId: uid,
                        intervalMs: 20000
                    };

                    const autosave = new AutosaveManager(config);

                    if (autosave && typeof autosave.init === 'function') {
                        autosave.init(config);
                    }
                    if (autosave && typeof autosave.start === 'function') {
                        autosave.start();
                    }
                    window.Autosave = autosave;
                } else {
                    console.warn('Skipping AutosaveManager init: ID missing or AutosaveManager class undefined.');
                }
            } catch (e) {
                console.warn('Autosave init error:', e);
            }
        });
    </script>

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
                            Overwrite Current Note?
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to replace the current content with the data from the last visit?
                            </p>
                            <p class="text-sm text-red-600 font-bold mt-2">
                                This action cannot be undone.
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

    <!-- AI Review Modal -->
    <div id="ai-review-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
        <div class="relative p-5 border w-full max-w-3xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex justify-between items-center pb-3 border-b">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Review AI Suggestions</h3>
                    <button onclick="closeAiModal()" class="text-gray-400 hover:text-gray-500">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div class="mt-4 max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-500 mb-2">Proposed Changes:</p>
                            <div id="ai-diff-content" class="p-4 bg-gray-50 rounded border text-base leading-relaxed whitespace-pre-wrap font-sans"></div>
                        </div>
                    </div>
                </div>

                <div class="mt-5 flex justify-end gap-3 pt-4 border-t">
                    <button onclick="closeAiModal()" class="px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 font-medium">
                        Discard
                    </button>
                    <button id="ai-accept-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-md shadow-sm hover:bg-indigo-700 font-medium flex items-center">
                        <i data-lucide="check" class="w-4 h-4 mr-2"></i> Accept Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Diff Styles */
        ins { background-color: #dcfce7; text-decoration: none; color: #166534; }
        del { background-color: #fee2e2; color: #991b1b; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('toggleSidebarBtn');
            const mainCol = document.getElementById('main-charting-column');
            const sidebarCol = document.getElementById('sidebar-column');
            
            if (toggleBtn && mainCol && sidebarCol) {
                toggleBtn.addEventListener('click', function() {
                    // Toggle sidebar visibility
                    sidebarCol.classList.toggle('hidden');
                    
                    // Adjust main column width
                    // If sidebar is hidden, main column should be full width
                    if (sidebarCol.classList.contains('hidden')) {
                        mainCol.classList.remove('lg:w-2/3');
                        mainCol.classList.add('w-full');
                        // Update button state/icon if needed
                        toggleBtn.classList.add('bg-gray-100');
                    } else {
                        mainCol.classList.add('lg:w-2/3');
                        mainCol.classList.remove('w-full');
                        toggleBtn.classList.remove('bg-gray-100');
                    }
                });
            }
        });
    </script>

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

        // 2. Disable Inputs
        const formElements = document.querySelectorAll('input, textarea, select, button');
        formElements.forEach(el => {
            // Skip navigation links/buttons
            if (el.tagName === 'A' || el.closest('nav') || el.innerText.includes('Next') || el.innerText.includes('Prev') || el.innerText.includes('Back') || el.innerText.includes('Review')) {
                return;
            }
            // Skip sidebar toggle and tab buttons
            if (el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn' || el.classList.contains('tab-btn') || el.id === 'previewNoteBtn') return;
            
            // Skip modal close buttons
            if (el.classList.contains('modal-close-btn') || el.classList.contains('modal-btn') || el.closest('.modal-footer')) return;

            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
        });

        // 3. Disable Quill Editors
        // Wait a bit for editors to initialize
        setTimeout(() => {
            if (window.quillEditors) {
                Object.values(window.quillEditors).forEach(quill => {
                    if (quill && typeof quill.disable === 'function') {
                        quill.disable();
                    }
                });
            }
            // Also target containers just in case
            document.querySelectorAll('.ql-editor').forEach(el => {
                el.contentEditable = false;
                el.classList.add('bg-gray-50');
            });
        }, 1000);

        // 4. Hide specific action buttons
        const hideSelectors = [
            '#saveNoteBtn', 
            '#cloneLastVisitBtn', 
            '.quick-insert-btn', 
            'button[onclick^="insertWNL"]', // Insert Normal buttons
            '.clinical-suggestions-btn',
            '#generateProcNarrativeBtn',
            '#clear-signature',
            '#add-addendum-form' // Strict read-only
        ];
        
        hideSelectors.forEach(sel => {
            document.querySelectorAll(sel).forEach(el => el.style.display = 'none');
        });

        // 5. Disable Signature Pad
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            canvas.style.pointerEvents = 'none';
            canvas.style.backgroundColor = '#f3f4f6';
        }

        // 6. Stop Autosave
        if (window.Autosave) {
            window.Autosave.stop();
        }
        // Also try to stop the one initialized in DOMContentLoaded
        setTimeout(() => {
            if (window.Autosave) window.Autosave.stop();
        }, 2000);
    });
</script>
<?php endif; ?>
<!-- Save Template Modal -->
<div id="save-template-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Save as Template</h3>
        <input type="text" id="template-name-input" class="w-full border rounded p-2 mb-4" placeholder="Template Name">
        <div class="flex justify-end space-x-2">
            <button onclick="closeSaveTemplateModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
            <button id="confirm-save-template-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
    </div>
</div>

<!-- Load Template Modal -->
<div id="load-template-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50 flex items-center justify-center">
    <div class="relative p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Load Template</h3>
        <div id="template-list" class="max-h-60 overflow-y-auto mb-4 space-y-2">
            <!-- Templates will be injected here -->
            <p class="text-gray-500 text-sm italic">Loading...</p>
        </div>
        <div class="flex justify-end">
            <button onclick="closeLoadTemplateModal()" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/templates/footer.php';
?>