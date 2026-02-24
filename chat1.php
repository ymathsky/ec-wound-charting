<?php
// Filename: add_appointment.php
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
        <?php require_once 'templates/sidebar.php'; ?>

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
                <div class="max-w-7xl mx-auto">
                    <div id="form-message" class="hidden p-4 mb-4 rounded-lg shadow-lg"></div>
                    <iframe
                        id="hpi-iframe"
                        src="chat.php"
                        class="w-full bg-white rounded-lg shadow-inner"
                        style="height: 800px; border: none; overflow-y: auto;"
                    ></iframe>
                </div>
            </main>
        </div>
    </div>


    <div id="mobile-prompt" class="w-full md:hidden flex flex-col justify-center items-center p-8 text-center text-gray-500 h-full">
        <i class="fas fa-mobile-alt text-6xl mb-4"></i>
        <p class="text-lg font-medium">Please select a user to begin chatting.</p>
    </div>
<?php
require_once 'templates/footer.php';
?>