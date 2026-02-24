<?php
// Filename: visit_ai_assistant.php
// Description: The new AI-first visit interface.

// Check if we are in "Modal Mode" (embedded in iframe or standalone modal)
$is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';

require_once 'templates/header.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($patient_id <= 0) {
    echo '<div class="p-4 text-red-600">Invalid Patient ID</div>';
    require_once 'templates/footer.php';
    exit;
}

// Fetch basic patient info for the header
require_once 'db_connect.php';
$stmt = $conn->prepare("SELECT first_name, last_name, date_of_birth, gender, allergies, past_medical_history FROM patients WHERE patient_id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$patient_name = $patient ? $patient['first_name'] . ' ' . $patient['last_name'] : 'Unknown Patient';

// Calculate Age
$age = 'N/A';
if ($patient && $patient['date_of_birth']) {
    $dob = new DateTime($patient['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y . ' yrs';
}

// Fetch Vitals for this appointment
$vitals = null;
$stmt_v = $conn->prepare("SELECT * FROM patient_vitals WHERE appointment_id = ?");
$stmt_v->bind_param("i", $appointment_id);
$stmt_v->execute();
$res_v = $stmt_v->get_result();
$vitals = $res_v->fetch_assoc();
$stmt_v->close();

// --- NEW INTELLIGENCE CONTEXT ---
// Fetch Active Medications
$active_meds = [];
$stmt_meds = $conn->prepare("SELECT drug_name, dosage, frequency FROM patient_medications WHERE patient_id = ? AND status = 'Active' LIMIT 15");
if ($stmt_meds) {
    $stmt_meds->bind_param("i", $patient_id);
    $stmt_meds->execute();
    $result_meds = $stmt_meds->get_result();
    while ($row = $result_meds->fetch_assoc()) {
        $active_meds[] = $row;
    }
    $stmt_meds->close();
}

// Fetch Active Wounds
$active_wounds_ctx = [];
$stmt_wounds = $conn->prepare("SELECT location, wound_type, date_onset FROM wounds WHERE patient_id = ? AND status = 'Active'");
if ($stmt_wounds) {
    $stmt_wounds->bind_param("i", $patient_id);
    $stmt_wounds->execute();
    $result_wounds = $stmt_wounds->get_result();
    while ($row = $result_wounds->fetch_assoc()) {
        $active_wounds_ctx[] = $row;
    }
    $stmt_wounds->close();
}

// Fetch Past Visits
$past_visits = [];
try {
    $stmt_pv = $conn->prepare("SELECT note_date as visit_date, chief_complaint FROM visit_notes WHERE patient_id = ? ORDER BY note_date DESC LIMIT 5");
    if ($stmt_pv) {
        $stmt_pv->bind_param("i", $patient_id);
        $stmt_pv->execute();
        $res_pv = $stmt_pv->get_result();
        while ($row = $res_pv->fetch_assoc()) {
            $past_visits[] = $row;
        }
        $stmt_pv->close();
    }
} catch (Exception $e) { /* Ignore */ }
// ------------------------------

// Prepare Data for JS
$print_data = [

    'patient' => [
        'name' => $patient_name,
        'dob' => $patient['date_of_birth'] ?? 'N/A',
        'age' => $age,
        'gender' => $patient['gender'] ?? 'N/A',
        'prn' => 'EC' . str_pad($patient_id, 4, '0', STR_PAD_LEFT), // Fake PRN based on ID
        'allergies' => $patient['allergies'] ?? 'No known drug allergies',
        'pmh' => $patient['past_medical_history'] ?? 'No past medical history recorded.'
    ],
    'encounter' => [
        'date' => date('m/d/y'),
        'clinician' => $_SESSION['ec_full_name'] ?? 'Clinician',
        'type' => 'Home Visit'
    ],
    'vitals' => $vitals
];
?>
<script>
    window.printData = <?php echo json_encode($print_data); ?>;
    
    window.aiContext = {
        patient_age: "<?php echo $age; ?>",
        patient_gender: "<?php echo $patient['gender'] ?? 'Unknown'; ?>",
        active_meds: <?php echo json_encode($active_meds); ?>,
        active_wounds: <?php echo json_encode($active_wounds_ctx); ?>,
        past_visits: <?php echo json_encode($past_visits); ?>
    };
</script>
<?php
// Fetch Chat History
$chat_history = [];
$sql_chat = "SELECT sender, message, image_path, created_at FROM visit_ai_messages WHERE appointment_id = ? ORDER BY created_at ASC";
$stmt_chat = $conn->prepare($sql_chat);
$stmt_chat->bind_param("i", $appointment_id);
$stmt_chat->execute();
$res_chat = $stmt_chat->get_result();
while ($row = $res_chat->fetch_assoc()) {
    $chat_history[] = $row;
}
$stmt_chat->close();

// Fetch Wounds for Dropdown
$wounds = [];
$sql_wounds = "SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ? AND status = 'Active'";
$stmt_w = $conn->prepare($sql_wounds);
$stmt_w->bind_param("i", $patient_id);
$stmt_w->execute();
$res_w = $stmt_w->get_result();
while ($row = $res_w->fetch_assoc()) {
    $wounds[] = $row;
}
$stmt_w->close();

// Fetch Live Note Draft
$live_note_draft = "";

// 1. Check visit_notes 'live_note' column (Primary Source for HTML Draft)
$sql_live = "SELECT live_note FROM visit_notes WHERE appointment_id = ?";
$stmt_live = $conn->prepare($sql_live);
$stmt_live->bind_param("i", $appointment_id);
$stmt_live->execute();
$res_live = $stmt_live->get_result();
if ($row_live = $res_live->fetch_assoc()) {
    if (!empty(trim($row_live['live_note']))) {
        $live_note_draft = $row_live['live_note'];
    }
}
$stmt_live->close();

// 2. Check visit_drafts if still empty (Legacy Backup)
if (empty($live_note_draft)) {
    $sql_draft = "SELECT draft_data FROM visit_drafts WHERE appointment_id = ?";
    $stmt_draft = $conn->prepare($sql_draft);
    $stmt_draft->bind_param("i", $appointment_id);
    $stmt_draft->execute();
    $res_draft = $stmt_draft->get_result();
    if ($row_draft = $res_draft->fetch_assoc()) {
        $draft_json = json_decode($row_draft['draft_data'], true);
        if (isset($draft_json['live_note']) && !empty(trim($draft_json['live_note']))) {
            $live_note_draft = $draft_json['live_note'];
        }
    }
    $stmt_draft->close();
}

// 3. Fallback: If no draft, check if there's an existing saved note in visit_notes columns
if (empty($live_note_draft)) {
    $sql_note = "SELECT chief_complaint, subjective, objective, assessment, plan FROM visit_notes WHERE appointment_id = ?";
    $stmt_note = $conn->prepare($sql_note);
    $stmt_note->bind_param("i", $appointment_id);
    $stmt_note->execute();
    $res_note = $stmt_note->get_result();
    if ($row_note = $res_note->fetch_assoc()) {
        // Construct HTML from SOAP fields
        $parts = [];
        if (!empty($row_note['chief_complaint'])) $parts[] = "<h2>Chief Complaint</h2><p>" . nl2br(htmlspecialchars($row_note['chief_complaint'])) . "</p>";
        if (!empty($row_note['subjective'])) $parts[] = "<h2>Subjective</h2><p>" . nl2br(htmlspecialchars($row_note['subjective'])) . "</p>";
        if (!empty($row_note['objective'])) $parts[] = "<h2>Objective</h2><p>" . nl2br(htmlspecialchars($row_note['objective'])) . "</p>";
        if (!empty($row_note['assessment'])) $parts[] = "<h2>Assessment</h2><p>" . nl2br(htmlspecialchars($row_note['assessment'])) . "</p>";
        if (!empty($row_note['plan'])) $parts[] = "<h2>Plan</h2><p>" . nl2br(htmlspecialchars($row_note['plan'])) . "</p>";
        
        if (!empty($parts)) {
            $live_note_draft = implode("\n", $parts);
        }
    }
    $stmt_note->close();
}
?>

<div class="flex h-screen bg-gray-100 font-sans overflow-hidden">
    <?php 
    if (!$is_modal_mode) {
        require_once 'templates/sidebar.php';
    }
    ?>
    
    <script>
        // Ensure SmartCommandParser has global access to context immediately
        window.patientId = <?php echo json_encode($patient_id); ?>;
        window.appointmentId = <?php echo json_encode($appointment_id); ?>;
        window.userId = <?php echo json_encode(isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0); ?>;
    </script>

    <div class="flex-1 flex flex-col h-full relative">
        <main class="flex-1 flex flex-col md:flex-row h-full overflow-hidden relative">
            <!-- Mobile View Tabs -->
            <div class="md:hidden flex border-b border-gray-200 bg-white shrink-0 z-20 shadow-sm">
                <button onclick="switchMobileView('chat')" id="mobile-tab-chat" class="flex-1 py-3 text-center text-sm font-bold text-indigo-600 border-b-2 border-indigo-600 transition-colors">
                    <i data-lucide="message-square" class="w-4 h-4 inline mr-1"></i> Chat
                </button>
                <button onclick="switchMobileView('note')" id="mobile-tab-note" class="flex-1 py-3 text-center text-sm font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition-colors">
                    <i data-lucide="file-text" class="w-4 h-4 inline mr-1"></i> Live Note
                </button>
            </div>

            <!-- Left Panel: AI Conversation -->
            <div id="panel-chat" class="w-full md:w-1/2 flex flex-col border-r border-gray-200 bg-white shadow-lg z-10 h-full">
        <!-- Header -->
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-indigo-600 text-white">
            <div>
                <h2 class="text-lg font-bold flex items-center">
                    <i data-lucide="bot" class="w-5 h-5 mr-2"></i> AI Assistant
                </h2>
                <p class="text-xs text-indigo-100">Visit with <?php echo htmlspecialchars($patient_name); ?></p>
            </div>
            <div class="flex space-x-2 items-center">
                <!-- Clinical Tools Dropdown -->
                <div class="relative group">
                    <button class="bg-indigo-500 hover:bg-indigo-400 text-white text-xs px-3 py-1.5 rounded transition flex items-center">
                        <i data-lucide="menu" class="w-4 h-4 mr-1"></i> Clinical Tools
                    </button>
                    <div class="absolute right-0 mt-1 w-48 bg-white rounded-md shadow-xl py-1 z-50 hidden group-hover:block border border-gray-200">
                        <a href="patient_profile.php?id=<?php echo $patient_id; ?>" data-tab-title="Patient Profile" data-tab-icon="user" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="user" class="w-4 h-4 mr-2 text-gray-400"></i> Profile</a>
                        <a href="visit_vitals.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>" data-tab-title="Vitals" data-tab-icon="activity" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="activity" class="w-4 h-4 mr-2 text-gray-400"></i> Vitals</a>
                        <a href="visit_hpi.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>" data-tab-title="HPI" data-tab-icon="file-text" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="file-text" class="w-4 h-4 mr-2 text-gray-400"></i> HPI</a>
                        <a href="visit_wounds.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>" data-tab-title="Wound Assessment" data-tab-icon="alert-circle" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="alert-circle" class="w-4 h-4 mr-2 text-gray-400"></i> Wounds</a>
                        <a href="visit_medications.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appointment_id; ?>" data-tab-title="Medications" data-tab-icon="pill" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="pill" class="w-4 h-4 mr-2 text-gray-400"></i> Medications</a>
                        <a href="patient_orders.php?id=<?php echo $patient_id; ?>" data-tab-title="Orders" data-tab-icon="clipboard-list" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="clipboard-list" class="w-4 h-4 mr-2 text-gray-400"></i> Orders</a>
                        <a href="patient_chart_history.php?id=<?php echo $patient_id; ?>" data-tab-title="Past Visit Notes" data-tab-icon="history" class="block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 flex items-center"><i data-lucide="history" class="w-4 h-4 mr-2 text-gray-400"></i> Past Visit Notes</a>
                    </div>
                </div>

                <a href="ai_assistant_guide.php" target="_blank" class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs px-3 py-1.5 rounded transition flex items-center" title="View Guide">
                    <i data-lucide="help-circle" class="w-4 h-4"></i>
                </a>
                <a href="patient_profile.php?id=<?php echo $patient_id; ?>" data-tab-title="Patient Profile" data-tab-icon="user" class="bg-red-500 hover:bg-red-600 text-white text-xs px-3 py-1.5 rounded transition flex items-center" title="Exit Assistant">
                    <i data-lucide="log-out" class="w-4 h-4 mr-1"></i> Exit
                </a>
            </div>
        </div>

        <!-- Chat Area -->
        <div id="ai-chat-container" class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50">
            <!-- Welcome Message -->
            <div class="flex items-start">
                <div class="bg-indigo-100 p-2 rounded-full mr-3 flex-shrink-0">
                    <i data-lucide="bot" class="w-5 h-5 text-indigo-600"></i>
                </div>
                <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-100 max-w-[85%]">
                    <p class="text-gray-800 text-sm">Hello! I'm ready to help with this visit. You can speak naturally. I'll draft the note as we go.</p>
                </div>
            </div>
        </div>

        <!-- Input Area -->
        <div class="p-2 md:p-4 bg-white border-t border-gray-100">
            <!-- Quick Action Chips -->
            <div class="flex space-x-2 mb-3 overflow-x-auto pb-1 no-scrollbar" id="quick-actions">
                <button class="quick-action-chip px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-100 border border-indigo-100 transition whitespace-nowrap">
                    Summarize History
                </button>
                <button class="quick-action-chip px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-100 border border-indigo-100 transition whitespace-nowrap">
                    Suggest Plan
                </button>
                <button class="quick-action-chip px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-100 border border-indigo-100 transition whitespace-nowrap">
                    Review Vitals
                </button>
                <button class="quick-action-chip px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-100 border border-indigo-100 transition whitespace-nowrap">
                    Draft Referral
                </button>
                <button class="quick-action-chip px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-xs font-medium hover:bg-indigo-100 border border-indigo-100 transition whitespace-nowrap">
                    Normal Exam
                </button>
            </div>

            <div class="flex items-center space-x-2">
                <label class="flex flex-col items-center cursor-pointer mr-1" title="Fast Chat Mode (No Note Updates)">
                    <input type="checkbox" id="fast_mode_toggle" class="sr-only peer">
                    <div class="w-8 h-4 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-green-500 relative"></div>
                    <span class="text-[10px] text-gray-500 font-medium mt-0.5">Fast</span>
                </label>
                <label class="flex flex-col items-center cursor-pointer mr-1" title="Enable Cloud Dictation for better accuracy">
                    <input type="checkbox" id="cloud_mode_toggle" class="sr-only peer">
                    <div class="w-8 h-4 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-indigo-600 relative"></div>
                    <span class="text-[10px] text-gray-500 font-medium mt-0.5">Cloud</span>
                </label>
                
                <div class="flex flex-col items-center mr-1">
                    <button id="mic-toggle-btn" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <i data-lucide="mic" class="w-5 h-5"></i>
                    </button>
                    <span class="text-[10px] text-gray-500 font-medium mt-0.5 hidden xs:block">Speak</span>
                </div>

                <div class="flex flex-col items-center mr-1">
                    <button id="camera-btn" class="p-2 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <i data-lucide="camera" class="w-5 h-5"></i>
                    </button>
                    <span class="text-[10px] text-gray-500 font-medium mt-0.5 hidden xs:block">Photo</span>
                </div>
                <textarea id="user-input" rows="1" placeholder="Type or speak..." class="flex-1 border-gray-300 rounded-2xl focus:ring-indigo-500 focus:border-indigo-500 px-4 py-3 text-sm min-w-0 resize-none overflow-hidden leading-normal shadow-sm" style="min-height: 44px; max-height: 150px;"></textarea>
                <button id="send-btn" class="p-2 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shrink-0 self-end mb-0.5">
                    <i data-lucide="send" class="w-5 h-5"></i>
                </button>
            </div>
            <p id="status-indicator" class="text-xs text-gray-400 mt-2 text-center h-4">Ready</p>
        </div>
    </div>

    <!-- Right Panel: Live Note Preview -->
    <div id="panel-note" class="hidden md:flex w-full md:w-1/2 flex-col bg-gray-50 h-full">
        <div class="p-4 border-b border-gray-200 bg-white flex justify-between items-center relative">
            <h3 class="font-bold text-gray-700 flex items-center">
                <i data-lucide="file-text" class="w-5 h-5 mr-2 text-gray-500"></i> Live Note Draft
            </h3>
            
            <!-- Floating Toolbar in Header -->
            <div id="live-note-toolbar" class="flex items-center space-x-1 bg-gray-100 border border-gray-200 rounded-md p-1 shadow-sm">
                <button type="button" data-command="bold" class="p-1.5 text-gray-600 hover:bg-white hover:shadow-sm rounded hover:text-indigo-600 transition-all" title="Bold">
                    <i data-lucide="bold" class="w-4 h-4"></i>
                </button>
                <button type="button" data-command="italic" class="p-1.5 text-gray-600 hover:bg-white hover:shadow-sm rounded hover:text-indigo-600 transition-all" title="Italic">
                    <i data-lucide="italic" class="w-4 h-4"></i>
                </button>
                <div class="w-px h-4 bg-gray-300 mx-1"></div>
                <button type="button" data-command="insertUnorderedList" class="p-1.5 text-gray-600 hover:bg-white hover:shadow-sm rounded hover:text-indigo-600 transition-all" title="Bullet List">
                    <i data-lucide="list" class="w-4 h-4"></i>
                </button>
                <div class="w-px h-4 bg-gray-300 mx-1"></div>
                <button type="button" id="copy-note-btn" class="p-1.5 text-gray-600 hover:bg-white hover:shadow-sm rounded hover:text-indigo-600 transition-all" title="Copy to Clipboard">
                    <i data-lucide="copy" class="w-4 h-4"></i>
                </button>
                <button type="button" id="print-note-btn" class="p-1.5 text-gray-600 hover:bg-white hover:shadow-sm rounded hover:text-indigo-600 transition-all" title="Print Note">
                    <i data-lucide="printer" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="flex items-center space-x-3">
                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded-full">Auto-Saving</span>
                <!-- Reconstruct SOAP Button Removed -->
                <button id="end-visit-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs px-3 py-1.5 rounded transition flex items-center shadow-sm">
                    <i data-lucide="pen-tool" class="w-3 h-3 mr-1"></i> Sign Visit Note
                </button>
            </div>
        </div>
        
        <div class="flex-1 overflow-y-auto p-6">
            <!-- AI Insights Section -->
            <div id="ai-insights-container" class="mb-6 bg-indigo-50 border border-indigo-100 rounded-lg p-4 hidden transition-all shadow-sm">
                <div class="flex justify-between items-start mb-2">
                    <h4 class="text-indigo-800 font-bold text-sm flex items-center">
                        <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i> AI Insights
                    </h4>
                    <button id="close-insights-btn" class="text-indigo-400 hover:text-indigo-600">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div id="ai-insights-content" class="text-sm text-indigo-900 prose prose-sm max-w-none prose-indigo">
                    <!-- Insights will appear here -->
                </div>
            </div>

            <div id="live-note-content" class="bg-white shadow-sm border border-gray-200 rounded-lg p-6 min-h-[500px] prose prose-lg max-w-none focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent" contenteditable="true">
                <div class="animate-pulse space-y-4">
                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                    <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                </div>
                <p class="text-gray-400 text-center mt-8 italic">As you speak, the structured note will appear here...</p>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="review-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl h-[90vh] flex flex-col overflow-hidden">
        <div class="p-4 bg-indigo-600 text-white flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center">
                <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i> Review AI Extraction
            </h3>
            <button id="close-review-btn" class="text-indigo-200 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        
        <!-- Tabs -->
        <div class="flex border-b border-gray-200 bg-gray-50">
            <button class="review-tab-btn px-6 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600 focus:outline-none" data-tab="soap-tab">
                SOAP Note
            </button>
            <button class="review-tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none" data-tab="clinical-tab">
                Clinical Data
            </button>
            <button class="review-tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none" data-tab="wounds-tab">
                Wounds & Procedures
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <!-- SOAP Tab -->
            <div id="soap-tab" class="review-tab-content space-y-4">
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3 flex items-center"><i data-lucide="file-text" class="w-4 h-4 mr-2"></i> Subjective & Objective</h4>
                    
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Chief Complaint</label>
                        <textarea id="review-cc" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500" rows="2"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">HPI (History of Present Illness)</label>
                            <textarea id="review-hpi" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500" rows="6"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">ROS (Review of Systems)</label>
                            <textarea id="review-ros" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500" rows="6"></textarea>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Subjective (Other)</label>
                            <div id="review-subjective" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500 min-h-[100px] max-h-[300px] overflow-y-auto" contenteditable="true"></div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Objective</label>
                            <div id="review-objective" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500 min-h-[100px] max-h-[300px] overflow-y-auto" contenteditable="true"></div>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3 flex items-center"><i data-lucide="stethoscope" class="w-4 h-4 mr-2"></i> Assessment & Plan</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Assessment</label>
                            <div id="review-assessment" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500 min-h-[150px] max-h-[300px] overflow-y-auto" contenteditable="true"></div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Plan</label>
                            <div id="review-plan" class="w-full p-2 border border-gray-300 rounded text-lg focus:ring-indigo-500 focus:border-indigo-500 min-h-[150px] max-h-[300px] overflow-y-auto" contenteditable="true"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clinical Data Tab -->
            <div id="clinical-tab" class="review-tab-content hidden space-y-4">
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3">Vitals</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="review-vitals-container">
                        <!-- Vitals inputs generated by JS -->
                    </div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3">Medications</h4>
                    <div id="review-medications-list" class="space-y-2">
                        <!-- Medications list generated by JS -->
                    </div>
                    <button id="add-med-btn" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Medication</button>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3">Diagnoses (ICD-10)</h4>
                    <div id="review-diagnoses-list" class="space-y-2">
                        <!-- Diagnoses list generated by JS -->
                    </div>
                    <button id="add-diag-btn" class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">+ Add Diagnosis</button>
                </div>
            </div>

            <!-- Wounds Tab -->
            <div id="wounds-tab" class="review-tab-content hidden space-y-4">
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3">Wound Assessments</h4>
                    <div id="review-wounds-list" class="space-y-4">
                        <!-- Wounds list generated by JS -->
                    </div>
                </div>
                
                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                    <h4 class="text-sm font-bold text-indigo-800 mb-3">Procedure Note</h4>
                    <textarea id="review-procedure" class="w-full p-2 border border-gray-300 rounded text-sm focus:ring-indigo-500 focus:border-indigo-500" rows="4" placeholder="Narrative description of procedure performed..."></textarea>
                </div>
            </div>
        </div>

        <div class="p-4 border-t border-gray-200 bg-white flex justify-between items-center">
            <div class="text-xs text-gray-500 italic">
                <i data-lucide="info" class="w-3 h-3 inline mr-1"></i> Review all tabs before saving.
            </div>
            <div class="flex space-x-3">
                <button id="cancel-review-btn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium">
                    Cancel
                </button>
                <button id="finalize-visit-btn" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium flex items-center shadow-sm">
                    <i data-lucide="pen-tool" class="w-4 h-4 mr-2"></i> Sign Visit Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Image Annotation Modal -->
<div id="annotation-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl h-[80vh] flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="p-4 bg-gray-800 text-white flex justify-between items-center">
            <h3 class="font-bold flex items-center">
                <i data-lucide="edit-3" class="w-5 h-5 mr-2"></i> Annotate Photo
            </h3>
            <button id="close-annotation-btn" class="text-gray-400 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Toolbar -->
        <div class="p-2 bg-gray-100 border-b border-gray-200 flex items-center space-x-2 overflow-x-auto">
            <button id="tool-pen" class="p-2 bg-indigo-600 text-white rounded shadow-sm" title="Pen">
                <i data-lucide="pen-tool" class="w-4 h-4"></i>
            </button>
            <div class="flex space-x-1 border-l border-r border-gray-300 px-2">
                <button class="color-btn w-6 h-6 rounded-full bg-red-500 ring-2 ring-offset-1 ring-gray-400" data-color="#ef4444"></button>
                <button class="color-btn w-6 h-6 rounded-full bg-yellow-400" data-color="#facc15"></button>
                <button class="color-btn w-6 h-6 rounded-full bg-green-500" data-color="#22c55e"></button>
                <button class="color-btn w-6 h-6 rounded-full bg-blue-500" data-color="#3b82f6"></button>
            </div>
            <button id="tool-undo" class="p-2 text-gray-600 hover:bg-gray-200 rounded" title="Undo">
                <i data-lucide="undo" class="w-4 h-4"></i>
            </button>
            <button id="tool-clear" class="p-2 text-gray-600 hover:bg-gray-200 rounded" title="Clear All">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        </div>

        <!-- Canvas Area -->
        <div class="flex-1 bg-gray-900 relative flex items-center justify-center overflow-hidden">
            <canvas id="annotation-canvas" class="block cursor-crosshair touch-none max-w-full max-h-full"></canvas>
            <div id="upload-prompt" class="absolute inset-0 flex flex-col items-center justify-center text-white">
                <i data-lucide="camera" class="w-16 h-16 mb-4 text-gray-500"></i>
                <p class="text-gray-400 mb-4">Upload a photo to start annotating</p>
                <input type="file" id="file-input" accept="image/*" class="hidden">
                <button id="trigger-upload-btn" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-semibold transition">
                    Select Photo
                </button>
            </div>
        </div>

        <!-- Metadata Inputs -->
        <div class="p-3 bg-gray-50 border-t border-gray-200 grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Image Type</label>
                <select id="annotate-image-type" class="w-full p-2 border border-gray-300 rounded text-sm">
                    <option value="Regular">Regular Assessment</option>
                    <option value="Pre-Debridement">Pre-Debridement</option>
                    <option value="Post-Debridement">Post-Debridement</option>
                    <option value="Post-Graft">Post-Graft</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-500 mb-1">Link to Wound</label>
                <select id="annotate-wound-id" class="w-full p-2 border border-gray-300 rounded text-sm">
                    <option value="">-- New / Unspecified --</option>
                    <?php foreach ($wounds as $w): ?>
                        <option value="<?php echo $w['wound_id']; ?>">
                            <?php echo htmlspecialchars($w['location'] . ' (' . $w['wound_type'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Footer -->
        <div class="p-4 bg-white border-t border-gray-200 flex justify-end space-x-3">
            <button id="cancel-annotation-btn" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg">Cancel</button>
            <button id="save-annotation-btn" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-md flex items-center disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                <i data-lucide="send" class="w-4 h-4 mr-2"></i> Send to Chat
            </button>
        </div>
    </div>
</div>

<!-- Help Modal -->
<div id="help-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl h-[85vh] flex flex-col overflow-hidden">
        <div class="p-4 bg-indigo-600 text-white flex justify-between items-center">
            <h3 class="font-bold text-lg flex items-center">
                <i data-lucide="book-open" class="w-5 h-5 mr-2"></i> AI Assistant Guide
            </h3>
            <button id="close-help-btn" class="text-indigo-200 hover:text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <!-- Tabs -->
        <div class="flex border-b border-gray-200 bg-gray-50 px-4 pt-4">
            <button class="help-tab-btn px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600 focus:outline-none" data-tab="guide-tab">
                User Guide
            </button>
            <button class="help-tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none" data-tab="prompts-tab">
                Sample Prompts
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 bg-gray-50">
            <!-- Guide Tab -->
            <div id="guide-tab" class="help-tab-content">
                <div class="prose prose-sm prose-indigo max-w-none bg-white p-6 rounded-lg shadow-sm border border-gray-200">
                    <h3 class="text-indigo-800 mt-0">Welcome to the AI Assistant</h3>
                    <p>This tool helps you document patient visits efficiently using natural language and automated extraction.</p>

                    <hr class="my-4 border-gray-200">

                    <h4 class="flex items-center text-gray-800"><i data-lucide="message-square" class="w-4 h-4 mr-2 text-indigo-500"></i> 1. The Conversation Panel (Left)</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600">
                        <li><strong>Chat with AI:</strong> Type or speak naturally. The AI understands medical terminology and context.</li>
                        <li><strong>Microphone:</strong> Click the <i data-lucide="mic" class="w-3 h-3 inline"></i> icon to dictate notes or commands.</li>
                        <li><strong>Camera:</strong> Click <i data-lucide="camera" class="w-3 h-3 inline"></i> to upload or take photos. You can <strong>annotate</strong> them (draw circles, arrows) before sending.</li>
                        <li><strong>Quick Actions:</strong> Use the chips above the input bar for common tasks like "Summarize History" or "Suggest Plan".</li>
                    </ul>

                    <h4 class="flex items-center text-gray-800 mt-6"><i data-lucide="file-text" class="w-4 h-4 mr-2 text-indigo-500"></i> 2. The Live Note (Right)</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600">
                        <li><strong>Real-time Drafting:</strong> As you provide information, the AI updates the note on the right automatically.</li>
                        <li><strong>Manual Editing:</strong> You can click into the note area and type corrections at any time.</li>
                        <li><strong>Auto-Save:</strong> Your work is saved automatically to a draft. Look for the "Saved" indicator.</li>
                    </ul>

                    <h4 class="flex items-center text-gray-800 mt-6"><i data-lucide="check-circle" class="w-4 h-4 mr-2 text-indigo-500"></i> 3. Finalizing the Visit</h4>
                    <ul class="list-disc pl-5 space-y-1 text-gray-600">
                        <li><strong>Process with AI:</strong> When finished, click the button in the top right header.</li>
                        <li><strong>Review Mode:</strong> The AI will extract structured data (Vitals, Medications, Diagnoses, Wounds) and format the SOAP note.</li>
                        <li><strong>Confirm & Save:</strong> Review the extracted data in the tabs, make final tweaks, and save directly to the patient's chart.</li>
                    </ul>
                    
                    <div class="mt-6 bg-indigo-50 p-4 rounded-lg border border-indigo-100">
                        <p class="text-xs text-indigo-800 font-medium flex items-start">
                            <i data-lucide="info" class="w-4 h-4 mr-2 flex-shrink-0 mt-0.5"></i>
                            <strong>Tip:</strong> You can ask the AI to "Add a wound to the left heel measuring 2x3cm" and it will be ready for extraction later.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Prompts Tab -->
            <div id="prompts-tab" class="help-tab-content hidden">
                <div class="space-y-6">
                    <!-- Section: History & Intake -->
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                        <h4 class="text-sm font-bold text-indigo-800 mb-3 flex items-center">
                            <i data-lucide="history" class="w-4 h-4 mr-2"></i> History & Intake
                        </h4>
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Patient presents with a new diabetic ulcer on the right big toe. Started 2 weeks ago after new shoes. Pain is 4/10."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Summarize the patient's past medical history from the chart, focusing on vascular issues."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Wound Assessment -->
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                        <h4 class="text-sm font-bold text-indigo-800 mb-3 flex items-center">
                            <i data-lucide="ruler" class="w-4 h-4 mr-2"></i> Wound Assessment
                        </h4>
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Wound on left sacrum measures 4.5 x 3.2 x 0.5 cm. 40% granulation, 60% slough. Moderate serous drainage."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Periwound skin is erythematous and warm. No odor detected. Edges are macerated."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                        </div>
                    </div>

                    <!-- Section: Plan & Orders -->
                    <div class="bg-white p-5 rounded-lg shadow-sm border border-gray-200">
                        <h4 class="text-sm font-bold text-indigo-800 mb-3 flex items-center">
                            <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i> Plan & Orders
                        </h4>
                        <div class="space-y-3">
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Plan: Cleanse with saline, apply Santyl to slough, cover with foam dressing. Change daily."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                            <div class="bg-gray-50 p-3 rounded border border-gray-100 cursor-pointer hover:bg-indigo-50 hover:border-indigo-200 transition-colors group" onclick="copyPrompt(this)">
                                <p class="text-sm text-gray-700 font-medium">"Order an arterial doppler for bilateral lower extremities to rule out PAD."</p>
                                <span class="text-xs text-indigo-500 mt-1 hidden group-hover:block">Click to copy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="p-4 border-t border-gray-200 bg-white flex justify-end">
            <button id="close-help-footer-btn" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 text-sm font-medium">
                Close Guide
            </button>
        </div>
    </div>
</div>

<!-- Pass PHP variables to JS -->
<script>
    window.visitContext = {
        patientId: <?php echo $patient_id; ?>,
        appointmentId: <?php echo $appointment_id; ?>,
        userId: <?php echo isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0; ?>,
        mode: 'ai_assistant',
        chatHistory: <?php echo json_encode($chat_history); ?>,
        liveNoteDraft: <?php echo json_encode($live_note_draft); ?>
    };

    // Render Live Note Draft
    const liveNoteContainer = document.getElementById('live-note-content');
    if (window.visitContext.liveNoteDraft && liveNoteContainer) {
        liveNoteContainer.innerHTML = window.visitContext.liveNoteDraft;
    }

    // Render Chat History
    const chatContainer = document.getElementById('ai-chat-container');
    if (window.visitContext.chatHistory && window.visitContext.chatHistory.length > 0) {
        window.visitContext.chatHistory.forEach(msg => {
            const isUser = msg.sender === 'user';
            const alignClass = isUser ? 'justify-end' : 'justify-start';
            const bgClass = isUser ? 'bg-indigo-600 text-white' : 'bg-white text-gray-800 border border-gray-100';
            const icon = isUser ? '<i data-lucide="user" class="w-5 h-5 text-white"></i>' : '<i data-lucide="bot" class="w-5 h-5 text-indigo-600"></i>';
            const iconBg = isUser ? 'bg-indigo-800' : 'bg-indigo-100';
            
            let content = `<p class="text-sm">${msg.message}</p>`;
            if (msg.image_path) {
                content = `<img src="${msg.image_path}" class="rounded mb-2 max-h-60 w-auto border border-indigo-500">` + content;
            }

            const html = `
                <div class="flex items-start ${alignClass}">
                    ${!isUser ? `<div class="${iconBg} p-2 rounded-full mr-3 flex-shrink-0">${icon}</div>` : ''}
                    <div class="${bgClass} p-3 rounded-lg shadow-sm max-w-[85%]">
                        ${content}
                    </div>
                    ${isUser ? `<div class="${iconBg} p-2 rounded-full ml-3 flex-shrink-0">${icon}</div>` : ''}
                </div>
            `;
            chatContainer.insertAdjacentHTML('beforeend', html);
        });
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    // Quick Action Chips Logic
    document.querySelectorAll('.quick-action-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            const text = chip.innerText.trim();
            const input = document.getElementById('user-input');
            const sendBtn = document.getElementById('send-btn');
            
            if (input && sendBtn) {
                input.value = text;
                sendBtn.click();
            }
        });
    });

    // AI Insights Logic
    const insightsContainer = document.getElementById('ai-insights-container');
    const closeInsightsBtn = document.getElementById('close-insights-btn');
    if (closeInsightsBtn && insightsContainer) {
        closeInsightsBtn.addEventListener('click', () => {
            insightsContainer.classList.add('hidden');
        });
    }

    // Handle End Visit Button
    const reviewModal = document.getElementById('review-modal');
    const closeReviewBtn = document.getElementById('close-review-btn');
    const cancelReviewBtn = document.getElementById('cancel-review-btn');
    const finalizeVisitBtn = document.getElementById('finalize-visit-btn');

    // Tab Switching Logic
    document.querySelectorAll('.review-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Update Buttons
            document.querySelectorAll('.review-tab-btn').forEach(b => {
                b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600');
                b.classList.add('text-gray-500');
            });
            btn.classList.remove('text-gray-500');
            btn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');

            // Update Content
            document.querySelectorAll('.review-tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(btn.dataset.tab).classList.remove('hidden');
        });
    });

    document.getElementById('end-visit-btn').addEventListener('click', function() {
        const liveNoteContainer = document.getElementById('live-note-content');
        if (!liveNoteContainer || liveNoteContainer.innerText.trim() === '') {
            alert("No note content generated yet. Please speak to the AI first.");
            return;
        }

        // Show Loading State on Button
        const btn = this;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 mr-2 animate-spin"></i> Analyzing...';

        // Call Parse Draft API
        fetch('api/ai_companion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'parse_draft',
                note_html: liveNoteContainer.innerHTML
            })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalText;

            if (data.success) {
                populateReviewModal(data.data);
                reviewModal.classList.remove('hidden');
            } else {
                alert('Error analyzing note: ' + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Network error occurred.');
        });
    });

    function populateReviewModal(data) {
        const soap = data.soap || {};
        const extracted = data.extracted || {};

        // SOAP Tab
        document.getElementById('review-cc').value = extracted.chief_complaint || '';
        document.getElementById('review-hpi').value = extracted.hpi || '';
        document.getElementById('review-ros').value = extracted.ros || '';
        document.getElementById('review-subjective').innerHTML = soap.subjective || ''; // Fallback or extra
        document.getElementById('review-objective').innerHTML = soap.objective || '';
        document.getElementById('review-assessment').innerHTML = soap.assessment || '';
        document.getElementById('review-plan').innerHTML = soap.plan || '';

        // Clinical Data Tab - Vitals
        const vitalsContainer = document.getElementById('review-vitals-container');
        vitalsContainer.innerHTML = '';
        const vitals = extracted.vitals || {};
        const vitalFields = [
            { key: 'blood_pressure', label: 'BP' },
            { key: 'heart_rate', label: 'HR' },
            { key: 'respiratory_rate', label: 'RR' },
            { key: 'oxygen_saturation', label: 'O2 Sat' },
            { key: 'temperature_f', label: 'Temp (F)' },
            { key: 'weight_lbs', label: 'Weight (lbs)' },
            { key: 'height_in', label: 'Height (in)' }
        ];
        
        vitalFields.forEach(field => {
            const val = vitals[field.key] || '';
            vitalsContainer.innerHTML += `
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">${field.label}</label>
                    <input type="text" class="review-vital-input w-full p-2 border border-gray-300 rounded text-sm" data-key="${field.key}" value="${val}">
                </div>
            `;
        });

        // Medications
        const medsList = document.getElementById('review-medications-list');
        medsList.innerHTML = '';
        if (extracted.medications && extracted.medications.length > 0) {
            extracted.medications.forEach(med => addMedicationRow(med));
        } else {
            addMedicationRow(); // Add empty row
        }

        // Diagnoses
        const diagList = document.getElementById('review-diagnoses-list');
        diagList.innerHTML = '';
        if (extracted.diagnoses && extracted.diagnoses.length > 0) {
            extracted.diagnoses.forEach(diag => addDiagnosisRow(diag));
        } else {
            addDiagnosisRow(); // Add empty row
        }

        // Wounds Tab
        const woundsList = document.getElementById('review-wounds-list');
        woundsList.innerHTML = '';
        if (extracted.wounds && extracted.wounds.length > 0) {
            extracted.wounds.forEach(wound => addWoundRow(wound));
        } else {
            woundsList.innerHTML = '<p class="text-sm text-gray-500 italic">No wounds detected.</p>';
        }

        // Procedure
        const proc = extracted.procedure || {};
        document.getElementById('review-procedure').value = proc.narrative || '';
    }

    // Helper to add Medication Row
    function addMedicationRow(med = {}) {
        const container = document.getElementById('review-medications-list');
        const div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-2 items-center review-med-row';
        div.innerHTML = `
            <div class="col-span-4"><input type="text" placeholder="Drug Name" class="w-full p-2 border border-gray-300 rounded text-sm med-name" value="${med.drug_name || ''}"></div>
            <div class="col-span-2"><input type="text" placeholder="Dose" class="w-full p-2 border border-gray-300 rounded text-sm med-dose" value="${med.dosage || ''}"></div>
            <div class="col-span-3"><input type="text" placeholder="Freq" class="w-full p-2 border border-gray-300 rounded text-sm med-freq" value="${med.frequency || ''}"></div>
            <div class="col-span-2"><input type="text" placeholder="Route" class="w-full p-2 border border-gray-300 rounded text-sm med-route" value="${med.route || ''}"></div>
            <div class="col-span-1 text-center"><button class="text-red-500 hover:text-red-700" onclick="this.closest('.review-med-row').remove()"><i data-lucide="trash" class="w-4 h-4"></i></button></div>
        `;
        container.appendChild(div);
        lucide.createIcons();
    }
    document.getElementById('add-med-btn').addEventListener('click', () => addMedicationRow());

    // Helper to add Diagnosis Row
    function addDiagnosisRow(diag = {}) {
        const container = document.getElementById('review-diagnoses-list');
        const div = document.createElement('div');
        div.className = 'grid grid-cols-12 gap-2 items-center review-diag-row';
        div.innerHTML = `
            <div class="col-span-3"><input type="text" placeholder="ICD-10" class="w-full p-2 border border-gray-300 rounded text-sm diag-code" value="${diag.icd10_code || ''}"></div>
            <div class="col-span-8"><input type="text" placeholder="Description" class="w-full p-2 border border-gray-300 rounded text-sm diag-desc" value="${diag.description || ''}"></div>
            <div class="col-span-1 text-center"><button class="text-red-500 hover:text-red-700" onclick="this.closest('.review-diag-row').remove()"><i data-lucide="trash" class="w-4 h-4"></i></button></div>
        `;
        container.appendChild(div);
        lucide.createIcons();
    }
    document.getElementById('add-diag-btn').addEventListener('click', () => addDiagnosisRow());

    // Helper to add Wound Row
    function addWoundRow(wound = {}) {
        const container = document.getElementById('review-wounds-list');
        const div = document.createElement('div');
        div.className = 'bg-gray-50 p-3 rounded border border-gray-200 review-wound-row';
        div.innerHTML = `
            <div class="grid grid-cols-2 gap-4 mb-2">
                <div><label class="text-xs text-gray-500">Location</label><input type="text" class="w-full p-1 border rounded text-sm wound-loc" value="${wound.location || ''}"></div>
                <div><label class="text-xs text-gray-500">Type</label><input type="text" class="w-full p-1 border rounded text-sm wound-type" value="${wound.type || ''}"></div>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-2">
                <div><label class="text-xs text-gray-500">L (cm)</label><input type="number" step="0.1" class="w-full p-1 border rounded text-sm wound-l" value="${wound.length_cm || ''}"></div>
                <div><label class="text-xs text-gray-500">W (cm)</label><input type="number" step="0.1" class="w-full p-1 border rounded text-sm wound-w" value="${wound.width_cm || ''}"></div>
                <div><label class="text-xs text-gray-500">D (cm)</label><input type="number" step="0.1" class="w-full p-1 border rounded text-sm wound-d" value="${wound.depth_cm || ''}"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="text-xs text-gray-500">Drainage</label><input type="text" class="w-full p-1 border rounded text-sm wound-drainage" value="${wound.drainage_type || ''}"></div>
                <div><label class="text-xs text-gray-500">Pain (0-10)</label><input type="number" class="w-full p-1 border rounded text-sm wound-pain" value="${wound.pain_level || ''}"></div>
            </div>
        `;
        container.appendChild(div);
    }

    function closeReview() {
        reviewModal.classList.add('hidden');
    }

    if (closeReviewBtn) closeReviewBtn.addEventListener('click', closeReview);
    if (cancelReviewBtn) cancelReviewBtn.addEventListener('click', closeReview);

    if (finalizeVisitBtn) {
        finalizeVisitBtn.addEventListener('click', function() {
            // Gather Data
            const reviewData = {
                chief_complaint: document.getElementById('review-cc').value,
                hpi: document.getElementById('review-hpi').value,
                ros: document.getElementById('review-ros').value,
                subjective: document.getElementById('review-subjective').innerHTML,
                objective: document.getElementById('review-objective').innerHTML,
                assessment: document.getElementById('review-assessment').innerHTML,
                plan: document.getElementById('review-plan').innerHTML,
                procedure: { narrative: document.getElementById('review-procedure').value },
                vitals: {},
                medications: [],
                diagnoses: [],
                wounds: []
            };

            // Vitals
            document.querySelectorAll('.review-vital-input').forEach(input => {
                if (input.value) reviewData.vitals[input.dataset.key] = input.value;
            });

            // Medications
            document.querySelectorAll('.review-med-row').forEach(row => {
                const name = row.querySelector('.med-name').value;
                if (name) {
                    reviewData.medications.push({
                        drug_name: name,
                        dosage: row.querySelector('.med-dose').value,
                        frequency: row.querySelector('.med-freq').value,
                        route: row.querySelector('.med-route').value
                    });
                }
            });

            // Diagnoses
            document.querySelectorAll('.review-diag-row').forEach(row => {
                const code = row.querySelector('.diag-code').value;
                if (code) {
                    reviewData.diagnoses.push({
                        icd10_code: code,
                        description: row.querySelector('.diag-desc').value
                    });
                }
            });

            // Wounds
            document.querySelectorAll('.review-wound-row').forEach(row => {
                const loc = row.querySelector('.wound-loc').value;
                if (loc) {
                    reviewData.wounds.push({
                        location: loc,
                        type: row.querySelector('.wound-type').value,
                        length_cm: row.querySelector('.wound-l').value,
                        width_cm: row.querySelector('.wound-w').value,
                        depth_cm: row.querySelector('.wound-d').value,
                        drainage_type: row.querySelector('.wound-drainage').value,
                        pain_level: row.querySelector('.wound-pain').value
                    });
                }
            });
            
            // Show loading state
            finalizeVisitBtn.disabled = true;
            finalizeVisitBtn.innerHTML = '<i data-lucide="loader" class="w-5 h-5 mr-2 animate-spin"></i> Saving...';

            fetch('api/ai_companion.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'confirm_save',
                    appointment_id: window.visitContext.appointmentId,
                    patient_id: window.visitContext.patientId,
                    user_id: window.visitContext.userId,
                    review_data: reviewData
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    window.location.href = 'visit_notes.php?patient_id=' + window.visitContext.patientId + '&appointment_id=' + window.visitContext.appointmentId;
                } else {
                    alert('Error saving note: ' + data.message);
                    finalizeVisitBtn.disabled = false;
                    finalizeVisitBtn.innerHTML = '<i data-lucide="pen-tool" class="w-5 h-5 mr-2"></i> Sign Visit Note';
                }
            })
            .catch(err => {
                console.error(err);
                alert('Network error occurred.');
                finalizeVisitBtn.disabled = false;
                finalizeVisitBtn.innerHTML = '<i data-lucide="pen-tool" class="w-5 h-5 mr-2"></i> Sign Visit Note';
            });
        });
    }

    // --- Live Note Autosave Logic ---
    const liveNoteContent = document.getElementById('live-note-content');
    const autoSaveBadge = document.querySelector('.bg-green-100.text-green-800'); // The "Auto-Saving" badge
    let autoSaveTimeout;

    if (liveNoteContent) {
        liveNoteContent.addEventListener('input', () => {
            // Show "Saving..." state
            if (autoSaveBadge) {
                autoSaveBadge.textContent = 'Saving...';
                autoSaveBadge.classList.remove('bg-green-100', 'text-green-800');
                autoSaveBadge.classList.add('bg-yellow-100', 'text-yellow-800');
            }

            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                saveLiveNoteDraft();
            }, 1000); // Debounce for 1 second
        });
    }

    function saveLiveNoteDraft() {
        const content = liveNoteContent.innerHTML;
        
        fetch('api/ai_companion.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'save_draft',
                patient_id: window.visitContext.patientId,
                appointment_id: window.visitContext.appointmentId,
                user_id: window.visitContext.userId,
                draft_data: { live_note: content }
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (autoSaveBadge) {
                    autoSaveBadge.textContent = 'Saved';
                    autoSaveBadge.classList.remove('bg-yellow-100', 'text-yellow-800');
                    autoSaveBadge.classList.add('bg-green-100', 'text-green-800');
                    
                    // Revert to "Auto-Saving" after a moment
                    setTimeout(() => {
                        autoSaveBadge.textContent = 'Auto-Saving';
                    }, 2000);
                }
            } else {
                console.error('Autosave failed:', data.message);
                if (autoSaveBadge) {
                    autoSaveBadge.textContent = 'Error Saving';
                    autoSaveBadge.classList.add('bg-red-100', 'text-red-800');
                }
            }
        })
        .catch(err => {
            console.error('Autosave network error:', err);
        });
    }
    // Expose globally for other scripts
    window.saveDraft = saveLiveNoteDraft;

    // --- Help Modal Logic ---
    const helpModal = document.getElementById('help-modal');
    const helpBtn = document.getElementById('help-btn');
    const closeHelpBtn = document.getElementById('close-help-btn');
    const closeHelpFooterBtn = document.getElementById('close-help-footer-btn');

    function toggleHelpModal() {
        helpModal.classList.toggle('hidden');
    }

    if (helpBtn) helpBtn.addEventListener('click', toggleHelpModal);
    if (closeHelpBtn) closeHelpBtn.addEventListener('click', toggleHelpModal);
    if (closeHelpFooterBtn) closeHelpFooterBtn.addEventListener('click', toggleHelpModal);

    // --- Help Modal Tabs ---
    document.querySelectorAll('.help-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            // Update Buttons
            document.querySelectorAll('.help-tab-btn').forEach(b => {
                b.classList.remove('text-indigo-600', 'border-b-2', 'border-indigo-600');
                b.classList.add('text-gray-500');
            });
            btn.classList.remove('text-gray-500');
            btn.classList.add('text-indigo-600', 'border-b-2', 'border-indigo-600');

            // Update Content
            document.querySelectorAll('.help-tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById(btn.dataset.tab).classList.remove('hidden');
        });
    });

    // Helper to copy prompt text
    window.copyPrompt = function(element) {
        const text = element.querySelector('p').innerText.replace(/^"|"$/g, ''); // Remove quotes
        const input = document.getElementById('user-input');
        
        // Close modal
        toggleHelpModal();
        
        // Insert into input
        if (input) {
            input.value = text;
            input.focus();
            // Optional: Highlight effect on input
            input.classList.add('ring-2', 'ring-indigo-500');
            setTimeout(() => input.classList.remove('ring-2', 'ring-indigo-500'), 1000);
        }
    };

    // --- Annotation Logic ---
    const annotationModal = document.getElementById('annotation-modal');
    const cameraBtn = document.getElementById('camera-btn');
    const closeAnnotationBtn = document.getElementById('close-annotation-btn');
    const cancelAnnotationBtn = document.getElementById('cancel-annotation-btn');
    const saveAnnotationBtn = document.getElementById('save-annotation-btn');
    const fileInput = document.getElementById('file-input');
    const triggerUploadBtn = document.getElementById('trigger-upload-btn');
    const uploadPrompt = document.getElementById('upload-prompt');
    const canvas = document.getElementById('annotation-canvas');
    const ctx = canvas.getContext('2d');
    
    // Tools
    const toolUndo = document.getElementById('tool-undo');
    const toolClear = document.getElementById('tool-clear');
    const colorBtns = document.querySelectorAll('.color-btn');

    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;
    let drawColor = '#ef4444';
    let drawHistory = [];
    let baseImage = new Image();

    // Open Modal
    cameraBtn.addEventListener('click', () => {
        annotationModal.classList.remove('hidden');
        // Reset state if needed, or keep previous? Let's reset for now.
        if (!baseImage.src) {
            resetCanvas();
        }
    });

    // Close Modal
    const closeModal = () => annotationModal.classList.add('hidden');
    closeAnnotationBtn.addEventListener('click', closeModal);
    cancelAnnotationBtn.addEventListener('click', closeModal);

    // File Upload
    triggerUploadBtn.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (evt) => loadImage(evt.target.result);
            reader.readAsDataURL(file);
        }
    });

    function loadImage(src) {
        baseImage = new Image();
        baseImage.onload = () => {
            // Fit to canvas area (max 800x600 for now or dynamic)
            // We'll set canvas size to image size but limit max dimension
            const MAX_DIM = 1200;
            let w = baseImage.width;
            let h = baseImage.height;
            
            if (w > MAX_DIM || h > MAX_DIM) {
                if (w > h) { h *= MAX_DIM/w; w = MAX_DIM; }
                else { w *= MAX_DIM/h; h = MAX_DIM; }
            }
            
            canvas.width = w;
            canvas.height = h;
            ctx.drawImage(baseImage, 0, 0, w, h);
            
            uploadPrompt.classList.add('hidden');
            saveAnnotationBtn.disabled = false;
            saveHistory();
        };
        baseImage.src = src;
    }

    // --- Copy & Print Logic ---
    const copyBtn = document.getElementById('copy-note-btn');
    const printBtn = document.getElementById('print-note-btn');
    const liveNote = document.getElementById('live-note-content');

    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            const text = liveNote.innerText;
            navigator.clipboard.writeText(text).then(() => {
                // Visual feedback
                const originalIcon = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i data-lucide="check" class="w-4 h-4 text-green-600"></i>';
                lucide.createIcons();
                setTimeout(() => {
                    copyBtn.innerHTML = originalIcon;
                    lucide.createIcons();
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy: ', err);
                alert('Failed to copy text.');
            });
        });
    }

    if (printBtn) {
        printBtn.addEventListener('click', () => {
            const content = liveNote.innerHTML;
            const printWindow = window.open('', '', 'height=800,width=1000');
            const d = window.printData;
            
            printWindow.document.write('<html><head><title>Print Note</title>');
            printWindow.document.write('<script src="https://cdn.tailwindcss.com"><\/script>');
            printWindow.document.write(`
                <style>
                    @media print {
                        body { -webkit-print-color-adjust: exact; }
                        @page { margin: 0.5in; }
                    }
                    .print-header-col { flex: 1; }
                    .vitals-table th, .vitals-table td { border: 1px solid #e5e7eb; padding: 4px 8px; text-align: left; font-size: 0.875rem; }
                    .vitals-table th { background-color: #f9fafb; font-weight: 600; }
                </style>
            `);
            printWindow.document.write('</head><body class="bg-white text-gray-900 text-sm font-sans p-8">');
            
            // 1. Header Section (3 Columns)
            printWindow.document.write(`
                <div class="flex justify-between border-b-2 border-gray-800 pb-4 mb-6 gap-4">
                    <div class="print-header-col">
                        <h3 class="font-bold text-lg mb-1">Patient</h3>
                        <p><span class="font-semibold">Name:</span> ${d.patient.name}</p>
                        <p><span class="font-semibold">DOB:</span> ${d.patient.dob} (${d.patient.age})</p>
                        <p><span class="font-semibold">Gender:</span> ${d.patient.gender}</p>
                        <p><span class="font-semibold">PRN:</span> ${d.patient.prn}</p>
                    </div>
                    <div class="print-header-col">
                        <h3 class="font-bold text-lg mb-1">Facility</h3>
                        <p>Empower Care</p>
                        <p>123 Health Way</p>
                        <p>Wellness City, ST 12345</p>
                        <p>Phone: (555) 123-4567</p>
                    </div>
                    <div class="print-header-col">
                        <h3 class="font-bold text-lg mb-1">Encounter</h3>
                        <p><span class="font-semibold">Date:</span> ${d.encounter.date}</p>
                        <p><span class="font-semibold">Clinician:</span> ${d.encounter.clinician}</p>
                        <p><span class="font-semibold">Type:</span> ${d.encounter.type}</p>
                    </div>
                </div>
            `);

            // 2. Vitals Section
            if (d.vitals) {
                const v = d.vitals;
                printWindow.document.write(`
                    <div class="mb-6">
                        <h3 class="font-bold text-base mb-2 uppercase border-b border-gray-300 pb-1">Vitals for this encounter</h3>
                        <table class="w-full vitals-table border-collapse">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>BP</th>
                                    <th>HR</th>
                                    <th>RR</th>
                                    <th>Temp</th>
                                    <th>SpO2</th>
                                    <th>Height</th>
                                    <th>Weight</th>
                                    <th>BMI</th>
                                    <th>Pain</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>${d.encounter.date}</td>
                                    <td>${v.blood_pressure || '-'}</td>
                                    <td>${v.heart_rate || '-'}</td>
                                    <td>${v.respiratory_rate || '-'}</td>
                                    <td>${v.temperature_f ? v.temperature_f + ' F' : '-'}</td>
                                    <td>${v.oxygen_saturation ? v.oxygen_saturation + '%' : '-'}</td>
                                    <td>${v.height_in ? v.height_in + ' in' : '-'}</td>
                                    <td>${v.weight_lbs ? v.weight_lbs + ' lbs' : '-'}</td>
                                    <td>${v.bmi || '-'}</td>
                                    <td>${v.pain_level || '-'}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                `);
            }

            // 3. Allergies Section
            printWindow.document.write(`
                <div class="mb-6">
                    <h3 class="font-bold text-base mb-2 uppercase border-b border-gray-300 pb-1">Drug Allergies</h3>
                    <p class="text-sm">${d.patient.allergies}</p>
                </div>
            `);

            // 4. PMH Section
            printWindow.document.write(`
                <div class="mb-6">
                    <h3 class="font-bold text-base mb-2 uppercase border-b border-gray-300 pb-1">Past Medical History</h3>
                    <p class="text-sm">${d.patient.pmh}</p>
                </div>
            `);

            // 5. Note Content
            printWindow.document.write(`
                <div class="note-content prose prose-sm max-w-none">
                    <style>
                        .note-content h2 { 
                            font-size: 1rem; 
                            font-weight: 700; 
                            text-transform: uppercase; 
                            border-bottom: 1px solid #d1d5db; 
                            padding-bottom: 0.25rem; 
                            margin-top: 1.5rem; 
                            margin-bottom: 0.5rem;
                            color: #111827;
                        }
                        .note-content p { margin-bottom: 0.5rem; }
                        .note-content ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 0.5rem; }
                    </style>
                    ${content}
                </div>
            `);
            
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            
            printWindow.onload = function() {
                printWindow.focus();
                printWindow.print();
            };
        });
    }

    function resetCanvas() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        uploadPrompt.classList.remove('hidden');
        saveAnnotationBtn.disabled = true;
        baseImage = new Image();
        drawHistory = [];
        fileInput.value = '';
    }

    // Drawing Logic
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);
    
    // Touch support
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        lastX = (touch.clientX - rect.left) * (canvas.width / rect.width);
        lastY = (touch.clientY - rect.top) * (canvas.height / rect.height);
        isDrawing = true;
    });
    canvas.addEventListener('touchmove', (e) => {
        e.preventDefault();
        if (!isDrawing) return;
        const touch = e.touches[0];
        const rect = canvas.getBoundingClientRect();
        const x = (touch.clientX - rect.left) * (canvas.width / rect.width);
        const y = (touch.clientY - rect.top) * (canvas.height / rect.height);
        drawPath(x, y);
        lastX = x;
        lastY = y;
    });
    canvas.addEventListener('touchend', stopDrawing);

    function startDrawing(e) {
        isDrawing = true;
        const rect = canvas.getBoundingClientRect();
        lastX = (e.clientX - rect.left) * (canvas.width / rect.width);
        lastY = (e.clientY - rect.top) * (canvas.height / rect.height);
    }

    function draw(e) {
        if (!isDrawing) return;
        const rect = canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) * (canvas.width / rect.width);
        const y = (e.clientY - rect.top) * (canvas.height / rect.height);
        drawPath(x, y);
        lastX = x;
        lastY = y;
    }

    function drawPath(x, y) {
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(x, y);
        ctx.strokeStyle = drawColor;
        ctx.lineWidth = 4;
        ctx.lineCap = 'round';
        ctx.stroke();
    }

    function stopDrawing() {
        if (isDrawing) {
            isDrawing = false;
            saveHistory();
        }
    }

    // History / Undo
    function saveHistory() {
        if (drawHistory.length > 10) drawHistory.shift();
        drawHistory.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
    }

    toolUndo.addEventListener('click', () => {
        if (drawHistory.length > 1) {
            drawHistory.pop(); // Remove current state
            const previousState = drawHistory[drawHistory.length - 1];
            ctx.putImageData(previousState, 0, 0);
        } else if (drawHistory.length === 1) {
            // Initial state (just image)
            ctx.putImageData(drawHistory[0], 0, 0);
        }
    });

    toolClear.addEventListener('click', () => {
        if (baseImage.src) {
            ctx.drawImage(baseImage, 0, 0, canvas.width, canvas.height);
            saveHistory();
        }
    });

    // Colors
    colorBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            colorBtns.forEach(b => b.classList.remove('ring-2', 'ring-offset-1', 'ring-gray-400'));
            btn.classList.add('ring-2', 'ring-offset-1', 'ring-gray-400');
            drawColor = btn.dataset.color;
        });
    });

    // Send to Chat
    saveAnnotationBtn.addEventListener('click', () => {
        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
        
        // Inject into Chat UI
        const chatContainer = document.getElementById('ai-chat-container');
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-end justify-end';
        msgDiv.innerHTML = `
            <div class="bg-indigo-600 text-white p-2 rounded-lg rounded-br-none shadow-md max-w-[85%]">
                <img src="${dataUrl}" class="rounded mb-2 max-h-60 w-auto border border-indigo-500">
                <p class="text-xs opacity-90">Attached annotated photo</p>
            </div>
            <div class="bg-gray-200 p-2 rounded-full ml-2 flex-shrink-0">
                <i data-lucide="user" class="w-5 h-5 text-gray-600"></i>
            </div>
        `;
        chatContainer.appendChild(msgDiv);
        chatContainer.scrollTop = chatContainer.scrollHeight;
        
        // Add to Live Note
        const liveNote = document.getElementById('live-note-content');
        if (liveNote) {
            const imgHTML = `<br><img src="${dataUrl}" class="max-w-full h-auto my-2 rounded shadow-sm"><br>`;
            liveNote.insertAdjacentHTML('beforeend', imgHTML);
            // Trigger autosave if applicable
            liveNote.dispatchEvent(new Event('input', { bubbles: true }));
            
            // Force immediate save to ensure image is persisted before any AI refresh
            if (typeof saveLiveNoteDraft === 'function') {
                saveLiveNoteDraft();
            }
        }

        // Close Modal
        closeModal();
        resetCanvas();

        // TODO: Send to Backend via SmartCommandParser or direct fetch
        // For now, we'll dispatch a custom event that SmartCommandParser can listen to, 
        // or we can manually trigger the send logic if we had access to the instance.
        // Since SmartCommandParser is global, we might need to expose a method or handle it here.
        
        // Let's try to send it as a "command" with image data attached
        // We can use the existing input field to simulate a send
        const input = document.getElementById('user-input');
        // We need a way to pass the image data. 
        // We'll attach it to the window object temporarily or use a hidden input.
        window.pendingImageAttachment = dataUrl;
        window.pendingImageType = document.getElementById('annotate-image-type').value;
        window.pendingWoundId = document.getElementById('annotate-wound-id').value;
        
        // Trigger the send button click to process it
        document.getElementById('send-btn').click();
    });

    // --- Live Note Toolbar Logic ---
    document.querySelectorAll('#live-note-toolbar button').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const command = btn.dataset.command;
            document.execCommand(command, false, null);
            // Keep focus on the editor
            document.getElementById('live-note-content').focus();
        });
    });

    // --- Generate SOAP Button Logic ---
    // --- Generate SOAP Button Logic (Removed) ---
    // The reconstruction feature has been disabled as per user request.
    /*
    const generateSoapBtn = document.getElementById('generate-soap-btn');
    if (generateSoapBtn) {
        generateSoapBtn.addEventListener('click', function() {
            // ... Logic Removed ...
        });
    }
    */

    // Mobile View Switcher
    window.switchMobileView = function(view) {
        const chatPanel = document.getElementById('panel-chat');
        const notePanel = document.getElementById('panel-note');
        const tabChat = document.getElementById('mobile-tab-chat');
        const tabNote = document.getElementById('mobile-tab-note');

        if (view === 'chat') {
            chatPanel.classList.remove('hidden');
            chatPanel.classList.add('flex');
            notePanel.classList.add('hidden');
            notePanel.classList.remove('flex');
            
            tabChat.classList.add('text-indigo-600', 'border-indigo-600');
            tabChat.classList.remove('text-gray-500', 'border-transparent');
            tabNote.classList.remove('text-indigo-600', 'border-indigo-600');
            tabNote.classList.add('text-gray-500', 'border-transparent');
        } else {
            chatPanel.classList.add('hidden');
            chatPanel.classList.remove('flex');
            notePanel.classList.remove('hidden');
            notePanel.classList.add('flex');

            tabNote.classList.add('text-indigo-600', 'border-indigo-600');
            tabNote.classList.remove('text-gray-500', 'border-transparent');
            tabChat.classList.remove('text-indigo-600', 'border-indigo-600');
            tabChat.classList.add('text-gray-500', 'border-transparent');
        }
    };

    // Auto-resize Chat Input
    const chatInput = document.getElementById('user-input');
    if (chatInput) {
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto'; // shrink
            this.style.height = (this.scrollHeight) + 'px'; // grow
            if (this.value === '') this.style.height = '44px';
        });
    }

</script>

<!-- Load the Smart Command Logic (Updated for Headless Mode) -->
<script src="js/smart_command_logic.js?v=<?php echo time(); ?>"></script>

<?php require_once 'templates/footer.php'; ?>
