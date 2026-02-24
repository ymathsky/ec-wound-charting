<?php
// Filename: audit_log.php

require_once 'templates/header.php';
require_once 'db_connect.php';

// Admin-only page
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    echo '<div class="flex h-screen bg-gray-100"><div class="m-auto"><h1 class="text-2xl font-bold text-red-600">Access Denied</h1><p>You do not have permission to view this page.</p></div></div>';
    require_once 'templates/footer.php';
    exit;
}
?>
    <!-- Include Lucide Icons for UI enhancement -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="shield-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Blank Page
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Blank Page</p>
                </div>
                <!-- No buttons needed here -->
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div id="log-container" class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Loading state -->
                    Main Content
                    <!-- Content will be injected here by JavaScript -->
                </div>
            </main>
        </div>
    </div>

<?php
require_once 'templates/footer.php';
?>