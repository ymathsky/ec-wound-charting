<?php
// Filename: add_appointment.php

// Check if we are in "Modal Mode" (embedded in MDI iframe)
$is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';

require_once 'templates/header.php';
require_once 'db_connect.php';
// Include audit log function if needed for appointment creation, assuming it's in a central file
// require_once 'audit_log_function.php';
?>

    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>


    <style>
        /* Custom styles for the calendar and time slots */
        #calendar {
            max-height: 70vh;
        }
        /* Floating Alert Styles */
        #floating-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            opacity: 0;
            transform: translateY(-20px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            pointer-events: none; /* Allow clicks through when hidden */
        }
        #floating-alert.show {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        #floating-alert.error {
            background-color: #FEF2F2; /* red-50 */
            border-left: 4px solid #EF4444; /* red-500 */
            color: #991B1B; /* red-800 */
        }
        #floating-alert.success {
            background-color: #ECFDF5; /* green-50 */
            border-left: 4px solid #10B981; /* green-500 */
            color: #065F46; /* green-800 */
        }
        .time-slot {
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            border: 1px solid #e5e7eb;
        }
        .time-slot:hover {
            background-color: #eff6ff; /* blue-50 */
            border-color: #3b82f6; /* blue-500 */
            transform: translateY(-2px);
        }
        .time-slot.booked {
            background-color: #fee2e2; /* red-100 */
            color: #991b1b; /* red-800 */
            cursor: not-allowed;
            text-decoration: line-through;
            border-color: #fca5a5; /* red-300 */
        }
        .time-slot.selected {
            background-color: #3b82f6; /* blue-500 */
            color: white;
            font-weight: bold;
            border-color: #2563eb; /* blue-600 */
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .fc-daygrid-day.fc-day-today {
            background-color: #eff6ff !important;
        }
        .step-header {
            display: flex;
            align-items: center;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937; /* gray-800 */
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .step-header i {
            margin-right: 0.75rem;
            color: #4b5563; /* gray-600 */
        }
        /* Style for disabled select */
        select:disabled {
            background-color: #f3f4f6; /* bg-gray-100 */
            cursor: not-allowed;
        }
        /* Spinner for loading time slots AND button */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #3b82f6;
            animation: spin 1s ease infinite;
            margin: 0 auto;
        }
        .button-spinner {
            border: 3px solid rgba(255, 255, 255, 0.3);
            width: 1.25rem; /* Equivalent to w-5 */
            height: 1.25rem; /* Equivalent to h-5 */
            border-radius: 50%;
            border-top-color: #ffffff; /* White */
            animation: spin 1s ease infinite;
            display: inline-block;
            margin-right: 0.5rem;
            vertical-align: middle;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <div class="flex h-screen bg-gray-50">
        <?php 
        // Only show sidebar if NOT in modal/MDI mode
        if (!$is_modal_mode) {
            require_once 'templates/sidebar.php';
        }
        ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="calendar-plus" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Schedule New Appointment
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Follow the steps below to book a new visit.</p>
                </div>
                <!-- No buttons needed here -->
            </header>
            <!-- END: UPDATED HEADER STYLE -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
                <!-- Floating Alert Container -->
                <div id="floating-alert" class="hidden flex items-start">
                    <div class="flex-shrink-0 mr-3">
                        <i id="alert-icon" data-lucide="alert-circle" class="w-5 h-5"></i>
                    </div>
                    <div class="flex-1">
                        <h3 id="alert-title" class="text-sm font-medium">Alert</h3>
                        <div id="alert-message" class="mt-1 text-sm"></div>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button id="alert-close" class="inline-flex text-gray-400 hover:text-gray-500 focus:outline-none">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto">
                    <div id="form-message" class="hidden p-4 mb-4 rounded-lg shadow-lg"></div>
                    <form id="addAppointmentForm" class="space-y-8">

                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h2 class="step-header">
                                <i data-lucide="users"></i>
                                Step 1: Select Patient & Provider
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="user_id" class="form-label font-semibold">Select Clinician</label>
                                    <select name="user_id" id="user_id" required class="form-input bg-white mt-2">
                                        <option value="">Loading clinicians...</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="patient_id" class="form-label font-semibold">Select Patient</label>
                                    <select name="patient_id" id="patient_id" required class="form-input bg-white mt-2" disabled>
                                        <option value="">Select a clinician first</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h2 class="step-header">
                                <i data-lucide="clipboard-list"></i>
                                Step 2: Define Visit Details
                            </h2>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="appointment_type" class="form-label font-semibold">Appointment Type</label>
                                    <select name="appointment_type" id="appointment_type" required class="form-input bg-white mt-2">
                                        <option>Follow Up Visit</option>
                                        <option>New Patient Visit</option>
                                        <option>Urgent Visit</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="notes" class="form-label font-semibold">Appointment Notes (Optional)</label>
                                    <textarea name="notes" id="notes" rows="3" class="form-input mt-2" placeholder="e.g., Patient requested morning slot..."></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h2 class="step-header">
                                <i data-lucide="calendar-days"></i>
                                Step 3: Choose Date & Time
                            </h2>
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                <div class="lg:col-span-2">
                                    <p class="text-sm text-gray-600 mb-2">Select a day from the calendar below.</p>
                                    <div id='calendar' class="border rounded-lg p-2"></div>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-600 mb-2">Select an available time for <strong id="selectedDate" class="text-blue-600">no date selected</strong>.</p>
                                    <div id="time-slots-container" class="bg-gray-50 p-4 rounded-lg h-96 overflow-y-auto border">
                                        <p class="text-center text-gray-500 pt-16">Please select a clinician and a date to see available times.</p>
                                    </div>
                                    <input type="hidden" name="appointment_time" id="appointment_time">
                                </div>
                            </div>
                        </div>

                        <div class="pt-4">
                            <button type="submit" id="submitButton" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-4 rounded-lg transition-transform transform hover:scale-105 text-lg shadow-xl" data-original-text="Confirm & Save Appointment">
                                Confirm & Save Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons(); // Initialize icons
            const form = document.getElementById('addAppointmentForm');
            const submitButton = document.getElementById('submitButton'); // Get the submit button
            const messageDiv = document.getElementById('form-message');
            const patientSelect = document.getElementById('patient_id');
            const clinicianSelect = document.getElementById('user_id');
            const calendarEl = document.getElementById('calendar');
            const timeSlotsContainer = document.getElementById('time-slots-container');
            const selectedDateSpan = document.getElementById('selectedDate');
            const appointmentTimeInput = document.getElementById('appointment_time');
            
            // Floating Alert Elements
            const floatingAlert = document.getElementById('floating-alert');
            const alertTitle = document.getElementById('alert-title');
            const alertMessage = document.getElementById('alert-message');
            const alertIcon = document.getElementById('alert-icon');
            const alertClose = document.getElementById('alert-close');
            let alertTimeout;

            function showFloatingAlert(title, message, type = 'error') {
                // Reset classes
                floatingAlert.className = 'flex items-start'; // Base classes
                floatingAlert.classList.remove('hidden', 'error', 'success', 'show');
                
                // Set content
                alertTitle.textContent = title;
                alertMessage.textContent = message;
                
                // Apply type styles
                if (type === 'success') {
                    floatingAlert.classList.add('success');
                    // Update icon (need to re-render lucide if changing icon name dynamically, 
                    // but for simplicity we'll just change color or keep generic alert icon)
                    // Ideally: alertIcon.setAttribute('data-lucide', 'check-circle'); lucide.createIcons();
                } else {
                    floatingAlert.classList.add('error');
                }
                
                // Show animation
                // Small delay to allow display:block to apply before opacity transition
                setTimeout(() => {
                    floatingAlert.classList.add('show');
                }, 10);

                // Auto hide after 5 seconds
                clearTimeout(alertTimeout);
                alertTimeout = setTimeout(() => {
                    hideFloatingAlert();
                }, 5000);
            }

            function hideFloatingAlert() {
                floatingAlert.classList.remove('show');
                setTimeout(() => {
                    floatingAlert.classList.add('hidden');
                }, 300); // Wait for transition
            }

            alertClose.addEventListener('click', hideFloatingAlert);

            // Get the application timezone from PHP
            const appTimezone = '<?php echo $app_timezone; ?>';

            let calendar;
            let selectedDate = null;
            let selectedClinicianId = null;

            // --- New Functions for Loading State ---
            function setSubmitButtonLoading(isLoading) {
                if (isLoading) {
                    // Store the original text to restore it later
                    submitButton.setAttribute('data-original-text', submitButton.textContent.trim());
                    submitButton.innerHTML = '<span class="button-spinner"></span> Submitting...';
                    submitButton.disabled = true;
                    submitButton.classList.add('bg-blue-400', 'hover:bg-blue-400');
                    submitButton.classList.remove('bg-blue-600', 'hover:bg-blue-700', 'hover:scale-105');
                } else {
                    submitButton.innerHTML = submitButton.getAttribute('data-original-text');
                    submitButton.disabled = false;
                    submitButton.classList.remove('bg-blue-400', 'hover:bg-blue-400');
                    submitButton.classList.add('bg-blue-600', 'hover:bg-blue-700', 'hover:scale-105');
                }
            }

            /**
             * Fetches and populates the patient dropdown based on the selected clinician.
             * This replaces the old client-side filtering approach.
             * @param {string} clinicianId - The ID of the selected clinician.
             */
            async function fetchAndPopulatePatients(clinicianId) {
                patientSelect.innerHTML = '<option value="">Loading patients...</option>';
                patientSelect.disabled = true;

                if (!clinicianId) {
                    patientSelect.innerHTML = '<option value="">Select a clinician first</option>';
                    return;
                }

                try {
                    // Call the new dedicated API, passing the selected clinician's ID
                    // NOTE: The previous file name used was api/get_patients_by_clinician.php
                    const response = await fetch(`api/get_patients_by_clinician.php?user_id=${clinicianId}`);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success && data.patients.length > 0) {
                        patientSelect.innerHTML = '<option value="">Select a Patient</option>';
                        data.patients.forEach(patient => {
                            const option = document.createElement('option');
                            option.value = patient.id;
                            option.textContent = patient.text;
                            patientSelect.appendChild(option);
                        });
                        patientSelect.disabled = false;

                        // Attempt to pre-select if patient ID is in URL
                        const urlParams = new URLSearchParams(window.location.search);
                        const preselectedPatientId = urlParams.get('patient_id');
                        if (preselectedPatientId && patientSelect.querySelector(`option[value="${preselectedPatientId}"]`)) {
                            patientSelect.value = preselectedPatientId;
                        }

                    } else {
                        patientSelect.innerHTML = '<option value="">No patients assigned to this clinician.</option>';
                    }

                } catch (error) {
                    console.error('Error loading assigned patients:', error);
                    patientSelect.innerHTML = '<option value="">Error loading patients</option>';
                }
            }

            /**
             * Fetches and populates the list of available clinicians.
             */
            async function populateClinicians() {
                try {
                    const response = await fetch('api/get_users.php');
                    if (!response.ok) throw new Error('Failed to fetch clinicians');
                    const data = await response.json();

                    clinicianSelect.innerHTML = '<option value="">Select a clinician</option>';
                    data.forEach(item => {
                        const option = document.createElement('option');
                        option.value = item['user_id'];
                        option.textContent = item['full_name'];
                        clinicianSelect.appendChild(option);
                    });
                } catch (error) {
                    clinicianSelect.innerHTML = '<option value="">Could not load clinicians</option>';
                    console.error(error);
                }
            }

            function initializeCalendar() {
                // Calculate "Today" in the application's configured timezone
                const todayInAppTimezone = new Date().toLocaleDateString('en-CA', { timeZone: appTimezone });

                calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth'
                    },
                    dateClick: function(info) {
                        selectedDate = info.dateStr;
                        selectedDateSpan.textContent = new Date(selectedDate + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                        fetchAndRenderAvailability();

                        // Visual feedback for selected date
                        document.querySelectorAll('.fc-daygrid-day').forEach(dayEl => dayEl.style.backgroundColor = '');
                        info.dayEl.style.backgroundColor = '#dbeafe'; // blue-100
                    },
                    validRange: {
                        start: todayInAppTimezone // Disable past dates based on App Timezone
                    }
                });
                calendar.render();
            }

            async function fetchAndRenderAvailability() {
                if (!selectedDate || !selectedClinicianId) {
                    timeSlotsContainer.innerHTML = '<p class="text-center text-gray-500 pt-16">Please select a clinician and a date to see available times.</p>';
                    return;
                }

                timeSlotsContainer.innerHTML = '<div class="flex justify-center items-center h-full"><div class="spinner"></div></div>';

                try {
                    const response = await fetch(`api/get_doctor_availability.php?user_id=${selectedClinicianId}&date=${selectedDate}`);
                    const bookedTimes = await response.json();

                    renderTimeSlots(bookedTimes);
                } catch (error) {
                    timeSlotsContainer.innerHTML = `<p class="text-red-500 text-center">Error fetching availability.</p>`;
                }
            }

            function renderTimeSlots(bookedTimes) {
                timeSlotsContainer.innerHTML = '';
                let availableSlots = 0;

                // Get current date/time in App Timezone
                const now = new Date(new Date().toLocaleString('en-US', { timeZone: appTimezone }));
                const isToday = selectedDate === now.toLocaleDateString('en-CA'); // YYYY-MM-DD comparison

                // 8 AM to 5 PM, in 30-minute intervals
                for (let hour = 8; hour < 17; hour++) {
                    for (let minute = 0; minute < 60; minute += 30) {
                        const time = `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
                        let isBooked = bookedTimes.includes(time);
                        let isPast = false;

                        // Check if time is in the past (only if selected date is today)
                        if (isToday) {
                            const slotTime = new Date(now);
                            slotTime.setHours(hour, minute, 0, 0);
                            if (slotTime < now) {
                                isPast = true;
                            }
                        }

                        if (!isBooked && !isPast) availableSlots++;

                        const slotDiv = document.createElement('div');
                        // Format time display
                        const displayHour = hour % 12 || 12;
                        const ampm = hour < 12 ? 'AM' : 'PM';
                        slotDiv.textContent = `${displayHour}:${String(minute).padStart(2, '0')} ${ampm}`;

                        slotDiv.dataset.time = time;
                        
                        // Apply classes based on status
                        if (isBooked) {
                            slotDiv.className = `time-slot p-3 rounded-md text-sm font-medium text-center shadow-sm booked`;
                            slotDiv.title = "Already booked";
                        } else if (isPast) {
                            slotDiv.className = `time-slot p-3 rounded-md text-sm font-medium text-center shadow-sm bg-gray-100 text-gray-400 cursor-not-allowed border-gray-200`;
                            slotDiv.title = "Time has passed";
                        } else {
                            slotDiv.className = `time-slot p-3 rounded-md text-sm font-medium text-center shadow-sm`;
                            slotDiv.addEventListener('click', function() {
                                document.querySelectorAll('.time-slot.selected').forEach(el => el.classList.remove('selected'));
                                this.classList.add('selected');
                                appointmentTimeInput.value = this.dataset.time;
                            });
                        }

                        timeSlotsContainer.appendChild(slotDiv);
                    }
                }
                if (availableSlots === 0) {
                    timeSlotsContainer.innerHTML = '<p class="text-center text-gray-600 font-semibold p-8">No available slots for this clinician on the selected date.</p>';
                }
            }

            // --- Event Listener for Clinician Selection ---
            clinicianSelect.addEventListener('change', function() {
                selectedClinicianId = this.value;

                // 1. Filter and populate the Patient dropdown using the new API
                fetchAndPopulatePatients(selectedClinicianId);

                // 2. Clear any selected time and update availability based on the new clinician
                appointmentTimeInput.value = '';
                fetchAndRenderAvailability();
            });

            form.addEventListener('submit', async function(event) {
                event.preventDefault();

                const formData = new FormData(form);
                const appointmentData = {
                    patient_id: formData.get('patient_id'),
                    user_id: formData.get('user_id'),
                    appointment_type: formData.get('appointment_type'),
                    notes: formData.get('notes'),
                    appointment_date: `${selectedDate}T${formData.get('appointment_time')}`
                };

                if (!appointmentData.appointment_date.includes(':')) {
                    messageDiv.textContent = 'Please select an available time slot.';
                    messageDiv.className = 'p-4 mb-4 rounded-lg shadow-lg bg-yellow-100 text-yellow-800';
                    messageDiv.classList.remove('hidden');
                    return;
                }

                // START: Set loading state on submit
                setSubmitButtonLoading(true);

                try {
                    const response = await fetch('api/create_appointment.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(appointmentData)
                    });
                    const result = await response.json();

                    if (!response.ok) throw new Error(result.message);

                    // Success Alert
                    showFloatingAlert('Success', result.message, 'success');
                    
                    form.reset();

                    // Reset UI state after successful submission
                    patientSelect.disabled = true;
                    patientSelect.innerHTML = '<option value="">Select a clinician first</option>';
                    timeSlotsContainer.innerHTML = '<p class="text-center text-gray-500">Please select a clinician and a date to see available times.</p>';
                    selectedDateSpan.textContent = 'No date selected';
                    document.querySelectorAll('.fc-daygrid-day').forEach(dayEl => dayEl.style.backgroundColor = '');

                    setTimeout(() => {
                        console.log('[Appointment] Post-save navigation started');
                        
                        // Check if we are inside an MDI iframe
                        if (window.parent && window.parent !== window && window.parent.mdiManager) {
                            console.log('[Appointment] MDI mode detected');
                            
                            const manager = window.parent.mdiManager;
                            const currentTabId = manager.activeTabId;
                            const currentTab = manager.tabs.find(t => t.id === currentTabId);
                            
                            console.log('[Appointment] Current tab:', currentTab);
                            
                            // Simply update the URL - the tab stays the same, just the content changes
                            const calendarUrl = 'appointments_calendar.php?layout=modal';
                            
                            if (currentTab) {
                                // Update the current tab's metadata
                                currentTab.url = calendarUrl;
                                currentTab.title = 'Appointments';
                                currentTab.icon = 'calendar-days';
                                
                                console.log('[Appointment] Updated tab metadata');
                                
                                // Save changes
                                manager.saveTabsToStorage();
                                manager.renderTabs();
                                
                                // Get the iframe and navigate it DIRECTLY from parent context
                                // This completely bypasses any child frame navigation interception
                                const iframe = window.parent.document.getElementById('mdi-content-frame');
                                if (iframe) {
                                    console.log('[Appointment] Navigating iframe to:', calendarUrl);
                                    // Set the src directly - NO timestamp to avoid creating "new" URL
                                    iframe.src = calendarUrl;
                                } else {
                                    console.error('[Appointment] Iframe element not found!');
                                }
                            } else {
                                console.error('[Appointment] Current tab not found! Tab ID:', currentTabId);
                            }
                        } else {
                            console.log('[Appointment] Non-MDI mode - regular redirect');
                            window.location.href = 'appointments_calendar.php';
                        }
                    }, 1500);

                } catch (error) {
                    // Error Alert
                    showFloatingAlert('Error', error.message, 'error');
                    
                    // Re-enable button on error so user can try again
                    setSubmitButtonLoading(false);
                }
                // Note: We do NOT re-enable the button in a finally block or on success.
                // This prevents double-clicking during the 2-second redirect delay.
            });

            // Initial population calls
            populateClinicians();
            initializeCalendar();

            // Removed fetchAllPatientsAndDisable(); as we now fetch dynamically on clinician select.
        });
    </script>

<?php
require_once 'templates/footer.php';
?>