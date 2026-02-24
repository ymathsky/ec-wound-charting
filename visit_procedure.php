<?php
// Filename: visit_procedure.php
// UPDATED: Aligned with 'save_superbill_services.php' bulk-save logic.
// UPDATED: Now handles description lookup since 'superbill_services' table doesn't store descriptions.

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

// --- FETCH DATA FOR PROCEDURE NOTE ---
// 1. Existing Note
$proc_note_sql = "SELECT procedure_note FROM visit_notes WHERE appointment_id = ?";
$stmt = $conn->prepare($proc_note_sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
$existing_note = $res->fetch_assoc()['procedure_note'] ?? '';
$stmt->close();

// 2. Wound Assessments (for auto-generation)
$wounds_sql = "SELECT wa.*, w.location, w.wound_type 
               FROM wound_assessments wa
               JOIN wounds w ON wa.wound_id = w.wound_id
               WHERE wa.appointment_id = ?";
$stmt = $conn->prepare($wounds_sql);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$res = $stmt->get_result();
$wound_assessments = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Patient Info (for auto-generation)
$pat_sql = "SELECT first_name, last_name, date_of_birth FROM patients WHERE patient_id = ?";
$stmt = $conn->prepare($pat_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$patient_info = $res->fetch_assoc();
$stmt->close();

// 4. Medications (for auto-generation)
$meds_sql = "SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND (end_date IS NULL OR end_date >= CURDATE()) AND status = 'Active'";
$stmt = $conn->prepare($meds_sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
$medications = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

    <!-- Inject Data for JS -->
    <script>
        window.procedureData = {
            userId: <?php echo $user_id; ?>,
            appointmentId: <?php echo $appointment_id; ?>,
            existingNote: <?php echo json_encode($existing_note); ?>,
            wounds: <?php echo json_encode($wound_assessments); ?>,
            patient: <?php echo json_encode($patient_info); ?>,
            medications: <?php echo json_encode($medications); ?>
        };
    </script>

    <!-- Include Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- Include Quill Editor -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

    <style>
        /* Quill Editor Font Size */
        #editor-container .ql-editor {
            font-size: 16px;
            line-height: 1.6;
        }
        #editor-container .ql-editor p {
            margin-bottom: 0.5em;
        }

        /* Custom Scrollbar for Diagnosis List */
        #diagnosis-selection-container::-webkit-scrollbar {
            width: 6px;
        }
        #diagnosis-selection-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        #diagnosis-selection-container::-webkit-scrollbar-thumb {
            background: #c7c7c7;
            border-radius: 4px;
        }
        #diagnosis-selection-container::-webkit-scrollbar-thumb:hover {
            background: #a0a0a0;
        }
    </style>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md sticky top-0 z-10">
                <div class="flex items-center">
                    <!-- Mobile Hamburger Menu Button -->
                    <button onclick="openSidebar()" class="md:hidden mr-4 text-gray-600 hover:text-gray-900 focus:outline-none">
                        <i data-lucide="menu" class="w-8 h-8"></i>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Procedures & Billing</h1>
                        <p class="text-sm text-gray-600">Step 5: Enter CPT codes for this visit.</p>
                    </div>
                </div>
                <div class="hidden lg:flex items-center space-x-4">
                    <!-- Auto-save Status Indicator -->
                    <span id="note-save-status" class="text-sm text-gray-500 italic font-medium transition-colors duration-300"></span>

                    <a href="visit_medications.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>&user_id=<?php echo $user_id; ?>"
                       class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md hover:bg-gray-300 transition flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back: Meds
                    </a>
                    <a href="visit_notes.php?appointment_id=<?php echo $appointment_id; ?>&patient_id=<?php echo $patient_id; ?>&user_id=<?php echo $user_id; ?>"
                       class="bg-green-600 text-white font-bold py-2 px-4 rounded-md hover:bg-green-700 transition flex items-center">
                        Next: Visit Note <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </a>
                </div>
            </header>

            <?php require_once 'templates/visit_submenu.php'; ?>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <div class="flex flex-col lg:flex-row gap-6 h-full">

                    <!-- Left Column: Procedure Selection -->
                    <div class="w-full lg:w-2/3 space-y-6">



                        <!-- Procedure Details Checklist -->
                        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                                    <i data-lucide="clipboard-check" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Procedure Details
                                </h3>
                                <span class="text-xs font-medium text-gray-500 bg-white px-2 py-1 rounded border border-gray-200">Auto-saves</span>
                            </div>
                            
                            <div class="p-6 space-y-8">
                                
                                <!-- Section 1: Safety & Prep -->
                                <div>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Safety & Preparation</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <!-- Anesthesia -->
                                        <div class="bg-indigo-50/50 rounded-lg p-4 border border-indigo-100">
                                            <h5 class="font-bold text-indigo-900 text-sm mb-3 flex items-center">
                                                <i data-lucide="syringe" class="w-4 h-4 mr-2"></i> Anesthesia
                                            </h5>
                                            <div class="space-y-2.5">
                                                <label class="flex items-center group cursor-pointer">
                                                    <div class="relative flex items-center">
                                                        <input type="radio" name="anesthesia" value="topical 5 percent lidocaine spray" class="peer h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500" checked>
                                                    </div>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-indigo-700 transition-colors">Topical Lidocaine 5%</span>
                                                </label>
                                                <label class="flex items-center group cursor-pointer">
                                                    <div class="relative flex items-center">
                                                        <input type="radio" name="anesthesia" value="injectable lidocaine 1 percent" class="peer h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                                    </div>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-indigo-700 transition-colors">Injectable Lidocaine 1%</span>
                                                </label>
                                                <label class="flex items-center group cursor-pointer">
                                                    <div class="relative flex items-center">
                                                        <input type="radio" name="anesthesia" value="none" class="peer h-4 w-4 text-indigo-600 border-gray-300 focus:ring-indigo-500">
                                                    </div>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-indigo-700 transition-colors">None</span>
                                                </label>
                                            </div>
                                        </div>

                                        <!-- Consent & Safety -->
                                        <div class="bg-green-50/50 rounded-lg p-4 border border-green-100">
                                            <h5 class="font-bold text-green-900 text-sm mb-3 flex items-center">
                                                <i data-lucide="shield-check" class="w-4 h-4 mr-2"></i> Consent & Safety
                                            </h5>
                                            <div class="space-y-2.5">
                                                <label class="flex items-center group cursor-pointer">
                                                    <input type="checkbox" id="chk_consent" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" checked>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-green-700 transition-colors">Consent Obtained</span>
                                                </label>
                                                <label class="flex items-center group cursor-pointer">
                                                    <input type="checkbox" id="chk_timeout" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" checked>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-green-700 transition-colors">Time-out Performed</span>
                                                </label>
                                                <label class="flex items-center group cursor-pointer">
                                                    <input type="checkbox" id="chk_risks" class="h-4 w-4 text-green-600 border-gray-300 rounded focus:ring-green-500" checked>
                                                    <span class="ml-2 text-sm text-gray-700 group-hover:text-green-700 transition-colors">Risks/Benefits Discussed</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Medications -->
                                <div>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Medications</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-2">Antibiotics Administered</label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <i data-lucide="pill" class="h-4 w-4 text-gray-400"></i>
                                                </div>
                                                <input type="text" id="txt_antibiotics" class="pl-10 form-input w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g. None, Cefazolin 1g IV">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-2">Current Meds (Reconciliation)</label>
                                            <div id="meds-checklist-container" class="max-h-40 overflow-y-auto border border-gray-200 rounded-md p-2 bg-gray-50 mb-2 custom-scrollbar shadow-inner">
                                                <span class="text-xs text-gray-400 italic">Loading meds...</span>
                                            </div>
                                            <input type="text" id="txt_meds_manual" class="form-input w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" placeholder="Add other meds or notes...">
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 3: Wound Debridement -->
                                <div id="wound-debridement-container">
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Wound Debridement Details</h4>
                                    <div id="wound-debridement-list" class="space-y-4">
                                        <!-- Injected via JS -->
                                    </div>
                                </div>

                                <!-- Section 4: Factors -->
                                <div>
                                    <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Factors Impeding Healing</h4>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Diabetes" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Diabetes</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="PVD" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">PVD</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Venous Insufficiency" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Venous Insuff.</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Neuropathy" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Neuropathy</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Mobility/Pressure" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Mobility/Pressure</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Nutrition" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Nutrition</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Smoking" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Smoking</span>
                                        </label>
                                        <label class="flex items-center p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors">
                                            <input type="checkbox" name="impeding_factors" value="Edema" class="text-indigo-600 focus:ring-indigo-500 rounded">
                                            <span class="ml-2 text-xs font-medium text-gray-700">Edema</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Section 5: Graft Info -->
                                <div id="graft-details-container" class="transition-opacity duration-300">
                                    <div class="flex justify-between items-center mb-4 border-b border-gray-100 pb-2">
                                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">Graft / Product Details</h4>
                                        <span id="no-graft-msg" class="text-xs text-gray-500 italic hidden bg-gray-100 px-2 py-1 rounded">No skin graft performed</span>
                                    </div>
                                    <div id="graft-inputs-wrapper" class="grid grid-cols-1 md:grid-cols-3 gap-4 transition-all duration-300 bg-blue-50/50 p-4 rounded-lg border border-blue-100">
                                        <div>
                                            <label class="block text-xs font-semibold text-blue-800 mb-1">Product Name</label>
                                            <input type="text" id="txt_product_name" class="form-input w-full text-sm border-blue-200 rounded focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. Tri-Membrane">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-blue-800 mb-1">Size (cm²)</label>
                                            <input type="text" id="txt_product_size" class="form-input w-full text-sm border-blue-200 rounded focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. 6.00">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-blue-800 mb-1">Serial / Lot</label>
                                            <input type="text" id="txt_product_serial" class="form-input w-full text-sm border-blue-200 rounded focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. BLS25-2285">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Procedure Narrative -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-800">Procedure Note</h3>
                                <div class="flex gap-2">
                                    <button type="button" id="copy-last-visit-btn" class="text-sm bg-blue-50 text-blue-700 border border-blue-200 px-3 py-1 rounded hover:bg-blue-100 font-medium transition flex items-center" title="Copy note from previous visit">
                                        <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy Last
                                    </button>
                                    <button type="button" id="start-dictation-btn" class="text-sm bg-gray-100 text-gray-700 px-3 py-1 rounded hover:bg-gray-200 font-medium transition flex items-center" title="Start Voice Dictation">
                                        <i data-lucide="mic" class="w-4 h-4 mr-2"></i> Dictate
                                    </button>
                                    <button type="button" id="generate-note-btn" class="text-sm bg-indigo-100 text-indigo-700 px-3 py-1 rounded hover:bg-indigo-200 font-medium transition flex items-center">
                                        <i data-lucide="wand-2" class="w-4 h-4 mr-2"></i> Generate Narrative
                                    </button>
                                    
                                    <!-- Templates Dropdown -->
                                    <div class="relative inline-block text-left">
                                        <button type="button" id="templates-menu-btn" class="text-sm bg-purple-50 text-purple-700 border border-purple-200 px-3 py-1 rounded hover:bg-purple-100 font-medium transition flex items-center" aria-expanded="false" aria-haspopup="true">
                                            <i data-lucide="layout-template" class="w-4 h-4 mr-2"></i> Templates
                                            <i data-lucide="chevron-down" class="w-3 h-3 ml-1"></i>
                                        </button>

                                        <div id="templates-dropdown" class="hidden origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                                            <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="templates-menu-btn">
                                                <button type="button" id="save-template-btn" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 flex items-center" role="menuitem">
                                                    <i data-lucide="save" class="w-4 h-4 mr-2 text-green-500"></i> Save Current as Template
                                                </button>
                                                <div class="border-t border-gray-100 my-1"></div>
                                                <div id="templates-list-container" class="max-h-60 overflow-y-auto">
                                                    <!-- Templates will be loaded here -->
                                                    <span class="block px-4 py-2 text-xs text-gray-500 italic">Loading...</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quill Editor Container -->
                            <div id="editor-container" class="h-64 bg-white rounded-md"></div>
                            <input type="hidden" id="procedure_note">

                            <div class="flex justify-end mt-2">
                                <button type="button" id="save-note-btn" class="bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition">
                                    Save Note
                                </button>
                            </div>
                        </div>

                        <!-- Smart Suggest Panel -->
                        <div id="smart-cpt-panel" class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 shadow-sm hidden">
                            <div class="flex justify-between items-center mb-3">
                                <h3 class="text-lg font-bold text-indigo-800 flex items-center">
                                    <i data-lucide="sparkles" class="w-5 h-5 mr-2 text-indigo-600"></i>
                                    Smart CPT Suggestions
                                </h3>
                                <span class="text-xs text-indigo-600 bg-indigo-100 px-2 py-1 rounded-full">Based on Wound Assessment</span>
                            </div>
                            <div id="suggestions-container" class="space-y-2">
                                <div class="flex justify-center p-2"><div class="spinner"></div></div>
                            </div>
                        </div>

                        <!-- Manual Entry Card -->
                        <div class="bg-white rounded-lg shadow-lg p-6 relative">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">Add Procedure (CPT)</h3>

                            <form id="addProcedureForm" class="flex flex-col gap-4">
                                <!-- Top Row: Inputs + Units + Button -->
                                <div class="flex flex-col md:flex-row gap-4 items-start md:items-end">
                                    
                                    <!-- Search / Manual Input Area -->
                                    <div class="flex-grow w-full">
                                        <!-- Search Mode -->
                                        <div id="search-mode-container" class="relative w-full">
                                            <label for="cpt_search" class="form-label">Search Database</label>
                                            <input type="text" id="cpt_search" class="form-input w-full" placeholder="Type code (e.g. 11042) or keyword..." autocomplete="off">
                                            <div id="cpt_search_results" class="absolute z-50 w-full bg-white border border-gray-300 mt-1 rounded-md shadow-xl max-h-60 overflow-y-auto hidden"></div>
                                            <button type="button" id="toggle-manual-btn" class="text-xs text-indigo-600 hover:underline mt-1 font-medium">
                                                Code not found? Enter manually
                                            </button>
                                        </div>

                                        <!-- Manual Mode -->
                                        <div id="manual-mode-container" class="hidden flex flex-col md:flex-row gap-4 items-end w-full">
                                            <div class="w-full md:w-1/3">
                                                <label class="form-label">Manual Code</label>
                                                <input type="text" id="manual_code" class="form-input w-full" placeholder="e.g. 99999">
                                            </div>
                                            <div class="w-full md:flex-grow">
                                                <label class="form-label">Description</label>
                                                <input type="text" id="manual_desc" class="form-input w-full" placeholder="Procedure description">
                                            </div>
                                            <div class="pb-2">
                                                 <button type="button" id="cancel-manual-btn" class="text-gray-500 hover:text-red-600" title="Cancel Manual Entry">
                                                    <i data-lucide="x-circle" class="w-6 h-6"></i>
                                                 </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Units -->
                                    <div class="w-24 flex-shrink-0">
                                        <label for="units" class="form-label">Units</label>
                                        <input type="number" id="units" class="form-input w-full" value="1" min="1">
                                    </div>

                                    <!-- Add Button -->
                                    <div class="flex-shrink-0">
                                        <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700 h-11 flex items-center justify-center min-w-[100px]">
                                            <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add
                                        </button>
                                    </div>
                                </div>

                                <!-- Bottom Row: Diagnosis Linking -->
                                <div class="w-full mt-2 border-t border-gray-100 pt-4">
                                    <label class="text-sm font-semibold text-gray-700 mb-3 block flex items-center">
                                        <i data-lucide="link" class="w-4 h-4 mr-1.5 text-indigo-500"></i>
                                        Link Diagnosis <span class="text-gray-400 font-normal ml-1 text-xs">(Select relevant diagnoses)</span>
                                    </label>
                                    <div id="diagnosis-selection-container" class="grid grid-cols-1 md:grid-cols-3 gap-3 max-h-60 overflow-y-auto pr-1 custom-scrollbar">
                                        <div class="text-sm text-gray-500 italic p-2">Loading diagnoses...</div>
                                    </div>
                                </div>
                                
                                <input type="hidden" id="selected_code_val">
                                <input type="hidden" id="selected_desc_val">
                            </form>
                        </div>

                        <!-- Selected Procedures List -->
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold text-gray-800">Procedures for this Visit</h3>
                                <div id="save-status" class="text-sm text-gray-500 italic h-6"></div>
                            </div>

                            <div id="procedures-list" class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody id="procedures-tbody" class="bg-white divide-y divide-gray-200">
                                    <!-- Items injected via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: SCREENING -->
                    <div class="w-full lg:w-1/3">
                        <!-- SCREENING (Braden Scale) -->
                        <div class="bg-white rounded-lg shadow-lg p-6 sticky top-24">
                            <div class="flex justify-between items-center mb-2">
                                <h3 class="text-xl font-semibold text-gray-800">SCREENING</h3>
                                <div class="text-right">
                                    <span class="text-xs text-gray-500 uppercase font-bold tracking-wider">Braden Score</span>
                                    <div class="text-2xl font-bold text-indigo-600 leading-none" id="braden-total-score">--</div>
                                </div>
                            </div>
                            
                            <button type="button" id="btn-screening-normal" class="mb-4 w-full text-xs bg-green-50 text-green-700 border border-green-200 py-1.5 rounded hover:bg-green-100 transition flex items-center justify-center font-medium">
                                <i data-lucide="check-circle" class="w-3 h-3 mr-1.5"></i> Quick Set: Low Risk / Normal
                            </button>

                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Sensory Perception</label>
                                    <select id="braden_sensory" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Completely Limited</option>
                                        <option value="2">2. Very Limited</option>
                                        <option value="3">3. Slightly Limited</option>
                                        <option value="4">4. No Impairment</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Moisture</label>
                                    <select id="braden_moisture" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Constantly Moist</option>
                                        <option value="2">2. Very Moist</option>
                                        <option value="3">3. Occasionally Moist</option>
                                        <option value="4">4. Rarely Moist</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Activity</label>
                                    <select id="braden_activity" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Bedfast</option>
                                        <option value="2">2. Chairfast</option>
                                        <option value="3">3. Walks Occasionally</option>
                                        <option value="4">4. Walks Frequently</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Mobility</label>
                                    <select id="braden_mobility" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Completely Immobile</option>
                                        <option value="2">2. Very Limited</option>
                                        <option value="3">3. Slightly Limited</option>
                                        <option value="4">4. No Limitation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Nutrition</label>
                                    <select id="braden_nutrition" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Very Poor</option>
                                        <option value="2">2. Probably Inadequate</option>
                                        <option value="3">3. Adequate</option>
                                        <option value="4">4. Excellent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Friction & Shear</label>
                                    <select id="braden_friction" class="braden-select form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select...</option>
                                        <option value="1">1. Problem</option>
                                        <option value="2">2. Potential Problem</option>
                                        <option value="3">3. No Apparent Problem</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 p-3 bg-gray-50 rounded border border-gray-200 flex justify-between items-center">
                                <p class="text-sm text-gray-700"><span class="font-bold">Risk Level:</span> <span id="braden-risk-level" class="font-medium text-gray-500">--</span></p>
                                <p class="text-xs text-gray-400 italic">Score range: 6-23 (Lower score = Higher risk)</p>
                            </div>

                            <!-- Additional Screenings -->
                            <div class="mt-6 pt-4 border-t border-gray-200 space-y-4">
                                <h4 class="font-bold text-gray-700 text-sm">Additional Screenings</h4>
                                
                                <!-- MNA -->
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="block text-xs font-bold text-gray-700">MNA Rating (0-14)</label>
                                        <span id="mna-risk" class="text-xs font-medium text-gray-500">--</span>
                                    </div>
                                    <input type="number" id="mna_score" class="form-input w-full text-sm" min="0" max="14" placeholder="Enter Score">
                                </div>

                                <!-- Norton -->
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="block text-xs font-bold text-gray-700">Norton Rating (5-20)</label>
                                        <span id="norton-risk" class="text-xs font-medium text-gray-500">--</span>
                                    </div>
                                    <input type="number" id="norton_score" class="form-input w-full text-sm" min="5" max="20" placeholder="Enter Score">
                                </div>

                                <!-- Bates-Jensen -->
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-1">Bates-Jensen Status</label>
                                    <select id="bates_jensen_status" class="form-select w-full text-sm border-gray-300 rounded-md focus:ring-indigo-500">
                                        <option value="">Select Status...</option>
                                        <option value="Healed status; no open wounds requiring scoring. Reinforced skin monitoring.">Healed / No Open Wounds</option>
                                        <option value="Wound improving; score decreased. Continue current plan.">Improving</option>
                                        <option value="Wound stalled; score unchanged. Re-evaluate plan.">Stalled</option>
                                        <option value="Wound deteriorating; score increased. Modify plan.">Deteriorating</option>
                                        <option value="N/A">N/A</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none"></div>

    <!-- Dictation Modal -->
    <div id="dictation-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6 relative">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <span class="relative flex h-3 w-3 mr-2">
                      <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                      <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                    </span>
                    Listening...
                </h3>
                <button id="close-dictation-btn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <textarea id="dictation-preview" class="w-full h-40 p-3 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-lg leading-relaxed" placeholder="Speak now..."></textarea>
                <p class="text-xs text-gray-500 mt-1 text-right">Text is being captured continuously.</p>
            </div>

            <div class="flex justify-end gap-3">
                <button id="clear-dictation-btn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                    Clear
                </button>
                <button id="insert-dictation-btn" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 flex items-center">
                    <i data-lucide="check" class="w-4 h-4 mr-2"></i> Stop & Insert
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Safe initialization
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const appointmentId = <?php echo $appointment_id; ?>;
            const proceduresTbody = document.getElementById('procedures-tbody');
            const saveStatus = document.getElementById('save-status');

            // Local state to hold procedures for bulk save
            // This is crucial because 'save_superbill_services.php' deletes everything first
            let currentProcedures = [];
            let availableDiagnoses = []; // Store fetched diagnoses

            // --- 0. FETCH DIAGNOSES ---
            async function fetchDiagnoses() {
                const container = document.getElementById('diagnosis-selection-container');
                try {
                    const res = await fetch(`api/get_diagnosis_data.php?appointment_id=${appointmentId}&patient_id=<?php echo $patient_id; ?>`);
                    const json = await res.json();
                    
                    if (json.success && json.data.length > 0) {
                        availableDiagnoses = json.data;
                        container.innerHTML = json.data.map(d => `
                            <label class="relative flex items-start p-3 rounded-lg border border-gray-200 bg-gray-50 cursor-pointer hover:bg-white hover:border-indigo-300 hover:shadow-sm transition-all group select-none">
                                <div class="flex items-center h-5">
                                    <input type="checkbox" class="diagnosis-checkbox w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 transition-colors" value="${d.visit_diagnosis_id}">
                                </div>
                                <div class="ml-3 text-sm w-full">
                                    <div class="flex justify-between items-center">
                                        <span class="font-bold text-gray-800 group-hover:text-indigo-700 transition-colors">${d.icd10_code}</span>
                                    </div>
                                    <p class="text-gray-500 text-xs mt-1 leading-snug line-clamp-2 group-hover:text-gray-700 transition-colors" title="${d.description}">${d.description}</p>
                                </div>
                            </label>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="text-sm text-gray-500 italic col-span-2">No diagnoses added for this visit yet.</div>';
                    }
                } catch (e) {
                    console.error("Error fetching diagnoses", e);
                    container.innerHTML = '<div class="text-sm text-red-500 italic">Failed to load diagnoses.</div>';
                }
            }

            // --- 1. FETCH & RENDER ---
            async function fetchProcedures() {
                try {
                    const res = await fetch(`api/get_superbill_data.php?appointment_id=${appointmentId}`);
                    if (!res.ok) throw new Error("API Error");
                    const data = await res.json();

                    // Sync local state with DB
                    if (data.services) {
                        currentProcedures = data.services.map(s => ({
                            cpt_code: s.cpt_code,
                            description: s.description || '', 
                            units: parseInt(s.units) || 1,
                            linked_diagnosis_ids: s.linked_diagnosis_ids || '' // Load linked IDs
                        }));
                    }
                    renderProcedures();
                } catch (e) {
                    console.error("Fetch error:", e);
                    proceduresTbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-red-500">Error loading procedures.</td></tr>';
                }
            }

            function renderProcedures() {
                // Update Graft Section Visibility based on current procedures/assessments
                if (typeof updateGraftSectionVisibility === 'function') {
                    updateGraftSectionVisibility();
                }

                if (currentProcedures.length === 0) {
                    proceduresTbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No procedures added yet.</td></tr>';
                    return;
                }

                proceduresTbody.innerHTML = currentProcedures.map((p, index) => {
                    // Resolve linked diagnosis IDs to codes for display
                    let linkedDisplay = '';
                    if (p.linked_diagnosis_ids) {
                        const ids = p.linked_diagnosis_ids.split(',');
                        const codes = ids.map(id => {
                            const dx = availableDiagnoses.find(d => d.visit_diagnosis_id == id);
                            return dx ? dx.icd10_code : '?';
                        }).filter(c => c !== '?');
                        
                        if (codes.length > 0) {
                            linkedDisplay = `
                                <div class="mt-2 flex flex-wrap gap-1">
                                    ${codes.map(code => `
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
                                            <i data-lucide="link" class="w-3 h-3 mr-1"></i>${code}
                                        </span>
                                    `).join('')}
                                </div>
                            `;
                        }
                    }

                    return `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-indigo-700">
                            ${p.cpt_code}
                            ${linkedDisplay}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">${p.description}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-medium">${p.units}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button class="text-red-600 hover:text-red-900 delete-proc-btn font-bold" data-index="${index}">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </td>
                    </tr>
                `}).join('');

                if (typeof lucide !== 'undefined') lucide.createIcons();
            }

            // --- TOAST NOTIFICATION ---
            function showToast(message, type = 'success') {
                const container = document.getElementById('toast-container');
                if (!container) return;
                
                const toast = document.createElement('div');
                const bgColor = type === 'success' ? 'bg-green-600' : 'bg-red-600';
                toast.className = `${bgColor} text-white px-4 py-3 rounded shadow-lg flex items-center transition-all duration-300 opacity-0 translate-y-2 pointer-events-auto min-w-[200px]`;
                toast.innerHTML = `
                    <i data-lucide="${type === 'success' ? 'check-circle' : 'alert-circle'}" class="w-5 h-5 mr-2"></i>
                    <span class="font-medium">${message}</span>
                `;
                
                container.appendChild(toast);
                if (typeof lucide !== 'undefined') lucide.createIcons();

                // Animate in
                requestAnimationFrame(() => {
                    toast.classList.remove('opacity-0', 'translate-y-2');
                });

                // Remove after 3s
                setTimeout(() => {
                    toast.classList.add('opacity-0', 'translate-y-2');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            // --- 2. BULK SAVE LOGIC ---
            // This matches your provided 'save_superbill_services.php' format:
            // { "appointment_id": 123, "services": [ { "cpt_code": "...", "units": 1 }, ... ] }
            async function saveAllProcedures() {
                saveStatus.textContent = "Saving...";
                saveStatus.className = "text-sm text-blue-600 font-medium h-6";

                try {
                    // Transform local state to exactly what the API expects
                    const servicesPayload = currentProcedures.map(p => ({
                        cpt_code: p.cpt_code,
                        units: p.units,
                        linked_diagnosis_ids: p.linked_diagnosis_ids // Send linked IDs
                    }));

                    const payload = {
                        appointment_id: appointmentId,
                        services: servicesPayload
                    };

                    const res = await fetch('api/save_superbill_services.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    if (!res.ok) {
                        const txt = await res.text();
                        throw new Error(`Server Error: ${res.status}`);
                    }

                    const result = await res.json(); // Consume body

                    saveStatus.textContent = "All changes saved.";
                    saveStatus.className = "text-sm text-green-600 font-medium h-6";
                    setTimeout(() => { saveStatus.textContent = ""; }, 3000);
                    return true;

                } catch (e) {
                    console.error("Save Error:", e);
                    saveStatus.textContent = "Error saving!";
                    saveStatus.className = "text-sm text-red-600 font-bold h-6";
                    alert("Failed to save changes. Please check your connection.");
                    return false;
                }
            }

            // --- 3. ADD / DELETE HANDLERS ---

            async function addProcedure(code, description, units) {
                // Get selected diagnoses
                const selectedCheckboxes = document.querySelectorAll('.diagnosis-checkbox:checked');
                const linkedIds = Array.from(selectedCheckboxes).map(cb => cb.value).join(',');

                currentProcedures.push({
                    cpt_code: code,
                    description: description,
                    units: parseInt(units),
                    linked_diagnosis_ids: linkedIds
                });

                renderProcedures();
                const success = await saveAllProcedures();
                
                if (success) {
                    // Clear checkboxes after add
                    selectedCheckboxes.forEach(cb => cb.checked = false);
                    showToast("Procedure added successfully!");
                }
            }

            proceduresTbody.addEventListener('click', async (e) => {
                const btn = e.target.closest('.delete-proc-btn');
                if (btn) {
                    const index = btn.dataset.index;
                    if (index > -1) {
                        currentProcedures.splice(index, 1);
                        renderProcedures();
                        await saveAllProcedures();
                    }
                }
            });

            // --- 4. DATABASE SEARCH LOGIC ---
            const searchInput = document.getElementById('cpt_search');
            const searchResults = document.getElementById('cpt_search_results');
            const selectedCodeInput = document.getElementById('selected_code_val');
            const selectedDescInput = document.getElementById('selected_desc_val');
            let debounceTimer;

            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const term = this.value.trim();

                if (term.length < 2) {
                    searchResults.classList.add('hidden');
                    return;
                }

                debounceTimer = setTimeout(async () => {
                    try {
                        const res = await fetch(`api/search_cpt.php?term=${encodeURIComponent(term)}`);
                        const results = await res.json();

                        if (results.length > 0) {
                            searchResults.innerHTML = results.map(r => `
                        <div class="p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-0 cpt-result-item flex justify-between"
                             data-code="${r.code}" data-desc="${r.description}">
                            <div>
                                <span class="font-bold text-indigo-700">${r.code}</span>
                                <span class="text-sm text-gray-700 ml-2">${r.description.substring(0, 60)}...</span>
                            </div>
                        </div>
                    `).join('');
                            searchResults.classList.remove('hidden');
                        } else {
                            searchResults.innerHTML = '<div class="p-2 text-gray-500 text-sm italic">No matches found.</div>';
                            searchResults.classList.remove('hidden');
                        }
                    } catch (e) { console.error(e); }
                }, 300);
            });

            // Handle Selection from Dropdown
            searchResults.addEventListener('click', function(e) {
                const item = e.target.closest('.cpt-result-item');
                if (item) {
                    const code = item.dataset.code;
                    const desc = item.dataset.desc;

                    searchInput.value = `${code} - ${desc}`;
                    selectedCodeInput.value = code;
                    selectedDescInput.value = desc;

                    searchResults.classList.add('hidden');
                }
            });

            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.add('hidden');
                }
            });

            // --- MANUAL ENTRY TOGGLE LOGIC ---
            const searchModeContainer = document.getElementById('search-mode-container');
            const manualModeContainer = document.getElementById('manual-mode-container');
            const toggleManualBtn = document.getElementById('toggle-manual-btn');
            const cancelManualBtn = document.getElementById('cancel-manual-btn');

            toggleManualBtn.addEventListener('click', () => {
                searchModeContainer.classList.add('hidden');
                manualModeContainer.classList.remove('hidden');
                document.getElementById('manual_code').focus();
            });

            cancelManualBtn.addEventListener('click', () => {
                manualModeContainer.classList.add('hidden');
                searchModeContainer.classList.remove('hidden');
                document.getElementById('manual_code').value = '';
                document.getElementById('manual_desc').value = '';
            });

            // Manual Add Form Submit
            document.getElementById('addProcedureForm').addEventListener('submit', (e) => {
                e.preventDefault();
                const unitsVal = document.getElementById('units').value;

                // Check if we are in manual mode
                if (!manualModeContainer.classList.contains('hidden')) {
                    const mCode = document.getElementById('manual_code').value.trim();
                    const mDesc = document.getElementById('manual_desc').value.trim();

                    if (mCode && mDesc) {
                        addProcedure(mCode, mDesc, unitsVal);
                        // Reset and switch back
                        document.getElementById('manual_code').value = '';
                        document.getElementById('manual_desc').value = '';
                        document.getElementById('units').value = 1;
                        // Optional: Switch back to search?
                        // manualModeContainer.classList.add('hidden');
                        // searchModeContainer.classList.remove('hidden');
                    } else {
                        alert("Please enter both a Code and a Description.");
                    }
                    return;
                }

                // Standard Search Mode Submit
                if (selectedCodeInput.value) {
                    addProcedure(selectedCodeInput.value, selectedDescInput.value, unitsVal);
                    // Reset inputs
                    searchInput.value = '';
                    selectedCodeInput.value = '';
                    selectedDescInput.value = '';
                    document.getElementById('units').value = 1;
                } else if (searchInput.value) {
                    // Fallback manual entry from search box
                    addProcedure(searchInput.value, "Manual Entry", unitsVal);
                    searchInput.value = '';
                    document.getElementById('units').value = 1;
                }
            });

            // Quick Add Listener
            document.body.addEventListener('click', (e) => {
                const btn = e.target.closest('.quick-add-btn');
                if (btn) {
                    e.preventDefault();
                    addProcedure(btn.dataset.code, btn.dataset.desc, btn.dataset.units || 1);
                }
            });

            // --- 5. LOAD SMART SUGGESTIONS ---
            async function loadSmartSuggestions() {
                const panel = document.getElementById('smart-cpt-panel');
                const container = document.getElementById('suggestions-container');

                try {
                    const res = await fetch(`api/get_cpt_suggestions.php?appointment_id=${appointmentId}`);
                    const data = await res.json();

                    container.innerHTML = '';

                    if (data.success && data.suggestions.length > 0) {
                        panel.classList.remove('hidden');

                        data.suggestions.forEach(sugg => {
                            const div = document.createElement('div');
                            div.className = "bg-white p-3 rounded border border-indigo-100 flex justify-between items-center";
                            div.innerHTML = `
                        <div>
                            <div class="flex items-center">
                                <span class="font-bold text-indigo-700 mr-2">${sugg.code}</span>
                                <span class="text-gray-800 text-sm font-medium">${sugg.description}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-1 italic"><i data-lucide="info" class="w-3 h-3 inline mr-1"></i>${sugg.reason}</p>
                        </div>
                        <button class="quick-add-btn bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold py-1.5 px-3 rounded transition"
                                data-code="${sugg.code}" data-desc="${sugg.description}" data-units="${sugg.quantity}">
                            Add (${sugg.quantity})
                        </button>
                    `;
                            container.appendChild(div);
                        });
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }

                } catch (e) {
                    console.error("Failed to load smart suggestions", e);
                }
            }

            // --- GRAFT VISIBILITY LOGIC ---
            function updateGraftSectionVisibility() {
                const container = document.getElementById('graft-details-container');
                const inputsWrapper = document.getElementById('graft-inputs-wrapper');
                const msg = document.getElementById('no-graft-msg');
                const inputs = inputsWrapper.querySelectorAll('input');

                if (!container) return;

                // Check for Graft (Same logic as generateProcedureNote)
                let isGraft = false;
                
                // 1. Check Wound Assessments
                const w = window.procedureData ? window.procedureData.wounds : [];
                if (w && w.length > 0) {
                    const graftWound = w.find(wd => (wd.graft_product_name && wd.graft_product_name.trim() !== '') || wd.graft_expiration_date);
                    if (graftWound) isGraft = true;
                }

                // 2. Check CPT Codes
                if (!isGraft && typeof currentProcedures !== 'undefined') {
                    const hasGraftCPT = currentProcedures.some(p => 
                        p.cpt_code.startsWith('1527') || 
                        p.cpt_code.startsWith('Q4') ||
                        p.description.toLowerCase().includes('skin substitute') ||
                        p.description.toLowerCase().includes('graft')
                    );
                    if (hasGraftCPT) isGraft = true;
                }

                // 3. Check Manual Input (If already has value, keep enabled)
                // Actually, if it's disabled, user can't type. But if they typed before, we shouldn't hide it?
                // Let's stick to the rule: If no graft indicated by Data or CPT, disable it.
                // Exception: If the fields ALREADY have data (e.g. loaded from saved note/DB), we should probably show it.
                // But here we are just checking visibility.
                // Let's check if inputs have values.
                const hasValue = Array.from(inputs).some(input => input.value.trim() !== '');
                if (hasValue) isGraft = true;

                if (isGraft) {
                    // Enable
                    container.classList.remove('opacity-50');
                    inputsWrapper.classList.remove('pointer-events-none');
                    msg.classList.add('hidden');
                    inputs.forEach(i => i.disabled = false);
                } else {
                    // Disable
                    container.classList.add('opacity-50');
                    inputsWrapper.classList.add('pointer-events-none');
                    msg.classList.remove('hidden');
                    inputs.forEach(i => i.disabled = true);
                }
            }

            // --- PROCEDURE NOTE LOGIC ---
            const noteTextarea = document.getElementById('procedure_note');
            const generateBtn = document.getElementById('generate-note-btn');
            const saveNoteBtn = document.getElementById('save-note-btn');
            const noteSaveStatus = document.getElementById('note-save-status');

            // Inject Custom Styles for Placeholders (since Quill strips Tailwind classes)
            const style = document.createElement('style');
            style.innerHTML = `
                .clickable-placeholder {
                    background-color: #eff6ff !important; /* bg-blue-50 */
                    color: #2563eb !important; /* text-blue-600 */
                    padding: 0 4px !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    border: 1px dashed #93c5fd !important; /* border-blue-300 */
                    display: inline-block !important; /* Ensure it behaves like a block for padding */
                }
                .clickable-placeholder:hover {
                    background-color: #dbeafe !important; /* hover:bg-blue-100 */
                }
                .clickable-placeholder.filled {
                    background-color: #fefce8 !important; /* bg-yellow-50 */
                    color: #111827 !important; /* text-gray-900 */
                    border-color: transparent !important;
                    border-style: solid !important;
                }
                .clickable-placeholder.filled:hover {
                    background-color: #fef9c3 !important; /* hover:bg-yellow-100 */
                }
            `;
            document.head.appendChild(style);

            // Register Custom Blot for Placeholders
            const Inline = Quill.import('blots/inline');
            class PlaceholderBlot extends Inline {
                static create(value) {
                    let node = super.create();
                    node.setAttribute('class', 'clickable-placeholder');
                    if (typeof value === 'string') {
                         node.setAttribute('data-default', value);
                    }
                    return node;
                }
                
                static formats(node) {
                    return node.getAttribute('data-default') || true;
                }
            }
            PlaceholderBlot.blotName = 'placeholder';
            PlaceholderBlot.tagName = 'span';
            PlaceholderBlot.className = 'clickable-placeholder';
            Quill.register('formats/placeholder', PlaceholderBlot);

            // Initialize Quill
            var quill = new Quill('#editor-container', {
                theme: 'snow',
                placeholder: 'Enter procedure details here...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'header': [1, 2, 3, false] }],
                        ['clean']
                    ]
                }
            });

            // Remove the clipboard matcher as we will use innerHTML for reliability
            // quill.clipboard.addMatcher... REMOVED

            // Sync Quill to Hidden Input
            quill.on('text-change', function() {
                noteTextarea.value = quill.root.innerHTML;
            });

            // Pre-fill Medications if available
            function renderMedsChecklist() {
                const container = document.getElementById('meds-checklist-container');
                if (!container) return;

                const meds = window.procedureData ? window.procedureData.medications : [];
                
                if (meds && meds.length > 0) {
                    container.innerHTML = meds.map(m => {
                        // Format: Name Dosage Freq (e.g. Lisinopril 10mg Daily)
                        const label = `${m.drug_name} ${m.dosage} ${m.frequency}`.trim();
                        return `
                            <label class="flex items-start mb-1 last:mb-0 hover:bg-gray-100 rounded p-0.5 transition cursor-pointer">
                                <input type="checkbox" name="meds_recon_check" value="${label}" class="mt-0.5 text-indigo-600 focus:ring-indigo-500 rounded" checked>
                                <span class="ml-2 text-xs text-gray-700 leading-snug select-none">${label}</span>
                            </label>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<span class="text-xs text-gray-500 italic">No active medications on file.</span>';
                }
                
                // Add listeners to new checkboxes for auto-update
                container.querySelectorAll('input').forEach(cb => {
                    cb.addEventListener('change', generateProcedureNote);
                    cb.addEventListener('change', saveInputsToLocal);
                });
            }

            // Render Wound Debridement Inputs
            function renderWoundDebridementInputs() {
                const container = document.getElementById('wound-debridement-list');
                if (!container) return;

                const wounds = window.procedureData ? window.procedureData.wounds : [];
                
                if (wounds && wounds.length > 0) {
                    container.innerHTML = wounds.map((w, idx) => {
                        const woundId = w.wound_id || idx;
                        const location = w.location;
                        // Default values from assessment if available
                        const defaultType = w.debridement_type || 'None';
                        
                        return `
                            <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm wound-deb-item hover:border-indigo-300 transition-colors" data-wound-id="${woundId}">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="font-bold text-sm text-indigo-800 flex items-center">
                                        <i data-lucide="target" class="w-4 h-4 mr-2 text-indigo-500"></i>
                                        Wound: ${location}
                                    </h5>
                                    <span class="text-xs font-medium px-2 py-1 bg-gray-100 text-gray-600 rounded-full border border-gray-200">
                                        ${defaultType}
                                    </span>
                                </div>
                                
                                <div class="bg-gray-50 rounded-md p-3 border border-gray-100">
                                    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Instruments Used</label>
                                    <div class="flex flex-wrap gap-2">
                                        <label class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all select-none">
                                            <input type="checkbox" class="deb-inst-check h-3.5 w-3.5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="curette" checked> 
                                            <span class="ml-2 text-xs font-medium text-gray-700">Curette</span>
                                        </label>
                                        <label class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all select-none">
                                            <input type="checkbox" class="deb-inst-check h-3.5 w-3.5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="forceps" checked> 
                                            <span class="ml-2 text-xs font-medium text-gray-700">Forceps</span>
                                        </label>
                                        <label class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all select-none">
                                            <input type="checkbox" class="deb-inst-check h-3.5 w-3.5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="scalpel"> 
                                            <span class="ml-2 text-xs font-medium text-gray-700">Scalpel</span>
                                        </label>
                                        <label class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all select-none">
                                            <input type="checkbox" class="deb-inst-check h-3.5 w-3.5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="scissors"> 
                                            <span class="ml-2 text-xs font-medium text-gray-700">Scissors</span>
                                        </label>
                                        <label class="inline-flex items-center px-3 py-1.5 rounded-md bg-white border border-gray-200 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition-all select-none">
                                            <input type="checkbox" class="deb-inst-check h-3.5 w-3.5 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" value="gauze"> 
                                            <span class="ml-2 text-xs font-medium text-gray-700">Gauze</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    
                    // Add listeners
                    container.querySelectorAll('input').forEach(input => {
                        input.addEventListener('change', generateProcedureNote);
                        input.addEventListener('change', saveInputsToLocal);
                    });
                    
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    
                } else {
                    container.innerHTML = '<div class="text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-300"><span class="text-xs text-gray-500 italic">No wounds recorded for this visit.</span></div>';
                }
            }
            
            // Call immediately
            renderMedsChecklist();
            renderWoundDebridementInputs();

            // Load existing note
            if (window.procedureData && window.procedureData.existingNote) {
                // Check if it looks like HTML (starts with <) or plain text
                const existing = window.procedureData.existingNote;
                if (existing.trim().startsWith('<')) {
                    quill.root.innerHTML = existing;
                } else {
                    quill.setText(existing);
                }
                noteTextarea.value = existing;
            }

            // Helper to create clickable placeholders
            function createPlaceholder(text) {
                // Use only the class recognized by the Blot. Styles are handled by injected CSS.
                return `<span class="clickable-placeholder" data-default="${text}">${text}</span>`;
            }

            // Function to Generate Note
            function generateProcedureNote() {
                const p = window.procedureData.patient;
                const w = window.procedureData.wounds;
                
                // Gather Checklist Data
                const anesthesia = document.querySelector('input[name="anesthesia"]:checked')?.value || 'none';
                const consent = document.getElementById('chk_consent').checked;
                const timeout = document.getElementById('chk_timeout').checked;
                const risks = document.getElementById('chk_risks').checked;
                const antibiotics = document.getElementById('txt_antibiotics').value.trim() || 'none';
                
                // Gather Meds
                const checkedMeds = Array.from(document.querySelectorAll('input[name="meds_recon_check"]:checked'))
                                         .map(cb => cb.value);
                const manualMeds = document.getElementById('txt_meds_manual').value.trim();
                
                let medsRecon = checkedMeds.join(', ');
                if (manualMeds) {
                    if (medsRecon) medsRecon += ', ' + manualMeds;
                    else medsRecon = manualMeds;
                }
                if (!medsRecon) medsRecon = 'reviewed';
                
                // Gather Impeding Factors
                const impedingFactors = Array.from(document.querySelectorAll('input[name="impeding_factors"]:checked'))
                                             .map(cb => cb.value)
                                             .join(', ');
                const factorsText = impedingFactors.length > 0 ? impedingFactors : createPlaceholder('[Enter Factors e.g. Diabetes, PVD, Mobility]');
                
                const prodNameInput = document.getElementById('txt_product_name').value.trim();
                const prodSizeInput = document.getElementById('txt_product_size').value.trim();
                const prodSerialInput = document.getElementById('txt_product_serial').value.trim();

                // Check for Graft in Assessments or Input
                let isGraft = (prodNameInput !== '');
                let graftData = {};
                
                // 1. Check Wound Assessments
                if (w && w.length > 0) {
                    // Find first wound with graft info
                    const graftWound = w.find(wd => (wd.graft_product_name && wd.graft_product_name.trim() !== '') || wd.graft_expiration_date);
                    if (graftWound) {
                        if (!isGraft) isGraft = true;
                        
                        // Calculate total size used (used + discarded)
                        let sizeVal = (parseFloat(graftWound.graft_sqcm_used || 0) + parseFloat(graftWound.graft_sqcm_discarded || 0)).toFixed(2);
                        if (sizeVal === "0.00") sizeVal = "";

                        graftData = {
                            name: graftWound.graft_product_name || '',
                            serial: graftWound.graft_serial_number || '',
                            expiry: graftWound.graft_expiration_date || graftWound.graft_expiry_date || '',
                            size: sizeVal,
                            appNum: graftWound.graft_application_number || '',
                            qcode: graftWound.graft_q_code || '',
                            used: graftWound.graft_sqcm_used || '0.00',
                            discarded: graftWound.graft_sqcm_discarded || '0.00'
                        };
                    }
                }

                // 2. Check CPT Codes (Smart Check)
                if (!isGraft && typeof currentProcedures !== 'undefined') {
                    // Check for CPT codes starting with 1527 (Application) or Q (Product)
                    const hasGraftCPT = currentProcedures.some(p => 
                        p.cpt_code.startsWith('1527') || 
                        p.cpt_code.startsWith('Q4') ||
                        p.description.toLowerCase().includes('skin substitute') ||
                        p.description.toLowerCase().includes('graft')
                    );
                    if (hasGraftCPT) {
                        isGraft = true;
                    }
                }

                // Auto-fill inputs if empty and we have data from assessment
                if (graftData.name && document.getElementById('txt_product_name').value === '') {
                        document.getElementById('txt_product_name').value = graftData.name;
                }
                if (graftData.size && document.getElementById('txt_product_size').value === '') {
                        document.getElementById('txt_product_size').value = graftData.size;
                }
                if (graftData.serial && document.getElementById('txt_product_serial').value === '') {
                        document.getElementById('txt_product_serial').value = graftData.serial;
                }
                
                // Re-read inputs in case they were just updated
                const finalProdName = document.getElementById('txt_product_name').value.trim() || graftData.name || createPlaceholder('[Product Name]');
                const finalProdSize = document.getElementById('txt_product_size').value.trim() || graftData.size || createPlaceholder('[Size]');
                const finalProdSerial = document.getElementById('txt_product_serial').value.trim() || graftData.serial || createPlaceholder('[Serial]');
                const finalProdExpiry = graftData.expiry || createPlaceholder('[Date]');
                const finalQCode = graftData.qcode || createPlaceholder('Q4160');
                
                // Helper for Ordinal
                const getOrdinal = (n) => {
                    if (!n) return '';
                    const s = ["th", "st", "nd", "rd"];
                    const v = n % 100;
                    return n + (s[(v - 20) % 10] || s[v] || s[0]);
                };
                const appNumStr = graftData.appNum ? getOrdinal(graftData.appNum) : createPlaceholder('11111th');

                let text = "";

                // --- SCREENINGS SECTION ---
                // Only add if at least one screening has data
                const bradenScore = document.getElementById('braden-total-score').textContent;
                const bradenRisk = document.getElementById('braden-risk-level').textContent;
                const mnaScore = document.getElementById('mna_score').value;
                const mnaRisk = document.getElementById('mna-risk').textContent;
                const nortonScore = document.getElementById('norton_score').value;
                const nortonRisk = document.getElementById('norton-risk').textContent;
                const batesStatus = document.getElementById('bates_jensen_status').value;

                let hasScreening = (bradenScore !== '--') || (mnaScore !== '') || (nortonScore !== '') || (batesStatus !== '');

                if (hasScreening) {
                    text += `<p><strong>SCREENINGS</strong></p>`;
                    
                    if (bradenScore !== '--') {
                        text += `<p><strong>Braden Rating:</strong> ${bradenScore} (${bradenRisk}). Reinforced importance of skin protection and hydration.</p>`;
                    }
                    if (mnaScore !== '') {
                        let mnaMsg = "Encouraged balanced diet.";
                        if (mnaRisk.includes("At Risk") || mnaRisk.includes("Malnourished")) {
                            mnaMsg = "Encouraged balanced diet with attention to diabetic nutritional needs.";
                        }
                        text += `<p><strong>MNA Rating:</strong> ${mnaScore} (${mnaRisk}). ${mnaMsg}</p>`;
                    }
                    if (batesStatus !== '') {
                        text += `<p><strong>Bates-Jensen Score:</strong> ${batesStatus}</p>`;
                    }
                    if (nortonScore !== '') {
                        text += `<p><strong>Norton Rating:</strong> ${nortonScore} (${nortonRisk}). Continued emphasis on mobility and pressure relief.</p>`;
                    }
                    text += `<p><br></p>`; // Spacer
                }

                if (isGraft) {
                    // --- GRAFT TEMPLATE (Updated from Image) ---
                    text += `<p><strong>Procedure</strong></p>`;
                    
                    // Location & Indication
                    text += `<p><strong>Location of care:</strong> outpatient wound clinic. <strong>Indication:</strong> re-application of ${finalProdName} skin substitute for a chronic wound that remains open despite over 30 days of comprehensive care with off-loading, moisture control, enzymatic and sharp debridement, and appropriate dressings, yet shows interval response with increased healthy granulation and reduced devitalized tissue.</p>`;
                    
                    // Meds
                    text += `<p><strong>Medication reconciliation today:</strong> ${medsRecon}. Underlying conditions and control: ${createPlaceholder('[Enter Conditions]')}.</p>`;
                    
                    // Infection Screen
                    text += `<p><strong>Infection screen at time of grafting:</strong> no clinical cellulitis present; no purulence, erythema, or induration; osteomyelitis absent by clinical exam with no exposed bone or hard palpable base.</p>`;
                    
                    // Consent
                    text += `<p><strong>Consent and pre-procedure safety:</strong> `;
                    if (risks) text += "the patient was counseled on risks and benefits including hypersensitivity, disease transmission, infection, graft non-adherence or failure, delayed wound progress, bleeding, pain, and scar or cosmetic variation. All questions were answered and written consent obtained. ";
                    if (timeout) text += "A time-out confirmed correct patient, procedure, and site with equipment availability and proper positioning.";
                    text += "</p>";
                    
                    // Anesthesia
                    text += `<p><strong>Anesthesia and preparation:</strong> ${anesthesia} achieved adequate local anesthesia. The wound was prepped and draped in a clean field under sterile technique.</p>`;

                    // Debridement Loop
                    let totalWoundArea = 0;
                    if (w && w.length > 0) {
                        w.forEach((wound, idx) => {
                            const area = (wound.length_cm * wound.width_cm).toFixed(2);
                            totalWoundArea += (wound.length_cm * wound.width_cm);
                            
                            // Get dynamic inputs for this wound
                            const woundId = wound.wound_id || idx;
                            const debItem = document.querySelector(`.wound-deb-item[data-wound-id="${woundId}"]`);
                            let debType = wound.debridement_type ? wound.debridement_type.toLowerCase() : 'sharp excisional';
                            let instruments = 'a sterile #4 curette and forceps'; // default for graft

                            if (debItem) {
                                const typeSelect = debItem.querySelector('.deb-type-select');
                                if (typeSelect) debType = typeSelect.value;
                                
                                const instChecks = debItem.querySelectorAll('.deb-inst-check:checked');
                                if (instChecks.length > 0) {
                                    const arr = Array.from(instChecks).map(c => c.value);
                                    if (arr.length === 1) instruments = arr[0];
                                    else if (arr.length === 2) instruments = arr.join(' and ');
                                    else {
                                        const last = arr.pop();
                                        instruments = arr.join(', ') + ', and ' + last;
                                    }
                                }
                            }
                            
                            if (debType === 'none') return;

                            text += `<p><strong>Debridement (Wound ${wound.location}):</strong> ${debType} debridement of nonviable tissue and adherent fibrin was performed using ${instruments} until pinpoint bleeding indicated a viable base. <strong>Final depth reached:</strong> subcutaneous tissue only; no exposure of tendon, muscle, joint capsule, or bone. Hemostasis was minimal and achieved with gentle pressure using sterile gauze. <strong>Pre-debridement dimensions:</strong> length ${wound.length_cm} cm by width ${wound.width_cm} cm by depth ${wound.depth_cm} cm, pre-area equals ${wound.length_cm} × ${wound.width_cm} equals ${area} cm². Post-debridement dimensions: length ${wound.length_cm} cm by width ${wound.width_cm} cm by depth ${wound.depth_cm} cm, post-area equals ${wound.length_cm} × ${wound.width_cm} equals ${area} cm². <strong>Extent debrided:</strong> 100 percent of the wound bed, debrided zone equals ${area} cm². The wound and periwound were irrigated with sterile normal saline and the periwound protected with liquid film barrier.</p>`;
                        });
                    } else {
                        text += `<p><strong>Debridement:</strong> ${createPlaceholder('[Enter Debridement Details]')}</p>`;
                    }

                    // Application
                    text += `<p><strong>${finalProdName} skin substitute application:</strong> the ${finalProdName} skin substitute was prepared per manufacturer’s instructions, transferred aseptically, trimmed to conform to the wound contours, and smoothed to eliminate air pockets to ensure intimate contact with the uniformly red granular base. Fixation: secured with non-adherent interface (Adaptic), overlaid with silver alginate for antimicrobial coverage, then covered with bordered foam to maintain a moist environment and protect from shear.</p>`;
                    
                    // Product Identifiers
                    const totalAreaStr = totalWoundArea > 0 ? totalWoundArea.toFixed(2) : createPlaceholder('[Wound Area]');
                    text += `<p><strong>Product identifiers:</strong> ${finalProdName} wrap <strong>size:</strong> ${finalProdSize} cm²; <strong>expiration date:</strong> ${finalProdExpiry}; <strong>serial number:</strong> ${finalProdSerial}. <strong>Amount used and justification:</strong> the entire ${finalProdSize} cm² skin substitute was required to achieve full coverage of the ${totalAreaStr} cm² wound bed with clinically appropriate overlap for adherence and edge apposition in a mobile region; this size selection minimizes seam lines and lift-off risk. Material accounting: ${graftData.used || finalProdSize} cm² applied; ${graftData.discarded || '0.00'} cm² discarded; no waste generated.</p>`;
                    
                    // Cultures
                    text += `<p><strong>Cultures or specimens:</strong> none indicated based on clean granular base without purulence, malodor, or systemic signs.</p>`;
                    
                    // Tolerance
                    text += `<p><strong>Tolerance and complications:</strong> the patient tolerated the procedure without pain or complication. Post-procedure appearance: graft well conformed with moist, pink, viable bed and secure fixation. Post-procedure instructions were provided regarding dressing protection, strict off-loading, moisture control, nutrition, adherence to medications, and early reporting of infection signs including increasing drainage, redness, warmth, swelling, odor, fever, or unexpected pain.</p>`;

                    // Footer Summary
                    text += `<p><br></p>`;
                    text += `<p><strong>${appNumStr} application of ${finalProdName} Wrap</strong></p>`;
                    text += `<p><strong>QCode:</strong> ${finalQCode}</p>`;
                    text += `<p><strong>Size:</strong> ${finalProdSize} cm²</p>`;
                    text += `<p><strong>Serial #:</strong> ${finalProdSerial}</p>`;
                    text += `<p><strong>Exp:</strong> ${finalProdExpiry}</p>`;

                } else {
                    // Check if ANY wound had debridement
                    const hasDebridement = w && w.some(wound => wound.debridement_performed === 'Yes');

                    if (hasDebridement) {
                        // --- DEBRIDEMENT TEMPLATE ---
                        text += `<p><strong>PROCEDURE: Debridement</strong></p>`;
                        text += `<p><strong>Medication reconciliation today:</strong> ${medsRecon}.</p>`;
                        text += `<p>Today's procedure is a debridement of skin or debris. I decided to perform debridement today because, based on the patient's current status, there is an expectation that debridement will improve healing potential, reduce or control tissue infection, and prepare the tissue for surgical management.</p>`;
                        
                        text += `<p>I have informed the patient of the risks and benefits of this procedure and they have had the opportunity to ask questions. Appropriate consent has been obtained. Debridement is required to excise a specific, targeted area of devitalized or necrotic tissue along the margin of viable tissue by sharp dissection. Debridement is needed as part of the plan to control heavy wound colonization. I have ensured that this wound has been adequately off-loaded.</p>`;
                        
                        if (timeout) {
                            text += `<p>Wound Care Team called a "Time Out" to verify correct patient, procedure and site. Correct patient verified using two patient identifiers. Correct equipment verified. Patient positioned appropriately.</p>`;
                        }

                        text += `<p>After consent and timeout, topical anesthetic (${anesthesia}) was applied, and the areas were cleansed with wound cleanser and gently patted dry.</p>`;
                        
                        if (w && w.length > 0) {
                            w.forEach((wound, idx) => {
                                // Get dynamic inputs for this wound
                                const woundId = wound.wound_id || idx;
                                const debItem = document.querySelector(`.wound-deb-item[data-wound-id="${woundId}"]`);
                                let debType = wound.debridement_type ? wound.debridement_type.toLowerCase() : 'sharp selective';
                                let instruments = 'curette and forceps';

                                if (debItem) {
                                    const instChecks = debItem.querySelectorAll('.deb-inst-check:checked');
                                    if (instChecks.length > 0) {
                                        const arr = Array.from(instChecks).map(c => c.value);
                                        if (arr.length === 1) instruments = arr[0];
                                        else if (arr.length === 2) instruments = arr.join(' and ');
                                        else {
                                            const last = arr.pop();
                                            instruments = arr.join(', ') + ', and ' + last;
                                        }
                                    }
                                }

                                // Only include if debridement is performed (either from assessment or UI override)
                                // If UI says 'none', skip. If UI says something else, include.
                                if (debType === 'none') return;

                                // If UI is not present (e.g. first load before render?), fallback to assessment
                                if (!debItem && wound.debridement_performed !== 'Yes') return;

                                const area = (wound.length_cm * wound.width_cm).toFixed(2);
                                
                                text += `<p><strong>Wound ${wound.location}:</strong> ${debType} debridement was performed using ${instruments} to remove adherent yellow slough, fibrin, and superficial necrotic tissue from the wound beds down to viable, punctate bleeding granulation tissue.</p>`;
                                
                                // Calculate post-debridement (assuming same for now, or user edits)
                                text += `<p>For the ${wound.location} wound, pre-debridement measurements were ${wound.length_cm} cm × ${wound.width_cm} cm × ${wound.depth_cm} cm (Area = ${area} cm²). Post-debridement measurements remained similar, with approximately ${createPlaceholder('[Enter %]')} of the surface area selectively debrided.</p>`;
                            });
                        }
                        
                        text += `<p>Hemostasis was achieved with gentle pressure only; estimated blood loss was minimal. The patient tolerated the procedure well without distress or complications.</p>`;
                        
                        text += `<p>Response/Progress to treatment: After debridement, the wound shows improved visualization of viable granulation tissue with reduction in adherent slough and necrotic material. There is no evidence of acute infection and drainage is controlled.</p>`;
                        
                        text += `<p>Factors impeding healing: ${factorsText}.</p>`;
                    } else {
                        // --- STANDARD WOUND CARE TEMPLATE (No Debridement) ---
                        text += `<p><strong>PROCEDURE: Wound Care & Assessment</strong></p>`;
                        text += `<p><strong>Medication reconciliation today:</strong> ${medsRecon}.</p>`;
                        text += `<p><strong>Indication:</strong> Routine wound care and assessment for chronic wound management.</p>`;
                        
                        text += `<p><strong>Procedure:</strong> The patient was seen and evaluated. The wound(s) were inspected and cleansed with wound cleanser. No debridement was indicated or performed at this visit as the wound bed(s) appeared clean with no significant non-viable tissue requiring removal.</p>`;
                        
                        if (w && w.length > 0) {
                            w.forEach((wound, idx) => {
                                const area = (wound.length_cm * wound.width_cm).toFixed(2);
                                text += `<p><strong>Wound ${wound.location}:</strong> Measured ${wound.length_cm} cm × ${wound.width_cm} cm × ${wound.depth_cm} cm (Area = ${area} cm²). The wound bed is ${createPlaceholder('[Enter %]')} granular. Dressing was changed according to the treatment plan.</p>`;
                            });
                        }
                        
                        text += `<p><strong>Tolerance:</strong> The patient tolerated the dressing change and assessment well without pain or complication.</p>`;
                        text += `<p><strong>Plan:</strong> Continue current treatment plan. Off-loading and moisture control discussed.</p>`;
                    }
                }
                
                // Update Quill
                // Use innerHTML directly to ensure custom classes/blots are preserved exactly as generated
                quill.root.innerHTML = text;
                noteTextarea.value = text;
                
                // Re-attach listeners to new placeholders
                attachPlaceholderListeners();
                
                // Optional: Auto-save after generation? 
                // saveProcedureNote(); 
                // User might want to review first.
            }

            // --- Custom Floating Prompt Logic ---
            function getCustomPrompt() {
                let modal = document.getElementById('custom-floating-prompt');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'custom-floating-prompt';
                    modal.className = 'fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden transition-opacity duration-200';
                    modal.innerHTML = `
                        <div class="bg-white rounded-lg shadow-xl w-96 p-6 transform transition-all scale-100">
                            <h3 id="custom-prompt-title" class="text-lg font-semibold text-gray-900 mb-4">Enter Value</h3>
                            <input type="text" id="custom-prompt-input" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4" autocomplete="off">
                            <div class="flex justify-end space-x-3">
                                <button id="custom-prompt-cancel" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">Cancel</button>
                                <button id="custom-prompt-ok" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">OK</button>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    
                    // Close on Escape
                    document.addEventListener('keydown', (e) => {
                        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                            document.getElementById('custom-prompt-cancel').click();
                        }
                    });
                }
                return modal;
            }

            function showCustomPrompt(title, defaultValue, callback) {
                const modal = getCustomPrompt();
                const titleEl = document.getElementById('custom-prompt-title');
                const inputEl = document.getElementById('custom-prompt-input');
                const okBtn = document.getElementById('custom-prompt-ok');
                const cancelBtn = document.getElementById('custom-prompt-cancel');

                titleEl.textContent = title;
                inputEl.value = defaultValue;
                
                modal.classList.remove('hidden');
                inputEl.focus();
                inputEl.select();

                const close = () => {
                    modal.classList.add('hidden');
                    cleanup();
                };

                const handleOk = () => {
                    const val = inputEl.value;
                    close();
                    callback(val);
                };

                const handleCancel = () => {
                    close();
                    callback(null);
                };

                const handleKey = (e) => {
                    if (e.key === 'Enter') handleOk();
                };

                const cleanup = () => {
                    okBtn.removeEventListener('click', handleOk);
                    cancelBtn.removeEventListener('click', handleCancel);
                    inputEl.removeEventListener('keydown', handleKey);
                };

                okBtn.addEventListener('click', handleOk);
                cancelBtn.addEventListener('click', handleCancel);
                inputEl.addEventListener('keydown', handleKey);
            }

            // Attach listeners to placeholders (using delegation)
            function attachPlaceholderListeners() {
                const editor = document.querySelector('#editor-container .ql-editor');
                if (!editor) return;

                // Check if already attached to avoid duplicates
                if (editor.getAttribute('data-listeners-attached') === 'true') return;
                
                editor.addEventListener('click', function(e) {
                    const target = e.target;
                    // Check if clicked element is our placeholder
                    if (target.classList.contains('clickable-placeholder')) {
                        const defaultText = target.getAttribute('data-default') || target.textContent;
                        const currentText = target.textContent === defaultText ? '' : target.textContent;
                        
                        showCustomPrompt(`Enter value for ${defaultText}`, currentText, (newVal) => {
                            if (newVal !== null && newVal.trim() !== '') {
                                let finalVal = newVal.trim();
                                // Auto-append % if the placeholder expects it (e.g. [Enter %])
                                if (defaultText.includes('%') && !finalVal.endsWith('%')) {
                                    finalVal += '%';
                                }
                                
                                target.textContent = finalVal;
                                target.classList.add('filled');
                            }
                        });
                    }
                });
                
                editor.setAttribute('data-listeners-attached', 'true');
            }
            
            // Ensure listeners are attached on load
            attachPlaceholderListeners();

            // Attach Listeners for Auto-Update
            const checklistInputs = [
                ...document.querySelectorAll('input[name="anesthesia"]'),
                ...document.querySelectorAll('input[name="impeding_factors"]'),
                ...document.querySelectorAll('input[name="meds_recon_check"]'),
                document.getElementById('chk_consent'),
                document.getElementById('chk_timeout'),
                document.getElementById('chk_risks'),
                document.getElementById('txt_antibiotics'),
                document.getElementById('txt_meds_manual'),
                document.getElementById('txt_product_name'),
                document.getElementById('txt_product_size'),
                document.getElementById('txt_product_serial')
            ];

            checklistInputs.forEach(input => {
                if(input) {
                    // Use 'input' for text fields for real-time updates, 'change' for others
                    const eventType = (input.type === 'text') ? 'input' : 'change';
                    input.addEventListener(eventType, generateProcedureNote);
                }
            });

            // --- LOCAL STORAGE PERSISTENCE ---
            const STORAGE_KEY = `procedure_inputs_${window.procedureData.appointmentId}`;

            function saveInputsToLocal() {
                const state = {
                    anesthesia: document.querySelector('input[name="anesthesia"]:checked')?.value,
                    impeding_factors: Array.from(document.querySelectorAll('input[name="impeding_factors"]:checked')).map(cb => cb.value),
                    meds_recon_check: Array.from(document.querySelectorAll('input[name="meds_recon_check"]:checked')).map(cb => cb.value),
                    chk_consent: document.getElementById('chk_consent').checked,
                    chk_timeout: document.getElementById('chk_timeout').checked,
                    chk_risks: document.getElementById('chk_risks').checked,
                    txt_antibiotics: document.getElementById('txt_antibiotics').value,
                    txt_meds_manual: document.getElementById('txt_meds_manual').value,
                    txt_product_name: document.getElementById('txt_product_name').value,
                    txt_product_size: document.getElementById('txt_product_size').value,
                    txt_product_serial: document.getElementById('txt_product_serial').value,
                    // Screenings
                    mna_score: document.getElementById('mna_score')?.value,
                    norton_score: document.getElementById('norton_score')?.value,
                    bates_jensen_status: document.getElementById('bates_jensen_status')?.value,
                    // Braden Scale
                    braden_sensory: document.getElementById('braden_sensory')?.value,
                    braden_moisture: document.getElementById('braden_moisture')?.value,
                    braden_activity: document.getElementById('braden_activity')?.value,
                    braden_mobility: document.getElementById('braden_mobility')?.value,
                    braden_nutrition: document.getElementById('braden_nutrition')?.value,
                    braden_friction: document.getElementById('braden_friction')?.value,
                    // Wound Debridement
                    wound_debridement: Array.from(document.querySelectorAll('.wound-deb-item')).map(item => ({
                        woundId: item.dataset.woundId,
                        instruments: Array.from(item.querySelectorAll('.deb-inst-check:checked')).map(cb => cb.value)
                    }))
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            }

            // Parse inputs from existing note (Fallback for cross-device or cleared cache)
            function parseInputsFromNote() {
                const note = window.procedureData.existingNote;
                if (!note) return {};

                const extracted = {};

                // MNA
                const mnaMatch = note.match(/MNA Rating:<\/strong>\s*(\d+)/);
                if (mnaMatch && mnaMatch[1]) extracted.mna_score = mnaMatch[1];

                // Norton
                const nortonMatch = note.match(/Norton Rating:<\/strong>\s*(\d+)/);
                if (nortonMatch && nortonMatch[1]) extracted.norton_score = nortonMatch[1];

                // Bates-Jensen
                // Handle potential HTML entities or variations
                const batesMatch = note.match(/Bates-Jensen Score:<\/strong>\s*([^<]+)/);
                if (batesMatch && batesMatch[1]) extracted.bates_jensen_status = batesMatch[1].trim();

                // Braden Total
                const bradenMatch = note.match(/Braden Rating:<\/strong>\s*(\d+)/);
                if (bradenMatch && bradenMatch[1]) {
                    const score = parseInt(bradenMatch[1]);
                    // If score is 23 (Max), we can infer all sub-scores are max
                    if (score === 23) {
                        extracted.braden_sensory = '4';
                        extracted.braden_moisture = '4';
                        extracted.braden_activity = '4';
                        extracted.braden_mobility = '4';
                        extracted.braden_nutrition = '4';
                        extracted.braden_friction = '3';
                    }
                }

                // --- GRAFT DETAILS ---
                // Product Name
                // Matches: "Product identifiers: Tri-Membrane wrap" or "Product identifiers: Tri-Membrane size"
                const prodMatch = note.match(/Product identifiers:(?:<\/strong>)?\s*(.*?)\s+(?:wrap|size|<strong>size)/i);
                if (prodMatch && prodMatch[1]) {
                    extracted.txt_product_name = prodMatch[1].replace(/<[^>]+>/g, '').trim();
                }

                // Size
                // Matches: "equals 6.00 cm²" (from complex string) OR "size: 6.00 cm²" (from simple string)
                // We prioritize 'equals' if present as it likely follows dimensions
                const sizeMatch = note.match(/(?:equals|size:)(?:<\/strong>)?\s*([\d\.]+)\s*cm²/i);
                if (sizeMatch && sizeMatch[1]) extracted.txt_product_size = sizeMatch[1].trim();

                // Serial
                // Matches: "serial number: BLS..."
                const serialMatch = note.match(/serial number:(?:<\/strong>)?\s*(.*?)\./i);
                if (serialMatch && serialMatch[1]) {
                    extracted.txt_product_serial = serialMatch[1].replace(/<[^>]+>/g, '').trim();
                }

                return extracted;
            }

            function restoreInputsFromLocal() {
                const saved = localStorage.getItem(STORAGE_KEY);
                let localState = {};
                if (saved) {
                    try { localState = JSON.parse(saved); } catch(e) { console.error(e); }
                }

                const noteState = parseInputsFromNote();
                
                // Helper to get value: Local > Note > Default
                const getVal = (key) => {
                    if (localState[key] !== undefined && localState[key] !== null && localState[key] !== "") {
                        return localState[key];
                    }
                    return noteState[key];
                };

                try {
                    // Restore Anesthesia
                    const anesthesiaVal = getVal('anesthesia');
                    if (anesthesiaVal) {
                        const radio = document.querySelector(`input[name="anesthesia"][value="${anesthesiaVal}"]`);
                        if (radio) radio.checked = true;
                    }

                    // Restore Impeding Factors (Merge arrays if needed, but usually local is authoritative if present)
                    // For arrays, if local exists use it, else empty. Note parsing for factors is hard, skipping for now.
                    if (localState.impeding_factors) {
                        document.querySelectorAll('input[name="impeding_factors"]').forEach(cb => {
                            cb.checked = localState.impeding_factors.includes(cb.value);
                        });
                    }

                    // Restore Meds
                    if (localState.meds_recon_check) {
                        document.querySelectorAll('input[name="meds_recon_check"]').forEach(cb => {
                            cb.checked = localState.meds_recon_check.includes(cb.value);
                        });
                    }

                    // Restore Checkboxes
                    if (localState.chk_consent !== undefined) document.getElementById('chk_consent').checked = localState.chk_consent;
                    if (localState.chk_timeout !== undefined) document.getElementById('chk_timeout').checked = localState.chk_timeout;
                    if (localState.chk_risks !== undefined) document.getElementById('chk_risks').checked = localState.chk_risks;

                    // Restore Text Inputs
                    const txtFields = ['txt_antibiotics', 'txt_meds_manual', 'txt_product_name', 'txt_product_size', 'txt_product_serial'];
                    txtFields.forEach(id => {
                        const val = getVal(id);
                        if (val !== undefined) document.getElementById(id).value = val;
                    });

                    // Restore Screenings
                    const mna = getVal('mna_score');
                    if (mna !== undefined && document.getElementById('mna_score')) document.getElementById('mna_score').value = mna;
                    
                    const norton = getVal('norton_score');
                    if (norton !== undefined && document.getElementById('norton_score')) document.getElementById('norton_score').value = norton;
                    
                    const bates = getVal('bates_jensen_status');
                    if (bates !== undefined && document.getElementById('bates_jensen_status')) document.getElementById('bates_jensen_status').value = bates;

                    // Restore Braden Scale
                    const bradenFields = ['braden_sensory', 'braden_moisture', 'braden_activity', 'braden_mobility', 'braden_nutrition', 'braden_friction'];
                    bradenFields.forEach(id => {
                        const val = getVal(id);
                        if (val !== undefined && document.getElementById(id)) document.getElementById(id).value = val;
                    });

                    // Recalculate Braden Score
                    if (typeof calculateBradenScore === 'function') {
                        calculateBradenScore();
                    }

                    // Restore Wound Debridement (Only from local for now as it's complex structure)
                    if (localState.wound_debridement) {
                        localState.wound_debridement.forEach(wd => {
                            const item = document.querySelector(`.wound-deb-item[data-wound-id="${wd.woundId}"]`);
                            if (item) {
                                const checks = item.querySelectorAll('.deb-inst-check');
                                checks.forEach(cb => {
                                    cb.checked = wd.instruments && wd.instruments.includes(cb.value);
                                });
                            }
                        });
                    }

                    // Re-generate note to reflect restored state IF the note is empty
                    // If note already exists, we probably shouldn't overwrite it immediately?
                    // But the user wants the inputs to match the note.
                    // generateProcedureNote(); // <-- This might overwrite the existing note text with a generated one. 
                    // Better to NOT regenerate automatically if we just parsed FROM the note.

                } catch (e) {
                    console.error("Error restoring inputs", e);
                }
            }

            // Attach Save Listeners
            checklistInputs.forEach(input => {
                if(input) {
                    const eventType = (input.type === 'text') ? 'input' : 'change';
                    input.addEventListener(eventType, saveInputsToLocal);
                }
            });
            
            // Attach Save Listeners to Screenings
            [document.getElementById('mna_score'), document.getElementById('norton_score'), document.getElementById('bates_jensen_status')].forEach(input => {
                if(input) input.addEventListener('input', saveInputsToLocal);
            });

            // Attach Save Listeners to Braden Scale
            document.querySelectorAll('.braden-select').forEach(select => {
                select.addEventListener('change', saveInputsToLocal);
            });

            // Restore on Load - MOVED TO END OF SCRIPT TO ENSURE DEPENDENCIES ARE READY
            // restoreInputsFromLocal();

            // Manual Trigger
            generateBtn.addEventListener('click', () => {
                if (quill.getText().trim().length > 0 && !confirm("This will overwrite the current note. Continue?")) {
                    return;
                }
                generateProcedureNote();
            });

            // Debounce Helper
            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    const context = this;
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(context, args), wait);
                };
            }

            // Save Note
            async function saveProcedureNote() {
                const noteContent = quill.root.innerHTML;
                
                // Don't save empty notes automatically unless user clicked save
                if (!noteContent || noteContent === '<p><br></p>') return;

                noteSaveStatus.textContent = "Saving...";
                noteSaveStatus.className = "text-sm text-blue-600 font-medium mr-3";

                try {
                    const res = await fetch('api/save_procedure_note.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            appointment_id: appointmentId,
                            procedure_note: noteContent
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        noteSaveStatus.textContent = "Saved!";
                        noteSaveStatus.className = "text-sm text-green-600 font-bold mr-3";
                        setTimeout(() => {
                            noteSaveStatus.textContent = "All changes saved";
                            noteSaveStatus.className = "text-sm text-gray-500 italic mr-3";
                        }, 2000);
                    } else {
                        noteSaveStatus.textContent = "Error saving!";
                        noteSaveStatus.className = "text-sm text-red-600 font-bold mr-3";
                    }
                } catch (e) {
                    console.error(e);
                    noteSaveStatus.textContent = "Error saving!";
                    noteSaveStatus.className = "text-sm text-red-600 font-bold mr-3";
                }
            }

            const debouncedSave = debounce(saveProcedureNote, 2000); // Autosave after 2 seconds of inactivity

            saveNoteBtn.addEventListener('click', saveProcedureNote);
            
            // Auto-save on text change
            quill.on('text-change', function(delta, oldDelta, source) {
                if (source === 'user') {
                    noteSaveStatus.textContent = "Unsaved changes...";
                    noteSaveStatus.className = "text-sm text-yellow-600 italic mr-3";
                    debouncedSave();
                }
            });
            
            // Also save on blur for good measure
            quill.root.addEventListener('blur', saveProcedureNote);

            // --- BRADEN SCALE LOGIC ---
            const bradenSelects = document.querySelectorAll('.braden-select');
            const bradenTotalDisplay = document.getElementById('braden-total-score');
            const bradenRiskDisplay = document.getElementById('braden-risk-level');

            // Additional Screening Inputs
            const mnaInput = document.getElementById('mna_score');
            const mnaRiskDisplay = document.getElementById('mna-risk');
            const nortonInput = document.getElementById('norton_score');
            const nortonRiskDisplay = document.getElementById('norton-risk');
            const batesInput = document.getElementById('bates_jensen_status');

            function calculateBradenScore() {
                let total = 0;
                let allSelected = true;

                bradenSelects.forEach(select => {
                    if (select.value) {
                        total += parseInt(select.value);
                    } else {
                        allSelected = false;
                    }
                });

                if (allSelected) {
                    bradenTotalDisplay.textContent = total;
                    
                    let riskText = "";
                    let riskClass = "";

                    if (total <= 9) {
                        riskText = "Very High Risk";
                        riskClass = "text-red-600 font-bold";
                    } else if (total <= 12) {
                        riskText = "High Risk";
                        riskClass = "text-orange-600 font-bold";
                    } else if (total <= 14) {
                        riskText = "Moderate Risk";
                        riskClass = "text-yellow-600 font-bold";
                    } else if (total <= 18) {
                        riskText = "Mild Risk";
                        riskClass = "text-blue-600 font-bold";
                    } else {
                        riskText = "No Risk";
                        riskClass = "text-green-600 font-bold";
                    }

                    bradenRiskDisplay.textContent = riskText;
                    bradenRiskDisplay.className = riskClass;
                } else {
                    bradenTotalDisplay.textContent = "--";
                    bradenRiskDisplay.textContent = "--";
                    bradenRiskDisplay.className = "font-medium text-gray-500";
                }
            }

            function updateMNARisk() {
                const val = parseInt(mnaInput.value);
                if (isNaN(val)) {
                    mnaRiskDisplay.textContent = "--";
                    mnaRiskDisplay.className = "text-xs font-medium text-gray-500";
                    return;
                }
                
                let text = "";
                let cls = "";
                if (val >= 12) {
                    text = "Normal Status";
                    cls = "text-green-600 font-bold";
                } else if (val >= 8) {
                    text = "At Risk";
                    cls = "text-orange-600 font-bold";
                } else {
                    text = "Malnourished";
                    cls = "text-red-600 font-bold";
                }
                mnaRiskDisplay.textContent = text;
                mnaRiskDisplay.className = "text-xs " + cls;
            }

            function updateNortonRisk() {
                const val = parseInt(nortonInput.value);
                if (isNaN(val)) {
                    nortonRiskDisplay.textContent = "--";
                    nortonRiskDisplay.className = "text-xs font-medium text-gray-500";
                    return;
                }

                let text = "";
                let cls = "";
                if (val > 18) {
                    text = "Low Risk";
                    cls = "text-green-600 font-bold";
                } else if (val >= 14) {
                    text = "Medium Risk";
                    cls = "text-yellow-600 font-bold";
                } else if (val >= 10) {
                    text = "High Risk";
                    cls = "text-orange-600 font-bold";
                } else {
                    text = "Very High Risk";
                    cls = "text-red-600 font-bold";
                }
                nortonRiskDisplay.textContent = text;
                nortonRiskDisplay.className = "text-xs " + cls;
            }

            bradenSelects.forEach(select => {
                select.addEventListener('change', calculateBradenScore);
            });
            
            if(mnaInput) mnaInput.addEventListener('input', updateMNARisk);
            if(nortonInput) nortonInput.addEventListener('input', updateNortonRisk);

            // Restore Inputs (Now that Braden Logic is initialized)
            restoreInputsFromLocal();

            // --- COPY LAST VISIT ---
            const copyLastBtn = document.getElementById('copy-last-visit-btn');
            if (copyLastBtn) {
                copyLastBtn.addEventListener('click', async () => {
                    if (quill.getText().trim().length > 0 && !confirm("This will overwrite the current note. Continue?")) {
                        return;
                    }

                    copyLastBtn.disabled = true;
                    copyLastBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Fetching...';
                    if(typeof lucide !== 'undefined') lucide.createIcons();

                    try {
                        const res = await fetch(`api/get_last_procedure_note.php?patient_id=<?php echo $patient_id; ?>&current_appointment_id=${appointmentId}`);
                        const data = await res.json();

                        if (data.success) {
                            // 1. Set Note
                            quill.root.innerHTML = data.note;
                            noteTextarea.value = data.note;
                            showToast("Note copied from " + data.date);

                            // 2. Parse & Set Screenings
                            // MNA
                            const mnaMatch = data.note.match(/MNA Rating:\s*(\d+)/i);
                            if (mnaMatch && mnaInput) {
                                mnaInput.value = mnaMatch[1];
                                updateMNARisk();
                            }

                            // Norton
                            const nortonMatch = data.note.match(/Norton Rating:\s*(\d+)/i);
                            if (nortonMatch && nortonInput) {
                                nortonInput.value = nortonMatch[1];
                                updateNortonRisk();
                            }

                            // Bates-Jensen
                            // Regex to match content inside the paragraph
                            const batesMatch = data.note.match(/Bates-Jensen Score:<\/strong>\s*(.*?)<\/p>/i);
                            if (batesMatch && batesInput) {
                                const textVal = batesMatch[1].trim();
                                // Try to match select option
                                for (let i = 0; i < batesInput.options.length; i++) {
                                    if (batesInput.options[i].value === textVal) {
                                        batesInput.selectedIndex = i;
                                        break;
                                    }
                                }
                            }

                        } else {
                            showToast(data.message || "No previous note found", "error");
                        }
                    } catch (e) {
                        console.error(e);
                        showToast("Error fetching last note", "error");
                    } finally {
                        copyLastBtn.disabled = false;
                        copyLastBtn.innerHTML = '<i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy Last';
                        if(typeof lucide !== 'undefined') lucide.createIcons();
                    }
                });
            }

            // --- QUICK SET NORMAL ---
            const btnNormal = document.getElementById('btn-screening-normal');
            if (btnNormal) {
                btnNormal.addEventListener('click', () => {
                    // Braden: All 4s, Friction 3
                    bradenSelects.forEach(s => {
                        if (s.id === 'braden_friction') s.value = "3";
                        else s.value = "4";
                    });
                    calculateBradenScore();

                    // MNA: 14
                    if(mnaInput) { mnaInput.value = 14; updateMNARisk(); }

                    // Norton: 20
                    if(nortonInput) { nortonInput.value = 20; updateNortonRisk(); }

                    // Bates: Healed
                    if(batesInput) batesInput.value = "Healed status; no open wounds requiring scoring. Reinforced skin monitoring.";

                    // Trigger Note Update
                    generateProcedureNote();
                    showToast("Screenings set to Normal/Low Risk");
                });
            }

            // --- VOICE DICTATION ---
            const dictationBtn = document.getElementById('start-dictation-btn');
            const dictationModal = document.getElementById('dictation-modal');
            const dictationPreview = document.getElementById('dictation-preview');
            const closeDictationBtn = document.getElementById('close-dictation-btn');
            const clearDictationBtn = document.getElementById('clear-dictation-btn');
            const insertDictationBtn = document.getElementById('insert-dictation-btn');
            
            let recognition;
            let isRecognizing = false;
            let lastCommittedValue = '';

            if (dictationBtn) {
                if ('webkitSpeechRecognition' in window) {
                    recognition = new webkitSpeechRecognition();
                    recognition.continuous = true; // Continuous listening
                    recognition.interimResults = true; // Show partial results

                    recognition.onstart = function() {
                        isRecognizing = true;
                        dictationPreview.placeholder = "Listening... Speak now.";
                        lastCommittedValue = dictationPreview.value; // Sync start state
                    };

                    recognition.onend = function() {
                        isRecognizing = false;
                        // If modal is still open, maybe restart? Or just show stopped state.
                    };

                    recognition.onresult = function(event) {
                        let interimTranscript = '';
                        let newFinalTranscript = '';

                        for (let i = event.resultIndex; i < event.results.length; ++i) {
                            if (event.results[i].isFinal) {
                                newFinalTranscript += event.results[i][0].transcript;
                            } else {
                                interimTranscript += event.results[i][0].transcript;
                            }
                        }

                        if (newFinalTranscript) {
                            lastCommittedValue += newFinalTranscript + ' ';
                        }
                        
                        // Update display: Committed + Interim
                        dictationPreview.value = lastCommittedValue + interimTranscript;
                        dictationPreview.scrollTop = dictationPreview.scrollHeight;
                    };

                    recognition.onerror = function(event) {
                        console.error("Speech recognition error", event.error);
                        // Don't show toast for 'no-speech' as it can be annoying in continuous mode
                        if (event.error !== 'no-speech') {
                            showToast("Dictation Error: " + event.error, 'error');
                        }
                        isRecognizing = false;
                    };

                    // Handle Manual Edits during dictation
                    dictationPreview.addEventListener('input', function() {
                        // If user types, we assume they are editing the 'committed' part
                        // This might conflict if they type while interim is showing, but it's a reasonable tradeoff
                        lastCommittedValue = dictationPreview.value;
                    });

                    // Open Modal & Start
                    dictationBtn.addEventListener('click', () => {
                        dictationModal.classList.remove('hidden');
                        dictationPreview.value = ''; // Clear previous
                        lastCommittedValue = '';
                        dictationPreview.focus();
                        recognition.start();
                    });

                    // Close / Cancel
                    const stopDictation = () => {
                        if (isRecognizing) recognition.stop();
                        dictationModal.classList.add('hidden');
                    };

                    closeDictationBtn.addEventListener('click', stopDictation);
                    
                    // Clear
                    clearDictationBtn.addEventListener('click', () => {
                        dictationPreview.value = '';
                        lastCommittedValue = '';
                        dictationPreview.focus();
                    });

                    // Insert
                    insertDictationBtn.addEventListener('click', () => {
                        if (isRecognizing) recognition.stop();
                        
                        const text = dictationPreview.value.trim();
                        if (text) {
                            const range = quill.getSelection(true);
                            if (range) {
                                quill.insertText(range.index, text + ' ');
                                quill.setSelection(range.index + text.length + 1);
                            } else {
                                const length = quill.getLength();
                                quill.insertText(length - 1, text + ' ');
                                quill.setSelection(length + text.length);
                            }
                        }
                        dictationModal.classList.add('hidden');
                    });

                } else {
                    dictationBtn.style.display = 'none';
                    console.log("Web Speech API not supported.");
                }
            }

            // --- TEMPLATE SYSTEM LOGIC ---
            const templatesMenuBtn = document.getElementById('templates-menu-btn');
            const templatesDropdown = document.getElementById('templates-dropdown');
            const saveTemplateBtn = document.getElementById('save-template-btn');
            const templatesListContainer = document.getElementById('templates-list-container');
            
            // Toggle Dropdown
            if (templatesMenuBtn) {
                templatesMenuBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = templatesDropdown.classList.contains('hidden');
                    if (isHidden) {
                        templatesDropdown.classList.remove('hidden');
                        fetchTemplates(); // Refresh list on open
                    } else {
                        templatesDropdown.classList.add('hidden');
                    }
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (templatesDropdown && !templatesDropdown.contains(e.target) && !templatesMenuBtn.contains(e.target)) {
                    templatesDropdown.classList.add('hidden');
                }
            });

            // Fetch Templates
            async function fetchTemplates() {
                try {
                    const userId = window.procedureData.userId;
                    const res = await fetch(`api/get_user_templates.php?user_id=${userId}&category=Procedure Note`);
                    const data = await res.json();

                    if (data.success) {
                        renderTemplates(data.templates);
                    } else {
                        templatesListContainer.innerHTML = '<span class="block px-4 py-2 text-xs text-red-500">Error loading templates</span>';
                    }
                } catch (e) {
                    console.error(e);
                    templatesListContainer.innerHTML = '<span class="block px-4 py-2 text-xs text-red-500">Connection error</span>';
                }
            }

            // Render Templates List
            function renderTemplates(templates) {
                if (templates.length === 0) {
                    templatesListContainer.innerHTML = '<span class="block px-4 py-2 text-xs text-gray-500 italic">No saved templates</span>';
                    return;
                }

                templatesListContainer.innerHTML = templates.map(t => `
                    <div class="group flex items-center justify-between px-4 py-2 hover:bg-gray-50">
                        <button type="button" class="text-sm text-gray-700 hover:text-indigo-600 flex-1 text-left truncate" onclick="loadTemplate(${t.template_id})">
                            ${t.title}
                        </button>
                        <button type="button" class="text-gray-400 hover:text-red-500 ml-2 opacity-0 group-hover:opacity-100 transition-opacity" onclick="deleteTemplate(${t.template_id}, event)" title="Delete Template">
                            <i data-lucide="trash-2" class="w-3 h-3"></i>
                        </button>
                    </div>
                `).join('');
                
                if(typeof lucide !== 'undefined') lucide.createIcons();
            }

            // Save Template
            if (saveTemplateBtn) {
                saveTemplateBtn.addEventListener('click', () => {
                    const content = quill.root.innerHTML;
                    if (!content || content === '<p><br></p>') {
                        showToast("Cannot save empty template", "error");
                        return;
                    }

                    showCustomPrompt("Enter Template Name", "My Procedure Template", async (name) => {
                        if (name) {
                            try {
                                const res = await fetch('api/save_user_template.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/json'},
                                    body: JSON.stringify({
                                        user_id: window.procedureData.userId,
                                        title: name,
                                        content: content,
                                        category: 'Procedure Note'
                                    })
                                });
                                const data = await res.json();
                                if (data.success) {
                                    showToast("Template saved!");
                                    templatesDropdown.classList.add('hidden');
                                } else {
                                    showToast(data.message || "Error saving template", "error");
                                }
                            } catch (e) {
                                console.error(e);
                                showToast("Error saving template", "error");
                            }
                        }
                    });
                });
            }

            // Load Template (Global function for onclick)
            window.loadTemplate = async (id) => {
                if (quill.getText().trim().length > 0 && !confirm("This will overwrite your current note. Continue?")) {
                    return;
                }

                try {
                    // We fetch all templates again to find the one (or we could have stored them)
                    // For simplicity, let's just fetch the list again or filter if we had it.
                    // Actually, let's just fetch the specific one? No, the API returns all.
                    // Let's just re-fetch list and find it.
                    const userId = window.procedureData.userId;
                    const res = await fetch(`api/get_user_templates.php?user_id=${userId}&category=Procedure Note`);
                    const data = await res.json();
                    
                    if (data.success) {
                        const template = data.templates.find(t => t.template_id == id);
                        if (template) {
                            quill.root.innerHTML = template.content;
                            noteTextarea.value = template.content;
                            attachPlaceholderListeners(); // Re-attach listeners
                            showToast(`Template "${template.title}" loaded`);
                            templatesDropdown.classList.add('hidden');
                        }
                    }
                } catch (e) {
                    console.error(e);
                    showToast("Error loading template", "error");
                }
            };

            // Delete Template (Global function for onclick)
            window.deleteTemplate = async (id, event) => {
                event.stopPropagation(); // Prevent loading the template
                if (!confirm("Are you sure you want to delete this template?")) return;

                try {
                    const res = await fetch('api/delete_user_template.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            template_id: id,
                            user_id: window.procedureData.userId
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        showToast("Template deleted");
                        fetchTemplates(); // Refresh list
                    } else {
                        showToast(data.message || "Error deleting template", "error");
                    }
                } catch (e) {
                    console.error(e);
                    showToast("Error deleting template", "error");
                }
            };

            // INITIALIZE
            fetchDiagnoses();
            fetchProcedures();
            loadSmartSuggestions();
            updateGraftSectionVisibility();
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
            if (el.tagName === 'A' || el.closest('nav') || el.innerText.includes('Next') || el.innerText.includes('Prev') || el.innerText.includes('Back')) {
                return;
            }
            // Skip sidebar toggle
            if (el.id === 'mobile-menu-btn' || el.id === 'toggleSidebarBtn') return;
            
            el.disabled = true;
            el.classList.add('opacity-60', 'cursor-not-allowed');
        });

        // 3. Hide specific action buttons
        const hideIds = ['addProcedureForm']; // Hide the whole form
        hideIds.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });
        
        // 4. Disable delete buttons in table
        const style = document.createElement('style');
        style.innerHTML = `
            .delete-proc-btn, .quick-add-btn { display: none !important; }
            #procedures-tbody button { display: none !important; }
        `;
        document.head.appendChild(style);
    });
</script>
<?php endif; ?>
<?php require_once 'templates/footer.php'; ?>