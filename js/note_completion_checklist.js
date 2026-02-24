// Filename: ec/js/note_completion_checklist.js
// Purpose: Handles all logic for the "Note Completion Checklist" panel.

/**
 * Checks all note sections and updates the "Note Completion Checklist" panel.
 */
function updateNoteCompletionChecklist() {
    const checklistEl = document.getElementById('note-completion-checklist');
    if (!checklistEl) return;

    // Helper to check if Quill editor has meaningful content
    const hasContent = (id) => {
        // getFieldContent is defined in quill_editor_manager.js
        if (typeof getFieldContent !== 'function') return false;

        const editor = window.quillEditors[id];
        if (editor) {
            return editor.getLength() > 1; // Quill's "empty" is length 1 (a newline)
        }
        return false;
    };

    // 1. Define all checks
    const checks = [
        {
            name: 'Chief Complaint',
            isDone: getFieldContent('chief_complaint').trim() !== ''
        },
        {
            name: 'Vitals',
            isDone: (window.globalDataBundle?.visit?.vitals) ? true : false
},
    {
        name: 'HPI Narrative',
            isDone: (window.globalDataBundle?.visit?.hpi_narrative) ? true : false
    },
    {
        name: 'Wound Assessments',
            isDone: (window.globalDataBundle?.visit?.wound_assessments?.length || 0) > 0
    },
    {
        name: 'Encounter Diagnoses',
            isDone: (window.globalDataBundle?.visit?.diagnoses?.length || 0) > 0
    },
    {
        name: 'Subjective Note',
            isDone: hasContent('subjective')
    },
    {
        name: 'Objective Note',
            isDone: hasContent('objective')
    },
    {
        name: 'Assessment Note',
            isDone: hasContent('assessment')
    },
    {
        name: 'Plan Note',
            isDone: hasContent('plan')
    }
];

    // 2. Build HTML
    let html = '';
    checks.forEach(check => {
        const isDone = check.isDone;
        const statusClass = isDone ? 'is-done' : 'is-missing';
        const icon = isDone ? 'check-circle-2' : 'x-circle';
        const color = isDone ? 'text-green-600' : 'text-red-600';

        html += `
            <div class="checklist-item ${statusClass}">
                <i data-lucide="${icon}" class="${color}"></i>
                <span>${check.name}</span>
            </div>
        `;
    });

    checklistEl.innerHTML = html;
    if (typeof lucide !== 'undefined') {
        lucide.createIcons(); // Re-render icons
    }
}

/**
 * Initializes the checklist logic, runs it once, and attaches listeners.
 */
function initNoteCompletionChecklist() {
    // Run once on page load (after data is fetched)
    updateNoteCompletionChecklist();

    // Attach live update listeners to all Quill editors
    Object.keys(window.quillEditors).forEach(id => {
        if (window.quillEditors[id]) {
            window.quillEditors[id].on('text-change', () => {
                updateNoteCompletionChecklist();
            });
        }
    });

    // Attach listener to the "Save CC" button
    const saveCcBtn = document.getElementById('save_cc_btn');
    if (saveCcBtn) {
        saveCcBtn.addEventListener('click', () => {
            // Short delay to ensure editor content is saved before check
            setTimeout(updateNoteCompletionChecklist, 100);
        });
    }
}