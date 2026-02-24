<?php
// ec/global_settings.php
// Dedicated page for managing global, high-level application settings (Timezone and other defaults).

// --- 1. PHP SETUP: Session, Database Connection, and Security ---
session_start();
// *** SECURITY CHECK: ENSURE ONLY ADMINS CAN ACCESS ***
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

require_once('db_connect.php');

/**
 * Returns an array of all available PHP timezone identifiers.
 * @return array
 */
function getTimezoneList() {
    return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
}

// Fetch the current global timezone setting for pre-selection in the form.
// NOTE: $app_timezone is already set in db_connect.php, but we fetch it again
// explicitly here for the form's selected option to be certain of the latest value.
$current_timezone = 'UTC';
if ($conn->connect_error === null) {
    try {
        $result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'app_timezone'");
        if ($result && $result->num_rows > 0) {
            $current_timezone = $result->fetch_assoc()['setting_value'];
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Timezone fetch failed: " . $e->getMessage());
    }
}
$timezones = getTimezoneList();

// Get Google Maps API Key for read-only display
$google_maps_key = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : 'KEY_NOT_SET';

// --- Include Header (Opens HTML/Body tags) ---
require_once('templates/header.php');
?>

    <style>
        /* Custom style for consistency */
        .form-input-custom {
            padding: 0.75rem 1rem;
            border: 1px solid #D1D5DB;
            border-radius: 0.5rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            background-color: white;
        }
        .form-input-custom:focus {
            outline: none;
            box-shadow: 0 0 0 2px #3B82F6; /* Focus ring color */
            border-color: #3B82F6;
        }

        /* Custom floating message box to replace the old Bootstrap/Alert style */
        #page-message-alert {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 50;
            min-width: 300px;
            max-width: 90%;
        }
    </style>

    <!-- START: Main Application Layout Wrapper (FLEX WRAPPER) -->
    <div class="flex h-screen bg-gray-100">
        <?php require_once('templates/sidebar.php'); ?>

        <!-- Main Content Area: Starts the flex layout inside the body from header.php -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4">
                            <i data-lucide="menu" class="w-6 h-6"></i>
                        </button>
                        <i data-lucide="settings" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Global Settings
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Manage application-wide configuration.</p>
                </div>
                <!-- No buttons needed here -->
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div id="page-message-alert" class="hidden p-4 rounded-lg shadow-lg"></div>

                <!-- Centralized Container -->
                <div class="max-w-3xl mx-auto space-y-6">

                    <!-- Global Timezone Card -->
                    <div class="bg-white rounded-xl shadow-2xl p-6 border-t-4 border-blue-600">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-3 flex items-center">
                            <i data-lucide="clock" class="w-5 h-5 mr-2 text-blue-600"></i>
                            Application Timezone Configuration
                        </h3>

                        <form id="timezone-form" class="space-y-4">
                            <input type="hidden" name="setting_name" value="app_timezone">
                            <div class="mb-4">
                                <label for="app_timezone" class="form-label font-semibold">Select Application Timezone</label>
                                <select id="app_timezone" name="app_timezone" required class="form-input-custom w-full">
                                    <option value="">-- Select a Timezone --</option>
                                    <?php foreach ($timezones as $tz): ?>
                                        <option value="<?php echo htmlspecialchars($tz); ?>"
                                            <?php if ($tz === $current_timezone) echo 'selected'; ?>>
                                            <?php echo htmlspecialchars($tz); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-sm text-gray-700 mt-3 p-3 border border-dashed rounded bg-gray-50">
                                    <i data-lucide="info" class="w-4 h-4 inline mr-1 text-blue-500"></i>
                                    The **Current Timezone** is:
                                    <strong id="current-tz-display" class="text-red-600"><?php echo htmlspecialchars($current_timezone); ?></strong>.
                                    This setting affects all date/time stamping and scheduling across the system.
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="submit" id="saveTimezoneBtn" class="bg-blue-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-blue-700 transition shadow-md flex items-center">
                                    <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Timezone
                                </button>
                            </div>
                        </form>
                        <div id="timezone-message" class="mt-4 text-sm"></div>
                    </div>

                    <!-- Other Global Settings (New functional card) -->
                    <div class="bg-white rounded-xl shadow-2xl p-6 border-t-4 border-gray-400">
                        <h3 class="text-xl font-bold text-gray-800 mb-4 border-b pb-3 flex items-center">
                            <i data-lucide="settings-2" class="w-5 h-5 mr-2 text-gray-600"></i>
                            Application Defaults & Integrations
                        </h3>
                        <form id="other-settings-form" class="space-y-4">
                            <!-- Default Language -->
                            <div class="mb-4">
                                <label for="app_language" class="form-label font-semibold">Default Language</label>
                                <select id="app_language" name="app_language" class="form-input-custom w-full bg-white">
                                    <option value="en" selected>English (US)</option>
                                    <option value="es">Spanish</option>
                                    <option value="fr">French</option>
                                </select>
                                <p class="text-xs text-gray-500 mt-1">This sets the default language for the user interface.</p>
                            </div>

                            <!-- Google Maps API Key Display (Read-only) -->
                            <div class="mb-4 pt-4 border-t">
                                <label for="maps_api_key" class="form-label font-semibold">Google Maps API Key</label>
                                <!-- PHP constant for the key is used here -->
                                <input type="password" id="maps_api_key" value="<?php echo htmlspecialchars($google_maps_key); ?>" readonly class="form-input-custom w-full bg-gray-100 font-mono text-sm" />
                                <p class="text-xs text-gray-500 mt-1">Key loaded from server configuration. Only administrators can change this value on the server side.</p>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="button" id="saveDefaultsBtn" class="bg-gray-600 text-white font-bold py-2 px-6 rounded-lg hover:bg-gray-700 transition shadow-md flex items-center">
                                    <i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Defaults
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </main>
        </div>
    </div>
    <!-- END: Main Application Layout Wrapper -->

    <!-- The main and body tags are closed by templates/footer.php -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            const timezoneForm = document.getElementById('timezone-form');
            const otherSettingsForm = document.getElementById('other-settings-form');
            const messageAlert = document.getElementById('page-message-alert');
            const timezoneMessageContainer = document.getElementById('timezone-message');
            const currentTzDisplay = document.getElementById('current-tz-display');
            const saveTimezoneBtn = document.getElementById('saveTimezoneBtn');
            const saveDefaultsBtn = document.getElementById('saveDefaultsBtn');

            /**
             * Displays a non-blocking message box in the fixed alert area.
             */
            function showFloatingMessage(message, type) {
                messageAlert.textContent = message;
                messageAlert.className = 'p-4 rounded-lg shadow-lg';

                if (type === 'error') {
                    messageAlert.classList.add('bg-red-100', 'text-red-800', 'border-l-4', 'border-red-500');
                } else if (type === 'success') {
                    messageAlert.classList.add('bg-green-100', 'text-green-800', 'border-l-4', 'border-green-500');
                } else if (type === 'info') {
                    messageAlert.classList.add('bg-blue-100', 'text-blue-800', 'border-l-4', 'border-blue-500');
                }
                messageAlert.classList.remove('hidden');

                setTimeout(() => messageAlert.classList.add('hidden'), 5000);
            }

            // --- TIMEZONE FORM LOGIC (Integrated from old global_settings.php) ---
            if (timezoneForm) {
                timezoneForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const form = e.target;
                    const formData = new FormData(form);
                    const selectedTimezone = document.getElementById('app_timezone').value;

                    // Show loading indicator in the local message area
                    timezoneMessageContainer.className = 'text-xs mt-2 text-blue-600 flex items-center';
                    timezoneMessageContainer.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-500" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> <strong>Saving...</strong>';
                    saveTimezoneBtn.disabled = true;

                    // FIX: Pointing to the new API endpoint path in the 'api' folder
                    fetch('api/update_app_settings.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            // Check if the response content-type is JSON before trying to parse
                            const contentType = response.headers.get("content-type");
                            if (contentType && contentType.indexOf("application/json") !== -1) {
                                return response.json().then(data => {
                                    if (!response.ok) {
                                        return Promise.reject(data);
                                    }
                                    return data;
                                });
                            } else {
                                // If not JSON, assume a server/PHP error (like the file not found in previous error)
                                return response.text().then(text => {
                                    console.error('API Error: Non-JSON response received:', text);
                                    return Promise.reject({ message: 'Server returned a non-JSON response. Check console for details.' });
                                });
                            }
                        })
                        .then(data => {
                            // Success block
                            timezoneMessageContainer.className = 'text-xs mt-2 text-green-600 font-semibold flex items-center';
                            timezoneMessageContainer.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 mr-1"></i> Success: ' + data.message;
                            showFloatingMessage('Application Timezone Saved. Reloading page...', 'success');

                            // Update the visible current setting and reload to apply PHP date_default_timezone_set() globally
                            currentTzDisplay.innerText = selectedTimezone;
                            setTimeout(() => window.location.reload(), 1500); // Reload after success message
                        })
                        .catch(errorData => {
                            // Error block
                            const errorMessage = errorData.message || 'An unknown error occurred during API call.';
                            timezoneMessageContainer.className = 'text-xs mt-2 text-red-600 font-semibold flex items-center';
                            timezoneMessageContainer.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4 mr-1"></i> Error: ' + errorMessage;
                            showFloatingMessage('Timezone Save Failed!', 'error');
                            console.error('API Error:', errorData);
                        })
                        .finally(() => {
                            saveTimezoneBtn.disabled = false;
                            lucide.createIcons();
                        });
                });
            }

            // --- MOCK SAVE FOR OTHER SETTINGS ---
            saveDefaultsBtn.addEventListener('click', () => {
                const selectedLanguage = document.getElementById('app_language').options[document.getElementById('app_language').selectedIndex].text;
                showFloatingMessage(`Defaults updated: Language set to ${selectedLanguage}. (API integration needed for persistence).`, 'success');
            });

            // Initialize Lucide Icons for the entire page
            lucide.createIcons();
        });
    </script>

<?php
require_once('templates/footer.php');
?>