<?php
// Filename: ec/patient_portal/upload_photo.php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_connect.php';
$patient_id = $_SESSION['portal_patient_id'];
$patient_name = $_SESSION['portal_patient_name'];

// Fetch Active Wounds for Dropdown
$sql = "SELECT wound_id, location, wound_type FROM wounds WHERE patient_id = ? AND status = 'Active'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$wounds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Helper for Initials
// REMOVED: getInitials() definition is now in nav_panel.php
$active_page = 'upload_photo'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Photo | Patient Portal</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- 1. Navigation Panel (Mobile Header and Sidebar) -->
<?php require_once 'nav_panel.php'; ?>

<!-- 2. Main Page Wrapper -->
<div class="page-wrapper">
    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container max-w-screen-lg">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="camera" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Upload Wound Photo
                </h1>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Left Column: Form and Metrics (2/3 width on desktop) -->
                <div class="lg:col-span-2 space-y-6">

                    <div class="card-base p-6">
                        <p class="text-base text-gray-600 mb-6">
                            Submit a high-quality photo to help your care team monitor wound healing progress between visits.
                        </p>

                        <!-- Submission Message Box -->
                        <div id="upload-message" class="hidden p-3 mb-4 rounded-lg text-sm border"></div>

                        <!-- Removed enctype from form as submission is now handled via JS/Fetch -->
                        <form id="uploadForm">
                            <div class="space-y-6">

                                <div>
                                    <label for="wound_id" class="block text-sm font-medium text-gray-700 mb-1">Select Active Wound</label>
                                    <select name="wound_id" id="wound_id" class="form-input w-full bg-white" required>
                                        <option value="">-- Choose an active wound --</option>
                                        <?php
                                        $woundPhotoCounts = [];
                                        foreach ($wounds as $wound):
                                            // Placeholder for fetching photo count (will be done via JS/API on change, but needed for initial reference)
                                            $woundPhotoCounts[$wound['wound_id']] = 0;
                                            ?>
                                            <option value="<?php echo $wound['wound_id']; ?>" data-location="<?php echo htmlspecialchars($wound['location']); ?>">
                                                <?php echo htmlspecialchars($wound['location'] . ' (' . $wound['wound_type'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Enhanced File Drop Zone -->
                                <div class="file-drop-zone transition group" id="drop-zone">
                                    <!-- Input remains hidden, its change event triggers upload -->
                                    <input type="file" name="wound_photo" id="wound_photo" class="hidden" accept="image/png, image/jpeg" required>
                                    <label for="wound_photo" class="cursor-pointer flex flex-col items-center p-8">
                                        <div class="icon-box-large bg-indigo-50 group-hover:bg-indigo-100 text-brand mb-4 transition-colors">
                                            <i data-lucide="image-plus" class="w-8 h-8"></i>
                                        </div>

                                        <span class="text-base font-semibold text-gray-800">Tap to Take Photo or Select File to Upload</span>
                                        <span class="text-xs text-muted mt-1">Accepted formats: JPG/PNG. Max size: 5MB.</span>
                                    </label>
                                    <p id="file-name" class="text-sm text-gray-800 mt-2 font-semibold hidden truncate"></p>
                                </div>

                            </div>

                            <!-- REMOVED: Manual Upload Button -->
                            <!-- <div class="mt-8">
                                <button type="submit" class="btn-primary btn-base w-full flex items-center justify-center">
                                    <i data-lucide="upload-cloud" class="w-4 h-4 mr-2 inline-block"></i>
                                    Upload Photo
                                </button>
                            </div> -->

                        </form>
                    </div>

                    <!-- Photo Metric Card (Dynamic Content) -->
                    <div id="metric-card" class="card-upload-metric hidden">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-indigo-200 uppercase tracking-wide">
                                Total Patient Uploads for <span id="metric-location-name">Selected Wound</span>
                            </p>
                            <i data-lucide="monitor" class="w-6 h-6 text-indigo-200"></i>
                        </div>
                        <p class="text-4xl font-extrabold mt-2 text-white">
                            <span id="metric-photo-count">0</span>
                        </p>
                        <p class="text-xs text-indigo-200 mt-1">This helps track visual progress between visits.</p>
                    </div>
                </div>

                <!-- Right Column: Tips (1/3 width on desktop) -->
                <div class="lg:col-span-1">
                    <div class="card-base bg-blue-50 border border-blue-100 p-6 h-full">
                        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                            <i data-lucide="lightbulb" class="w-5 h-5 mr-2 text-indigo-600"></i>
                            Maximize Photo Quality
                        </h3>
                        <ul class="space-y-4 text-sm text-gray-700">
                            <li class="flex items-start gap-3">
                                <i data-lucide="sun" class="w-5 h-5 flex-shrink-0 text-green-600 mt-0.5"></i>
                                <div>
                                    <span class="font-semibold">Good Lighting:</span> Use indirect natural light. Avoid harsh shadows or flash if possible.
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="ruler" class="w-5 h-5 flex-shrink-0 text-green-600 mt-0.5"></i>
                                <div>
                                    <span class="font-semibold">Size Reference:</span> Always include a ruler or coin in the photo for scale comparison.
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <i data-lucide="crosshair" class="w-5 h-5 flex-shrink-0 text-green-600 mt-0.5"></i>
                                <div>
                                    <span class="font-semibold">Angle:</span> Shoot straight down (90-degree angle) and ensure the entire wound area is clearly in focus.
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();

    const fileInput = document.getElementById('wound_photo');
    const woundSelect = document.getElementById('wound_id');
    const fileNameDisplay = document.getElementById('file-name');
    const dropZone = document.getElementById('drop-zone');
    const uploadMessageBox = document.getElementById('upload-message');
    const form = document.getElementById('uploadForm');

    // Metric Card Elements
    const metricCard = document.getElementById('metric-card');
    const metricLocation = document.getElementById('metric-location-name');
    const metricCount = document.getElementById('metric-photo-count');

    // Placeholder data for demonstration (In a real app, this would be an API call)
    const MOCK_PHOTO_COUNTS = {
    // Mock data mapping wound_id to count
    <?php foreach ($wounds as $wound): ?>
    <?php echo $wound['wound_id']; ?>: Math.floor(Math.random() * 8) + 2,
    <?php endforeach; ?>
    };


    function displayUploadMessage(message, isSuccess = true) {
        uploadMessageBox.textContent = message;
        if (isSuccess) {
            uploadMessageBox.className = 'p-3 mb-4 rounded-lg text-sm bg-green-50 border-green-200 text-green-700 border';
        } else {
            uploadMessageBox.className = 'p-3 mb-4 rounded-lg text-sm bg-red-50 border-red-200 text-red-700 border';
        }
        uploadMessageBox.classList.remove('hidden');
    }

    // --- Metric Update Logic ---
    function updatePhotoMetric() {
        const selectedOption = woundSelect.options[woundSelect.selectedIndex];
        const woundId = selectedOption.value;
        const locationName = selectedOption.getAttribute('data-location') || 'Selected Wound';

        if (woundId) {
            const count = MOCK_PHOTO_COUNTS[woundId] || 0;

            metricLocation.textContent = locationName;
            metricCount.textContent = count;
            metricCard.classList.remove('hidden');
        } else {
            metricCard.classList.add('hidden');
        }
    }

    // --- Core Upload Logic ---
    async function handleAutoUpload() {
        // Ensure necessary fields are filled before submitting
        if (woundSelect.value === "") {
            displayUploadMessage("Please select the wound location before uploading the photo.", false);
            // Clear file input so user can try again
            fileInput.value = "";
            return;
        }

        // Prepare UI for upload feedback
        displayUploadMessage('<i data-lucide="loader-circle" class="w-4 h-4 mr-2 inline-block animate-spin"></i> Uploading photo...', false);
        lucide.createIcons();

        // Create form data (using the actual form element to easily capture all inputs)
        const formData = new FormData(form);

        // Disable interaction during upload
        woundSelect.disabled = true;
        fileInput.disabled = true;

        try {
            const res = await fetch('api/upload_wound_photo.php', {
                method: 'POST',
                body: formData
            });
            const result = await res.json();

            if (res.ok) {
                displayUploadMessage('Photo uploaded successfully! Redirecting...', true);

                // On success, update the metric and redirect
                updatePhotoMetric();
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2000);

            } else {
                displayUploadMessage('Error: ' + result.message, false);
            }
        } catch (error) {
            displayUploadMessage('Failed to connect to server for upload. Please try again.', false);
        } finally {
            // Re-enable interaction on failure, clear file input
            woundSelect.disabled = false;
            fileInput.disabled = false;
            fileInput.value = ""; // Clear file name display
            fileNameDisplay.classList.add('hidden');
            dropZone.classList.remove('file-selected');
        }
    }


    // --- Event Listeners ---
    woundSelect.addEventListener('change', updatePhotoMetric);

    // NEW: Trigger upload automatically when a file is selected
    fileInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
            fileNameDisplay.classList.remove('hidden');
            dropZone.classList.add('file-selected');

            // AUTOMATICALLY TRIGGER UPLOAD
            handleAutoUpload();
        } else {
            fileNameDisplay.classList.add('hidden');
            dropZone.classList.remove('file-selected');
        }
    });

    // Initial load check for metric card
    updatePhotoMetric();
</script>
</body>
</html>