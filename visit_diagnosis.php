<?php
// Filename: visit_diagnosis.php
// UPDATED: Fixed alignment of Patient History card to match Add Diagnosis card

session_start();
require_once __DIR__ . '/templates/header.php';
require_once __DIR__ . '/db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : (isset($_SESSION['ec_user_id']) ? intval($_SESSION['ec_user_id']) : 0);

// --- Fetch Patient Details for Header ---
$patient_name = "Loading...";
if ($patient_id > 0) {
    $p_sql = "SELECT first_name, last_name FROM patients WHERE patient_id = ?";
    $p_stmt = $conn->prepare($p_sql);
    $p_stmt->bind_param("i", $patient_id);
    $p_stmt->execute();
    $p_res = $p_stmt->get_result();
    if ($row = $p_res->fetch_assoc()) {
        $patient_name = $row['first_name'] . " " . $row['last_name'];
    }
    $p_stmt->close();
}

// --- Fetch Active Wounds for Dropdown ---
$wounds = [];
if ($patient_id > 0) {
    $w_sql = "SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ? AND status = 'Active' ORDER BY location ASC";
    $w_stmt = $conn->prepare($w_sql);
    $w_stmt->bind_param("i", $patient_id);
    $w_stmt->execute();
    $w_res = $w_stmt->get_result();
    while ($row = $w_res->fetch_assoc()) {
        $wounds[] = $row;
    }
    $w_stmt->close();
}

$previous_step_url = "visit_wounds.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";
$next_step_url = "visit_medications.php?appointment_id={$appointment_id}&patient_id={$patient_id}&user_id={$user_id}";

// --- CHECK VISIT STATUS ---
require_once 'visit_status_check.php';
?>

    <div class="flex h-screen bg-gray-100">
        <?php require_once __DIR__ . '/templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md flex-shrink-0">
                <div class="flex items-center min-w-0 flex-grow">
                    <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4 flex-shrink-0">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <div class="min-w-0 mr-4 flex-shrink">
                        <h1 class="text-xl font-bold text-gray-800 truncate">Visit Diagnosis: <?php echo htmlspecialchars($patient_name); ?></h1>
                        <p class="text-xs text-gray-600">Step 4 of 6</p>
                    </div>
                </div>
            </header>

            <div class="sticky top-0 z-30">
                <?php require_once __DIR__ . '/templates/visit_submenu.php'; ?>
            </div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">

                <!-- UPDATED: Added items-start to align columns at the top -->
                <div class="flex flex-col lg:flex-row gap-6 items-start">

                    <!-- LEFT COLUMN: Current Visit -->
                    <div class="w-full lg:w-2/3 space-y-6">

                        <!-- Add Diagnosis Card -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg font-semibold text-gray-800">Add New Diagnosis</h2>
                                <button id="btnAiSuggest" class="text-sm bg-purple-100 text-purple-700 hover:bg-purple-200 px-3 py-1.5 rounded-md font-medium flex items-center transition">
                                    <i data-lucide="sparkles" class="w-4 h-4 mr-2"></i> AI Suggestions
                                </button>
                            </div>

                            <!-- AI Suggestions Container (Hidden by default) -->
                            <div id="aiSuggestionsContainer" class="hidden mb-6 bg-purple-50 border border-purple-100 rounded-md p-4">
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-sm font-bold text-purple-800">Recommended Codes</h3>
                                    <button id="closeAiSuggestions" class="text-purple-400 hover:text-purple-600"><i data-lucide="x" class="w-4 h-4"></i></button>
                                </div>
                                <div id="aiLoading" class="text-center py-4 text-purple-600 text-sm hidden">
                                    <i data-lucide="loader-2" class="w-5 h-5 animate-spin mx-auto mb-2"></i>
                                    Analyzing HPI & Wounds...
                                </div>
                                <div id="aiResults" class="space-y-2"></div>
                            </div>

                            <div class="space-y-4">
                                <!-- Search Mode -->
                                <div id="searchModeContainer">
                                    <div class="relative">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Search ICD-10 Database</label>
                                        <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="search" class="h-5 w-5 text-gray-400"></i>
                                        </span>
                                            <input type="text" id="icdSearchInput" class="pl-10 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2.5" placeholder="Type code (e.g. E11) or description (e.g. Diabetes)...">

                                            <div id="searchResults" class="absolute z-50 mt-1 w-full bg-white shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm hidden"></div>
                                        </div>
                                    </div>
                                    <div class="text-right mt-1">
                                        <button type="button" id="toggleManualMode" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline">
                                            Can't find it? Enter manually
                                        </button>
                                    </div>
                                </div>

                                <!-- Manual Mode (Hidden) -->
                                <div id="manualModeContainer" class="hidden space-y-3 border border-gray-200 rounded-md p-3 bg-gray-50">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-sm font-medium text-gray-700 flex items-center">
                                            <i data-lucide="pen-tool" class="w-4 h-4 mr-2"></i> Manual Entry
                                        </h3>
                                        <button type="button" id="toggleSearchMode" class="text-xs text-indigo-600 hover:text-indigo-800 hover:underline">
                                            Back to Search
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div class="md:col-span-1">
                                            <label class="block text-xs font-medium text-gray-500 mb-1">ICD Code</label>
                                            <input type="text" id="manualCodeInput" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2" placeholder="e.g. X99.9">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                                            <input type="text" id="manualDescInput" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2" placeholder="Diagnosis description">
                                        </div>
                                    </div>
                                </div>

                                <!-- Selected Preview -->
                                <div id="selectedCodePreview" class="p-3 bg-indigo-50 text-indigo-800 rounded-md text-sm hidden flex justify-between items-center border border-indigo-100">
                                    <div>
                                        <span class="font-bold block" id="previewCode"></span>
                                        <span id="previewDesc"></span>
                                    </div>
                                    <button id="clearSelection" class="text-indigo-600 hover:text-indigo-900"><i data-lucide="x" class="w-4 h-4"></i></button>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Wound Link -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Link to Wound (Optional)</label>
                                        <select id="woundSelect" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2.5">
                                            <option value="">-- General Diagnosis --</option>
                                            <?php foreach ($wounds as $w): ?>
                                                <option value="<?php echo $w['wound_id']; ?>">
                                                    <?php echo htmlspecialchars($w['location'] . " (" . $w['wound_type'] . ")"); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <!-- Comments -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Comment / Note</label>
                                        <input type="text" id="diagnosisNote" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm p-2.5" placeholder="e.g. Monitor progression">
                                    </div>
                                </div>

                                <!-- Add Button -->
                                <button id="btnAddDiagnosis" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-md transition flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                    <i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add to Visit
                                </button>
                            </div>
                        </div>

                        <!-- Diagnoses Table -->
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <h3 class="text-lg font-medium text-gray-900">Current Visit Diagnoses</h3>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">Pri</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Code / Desc</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Link</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody id="diagnosisTableBody" class="bg-white divide-y divide-gray-200">
                                    <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto"></i></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- RIGHT COLUMN: Patient History -->
                    <!-- UPDATED: Removed sticky top-24, changed to just sticky top-0 or removed to align at top immediately -->
                    <div class="w-full lg:w-1/3">
                        <div class="bg-white rounded-lg shadow-md p-6"> <!-- Removed sticky class for basic alignment check -->
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="history" class="w-5 h-5 mr-2 text-gray-500"></i>
                                    Patient History
                                </h3>
                            </div>

                            <!-- Bulk Actions -->
                            <div id="historyActions" class="flex justify-between items-center mb-3 text-sm hidden">
                                <label class="flex items-center space-x-2 cursor-pointer">
                                    <input type="checkbox" id="selectAllHistory" class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-gray-600">Select All</span>
                                </label>
                                <button id="addSelectedHistoryBtn" class="text-indigo-600 font-medium hover:text-indigo-800 disabled:opacity-50" disabled>
                                    Add Selected
                                </button>
                            </div>

                            <p class="text-xs text-gray-500 mb-4">Diagnoses from previous visits.</p>

                            <div id="historyList" class="space-y-3 overflow-y-auto max-h-[600px] pr-1">
                                <div class="text-center text-gray-400 text-sm py-4">Loading history...</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Navigation -->
                <div class="mt-6 flex justify-between">
                    <a href="<?php echo $previous_step_url; ?>" class="bg-gray-200 text-gray-800 font-bold py-2 px-4 rounded-md hover:bg-gray-300 transition flex items-center">
                        &larr; Back: Wounds
                    </a>
                    <a href="<?php echo $next_step_url; ?>" class="bg-green-600 text-white font-bold py-2 px-4 rounded-md hover:bg-green-700 transition flex items-center">
                        Next: Meds &rarr;
                    </a>
                </div>

            </main>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
        <!-- Toasts will be injected here -->
    </div>

    <script>
        window.appointmentId = <?php echo $appointment_id; ?>;
        window.patientId = <?php echo $patient_id; ?>;
        window.userId = <?php echo $user_id; ?>;
    </script>

    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <script src="visit_diagnosis_logic.js"></script>

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
        const hideIds = ['btnAddDiagnosis', 'btnAiSuggest', 'addSelectedHistoryBtn'];
        hideIds.forEach(id => {
            const btn = document.getElementById(id);
            if(btn) btn.style.display = 'none';
        });
        
        // 4. Disable delete buttons in table (might be dynamic, so we use CSS or observer, but simple disable is good start)
        // Since table is dynamic, we might need a MutationObserver or just hide the action column via CSS
        const style = document.createElement('style');
        style.innerHTML = `
            .delete-diagnosis-btn, .move-up-btn, .move-down-btn { display: none !important; }
            #diagnosisTableBody button { display: none !important; }
        `;
        document.head.appendChild(style);
    });
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/templates/footer.php'; ?>