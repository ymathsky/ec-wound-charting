// Filename: ec/js/soap_objective_checklist.js
// Purpose: Handles all logic for the Objective (PE) Checklist modal.

/**
 * Applies selected checklist items and custom text to the main objective editor.
 */
function applyObjectiveNotes() {
    const form = document.getElementById('objectiveForm');
    const customText = document.getElementById('objective_custom');
    const noteMessage = document.getElementById('note-message');

    if (!form) return;

    let content = '';
    const selectedItems = form.querySelectorAll('.checklist-item:checked');
    const customNote = customText ? customText.value.trim() : '';

    if (selectedItems.length > 0) {
        content += `<h3>Checklist Findings (PE):</h3><ul>`;
        selectedItems.forEach(item => {
            content += `<li>${item.getAttribute('data-text')}</li>`;
        });
        content += '</ul>';
    }

    if (customNote.length > 0) {
        content += `<p><strong>Custom Objective:</strong> ${customNote.replace(/\n/g, '<br>')}</p>`;
    }

    if (content.length > 0) {
        if (typeof getFieldContent === 'function' && typeof setFieldContent === 'function') {
            setFieldContent('objective', getFieldContent('objective') + content);
            showMessage(noteMessage, `Checklist items appended to Objective section.`, 'success');
        }
    }

    // Clear and close
    if(form) form.reset();
    if(customText) customText.value = '';
    const modal = document.getElementById('objectiveModal');
    const dialog = document.getElementById('objectiveModalDialog');
    closeModal(modal, dialog);
}

/**
 * Initializes all event listeners for the Objective modal.
 */
function initObjectiveChecklist() {
    const modal = document.getElementById('objectiveModal');
    const dialog = document.getElementById('objectiveModalDialog');
    const form = document.getElementById('objectiveForm');
    const customText = document.getElementById('objective_custom');

    // Listener to open the modal
    document.querySelector('#objective-section [data-action="checklist"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        // openChecklistModal is defined in visit_notes_logic.js
        if (typeof openChecklistModal === 'function') {
            openChecklistModal('objective');
        }
    });

    // Listener for the "Apply" button
    document.getElementById('applyObjectiveNotesBtn')?.addEventListener('click', applyObjectiveNotes);

    // Listener for the "Clear" button
    document.getElementById('clearObjectiveFormBtn')?.addEventListener('click', () => {
        if(form) form.reset();
        if(customText) customText.value = '';
    });

    // Listener for the "Close" (X) button
    document.getElementById('closeObjectiveModalBtn')?.addEventListener('click', () => {
        closeModal(modal, dialog);
    });
}