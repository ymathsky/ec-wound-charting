<?php
// Filename: ec/patient_portal/medications.php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

$patient_name = $_SESSION['portal_patient_name'];
$active_page = 'medications'; // Set active page for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medications | Patient Portal</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<!-- 1. Navigation Panel (Mobile Header and Sidebar) -->
<?php require_once 'nav_panel.php'; ?>

<!-- 2. Main Page Wrapper -->
<div class="page-wrapper">
    <!-- Nav Panel is sticky/fixed/sidebar depending on viewport -->

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="container max-w-screen-lg">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="pill" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    My Medications
                </h1>
            </div>

            <div id="loading" class="text-center py-12 text-muted">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mb-2"></div>
                <p>Loading prescription records...</p>
            </div>

            <div id="medications-container" class="hidden space-y-8">

                <!-- Active Meds Section -->
                <div>
                    <h2 class="section-title text-success font-semibold text-lg border-b border-green-200 pb-2 mb-4">
                        <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                        Active Prescriptions
                    </h2>
                    <div id="active-meds-list" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- JS will inject content -->
                    </div>
                    <p id="no-active-msg" class="hidden text-muted italic text-sm py-4 px-4 bg-white rounded-lg border border-gray-200">No active medications on file.</p>
                </div>

                <!-- Past Meds Section -->
                <div>
                    <h2 class="section-title text-muted font-semibold text-lg border-b border-gray-200 pb-2 mb-4 mt-6">
                        <i data-lucide="history" class="w-5 h-5 mr-2"></i>
                        Past / Discontinued
                    </h2>
                    <div id="past-meds-list" class="space-y-3">
                        <!-- JS will inject content -->
                    </div>
                    <p id="no-past-msg" class="hidden text-muted italic text-sm py-4 px-4 bg-white rounded-lg border border-gray-200">No medication history found.</p>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
    lucide.createIcons();

    document.addEventListener('DOMContentLoaded', async () => {
        const container = document.getElementById('medications-container');
        const loading = document.getElementById('loading');
        const activeList = document.getElementById('active-meds-list');
        const pastList = document.getElementById('past-meds-list');

        try {
            const response = await fetch('api/get_medications.php');
            const meds = await response.json();

            loading.classList.add('hidden');
            container.classList.remove('hidden');

            let hasActive = false;
            let hasPast = false;

            if (meds.length === 0) {
                document.getElementById('no-active-msg').classList.remove('hidden');
                document.getElementById('no-past-msg').classList.remove('hidden');
                return;
            }

            meds.forEach(med => {
                const isActive = med.status === 'Active';

                if (isActive) {
                    hasActive = true;
                    const card = document.createElement('div');
                    card.className = 'card-base border-l-4 border-l-success shadow-md hover:shadow-lg transition p-4';
                    card.innerHTML = `
                            <div class="flex justify-between items-start">
                                <h3 class="font-bold text-gray-900 text-lg">${med.drug_name}</h3>
                                <span class="bg-green-50 text-success text-xs font-bold px-2 py-1 rounded-full border border-green-200">Active</span>
                            </div>
                            <div class="mt-2 text-sm text-gray-700 space-y-1">
                                <p><span class="font-semibold text-gray-600">Dosage:</span> ${med.dosage}</p>
                                <p><span class="font-semibold text-gray-600">Frequency:</span> ${med.frequency}</p>
                                <p><span class="font-semibold text-gray-600">Route:</span> ${med.route || 'Oral'}</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 text-xs text-muted flex justify-between items-end">
                                <span>Started: ${new Date(med.start_date).toLocaleDateString()}</span>
                                <span class="text-right truncate max-w-[50%]">${med.prescribing_doctor || 'Unknown Doctor'}</span>
                            </div>
                        `;
                    activeList.appendChild(card);
                } else {
                    hasPast = true;
                    const row = document.createElement('div');
                    row.className = 'card-base p-3 flex justify-between items-center bg-white opacity-90 hover:opacity-100 transition border-l-4 border-l-gray-300';
                    row.innerHTML = `
                            <div>
                                <h4 class="font-semibold text-gray-800">${med.drug_name} <span class="text-sm font-normal text-muted">(${med.dosage})</span></h4>
                                <p class="text-xs text-muted">Ended: ${med.end_date ? new Date(med.end_date).toLocaleDateString() : 'Discontinued'}</p>
                            </div>
                            <span class="bg-gray-100 text-muted text-xs px-2 py-1 rounded border border-gray-200">${med.status}</span>
                        `;
                    pastList.appendChild(row);
                }
            });

            if (!hasActive) document.getElementById('no-active-msg').classList.remove('hidden');
            if (!hasPast) document.getElementById('no-past-msg').classList.remove('hidden');

        } catch (error) {
            loading.innerHTML = '<p class="text-red-700 font-medium">Unable to load medications. Please check your network connection.</p>';
        }
    });
</script>
</body>
</html>