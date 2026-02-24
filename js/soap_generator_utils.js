// Filename: ec/js/soap_generator_utils.js
// Description: Contains utility functions for generating SOAP note content,
// primarily returning HTML for insertion into Quill RTEs.

/**
 * Generates narrative text for HPI.
 * @returns {string} HTML narrative.
 */
function getHpiNarrativeText() {
    const currentHpiData = window.currentHpiData; // Get global state
    if (!currentHpiData || Object.keys(currentHpiData).length === 0 || !currentHpiData.problem_status) {
        return '<p>No HPI data has been charted for this visit.</p>';
    }
    let parts = [];
    if (currentHpiData.problem_status) {
        parts.push(`Patient reports problem is <b>${currentHpiData.problem_status}</b>.`);
    }
    if (currentHpiData.pain_duration) {
        parts.push(`Onset was ${currentHpiData.pain_duration}.`);
    }
    if (currentHpiData.pain_rating) {
        parts.push(`Pain is rated <b>${currentHpiData.pain_rating}/10</b>.`);
    }
    if (currentHpiData.risk_factors) {
        parts.push(`Risk factors include: ${currentHpiData.risk_factors}.`);
    }
    if (currentHpiData.associated_symptoms) {
        parts.push(`Associated symptoms include ${currentHpiData.associated_symptoms}.`);
    }
    // Return HTML format for direct insertion into Quill
    return `<p>${parts.join(' ')}</p>`;
}

/**
 * Generates narrative text for Vitals.
 * @returns {string} HTML narrative.
 */
function getVitalsNarrativeText() {
    const currentVitalsData = window.currentVitalsData; // Get global state

    if (!currentVitalsData || Object.keys(currentVitalsData).length === 0 || !currentVitalsData.vitals_id) {
        return '<p>No Vitals data has been charted for this visit.</p>';
    }

    // Relying on global utility functions (cmToInches, formatValue, etc.) defined in visit_notes.php

    const heightIn = formatValue(cmToInches(currentVitalsData.height_cm), ' in', 1);
    const weightLbs = formatValue(kgToLbs(currentVitalsData.weight_kg), ' lbs', 1);
    const tempF = formatValue(cToF(currentVitalsData.temperature_celsius), ' °F', 1);

    const bmi = formatValue(currentVitalsData.bmi, ' kg/m²', 1);
    const bp = currentVitalsData.blood_pressure || 'N/A';
    const hr = formatValue(currentVitalsData.heart_rate, ' bpm');
    const rr = formatValue(currentVitalsData.respiratory_rate, ' bpm');
    const o2sat = formatValue(currentVitalsData.oxygen_saturation, '%');

    // Combine Anthropometrics
    let anthropometrics = `<b>Anthropometrics:</b> Height: ${heightIn} | Weight: ${weightLbs} | BMI: ${bmi}`;

    // Combine Core Vitals
    let coreVitals = `<b>Core Vitals:</b> BP: ${bp} | HR: ${hr} | RR: ${rr} | Temp: ${tempF} | O2 Sat: ${o2sat}`;

    // Return HTML
    return `<p>${anthropometrics}</p><p>${coreVitals}</p>`;
}

/**
 * Generates structured assessment text from detailed visit assessment data.
 * @returns {string} A formatted HTML string for the Assessment section.
 */
function generateStructuredAssessmentText() {
    const assessments = window.currentVisitAssessments; // Get global state

    if (!assessments || assessments.length === 0) {
        return "<p>No wound assessments were recorded for this visit.</p>";
    }

    let assessmentHtml = "<h3>Wound Assessment Summary:</h3><ul>";

    assessments.forEach((wound, index) => {
        let dims = 'N/A';
        if (wound.length_cm && wound.width_cm && wound.depth_cm) {
            const area = (parseFloat(wound.length_cm) * parseFloat(wound.width_cm)).toFixed(2);
            dims = `${wound.length_cm}cm x ${wound.width_cm}cm x ${wound.depth_cm}cm = ${area} cm²`;
        }

        let tissue = 'N/A';
        let parts = [];
        if (wound.granulation_percent) parts.push(`${wound.granulation_percent}% granulation`);
        if (wound.slough_percent) parts.push(`${wound.slough_percent}% slough`);
        if (parts.length > 0) tissue = parts.join(', ');

        assessmentHtml += `<li><b>Wound #${index + 1}: ${wound.location || 'N/A'} (${wound.wound_type || 'N/A'})</b>`;
        assessmentHtml += `<ul>`;
        assessmentHtml += `<li>Dimensions: ${dims}</li>`;
        assessmentHtml += `<li>Tissue: ${tissue}</li>`;
        assessmentHtml += `<li>Drainage: ${wound.exudate_amount || 'N/A'}, ${wound.drainage_type || 'N/A'}</li>`;
        assessmentHtml += `<li>Odor: ${wound.odor_present || 'N/A'}</li>`;
        assessmentHtml += `<li>Periwound: ${wound.periwound_condition || 'N/A'}</li>`;
        assessmentHtml += `<li>Signs of Infection: ${wound.signs_of_infection || 'N/A'}</li>`;
        assessmentHtml += `</ul></li>`;
    });

    assessmentHtml += "</ul>";
    return assessmentHtml;
}

/**
 * Generates default ROS text (HTML).
 * @returns {string} Formatted ROS text (HTML).
 */
function generateDefaultRosText() {
    return '<p>Review of Systems (ROS) reviewed and incorporated via checklist.</p>';
}

/**
 * Generates default PE text (HTML).
 * @returns {string} Formatted PE text (HTML).
 */
function generateDefaultPeText() {
    return '<p>Physical examination findings incorporated via checklist.</p>';
}

// --- Dynamic Checklist Rendering (Moved from original logic file) ---

/**
 * Fetches and renders the checklist items for a given section.
 * @param {string} section - 'subjective', 'objective', or 'plan'
 */
async function fetchAndRenderSoapChecklist(section) {
    const containerId = `${section}-checklist-container`;
    const container = document.getElementById(containerId);
    if (!container) return;

    // Determine color based on section for loading spinner
    const color = section === 'subjective' ? 'blue' : (section === 'objective' ? 'green' : 'indigo');

    container.innerHTML = `<div class="text-center p-8 text-gray-500">
        <svg class="h-6 w-6 animate-spin inline-block mr-2 text-${color}-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.01M16 16v5h.01M8 12h.01M12 12h.01M16 12h.01M20 12h.01M4 20h.01M8 20h.01M12 20h.01M16 20h.01M20 20h.01M4 16h.01M8 16h.01M12 16h.01M16 16h.01M20 16h.01M4 8h.01M8 8h.01M12 8h.01M16 8h.01M20 8h.01M4 12h.01M8 12h.01M12 12h.01M16 12h.01M20 12h.01"/></svg>
        Loading ${section.toUpperCase()} items...
    </div>`;

    try {
        const cacheBuster = `&_=${new Date().getTime()}`;
        const response = await fetch(`api/get_soap_checklist.php?section=${section}${cacheBuster}`);
        if (!response.ok) throw new Error('Failed to fetch checklist data.');
        const data = await response.json();

        if (data.success) {
            renderSoapChecklist(container, section, data.items);
        } else {
            throw new Error(data.message || 'API call failed.');
        }

    } catch (error) {
        container.innerHTML = `<div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg">Error loading ${section} checklist: ${error.message}</div>`;
        console.error(`Error fetching SOAP checklist for ${section}:`, error);
    }
}

/**
 * Dynamically builds and inserts the checklist HTML into the modal container.
 * @param {HTMLElement} container - The DOM element to insert the checklist into.
 * @param {string} section - 'subjective', 'objective', or 'plan'
 * @param {object} groupedItems - Object where keys are categories and values are array of item_text.
 */
function renderSoapChecklist(container, section, groupedItems) {
    let html = '';
    const inputClass = section === 'subjective' ? 'ros-input h-4 w-4 text-blue-600 mr-2 rounded' :
        (section === 'objective' ? 'pe-input h-4 w-4 text-green-600 mr-2 rounded' : 'plan-input h-4 w-4 text-indigo-600 mr-2 rounded');
    const formName = section === 'subjective' ? 'subjective[]' : (section === 'objective' ? 'objective[]' : 'plan[]');

    if (Object.keys(groupedItems).length === 0) {
        html = '<div class="p-4 text-sm text-gray-700 bg-gray-50 rounded-lg">No active checklist items found for this section.</div>';
    } else {
        for (const category in groupedItems) {
            // Use different background for Plan section to match modal design
            const bgClass = section === 'plan' ? 'bg-indigo-50 p-4 rounded-lg shadow-inner border border-indigo-100' : 'bg-white p-3 rounded-md shadow-sm border border-gray-100';
            const titleClass = section === 'plan' ? 'font-bold text-indigo-800 mb-3 border-b border-indigo-200 pb-1' : 'font-semibold text-gray-800 mb-2 border-b pb-1';
            const labelClass = section === 'plan' ? 'text-indigo-700' : '';

            html += `
                <div class="${bgClass}">
                    <h5 class="${titleClass} flex items-center">
                        ${category}
                    </h5>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm ${labelClass}">
            `;

            groupedItems[category].forEach(itemText => {
                // Escape itemText for HTML attribute safely
                const itemValue = itemText.replace(/"/g, '&quot;');

                html += `
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" name="${formName}" value="${itemValue}" class="${inputClass}">
                        ${itemText}
                    </label>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }
    }
    container.innerHTML = html;
}

// --- Smart Checklist Suggestions (Moved from original logic file) ---

/**
 * Pre-checks items in the Plan modal based on fetched assessment data.
 */
function suggestPlanItems() {
    const checkPlanBox = (value) => {
        const checkbox = document.querySelector(`#planChecklistForm input[value="${value}"]`);
        if (checkbox) checkbox.checked = true;
    };
    const planForm = document.getElementById('planChecklistForm');
    if (planForm) planForm.reset();

    // 1. Apply wound assessment checks
    (window.currentVisitAssessments || []).forEach(wound => {
        const drainage = (wound.drainage_type || '').toLowerCase();
        const signs = (wound.signs_of_infection || '').toLowerCase();
        const sloughPercent = (wound.slough_percent || 0);
        const escharPercent = (wound.eschar_percent || 0);

        // Based on DB `soap_checklist_items` item_id 28 (Consider trial of silver-based dressing for increased bioburden) and common sense:
        if (drainage.includes('purulent') || signs.includes('purulent') || signs.includes('erythema') || signs.includes('warmth')) {
            checkPlanBox('Consider trial of silver-based dressing for increased bioburden'); // from DB
            checkPlanBox('Order wound culture and sensitivity.'); // from DB (item_id 30)
        }

        // Based on DB `soap_checklist_items` item_id 29 (Consider conservative sharp debridement at next visit)
        if (sloughPercent > 10 || escharPercent > 10) {
            checkPlanBox('Consider conservative sharp debridement at next visit.'); // from DB
        }

        // Example check for offloading, if applicable to wound type
        if (wound.wound_type && wound.wound_type.toLowerCase().includes('pressure')) {
            checkPlanBox('Educate on importance of offloading pressure area.'); // from DB (item_id 32)
        }
    });

    // 2. Apply general plan checks (from DB)
    checkPlanBox('Continue current wound care plan.'); // from DB (item_id 26)
}


/**
 * Pre-checks items in the ROS/PE modal based on fetched HPI and Vitals data.
 */
function suggestRosPeItems() {
    const checkBox = (value) => {
        const checkbox = document.querySelector(`input[type="checkbox"][value="${value}"]`);
        if (checkbox) checkbox.checked = true;
    };

    // 1. Clear any previously checked boxes
    document.querySelectorAll('.ros-input, .pe-input').forEach(checkbox => {
        checkbox.checked = false;
    });

    const currentHpiData = window.currentHpiData; // Get global state
    const currentVitalsData = window.currentVitalsData; // Get global state
    const currentVisitAssessments = window.currentVisitAssessments; // Get global state

    // 2. Apply smart checks based on existing data

    // --- Subjective Suggestions (using item text from DB dump as reference) ---
    if (currentHpiData) {
        if (parseInt(currentHpiData.pain_rating) > 5) {
            checkBox("Patient reports increased pain at wound site."); // item_id 6
        }
        if (currentHpiData.problem_status === 'Improving') {
            checkBox("Patient reports improved pain control."); // item_id 5
        }
        if (!currentHpiData.patient_statement || currentHpiData.patient_statement.trim() === '') {
            checkBox("Patient reports no new complaints."); // item_id 1
        }
    }

    // --- Objective Suggestions (using item text from DB dump as reference) ---
    if (currentVitalsData) {
        const tempC = parseFloat(currentVitalsData.temperature_celsius);
        if (tempC > 30) { // Simple check since provided dummy data is very low
            checkBox("Vitals reviewed and are stable."); // item_id 9
        }
    }

    // Apply checks based on Wound Assessments
    let hasPurulent = false;
    let hasSlough = false;
    let hasInfectionSigns = false;

    currentVisitAssessments.forEach(wound => {
        const drainage = (wound.drainage_type || '').toLowerCase();
        const signs = (wound.signs_of_infection || '').toLowerCase();

        if (drainage.includes('purulent')) hasPurulent = true;
        if (signs.includes('purulent')) hasInfectionSigns = true;
        if ((wound.slough_percent || 0) > 0) hasSlough = true;
    });

    if (hasPurulent || hasInfectionSigns) {
        checkBox("Signs of local infection noted."); // item_id 18
    } else {
        checkBox("No signs of local infection (erythema, edema, warmth, purulence) noted."); // item_id 17
        checkBox("No purulent drainage observed."); // item_id 13
    }

    if (hasSlough) {
        checkBox("Wound bed contains slough/fibrin, estimated at X%."); // item_id 11
    }

    checkBox("Periwound skin is intact."); // Default if no specific periwound issue noted
}