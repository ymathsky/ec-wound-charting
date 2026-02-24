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
                        System Audit Log
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">A chronological record of all system and user actions.</p>
                </div>
                <!-- No buttons needed here -->
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div id="log-container" class="bg-white rounded-lg shadow-lg p-6">
                    <!-- Loading state -->
                    <div id="loading" class="text-center">
                        <div class="spinner"></div>
                        <p class="mt-2 text-gray-600">Loading audit log...</p>
                    </div>
                    <!-- Content will be injected here by JavaScript -->
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            const container = document.getElementById('log-container');
            const loadingDiv = document.getElementById('loading');

            async function fetchAuditLog() {
                try {
                    const response = await fetch('api/get_audit_log.php');
                    if (!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.message || 'Failed to fetch data.');
                    }
                    const logs = await response.json();
                    renderLogs(logs);
                } catch (error) {
                    loadingDiv.innerHTML = `<p class="text-red-600 font-semibold">Error: ${error.message}</p>`;
                }
            }

            function renderLogs(logs) {
                if (document.getElementById('loading')) {
                    document.getElementById('loading').remove();
                }

                if (logs.length === 0) {
                    container.innerHTML = '<p class="text-center text-gray-500 py-8">No audit log entries found.</p>';
                    return;
                }

                const tableRows = logs.map(log => {
                    const timestamp = new Date(log.timestamp).toLocaleString();
                    // --- FIX: Changed `log.username` to `log.user_name` ---
                    const user = log.user_name ? `${log.user_name} (ID: ${log.user_id || 'N/A'})` : 'System/Unknown';
                    const entity = log.entity_type ? `${log.entity_type} (ID: ${log.entity_id || 'N/A'})` : 'N/A';

                    return `
                <tr class="table-row hover:bg-gray-50 transition duration-150">
                    <td class="table-cell px-6 py-4 whitespace-nowrap text-sm text-gray-600">${timestamp}</td>
                    <td class="table-cell px-6 py-4 whitespace-nowrap font-semibold text-gray-800">${log.action}</td>
                    <td class="table-cell px-6 py-4 whitespace-nowrap text-gray-700">${user}</td>
                    <td class="table-cell px-6 py-4 whitespace-nowrap text-gray-700">${entity}</td>
                    <td class="table-cell px-6 py-4 text-sm text-gray-700 max-w-lg truncate">${log.details || ''}</td>
                    <td class="table-cell px-6 py-4 whitespace-nowrap font-mono text-xs text-gray-500">${log.ip_address}</td>
                </tr>
                `;
                }).join('');

                container.innerHTML = `
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Timestamp</th>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Action</th>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">User</th>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Entity</th>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Details</th>
                    <th class="table-header-cell px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">IP Address</th>
                </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                ${tableRows}
                </tbody>
                </table>
                </div>
            `;
            }

            fetchAuditLog();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>