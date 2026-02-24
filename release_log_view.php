<?php
// Filename: ec/release_log_view.php
// Description: Displays the static HTML content of the system's release log.

// Include header template
require_once 'templates/header.php';
?>

    <div class="flex h-screen bg-gray-100">
        <?php
        // Assuming the sidebar is generally present (keep this line unless explicitly removed by user)
        require_once 'templates/sidebar.php';
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <h1 class="text-2xl font-bold text-gray-800">System Updates & Release History</h1>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white p-6 rounded-lg shadow-lg max-w-4xl mx-auto space-y-6">
                    <h1 class="text-3xl font-extrabold text-gray-900 border-b pb-2">System Release Log - November 7, 2025 Update (v1.5.0)</h1>

                    <h2 class="text-2xl font-bold text-gray-800 pt-4">Version 1.5.0 - Dynamic Calendar & Rescheduling</h2>

                    <p class="text-sm text-gray-600 mb-4">Major release focused on overhauling the scheduling interface for superior usability and clinician efficiency.</p>

                    <div class="space-y-4 pt-4">

                        <h3 class="text-xl font-semibold text-gray-800">1. Calendar Management Enhancements</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Administrative / Scheduling Efficiency</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Area</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Enhancement Description</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Appointments/Email</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added **Asynchronous Email Notifications** to the assigned clinician immediately after a new appointment is scheduled. This prevents client timeouts while ensuring timely clinician alerts.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Calendar View</td>
                                <td class="px-6 py-3 text-sm text-gray-700">**Dynamic FullCalendar Integration** added for visual scheduling, replacing static lists. Features include viewing, modifying, and creating appointments directly on the calendar interface.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Calendar UI/UX</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Implemented **Drag-and-Drop Rescheduling** directly on the calendar. Users can now quickly move appointments, with changes saved instantly via API.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Calendar Theming</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Updated the calendar event and modal headers to a new **Indigo/Teal color scheme** for improved visual distinction and aesthetics.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">UI Stability</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Fixed a critical layout bug causing the calendar container to render incorrectly on page load by setting explicit height constraints.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-8 border-t border-gray-300">

                    <h1 class="text-3xl font-extrabold text-gray-900 border-b pb-2">System Release Log - November 6, 2025 Update</h1>

                    <h2 class="text-2xl font-bold text-gray-800 pt-4">Version 1.4.0 - Advanced AI, Orders & Compliance Integration</h2>

                    <p class="text-sm text-gray-600 mb-4">Major release focusing on integrating Artificial Intelligence into clinical workflows, expanding wound analytics, and enhancing administrative compliance.</p>

                    <div class="space-y-4 pt-4">

                        <h3 class="text-xl font-semibold text-gray-800">1. AI Clinical Generation</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Clinical Workflow Automation</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoints (api/generate_ai_summary.php, api/generate_treatment_plan.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added dedicated endpoints for generating full visit summaries and drafting comprehensive patient treatment plans using Artificial Intelligence.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">2. Advanced Wound Analytics</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Decision Support / Progress Tracking</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Comparison View (wound_comparison.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Introduced a new page for side-by-side comparison of multiple wound assessments.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoints (api/get_healing_trajectory_data.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added API support for predicting healing trajectory and providing prognostic data visualization.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Automated Measurement (api/auto_measure_wound.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Implemented an API endpoint for automated wound measurement from uploaded images to improve speed and accuracy.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">3. Clinical Orders Management</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Administration / Patient Care Coordination</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Management Pages (manage_all_orders.php, patient_orders.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added pages to create, track, and manage all patient orders (e.g., labs, durable medical equipment, referrals).</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoints (api/create_order.php, api/get_all_orders.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Introduced new endpoints for the creation and retrieval of patient orders.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">4. Regulatory Compliance & Audit Log</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Security / Compliance</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Audit Log System (audit_log.php, audit_log_function.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Deployed a comprehensive system to track all user actions, data modifications, and system access for security and compliance purposes.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoint (api/get_audit_log.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added API for secure retrieval of audit data.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">5. Geospatial Mapping View</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Administrative / Scheduling Efficiency</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Map View Page (map_view.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Implemented a new map interface, primarily for optimizing clinician travel routes and visualizing patient and facility locations.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoint (api/get_maps_api_key.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added API to securely retrieve the necessary mapping service key.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-8 border-t border-gray-300">

                    <h1 class="text-3xl font-extrabold text-gray-900 border-b pb-2">System Release Log - November 4, 2025 Update</h1>

                    <h2 class="text-2xl font-bold text-gray-800 pt-4">Version 1.3.0 - User & Patient Management Overhaul</h2>

                    <div class="space-y-4 pt-4">
                        <p class="text-sm text-gray-600 mb-4">Focus on refining user permissions and enhancing the core patient and wound management interfaces.</p>

                        <h3 class="text-xl font-semibold text-gray-800">1. Granular User Role Permissions</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Security / Administration</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">User Management Interface</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Introduced new permission toggles for disabling specific modules (e.g., Reports, Billing) based on the user role (Clinician, Admin, Manager).</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">2. Patient Status Management</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Workflow Efficiency</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Patient Profile (patient_profile.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added ability to set and display an active/inactive status for patients. Inactive patients are filtered out of the daily dashboard view.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoint (api/manage_patient_status.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">New endpoint to quickly update a patient's active status.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">3. Simplified Wound Menu Actions</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Usability / UI Cleanup</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Patient Profile Wound Master List</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Removed redundant "Chart History" and "Assess" actions from the Patient Profile Wound Master Management table, leaving only <strong>Chart</strong> and <strong>Delete</strong>.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-8 border-t border-gray-300">

                    <h1 class="text-3xl font-extrabold text-gray-900 border-b pb-2">System Release Log - November 1, 2025 Update</h1>

                    <h2 class="text-2xl font-bold text-gray-800 pt-4">Version 1.1.0 - Core Clinical Enhancements</h2>

                    <p class="text-sm text-gray-600">Initial release focused on establishing the core patient charting and administrative workflow.</p>

                    <div class="space-y-4 pt-4">

                        <h3 class="text-xl font-semibold text-gray-800">1. Wound Assessment Autosave / Auto-Sync</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Quality of Life / Data Integrity</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Wound Assessment Form (wound_assessment.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added visual feedback for the autosave status using a small status message and button text changes.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Client Logic (wound_assessment_logic.js)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Implemented a debounced autosave function (saving every 2 seconds after inactivity). This prevents data loss during long assessments and loads any existing drafts on page load.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">API Endpoint (api/create_assessment.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Functionality confirmed: The API now seamlessly handles both creating a new assessment draft and updating an existing one, supporting the auto-save feature transparently.</td>
                            </tr>
                            </tbody>
                        </table>

                        <h3 class="text-xl font-semibold text-gray-800 pt-4">2. Recent Clinical Notes Viewer</h3>
                        <p class="text-gray-700"><strong>Feature Type:</strong> Usability / Workflow Efficiency</p>
                        <table class="min-w-full divide-y divide-gray-200 border border-gray-300 rounded-lg shadow-sm">
                            <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Component</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-gray-50">Change Summary</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">New Page (recent_notes.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Created a dedicated, read-only dashboard view to list the 50 most recently finalized clinical notes across all patients for quick review.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">New API (api/get_recent_notes.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Implemented a highly optimized database query to retrieve recent notes, patient names, and appointment context in a single call.</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700">Navigation (templates/sidebar.php)</td>
                                <td class="px-6 py-3 text-sm text-gray-700">Added a new link in the main application sidebar for easy, one-click access to the Recent Notes list.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="space-y-4 pt-4 border-t mt-10">
                        <h2 class="text-2xl font-bold text-gray-800">Next Planned Features</h2>
                        <ul class="list-disc list-inside space-y-2 pl-4 text-gray-700">
                            <li><strong>Customizable CPT Code Bundles:</strong> Allow users to group frequently used CPT codes for faster billing generation.</li>
                            <li><strong>Patient Intake Form PDF Generation:</strong> Convert patient registration data into a standardized printable PDF.</li>
                        </ul>
                    </div>

                </div>
            </main>
        </div>
    </div>

<?php
// Include footer template
require_once 'templates/footer.php';
?>