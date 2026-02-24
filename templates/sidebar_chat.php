<?php
// Filename: templates/sidebar_chat.php
// This file is based on your working ec/templates/sidebar.php
// It renders the <aside> AND opens the main content column.

// Session check
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user info from session for display
$user_id = $_SESSION['ec_user_id'];
// Note: $user_full_name is defined in header_chat.php, which is included before this file.
// We just need the role for the Admin check here.
$user_role = isset($_SESSION['ec_role']) ? htmlspecialchars($_SESSION['ec_role']) : 'Role';
$page_title = "Real-Time Chat"; // Set page title for the header

// --- Active Link Logic ---
$current_page = basename($_SERVER['PHP_SELF']);

// Determine if we are on a visit-related page
$is_on_visit_page = in_array($current_page, [
    'visit_vitals.php',
    'visit_hpi.php',
    'visit_medications.php',
    'visit_notes.php',
    'visit_wounds.php',
    'wound_assessment.php',
    'visit_summary.php',
    'superbill.php'
]);

// Determine if the entire "Today's Visit" section is active
$is_todays_visit_active = in_array($current_page, ['todays_visit.php', 'map_view.php']) || $is_on_visit_page;

// Determine if we are on a patient profile-related page
$is_on_patient_profile = in_array($current_page, ['patient_profile.php', 'patient_appointments.php', 'patient_chart_history.php', 'patient_billing.php', 'wound_comparison.php', 'patient_emr.php']);

?>

<!-- This is the FIRST child of the flex wrapper -->
<aside id="sidebar" class="w-64 bg-white shadow-xl flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-4 flex items-center justify-center border-b">
        <img src="logo.png" alt="E-Care Logo" class="h-10">
    </div>
    <nav class="flex-1 overflow-y-auto">
        <a href="dashboard.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
            <i class="fas fa-home mr-3"></i> Dashboard
        </a>
        <a href="view_patients.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
            <i class="fas fa-hospital-user mr-3"></i> Patients
        </a>
        <a href="appointments_calendar.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
            <i class="fas fa-calendar-alt mr-3"></i> Appointments
        </a>

        <!-- NEW CHAT LINK - Marked as Active -->
        <a href="chat.php" class="flex items-center p-4 text-white bg-indigo-600 hover:bg-indigo-700 transition duration-150">
            <i class="fas fa-comments mr-3"></i> Chat
        </a>
        <!-- END NEW CHAT LINK -->

        <a href="reports.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
            <i class="fas fa-chart-line mr-3"></i> Reports
        </a>
        <?php if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] == 'Admin'): ?>
            <a href="view_users.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
                <i class="fas fa-users-cog mr-3"></i> Manage Users
            </a>
            <a href="data_management.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
                <i class="fas fa-database mr-3"></i> Data Management
            </a>
            <a href="global_settings.php" class="flex items-center p-4 text-gray-700 hover:bg-indigo-100 transition duration-150">
                <i class="fas fa-cog mr-3"></i> Global Settings
            </a>
        <?php endif; ?>
    </nav>
    <div class="p-4 border-t">
        <a href="account_profile.php" class="flex items-center p-2 text-gray-700 hover:bg-gray-100 transition duration-150 rounded-lg">
            <i class="fas fa-user-circle mr-3"></i> My Profile
        </a>
        <a href="logout.php" class="flex items-center p-2 text-red-600 hover:bg-red-100 transition duration-150 rounded-lg mt-2">
            <i class="fas fa-sign-out-alt mr-3"></i> Logout
        </a>
    </div>
</aside>
