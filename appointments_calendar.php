<?php
// Filename: appointments_calendar.php

// Check if we are in "Modal Mode" (embedded in MDI iframe)
$is_modal_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';

require_once 'templates/header.php';
require_once 'templates/visit_mode_modal.php';
require_once 'db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Role Check - Ensure user is authorized to view the calendar (Admin, Scheduler, Clinician)
$allowed_view_roles = ['admin', 'scheduler', 'facility'];
if (!in_array($_SESSION['ec_role'], $allowed_view_roles)) {
    header("Location: dashboard.php");
    exit();
}

// CRITICAL: Determine if the user can ADD appointments
$can_schedule = in_array($_SESSION['ec_role'], ['admin', 'scheduler']);
?>

<!-- FullCalendar CSS and JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<!-- Lucide Icons for UI enhancement -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>


<style>
    /* --- Modern FullCalendar Overrides --- */
    :root {
        --fc-border-color: #e5e7eb; /* gray-200 */
        --fc-button-text-color: #fff;
        --fc-button-bg-color: #4f46e5; /* indigo-600 */
        --fc-button-border-color: #4f46e5;
        --fc-button-hover-bg-color: #4338ca; /* indigo-700 */
        --fc-button-hover-border-color: #4338ca;
        --fc-button-active-bg-color: #3730a3; /* indigo-800 */
        --fc-button-active-border-color: #3730a3;
        --fc-today-bg-color: #f0f9ff; /* sky-50 */
        --fc-page-bg-color: #ffffff;
        --fc-neutral-bg-color: #f9fafb; /* gray-50 */
        --fc-list-event-hover-bg-color: #f3f4f6;
    }

    #calendar {
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        --fc-event-border-color: transparent;
    }

    /* Header Toolbar */
    .fc-header-toolbar {
        margin-bottom: 1.5rem !important;
        padding: 0.5rem;
    }

    .fc-toolbar-title {
        font-size: 1.75rem !important;
        font-weight: 800 !important;
        color: #111827; /* gray-900 */
        letter-spacing: -0.025em;
    }

    .fc-button {
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        text-transform: capitalize;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        transition: all 0.2s ease-in-out;
    }
    
    .fc-button:focus {
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.3) !important;
    }

    /* Calendar Grid */
    .fc-theme-standard td, .fc-theme-standard th {
        border-color: var(--fc-border-color);
    }

    .fc-col-header-cell-cushion {
        padding: 12px 0 !important;
        color: #4b5563; /* gray-600 */
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }

    .fc-daygrid-day-number {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6b7280; /* gray-500 */
        padding: 8px 8px 0 0 !important;
    }

    .fc-day-today .fc-daygrid-day-number {
        color: #4f46e5; /* indigo-600 */
        font-weight: 700;
    }

    /* Events Styling */
    .fc-event {
        border-radius: 6px !important;
        margin-bottom: 2px !important;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        border: none !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .fc-event:hover {
        transform: translateY(-1px) scale(1.01);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        z-index: 5;
    }

    .fc-event-main {
        padding: 4px 8px !important;
    }

    .fc-event-time {
        font-size: 0.75rem;
        font-weight: 600;
        opacity: 0.9;
    }

    .fc-event-title {
        font-size: 0.8125rem; /* 13px */
        font-weight: 500;
        line-height: 1.2;
    }

    /* Daily Count Badge - Container Reset */
    .daily-count-event {
        background-color: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 4px 2px !important;
        width: auto !important;
        cursor: default !important; /* Inner elements handle cursor */
    }
    
    .daily-count-event:hover {
        background-color: transparent !important;
        transform: none !important;
        box-shadow: none !important;
    }
    
    /* Hide default event dot if present */
    .daily-count-event .fc-event-main {
        color: inherit;
    }

    /* --- Custom Tooltip Styling --- */
    #appointment-tooltip {
        position: absolute;
        padding: 0;
        background: rgba(17, 24, 39, 0.95); /* gray-900 with opacity */
        backdrop-filter: blur(4px);
        color: white;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        z-index: 1000;
        pointer-events: none;
        max-width: 280px;
        font-size: 0.875rem;
        opacity: 0;
        transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
        transform: translateY(5px);
        overflow: hidden;
        border: 1px solid rgba(255,255,255,0.1);
    }
    #appointment-tooltip.show {
        opacity: 1;
        transform: translateY(0);
    }
    #tooltip-title {
        display: block;
        background: rgba(255,255,255,0.1);
        padding: 8px 12px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #e0e7ff; /* indigo-100 */
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    #tooltip-content {
        padding: 10px 12px;
    }
    #tooltip-content p {
        margin: 4px 0;
        display: flex;
        justify-content: space-between;
    }
    #tooltip-content strong {
        color: #9ca3af; /* gray-400 */
        font-weight: 500;
        margin-right: 8px;
    }

    /* --- Context Menu Styling --- */
    #context-menu {
        position: absolute;
        z-index: 10000;
        width: 220px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        border: 1px solid #f3f4f6;
        display: none;
        overflow: hidden;
        animation: menuFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1);
    }
    #context-menu.show {
        display: block;
    }
    .context-menu-item {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        cursor: pointer;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        transition: all 0.1s;
    }
    .context-menu-item:hover {
        background-color: #f9fafb;
        color: #4f46e5; /* indigo-600 */
    }
    .context-menu-item i {
        margin-right: 12px;
        width: 18px;
        height: 18px;
        stroke-width: 2px;
    }
    .context-menu-item.delete {
        color: #ef4444;
    }
    .context-menu-item.delete:hover {
        background-color: #fef2f2;
        color: #dc2626;
    }
    @keyframes menuFadeIn {
        from { opacity: 0; transform: scale(0.95) translateY(-5px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
</style>

<div class="flex h-screen bg-gray-50 font-sans">
    <?php 
    if (!$is_modal_mode) {
        require_once 'templates/sidebar.php'; 
    }
    ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                    <i data-lucide="calendar-check" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Appointments Overview
                </h1>
                <p class="text-sm text-gray-500 mt-1">Manage all scheduled visits for your team and patients.</p>
            </div>
            <?php if ($can_schedule): ?>
                <div class="flex space-x-3">
                    <a href="map_view.php" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold py-2.5 px-6 rounded-xl flex items-center transition shadow-sm">
                        <i data-lucide="map" class="w-5 h-5 mr-2 text-indigo-600"></i>
                        View Route Map
                    </a>
                    <a href="add_appointment.php" data-tab-title="New Appointment" data-tab-icon="calendar-plus" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center transition transform hover:scale-105 shadow-md">
                        <i data-lucide="calendar-plus" class="w-5 h-5 mr-2"></i>
                        Add New Appointment
                    </a>
                </div>
            <?php endif; ?>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-50 p-6">
            <div class="max-w-7xl mx-auto">

                <?php // Hide Filters for facility role
                if ($user_role !== 'facility'): ?>
                    <!-- Filters Card -->
                    <div class="bg-white rounded-2xl shadow-sm p-6 mb-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-bold text-gray-800 flex items-center">
                                <i data-lucide="filter" class="w-5 h-5 mr-2 text-indigo-500"></i>
                                Filter Appointments
                            </h2>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="relative">
                                <label for="clinicianFilter" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Clinician</label>
                                <select id="clinicianFilter" class="w-full form-select bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2.5">
                                    <option value="">All Clinicians</option>
                                    <!-- Options will be populated by JS -->
                                </select>
                            </div>
                            <div class="relative">
                                <label for="patientFilter" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Patient</label>
                                <select id="patientFilter" class="w-full form-select bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2.5">
                                    <option value="">All Patients</option>
                                    <!-- Options will be populated by JS -->
                                </select>
                            </div>
                            <div class="relative">
                                <label for="statusFilter" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</label>
                                <select id="statusFilter" class="w-full form-select bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2.5">
                                    <option value="">All Statuses</option>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="Confirmed">Confirmed</option>
                                    <option value="Checked-in">Checked-in</option>
                                    <option value="Completed">Completed</option>
                                    <option value="Cancelled">Cancelled</option>
                                    <option value="No-show">No-show</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button onclick="applyFilters()" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2.5 px-4 rounded-lg font-semibold transition-all shadow-md hover:shadow-lg transform hover:-translate-y-0.5 flex justify-center items-center">
                                    Apply Filters
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Calendar Card -->
                <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
                    <div id='calendar-container' class="mt-2 relative">
                        <div id='calendar'></div>
                        <!-- Loading Overlay -->
                        <div id="calendar-loading" class="absolute inset-0 bg-white/80 backdrop-blur-sm flex items-center justify-center z-10 hidden rounded-xl">
                            <div class="flex flex-col items-center">
                                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-indigo-600"></div>
                                <span class="mt-3 text-indigo-600 font-semibold text-sm tracking-wide">Updating Calendar...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 backdrop-blur-sm p-4 md:p-0 transition-opacity duration-300">
    <div class="relative w-full max-w-md transform rounded-2xl bg-white shadow-2xl transition-all md:my-8">
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <div class="mr-3 flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                    <i data-lucide="calendar" class="h-5 w-5"></i>
                </div>
                Appointment Details
            </h3>
            <button id="closeModalBtn" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 transition-colors">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="px-6 py-6 space-y-5">
            <!-- Patient Info -->
            <div class="flex items-start space-x-3">
                <div class="mt-1 flex-shrink-0 text-gray-400">
                    <i data-lucide="user" class="h-5 w-5"></i>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Patient</p>
                    <p id="modalPatientName" class="text-base font-semibold text-gray-900"></p>
                    <div id="modalContactInfo" class="mt-1 space-y-1">
                        <p id="modalPatientPhone" class="text-sm text-gray-600 flex items-center hidden">
                            <i data-lucide="phone" class="w-3 h-3 mr-1.5"></i> <span class="val"></span>
                        </p>
                        <p id="modalPatientAddress" class="text-sm text-gray-600 flex items-center hidden">
                            <i data-lucide="map-pin" class="w-3 h-3 mr-1.5"></i> <span class="val"></span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Clinician Info -->
            <div class="flex items-start space-x-3">
                <div class="mt-1 flex-shrink-0 text-gray-400">
                    <i data-lucide="stethoscope" class="h-5 w-5"></i>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Clinician</p>
                    <p id="modalClinicianName" class="text-base font-medium text-gray-900"></p>
                </div>
            </div>

            <!-- Date & Time -->
            <div class="flex items-start space-x-3">
                <div class="mt-1 flex-shrink-0 text-gray-400">
                    <i data-lucide="clock" class="h-5 w-5"></i>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Date & Time</p>
                    <p id="modalDateTime" class="text-base font-medium text-gray-900 font-mono"></p>
                </div>
            </div>

            <!-- Status Selector -->
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                <label for="modalStatusSelect" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Current Status</label>
                <select id="modalStatusSelect" class="w-full form-select bg-white border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2">
                    <option value="Scheduled">Scheduled</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Checked-in">Checked-in</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="No-show">No-show</option>
                </select>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50 px-6 py-4 rounded-b-2xl">
            <button id="deleteAppointmentBtn" class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                <i data-lucide="trash-2" class="mr-2 h-4 w-4"></i>
                Cancel
            </button>
            <div class="flex space-x-3">
                <button id="saveStatusBtn" class="inline-flex items-center justify-center rounded-lg bg-white border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all">
                    Save Status
                </button>
                <a href="#" id="startVisitBtn" class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all">
                    Start Visit
                    <i data-lucide="arrow-right" class="ml-2 h-4 w-4"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Appointment Modal -->
<div id="addAppointmentModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 backdrop-blur-sm p-4 md:p-0 transition-opacity duration-300">
    <div class="relative w-full max-w-lg transform rounded-2xl bg-white shadow-2xl transition-all md:my-8">
        <!-- Modal Header -->
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                <div class="mr-3 flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-indigo-600">
                    <i data-lucide="calendar-plus" class="h-5 w-5"></i>
                </div>
                New Appointment
            </h3>
            <button id="closeAddModalBtn" class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 transition-colors">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="px-6 py-6">
            <div id="add-modal-message" class="hidden mb-4 p-3 rounded-lg text-sm font-medium"></div>
            
            <form id="addAppointmentForm" class="space-y-5">
                <div>
                    <label for="addPatient" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Patient</label>
                    <select id="addPatient" name="patient_id" required class="w-full form-select bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2.5"></select>
                </div>
                <div>
                    <label for="addClinician" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Clinician</label>
                    <select id="addClinician" name="user_id" required class="w-full form-select bg-gray-50 border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm py-2.5"></select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="addDate" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Date</label>
                        <input type="date" id="addDate" name="appointment_date_only" required class="w-full form-input bg-gray-100 border-gray-200 rounded-lg text-gray-500 cursor-not-allowed" readonly>
                    </div>
                    <div>
                        <label for="addTime" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Time</label>
                        <input type="time" id="addTime" name="appointment_time" required class="w-full form-input bg-white border-gray-200 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all text-sm">
                    </div>
                </div>
                
                <div class="pt-4 flex justify-end">
                    <button type="submit" id="saveAppointmentBtn" class="w-full inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all transform hover:-translate-y-0.5">
                        <i data-lucide="check" class="mr-2 h-4 w-4"></i>
                        Confirm Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Plan Route Modal (Replaces Day Schedule Modal) -->
<div id="planRouteModal" class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 backdrop-blur-sm p-4 transition-opacity duration-300">
    <div class="relative w-full max-w-7xl transform rounded-xl bg-white shadow-2xl transition-all flex flex-col h-[90vh]">
        
        <!-- Header -->
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 bg-gray-50 rounded-t-xl flex-none">
            <h3 class="text-xl font-bold text-gray-800 flex items-center">
                <span id="planRouteTitle">Plan Route</span>
            </h3>
            <button id="closePlanRouteBtn" class="rounded-full p-1 text-gray-400 hover:bg-gray-200 hover:text-gray-600 transition-colors">
                <i data-lucide="x" class="h-6 w-6"></i>
            </button>
        </div>

        <!-- Toolbar / Info -->
        <div class="px-6 py-4 border-b border-gray-200 bg-white flex flex-col md:flex-row justify-between items-start md:items-center gap-4 flex-none">
            <div class="flex flex-col space-y-1 text-sm">
                <div class="flex items-center text-gray-600">
                    <span class="font-bold w-20 uppercase text-xs text-gray-400">Pick-Up</span>
                    <span class="font-medium">Current Location</span>
                </div>
                <div class="flex items-center text-gray-600">
                    <span class="font-bold w-20 uppercase text-xs text-gray-400">Drop-Off</span>
                    <span class="font-medium">Current Location</span>
                </div>
            </div>
            <div class="flex space-x-2">
                <button id="rerouteBtn" class="bg-cyan-500 hover:bg-cyan-600 text-white px-4 py-2 rounded text-sm font-bold uppercase tracking-wide transition shadow-sm">
                    Reroute
                </button>
                <button id="printRouteBtn" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded text-sm font-bold uppercase tracking-wide transition shadow-sm">
                    Print
                </button>
                <button id="toggleMapBtn" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded text-sm font-bold uppercase tracking-wide transition shadow-sm">
                    Show Map
                </button>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-hidden flex flex-col md:flex-row min-h-0">
            <!-- Patient List Table -->
            <div id="routeTableContainer" class="flex-1 overflow-auto border-r border-gray-200 transition-all duration-300 w-full">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider w-12 bg-gray-50">No.</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50">Name (DOB)</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50">Time Range</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50">Remarks</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wider bg-gray-50">Address</th>
                        </tr>
                    </thead>
                    <tbody id="routeTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows injected via JS -->
                    </tbody>
                </table>
            </div>

            <!-- Map Container -->
            <div id="routeMapContainer" class="hidden w-full md:w-1/2 bg-gray-100 relative transition-all duration-300 h-[400px] md:h-auto">
                <div id="routeMap" class="w-full h-full"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="border-t border-gray-200 px-6 py-3 bg-gray-50 rounded-b-xl flex justify-between items-center text-sm flex-none">
            <div id="routeSummary" class="font-medium text-gray-600">
                <!-- Distance / Duration -->
            </div>
        </div>
    </div>
</div>

<!-- --- NEW: Custom Tooltip Placeholder --- -->
<div id="appointment-tooltip" class="hidden">
    <strong id="tooltip-title"></strong>
    <div id="tooltip-content"></div>
</div>

<!-- --- NEW: Context Menu --- -->
<div id="context-menu" class="hidden">
    <div class="context-menu-item" data-action="view">
        <i data-lucide="eye"></i> View Details
    </div>
    <div class="context-menu-item" data-action="check-in">
        <i data-lucide="check-circle"></i> Check In
    </div>
    <div class="context-menu-item" data-action="start-visit">
        <i data-lucide="notebook-pen"></i> Start Visit
    </div>
    <div class="border-t border-gray-100 my-1"></div>
    <div class="context-menu-item delete" data-action="delete">
        <i data-lucide="trash-2"></i> Cancel Appointment
    </div>
</div>
<!-- ----------------------------------------- -->


<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Lucide icons
        lucide.createIcons();

        const userRole = '<?php echo $user_role; ?>'; // Get role for JS checks
        const appTimezone = '<?php echo $app_timezone; ?>'; // Get App Timezone
        const calendarEl = document.getElementById('calendar');
        const clinicianFilter = document.getElementById('clinicianFilter');
        const patientFilter = document.getElementById('patientFilter');
        const statusFilter = document.getElementById('statusFilter');

        // Tooltip elements
        const tooltip = document.getElementById('appointment-tooltip');
        const tooltipTitle = document.getElementById('tooltip-title');
        const tooltipContent = document.getElementById('tooltip-content');

        // Context Menu elements
        const contextMenu = document.getElementById('context-menu');
        let contextMenuEventId = null;
        let contextMenuEventProps = null;

        // Details Modal
        const appointmentModal = document.getElementById('appointmentModal');
        const closeModalBtn = document.getElementById('closeModalBtn');
        const saveStatusBtn = document.getElementById('saveStatusBtn');
        const deleteAppointmentBtn = document.getElementById('deleteAppointmentBtn'); // New
        const startVisitBtn = document.getElementById('startVisitBtn'); // New
        let currentAppointmentId = null;

        // Add Modal
        const addAppointmentModal = document.getElementById('addAppointmentModal');
        const closeAddModalBtn = document.getElementById('closeAddModalBtn');
        const addAppointmentForm = document.getElementById('addAppointmentForm');
        const addModalMessage = document.getElementById('add-modal-message');

        // Plan Route Modal Elements
        const planRouteModal = document.getElementById('planRouteModal');
        const closePlanRouteBtn = document.getElementById('closePlanRouteBtn');
        const planRouteTitle = document.getElementById('planRouteTitle');
        const routeTableBody = document.getElementById('routeTableBody');
        const toggleMapBtn = document.getElementById('toggleMapBtn');
        const printRouteBtn = document.getElementById('printRouteBtn');
        const routeMapContainer = document.getElementById('routeMapContainer');
        const routeTableContainer = document.getElementById('routeTableContainer');
        const rerouteBtn = document.getElementById('rerouteBtn');
        const routeSummary = document.getElementById('routeSummary');

        let routeMap;
        let routeDirectionsService;
        let routeDirectionsRenderer;
        let routeMarkers = [];
        let currentRouteDate = null;
        let currentRouteEvents = [];

        // --- Plan Route Modal Logic ---

        function openPlanRouteModal(dateStr, showMap = false) {
            currentRouteDate = dateStr;
            const dateObj = new Date(dateStr);
            const formattedDate = dateObj.toLocaleDateString('en-US', { month: 'numeric', day: 'numeric', year: 'numeric' });
            planRouteTitle.textContent = `Plan Route (${formattedDate})`;

            // 1. Filter Events
            const allEvents = calendar.getEvents();
            currentRouteEvents = allEvents.filter(event => {
                if (event.classNames.includes('daily-count-event')) return false;
                const eventDate = event.startStr.split('T')[0];
                return eventDate === dateStr;
            });

            // 2. Populate Table
            populateRouteTable(currentRouteEvents);

            // 3. Show Modal
            planRouteModal.classList.remove('hidden');
            planRouteModal.classList.add('flex');

            // 4. Handle Map Visibility
            if (showMap) {
                routeMapContainer.classList.remove('hidden');
                routeTableContainer.classList.remove('w-full');
                toggleMapBtn.textContent = 'Hide Map';
                
                const onMapReady = () => {
                    setTimeout(() => {
                        google.maps.event.trigger(routeMap, 'resize');
                        calculateRoute();
                    }, 100);
                };

                if (!routeMap) {
                    loadGoogleMapsScript().then(() => {
                        initRouteMap();
                        onMapReady();
                    }).catch(err => console.error("Map Load Error", err));
                } else {
                    onMapReady();
                }
            } else {
                routeMapContainer.classList.add('hidden');
                routeTableContainer.classList.add('w-full');
                toggleMapBtn.textContent = 'Show Map';
            }
        }

        function populateRouteTable(events) {
            routeTableBody.innerHTML = '';
            if (events.length === 0) {
                routeTableBody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500 italic">No appointments found.</td></tr>';
                return;
            }

            // Sort by time
            events.sort((a, b) => a.start - b.start);

            events.forEach((event, index) => {
                const timeStr = event.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) + 
                                ' - ' + 
                                new Date(event.start.getTime() + 60*60*1000).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); // Mock 1hr duration
                
                const patientName = event.title;
                const dob = event.extendedProps.dob || 'N/A'; // Need to ensure DOB is in props
                const remarks = event.extendedProps.notes || 'No Remarks';
                const address = event.extendedProps.address || 'No Address';

                const row = document.createElement('tr');
                // Alternating colors handled by CSS or simple logic
                row.className = index % 2 === 0 ? 'bg-red-50' : 'bg-white'; // Matching screenshot reddish tint
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${index + 1}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                        ${patientName} <br>
                        <span class="text-xs font-normal text-gray-500">${dob}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${timeStr}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 uppercase">${remarks}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs" title="${address}">${address}</td>
                `;
                routeTableBody.appendChild(row);
            });
        }

        // Toggle Map
        toggleMapBtn.addEventListener('click', () => {
            const isHidden = routeMapContainer.classList.contains('hidden');
            if (isHidden) {
                routeMapContainer.classList.remove('hidden');
                routeTableContainer.classList.remove('w-full'); // Allow flex to shrink it
                toggleMapBtn.textContent = 'Hide Map';
                // Trigger resize for Google Maps
                if(routeMap) setTimeout(() => google.maps.event.trigger(routeMap, 'resize'), 100);
                
                // Auto calculate route when map is shown
                calculateRoute();
            } else {
                routeMapContainer.classList.add('hidden');
                routeTableContainer.classList.add('w-full');
                toggleMapBtn.textContent = 'Show Map';
            }
        });

        // Close Modal
        closePlanRouteBtn.addEventListener('click', () => {
            planRouteModal.classList.add('hidden');
            planRouteModal.classList.remove('flex');
        });

        // Reroute (Just recalculates for now)
        rerouteBtn.addEventListener('click', calculateRoute);

        // Print Route
        printRouteBtn.addEventListener('click', () => {
            if (currentRouteEvents.length === 0) {
                alert("No appointments to print.");
                return;
            }

            const printWindow = window.open('', '_blank');
            const dateStr = new Date(currentRouteDate).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            
            let tableRows = '';
            
            currentRouteEvents.forEach((event, index) => {
                const timeStr = event.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                const patientName = event.title;
                const clinicianName = event.extendedProps.clinician || 'N/A';
                const address = event.extendedProps.address || 'No Address';
                const phone = event.extendedProps.contact_number || 'N/A';
                const notes = event.extendedProps.notes || '';

                tableRows += `
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${index + 1}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">
                            <strong>${patientName}</strong><br>
                            <span style="font-size: 0.9em; color: #666;">${phone}</span>
                        </td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${clinicianName}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${timeStr}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${address}</td>
                        <td style="padding: 8px; border-bottom: 1px solid #ddd;">${notes}</td>
                    </tr>
                `;
            });

            const htmlContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Route Schedule - ${dateStr}</title>
                    <style>
                        body { font-family: sans-serif; padding: 20px; }
                        h1 { text-align: center; color: #333; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background-color: #f8f9fa; text-align: left; padding: 10px; border-bottom: 2px solid #ddd; }
                        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
                        .footer { margin-top: 30px; text-align: center; font-size: 0.8em; color: #888; }
                        @media print {
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Route Schedule</h1>
                    <p style="text-align: center; font-size: 1.2em;">${dateStr}</p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="20%">Patient</th>
                                <th width="15%">Clinician</th>
                                <th width="10%">Time</th>
                                <th width="25%">Address</th>
                                <th width="25%">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${tableRows}
                        </tbody>
                    </table>

                    <div class="footer">
                        Printed on ${new Date().toLocaleString()}
                    </div>
                    
                    <script>
                        window.onload = function() { window.print(); }
                    <\/script>
                </body>
                </html>
            `;

            printWindow.document.write(htmlContent);
            printWindow.document.close();
        });


        // --- Map Logic ---
        async function loadGoogleMapsScript() {
            if (window.google && window.google.maps) return; // Already loaded
            try {
                const response = await fetch('api/get_maps_api_key.php');
                if (!response.ok) throw new Error('Could not fetch Google Maps API key.');
                const data = await response.json();
                
                return new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = `https://maps.googleapis.com/maps/api/js?key=${data.apiKey}&libraries=places`;
                    script.async = true;
                    script.defer = true;
                    script.onload = resolve;
                    script.onerror = reject;
                    document.head.appendChild(script);
                });
            } catch (err) {
                console.error(err);
                alert("Failed to load Google Maps.");
            }
        }

        function initRouteMap() {
            const defaultCenter = { lat: 42.03, lng: -88.08 }; // Schaumburg approx (from screenshot)
            routeMap = new google.maps.Map(document.getElementById("routeMap"), {
                zoom: 10,
                center: defaultCenter,
                mapTypeControl: false,
                streetViewControl: false,
            });
            routeDirectionsService = new google.maps.DirectionsService();
            routeDirectionsRenderer = new google.maps.DirectionsRenderer({
                map: routeMap,
                suppressMarkers: true // We will add custom markers
            });
        }

        function calculateRoute() {
            if (!routeDirectionsService || currentRouteEvents.length === 0) return;

            const waypoints = currentRouteEvents
                .filter(e => e.extendedProps.address)
                .map(e => ({ location: e.extendedProps.address, stopover: true }));

            if (waypoints.length === 0) {
                routeSummary.innerHTML = `<span class="text-red-500">No appointments with valid addresses to route.</span>`;
                return;
            }

            // Function to execute the route request
            const executeRoute = (origin, destination, isRetry = false) => {
                const request = {
                    origin: origin,
                    destination: destination,
                    waypoints: waypoints,
                    optimizeWaypoints: true,
                    travelMode: google.maps.TravelMode.DRIVING,
                };

                routeSummary.innerHTML = '<span class="text-gray-500">Calculating route...</span>';

                routeDirectionsService.route(request, function(result, status) {
                    if (status === google.maps.DirectionsStatus.OK) {
                        routeDirectionsRenderer.setDirections(result);
                        
                        // --- Custom Markers Logic ---
                        // Clear existing markers
                        if (routeMarkers) {
                            routeMarkers.forEach(marker => marker.setMap(null));
                        }
                        routeMarkers = [];

                        const route = result.routes[0];
                        const legs = route.legs;

                        // 1. Start/End Marker (My Location) - Blue Pin
                        const startMarker = new google.maps.Marker({
                            position: legs[0].start_location,
                            map: routeMap,
                            title: "Start/End",
                            icon: "http://maps.google.com/mapfiles/ms/icons/blue-dot.png"
                        });
                        routeMarkers.push(startMarker);

                        // 2. Waypoint Markers (Patients) - Numbered
                        // Loop through legs to place markers at the END of each leg (except the last one which is back to start)
                        for (let i = 0; i < legs.length - 1; i++) {
                            const leg = legs[i];
                            const marker = new google.maps.Marker({
                                position: leg.end_location,
                                map: routeMap,
                                label: {
                                    text: (i + 1).toString(),
                                    color: "white",
                                    fontWeight: "bold"
                                },
                                title: `Stop ${i + 1}`
                            });
                            routeMarkers.push(marker);
                        }

                        // Update Summary
                        const totalDist = route.legs.reduce((acc, leg) => acc + leg.distance.value, 0);
                        const totalDur = route.legs.reduce((acc, leg) => acc + leg.duration.value, 0);
                        
                        const distMiles = (totalDist * 0.000621371).toFixed(1);
                        const durMins = Math.round(totalDur / 60);
                        
                        routeSummary.innerHTML = `
                            <div class="flex space-x-6">
                                <div><span class="font-bold text-gray-500 text-xs uppercase">Distance</span> <span class="font-bold text-gray-800">${distMiles} mi</span></div>
                                <div><span class="font-bold text-gray-500 text-xs uppercase">Duration</span> <span class="font-bold text-gray-800">${durMins} mins</span></div>
                            </div>
                        `;
                    } else {
                        if (status === 'ZERO_RESULTS' && !isRetry) {
                            console.warn("Routing from user location failed (ZERO_RESULTS). Falling back to first patient address.");
                            const fallback = waypoints[0].location;
                            executeRoute(fallback, fallback, true);
                            return;
                        }
                        console.error("Directions request failed due to " + status);
                        routeSummary.innerHTML = `<span class="text-red-500">Routing failed: ${status}</span>`;
                    }
                });
            };

            // 1. Try Browser Geolocation
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userPos = { lat: position.coords.latitude, lng: position.coords.longitude };
                        executeRoute(userPos, userPos);
                    },
                    (error) => {
                        console.warn("Geolocation failed/denied. Falling back to first patient address.", error);
                        // 2. Fallback: Use the first patient's address as Start/End
                        // This ensures we are at least in the same continent/area as the waypoints.
                        const fallback = waypoints[0].location;
                        executeRoute(fallback, fallback);
                    }
                );
            } else {
                // 3. No Geolocation Support: Fallback to first patient address
                const fallback = waypoints[0].location;
                executeRoute(fallback, fallback);
            }
        }


        // --- Day Schedule Modal Logic (Legacy - Removed) ---
        // function openDayScheduleModal(dateStr) { ... } 
        // ... (Removed old logic)

        // Close Day Modal Handlers (Legacy - Removed)
        // ...


        /**
         * Fetches and populates patients based on the selected clinician filter.
         * If no clinician is selected, it fetches all patients.
         * @param {number|string|null} clinicianId - The selected clinician's ID, or null/empty string for all patients.
         */
        async function fetchAndPopulatePatients(clinicianId) {
            let endpoint = 'api/get_patient_to_calendar.php'; // Default: get all patients
            if (clinicianId) {
                // *** UPDATED FILENAME HERE ***
                endpoint = `api/get_patients_by_clinician_to_calendar.php?user_id=${clinicianId}`;
            }

            try {
                const patientsResponse = await fetch(endpoint);
                const data = await patientsResponse.json();

                // If fetching by clinician, the response is wrapped in a 'patients' key
                // Otherwise, it is a direct array of patients (from get_patient_to_calendar.php)
                const patients = clinicianId ? (data.patients || []) : data;

                const addPatientSelect = document.getElementById('addPatient');

                // Clear and add the default "All Patients" option for the filter
                patientFilter.innerHTML = '<option value="">All Patients</option>';

                // Clear and add the default "Select a Patient" option for the Add Modal
                addPatientSelect.innerHTML = '<option value="">Select a Patient</option>';

                patients.forEach(patient => {
                    const optionText = `${patient.last_name}, ${patient.first_name}`;

                    // Populate filter
                    patientFilter.innerHTML += `<option value="${patient.patient_id}">${optionText}</option>`;

                    // Populate add modal
                    addPatientSelect.innerHTML += `<option value="${patient.patient_id}">${optionText}</option>`;
                });

            } catch (error) {
                console.error("Error populating patients:", error);
                // Reset to default on error
                patientFilter.innerHTML = '<option value="">Error loading patients</option>';
            }
        }


        // --- Populate Filters ---
        async function populateFilters(selectPatientId = null, selectClinicianId = null) {
            // Only populate if filters are visible
            if (userRole === 'facility') return;

            try {
                // 1. Populate clinicians
                const usersResponse = await fetch('api/get_assigned_clinicians.php');

                if (!usersResponse.ok) {
                    throw new Error(`Failed to fetch clinicians. Status: ${usersResponse.status}`);
                }

                const users = await usersResponse.json();
                const addClinicianSelect = document.getElementById('addClinician');

                // Ensure default options are set
                clinicianFilter.innerHTML = '<option value="">All Clinicians</option>';
                addClinicianSelect.innerHTML = '<option value="">Select a Clinician</option>';

                // FIX: Check if users is an array before calling forEach
                if (Array.isArray(users) && users.length > 0) {
                    users.forEach(user => {
                        clinicianFilter.innerHTML += `<option value="${user.user_id}">${user.full_name}</option>`;
                        addClinicianSelect.innerHTML += `<option value="${user.user_id}">${user.full_name}</option>`;
                    });
                } else {
                    console.warn("No assigned clinicians found, or API returned non-array data.");
                    clinicianFilter.innerHTML = '<option value="">No Clinicians Assigned</option>';
                }


                // 2. Populate patients (Initial load: loads ALL patients)
                // Calls fetchAndPopulatePatients(null) which uses api/get_patient_to_calendar.php
                fetchAndPopulatePatients(null);

            } catch (error) {
                console.error("Error populating clinicians:", error.message);
                // Display error message in the filter dropdown
                clinicianFilter.innerHTML = `<option value="">Error: ${error.message.substring(0, 30)}...</option>`;
            }
        }

        // --- Clinician Filter Change Listener (New Logic) ---
        clinicianFilter.addEventListener('change', function() {
            const clinicianId = this.value;
            // 1. Filter the patients dropdown based on selected clinician
            fetchAndPopulatePatients(clinicianId);

            // 2. Apply the main calendar filter immediately (which includes patient filter reset to "All Patients")
            applyFilters();
        });


        // --- Filter Logic ---
        function applyFilters() {
            // Check if filter elements exist before accessing value
            const clinicianId = clinicianFilter ? clinicianFilter.value : '';
            const patientId = patientFilter ? patientFilter.value : '';
            const status = statusFilter ? statusFilter.value : '';
            
            const sourceUrl = `api/get_all_appointments.php?user_id=${clinicianId}&patient_id=${patientId}&status=${status}`;
            calendar.getEventSources().forEach(source => source.remove());
            calendar.addEventSource(sourceUrl);
        }
        window.applyFilters = applyFilters; // Expose to global scope for the button

        // Only keep the patient filter listener for filtering the calendar
        if (patientFilter) {
            patientFilter.addEventListener('change', applyFilters);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', applyFilters);
        }

        // --- Helper Functions ---
        
        async function updateStatus(appointmentId, newStatus) {
            if (userRole === 'facility') return; 

            try {
                const response = await fetch('api/update_appointment_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        appointment_id: appointmentId,
                        status: newStatus
                    })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);

                // alert('Appointment status updated!'); // Removed for smoother UX
                
                // Close modal if open
                appointmentModal.classList.add('hidden');
                appointmentModal.classList.remove('flex');
                
                calendar.refetchEvents();

            } catch(error) {
                alert('Error updating status: ' + error.message);
            }
        }

        function openAppointmentModal(event) {
            currentAppointmentId = event.id;
            const patientId = event.extendedProps.patient_id;
            const userId = event.extendedProps.user_id;
            const status = event.extendedProps.status;

            // Populate modal
            document.getElementById('modalPatientName').textContent = event.title;

            // Populate Contact Info
            const phone = event.extendedProps.contact_number;
            const address = event.extendedProps.address;
            
            const phoneEl = document.getElementById('modalPatientPhone');
            if (phone) {
                phoneEl.querySelector('.val').textContent = phone;
                phoneEl.classList.remove('hidden');
            } else {
                phoneEl.classList.add('hidden');
            }

            const addressEl = document.getElementById('modalPatientAddress');
            if (address) {
                addressEl.querySelector('.val').textContent = address;
                addressEl.classList.remove('hidden');
            } else {
                addressEl.classList.add('hidden');
            }

            document.getElementById('modalClinicianName').textContent = event.extendedProps.clinician || 'N/A';
            document.getElementById('modalDateTime').textContent = event.start.toLocaleString();
            document.getElementById('modalStatusSelect').value = status;

            // Re-render lucide icons inside the modal
            lucide.createIcons();

            // Set button link for non-facility users
            if (userRole !== 'facility') {
                const startVisitBtn = document.getElementById('startVisitBtn');
                // Remove href attribute to prevent default navigation
                startVisitBtn.removeAttribute('href');
                
                // Use the new Visit Mode Modal
                startVisitBtn.onclick = function(e) {
                    e.preventDefault();
                    // Close the details modal first
                    appointmentModal.classList.add('hidden');
                    appointmentModal.classList.remove('flex');
                    
                    // Open the mode selection modal
                    openVisitModeModal(patientId, currentAppointmentId, userId);
                };
            }

            // Show modal
            appointmentModal.classList.remove('hidden');
            appointmentModal.classList.add('flex');
        }

        // --- Context Menu Logic ---
        
        // Close context menu on click outside
        document.addEventListener('click', function(e) {
            if (!contextMenu.contains(e.target)) {
                contextMenu.classList.remove('show');
                contextMenu.classList.add('hidden');
            }
        });

        // Handle Context Menu Actions
        contextMenu.querySelectorAll('.context-menu-item').forEach(item => {
            item.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                contextMenu.classList.remove('show');
                contextMenu.classList.add('hidden');

                if (!contextMenuEventId) return;

                if (action === 'view') {
                    const event = calendar.getEventById(contextMenuEventId);
                    if (event) openAppointmentModal(event);
                } else if (action === 'check-in') {
                    updateStatus(contextMenuEventId, 'Checked-in');
                } else if (action === 'start-visit') {
                     if (userRole !== 'facility') {
                        // Use the new Visit Mode Modal
                        openVisitModeModal(contextMenuEventProps.patient_id, contextMenuEventId, contextMenuEventProps.user_id);
                    } else {
                        alert("Facility users cannot start visits.");
                    }
                } else if (action === 'delete') {
                    if (confirm('Are you sure you want to cancel this appointment?')) {
                        updateStatus(contextMenuEventId, 'Cancelled');
                    }
                }
            });
        });


        // --- Initialize Calendar ---
        function initializeCalendar() {
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                slotMinTime: '06:00:00',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                height: 'auto', // Adjust height to fill container without excessive scroll
                editable: userRole !== 'facility', // Disable drag/drop for facility
                loading: function(isLoading) {
                    const loadingEl = document.getElementById('calendar-loading');
                    if (isLoading) {
                        loadingEl.classList.remove('hidden');
                    } else {
                        loadingEl.classList.add('hidden');
                    }
                },
                events: {
                    url: 'api/get_all_appointments.php', // API already handles facility filtering based on session
                    failure: function() {
                        // Use a custom message box instead of alert later
                        console.error('There was an error while fetching events!');
                    },
                },
                dateClick: function(info) {
                    // Prevent opening add modal for facility
                    if (userRole === 'facility') {
                        return;
                    }

                    // Prevent adding appointments in the past (Using App Timezone)
                    const clickedDateStr = info.dateStr; // YYYY-MM-DD
                    const todayInAppTimezone = new Date().toLocaleDateString('en-CA', { timeZone: appTimezone });

                    if (clickedDateStr < todayInAppTimezone) {
                        alert("You cannot schedule appointments in the past.");
                        return;
                    }

                    // Open the "Add Appointment" modal
                    addAppointmentForm.reset();
                    document.getElementById('addDate').value = info.dateStr;
                    // Reset message div
                    addModalMessage.classList.add('hidden');
                    addAppointmentModal.classList.remove('hidden');
                    addAppointmentModal.classList.add('flex');
                },
                eventClick: function(info) {
                    // Handle Daily Count Event Click
                    if (info.event.classNames.includes('daily-count-event')) {
                        // Check if clicked on specific icon
                        // Since eventClick triggers on the whole element, we need to check target
                        // But FullCalendar might swallow the target.
                        // Let's just open the modal for now.
                        // Ideally we distinguish between Map and List, but the Modal has both.
                        
                        const dateStr = info.event.id.replace('count-', '');
                        openPlanRouteModal(dateStr);
                        return; 
                    }
                    
                    info.jsEvent.preventDefault(); 
                    openAppointmentModal(info.event);
                },
                eventDidMount: function(info) {
                    // Prevent context menu on count events
                    if (info.event.classNames.includes('daily-count-event')) {
                        if (info.el) {
                            lucide.createIcons({ root: info.el });
                        }
                        return;
                    }

                    info.el.addEventListener('contextmenu', function(e) {
                        e.preventDefault();
                        
                        // Store event data
                        contextMenuEventId = info.event.id;
                        contextMenuEventProps = info.event.extendedProps;

                        // Position menu
                        contextMenu.style.top = `${e.pageY}px`;
                        contextMenu.style.left = `${e.pageX}px`;
                        contextMenu.classList.remove('hidden');
                        contextMenu.classList.add('show');
                    });
                },
                eventDrop: async function(info) {
                    // Prevent drag/drop for facility (already set by editable: false)
                    // Also prevent moving the count event
                    if (userRole === 'facility' || info.event.classNames.includes('daily-count-event')) {
                        info.revert();
                        return;
                    }
                    if (!confirm("Are you sure you want to move this appointment?")) {
                        info.revert();
                        return;
                    }

                    const appointmentId = info.event.id;
                    const newStart = info.event.start;

                    const year = newStart.getFullYear();
                    const month = String(newStart.getMonth() + 1).padStart(2, '0');
                    const day = String(newStart.getDate()).padStart(2, '0');
                    const hours = String(newStart.getHours()).padStart(2, '0');
                    const minutes = String(newStart.getMinutes()).padStart(2, '0');
                    const seconds = String(newStart.getSeconds()).padStart(2, '0');

                    const newDatetime = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;

                    try {
                        const response = await fetch('api/update_appointment_date.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                appointment_id: appointmentId,
                                new_datetime: newDatetime
                            })
                        });
                        const result = await response.json();
                        if (!response.ok) throw new Error(result.message);

                        // alert('Appointment date and time updated successfully!'); // Removed for smoother UX
                        calendar.refetchEvents();

                    } catch(error) {
                        alert('Error updating appointment: ' + error.message); // Replace with custom message box later
                        info.revert();
                    }
                },
                eventDataTransform: function(eventData) {
                    // Check if it's a count event (API sends classNames=['daily-count-event'] or id starts with 'count-')
                    if ((eventData.classNames && eventData.classNames.includes('daily-count-event')) || (eventData.id && String(eventData.id).startsWith('count-'))) {
                        eventData.classNames = ['daily-count-event'];
                        eventData.display = 'block';
                        eventData.textColor = '#3730a3'; // Force correct text color
                        eventData.backgroundColor = '#e0e7ff';
                        eventData.borderColor = '#c7d2fe';
                        return eventData;
                    }

                    eventData.extendedProps = { ...eventData.extendedProps, status: eventData.status };

                    // Define distinct colors for each status (Modern Palette)
                    let backgroundColor = '#9CA3AF'; // Default: Gray (No-show)
                    let borderColor = 'transparent';

                    switch(eventData.status) {
                        case 'Scheduled':
                            backgroundColor = '#6366f1'; // Indigo-500
                            break;
                        case 'Confirmed':
                            backgroundColor = '#10b981'; // Emerald-500
                            break;
                        case 'Checked-in':
                            backgroundColor = '#f59e0b'; // Amber-500
                            break;
                        case 'Completed':
                            backgroundColor = '#4b5563'; // Gray-600
                            break;
                        case 'Cancelled':
                            backgroundColor = '#ef4444'; // Red-500
                            break;
                        case 'No-show':
                            backgroundColor = '#9ca3af'; // Gray-400
                            break;
                        default:
                            backgroundColor = '#6b7280'; // Gray-500
                            break;
                    }

                    eventData.backgroundColor = backgroundColor;
                    eventData.borderColor = borderColor;
                    eventData.textColor = '#FFFFFF'; // Ensure text is white for contrast

                    return eventData;
                },
                // --- Event Hover (Tooltip) Callbacks ---
                eventMouseEnter: function(info) {
                    // Do not show tooltip for count events
                    if (info.event.classNames.includes('daily-count-event')) {
                        return;
                    }

                    const event = info.event;
                    const props = event.extendedProps;
                    const date = event.start.toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                    const time = event.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });

                    // Populate Tooltip
                    tooltipTitle.textContent = event.title || 'Patient Visit';

                    tooltipContent.innerHTML = `
                        <p><strong>Clinician:</strong> ${props.clinician || 'N/A'}</p>
                        <p><strong>Date:</strong> ${date}</p>
                        <p><strong>Time:</strong> ${time}</p>
                        <p><strong>Type:</strong> ${props.appointment_type || 'General'}</p>
                        <p><strong>Status:</strong> ${props.status || 'Unknown'}</p>
                        ${props.contact_number ? `<p><strong>Phone:</strong> ${props.contact_number}</p>` : ''}
                        ${props.address ? `<p><strong>Address:</strong> ${props.address}</p>` : ''}
                        ${props.notes ? `<p><strong>Notes:</strong> ${props.notes}</p>` : ''} <!-- FULL NOTES DISPLAY -->
                        `;

                    // Show and position Tooltip
                    tooltip.classList.remove('hidden');
                    tooltip.classList.add('show');

                    const rect = info.el.getBoundingClientRect();
                    const calendarRect = calendarEl.getBoundingClientRect();

                    // Position the tooltip to the right of the event element, or above if near the right edge
                    let leftPos = rect.right + 10;
                    let topPos = rect.top + window.scrollY;

                    // Adjust if tooltip goes off the right edge
                    if (leftPos + 300 > calendarRect.right) {
                        leftPos = rect.left - 300 - 10; // Position to the left
                        if (leftPos < calendarRect.left) {
                             // Fallback: position above the element
                             leftPos = rect.left;
                             topPos = rect.top + window.scrollY - tooltip.offsetHeight - 10;
                        }
                    }

                    tooltip.style.left = `${leftPos}px`;
                    tooltip.style.top = `${topPos}px`;
                },
                eventMouseLeave: function(info) {
                    // Hide Tooltip
                    tooltip.classList.remove('show');
                    // Add delay before hiding to prevent flicker
                    setTimeout(() => {
                        if (!tooltip.classList.contains('show')) {
                            tooltip.classList.add('hidden');
                        }
                    }, 150);
                },
                // --- END of Event Hover Callbacks ---

                // --- Existing eventContent hook to customize event display ---
                eventContent: function(arg) {
                    // Custom rendering for Daily Count Event
                    if (arg.event.classNames.includes('daily-count-event')) {
                        // Create a container
                        let container = document.createElement('div');
                        // Enhanced styling: Gradient pill with shadow
                        container.className = 'flex items-center justify-between w-full h-full px-3 py-1.5 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5 cursor-pointer';
                        
                        container.innerHTML = `
                            <div class="flex items-center space-x-2 font-bold text-sm">
                                <i data-lucide="calendar" class="w-4 h-4 text-blue-100"></i>
                                <span>${arg.event.title}</span>
                            </div>
                            <div class="flex items-center space-x-1">
                                <div class="p-1 hover:bg-white/20 rounded-full transition-colors route-icon" title="Plan Route">
                                    <i data-lucide="map-pin" class="w-4 h-4 text-white"></i>
                                </div>
                                <div class="p-1 hover:bg-white/20 rounded-full transition-colors list-icon" title="View List">
                                    <i data-lucide="users" class="w-4 h-4 text-white"></i>
                                </div>
                            </div>
                        `;

                        // Attach Event Listeners
                        const routeBtn = container.querySelector('.route-icon');
                        const listBtn = container.querySelector('.list-icon');

                        if (routeBtn) {
                            routeBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                const dateStr = arg.event.id.replace('count-', '');
                                openPlanRouteModal(dateStr, true); // Show Map
                            });
                        }

                        if (listBtn) {
                            listBtn.addEventListener('click', (e) => {
                                e.stopPropagation();
                                const dateStr = arg.event.id.replace('count-', '');
                                openPlanRouteModal(dateStr, false); // List only
                            });
                        }
                        
                        return { domNodes: [container] };
                    }

                    // Get the patient name (title)
                    let patientName = arg.event.title || 'Unknown Patient';
                    // Format the time (e.g., "2:30pm")
                    let eventTime = arg.event.start ? arg.event.start.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }).toLowerCase() : '';

                    // Create custom HTML for the event body
                    let innerHtml = `
                        <div class="fc-event-main-frame">
                        <div class="fc-event-time">${eventTime}</div>
                        <div class="fc-event-title-container">
                        <div class="fc-event-title fc-sticky">${patientName}</div>
                        </div>
                        </div>
                        `;

                    return { html: innerHtml };
                }
            });

            calendar.render();
        }

        // --- Modal close buttons ---
        closeModalBtn.addEventListener('click', () => {
            appointmentModal.classList.add('hidden');
            appointmentModal.classList.remove('flex');
        });
        closeAddModalBtn.addEventListener('click', () => {
            addAppointmentModal.classList.add('hidden');
            addAppointmentModal.classList.remove('flex');
        });

        // --- Save Status Handler (Disabled for facility) ---
        saveStatusBtn.addEventListener('click', async () => {
            if (userRole === 'facility') return; // Prevent saving status
            const newStatus = document.getElementById('modalStatusSelect').value;
            if (!currentAppointmentId) return;
            updateStatus(currentAppointmentId, newStatus);
        });

        // --- Delete Appointment Handler ---
        deleteAppointmentBtn.addEventListener('click', async () => {
            if (userRole === 'facility') return;
            if (!currentAppointmentId) return;
            
            if (confirm('Are you sure you want to cancel this appointment?')) {
                updateStatus(currentAppointmentId, 'Cancelled');
            }
        });

        // Disable status elements for facility in the details modal
        if (userRole === 'facility') {
            document.getElementById('modalStatusSelect').disabled = true;
            saveStatusBtn.style.display = 'none'; // Hide save button
            document.getElementById('startVisitBtn').style.display = 'none'; // Hide start visit button
            deleteAppointmentBtn.style.display = 'none'; // Hide delete button
        }


        // --- Add Appointment Form Submission (Disabled for facility) ---
        addAppointmentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (userRole === 'facility') return; // Prevent submission

            // Clear previous messages
            addModalMessage.classList.add('hidden');

            const formData = new FormData(addAppointmentForm);
            const data = {
                patient_id: formData.get('patient_id'),
                user_id: formData.get('user_id'),
                appointment_date: `${formData.get('appointment_date_only')}T${formData.get('appointment_time')}`
            };

            try {
                // Temporarily disable button and show loading if needed (not implemented here as it's a small modal action)

                const response = await fetch('api/create_appointment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);

                addModalMessage.textContent = 'Appointment created successfully! Refreshing calendar...';
                addModalMessage.className = 'p-3 my-3 rounded-md bg-green-100 text-green-800 font-medium text-sm';
                addModalMessage.classList.remove('hidden');

                setTimeout(() => {
                    addAppointmentModal.classList.add('hidden');
                    addAppointmentModal.classList.remove('flex');
                    calendar.refetchEvents();
                    addAppointmentForm.reset();
                }, 1500);

            } catch (error) {
                addModalMessage.textContent = `Error: ${error.message}`;
                addModalMessage.className = 'p-3 my-3 rounded-md bg-red-100 text-red-800 font-medium text-sm';
                addModalMessage.classList.remove('hidden');
            }
        });


        // --- Initial Load ---
        // This function executes the patient and clinician population logic:
        populateFilters();
        initializeCalendar();

        // --- Auto-Cancel Past Appointments ---
        // Automatically cancel appointments from previous days that have no charts
        fetch('api/auto_update_appointment_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.cancelled_count > 0) {
                    console.log(`Auto-cancelled ${data.cancelled_count} past appointments.`);
                    // Refresh calendar to show updated statuses
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                }
            })
            .catch(err => console.error("Error running auto-cancellation:", err));
    });
</script>