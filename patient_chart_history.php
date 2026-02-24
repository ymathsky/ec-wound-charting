<?php
// Filename: ec/patient_chart_history.php

require_once 'templates/header.php';
?>

    <!-- Include Lucide Icons for UI consistency -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- Include FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />

    <div class="flex h-screen bg-gray-50 font-sans">
        <?php 
        if (!isset($_GET['layout']) || $_GET['layout'] !== 'modal') {
            require_once 'templates/sidebar.php'; 
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 id="patient-name-header" class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="history" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Past Charting
                    </h1>
                    <p id="patient-subheader" class="text-sm text-gray-500 mt-1 ml-10">Review of all historical visit notes for the patient.</p>
                </div>
                <!-- Future enhancement: Add Search/Filter button here -->
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <!-- Main Content Area uses p-6/p-8 padding and stretched layout -->
            <main class="flex-1 overflow-y-auto bg-gray-50 p-6 sm:p-8">

                <div class="space-y-6">

                    <!-- NEW ROW 1: Patient Demographics Band (Full Width) -->
                    <div id="patient-demographics-band" class="w-full">
                        <!-- Skeleton Loader for Demographics -->
                        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4 animate-pulse">
                            <div class="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
                            <div class="space-y-2">
                                <div class="h-3 bg-gray-100 rounded w-full"></div>
                                <div class="h-3 bg-gray-100 rounded w-5/6"></div>
                            </div>
                        </div>
                    </div>

                    <!-- ROW 2: Master List and Detail View (Constrained to vertical height) -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                        <!-- COLUMN 1: Master Visit List (lg:col-span-3) -->
                        <div class="lg:col-span-3 h-full-constrained">
                            <div class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col h-full">
                                <h4 class="text-lg font-semibold text-gray-800 p-4 border-b sticky top-0 bg-white z-10 rounded-t-xl">Visit History</h4>
                                <div id="visit-list-container" class="flex-1 overflow-y-auto divide-y divide-gray-100 min-h-[100px]">
                                    <!-- List items injected here -->
                                    <div class="p-4 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading list...
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- COLUMN 2: Detail View (lg:col-span-9 - Takes up remaining width) -->
                        <div id="visit-detail-panel" class="lg:col-span-9 h-full-constrained">
                            <div id="visit-detail-content" class="bg-white rounded-xl shadow-lg border border-gray-100 p-6 h-full overflow-y-auto flex items-center justify-center text-gray-400 italic">
                                Select a visit from the list to view details.
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <style>
        /* New Styles for Master/Detail View */
        /* This calc accounts for the header height (~60px), main padding (~64px total), and the height of the new demographics band (~120px) */
        .h-full-constrained {
            min-height: calc(100vh - 250px);
            max-height: calc(100vh - 250px);
        }

        .visit-list-item {
            padding: 1rem;
            cursor: pointer;
            transition: background-color 0.15s, border-left 0.15s;
            border-left: 3px solid transparent;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .visit-list-item:hover {
            background-color: #f3f4f6; /* gray-100 */
        }
        .visit-list-item.active {
            background-color: #eef2ff; /* indigo-50 */
            border-left: 3px solid #4f46e5; /* indigo-600 */
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .soap-section-content {
            padding-left: 1rem;
            border-left: 3px solid #e0e7ff; /* Indigo-100 line */
        }
        /* Date Block Styling */
        .visit-date-block {
            width: 45px;
            flex-shrink: 0;
            text-align: center;
            background-color: #f7f7f9;
            border-radius: 4px;
            padding: 4px 0;
            font-weight: 700;
            border: 1px solid #e5e7eb;
        }
        .visit-date-block .month {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #6b7280;
        }
        .visit-date-block .day {
            font-size: 1.1rem;
            line-height: 1;
            color: #1f2937;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const patientId = <?php echo isset($_GET['id']) ? intval($_GET['id']) : 0; ?>;
            // Declare all top-level variables explicitly
            const patientNameHeader = document.getElementById('patient-name-header');
            const demographicsBand = document.getElementById('patient-demographics-band'); // New Demographics target
            const visitListContainer = document.getElementById('visit-list-container');
            const detailPanel = document.getElementById('visit-detail-content');

            let historyData = []; // Full raw data store

            function calculateAge(dobString) {
                if (!dobString) return '?';
                const dob = new Date(dobString);
                const diff_ms = Date.now() - dob.getTime();
                const age_dt = new Date(diff_ms);
                return Math.abs(age_dt.getUTCFullYear() - 1970);
            }

            async function fetchChartHistory() {
                if (patientId <= 0) {
                    visitListContainer.innerHTML = `<p class="text-center text-red-500 p-4">Invalid Patient ID.</p>`;
                    detailPanel.innerHTML = '';
                    return;
                }

                try {
                    // 1. Fetch patient details for demographics and header
                    const patientResponse = await fetch(`api/get_patient_profile_data.php?id=${patientId}`);
                    if(!patientResponse.ok) throw new Error('Failed to fetch patient details.');
                    const patientData = await patientResponse.json();
                    const p = patientData.details;

                    if (patientNameHeader) {
                        patientNameHeader.innerHTML = `<i data-lucide="history" class="w-7 h-7 mr-3 text-indigo-600"></i> Past Charting: ${p.first_name} ${p.last_name}`;
                    }
                    renderDemographics(p);

                    // 2. Fetch chart history
                    const response = await fetch(`api/get_patient_chart_history.php?id=${patientId}`);
                    if (!response.ok) throw new Error('Failed to fetch chart history.');
                    const history = await response.json();

                    historyData = Array.isArray(history) ? history : [];
                    // Sort by date descending (most recent first)
                    historyData.sort((a, b) => new Date(b.appointment_date) - new Date(a.appointment_date));

                    renderMasterList(historyData);

                    // Automatically show the latest entry (if available)
                    if (historyData.length > 0) {
                        loadVisitDetail(historyData[0].appointment_id);
                        // Ensure the element exists before attempting to set class
                        const firstItem = document.getElementById(`list-item-${historyData[0].appointment_id}`);
                        if (firstItem) {
                            firstItem.classList.add('active');
                        }
                    } else {
                        detailPanel.innerHTML = '<div class="p-6 text-gray-500 italic">No visit notes available for this patient.</div>';
                    }

                } catch (error) {
                    visitListContainer.innerHTML = `<p class="text-center text-red-500 p-4">Error loading chart history: ${error.message}</p>`;
                    detailPanel.innerHTML = '<div class="p-6 text-red-500">Could not load details.</div>';
                }
                lucide.createIcons();
            }

            function renderDemographics(patient) {
                // Updated render function targeting the full width band
                demographicsBand.innerHTML = `
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 text-sm">

                        <!-- Name/DOB (Main Info) -->
                    <div class="border-r border-gray-100 pr-4">
                    <p class="font-bold text-lg text-gray-900">${patient.first_name} ${patient.last_name}</p>
                    <p class="text-xs text-gray-500">${patient.date_of_birth} (${calculateAge(patient.date_of_birth)})</p>
                    </div>

                    <!-- ID/Gender -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Patient ID / Gender</span>
                    <p class="font-medium text-gray-900">${patient.patient_code || 'N/A'} / ${patient.gender}</p>
                    </div>

                    <!-- Primary MD -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Primary Clinician</span>
                    <p class="font-medium text-indigo-600">${patient.primary_doctor_name || 'Unassigned'}</p>
                    </div>

                    <!-- Phone -->
                    <div>
                    <span class="block text-xs font-medium text-gray-400 uppercase">Phone</span>
                    <p class="font-medium text-gray-900">${patient.contact_number || '--'}</p>
                    </div>

                    <!-- Allergies -->
                    <div class="col-span-full md:col-span-1 border-l border-gray-100 pl-4 bg-red-50 rounded-lg">
                    <span class="block text-xs font-medium text-red-700 uppercase">Allergies</span>
                    <p class="text-sm font-semibold text-red-800">${patient.allergies || 'NKDA'}</p>
                    </div>

                    <!-- Medical History Summary -->
                    <div class="col-span-full md:col-span-1 border-l border-gray-100 pl-4 bg-gray-50 rounded-lg">
                    <span class="block text-xs font-medium text-gray-600 uppercase">PMH Summary</span>
                    <p class="text-xs font-medium text-gray-800 truncate">${patient.past_medical_history || 'N/A'}</p>
                    </div>
                    </div>
                    </div>
                    `;
        }

        function renderMasterList(history) {
            if (history.length === 0) {
                visitListContainer.innerHTML = '<p class="text-center text-gray-500 p-4">No visits recorded.</p>';
                return;
            }

            const listHtml = history.map(entry => {
                const dateObj = new Date(entry.appointment_date);
                const day = dateObj.getDate();
                const month = dateObj.toLocaleDateString('en-US', { month: 'short' });
                const time = dateObj.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const clinicianName = entry.clinician_name || 'Unassigned';
                const isCompleted = entry.status === 'Completed';
                const statusClass = isCompleted ? 'text-green-600' : 'text-gray-500';

                return `
                    <div id="list-item-${entry.appointment_id}"
                    class="visit-list-item"
                    onclick="loadVisitDetail(${entry.appointment_id}, this)">

                    <!-- NEW: Date Block -->
                    <div class="visit-date-block">
                    <span class="month">${month}</span>
                    <span class="day">${day}</span>
                    </div>

                    <!-- Visit Summary -->
                    <div class="flex-1">
                    <p class="font-bold text-gray-900 leading-tight">${entry.appointment_type || 'Visit'}</p>
                    <p class="text-xs text-gray-500">${time}</p>
                    <p class="text-xs text-gray-600 truncate mt-1">
                    <i class="fas fa-user-md mr-1"></i> ${clinicianName}
                    </p>
                    <p class="text-xs ${statusClass} font-semibold flex items-center mt-1">
                    <i class="fas fa-circle text-[8px] mr-1 ${isCompleted ? 'text-green-500' : 'text-orange-400'}"></i>
                    ${entry.status || 'Draft'}
                    </p>
                    </div>
                    </div>
                    `;
            }).join('');

            visitListContainer.innerHTML = listHtml;
        }

        // --- Detail Panel Logic Helpers (Omitting helpers for brevity, but they remain) ---

        function generateAssessmentSummary(asm) {
            let summaryParts = [];

            if (asm.length_cm && asm.width_cm) {
                let measurement = `<span class="font-bold">Size:</span> ${asm.length_cm} x ${asm.width_cm}`;
                if (asm.depth_cm) measurement += ` x ${asm.depth_cm}`;
                measurement += ' cm.';
                summaryParts.push(measurement);
            }
            if (asm.granulation_percent != null || asm.slough_percent != null) {
                let tissueSummary = `<span class="font-bold">Tissue:</span> `;
                if (asm.granulation_percent != null) tissueSummary += `<span class="${asm.granulation_percent < 50 ? 'text-orange-600' : 'text-green-600'}">${asm.granulation_percent}% Gran</span>`;
                if (asm.slough_percent != null) tissueSummary += `, ${asm.slough_percent}% Slough`;
                let cleanedSummary = tissueSummary.replace(':,', ':');
                summaryParts.push(cleanedSummary);
            }
            if (asm.exudate_amount && asm.exudate_type) {
                summaryParts.push(`<span class="font-bold">Drainage:</span> ${asm.exudate_amount} ${asm.exudate_type}`);
            }
            if (asm.odor_present === 'Yes') {
                 summaryParts.push(`<span class="font-bold text-red-600">Odor:</span> Yes`);
            }

            if (summaryParts.length === 0) return 'No detailed assessment data recorded.';
            return summaryParts.join(' | ');
        }

        window.loadVisitDetail = function(appointmentId, element) {
            // Highlight the active item
            document.querySelectorAll('.visit-list-item').forEach(item => item.classList.remove('active'));
            if (element) {
                element.classList.add('active');
            }

            // Clear and show loading state
            detailPanel.innerHTML = `<div class="p-6 flex items-center justify-center text-gray-500 h-full"><i class="fas fa-spinner fa-spin mr-2"></i> Loading details...</div>`;

            const entry = historyData.find(e => e.appointment_id === appointmentId);

            if (!entry) {
                detailPanel.innerHTML = `<div class="p-6 text-red-500 h-full">Error: Visit data not found.</div>`;
                return;
            }

            // --- Aggregate Wound Data ---
            const woundsInVisit = {};
            if (entry.wound_assessments) {
                entry.wound_assessments.forEach(asm => {
                    if (!woundsInVisit[asm.wound_id]) woundsInVisit[asm.wound_id] = { location: asm.location, assessments: [], images: [] };
                    woundsInVisit[asm.wound_id].assessments.push(asm);
                });
            }
            if (entry.wound_images) {
                entry.wound_images.forEach(img => {
                    const woundId = img.wound_id;
                    if (!woundsInVisit[woundId]) {
                        const defaultLocation = img.location || 'Wound ID ' + woundId;
                        woundsInVisit[woundId] = { location: defaultLocation, assessments: [], images: [] };
                    }
                    woundsInVisit[woundId].images.push(img);
                });
            }

            const woundDetailsHtml = Object.keys(woundsInVisit).map(woundId => {
                const wound = woundsInVisit[woundId];
                const latestAssessment = wound.assessments[0] || null;
                const assessmentSummary = latestAssessment ? generateAssessmentSummary(latestAssessment) : 'No measurement taken.';

                return `
                    <div class="mt-4 pt-4 border-t border-gray-200">
                    <h4 class="font-semibold text-indigo-700 text-base flex items-center mb-2">
                    <i data-lucide="bandage" class="w-5 h-5 mr-2"></i> ${wound.location}
                    </h4>
                    <div class="bg-gray-50 p-3 rounded-lg text-sm space-y-1">
                    <p>${assessmentSummary}</p>
                    ${latestAssessment && latestAssessment.signs_of_infection ? `<p class="text-red-500">Signs of Infection: ${latestAssessment.signs_of_infection}</p>` : ''}
                        </div>

                        <h5 class="text-xs font-bold text-gray-500 uppercase mt-4 mb-2">Images (${wound.images.length})</h5>
                        <div class="grid grid-cols-4 gap-2">
                           ${wound.images.map(img => `
                               <div class="border rounded-md overflow-hidden shadow-sm">
                                   <a href="${img.image_path}" target="_blank">
                                       <img src="${img.image_path}" class="w-full h-16 object-cover" alt="${img.image_type}" onerror="this.onerror=null; this.src='https://placehold.co/100x100/e0e0e0/555555?text=Img+Error';">
                                   </a>
                                   <p class="text-xs p-1 text-center bg-gray-100 text-gray-600 truncate">${img.image_type}</p>
                               </div>
                           `).join('')}
                        </div>
                    </div>`;
                }).join('');

        // --- Render Detail Panel ---
        detailPanel.innerHTML = `
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 h-full overflow-y-auto">
                    <div class="p-5 border-b flex justify-between items-center bg-indigo-50 rounded-t-xl sticky top-0 z-10">
                        <h2 class="text-2xl font-bold text-indigo-800">${new Date(entry.appointment_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })}</h2>
                        <a href="visit_report.php?appointment_id=${entry.appointment_id}&patient_id=${patientId}" class="text-indigo-800 hover:text-indigo-900 text-sm font-medium flex items-center bg-indigo-100 px-3 py-1 rounded-full">
                            <i data-lucide="printer" class="w-4 h-4 mr-1"></i> Print/View Full Report
                        </a>
                    </div>

                    <div class="p-5 space-y-5">

                        <h3 class="text-lg font-semibold text-gray-800">SOAP Note Content</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="detail-card">
                                <h4 class="font-bold text-gray-800 border-b pb-1 mb-2">S: Subjective</h4>
                                <div class="soap-section-content text-gray-700 whitespace-pre-wrap text-sm">${entry.subjective || 'N/A'}</div>
                            </div>
                            <div class="detail-card">
                                <h4 class="font-bold text-gray-800 border-b pb-1 mb-2">O: Objective</h4>
                                <div class="soap-section-content text-gray-700 whitespace-pre-wrap text-sm">${entry.objective || 'N/A'}</div>
                            </div>
                            <div class="detail-card md:col-span-2">
                                <h4 class="font-bold text-gray-800 border-b pb-1 mb-2">A: Assessment</h4>
                                <div class="soap-section-content text-gray-700 whitespace-pre-wrap text-sm">${entry.assessment || 'N/A'}</div>
                            </div>
                            <div class="detail-card md:col-span-2">
                                <h4 class="font-bold text-gray-800 border-b pb-1 mb-2">P: Plan</h4>
                                <div class="soap-section-content text-gray-700 whitespace-pre-wrap text-sm">${entry.plan || 'N/A'}</div>
                            </div>
                        </div>

                        <div class="border-t pt-5">
                            <h3 class="text-lg font-semibold text-gray-800">Wound & Image History</h3>
                            ${woundDetailsHtml}
                        </div>
                    </div>
                </div>
            `;
        // Re-create lucide icons after injecting HTML
        lucide.createIcons();
        }

        fetchChartHistory();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>