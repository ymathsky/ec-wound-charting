// Filename: ec/js/auto_narrative_notes.js
// Description: Core logic to combine structured data into a single narrative HTML block.

// NOTE: This function relies on the following global variables/functions
// being defined in other imported scripts:
// - window.currentHpiData, window.currentVitalsData, window.currentVisitAssessments (State)
// - getFieldContent, setFieldContent (Quill Manager/Utility)
// - getHpiNarrativeText, getVitalsNarrativeText, generateStructuredAssessmentText (Content Generators)

function updateAutoNarrative() {
    const contentDiv = document.getElementById('auto-narrative-content');
    if (!contentDiv) return;

    let narrativeHtml = '';
    let sectionsUsed = [];

    // --- 1. HPI Narrative ---
    if (document.getElementById('toggle-hpi')?.checked) {
        narrativeHtml += '<h2>History of Present Illness (HPI)</h2>' + getHpiNarrativeText();
        sectionsUsed.push('HPI');
    }

    // --- 2. Vitals Narrative ---
    if (document.getElementById('toggle-vitals')?.checked) {
        narrativeHtml += '<h2>Vitals</h2>' + getVitalsNarrativeText();
        sectionsUsed.push('Vitals');
    }

    // --- 3. Wound Assessment Narrative (Structured) ---
    if (document.getElementById('toggle-wound')?.checked && window.currentVisitAssessments?.length > 0) {
        narrativeHtml += '<h2>Wound Assessment Overview</h2>' + generateStructuredAssessmentText();
        sectionsUsed.push('Wound Assessment');
    }

    // --- 4. SOAP Notes Content ---
    if (document.getElementById('toggle-notes')?.checked) {
        const subjectiveHtml = getFieldContent('subjective');
        const objectiveHtml = getFieldContent('objective');
        const assessmentHtml = getFieldContent('assessment');
        const planHtml = getFieldContent('plan');

        if (subjectiveHtml || objectiveHtml || assessmentHtml || planHtml) {
            narrativeHtml += '<h2>SOAP Note Sections</h2>';
            if (subjectiveHtml) narrativeHtml += '<h3>Subjective (S)</h3>' + subjectiveHtml;
            if (objectiveHtml) narrativeHtml += '<h3>Objective (O)</h3>' + objectiveHtml;
            if (assessmentHtml) narrativeHtml += '<h3>Assessment (A)</h3>' + assessmentHtml;
            if (planHtml) narrativeHtml += '<h3>Plan (P)</h3>' + planHtml;
            sectionsUsed.push('SOAP Notes');
        }
    }

    // --- 5. Graft/Skin Data ---
    // Placeholder for future logic if you add data capture for this section
    if (document.getElementById('toggle-graft')?.checked) {
        // Example: Check if graft data exists
        // if (window.graftData?.status === 'performed') {
        //     narrativeHtml += '<h2>Graft/Skin Assessment</h2><p>Graft performed on [Date] with good capillary refill.</p>';
        //     sectionsUsed.push('Graft');
        // } else {
        narrativeHtml += '<p class="text-gray-500">Graft/Skin data toggled, but no relevant data was found.</p>';
        // }
    }


    if (narrativeHtml === '') {
        contentDiv.innerHTML = '<p class="text-gray-500 text-center py-6 border border-dashed rounded-lg">Select sections to view or enter clinical data to generate narrative.</p>';
    } else {
        contentDiv.innerHTML = narrativeHtml;
    }
}

// Attach listener to narrative toggles
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.narrative-toggle-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateAutoNarrative);
    });
});