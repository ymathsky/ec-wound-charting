// Filename: ec/js/visit_dropdown.js
// Description: Manages all custom Action dropdown and Quick Insert menu behaviors for
// Subjective, Objective, Assessment, and Plan sections.

// This file relies on global functions and state defined in other imported scripts:
// - getFieldContent, setFieldContent, clearFieldContent (from quill_editor_manager.js)
// - getHpiNarrativeText, getVitalsNarrativeText, generateStructuredAssessmentText (from soap_generator_utils.js)
// - fetchAndRenderSoapChecklist, suggestRosPeItems, suggestPlanItems (from soap_generator_utils.js)
// - quickSnippets, window.currentVitalsData, window.currentVisitAssessments (Global State)


// --- Assessment Content Builder ---

/**
 * Rebuilds the Assessment Quill content based on selected checkboxes and static data.
 * This is called whenever an Assessment dropdown checkbox is toggled.
 */
function updateAssessmentContent() {
    let content = '';

    // Checkboxes and diagnosis link state
    const chkAssessmentSummary = document.getElementById('chkAssessmentSummary');
    const chkWoundAssessment = document.getElementById('chkWoundAssessment');
    const chkDebridement = document.getElementById('chkDebridement');
    const chkSkinGraft = document.getElementById('chkSkinGraft');
    const dropdownDiagnosisLink = document.getElementById('dropdownDiagnosisLink');


    if (chkAssessmentSummary?.checked) {
        content += '<h3>Assessment Summary:</h3>' + generateStructuredAssessmentText();
    }
    if (chkWoundAssessment?.checked) {
        // Placeholder: Replace with logic to generate detailed wound assessment notes
        content += '<h3>Wound Assessment Details:</h3><p>Detailed notes section placeholder. Review visit_wounds for wound assessment details.</p>';
    }
    if (chkDebridement?.checked) {
        // Placeholder: Replace with logic to generate debridement notes
        content += '<h3>Debridement:</h3><p>Debridement details placeholder: Sharp debridement performed. Tissue margin evaluated.</p>';
    }
    if (chkSkinGraft?.checked) {
        // Placeholder: Replace with logic to generate skin graft notes
        content += '<h3>Skin Graft/Biologic:</h3><p>Skin graft/biologic procedure status placeholder.</p>';
    }

    // Diagnosis (Always include if link is "active")
    if (dropdownDiagnosisLink?.classList.contains('active-link')) {
        content += '<h3>Diagnosis:</h3><p>ICD-10 codes and descriptions here (Dynamic content placeholder).</p>';
    }

    setFieldContent('assessment', content.trim());
}


document.addEventListener('DOMContentLoaded', function() {
    const patientId = window.phpVars.patientId;

    // --- Subjective Modals/Elements ---
    const subjectiveModal = document.getElementById('subjectiveModal');
    const subjectiveModalDialog = document.getElementById('subjectiveModalDialog');
    const subjectivePreviewModal = document.getElementById('subjectivePreviewModal');
    const subjectivePreviewModalDialog = document.getElementById('subjectivePreviewModalDialog');
    const subjectivePreviewContent = document.getElementById('subjective-preview-content');

    // --- Objective Modals/Elements ---
    const objectiveModal = document.getElementById('objectiveModal');
    const objectiveModalDialog = document.getElementById('objectiveModalDialog');
    const objectivePreviewModal = document.getElementById('objectivePreviewModal');
    const objectivePreviewModalDialog = document.getElementById('objectivePreviewModalDialog');
    const objectivePreviewContent = document.getElementById('objective-preview-content');

    // --- Assessment Modals/Elements ---
    const assessmentPreviewModal = document.getElementById('assessmentPreviewModal');
    const assessmentPreviewModalDialog = document.getElementById('assessmentPreviewModalDialog');
    const assessmentPreviewContent = document.getElementById('assessment-preview-content');

    // --- Plan Modals/Elements ---
    const planModal = document.getElementById('planModal');
    const planPreviewModal = document.getElementById('planPreviewModal');
    const planPreviewModalDialog = document.getElementById('planPreviewModalDialog');
    const planPreviewContent = document.getElementById('plan-preview-content');


    // -------------------------------------------------------------
    // GENERAL DROPDOWN TOGGLE LOGIC (Applies to Action and Quick Insert menus)
    // -------------------------------------------------------------
    document.querySelectorAll('[data-dropdown-toggle], .quick-insert-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const targetId = this.getAttribute('data-dropdown-toggle') || this.getAttribute('data-target');
            const targetMenu = document.getElementById(targetId);

            document.querySelectorAll('[id^="dropdown-"], [id$="-quick-insert-menu"]').forEach(menu => {
                if (menu.id !== targetId) {
                    menu.classList.add('hidden');
                }
            });

            if (targetMenu) {
                targetMenu.classList.toggle('hidden');
            }
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        document.querySelectorAll('[id^="dropdown-"], [id$="-quick-insert-menu"]').forEach(menu => {
            const button = document.querySelector(`[data-dropdown-toggle="${menu.id}"], [data-target="${menu.id}"]`);
            if (button && (menu.contains(e.target) || button.contains(e.target) || menu.classList.contains('hidden'))) {
                return;
            }
            menu.classList.add('hidden');
        });
    });

    // -------------------------------------------------------------
    // SUBJECTIVE ACTION DROPDOWN LOGIC
    // -------------------------------------------------------------
    document.getElementById('dropdownChecklistLink')?.addEventListener('click', async (e) => {
        e.preventDefault();
        // Use flag here to prevent multiple fetches if checklist is already loaded
        const checklistContainer = document.getElementById('subjective-checklist-container');
        if (checklistContainer.children.length <= 1) {
            await fetchAndRenderSoapChecklist('subjective');
        }
        suggestRosPeItems();
        openModal(subjectiveModal, subjectiveModalDialog);
    });

    document.getElementById('dropdownHpiSummaryLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const hpiContent = `<h3>History of Present Illness:</h3>${getHpiNarrativeText()}`;
        setFieldContent('subjective', hpiContent);
        showMessage(document.getElementById('note-message'), 'HPI Summary inserted into Subjective.', 'info');
    });

    document.getElementById('dropdownQuickPreviewLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const currentContent = getFieldContent('subjective');
        if (subjectivePreviewContent) {
            subjectivePreviewContent.innerHTML = currentContent || '<p class="text-gray-500">Subjective editor is currently empty.</p>';
        }
        openModal(subjectivePreviewModal, subjectivePreviewModalDialog);
    });

    document.getElementById('dropdownMedicationReviewLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        window.open(`patient_medication.php?patient_id=${patientId}`, '_blank');
    });

    document.getElementById('dropdownClearTextLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        clearFieldContent('subjective');
        showMessage(document.getElementById('note-message'), 'Subjective section cleared.', 'info');
    });

    // Attach close handler for the preview modal
    document.getElementById('closeSubjectivePreviewModalBtn')?.addEventListener('click', () => closeModal(subjectivePreviewModal, subjectivePreviewModalDialog));
    document.getElementById('copySubjectivePreviewBtn')?.addEventListener('click', () => {
        const previewContent = subjectivePreviewContent ? subjectivePreviewContent.innerHTML : '';
        copyToClipboard(previewContent);
        showMessage(document.getElementById('note-message'), 'Subjective preview copied to clipboard!', 'success');
        closeModal(subjectivePreviewModal, subjectivePreviewModalDialog);
    });


    // -------------------------------------------------------------
    // OBJECTIVE ACTION DROPDOWN LOGIC
    // -------------------------------------------------------------
    document.getElementById('dropdownObjectiveChecklistLink')?.addEventListener('click', async (e) => {
        e.preventDefault();
        const checklistContainer = document.getElementById('objective-checklist-container');
        if (checklistContainer.children.length <= 1) {
            await fetchAndRenderSoapChecklist('objective');
        }
        suggestRosPeItems();
        openModal(objectiveModal, objectiveModalDialog);
    });

    document.getElementById('dropdownAddVitalsLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const vitalsContent = `<h3>Vitals:</h3>${getVitalsNarrativeText()}`;

        let currentObjectiveHtml = getFieldContent('objective');
        const vitalsMarker = '<h3>Vitals:</h3>';

        // Find existing Vitals marker and replace the content block, or prepend if missing
        if (currentObjectiveHtml.includes(vitalsMarker)) {
            // Find content before Vitals marker and content after (Physical Exam header)
            const startVitalsIndex = currentObjectiveHtml.indexOf(vitalsMarker);
            const startPeIndex = currentObjectiveHtml.indexOf('<h3>Physical Exam:</h3>');

            let contentBeforeVitals = currentObjectiveHtml.substring(0, startVitalsIndex);
            let contentAfterVitals = (startPeIndex !== -1 && startPeIndex > startVitalsIndex)
                ? currentObjectiveHtml.substring(startPeIndex)
                : '';

            setFieldContent('objective', contentBeforeVitals.trim() + vitalsContent + contentAfterVitals.trim());

        } else {
            // Vitals section doesn't exist, prepend it.
            setFieldContent('objective', vitalsContent + currentObjectiveHtml);
        }

        showMessage(document.getElementById('note-message'), 'Vitals data inserted into Objective.', 'info');
    });

    document.getElementById('dropdownObjectivePreviewLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const currentContent = getFieldContent('objective');
        if (objectivePreviewContent) {
            objectivePreviewContent.innerHTML = currentContent || '<p class="text-gray-500">Objective editor is currently empty.</p>';
        }
        openModal(objectivePreviewModal, objectivePreviewModalDialog);
    });

    document.getElementById('dropdownObjectiveClearTextLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        clearFieldContent('objective');
        showMessage(document.getElementById('note-message'), 'Objective section cleared.', 'info');
    });

    document.getElementById('closeObjectivePreviewModalBtn')?.addEventListener('click', () => closeModal(objectivePreviewModal, objectiveModalDialog));

    // -------------------------------------------------------------
    // ASSESSMENT ACTION DROPDOWN LOGIC
    // -------------------------------------------------------------

    // Assessment Actions: Checkbox Toggles and Insertion
    document.querySelectorAll('#dropdown-A .assessment-section-link').forEach(link => {
        link.addEventListener('click', function(e) {
            const checkbox = this.querySelector('input[type="checkbox"]');
            const action = this.getAttribute('data-action');

            if (checkbox && e.target !== checkbox) {
                e.preventDefault();
                checkbox.checked = !checkbox.checked;
                updateAssessmentContent();
            } else if (action === 'diagnosis') {
                e.preventDefault(); // Prevent navigation
                e.currentTarget.classList.toggle('active-link');
                updateAssessmentContent();
            }
        });
    });

    document.getElementById('dropdownAssessmentPreviewLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const currentContent = getFieldContent('assessment');
        if (assessmentPreviewContent) {
            assessmentPreviewContent.innerHTML = currentContent || '<p class="text-gray-500">Assessment editor is currently empty.</p>';
        }
        openModal(assessmentPreviewModal, assessmentPreviewModalDialog);
    });

    document.getElementById('dropdownAssessmentClearContentLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        clearFieldContent('assessment');
        // Also clear all pseudo-checkbox states
        document.getElementById('chkAssessmentSummary').checked = false;
        document.getElementById('chkWoundAssessment').checked = false;
        document.getElementById('chkDebridement').checked = false;
        document.getElementById('chkSkinGraft').checked = false;
        document.getElementById('dropdownDiagnosisLink')?.classList.remove('active-link');

        showMessage(document.getElementById('note-message'), 'Assessment section cleared.', 'info');
    });

    document.getElementById('closeAssessmentPreviewModalBtn')?.addEventListener('click', () => closeModal(assessmentPreviewModal, assessmentPreviewModalDialog));


    // -------------------------------------------------------------
    // PLAN ACTION DROPDOWN LOGIC
    // -------------------------------------------------------------
    document.getElementById('dropdownPlanChecklistLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.getElementById('openPlanModalBtn')?.click();
    });

    document.getElementById('dropdownPlanTreatmentLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        let treatmentHtml = '<h3>Treatment Plan:</h3>';
        (window.currentVisitAssessments || []).forEach((wound, index) => {
            treatmentHtml += `<p><b>Wound #${index + 1}: ${wound.location} (${wound.wound_type})</b></p><ol><li>Cleanse wound.</li><li>Apply dressing.</li><li>Follow-up in one week.</li></ol>`;
        });
        setFieldContent('plan', getFieldContent('plan') + treatmentHtml);
        showMessage(document.getElementById('note-message'), 'Per-Wound Treatment template inserted.', 'info');
    });

    document.getElementById('dropdownNutritionEducationLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const snippet = '<p><b>Nutrition Education:</b> Educated patient/family on high protein/high calorie diet importance for wound healing.</p>';
        setFieldContent('plan', getFieldContent('plan') + snippet);
        showMessage(document.getElementById('note-message'), 'Nutrition Education snippet inserted.', 'info');
    });

    document.getElementById('dropdownMedicationLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const snippet = '<h3>Medications:</h3><p>Reviewed and reconciled current medication list. Continue active medications. No new prescriptions issued today.</p>';
        setFieldContent('plan', getFieldContent('plan') + snippet);
        showMessage(document.getElementById('note-message'), 'Medication review snippet inserted.', 'info');
    });

    document.getElementById('dropdownClinicalSuggestionsLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        showMessage(document.getElementById('note-message'), 'Please use the Quick Insert button (pen icon) to the left to add clinical suggestions.', 'info');
    });

    document.getElementById('dropdownPlanPreviewLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        const currentContent = getFieldContent('plan');
        const planPreviewContent = document.getElementById('plan-preview-content');
        if (planPreviewContent) {
            planPreviewContent.innerHTML = currentContent || '<p class="text-gray-500">Plan editor is currently empty.</p>';
        }
        openModal(planPreviewModal, document.getElementById('planPreviewModalDialog'));
    });

    document.getElementById('dropdownPlanClearTextLink')?.addEventListener('click', (e) => {
        e.preventDefault();
        clearFieldContent('plan');
        showMessage(document.getElementById('note-message'), 'Plan section cleared.', 'info');
    });

    document.getElementById('closePlanPreviewModalBtn')?.addEventListener('click', () => closeModal(planPreviewModal, document.getElementById('planPreviewModalDialog')));

});