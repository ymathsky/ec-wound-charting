<?php
session_start();
if (!isset($_SESSION['portal_patient_id'])) {
    header("Location: login.php");
    exit();
}

$patient_name = $_SESSION['portal_patient_name'];
$active_page = 'appointments';
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments | Patient Portal</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<?php require_once 'nav_panel.php'; ?>

<div class="page-wrapper">
    <main class="main-content">
        <div class="container max-w-screen-lg">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    My Appointments
                </h1>
                <button onclick="openRequestModal()" class="btn-primary btn-base w-auto px-4 py-2 text-sm font-medium">
                    <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Request New Appointment
                </button>
            </div>

            <!-- Tabbed Interface -->
            <div class="bg-white p-6 rounded-lg shadow-md card-base">
                <div class="flex border-b border-gray-200 mb-6 gap-2 -mt-6 -mx-6 px-6 pt-4">
                    <button class="tab-button active" data-tab="upcoming">Upcoming & Pending</button>
                    <button class="tab-button" data-tab="history">History</button>
                </div>

                <div id="loading" class="text-center py-12 text-muted">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600 mb-2"></div>
                    <p>Loading appointments...</p>
                </div>

                <div id="content-upcoming" class="tab-content">
                    <table class="appointment-table">
                        <thead>
                        <tr>
                            <th class="w-1/4">Date & Time</th>
                            <th class="w-1/3">Type / Details</th>
                            <th class="w-1/4">Provider</th>
                            <th class="w-1/6">Status</th>
                        </tr>
                        </thead>
                        <tbody id="upcoming-list">
                        <!-- Upcoming/Pending appointments injected here -->
                        </tbody>
                    </table>
                </div>

                <div id="content-history" class="tab-content hidden">
                    <table class="appointment-table">
                        <thead>
                        <tr>
                            <th class="w-1/4">Date & Time</th>
                            <th class="w-1/3">Type / Details</th>
                            <th class="w-1/4">Provider</th>
                            <th class="w-1/6">Status</th>
                        </tr>
                        </thead>
                        <tbody id="history-list">
                        <!-- Past appointments injected here -->
                        </tbody>
                    </table>
                </div>

                <p id="no-appointments-msg" class="hidden text-center text-gray-500 py-8 italic border border-dashed rounded-lg bg-gray-50">
                    <i data-lucide="calendar-off" class="w-6 h-6 mx-auto mb-2 text-gray-400"></i>
                    No appointments found for this view.
                </p>
            </div>
        </div>
    </main>
</div>

<!-- Request Appointment Modal (ENHANCED CALENDAR/TIME) -->
<div id="requestApptModal" class="modal-overlay hidden items-center justify-center">
    <div class="modal-content">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                <i data-lucide="calendar-plus" class="w-5 h-5 mr-2 text-indigo-600"></i>
                Request Appointment Time
            </h3>
            <button onclick="closeRequestModal()" class="text-gray-500 hover:text-gray-800 text-2xl p-1 rounded-full hover:bg-gray-100 transition-colors">&times;</button>
        </div>

        <div id="modal-message" class="hidden p-3 mb-4 rounded-lg text-sm border"></div>

        <form id="requestApptForm">
            <div class="space-y-4">

                <!-- Date Picker -->
                <div>
                    <label for="req_date" class="block text-sm font-medium text-gray-700">Preferred Date</label>
                    <input type="date" id="req_date" name="requested_date" class="form-input" required min="<?php echo $tomorrow; ?>">
                </div>

                <!-- Time Selection (Hour, Minute, AM/PM) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Preferred Time</label>
                    <div class="flex space-x-2">
                        <!-- Hour -->
                        <select id="req_hour" name="requested_hour" class="form-input w-1/3 bg-white" required>
                            <option value="" disabled selected>Hour</option>
                            <?php for ($h = 1; $h <= 12; $h++): ?>
                                <option value="<?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?>"><?php echo $h; ?></option>
                            <?php endfor; ?>
                        </select>

                        <!-- Minute -->
                        <select id="req_minute" name="requested_minute" class="form-input w-1/3 bg-white" required>
                            <option value="" disabled selected>Minute</option>
                            <option value="00">00</option>
                            <option value="15">15</option>
                            <option value="30">30</option>
                            <option value="45">45</option>
                        </select>

                        <!-- AM/PM -->
                        <select id="req_ampm" name="requested_ampm" class="form-input w-1/3 bg-white" required>
                            <option value="" disabled selected>AM/PM</option>
                            <option value="AM">AM</option>
                            <option value="PM">PM</option>
                        </select>
                    </div>
                </div>

                <!-- Hidden Input to compile full datetime for API -->
                <input type="hidden" name="time_preference" id="time_preference_output" value="">

                <div>
                    <label for="req_reason" class="block text-sm font-medium text-gray-700">Reason for Visit</label>
                    <textarea id="req_reason" name="reason" rows="3" class="form-input" placeholder="e.g., Wound is getting worse, need supplies..." required></textarea>
                </div>
            </div>
            <div class="mt-6">
                <button type="submit" id="submitRequestBtn" class="btn-primary flex items-center justify-center">
                    <i data-lucide="send" class="w-4 h-4 mr-2"></i>
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();

    const requestApptModal = document.getElementById('requestApptModal');
    const requestApptForm = document.getElementById('requestApptForm');
    const modalMessageBox = document.getElementById('modal-message');
    const timePreferenceOutput = document.getElementById('time_preference_output');

    // Time inputs
    const reqHour = document.getElementById('req_hour');
    const reqMinute = document.getElementById('req_minute');
    const reqAmpm = document.getElementById('req_ampm');

    // --- Modal Functions ---
    function openRequestModal() {
        requestApptModal.classList.remove('hidden');
        requestApptModal.classList.add('flex');
        requestApptForm.reset();
        modalMessageBox.classList.add('hidden');
        requestApptForm.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
        // Set default minimum date
        document.getElementById('req_date').min = '<?php echo $tomorrow; ?>';
    }

    function closeRequestModal() {
        requestApptModal.classList.add('hidden');
        requestApptModal.classList.remove('flex');
    }

    function displayModalMessage(message, isSuccess = true) {
        modalMessageBox.textContent = message;
        if (isSuccess) {
            modalMessageBox.className = 'p-3 mb-4 rounded-lg text-sm bg-green-50 border-green-200 text-green-700 border';
        } else {
            modalMessageBox.className = 'p-3 mb-4 rounded-lg text-sm bg-red-50 border-red-200 text-red-700 border';
        }
        modalMessageBox.classList.remove('hidden');
    }

    // --- Data Rendering Logic (Unchanged) ---
    // ... [Tab Switching and Rendering functions] ... (omitted for brevity, assume they are present)

    // --- Tab Switching Logic ---
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.getAttribute('data-tab');
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));

            button.classList.add('active');
            // Hide/Show the table wrapper div
            document.getElementById('content-' + tab).classList.remove('hidden');

            // Re-check for empty message when switching tabs
            checkEmptyState();
        });
    });

    function checkEmptyState() {
        const activeTab = document.querySelector('.tab-button.active').getAttribute('data-tab');
        const upcomingList = document.getElementById('upcoming-list');
        const historyList = document.getElementById('history-list');
        const noAppointmentsMsg = document.getElementById('no-appointments-msg');

        // Hide/Show the table wrapper based on content
        const upcomingContentDiv = document.getElementById('content-upcoming');
        const historyContentDiv = document.getElementById('content-history');

        let isEmpty = true;

        if (activeTab === 'upcoming') {
            isEmpty = upcomingList.children.length === 0;
            upcomingContentDiv.classList.toggle('hidden', isEmpty);
        } else if (activeTab === 'history') {
            isEmpty = historyList.children.length === 0;
            historyContentDiv.classList.toggle('hidden', isEmpty);
        }

        noAppointmentsMsg.classList.toggle('hidden', !isEmpty);
        lucide.createIcons(); // Refresh icons after potentially showing/hiding them
    }

    // --- Appointment Rendering ---
    function renderAppointments(appointments) {
        const upcomingList = document.getElementById('upcoming-list');
        const historyList = document.getElementById('history-list');

        // Clear previous content
        upcomingList.innerHTML = '';
        historyList.innerHTML = '';

        const historyStatuses = ['Completed', 'Cancelled', 'No-show', 'On Hold'];
        const listMap = {
            'upcoming': upcomingList,
            'history': historyList
        };

        appointments.forEach(appt => {
            const status = appt.status || 'Pending';
            const isHistory = historyStatuses.includes(status);


            // Status visualization classes based on DB data
            let statusBadgeClass = 'table-status-';
            let iconName = 'calendar';
            let notesDisplay = 'No specific reason provided.'; // Default reason

            if (appt.notes) {
                // Remove the "Patient Request via Portal." prefix and "Reason:" label if present
                notesDisplay = appt.notes.split('\n')[0].replace('Patient Request via Portal.\nReason: ', '').replace('Patient Request via Portal.Reason: ', '').replace('Patient Request via Portal.', '').trim();
                if (notesDisplay.length === 0) {
                    notesDisplay = 'Patient requested appointment.';
                }
            }


            if (status === 'Pending') {
                statusBadgeClass += 'Pending';
                iconName = 'clock';
            } else if (status === 'Scheduled' || status === 'Confirmed' || status === 'Checked-in') {
                statusBadgeClass += 'Scheduled';
                iconName = 'calendar-check';
            } else if (status === 'Completed') {
                statusBadgeClass += 'Completed';
                iconName = 'check-circle';
            } else if (isHistory) {
                statusBadgeClass += 'Cancelled';
                iconName = 'x-circle';
            }

            const date = new Date(appt.appointment_date);
            const formattedDate = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const formattedTime = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const providerName = appt.doctor_name ? `Dr. ${appt.doctor_name}` : '<span class="italic text-gray-400">To be assigned</span>';

            const row = document.createElement('tr');
            row.className = 'hover:bg-indigo-50/20 cursor-pointer';

            // --- TABLE ROW STRUCTURE ---
            row.innerHTML = `
                <td>
                    <p class="font-semibold text-gray-800 flex items-center gap-2">
                        <i data-lucide="calendar" class="w-4 h-4 text-indigo-600"></i>
                        ${formattedDate}
                    </p>
                    <p class="text-sm text-muted flex items-center gap-2 mt-1">
                        <i data-lucide="clock" class="w-4 h-4 text-muted"></i>
                        ${formattedTime}
                    </p>
                </td>
                <td>
                    <p class="font-medium text-gray-800">${appt.appointment_type}</p>
                    <p class="text-xs text-muted truncate max-w-[200px]">${notesDisplay}</p>
                </td>
                <td>
                    <p class="text-sm text-gray-600">${providerName}</p>
                </td>
                <td>
                    <span class="${statusBadgeClass} table-status-badge">${status}</span>
                </td>
            `;
            // --- END TABLE ROW STRUCTURE ---

            // Separate based on status:
            if (isHistory) {
                listMap['history'].appendChild(row);
            } else {
                // Everything else (Pending, Scheduled, Confirmed, Checked-in) is Upcoming
                listMap['upcoming'].appendChild(row);
            }
        });

        // Final check after rendering
        checkEmptyState();
    }

    // --- Initial Data Load ---
    document.addEventListener('DOMContentLoaded', async () => {
        const loading = document.getElementById('loading');
        try {
            const response = await fetch('api/get_appointments.php');

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const appointments = await response.json();

            renderAppointments(appointments);

        } catch (error) {
            console.error("Fetch Error:", error);
            loading.innerHTML = '<p class="text-red-700 font-medium">Unable to load appointments. Please check your network connection.</p>';
        }
    });

    // --- Appointment Request Submission (Updated for specific time fields) ---
    requestApptForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submitRequestBtn');
        const originalContent = btn.innerHTML;

        // 1. Compile the chosen time into a single string for the API
        const hour = reqHour.value;
        const minute = reqMinute.value;
        const ampm = reqAmpm.value;

        if (!hour || !minute || !ampm) {
            displayModalMessage("Please select a valid hour, minute, and AM/PM preference.", false);
            return;
        }

        // Compile the full time preference string (e.g., "10:30 AM")
        const timePreferenceString = `${hour}:${minute} ${ampm}`;
        timePreferenceOutput.value = timePreferenceString;

        modalMessageBox.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 animate-spin"></i> Submitting...';
        lucide.createIcons();

        const formData = new FormData(requestApptForm);
        // We only send 'requested_date', 'reason', and the compiled 'time_preference' (which is now in the hidden input)
        const data = {
            requested_date: formData.get('requested_date'),
            reason: formData.get('reason'),
            time_preference: formData.get('time_preference')
        };


        try {
            const res = await fetch('api/request_appointment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (res.ok) {
                displayModalMessage('Appointment request submitted successfully! Our team will contact you to confirm.', true);

                requestApptForm.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);

                setTimeout(() => {
                    window.location.reload();
                }, 2000);

            } else {
                displayModalMessage(result.message || 'Error submitting request.', false);
            }
        } catch (error) {
            displayModalMessage('Failed to submit request. Please check your connection.', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
            lucide.createIcons();
        }
    });
</script>
</body>
</html>