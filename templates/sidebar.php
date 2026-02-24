<?php
// Filename: templates/sidebar.php

// Check if we are in "Modal Mode" (embedded in iframe)
// Method 1: Check URL parameter
$is_modal_from_url = isset($_GET['layout']) && $_GET['layout'] === 'modal';

// Method 2: Check if page is in iframe via JavaScript (will be set on client side)
// For now, we rely on URL parameter but add JavaScript detection as backup

// If modal mode is detected, do not render sidebar at all
if ($is_modal_from_url) {
   return; // Do not render sidebar
}

// Determine the current page to set the active link
$current_page = basename($_SERVER['PHP_SELF']);

$user_role = isset($_SESSION['ec_role']) ? $_SESSION['ec_role'] : '';
$user_full_name = isset($_SESSION['ec_full_name']) ? $_SESSION['ec_full_name'] : 'User';

// Determine if we are on a visit-related page
$is_on_visit_page = in_array($current_page, [
    'visit_vitals.php',
    'visit_hpi.php',
    'visit_medications.php',
    'visit_notes.php',
    'visit_wounds.php',
    'visit_diagnosis.php',
    'visit_procedure.php',
    'wound_assessment.php',
    'visit_summary.php',
    'superbill.php',
    'visit_ai_assistant.php'
]);

// Determine if the entire "Today's Visit" section is active
$is_todays_visit_active = in_array($current_page, ['todays_visit.php', 'map_view.php']) || $is_on_visit_page;

// Determine if we are on a patient profile-related page
$is_on_patient_profile = in_array($current_page, ['patient_profile.php', 'patient_appointments.php', 'patient_chart_history.php', 'patient_billing.php', 'wound_comparison.php', 'patient_emr.php', 'patient_orders.php']);


// --- Robust ID gathering for sidebar links ---
$sidebar_patient_id = 0;
$sidebar_appointment_id = 0;
$sidebar_user_id = 0;

if (isset($_GET['patient_id'])) {
    $sidebar_patient_id = intval($_GET['patient_id']);
} elseif (isset($_GET['id'])) {
    $sidebar_patient_id = intval($_GET['id']);
}

if (isset($_GET['appointment_id'])) {
    $sidebar_appointment_id = intval($_GET['appointment_id']);
}
if (isset($_GET['user_id'])) {
    $sidebar_user_id = intval($_GET['user_id']);
}

// Construct URL parameters for different contexts
$profile_params = $sidebar_patient_id ? "?id={$sidebar_patient_id}" : "";
$visit_params = ($sidebar_patient_id && $sidebar_appointment_id) ? "?patient_id={$sidebar_patient_id}&appointment_id={$sidebar_appointment_id}&user_id={$sidebar_user_id}" : "";


// Determine if we are on the user manual page
$is_on_manual_page = ($current_page == 'user_manual.php');

?>

<style>
    /* Sidebar Modern Styling */
    .sidebar {
        background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
    }
    
    .sidebar-header {
        background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
        border-bottom: 2px solid #4f46e5;
    }
    
    .nav-section-title {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #94a3b8;
        padding: 0.75rem 1rem 0.5rem;
        margin-top: 1rem;
    }
    
    .nav-item {
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }
    
    .nav-item::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background: #3b82f6;
        transform: scaleY(0);
        transition: transform 0.2s ease;
    }
    
    .nav-item:hover::before,
    .nav-item.active::before {
        transform: scaleY(1);
    }
    
    .nav-item:hover {
        background: rgba(59, 130, 246, 0.1);
        transform: translateX(2px);
    }
    
    .nav-item.active {
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.05) 100%);
        color: #60a5fa !important;
        font-weight: 600;
    }
    
    .submenu {
        background: rgba(0, 0, 0, 0.2);
        border-left: 2px solid #1e40af;
        margin: 0.25rem 0 0.5rem 1rem;
        border-radius: 0.5rem;
    }
    
    .submenu-item {
        font-size: 0.875rem;
        transition: all 0.15s ease;
    }
    
    .submenu-item:hover {
        background: rgba(59, 130, 246, 0.15);
        padding-left: 2rem;
    }
    
    .submenu-item.active {
        background: rgba(59, 130, 246, 0.25);
        color: #93c5fd;
        font-weight: 600;
    }
    
    .user-profile-section {
        background: rgba(0, 0, 0, 0.3);
        border-top: 2px solid #334155;
    }
    
    .badge {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }
    
    /* Mobile sidebar override */
    @media (max-width: 767px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 50;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
            height: 100vh;
        }
        .sidebar.open {
            transform: translateX(0);
        }
    }
</style>

<!-- Mobile Overlay (only visible on small screens when sidebar is open) -->
<div id="mobile-sidebar-overlay" onclick="closeSidebar()" class="fixed inset-0 bg-black bg-opacity-60 z-40 hidden md:hidden backdrop-blur-sm"></div>

<!-- Modern Sidebar -->
<div id="sidebar" class="sidebar w-64 text-white flex flex-col h-screen flex-shrink-0 md:relative">
    
    <!-- Logo / Brand Header -->
    <div class="sidebar-header h-16 flex items-center justify-between px-4 flex-shrink-0">
        <div id="logo-and-title" class="flex items-center overflow-hidden flex-grow">
            <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center mr-3 shadow-lg">
                <i data-lucide="activity" class="w-6 h-6 text-indigo-600"></i>
            </div>
            <div>
                <h1 id="sidebar-title" class="text-base font-bold text-white">EC Wound Care</h1>
                <p class="text-xs text-blue-200 opacity-90">EMR System</p>
            </div>
        </div>
        <!-- Mobile Close Button -->
        <button id="sidebar-close-btn" onclick="closeSidebar()" class="md:hidden text-blue-200 hover:text-white focus:outline-none flex-shrink-0 ml-2">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>
    </div>

    <!-- Navigation Links -->
    <nav class="flex-1 py-4 space-y-1 overflow-y-auto scrollbar-thin scrollbar-thumb-slate-700 scrollbar-track-transparent">
        
        <!-- MAIN NAVIGATION -->
        <div class="nav-section-title">Main</div>
        
        <?php if (in_array($user_role, ['admin', 'facility', 'clinician'])): ?>
            <a href="dashboard.php" data-tab-title="Dashboard" data-tab-icon="layout-dashboard" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if ($user_role === 'admin' || $user_role === 'clinician' || $user_role === 'facility'): ?>
            <!-- PATIENT MANAGEMENT -->
            <div class="nav-section-title">Patients</div>
            
            <a href="view_patients.php" data-tab-title="Patients" data-tab-icon="users" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'view_patients.php' || $current_page == 'add_patient_form.php' ) ? 'active' : ''; ?>">
                <i data-lucide="users" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Patient List</span>
            </a>
            
            <!-- Patient Submenu -->
            <?php if ($is_on_patient_profile && $sidebar_patient_id > 0): ?>
                <div class="submenu ml-4 py-1 space-y-0.5">
                    <a href="patient_profile.php<?php echo $profile_params; ?>" data-tab-title="Patient Profile" data-tab-icon="user" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_profile.php') ? 'active' : ''; ?>">
                        <i data-lucide="user" class="w-4 h-4 mr-2 flex-shrink-0"></i> Profile
                    </a>
                    <a href="patient_emr.php<?php echo $profile_params; ?>" data-tab-title="EMR" data-tab-icon="folder-open" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_emr.php') ? 'active' : ''; ?>">
                        <i data-lucide="folder-open" class="w-4 h-4 mr-2 flex-shrink-0"></i> EMR
                    </a>
                    <a href="patient_appointments.php<?php echo $profile_params; ?>" data-tab-title="Appointments" data-tab-icon="calendar" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_appointments.php') ? 'active' : ''; ?>">
                        <i data-lucide="calendar" class="w-4 h-4 mr-2 flex-shrink-0"></i> Appointments
                    </a>
                    <a href="patient_chart_history.php<?php echo $profile_params; ?>" data-tab-title="Chart History" data-tab-icon="history" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_chart_history.php') ? 'active' : ''; ?>">
                        <i data-lucide="history" class="w-4 h-4 mr-2 flex-shrink-0"></i> Chart History
                    </a>
                    <a href="wound_comparison.php<?php echo $profile_params; ?>" data-tab-title="Wound Comparison" data-tab-icon="git-compare" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'wound_comparison.php') ? 'active' : ''; ?>">
                        <i data-lucide="git-compare" class="w-4 h-4 mr-2 flex-shrink-0"></i> Wound Comparison
                    </a>
                    <a href="patient_orders.php<?php echo $profile_params; ?>" data-tab-title="Labs" data-tab-icon="beaker" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_orders.php') ? 'active' : ''; ?>">
                        <i data-lucide="beaker" class="w-4 h-4 mr-2 flex-shrink-0"></i> Labs
                    </a>
                    <?php if ($user_role !== 'facility'): ?>
                        <a href="patient_billing.php<?php echo $profile_params; ?>" data-tab-title="Billing" data-tab-icon="dollar-sign" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'patient_billing.php') ? 'active' : ''; ?>">
                            <i data-lucide="dollar-sign" class="w-4 h-4 mr-2 flex-shrink-0"></i> Billing
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- VISITS SECTION -->
        <div class="nav-section-title">Clinical</div>
        
        <a href="todays_visit.php" data-tab-title="Today's Visits" data-tab-icon="calendar-check" class="nav-item flex items-center px-4 py-3 text-gray-300
            <?php echo ($current_page == 'todays_visit.php') ? 'active' : ''; ?>">
            <i data-lucide="calendar-check" class="w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="nav-text">Today's Visits</span>
        </a>

        <?php if ($is_todays_visit_active): ?>
            <div class="submenu ml-4 py-1 space-y-0.5">
                <a href="map_view.php" data-tab-title="Route" data-tab-icon="map" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'map_view.php') ? 'active' : ''; ?>">
                    <i data-lucide="map" class="w-4 h-4 mr-2 flex-shrink-0"></i> Route
                </a>

                <?php if ($is_on_visit_page && $sidebar_appointment_id > 0 && $user_role !== 'facility'): ?>
                    <div class="border-t border-slate-700 mt-2 pt-2">
                        <p class="px-4 text-xs font-semibold text-blue-400 mb-1">Active Visit</p>
                        <a href="visit_ai_assistant.php<?php echo $visit_params; ?>" data-tab-title="AI Assistant" data-tab-icon="bot" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_ai_assistant.php') ? 'active' : ''; ?>">
                            <i data-lucide="bot" class="w-4 h-4 mr-2 flex-shrink-0"></i> AI Assistant
                        </a>
                        <a href="visit_vitals.php<?php echo $visit_params; ?>" data-tab-title="Vitals" data-tab-icon="heart-pulse" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_vitals.php') ? 'active' : ''; ?>">
                            <i data-lucide="heart-pulse" class="w-4 h-4 mr-2 flex-shrink-0"></i> Vitals
                        </a>
                        <a href="visit_hpi.php<?php echo $visit_params; ?>" data-tab-title="HPI" data-tab-icon="clipboard-list" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_hpi.php') ? 'active' : ''; ?>">
                            <i data-lucide="clipboard-list" class="w-4 h-4 mr-2 flex-shrink-0"></i> HPI
                        </a>
                        <a href="visit_wounds.php<?php echo $visit_params; ?>" data-tab-title="Wounds" data-tab-icon="bandage" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_wounds.php' || $current_page == 'wound_assessment.php') ? 'active' : ''; ?>">
                            <i data-lucide="bandage" class="w-4 h-4 mr-2 flex-shrink-0"></i> Wound Mgmt
                        </a>
                        <a href="visit_diagnosis.php<?php echo $visit_params; ?>" data-tab-title="Diagnosis" data-tab-icon="stethoscope" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_diagnosis.php') ? 'active' : ''; ?>">
                            <i data-lucide="stethoscope" class="w-4 h-4 mr-2 flex-shrink-0"></i> Diagnosis
                        </a>
                        <a href="visit_medications.php<?php echo $visit_params; ?>" data-tab-title="Medications" data-tab-icon="pill" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_medications.php') ? 'active' : ''; ?>">
                            <i data-lucide="pill" class="w-4 h-4 mr-2 flex-shrink-0"></i> Medications
                        </a>
                        <a href="visit_notes.php<?php echo $visit_params; ?>" data-tab-title="Advanced Note" data-tab-icon="file-text" class="submenu-item flex items-center px-4 py-2 rounded text-gray-300 <?php echo ($current_page == 'visit_notes.php') ? 'active' : ''; ?>">
                            <i data-lucide="file-text" class="w-4 h-4 mr-2 flex-shrink-0"></i> Advanced Note
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- SCHEDULING -->
        <?php if (in_array($user_role, ['admin', 'facility', 'scheduler'])): ?>
            <div class="nav-section-title">Scheduling</div>
            
            <a href="appointments_calendar.php" data-tab-title="Appointments" data-tab-icon="calendar-days" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'appointments_calendar.php') ? 'active' : ''; ?>">
                <i data-lucide="calendar-days" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Calendar</span>
            </a>
        <?php endif; ?>
        
        <?php if (in_array($user_role, ['admin', 'clinician', 'scheduler'])): ?>
            <a href="timeline.php" data-tab-title="Timeline" data-tab-icon="list-checks" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'timeline.php') ? 'active' : ''; ?>">
                <i data-lucide="list-checks" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Timeline</span>
            </a>
        <?php endif; ?>

        <!-- COMMUNICATION -->
        <div class="nav-section-title">Communication</div>
        
        <a href="chat.php" id="sidebar-chat-link" data-tab-title="Chat" data-tab-icon="message-square" class="nav-item flex items-center px-4 py-3 text-gray-300
            <?php echo ($current_page == 'chat.php') ? 'active' : ''; ?>">
            <i data-lucide="message-square" class="w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="nav-text">Team Chat</span>
            <span id="sidebar-chat-badge" class="hidden ml-auto bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full badge"></span>
        </a>

        <!-- REPORTS & ADMIN -->
        <?php if (in_array($user_role, ['admin', 'facility'])): ?>
            <div class="nav-section-title">Analytics</div>
            
            <a href="reports.php" data-tab-title="Reports" data-tab-icon="bar-chart-2" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <i data-lucide="bar-chart-2" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Reports</span>
            </a>
        <?php endif; ?>

        <?php if ($user_role === 'admin'): ?>
            <div class="nav-section-title">Administration</div>
            
            <a href="view_users.php" data-tab-title="Users" data-tab-icon="user-cog" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'view_users.php' || $current_page == 'add_user.php') ? 'active' : ''; ?>">
                <i data-lucide="user-cog" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Users</span>
            </a>

            <a href="data_management.php" data-tab-title="Data Management" data-tab-icon="database" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'data_management.php' || $current_page == 'manage_hpi_questions.php') ? 'active' : ''; ?>">
                <i data-lucide="database" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Data Management</span>
            </a>

            <a href="audit_log.php" data-tab-title="Audit Log" data-tab-icon="shield-check" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'audit_log.php') ? 'active' : ''; ?>">
                <i data-lucide="shield-check" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Audit Log</span>
            </a>
            
            <a href="global_settings.php" data-tab-title="Settings" data-tab-icon="settings" class="nav-item flex items-center px-4 py-3 text-gray-300
                <?php echo ($current_page == 'global_settings.php') ? 'active' : ''; ?>">
                <i data-lucide="settings" class="w-5 h-5 mr-3 flex-shrink-0"></i>
                <span class="nav-text">Settings</span>
            </a>
        <?php endif; ?>

        <!-- HELP -->
        <div class="nav-section-title">Help</div>
        
        <a href="user_manual.php" id="help-menu-btn" data-tab-title="User Manual" data-tab-icon="book-open" class="nav-item flex items-center px-4 py-3 text-gray-300
             <?php echo $is_on_manual_page ? 'active' : ''; ?>">
            <i data-lucide="book-open" class="w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="nav-text">User Manual</span>
        </a>
    </nav>

    <!-- User Profile Section -->
    <div class="user-profile-section px-4 py-4 flex-shrink-0">
        <a href="account_profile.php" data-tab-title="My Profile" data-tab-icon="user-circle" id="user-menu-btn" class="nav-item flex items-center px-4 py-3 rounded-lg text-gray-300 hover:bg-slate-800">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mr-3 flex-shrink-0 shadow-lg">
                <span class="text-white font-bold text-sm"><?php echo strtoupper(substr($user_full_name, 0, 2)); ?></span>
            </div>
            <div class="user-info flex-1 min-w-0">
                <p class="font-semibold text-sm text-white truncate"><?php echo $user_full_name; ?></p>
                <p class="text-xs text-blue-300 capitalize"><?php echo $user_role; ?></p>
            </div>
            <i data-lucide="chevron-right" class="w-4 h-4 text-gray-500 flex-shrink-0"></i>
        </a>

        <a href="logout.php" data-no-mdi class="nav-item flex items-center px-4 py-2 mt-2 rounded-lg text-gray-300 hover:bg-red-900 hover:text-white transition-colors">
            <i data-lucide="log-out" class="w-5 h-5 mr-3 flex-shrink-0"></i>
            <span class="nav-text font-medium">Logout</span>
        </a>
    </div>

</div>

<!-- Mobile Sidebar Control Script (Required in this file since the header doesn't define the shell) -->
<script src="js/global_chat_notification.js"></script>
<script>
    function openSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-sidebar-overlay');
        if (sidebar && overlay) {
            // Ensure the overlay is fully visible (critical fix)
            overlay.classList.remove('hidden');

            // Timeout ensures the browser registers the class change and applies the CSS transition
            setTimeout(() => {
                sidebar.classList.add('open');
            }, 10);
        }
    }

    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-sidebar-overlay');
        if (sidebar && overlay) {
            sidebar.classList.remove('open');

            // Wait for the CSS transition to finish (0.3s) before hiding the overlay
            setTimeout(() => {
                overlay.classList.add('hidden');
            }, 300);
        }
    }

    // Initialize icons and mobile closing logic
    document.addEventListener('DOMContentLoaded', () => {
        const navItems = document.querySelectorAll('#sidebar .nav-item, #sidebar .submenu-item');
        navItems.forEach(item => {
            item.addEventListener('click', (event) => {
                // Check if we're in MDI shell (parent has mdiManager)
                if (window.mdiManager) {
                    event.preventDefault(); // Prevent default navigation
                    
                    const href = item.getAttribute('href');
                    const title = item.getAttribute('data-tab-title') || item.textContent.trim();
                    const icon = item.getAttribute('data-tab-icon') || 'file';
                    
                    // Skip if it's logout or login
                    if (href && !href.includes('logout.php') && !href.includes('login.php')) {
                        window.openPageInTab(href, title, icon);
                    } else {
                        // Allow logout/login to navigate normally
                        window.location.href = href;
                    }
                }
                
                // Close sidebar on mobile (based on window size)
                if (window.innerWidth < 768) {
                    closeSidebar();
                }
            });
        });

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>