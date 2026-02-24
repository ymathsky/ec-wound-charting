// Filename: ec/js/soap_plan_checklist.js
// Purpose: Handles all logic for the Plan Checklist modal.

/**
 * Applies selected checklist items and custom text to the main plan editor.
 */
function applyPlanNotes() {
    const form = document.getElementById('planChecklistForm');
    const customText = document.getElementById('plan_custom');
    const noteMessage = document.getElementById('note-message');

    if (!form) return;

    let content = '';
    const selectedItems = form.querySelectorAll('.checklist-item:checked');
    const customNote = customText ? customText.value.trim() : '';

    if (selectedItems.length > 0) {
        content += `<h3>Checklist Items (Plan):</h3><ul>`;
        selectedItems.forEach(item => {
            content += `<li>${item.getAttribute('data-text')}</li>`;
        });
        content += '</ul>';
    }

    if (customNote.length > 0) {
        // Treat each line as a separate item for the plan
        content += `<p><strong>Custom Plan Items:</strong></p><ul>`;
        customNote.split('\n').forEach(line => {
            if (line.trim()) {
                content += `<li>${line.trim()}</li>`;
            }
        });
        content += '</ul>';
    }

    if (content.length > 0) {
        if (typeof getFieldContent === 'function' && typeof setFieldContent === 'function') {
            setFieldContent('plan', getFieldContent('plan') + content);
            showMessage(noteMessage, `Checklist items appended to Plan section.`, 'success');
        }
    }

    // Clear and close
    if(form) form.reset();
    if(customText) customText.value = '';
    const modal = document.getElementById('planModal');
    const dialog = document.getElementById('planModalDialog');
    closeModal(modal, dialog);
}

/**
 * Initializes all event listeners for the Plan modal.
 */
function initPlanChecklist() {
    const modal = document.getElementById('planModal');
    const dialog = document.getElementById('planModalDialog');
    const form = document.getElementById('planChecklistForm');
    const customText = document.getElementById('plan_custom');

    // Listener to open the modal
    document.querySelector('#plan-section [data-action="checklist"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        // openChecklistModal is defined in visit_notes_logic.js
        if (typeof openChecklistModal === 'function') {
            openChecklistModal('plan');
        }
    });

    // Listener for the "Apply" button
    document.getElementById('applyPlanNotesBtn')?.addEventListener('click', applyPlanNotes);

    // Listener for the "Clear" button
    document.getElementById('clearPlanFormBtn')?.addEventListener('click', () => {
        if(form) form.reset();
        if(customText) customText.value = '';
    });

    // Listener for the "Close" (X) button
    document.getElementById('closePlanModalBtn')?.addEventListener('click', () => {
        closeModal(modal, dialog);
    });
}