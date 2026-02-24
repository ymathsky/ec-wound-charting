<?php
// Filename: ec/patient_portal/nav_panel.php
// Requires: $patient_name (from session), $active_page (set by parent PHP file)
if (!isset($active_page)) {
    $active_page = basename($_SERVER['PHP_SELF'], ".php");
}

// *** HELPER FUNCTIONS DEFINED HERE ONLY, WITH A CHECK TO PREVENT REDECLARATION ***
if (!function_exists('isActive')) {
    function isActive($page_name, $current_page) {
        return $page_name === $current_page ? 'nav-active' : 'nav-inactive';
    }
}

// This function is now defined here with a check. It must be removed from index.php and profile.php
if (!function_exists('getInitials')) {
    function getInitials($name) {
        $parts = explode(' ', $name);
        return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
    }
}
// ****************************************
?>

<!-- Sticky Mobile/Tablet Header (Main Identity Bar) -->
<!-- Hides this bar entirely on desktop (md:hidden) -->
<header class="portal-header flex items-center justify-between px-4 py-3 shadow-sm border-b md:hidden">
    <div class="brand-logo flex items-center text-xl font-bold text-brand">
        <i data-lucide="activity" class="w-6 h-6 mr-2"></i>
        <span>Patient Portal</span>
    </div>

    <div class="flex items-center gap-3">
        <!-- User Initials Circle -->
        <div class="icon-circle-box bg-brand-light text-brand font-bold text-xs border border-indigo-200">
            <?php echo getInitials($patient_name); ?>
        </div>

        <!-- Mobile Menu Toggle: Added explicit border and text color for clarity on mobile -->
        <button id="mobile-menu-toggle" class="p-2 border border-gray-400 rounded-lg text-gray-700 hover:bg-gray-100">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>
</header>

<!-- Mobile Overlay (Hidden by default, shows when menu is open on mobile) -->
<div id="mobile-nav-overlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden md:hidden" onclick="closeMobileMenu()"></div>

<!-- Fixed Navigation Panel (Hidden by default on mobile) -->
<nav id="nav-panel" class="fixed-nav-panel">
    <div class="nav-links-container">

        <!-- Site Logo/Title section integrated into the sidebar for desktop view -->
        <div class="hidden md:flex items-center text-xl font-bold text-brand px-4 py-3 border-b border-indigo-100">
            <i data-lucide="activity" class="w-6 h-6 mr-2"></i>
            <span>Patient Portal</span>
        </div>

        <!-- FIX: Changed mt-4 to mt-8 to provide greater vertical separation from the fixed title block above. -->
        <div class="nav-links-list mt-8">

            <a href="index.php" class="nav-link flex items-center gap-3 <?php echo isActive('index', $active_page); ?>">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i> Dashboard
            </a>

            <!-- NEW APPOINTMENTS LINK -->
            <a href="appointments.php" class="nav-link flex items-center gap-3 <?php echo isActive('appointments', $active_page); ?>">
                <i data-lucide="calendar-check" class="w-5 h-5"></i> Appointments
            </a>
            <!-- END NEW LINK -->

            <a href="upload_photo.php" class="nav-link flex items-center gap-3 <?php echo isActive('upload_photo', $active_page); ?>">
                <i data-lucide="camera" class="w-5 h-5"></i> Upload Photo
            </a>

            <a href="messages.php" class="nav-link flex items-center gap-3 <?php echo isActive('messages', $active_page); ?>">
                <i data-lucide="message-square" class="w-5 h-5"></i> Messages
            </a>

            <div class="nav-separator"></div>

            <a href="documents.php" class="nav-link flex items-center gap-3 <?php echo isActive('documents', $active_page); ?>">
                <i data-lucide="file-text" class="w-5 h-5"></i> My Documents
            </a>

            <a href="medications.php" class="nav-link flex items-center gap-3 <?php echo isActive('medications', $active_page); ?>">
                <i data-lucide="pill" class="w-5 h-5"></i> Medications
            </a>

            <div class="nav-separator"></div>

            <a href="profile.php" class="nav-link flex items-center gap-3 <?php echo isActive('profile', $active_page); ?>">
                <i data-lucide="user-cog" class="w-5 h-5"></i> Profile Settings
            </a>
        </div> <!-- End of nav-links-list -->

        <div class="nav-separator md:hidden"></div>

        <!-- Desktop User/Logout area -->
        <div class="hidden md:block absolute bottom-0 left-0 w-full p-4 border-t border-gray-100 bg-white">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="icon-circle-box bg-brand-light text-brand font-bold text-xs border border-indigo-200">
                        <?php echo getInitials($patient_name); ?>
                    </div>
                    <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($patient_name); ?></span>
                </div>
                <a href="logout.php" class="logout-link px-2 py-1.5 rounded-lg text-sm text-muted" title="Sign Out">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </a>
            </div>
        </div>

        <!-- Mobile-Only Logout Link -->
        <a href="logout.php" class="nav-link nav-logout md:hidden flex items-center gap-3">
            <i data-lucide="log-out" class="w-5 h-5"></i> Sign Out
        </a>
    </div>
</nav>

<script>
    function openMobileMenu() {
        const navPanel = document.getElementById('nav-panel');
        const overlay = document.getElementById('mobile-nav-overlay');

        if (navPanel) navPanel.classList.add('nav-open');
        if (overlay) overlay.classList.remove('hidden');
    }

    function closeMobileMenu() {
        const navPanel = document.getElementById('nav-panel');
        const overlay = document.getElementById('mobile-nav-overlay');

        if (navPanel) navPanel.classList.remove('nav-open');
        if (overlay) overlay.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggleButton = document.getElementById('mobile-menu-toggle');

        toggleButton.addEventListener('click', () => {
            // Check if it's currently open (using a quick boolean check on class)
            const navPanel = document.getElementById('nav-panel');
            if (navPanel.classList.contains('nav-open')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });

        // Close menu if a link is clicked (mobile only)
        document.querySelectorAll('#nav-panel .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    closeMobileMenu();
                }
            });
        });

        // Hide panel when resizing back to desktop view
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 768) {
                closeMobileMenu();
            }
        });
    });
</script>