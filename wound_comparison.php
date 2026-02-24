<?php
// Filename: ec/wound_comparison.php
// Purpose: Allows multi-step selection (Patient -> Wound -> Visits) for comparison.

require_once 'templates/header.php';
require_once 'db_connect.php'; // Needed to fetch initial list of patient's wounds

// 1. Determine Patient ID (from 'id' in URL)
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
// We no longer rely on wound_id being preset in PHP, but keep it for persistent links
$initial_wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;

if ($patient_id <= 0) {
    echo "<div class='p-8 text-center text-red-600 font-bold'>Invalid Patient ID provided.</div>";
    require_once 'templates/footer.php';
    exit();
}

// Fetch all wounds for the patient for the initial dropdown rendering
$all_wounds = [];
try {
    $sql_all_wounds = "SELECT wound_id, location, wound_type 
                       FROM wounds 
                       WHERE patient_id = ? AND status = 'Active'
                       ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql_all_wounds);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_wounds = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // Fail silently in PHP, JS will show error message
}

?>

    <!-- Include Lucide Icons and FontAwesome for UI consistency -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <style>
        /* Fixed Height Utility */
        .h-full-constrained {
            min-height: calc(100vh - 124px);
            max-height: calc(100vh - 124px);
        }
        /* Comparison List Styling */
        .assessment-list-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: background-color 0.15s, border-right 0.15s;
            border-right: 4px solid transparent;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .assessment-list-item:hover { background-color: #f3f4f6; }
        .assessment-list-item.selected-1 {
            background-color: #eef2ff; border-right-color: #4f46e5; font-weight: 600;
        }
        .assessment-list-item.selected-2 {
            background-color: #fef2f4; border-right-color: #dc2626; font-weight: 600;
        }

        /* Comparison Viewer Styles */
        .comparison-container {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            margin: 0 auto;
            cursor: ew-resize;
            user-select: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            border-radius: 0.5rem;
            overflow: hidden;
        }
        .comparison-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            pointer-events: none;
        }
        #image-after {
            clip-path: inset(0 0 0 50%);
        }
        #comparison-handle {
            position: absolute;
            top: 0;
            left: 50%;
            width: 4px;
            height: 100%;
            background-color: white;
            transform: translateX(-50%);
            z-index: 10;
        }
        #comparison-handle::before {
            content: '\2194';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 32px; height: 32px;
            background-color: white;
            border-radius: 50%;
            border: 3px solid #3B82F6;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.4);
            color: #3B82F6;
            font-size: 1rem;
            font-weight: bold;
        }
    </style>

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 id="page-title" class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="scan-line" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Wound Comparison
                    </h1>
                    <p id="wound-info" class="text-sm text-gray-500 mt-1 ml-10">Patient ID: <?php echo $patient_id; ?> | Select a wound below.</p>
                </div>
                <a href="patient_profile.php?id=<?php echo $patient_id; ?>" class="text-sm text-gray-600 hover:text-gray-800 font-medium flex items-center">
                    <i data-lucide="arrow-left" class="w-4 h-4 mr-1"></i> Back to Patient
                </a>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-y-auto bg-gray-50 p-6 sm:p-8">
                <div class="space-y-6 h-full">

                    <!-- NEW WOUND SELECTOR ROW (Step 1) -->
                    <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4">
                        <label for="wound-select-dropdown" class="block text-sm font-medium text-gray-700 mb-2">
                            1. Select Wound to Compare
                        </label>
                        <select id="wound-select-dropdown" class="block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="0">-- Choose a Wound --</option>
                            <?php foreach ($all_wounds as $wound): ?>
                                <option
                                        value="<?php echo $wound['wound_id']; ?>"
                                        data-location="<?php echo htmlspecialchars($wound['location']); ?>"
                                        data-type="<?php echo htmlspecialchars($wound['wound_type']); ?>"
                                    <?php echo ($wound['wound_id'] == $initial_wound_id) ? 'selected' : ''; ?>
                                >
                                    <?php echo htmlspecialchars($wound['location'] . ' (' . $wound['wound_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($all_wounds)): ?>
                            <p class="text-sm text-red-500 mt-2">No active wounds found for this patient.</p>
                        <?php endif; ?>
                    </div>

                    <!-- MAIN COMPARISON GRID -->
                    <!-- Initially hidden until a wound is selected -->
                    <div id="comparison-grid" class="grid grid-cols-1 lg:grid-cols-12 gap-8 h-full hidden">

                        <!-- COLUMN 1: Assessment Selection List (lg:col-span-3 - Step 2) -->
                        <div class="lg:col-span-3">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col h-full h-full-constrained">
                                <h4 class="text-lg font-semibold text-gray-800 p-4 border-b sticky top-0 bg-white z-10 rounded-t-xl">
                                    2. Select Comparison Visits
                                </h4>
                                <div class="p-3 text-sm text-gray-600 border-b">
                                    <p class="text-xs">Select two visits/appointments below.</p>
                                </div>
                                <div id="assessment-timeline" class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-[100px]">
                                    <div class="p-4 text-center text-gray-500">
                                        Awaiting wound selection...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMN 2: Viewer & Data Comparison (lg:col-span-9 - Step 3) -->
                        <div class="lg:col-span-9 space-y-6 h-full-constrained overflow-y-auto">

                            <!-- Image Comparison Area -->
                            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
                                <h4 class="text-xl font-semibold text-gray-800 mb-4">3. Visual Progression Analysis</h4>

                                <div id="comparison-selector-message" class="bg-yellow-100 text-yellow-800 p-4 rounded-md mb-4 text-center">
                                    Select two distinct dates from the list to activate the comparison slider.
                                </div>

                                <div id="image-comparison-wrapper" class="comparison-container hidden">
                                    <img id="image-before" src="https://placehold.co/600x450/eef2ff/4f46e5?text=Visit+1" alt="Before Image" class="comparison-image">
                                    <img id="image-after" src="https://placehold.co/600x450/fef2f4/dc2626?text=Visit+2" alt="After Image" class="comparison-image">
                                    <div id="comparison-handle"></div>
                                </div>
                            </div>

                            <!-- Assessment Details Comparison -->
                            <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-100">
                                <h4 class="text-xl font-semibold text-gray-800 mb-4">4. Quantitative Data Comparison</h4>
                                <div id="comparison-data-table-container">
                                    <p class="text-gray-500 italic text-sm">Comparison data will appear here upon selection.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // FIX: Variables consolidated and initialized globally within the script scope
        const patientId = <?php echo $patient_id; ?>;

        let comparison = {
            woundId: <?php echo $initial_wound_id; ?>, // Currently selected Wound ID
            id1: null, // Older/Before Appointment ID
            id2: null, // Newer/After Appointment ID
            data: [] // Stores all assessment data grouped by Appointment ID
        };

        // DOM Elements (assigned on DOMContentLoaded, referenced globally by slider functions)
        const woundSelector = document.getElementById('wound-select-dropdown');
        const comparisonGrid = document.getElementById('comparison-grid');
        const timelineContainer = document.getElementById('assessment-timeline');
        const titleInfo = document.getElementById('wound-info');
        const messageBox = document.getElementById('comparison-selector-message');
        const imageWrapper = document.getElementById('image-comparison-wrapper');
        const imageBefore = document.getElementById('image-before');
        const imageAfter = document.getElementById('image-after');
        const dataTableContainer = document.getElementById('comparison-data-table-container');
        const handle = document.getElementById('comparison-handle');

        // Slider State Variables (Must be outside functions)
        let isDragging = false;

        const PLACEHOLDER_IMAGE = 'https://placehold.co/600x450/cccccc/333333?text=Image+Missing';

        // --- Data Grouping and Helpers ---

        function getAssessmentImage(assessment) {
            if (!assessment || !assessment.wound_images || assessment.wound_images.length === 0) {
                return PLACEHOLDER_IMAGE;
            }
            const postDebridement = assessment.wound_images.find(img => img.image_type === 'Post-Debridement');
            const preDebridement = assessment.wound_images.find(img => img.image_type === 'Pre-Debridement');
            return (postDebridement || preDebridement || assessment.wound_images[0]).image_path || PLACEHOLDER_IMAGE;
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function groupAssessmentsByAppointment(rawAssessments) {
            const grouped = {};

            rawAssessments.forEach(a => {
                const appId = a.appointment_id;

                if (!grouped[appId]) {
                    grouped[appId] = {
                        appointment_id: appId,
                        assessment_date: a.assessment_date,
                        latest_assessment: a,
                        images: a.wound_images || []
                    };
                } else {
                    // Keep the assessment with the latest date within the same appointment
                    if (new Date(a.assessment_date) > new Date(grouped[appId].assessment_date)) {
                        grouped[appId].assessment_date = a.assessment_date;
                        grouped[appId].latest_assessment = a;
                    }
                }
            });
            return Object.values(grouped);
        }

        // --- State Management ---

        window.selectWound = function(woundId) {
            // Reset state
            comparison.woundId = woundId;
            comparison.id1 = null;
            comparison.id2 = null;
            comparison.data = [];

            // Remove existing selections from UI
            document.querySelectorAll('.assessment-list-item').forEach(el => el.classList.remove('selected-1', 'selected-2'));
            imageWrapper.classList.add('hidden');
            messageBox.classList.remove('hidden');

            if (woundId == 0) {
                comparisonGrid.classList.add('hidden');
                titleInfo.textContent = `Patient ID: ${patientId} | Select a wound below.`;
                return;
            }

            const selectedOption = woundSelector.options[woundSelector.selectedIndex];
            const location = selectedOption.getAttribute('data-location');
            const type = selectedOption.getAttribute('data-type');

            titleInfo.textContent = `Patient ID: ${patientId} | Wound ID: ${woundId} - Location: ${location} (${type})`;
            comparisonGrid.classList.remove('hidden');

            fetchAssessmentHistory(woundId);
        };

        async function fetchAssessmentHistory(woundId) {
            timelineContainer.innerHTML = `<div class="p-4 text-center text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i> Fetching history for Wound ${woundId}...</div>`;

            try {
                const response = await fetch(`api/get_wound_comparison_data.php?wound_id=${woundId}`);
                if (!response.ok) throw new Error('Failed to fetch wound history.');

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.message || 'API error occurred.');
                }

                if (!result.assessments || result.assessments.length === 0) {
                    timelineContainer.innerHTML = '<p class="p-4 text-center text-gray-500">No assessments found for this wound.</p>';
                    return;
                }

                // Group and store the consolidated data
                comparison.data = groupAssessmentsByAppointment(result.assessments);

                renderTimeline(comparison.data);

            } catch (error) {
                console.error("Assessment History Fetch Error:", error);
                timelineContainer.innerHTML = `<p class="p-4 text-center text-red-500">Error loading history: ${error.message}</p>`;
            }
        }

        function renderTimeline(groupedAssessments) {
            // Sort by assessment date descending (most recent at top)
            groupedAssessments.sort((a, b) => new Date(b.assessment_date) - new Date(a.assessment_date));

            const html = groupedAssessments.map((entry) => {
                const date = formatDate(entry.assessment_date);
                const a = entry.latest_assessment;
                const size = (a.length_cm && a.width_cm) ? `${a.length_cm} x ${a.width_cm} cm` : 'No Size Recorded';

                return `
                <div id="assessment-item-${entry.appointment_id}"
                     class="assessment-list-item text-sm"
                     onclick="selectVisit(${entry.appointment_id})">

                    <div>
                        <p class="text-base font-medium">${date}</p>
                        <p class="text-xs text-gray-500">${size}</p>
                    </div>
                    <i data-lucide="chevron-right" class="w-4 h-4 text-gray-400"></i>
                </div>
            `;
            }).join('');

            timelineContainer.innerHTML = `<div class="divide-y divide-gray-100">${html}</div>`;
            lucide.createIcons();
        }

        // --- Selection and Comparison Logic ---

        window.selectVisit = function(appId) {
            const item = document.getElementById(`assessment-item-${appId}`);

            // Find which position to fill
            if (comparison.id1 === appId) {
                comparison.id1 = null;
                item.classList.remove('selected-1');
            } else if (comparison.id2 === appId) {
                comparison.id2 = null;
                item.classList.remove('selected-2');
            } else if (!comparison.id1) {
                comparison.id1 = appId;
                item.classList.add('selected-1');
            } else if (!comparison.id2) {
                comparison.id2 = appId;
                item.classList.add('selected-2');
            } else {
                // Both slots full: Find the oldest date among current selections to replace
                const data1 = comparison.data.find(e => e.appointment_id === comparison.id1);
                const data2 = comparison.data.find(e => e.appointment_id === comparison.id2);

                const date1 = new Date(data1.assessment_date).getTime();
                const date2 = new Date(data2.assessment_date).getTime();

                if (date1 < date2) {
                    // id1 is older. Replace id1.
                    document.getElementById(`assessment-item-${comparison.id1}`).classList.remove('selected-1');
                    comparison.id1 = appId;
                    item.classList.add('selected-1');
                } else {
                    // id2 is older/same. Replace id2.
                    document.getElementById(`assessment-item-${comparison.id2}`).classList.remove('selected-2');
                    comparison.id2 = appId;
                    item.classList.add('selected-2');
                }
            }

            // Final chronological check and class redraw
            if (comparison.id1 && comparison.id2) {
                const data1 = comparison.data.find(e => e.appointment_id === comparison.id1);
                const data2 = comparison.data.find(e => e.appointment_id === comparison.id2);

                // Determine chronological order: id1 must be older/before
                if (new Date(data1.assessment_date).getTime() > new Date(data2.assessment_date).getTime()) {
                    // Swap the IDs
                    [comparison.id1, comparison.id2] = [comparison.id2, comparison.id1];

                    // Redraw classes based on the new order
                    document.querySelectorAll('.assessment-list-item').forEach(el => {
                        el.classList.remove('selected-1', 'selected-2');
                    });
                    document.getElementById(`assessment-item-${comparison.id1}`).classList.add('selected-1');
                    document.getElementById(`assessment-item-${comparison.id2}`).classList.add('selected-2');
                }
            }

            updateComparisonView();
        }

        function updateComparisonView() {
            const id1 = comparison.id1;
            const id2 = comparison.id2;

            if (!id1 || !id2) {
                imageWrapper.classList.add('hidden');
                messageBox.classList.remove('hidden');
                dataTableContainer.innerHTML = '<p class="text-gray-500 italic text-sm">Comparison data will appear here upon selection.</p>';
                return;
            }

            messageBox.classList.add('hidden');
            imageWrapper.classList.remove('hidden');

            // Find the latest assessment objects within the selected appointments
            const entry1 = comparison.data.find(e => e.appointment_id === id1);
            const entry2 = comparison.data.find(e => e.appointment_id === id2);

            const data1 = entry1.latest_assessment;
            const data2 = entry2.latest_assessment;

            // --- 1. Update Images ---
            imageBefore.src = getAssessmentImage(data1);
            imageAfter.src = getAssessmentImage(data2);

            imageBefore.onerror = () => imageBefore.src = PLACEHOLDER_IMAGE;
            imageAfter.onerror = () => imageAfter.src = PLACEHOLDER_IMAGE;

            // --- 2. Update Data Table ---
            renderDataTable(data1, data2);

            // --- 3. Initialize/Reset Slider ---
            initializeSlider();
        }

        function renderDataTable(d1, d2) {
            // Function to safely extract data from an assessment object
            const getValue = (data, key, unit = '') => {
                const value = data[key];
                if (value === null || value === undefined || value === '') return '<span class="text-gray-400 italic">N/A</span>';
                return `${value}${unit}`;
            };

            const tableHtml = `
            <div class="overflow-x-auto rounded-lg border">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase w-2/6">Metric</th>
                            <th class="py-3 px-4 text-center text-xs font-medium text-indigo-700 uppercase w-2/6">Before (${formatDate(d1.assessment_date)})</th>
                            <th class="py-3 px-4 text-center text-xs font-medium text-red-700 uppercase w-2/6">After (${formatDate(d2.assessment_date)})</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${renderDataRow('Length', getValue(d1, 'length_cm', ' cm'), getValue(d2, 'length_cm', ' cm'))}
                        ${renderDataRow('Width', getValue(d1, 'width_cm', ' cm'), getValue(d2, 'width_cm', ' cm'))}
                        ${renderDataRow('Depth', getValue(d1, 'depth_cm', ' cm'), getValue(d2, 'depth_cm', ' cm'))}
                        ${renderDataRow('Area (Computed)', getValue(d1, 'computed_area_cm2', ' cm²'), getValue(d2, 'computed_area_cm2', ' cm²'), false, false, true)}
                        ${renderDataRow('Granulation %', getValue(d1, 'granulation_percent', '%'), getValue(d2, 'granulation_percent', '%'), true)}
                        ${renderDataRow('Slough %', getValue(d1, 'slough_percent', '%'), getValue(d2, 'slough_percent', '%'), false, false, false, true)}
                        ${renderDataRow('Exudate Type/Amt', `${getValue(d1, 'exudate_type')} / ${getValue(d1, 'exudate_amount')}`, `${getValue(d2, 'exudate_type')} / ${getValue(d2, 'exudate_amount')}`)}
                        ${renderDataRow('Odor Present', getValue(d1, 'odor_present'), getValue(d2, 'odor_present'), false, true)}
                        ${renderDataRow('Periwound', getValue(d1, 'periwound_condition'), getValue(d2, 'periwound_condition'))}
                    </tbody>
                </table>
            </div>
        `;
            dataTableContainer.innerHTML = tableHtml;
        }

        // Core function for highlighting data changes
        function renderDataRow(label, v1, v2, highlightIncreaseGood = false, highlightWarning = false, highlightDecreaseGood = false, reverseHighlight = false) {
            let v2Class = '';
            if (v1 !== 'N/A' && v2 !== 'N/A') {
                const val1 = parseFloat(String(v1).replace(/[^0-9.]/g, ''));
                const val2 = parseFloat(String(v2).replace(/[^0-9.]/g, ''));

                if (!isNaN(val1) && !isNaN(val2)) {

                    if (val2 > val1) { // Value Increased
                        v2Class = highlightIncreaseGood ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
                    } else if (val2 < val1) { // Value Decreased
                        v2Class = highlightDecreaseGood || reverseHighlight ? 'text-green-600 font-bold' : 'text-red-600 font-bold';
                    }
                }
            }
            // Non-numeric warning/highlight (e.g., Odor: Yes)
            if (highlightWarning && v2.includes('Yes')) v2Class = 'text-red-600 font-bold';

            return `
            <tr class="text-sm">
                <td class="py-3 px-4 font-semibold text-gray-800">${label}</td>
                <td class="py-3 px-4 text-center">${v1}</td>
                <td class="py-3 px-4 text-center ${v2Class}">${v2}</td>
            </tr>
        `;
        }


        // --- Slider Interaction Logic ---

        function initializeSlider() {
            const handle = document.getElementById('comparison-handle');
            const container = document.getElementById('image-comparison-wrapper');
            const imgAfter = document.getElementById('image-after');

            if (!container || !imgAfter || !handle) return;

            // Ensure listeners are only added once
            container.removeEventListener('mousedown', startDrag);
            container.removeEventListener('touchstart', startDragTouch);
            container.addEventListener('mousedown', startDrag);
            container.addEventListener('touchstart', startDragTouch);

            // Initial reset position to 50%
            updateSlider(container.offsetWidth / 2);
        }

        function startDrag(e) {
            e.preventDefault();
            isDragging = true;
            document.addEventListener('mousemove', duringDrag);
            document.addEventListener('mouseup', stopDrag);
        }

        function startDragTouch(e) {
            isDragging = true;
            document.addEventListener('touchmove', duringDragTouch);
            document.addEventListener('touchend', stopDrag);
        }

        function duringDrag(e) {
            if (!isDragging) return;
            const rect = container.getBoundingClientRect();
            let x = e.clientX - rect.left;
            updateSlider(x);
        }

        function duringDragTouch(e) {
            if (!isDragging || !e.touches || e.touches.length === 0) return;
            const rect = container.getBoundingClientRect();
            let x = e.touches[0].clientX - rect.left;
            updateSlider(x);
        }

        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', duringDrag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchmove', duringDragTouch);
            document.removeEventListener('touchend', stopDrag);
        }

        function updateSlider(x) {
            const width = container.offsetWidth;
            const handle = document.getElementById('comparison-handle');

            const boundedX = Math.max(0, Math.min(width, x));
            const percent = (boundedX / width) * 100;

            imgAfter.style.clipPath = `inset(0 0 0 ${boundedX}px)`;
            handle.style.left = `${percent}%`;
        }

        // --- Initialization ---

        // Initial load check if a wound was selected via URL
        if (comparison.woundId !== 0) {
            document.addEventListener('DOMContentLoaded', () => {
                // Set the dropdown value to trigger the initial load
                woundSelector.value = comparison.woundId;
                selectWound(comparison.woundId);
            });
        }

        // Attach listener to dropdown
        woundSelector.addEventListener('change', (e) => {
            selectWound(parseInt(e.target.value));
        });

        lucide.createIcons();
    </script>

<?php
require_once 'templates/footer.php';
?>