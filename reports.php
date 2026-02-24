<?php
// Filename: reports.php
require_once 'templates/header.php';
require_once 'db_connect.php';

// --- Role-based Access Control ---
// FIX: Allow 'clinician' role to access the reports page
if (!isset($_SESSION['ec_role']) || !in_array($_SESSION['ec_role'], ['admin', 'facility', 'clinician'])) {
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 flex flex-col overflow-hidden'>";
    echo "<header class='w-full bg-white p-4 flex justify-between items-center shadow-md'><h1>Access Denied</h1></header>";
    echo "<main class='flex-1 overflow-y-auto bg-gray-100 p-6'><div class='max-w-4xl mx-auto bg-white p-6 rounded-lg shadow'>";
    echo "<h2 class='text-2xl font-bold text-red-600'>Access Denied</h2>";
    echo "<p class='mt-4 text-gray-700'>You do not have permission to access this page.</p>";
    echo "</div></main></div></div>";
    require_once 'templates/footer.php';
    exit();
}
?>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="bar-chart-3" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Reporting & Analytics
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Clinical and operational insights across patient data.</p>
                </div>
                <?php // Only show New Appointment button for admin/clinician
                if (in_array($_SESSION['ec_role'], ['admin', 'clinician'])): ?>
                    <!-- Adding a quick link to scheduling here (matches user's template requirement) -->
                    <a href="add_appointment.php" data-tab-title="New Appointment" data-tab-icon="calendar-plus" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center transition transform hover:scale-105 shadow-md">
                        <i data-lucide="calendar-plus" class="w-5 h-5 mr-2"></i>
                        New Appointment
                    </a>
                <?php endif; ?>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 space-y-6">

                <!-- Key Operational Metrics -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Key Operational Metrics</h3>
                    <div id="operational-metrics-container" class="grid grid-cols-1 md:grid-cols-3 gap-6 text-center">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase">Avg. Visits Per Patient</h4>
                            <p id="metric-avg-visits" class="text-3xl font-bold text-blue-600 mt-2">...</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase">Cancellation Rate</h4>
                            <p id="metric-cancellation-rate" class="text-3xl font-bold text-red-600 mt-2">...</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500 uppercase">Average Patient Age</h4>
                            <p id="metric-avg-age" class="text-3xl font-bold text-green-600 mt-2">...</p>
                        </div>
                    </div>
                </div>

                <div id="reports-container" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    <!-- Healing Rate by Wound Type -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Average Healing Time by Wound Type</h3>
                        <div class="relative h-80">
                            <div id="healing-rate-chart-container">
                                <canvas id="healingRateChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- CPT Code Utilization -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">CPT Code Utilization</h3>
                        <div class="relative h-80">
                            <div id="cpt-utilization-chart-container">
                                <canvas id="cptUtilizationChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Patient Demographics by Age Group -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Patient Demographics (By Age Group)</h3>
                        <div class="relative h-80">
                            <div id="demographics-chart-container">
                                <canvas id="demographicsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Appointment Status Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Appointment Status Distribution</h3>
                        <div class="relative h-80">
                            <div id="appointment-status-chart-container">
                                <canvas id="appointmentStatusChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Wound Type Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Active Wound Type Distribution</h3>
                        <div class="relative h-80">
                            <div id="wound-type-chart-container">
                                <canvas id="woundTypeChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Visit Frequency Distribution -->
                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Visit Frequency Distribution</h3>
                        <div class="relative h-80">
                            <div id="visit-frequency-chart-container">
                                <canvas id="visitFrequencyChart"></canvas>
                            </div>
                        </div>
                    </div>


                    <!-- Clinician Caseload -->
                    <div class="bg-white p-6 rounded-lg shadow-lg lg:col-span-3">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Clinician Caseload & Activity</h3>
                        <div id="clinician-activity-container" class="overflow-x-auto">
                            <p class="text-center text-gray-500 py-8">Loading clinician data...</p>
                        </div>
                    </div>
                </div>

                <!-- Patient Specific Report Section -->
                <div class="bg-white p-6 rounded-lg shadow-lg lg:col-span-3">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-3">Patient-Specific Wound Healing Trajectory</h3>
                    <div class="mb-4 max-w-sm">
                        <label for="patient-selector" class="form-label">Select a Patient</label>
                        <select id="patient-selector" class="form-input bg-white">
                            <option value="">Loading patients...</option>
                        </select>
                    </div>
                    <div class="relative h-96 mt-6">
                        <div id="patient-wound-chart-container">
                            <p class="text-center text-gray-500 pt-16">Please select a patient to view their wound healing progress.</p>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Containers for global reports
            const healingRateContainer = document.getElementById('healing-rate-chart-container');
            const cptUtilizationContainer = document.getElementById('cpt-utilization-chart-container');
            const clinicianActivityContainer = document.getElementById('clinician-activity-container');
            const demographicsContainer = document.getElementById('demographics-chart-container');
            const appointmentStatusContainer = document.getElementById('appointment-status-chart-container');
            const woundTypeContainer = document.getElementById('wound-type-chart-container');
            const visitFrequencyContainer = document.getElementById('visit-frequency-chart-container');

            // Containers for operational metrics
            const metricAvgVisits = document.getElementById('metric-avg-visits');
            const metricCancellationRate = document.getElementById('metric-cancellation-rate');
            const metricAvgAge = document.getElementById('metric-avg-age');


            // Containers for patient-specific reports
            const patientSelector = document.getElementById('patient-selector');
            const patientWoundChartContainer = document.getElementById('patient-wound-chart-container');

            let healingRateChart, cptChart, demographicsChart, appointmentStatusChart, woundTypeChart, visitFrequencyChart, patientWoundChart;

            async function fetchReportData() {
                try {
                    const response = await fetch('api/get_report_data.php');
                    if (!response.ok) throw new Error('Failed to fetch report data.');
                    const data = await response.json();

                    // Render new operational metrics
                    renderOperationalMetrics(data.operational_metrics);

                    // Render charts
                    renderHealingRateChart(data.healing_rates);
                    renderCptUtilizationChart(data.cpt_utilization);
                    renderClinicianActivityTable(data.clinician_activity);
                    renderPatientDemographicsChart(data.patient_demographics);
                    renderAppointmentStatusChart(data.appointment_status);
                    renderWoundTypeChart(data.wound_type_distribution);
                    renderVisitFrequencyChart(data.visit_frequency_distribution);

                } catch (error) {
                    [healingRateContainer, cptUtilizationContainer, clinicianActivityContainer, demographicsContainer, appointmentStatusContainer, woundTypeContainer, visitFrequencyContainer].forEach(container => {
                        if(container) container.innerHTML = `<p class="text-red-500 text-center p-4">${error.message}</p>`;
                    });
                }
            }

            function renderOperationalMetrics(data) {
                if (!data) return;
                metricAvgVisits.textContent = data.avg_visits_per_patient || 'N/A';
                metricCancellationRate.textContent = `${data.cancellation_rate || 0}%`;
                metricAvgAge.textContent = data.avg_patient_age || 'N/A';
            }

            // --- PATIENT-SPECIFIC REPORTING LOGIC ---

            async function populatePatientSelector() {
                try {
                    const response = await fetch('api/get_patients.php');
                    if (!response.ok) throw new Error('Failed to load patients');
                    const patients = await response.json();

                    patientSelector.innerHTML = '<option value="">Select a patient</option>';
                    patients.forEach(p => {
                        const option = document.createElement('option');
                        option.value = p.patient_id;
                        option.textContent = `${p.last_name}, ${p.first_name}`;
                        patientSelector.appendChild(option);
                    });
                } catch (error) {
                    patientSelector.innerHTML = `<option value="">${error.message}</option>`;
                }
            }

            patientSelector.addEventListener('change', async function() {
                const patientId = this.value;
                if (!patientId) {
                    patientWoundChartContainer.innerHTML = '<p class="text-center text-gray-500 pt-16">Please select a patient to view their wound healing progress.</p>';
                    if(patientWoundChart) patientWoundChart.destroy();
                    return;
                }

                patientWoundChartContainer.innerHTML = '<div class="flex justify-center items-center h-full"><div class="spinner"></div></div>';

                try {
                    const response = await fetch(`api/get_patient_report_data.php?patient_id=${patientId}`);
                    if (!response.ok) throw new Error('Failed to fetch patient-specific report data.');
                    const data = await response.json();
                    renderPatientWoundChart(data);
                } catch (error) {
                    patientWoundChartContainer.innerHTML = `<p class="text-red-500 text-center p-4">${error.message}</p>`;
                }
            });

            function renderPatientWoundChart(data) {
                if (!data || data.datasets.length === 0) {
                    patientWoundChartContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No wound assessment data found for this patient.</p>';
                    return;
                }

                patientWoundChartContainer.innerHTML = '<canvas id="patientWoundChart"></canvas>';
                const ctx = document.getElementById('patientWoundChart').getContext('2d');

                if (patientWoundChart) patientWoundChart.destroy();

                patientWoundChart = new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day'
                                },
                                title: {
                                    display: true,
                                    text: 'Date of Assessment'
                                }
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Wound Area (cm²)'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            label += context.parsed.y + ' cm²';
                                        }
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }


            // --- GLOBAL REPORT RENDERING ---
            function renderHealingRateChart(data) {
                if (!data || data.labels.length === 0) {
                    healingRateContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No healing data available to calculate rates.</p>';
                    return;
                }
                const ctx = document.getElementById('healingRateChart').getContext('2d');
                if (healingRateChart) healingRateChart.destroy();
                healingRateChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Avg. Days to Heal',
                            data: data.data,
                            backgroundColor: 'rgba(59, 130, 246, 0.7)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Average Days'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            function renderCptUtilizationChart(data) {
                if (!data || data.labels.length === 0) {
                    cptUtilizationContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No CPT code usage has been recorded.</p>';
                    return;
                }
                const ctx = document.getElementById('cptUtilizationChart').getContext('2d');
                if(cptChart) cptChart.destroy();
                cptChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Usage Count',
                            data: data.data,
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.7)',
                                'rgba(59, 130, 246, 0.7)',
                                'rgba(245, 158, 11, 0.7)',
                                'rgba(16, 185, 129, 0.7)',
                                'rgba(139, 92, 246, 0.7)',
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            function renderPatientDemographicsChart(data) {
                if (!data || data.labels.length === 0) {
                    demographicsContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No patient demographic data available.</p>';
                    return;
                }
                const ctx = document.getElementById('demographicsChart').getContext('2d');
                if(demographicsChart) demographicsChart.destroy();
                demographicsChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Number of Patients',
                            data: data.data,
                            backgroundColor: 'rgba(16, 185, 129, 0.7)',
                            borderColor: 'rgba(16, 185, 129, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Patients'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }

            function renderAppointmentStatusChart(data) {
                if (!data || data.labels.length === 0) {
                    appointmentStatusContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No appointment data available.</p>';
                    return;
                }
                const ctx = document.getElementById('appointmentStatusChart').getContext('2d');
                if(appointmentStatusChart) appointmentStatusChart.destroy();
                appointmentStatusChart = new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Count',
                            data: data.data,
                            backgroundColor: [
                                'rgba(59, 130, 246, 0.7)',  // Scheduled
                                'rgba(16, 185, 129, 0.7)',  // Completed
                                'rgba(239, 68, 68, 0.7)'   // Cancelled
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            function renderWoundTypeChart(data) {
                if (!data || data.labels.length === 0) {
                    woundTypeContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No active wound data available.</p>';
                    return;
                }
                const ctx = document.getElementById('woundTypeChart').getContext('2d');
                if(woundTypeChart) woundTypeChart.destroy();
                woundTypeChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Count',
                            data: data.data,
                            backgroundColor: [
                                'rgba(239, 68, 68, 0.7)',
                                'rgba(59, 130, 246, 0.7)',
                                'rgba(245, 158, 11, 0.7)',
                                'rgba(16, 185, 129, 0.7)',
                                'rgba(139, 92, 246, 0.7)',
                                'rgba(107, 114, 128, 0.7)'
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                            }
                        }
                    }
                });
            }

            function renderClinicianActivityTable(data) {
                if (!data || data.length === 0) {
                    clinicianActivityContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No clinician activity data available.</p>';
                    return;
                }
                const tableRows = data.map(row => `
            <tr class="border-b border-gray-200 hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap font-semibold">${row.clinician_name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${row.assigned_patients}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${row.completed_appointments}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${row.total_wounds_managed}</td>
            </tr>
        `).join('');

                clinicianActivityContainer.innerHTML = `
            <table class="min-w-full">
                <thead class="bg-gray-800 text-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Clinician</th>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Assigned Patients</th>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Completed Visits</th>
                        <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Wounds Managed</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${tableRows}
                </tbody>
            </table>
        `;
            }

            function renderVisitFrequencyChart(data) {
                if (!data || data.labels.length === 0) {
                    visitFrequencyContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No visit frequency data available.</p>';
                    return;
                }

                const ctx = document.getElementById('visitFrequencyChart').getContext('2d');
                if(visitFrequencyChart) visitFrequencyChart.destroy();
                visitFrequencyChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Number of Patients',
                            data: data.data,
                            backgroundColor: 'rgba(139, 92, 246, 0.7)',
                            borderColor: 'rgba(139, 92, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Patients'
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }


            // Initial Load
            fetchReportData();
            populatePatientSelector();
        });
    </script>

<?php require_once 'templates/footer.php'; ?>