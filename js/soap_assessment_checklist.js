// Filename: ec/js/soap_assessment_checklist.js
// Purpose: Handles all logic for the Assessment Checklist modal.

/**
 * Applies selected checklist items and custom text to the main assessment editor.
 */
function applyAssessmentNotes() {
    const form = document.getElementById('assessmentForm');
    const customText = document.getElementById('assessment_custom');
    const noteMessage = document.getElementById('note-message');

    if (!form) return;

    let content = '';
    const selectedItems = form.querySelectorAll('.checklist-item:checked');
    const customNote = customText ? customText.value.trim() : '';

    if (selectedItems.length > 0) {
        content += `<h3>Checklist Findings (Assessment):</h3><ul>`;
        selectedItems.forEach(item => {
            content += `<li>${item.getAttribute('data-text')}</li>`;
        });
        content += '</ul>';
    }

    if (customNote.length > 0) {
        content += `<p><strong>Custom Assessment:</strong> ${customNote.replace(/\n/g, '<br>')}</p>`;
    }

    if (content.length > 0) {
        if (typeof getFieldContent === 'function' && typeof setFieldContent === 'function') {
            setFieldContent('assessment', getFieldContent('assessment') + content);
            showMessage(noteMessage, `Checklist items appended to Assessment section.`, 'success');
        }
    }

    // Clear and close
    if(form) form.reset();
    if(customText) customText.value = '';
    const modal = document.getElementById('assessmentModal');
    const dialog = document.getElementById('assessmentModalDialog');
    closeModal(modal, dialog);
}

/**
 * Initializes all event listeners for the Assessment modal.
 */
function initAssessmentChecklist() {
    const modal = document.getElementById('assessmentModal');
    const dialog = document.getElementById('assessmentModalDialog');
    const form = document.getElementById('assessmentForm');
    const customText = document.getElementById('assessment_custom');

    // Listener to open the modal
    document.querySelector('#assessment-section [data-action="checklist"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        // openChecklistModal is defined in visit_notes_logic.js
        if (typeof openChecklistModal === 'function') {
            openChecklistModal('assessment');
        }
    });

    // Listener for the "Apply" button
    document.getElementById('applyAssessmentNotesBtn')?.addEventListener('click', applyAssessmentNotes);

    // Listener for the "Clear" button
    document.getElementById('clearAssessmentFormBtn')?.addEventListener('click', () => {
        if(form) form.reset();
        if(customText) customText.value = '';
    });

    // Listener for the "Close" (X) button
    document.getElementById('closeAssessmentModalBtn')?.addEventListener('click', () => {
        closeModal(modal, dialog);
    });
}