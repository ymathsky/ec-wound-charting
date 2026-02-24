<?php
// Filename: ec/visit_notes_history.php
session_start();

// Basic validation
if (!isset($_GET['patient_id']) || !isset($_GET['appointment_id'])) {
    die("Patient ID and Appointment ID are required.");
}
$patient_id = htmlspecialchars($_GET['patient_id']);
$current_appointment_id = htmlspecialchars($_GET['appointment_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Notes History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        /* Styles copied from visit_patient.php for consistency */
        .note-card {
            border-left: 4px solid #3B82F6;
            transition: box-shadow 0.2s ease;
            cursor: pointer;
        }
        .note-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .note-body {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-in-out;
        }
        .note-body.open {
            max-height: 1000px; /* Large value to allow height transition */
        }
        .note-chevron {
            transition: transform 0.3s ease-in-out;
        }
        .note-chevron.open {
            transform: rotate(90deg);
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border-left-color: #09f;
            animation: spin 1s ease infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 sm:p-6">
<div class="max-w-4xl w-full mx-auto">
    <h1 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Past Clinical Notes (History)</h1>
    <div id="notes-history-container">
        <div id="loading" class="flex justify-center items-center h-24"><div class="spinner"></div></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const patientId = <?php echo $patient_id; ?>;
        const currentAppointmentId = <?php echo $current_appointment_id; ?>;
        const notesHistoryContainer = document.getElementById('notes-history-container');
        const loadingDiv = document.getElementById('loading');
        let globalNotesData = [];

        async function fetchNotesHistory() {
            loadingDiv.classList.remove('hidden');

            try {
                // Fetch ALL notes for the patient
                const response = await fetch(`api/get_notes.php?patient_id=${patientId}`);
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || 'Failed to fetch notes history.');
                }

                globalNotesData = await response.json();

                // Filter out the current note being worked on in the parent window
                const historyNotes = globalNotesData.filter(note => note.appointment_id != currentAppointmentId);

                renderNotesHistory(historyNotes);
            } catch (error) {
                notesHistoryContainer.innerHTML = `<p class="text-red-500 py-8">Error loading notes: ${error.message}</p>`;
                console.error("Error fetching notes:", error);
            } finally {
                loadingDiv.classList.add('hidden');
            }
        }

        function renderNotesHistory(notes) {
            if (!notes || notes.length === 0) {
                notesHistoryContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No previous clinical notes found for this patient.</p>';
                return;
            }

            const historyHtml = notes.map(note => {
                // Helper to render sections with individual copy buttons
                const renderSection = (key, label, content) => {
                    const hasContent = content && content.replace(/<[^>]*>/g, '').trim() !== '';
                    return `
                    <div class="mb-3 group relative border-b border-gray-100 pb-2 last:border-0">
                        <div class="flex justify-between items-center mb-1">
                            <strong class="text-sm text-gray-700">${label}:</strong>
                            ${hasContent ? `
                            <button type="button" 
                                class="copy-part-btn opacity-0 group-hover:opacity-100 transition-opacity text-xs bg-blue-50 text-blue-600 px-2 py-0.5 rounded hover:bg-blue-100 flex items-center border border-blue-100"
                                title="Copy only ${label}"
                                data-note-id="${note.note_id}" 
                                data-field="${key}">
                                <svg class="h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                Copy
                            </button>` : ''}
                        </div>
                        <div class="text-sm text-gray-600 prose prose-sm max-w-none">${content || '-'}</div>
                    </div>`;
                };

                // Strip HTML for the header summary
                const ccSummary = note.chief_complaint ? note.chief_complaint.replace(/<[^>]*>/g, '').substring(0, 50) : 'No Chief Complaint';

                return `
                <div class="note-card bg-white p-4 mb-4 rounded-lg shadow-sm">
                    <div class="flex justify-between items-center header-toggle cursor-pointer" data-target="#note-body-${note.note_id}">
                        <h4 class="text-md font-semibold text-gray-800 truncate pr-4">
                            ${note.note_date} - ${ccSummary}
                        </h4>
                        <div class="flex items-center space-x-2 flex-shrink-0">
                            <span class="text-xs text-gray-500 hidden sm:inline">Dr. ${note.full_name ? note.full_name.split(' ').pop() : 'N/A'}</span>
                            <button type="button" data-note-id="${note.note_id}" class="copy-note-btn bg-gray-100 text-gray-700 hover:bg-gray-200 px-3 py-1 text-xs font-semibold rounded-md transition flex items-center border border-gray-200" title="Copy Entire Note">
                                <svg class="h-3 w-3 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                                Copy All
                            </button>
                            <svg class="note-chevron h-4 w-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </div>
                    </div>
                    <div id="note-body-${note.note_id}" class="note-body mt-3 pt-3 border-t border-gray-100" style="max-height: 0;">
                        ${renderSection('chief_complaint', 'Chief Complaint', note.chief_complaint)}
                        ${renderSection('subjective', 'Subjective', note.subjective)}
                        ${renderSection('objective', 'Objective', note.objective)}
                        ${renderSection('assessment', 'Assessment', note.assessment)}
                        ${renderSection('plan', 'Plan', note.plan)}
                    </div>
                </div>
            `}).join('');

            notesHistoryContainer.innerHTML = historyHtml;

            // Add click listeners for toggling visibility
            document.querySelectorAll('.header-toggle').forEach(header => {
                header.addEventListener('click', () => {
                    const targetId = header.dataset.target;
                    const body = document.querySelector(targetId);
                    const chevron = header.querySelector('.note-chevron');

                    const isHidden = body.style.maxHeight === '0px' || body.style.maxHeight === '';

                    // Close all other open notes
                    document.querySelectorAll('.note-body').forEach(openBody => {
                        if (openBody !== body) {
                            openBody.style.maxHeight = '0px';
                            openBody.classList.remove('open');
                        }
                    });
                    document.querySelectorAll('.note-chevron').forEach(openChevron => {
                        if (openChevron !== chevron) {
                            openChevron.style.transform = 'rotate(0deg)';
                            openChevron.classList.remove('open');
                        }
                    });

                    if (isHidden) {
                        body.style.maxHeight = `${body.scrollHeight + 50}px`; // Add buffer
                        chevron.style.transform = 'rotate(90deg)';
                        body.classList.add('open');
                        chevron.classList.add('open');
                    } else {
                        body.style.maxHeight = '0px';
                        chevron.style.transform = 'rotate(0deg)';
                        body.classList.remove('open');
                        chevron.classList.remove('open');
                    }
                });
            });

            // Add click listeners for Copy All buttons
            document.querySelectorAll('.copy-note-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const noteId = parseInt(btn.dataset.noteId);
                    const noteToCopy = globalNotesData.find(n => n.note_id == noteId);

                    if (noteToCopy) {
                        if (window.parent) {
                            window.parent.postMessage({
                                type: 'copyNote',
                                data: noteToCopy
                            }, '*');
                        }
                    }
                });
            });

            // Add click listeners for Copy Part buttons
            document.querySelectorAll('.copy-part-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const noteId = parseInt(btn.dataset.noteId);
                    const field = btn.dataset.field;
                    const note = globalNotesData.find(n => n.note_id == noteId);

                    if (note && note[field]) {
                        const payload = {};
                        payload[field] = note[field];
                        payload.visit_date = note.note_date;
                        
                        if (window.parent) {
                            window.parent.postMessage({
                                type: 'copyNote',
                                data: payload
                            }, '*');
                        }
                    }
                });
            });
        }

        // Initial data fetch
        fetchNotesHistory();
    });
</script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
</body>
</html>
