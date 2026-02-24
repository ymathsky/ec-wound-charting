// Filename: ec/js/soap_subjective_checklist.js
// Purpose: Handles all logic for the Subjective (ROS) Checklist modal.

/**
 * Applies selected checklist items and custom text to the main subjective editor.
 */
function applySubjectiveNotes() {
    const form = document.getElementById('subjectiveForm');
    const customText = document.getElementById('subjective_custom');
    const noteMessage = document.getElementById('note-message');

    if (!form) return;

    let content = '';
    const selectedItems = form.querySelectorAll('.checklist-item:checked');
    const customNote = customText ? customText.value.trim() : '';

    if (selectedItems.length > 0) {
        content += `<h3>Checklist Findings (ROS):</h3><ul>`;
        selectedItems.forEach(item => {
            content += `<li>${item.getAttribute('data-text')}</li>`;
        });
        content += '</ul>';
    }

    if (customNote.length > 0) {
        content += `<p><strong>Custom Subjective:</strong> ${customNote.replace(/\n/g, '<br>')}</p>`;
    }

    if (content.length > 0) {
        if (typeof getFieldContent === 'function' && typeof setFieldContent === 'function') {
            setFieldContent('subjective', getFieldContent('subjective') + content);
            showMessage(noteMessage, `Checklist items appended to Subjective section.`, 'success');
        }
    }

    // Clear and close
    if(form) form.reset();
    if(customText) customText.value = '';
    const modal = document.getElementById('subjectiveModal');
    const dialog = document.getElementById('subjectiveModalDialog');
    closeModal(modal, dialog);
}

/**
 * Initializes all event listeners for the Subjective modal.
 */
function initSubjectiveChecklist() {
    const modal = document.getElementById('subjectiveModal');
    const dialog = document.getElementById('subjectiveModalDialog');
    const form = document.getElementById('subjectiveForm');
    const customText = document.getElementById('subjective_custom');

    // Listener to open the modal
    document.querySelector('#subjective-section [data-action="checklist"]')?.addEventListener('click', (e) => {
        e.preventDefault();
        // openChecklistModal is defined in visit_notes_logic.js
        if (typeof openChecklistModal === 'function') {
            openChecklistModal('subjective');
        }
    });

    // Listener for the "Apply" button
    document.getElementById('applySubjectiveNotesBtn')?.addEventListener('click', applySubjectiveNotes);

    // Listener for the "Clear" button
    document.getElementById('clearSubjectiveFormBtn')?.addEventListener('click', () => {
        if(form) form.reset();
        if(customText) customText.value = '';
    });

    // Listener for the "Close" (X) button
    document.getElementById('closeSubjectiveModalBtn')?.addEventListener('click', () => {
        closeModal(modal, dialog);
    });
}