<?php
// ec/visit_notes_settings.php

// FIX: Explicitly start the session here to ensure $_SESSION variables are accessible.
session_start();

// Include necessary files (assuming DB connection are in db_connect.php)
require_once 'db_connect.php';
// Assuming session has started and user is authenticated
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. Use the new session variable for the user ID
$user_id = $_SESSION['ec_user_id'];

// 2. Use the new session variable to determine the user's role
// The Global Settings section is only editable if the user's role is 'Admin'
$is_admin = (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'Admin');

// --- Template Includes ---
require_once 'templates/header.php';
?>
<!-- Include Lucide Icons for UI enhancement (Required for the new header style) -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- START: NEW STICKY HEADER STYLE -->
        <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                    <i data-lucide="file-text" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Visit Note Settings Manager
                </h1>
                <p class="text-sm text-gray-500 mt-1">Manage global standards and personalized preferences</p>
            </div>
            <!-- No buttons needed here -->
        </header>
        <!-- END: NEW STICKY HEADER STYLE -->

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container-fluid p-0">
                <p class="mb-8 text-gray-600">
                    Configure the rules for all clinicians (Admin) and customize your personal auto-population defaults.
                </p>

                <div id="status-message" class="mt-3 text-center text-sm font-medium hidden"></div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <!-- ========================================================= -->
                    <!-- GLOBAL SETTINGS (ADMIN) -->
                    <!-- ========================================================= -->

                    <div class="bg-white shadow-xl rounded-xl p-6 border-t-4 border-indigo-600">
                        <h2 class="text-2xl font-bold text-indigo-700 mb-4 border-b pb-2">
                            Global Settings
                            <?php if ($is_admin): ?>
                                <span class="ml-2 text-sm px-2 py-0.5 bg-red-100 text-red-800 rounded-full">Admin Edit</span>
                            <?php else: ?>
                                <span class="ml-2 text-sm px-2 py-0.5 bg-green-100 text-green-800 rounded-full">Read Only</span>
                            <?php endif; ?>
                        </h2>

                        <form id="globalSettingsForm">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

                            <div class="space-y-4">
                                <!-- Default Template Setting -->
                                <div class="flex flex-col">
                                    <label for="default_template" class="font-semibold text-gray-700 mb-1">Default Note Template</label>
                                    <input type="text" id="default_template" name="default_template"
                                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 focus:ring-2 focus:border-2 w-full <?php echo $is_admin ? '' : 'bg-gray-50'; ?>"
                                           value="Comprehensive SOAP Note"
                                        <?php echo $is_admin ? '' : 'disabled'; ?>
                                           placeholder="e.g., SOAP Note, Consult Report">
                                    <p class="text-xs text-gray-500 mt-1">Sets the default note structure for all clinicians.</p>
                                </div>

                                <!-- Required Sections Setting -->
                                <div class="flex flex-col">
                                    <label for="required_sections" class="font-semibold text-gray-700 mb-1">Mandatory Note Sections</label>
                                    <input type="text" id="required_sections" name="required_sections"
                                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 focus:ring-2 focus:border-2 w-full <?php echo $is_admin ? '' : 'bg-gray-50'; ?>"
                                           value="Subjective, Objective, Assessment, Plan"
                                        <?php echo $is_admin ? '' : 'disabled'; ?>
                                           placeholder="Comma separated, e.g., HPI, ROS, Exam">
                                    <p class="text-xs text-gray-500 mt-1">Sections that cannot be hidden or deleted by clinicians.</p>
                                </div>

                                <!-- Max Note Length Setting -->
                                <div class="flex flex-col">
                                    <label for="max_length" class="font-semibold text-gray-700 mb-1">Maximum Note Length (characters)</label>
                                    <input type="number" id="max_length" name="max_length"
                                           class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500 focus:ring-2 focus:border-2 w-full <?php echo $is_admin ? '' : 'bg-gray-50'; ?>"
                                           value="5000"
                                        <?php echo $is_admin ? '' : 'disabled'; ?>
                                           min="1000">
                                    <p class="text-xs text-gray-500 mt-1">Limits the length of the final visit note.</p>
                                </div>

                                <!-- Clinical Suggestions Mandate Setting -->
                                <div class="flex items-start justify-between border-t pt-4">
                                    <div>
                                        <label for="mandate_clinical_suggestions" class="font-semibold text-lg text-gray-700 block">Mandate Clinical Suggestions</label>
                                        <p class="text-sm text-gray-500">Force the display of AI-driven/protocol-based clinical suggestions for all users.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                                        <input type="checkbox" id="mandate_clinical_suggestions" name="mandate_clinical_suggestions" value="1" class="sr-only peer"
                                            <?php echo $is_admin ? '' : 'disabled'; ?>>
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                    </label>
                                </div>

                            </div>

                            <?php if ($is_admin): ?>
                                <div class="mt-8">
                                    <button type="submit" class="w-full bg-red-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-red-700 transition duration-150 ease-in-out shadow-lg">
                                        <i data-lucide="shield-check" class="w-4 h-4 inline mr-2"></i>
                                        Save Global Settings (Admin)
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>


                    <!-- ========================================================= -->
                    <!-- CUSTOM SETTINGS (CLINICIAN) -->
                    <!-- ========================================================= -->

                    <div class="bg-white shadow-xl rounded-xl p-6 border-t-4 border-teal-600">
                        <h2 class="text-2xl font-bold text-teal-700 mb-4 border-b pb-2">
                            Custom Auto-Population Settings
                        </h2>
                        <p class="mb-4 text-sm text-gray-600">
                            Customize which visit data fields are automatically pulled into your personal note draft.
                        </p>

                        <form id="customSettingsForm">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

                            <div class="space-y-6">

                                <!-- Vitals Setting -->
                                <div class="flex items-start justify-between border-b pb-4">
                                    <div>
                                        <label for="auto_populate_vitals" class="font-semibold text-lg text-gray-700 block">Auto-Populate Vitals</label>
                                        <p class="text-sm text-gray-500">Automatically include the patient's recorded Vitals (BP, Temp, etc.) in the Objective section.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                                        <input type="checkbox" id="auto_populate_vitals" name="auto_populate_vitals" value="1" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                                    </label>
                                </div>

                                <!-- HPI Auto-Populate Setting (REMAINS) -->
                                <div class="flex items-start justify-between border-b pb-4">
                                    <div>
                                        <label for="auto_populate_hpi" class="font-semibold text-lg text-gray-700 block">Auto-Populate HPI Narrative</label>
                                        <p class="text-sm text-gray-500">Automatically include the generated narrative from the HPI module in the Subjective section.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                                        <input type="checkbox" id="auto_populate_hpi" name="auto_populate_hpi" value="1" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                                    </label>
                                </div>

                                <!-- Medications Setting -->
                                <div class="flex items-start justify-between border-b pb-4">
                                    <div>
                                        <label for="auto_populate_meds" class="font-semibold text-lg text-gray-700 block">Auto-Populate Medications</label>
                                        <p class="text-sm text-gray-500">Automatically include the complete active medication list in the Assessment/Plan section.</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer ml-4">
                                        <input type="checkbox" id="auto_populate_meds" name="auto_populate_meds" value="1" class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-teal-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                                    </label>
                                </div>

                                <!-- NEW: HPI Source Setting -->
                                <div class="flex flex-col">
                                    <label for="hpi_source" class="font-semibold text-gray-700 mb-1">HPI Data Source Preference</label>
                                    <select id="hpi_source" name="hpi_source"
                                            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-teal-500 focus:border-teal-500 focus:ring-2 focus:border-2 w-full">
                                        <option value="structured">Structured Form Data (e.g., Checkboxes/Sliders)</option>
                                        <option value="raw_text">Raw Clinical Text Input (e.g., Physician Dictation)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Selects which source of HPI data is preferred for auto-population.</p>
                                </div>

                            </div>

                            <div class="mt-8">
                                <button type="submit" class="w-full bg-indigo-600 text-white font-medium py-2 px-4 rounded-lg hover:bg-indigo-700 transition duration-150 ease-in-out shadow-lg">
                                    <i data-lucide="save" class="w-4 h-4 inline mr-2"></i>
                                    Save Personal Preferences (Clinician)
                                </button>
                            </div>
                        </form>
                    </div>

                </div> <!-- End of Grid -->
            </div>
        </main>
    </div>
</div>

<?php require_once 'templates/footer.php'; ?>

<!-- FIX 1: Add jQuery explicitly without integrity/crossorigin attributes to fix the "$ is not defined" error.
     This resolves the script dependencies. -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- NOTE: If you are still seeing integrity errors for Bootstrap CSS/JS,
     you must find those links in your 'templates/header.php' or 'templates/footer.php'
     and remove the 'integrity' and 'crossorigin' attributes from them. -->

<script>
    $(document).ready(function() {
        // Note: The $user_id PHP variable is now sourced from $_SESSION['ec_user_id']
        const userId = <?php echo json_encode($user_id); ?>;
        const $globalForm = $('#globalSettingsForm');
        const $customForm = $('#customSettingsForm');
        const $message = $('#status-message');

        function showMessage(text, isError = false) {
            // Updated styling and added icons for better feedback
            const icon = isError ? 'x-circle' : 'check-circle';
            const baseClasses = 'mt-3 text-center text-sm font-medium p-3 rounded-lg border flex items-center justify-center';
            const colorClasses = isError ?
                'text-red-600 bg-red-50 border-red-200' :
                'text-green-600 bg-green-50 border-green-200';

            $message.html(`<i data-lucide="${icon}" class="w-4 h-4 mr-2"></i> ${text}`)
                .removeClass('hidden text-green-600 text-red-600 bg-red-50 border-red-200 bg-green-50 border-green-200')
                .addClass(`${baseClasses} ${colorClasses}`)
                .show();

            // Re-initialize Lucide icons for the status message
            lucide.createIcons();

            setTimeout(() => $message.addClass('hidden'), 5000);
        }

        // --- 1. Fetch current settings on load ---

        // Function to fetch and populate custom settings
        function fetchCustomSettings() {
            $.ajax({
                url: 'api/manage_note_preferences.php',
                method: 'GET',
                data: { action: 'get', user_id: userId, type: 'custom' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        $('#auto_populate_vitals').prop('checked', data.auto_populate_vitals == 1);
                        $('#auto_populate_hpi').prop('checked', data.auto_populate_hpi == 1);
                        $('#auto_populate_meds').prop('checked', data.auto_populate_meds == 1);
                        $('#hpi_source').val(data.hpi_source || 'structured');
                    } else {
                        console.log('Using default custom settings or failed to load:', response.message);
                    }
                },
                error: function() {
                    console.error('Failed to communicate with the server to fetch custom settings.');
                }
            });
        }

        // Function to fetch and populate global settings
        function fetchGlobalSettings() {
            $.ajax({
                url: 'api/manage_global_settings.php',
                method: 'GET',
                data: { action: 'get', type: 'global' },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        const data = response.data;
                        $('#default_template').val(data.default_template || 'Comprehensive SOAP Note');
                        $('#required_sections').val(data.required_sections || 'Subjective, Objective, Assessment, Plan');
                        $('#max_length').val(data.max_length || 5000);
                        $('#mandate_clinical_suggestions').prop('checked', data.mandate_clinical_suggestions == 1);
                    } else {
                        console.log('Using default global settings or failed to load:', response.message);
                    }
                },
                error: function() {
                    console.error('Failed to communicate with the server to fetch global settings.');
                }
            });
        }

        // --- 2. Handle form submissions (Save settings) ---

        // Handler for Custom (Clinician) Settings
        $customForm.on('submit', function(e) {
            e.preventDefault();

            const formData = {
                action: 'save',
                user_id: userId,
                auto_populate_vitals: $('#auto_populate_vitals').is(':checked') ? 1 : 0,
                auto_populate_hpi: $('#auto_populate_hpi').is(':checked') ? 1 : 0,
                auto_populate_meds: $('#auto_populate_meds').is(':checked') ? 1 : 0,
                hpi_source: $('#hpi_source').val()
            };

            // Show saving message instantly
            showMessage('Saving personal preferences...', false);

            $.ajax({
                url: 'api/manage_note_preferences.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('Personal Preferences saved successfully!');
                    } else {
                        showMessage('Error saving preferences: ' + (response.message || 'Unknown error'), true);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Server error: Could not save personal preferences.', true);
                    console.error('AJAX Error:', status, error);
                }
            });
        });

        // Handler for Global (Admin) Settings - Only available if $is_admin is true in PHP
        $globalForm.on('submit', function(e) {
            e.preventDefault();

            const formData = {
                action: 'save',
                default_template: $('#default_template').val(),
                required_sections: $('#required_sections').val(),
                max_length: $('#max_length').val(),
                mandate_clinical_suggestions: $('#mandate_clinical_suggestions').is(':checked') ? 1 : 0
            };

            // Show saving message instantly
            showMessage('Saving global settings...', false);

            $.ajax({
                url: 'api/manage_global_settings.php',
                method: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        showMessage('Global Settings saved successfully!');
                    } else {
                        showMessage('Error saving global settings: ' + (response.message || 'Unknown error'), true);
                    }
                },
                error: function(xhr, status, error) {
                    showMessage('Server error: Could not save global settings.', true);
                    console.error('AJAX Error:', status, error);
                }
            });
        });

        // Load data on page ready
        fetchCustomSettings();
        fetchGlobalSettings();

        // Initialize Lucide Icons after the page content is loaded
        lucide.createIcons();
    });
</script>