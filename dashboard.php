<?php
// Filename: dashboard.php

// Check if we are in "Modal Mode" (embedded in MDI iframe)
$is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';

require_once 'templates/header.php';
require_once 'db_connect.php';
?>

<style>
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .fade-in { animation: fadeIn 0.4s ease-out; }
    
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }
    .skeleton {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite;
    }
</style>

<div class="flex h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50">
    <?php 
    if (!$is_modal_mode) {
        require_once 'templates/sidebar.php';
    }
    ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- Header -->
        <header class="bg-white/80 backdrop-blur-lg border-b border-gray-200 shadow-sm sticky top-0 z-30">
            <div class="px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-700 hover:text-indigo-600 transition">
                            <i data-lucide="menu" class="w-6 h-6"></i>
                        </button>
                        <div>
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <i data-lucide="layout-dashboard" class="w-5 h-5 text-white"></i>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold text-gray-900">
                                        <?php echo isset($_SESSION['ec_role']) ? ucfirst($_SESSION['ec_role']) : 'User'; ?> Dashboard
                                    </h1>
                                    <p class="text-sm text-gray-500">Welcome back, <?php echo isset($_SESSION['ec_full_name']) ? htmlspecialchars(explode(' ', $_SESSION['ec_full_name'])[0]) : 'User'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="hidden md:flex items-center space-x-2 px-4 py-2 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-bold">
                                <?php 
                                if (isset($_SESSION['ec_full_name'])) {
                                    $names = explode(' ', $_SESSION['ec_full_name']);
                                    echo substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : '');
                                } else {
                                    echo 'U';
                                }
                                ?>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-900"><?php echo isset($_SESSION['ec_full_name']) ? htmlspecialchars($_SESSION['ec_full_name']) : 'User'; ?></p>
                                <p class="text-xs text-indigo-600"><?php echo isset($_SESSION['ec_role']) ? ucfirst($_SESSION['ec_role']) : 'Role'; ?></p>
                            </div>
                        </div>
                        <a href="logout.php" class="flex items-center space-x-2 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg shadow-sm hover:shadow-md transition-all duration-200">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            <span class="hidden sm:inline">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto p-6 space-y-6">
            
            <!-- Stats Cards -->
            <div id="stats-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Loading Skeletons -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><div class="skeleton h-4 w-24 rounded mb-3"></div><div class="skeleton h-8 w-16 rounded"></div></div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><div class="skeleton h-4 w-24 rounded mb-3"></div><div class="skeleton h-8 w-16 rounded"></div></div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><div class="skeleton h-4 w-24 rounded mb-3"></div><div class="skeleton h-8 w-16 rounded"></div></div>
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100"><div class="skeleton h-4 w-24 rounded mb-3"></div><div class="skeleton h-8 w-16 rounded"></div></div>
            </div>

            <!-- Quick Actions & Search -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Patient Search -->
                <div class="lg:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="search" class="w-4 h-4 text-blue-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Quick Patient Search</h3>
                    </div>
                    <div class="relative mb-4">
                        <input type="text" id="patientSearchInput" placeholder="Search by name, code, or DOB..." 
                            class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <i data-lucide="search" class="w-5 h-5 text-gray-400 absolute left-3 top-3.5"></i>
                    </div>
                    <div id="patient-search-results" class="max-h-64 overflow-y-auto space-y-2">
                        <p class="text-center text-gray-400 py-8 text-sm">Start typing to find patients...</p>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="zap" class="w-4 h-4 text-purple-600"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="space-y-2">
                        <a href="add_appointment.php" data-tab-title="New Appointment" data-tab-icon="calendar-plus" 
                            class="flex items-center space-x-3 p-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl hover:shadow-lg transition-all duration-200 group">
                            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center group-hover:scale-110 transition">
                                <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                            </div>
                            <span class="font-medium">New Appointment</span>
                        </a>
                        <a href="todays_visit.php" data-tab-title="Today's Visits" data-tab-icon="notebook-tabs"
                            class="flex items-center space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:scale-110 transition">
                                <i data-lucide="notebook-tabs" class="w-4 h-4 text-gray-700"></i>
                            </div>
                            <span class="font-medium text-gray-700">Today's Visits</span>
                        </a>
                        <a href="add_patient_form.php" data-tab-title="New Patient" data-tab-icon="user-plus"
                            class="flex items-center space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:scale-110 transition">
                                <i data-lucide="user-plus" class="w-4 h-4 text-gray-700"></i>
                            </div>
                            <span class="font-medium text-gray-700">New Patient</span>
                        </a>
                        <a href="view_patients.php" data-tab-title="All Patients" data-tab-icon="users"
                            class="flex items-center space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:scale-110 transition">
                                <i data-lucide="users" class="w-4 h-4 text-gray-700"></i>
                            </div>
                            <span class="font-medium text-gray-700">All Patients</span>
                        </a>
                        <?php if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'admin'): ?>
                        <a href="view_users.php" data-tab-title="Manage Users" data-tab-icon="shield"
                            class="flex items-center space-x-3 p-3 bg-gray-50 hover:bg-gray-100 rounded-xl transition-all duration-200 group">
                            <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center shadow-sm group-hover:scale-110 transition">
                                <i data-lucide="shield" class="w-4 h-4 text-gray-700"></i>
                            </div>
                            <span class="font-medium text-gray-700">Manage Users</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-4 gap-6">
                <!-- Patient Status Chart -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="pie-chart" class="w-4 h-4 text-green-600"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900">Patient Status</h3>
                    </div>
                    <div class="h-48">
                        <canvas id="patientStatusChart"></canvas>
                    </div>
                </div>

                <!-- Monthly Appointments -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 text-blue-600"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900">Monthly Visits</h3>
                    </div>
                    <div class="h-48">
                        <canvas id="monthlyAppointmentsChart"></canvas>
                    </div>
                </div>

                <!-- Healing Trajectory -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="activity" class="w-4 h-4 text-purple-600"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900">Healing Progress</h3>
                    </div>
                    <div class="h-48">
                        <canvas id="healingTrajectoryChart"></canvas>
                    </div>
                </div>

                <!-- Wound Location Heatmap -->
                <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                    <div class="flex items-center space-x-2 mb-4">
                        <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                            <i data-lucide="map-pin" class="w-4 h-4 text-red-600"></i>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900">Wound Locations</h3>
                    </div>
                    <div class="h-48 flex items-center justify-center">
                        <svg id="wound-location-svg" viewBox="0 0 200 300" class="w-full h-full">
                            <rect x="80" y="20" width="40" height="80" fill="#E5E7EB" rx="5"/>
                            <circle cx="100" cy="50" r="20" fill="#D1D5DB"/>
                            <rect x="80" y="100" width="40" height="150" fill="#E5E7EB" rx="8"/>
                            <circle cx="100" cy="150" r="12" fill="#EF4444" opacity="0.2" class="wound-hotspot" data-location="Torso"/>
                            <circle cx="100" cy="200" r="12" fill="#F59E0B" opacity="0.2" class="wound-hotspot" data-location="Knee"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Today's Schedule -->
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <div class="flex items-center space-x-2 mb-4">
                    <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i data-lucide="clock" class="w-4 h-4 text-yellow-600"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900">Today's Schedule</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Time</th>
                                <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Action</th>
                            </tr>
                        </thead>
                        <tbody id="appointments-body">
                            <tr><td colspan="3" class="text-center py-8 text-gray-400">Loading schedule...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

    <!-- Ensure Chart.js is loaded -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- Ensure Lucide icons are available for the new panel -->
    <script>
        document.write('<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></sc' + 'ript>');
    </script>

    <script>
        // --- Global Chart Instances for destruction/real-time update ---
        let patientStatusChartInstance = null;
        let monthlyAppointmentsChartInstance = null;
        let healingTrajectoryChartInstance = null;
        let heatmapData = {
            'Knee': 5,
            'Torso': 10,
            'Foot': 2,
            'Elbow': 1
        }; // Mock data for heatmap

        // --- Debounce Utility ---
        const debounce = (func, delay) => {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), delay);
            };
        };

        // Hardcoded colors for consistent patient status charting (using Gender as proxy for Status)
        const CHART_COLORS = {
            'Male': '#3B82F6',       // Blue
            'Female': '#10B981',     // Green
            'Other': '#F59E0B',      // Amber
            'Unknown': '#EF4444'     // Red (Fallback)
        };
        // ---------------------------------------------------------------

        // --- Element Declarations ---
        const statsContainer = document.getElementById('stats-container');
        const appointmentsBody = document.getElementById('appointments-body');
        const patientSearchInput = document.getElementById('patientSearchInput');
        const patientSearchResults = document.getElementById('patient-search-results');
        const heatmapLoadingMsg = document.getElementById('heatmap-loading-msg');

        // --- Chart Rendering (Now pulls data from a dedicated API) ---
        const patientStatusCtx = document.getElementById('patientStatusChart').getContext('2d');
        const monthlyAppointmentsCtx = document.getElementById('monthlyAppointmentsChart').getContext('2d');
        const healingTrajectoryCtx = document.getElementById('healingTrajectoryChart').getContext('2d');

        // NEW: Heatmap Rendering Function
        function renderHeatmap(data) {
            if (heatmapLoadingMsg) {
                heatmapLoadingMsg.style.display = 'none';
            }

            // Find the maximum count to normalize opacity
            const maxCount = Math.max(...Object.values(data));
            const hotspots = document.querySelectorAll('.wound-hotspot');

            hotspots.forEach(hotspot => {
                const location = hotspot.getAttribute('data-location');
                const count = data[location] || 0;
                const opacity = maxCount > 0 ? (count / maxCount) : 0.1;
                const radius = 15 + (count / maxCount) * 10; // Scale size up to 25px radius

                hotspot.setAttribute('data-count', count);
                hotspot.setAttribute('opacity', opacity > 0 ? Math.max(0.2, opacity) : 0.1); // Min opacity of 0.2
                hotspot.setAttribute('r', radius);

                // Update text label
                const textElement = document.getElementById(`hotspot-text-${location}`);
                if (textElement) {
                    textElement.textContent = count;
                    textElement.setAttribute('fill', opacity > 0.5 ? '#FFFFFF' : '#1F2937'); // White text for high opacity
                }

                // Simple scaling of color intensity (red for higher count)
                if (count > 0) {
                    const red = Math.min(255, 100 + (count / maxCount) * 155);
                    hotspot.setAttribute('fill', `rgb(${red}, 50, 50)`);
                } else {
                    hotspot.setAttribute('fill', '#E5E7EB');
                }
            });
        }

        async function renderCharts() {
            try {
                // Fetch chart data
                const response = await fetch('api/get_chart_data.php');
                if (!response.ok) throw new Error('Failed to fetch chart data. Network response not OK.');

                const text = await response.text();
                if (!text.trim() || text.includes('<b>Warning</b>') || text.includes('<b>Fatal error</b>')) {
                    throw new Error(`PHP or Empty response detected: ${text.substring(0, 50)}...`);
                }
                const chartData = JSON.parse(text);
                if (!chartData.success) throw new Error(chartData.message || 'API returned failure.');

                // 0. NEW: Render Heatmap Data
                const locationData = chartData.wound_locations || heatmapData; // Use live data if available, otherwise mock
                renderHeatmap(locationData);

                // 1. Patient Status Pie Chart
                const statusData = chartData.patient_status;
                const backgroundColors = statusData.labels.map(label => CHART_COLORS[label] || CHART_COLORS['Unknown']);
                if (patientStatusChartInstance) {
                    patientStatusChartInstance.destroy();
                }
                patientStatusChartInstance = new Chart(patientStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusData.labels,
                        datasets: [{
                            label: 'Patient Distribution',
                            data: statusData.data,
                            backgroundColor: backgroundColors,
                            hoverOffset: 16,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'right' },
                            title: { display: false }
                        }
                    }
                });

                // 2. Monthly Appointments Bar Chart
                const monthlyData = chartData.monthly_appointments;
                if (monthlyAppointmentsChartInstance) {
                    monthlyAppointmentsChartInstance.destroy();
                }
                monthlyAppointmentsChartInstance = new Chart(monthlyAppointmentsCtx, {
                    type: 'bar',
                    data: {
                        labels: monthlyData.labels,
                        datasets: [{
                            label: 'Total Appointments',
                            data: monthlyData.data,
                            backgroundColor: '#3B82F6', // Blue
                            borderColor: '#2563EB',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { display: false },
                                title: { display: true, text: 'Number of Visits' }
                            },
                            x: {
                                grid: { display: false }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            title: { display: false }
                        }
                    }
                });

                // 3. Healing Trajectory Chart
                const trajectoryData = chartData.healing_trajectory || { labels: ['Month 1', 'Month 2', 'Month 3'], data: [0.1, 0.2, 0.3] }; // Mock data fallback
                if (healingTrajectoryChartInstance) {
                    healingTrajectoryChartInstance.destroy();
                }
                healingTrajectoryChartInstance = new Chart(healingTrajectoryCtx, {
                    type: 'line',
                    data: {
                        labels: trajectoryData.labels,
                        datasets: [{
                            label: 'Avg. Reduction in Wound Area (%)',
                            data: trajectoryData.data,
                            backgroundColor: 'rgba(16, 185, 129, 0.2)', // Green, transparent fill
                            borderColor: '#10B981', // Green line
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 1, // Represents 100% healing
                                title: { display: true, text: 'Avg. Healing Progress (0.0 - 1.0)' },
                                ticks: {
                                    callback: function(value) { return (value * 100) + '%'; }
                                }
                            },
                            x: { grid: { display: false } }
                        },
                        plugins: {
                            legend: { display: false },
                            title: { display: false }
                        }
                    }
                });

            } catch (error) {
                console.error('Error rendering live charts:', error);
                if (patientStatusChartInstance) patientStatusChartInstance.destroy();
                if (monthlyAppointmentsChartInstance) monthlyAppointmentsChartInstance.destroy();
                if (healingTrajectoryChartInstance) healingTrajectoryChartInstance.destroy();

                if (heatmapLoadingMsg) {
                    heatmapLoadingMsg.textContent = 'Error loading heatmap data.';
                    heatmapLoadingMsg.style.display = 'block';
                }
            }
        }

        // --- Data Fetching for Stats/Lists ---
        async function fetchDashboardData() {
            try {
                document.getElementById('stats-container').querySelectorAll('.animate-pulse').forEach(el => el.remove());

                const response = await fetch('api/get_dashboard_stats.php');
                if (!response.ok) throw new Error('Failed to fetch stats from the server.');

                const text = await response.text();

                if (!text.trim() || text.includes('<b>Warning</b>') || text.includes('<b>Fatal error</b>')) {
                    throw new Error(`PHP or Empty response detected: ${text.substring(0, 50)}...`);
                }

                const data = JSON.parse(text);

                if (data && data.stats) {
                    renderStats(data.stats);
                } else {
                    renderStats({total_patients: 'N/A', active_wounds: 'N/A', high_risk_wounds: 'N/A', assessments_today: 'N/A'});
                }

                if (data && data.todays_appointments) {
                    renderAppointments(data.todays_appointments);
                } else {
                    appointmentsBody.innerHTML = '<tr><td colspan="3" class="text-center p-4 text-gray-500">Could not load today\'s appointments.</td></tr>';
                }

            } catch (error) {
                console.error('Error fetching dashboard data:', error);
                statsContainer.innerHTML = `<div class="col-span-4 bg-red-100 p-4 rounded-xl shadow-lg text-red-800 border border-red-300">
                <strong class="font-bold">Error Loading Dashboard Data:</strong> ${error.message}
                <p class="mt-2 text-sm">The API call to <code>api/get_dashboard_stats.php</code> failed. Please check the browser console for exact PHP errors.</p>
            </div>`;
                appointmentsBody.innerHTML = '<tr><td colspan="3" class="text-center p-4 text-red-500">Error loading schedule.</td></tr>';
            }
        }

        // --- Render UI Components ---
        function renderStats(stats) {
            statsContainer.innerHTML = `
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 shadow-sm text-white fade-in">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <i data-lucide="users" class="w-5 h-5"></i>
                        </div>
                        <span class="text-3xl font-bold">${stats.total_patients}</span>
                    </div>
                    <p class="text-sm text-blue-100">Total Patients</p>
                </div>
                
                <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 shadow-sm text-white fade-in">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <i data-lucide="activity" class="w-5 h-5"></i>
                        </div>
                        <span class="text-3xl font-bold">${stats.active_wounds}</span>
                    </div>
                    <p class="text-sm text-green-100">Active Wounds</p>
                </div>
                
                <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-6 shadow-sm text-white fade-in">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        </div>
                        <span class="text-3xl font-bold">${stats.high_risk_wounds}</span>
                    </div>
                    <p class="text-sm text-red-100">High-Risk Wounds</p>
                </div>
                
                <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 shadow-sm text-white fade-in">
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                            <i data-lucide="stethoscope" class="w-5 h-5"></i>
                        </div>
                        <span class="text-3xl font-bold">${stats.assessments_today}</span>
                    </div>
                    <p class="text-sm text-purple-100">Assessments Today</p>
                </div>
            `;
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        function renderAppointments(appointments) {
            if (appointments && appointments.length > 0) {
                appointmentsBody.innerHTML = appointments.map(appt => `
                    <tr class="hover:bg-gray-50 transition">
                        <td class="py-3 px-4">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center text-white text-xs font-bold">
                                    ${appt.first_name.charAt(0)}${appt.last_name.charAt(0)}
                                </div>
                                <div>
                                    <p class="font-medium text-gray-900">${appt.first_name} ${appt.last_name}</p>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">${appt.appointment_time}</td>
                        <td class="py-3 px-4 text-right">
                            <a href="visit_vitals.php?appointment_id=${appt.appointment_id}&patient_id=${appt.patient_id}&user_id=${appt.user_id}"
                               class="inline-flex items-center space-x-1 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition shadow-sm hover:shadow-md">
                                <i data-lucide="play" class="w-3 h-3"></i>
                                <span>Start</span>
                            </a>
                        </td>
                    </tr>
                `).join('');
            } else {
                appointmentsBody.innerHTML = '<tr><td colspan="3" class="text-center py-8 text-gray-400">No appointments scheduled for today.</td></tr>';
            }
            if (typeof lucide !== 'undefined') lucide.createIcons();
        }

        // --- MODIFIED: Patient Search Functions (Removed 'Start Visit' button) ---

        // 1. Function to handle the actual API call and rendering
        const handlePatientSearch = debounce(async () => {
            const searchTerm = patientSearchInput.value.toLowerCase();

            if (searchTerm.length < 2) {
                patientSearchResults.innerHTML = '<p class="text-center text-gray-500 py-4 text-sm">Start typing to quickly find and take action on a patient record.</p>';
                return;
            }

            // Show a temporary spinner state
            patientSearchResults.innerHTML = `<p class="text-center text-blue-500 py-4 text-sm"><div class="spinner-small inline-block mr-2"></div>Searching...</p>`;

            // Inline spinner style since we cannot use separate CSS/JS files
            const searchSpinnerStyle = `
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
            .spinner-small {
                border: 2px solid rgba(59, 130, 246, 0.3);
                border-top: 2px solid #3B82F6;
                border-radius: 50%;
                width: 16px;
                height: 16px;
                animation: spin 1s linear infinite;
            }
        `;

            // Prepend the inline style if not already present
            if (!document.getElementById('search-spinner-style')) {
                const style = document.createElement('style');
                style.id = 'search-spinner-style';
                style.textContent = searchSpinnerStyle;
                document.head.appendChild(style);
            }

            try {
                // Call the new dedicated search API
                const response = await fetch(`api/search_patients.php?term=${encodeURIComponent(searchTerm)}`);
                if (!response.ok) throw new Error('API failed to return results.');

                const filteredPatients = await response.json();

                if (filteredPatients.length > 0) {
                    patientSearchResults.innerHTML = filteredPatients.map(p => {
                        const patientId = p.patient_id;
                        const viewProfileLink = `patient_profile.php?id=${patientId}`;
                        const scheduleApptLink = `add_appointment.php?patient_id=${patientId}`;
                        const doctor = p.primary_doctor_name || 'N/A';

                        return `
                        <div class="flex items-center justify-between p-3 bg-gray-50 hover:bg-indigo-50 rounded-xl transition group">
                            <a href="${viewProfileLink}" data-tab-title="${p.first_name} ${p.last_name}" data-tab-icon="user" class="flex items-center space-x-3 flex-1 min-w-0">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                    ${p.first_name.charAt(0)}${p.last_name.charAt(0)}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 group-hover:text-indigo-600 transition truncate">
                                        ${p.last_name}, ${p.first_name}
                                        <span class="text-xs text-gray-400 font-mono ml-1">${p.patient_code}</span>
                                    </p>
                                    <p class="text-xs text-gray-500 truncate">DOB: ${p.date_of_birth} • Dr: ${doctor}</p>
                                </div>
                            </a>
                            <div class="flex space-x-1 ml-2">
                                <a href="${viewProfileLink}" data-tab-title="${p.first_name} ${p.last_name}" data-tab-icon="user" title="View Profile"
                                   class="w-8 h-8 bg-white hover:bg-indigo-600 text-gray-600 hover:text-white rounded-lg flex items-center justify-center transition shadow-sm">
                                    <i data-lucide="user" class="w-4 h-4"></i>
                                </a>
                                <a href="${scheduleApptLink}" data-tab-title="New Appointment" data-tab-icon="calendar-plus" title="Schedule"
                                   class="w-8 h-8 bg-white hover:bg-blue-600 text-gray-600 hover:text-white rounded-lg flex items-center justify-center transition shadow-sm">
                                    <i data-lucide="calendar-plus" class="w-4 h-4"></i>
                                </a>
                            </div>
                        </div>
                        `;
                    }).join('');

                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else {
                    patientSearchResults.innerHTML = '<p class="text-center text-gray-400 py-8 text-sm">No patients found.</p>';
                }

        } catch(error) {
            patientSearchResults.innerHTML = `<p class="text-red-500 p-4">Error fetching search results: ${error.message}</p>`;
        }
    }, 300); // Debounce time of 300ms

    // 2. Attach the search handler to the input field
    patientSearchInput.addEventListener('input', handlePatientSearch);


    // --- Initialization ---
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

        // Run initial data fetches
        fetchDashboardData();
        renderCharts();

        // Setup real-time updates
        setInterval(fetchDashboardData, 10000);
        setInterval(renderCharts, 10000);
    });
</script>

<?php
require_once 'templates/footer.php';
?>