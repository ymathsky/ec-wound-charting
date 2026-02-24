
/**
 * Creates the HTML for a single collapsible section.
 * @param {string} title - The title to be displayed in the header.
 * @param {string} content - The HTML content of the collapsible section.
 * @returns {string} - The HTML string for the section.
 */
function createCollapsibleSection(title, content) {
    const targetId = `narrative-content-${title.replace(/\s+/g, '-')}`;

    // Check if content implies no data
    const isEmpty = content.toLowerCase().includes('no data has been charted') ||
        content.toLowerCase().includes('no vitals data has been charted') ||
        content.toLowerCase().includes('no active wounds') ||
        content.toLowerCase().includes('not yet integrated');

    // Only set it to auto-open if it is NOT empty
    const autoOpenStyle = !isEmpty ? `max-height: 1000px;` : `max-height: 0;`;
    const chevronStyle = !isEmpty ? `transform: rotate(180deg);` : `transform: rotate(0deg);`;

    return `
        <div class="narrative-section border-b border-gray-200 last:border-b-0 py-3">
            <div class="narrative-section-header flex justify-between items-center cursor-pointer font-semibold text-gray-800 transition duration-150" data-target="${targetId}">
                <span class="text-base">${title}</span>
                <i data-lucide="chevron-down" class="narrative-chevron w-5 h-5 text-blue-600 transition duration-300" style="${chevronStyle}"></i>
            </div>
            <div id="${targetId}" class="narrative-section-content overflow-hidden transition-all duration-300 ease-in-out pt-2" style="${autoOpenStyle}">
                <div class="pl-2 prose prose-sm max-w-none">
                    ${content}
                </div>
            </div>
        </div>
    `;
}

// --- UNIT CONVERSION FUNCTIONS (Metric DB -> Imperial Narrative/Display) ---
const KG_TO_LBS = 2.20462;
const C_TO_F_MULTIPLIER = 9 / 5;
const C_TO_F_OFFSET = 32;
const CM_TO_INCH_FACTOR = 0.393701;

function cmToInches(cm) {
    return cm ? (parseFloat(cm) * CM_TO_INCH_FACTOR) : null;
}
function kgToLbs(kg) {
    return kg ? (parseFloat(kg) * KG_TO_LBS) : null;
}
function cToF(c) {
    return c ? (parseFloat(c) * C_TO_F_MULTIPLIER) + C_TO_F_OFFSET : null;
}

// Helper to format values or return '-'
function formatValue(value, unit = '', precision = 0) {
    if (value === null || value === undefined || isNaN(value) || value === '') {
        return '-';
    }
    return `${parseFloat(value).toFixed(precision)}${unit}`;
}


// ====================================================================
// === CORE NARRATIVE GENERATION FUNCTIONS (GLOBAL) =================
// ====================================================================

/**
 * Generates the narrative sentence for Vitals.
 * @param {object} vitalsData - The vital signs data (in Metric: cm, kg, C).
 * @returns {string} HTML content for the Vitals section.
 */
function generateVitalsNarrativeSentence(vitalsData) {
    // Use global window data
    const data = window.currentVitalsData || {};

    if (Object.keys(data).length === 0) {
        return '<p class="text-gray-500">No Vitals data has been charted for this visit.</p>';
    }

    // Convert Metric data to Imperial for the narrative
    const heightIn = formatValue(cmToInches(data.height_cm), ' in', 1);
    const weightLbs = formatValue(kgToLbs(data.weight_kg), ' lbs', 1);
    const tempF = formatValue(cToF(data.temperature_celsius), ' °F', 1);
    const hr = formatValue(data.heart_rate, ' bpm');
    const rr = formatValue(data.respiratory_rate);
    const o2sat = formatValue(data.oxygen_saturation, '%');
    const bp = data.blood_pressure || '-';
    const bmi = data.bmi || '-';

    const narrative = `Patient presents today with recorded vital signs: Height ${heightIn}, Weight ${weightLbs}, **BMI ${bmi}**. Blood Pressure is **${bp}**, Heart Rate is ${hr}, Respiratory Rate is ${rr}, Temperature is ${tempF}, and Oxygen Saturation is ${o2sat}. Vitals are generally stable and within acceptable limits for the patient's condition.`;

    return narrative;
}

/**
 * Generates the narrative sentence for HPI (History of Present Illness).
 * @param {object} hpiData - The HPI data object (expected to be CSV strings for checkboxes).
 * @returns {string} HTML content for the HPI section.
 */
function generateHpiNarrativeSentence(hpiData) {
    // Use global window data
    const data = window.currentHpiData || {};

    if (Object.keys(data).length === 0 || data.problem_status === undefined) {
        return '<p class="text-gray-500">No HPI data has been charted for this visit.</p>';
    }

    // Extract core variables
    const status = data.problem_status || 'not specified';
    const painRating = data.pain_rating || '0';
    const duration = data.pain_duration || 'unknown duration';
    const impact = data.functional_capacity ? `and functional capacity is **${data.functional_capacity.toLowerCase()}**` : '';
    const infection = data.signs_of_infection === 'Yes' ? 'with **signs of infection noted**' : 'with no signs of infection reported';

    // Build narrative segments
    let painText = `Pain is rated at **${painRating}/10** (for ${duration}).`;
    if (painRating === '0') {
        painText = 'Patient denies pain today.';
    }

    const interventions = data.interventions ? `Interventions required include: ${data.interventions}.` : 'No recent interventions are documented.';

    const narrative = `The patient's current problem status is **${status.toUpperCase()}** ${impact}. ${painText} The assessment today confirms the situation ${infection}. ${interventions}`;

    return narrative;
}

/**
 * Generates the narrative sentence for Wounds.
 * @param {array} woundsData - The wounds data array.
 * @returns {string} HTML content for the Wound section.
 */
function generateWoundNarrativeSentence(woundsData) {
    // Use global window data
    const data = window.globalWoundsData || [];

    if (data.length === 0) {
        return '<p class="text-gray-500">No active wounds found for this patient.</p>';
    }

    const woundCount = data.length;
    const activeWounds = data.filter(w => w.status !== 'Healed').length;

    const narrative = `A total of ${woundCount} wounds are associated with this patient, with ${activeWounds} currently requiring assessment. Wounds are documented in the Wound Assessment section.`;

    return narrative;
}

/**
 * Generates the narrative sentence for Notes (Placeholder).
 */
function generateNotesNarrativeSentence() {
    return '<p class="text-gray-500">Notes section not yet integrated for narrative generation.</p>';
}

/**
 * Generates the narrative sentence for Graft (Placeholder).
 */
function generateGraftNarrativeSentence() {
    return '<p class="text-gray-500">Graft details not yet available for narrative generation.</p>';
}


// --- MAIN NARRATIVE BUILDER (GLOBAL) ---
/**
 * Main function to assemble all narrative sections based on toggles and data availability.
 */
function updateAutoNarrative() {
    const contentDiv = document.getElementById('auto-narrative-content');
    if (!contentDiv) return;

    let narrativeHtml = '';
    const sections = [];

    // --- 1. Get Narratives ---
    if (document.getElementById('toggle-vitals')?.checked) {
        const content = generateVitalsNarrativeSentence(window.currentVitalsData);
        sections.push({ title: 'Vitals Assessment', content: content });
    }

    if (document.getElementById('toggle-hpi')?.checked) {
        const content = generateHpiNarrativeSentence(window.currentHpiData);
        sections.push({ title: 'HPI Summary', content: content });
    }

    if (document.getElementById('toggle-wound')?.checked) {
        const content = generateWoundNarrativeSentence(window.globalWoundsData);
        sections.push({ title: 'Wound Overview', content: content });
    }

    if (document.getElementById('toggle-notes')?.checked) {
        const content = generateNotesNarrativeSentence();
        sections.push({ title: 'Visit Notes', content: content });
    }

    if (document.getElementById('toggle-graft')?.checked) {
        const content = generateGraftNarrativeSentence();
        sections.push({ title: 'Graft Details', content: content });
    }

    // --- 2. Assemble Collapsible Sections ---
    sections.forEach(section => {
        narrativeHtml += createCollapsibleSection(section.title, section.content);
    });


    // --- 3. Final Render ---
    if (narrativeHtml.trim() === '') {
        narrativeHtml = '<p class="text-gray-500 text-center py-6">Select sections above to generate narrative.</p>';
    }

    contentDiv.innerHTML = narrativeHtml;

    // FIX: Re-render lucide icons immediately after content injection
    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
}


// --- INITIALIZATION ---

/**
 * Sets up event listeners for toggles and click functionality.
 */


function initializeNarrativeGenerator() {
    // 1. Attach listeners for narrative toggles
    document.querySelectorAll('.narrative-toggle-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateAutoNarrative);
    });

    // 2. Add a single click listener to the narrative container for toggling sections
    const narrativeContainer = document.getElementById('auto-narrative-content');
    if (narrativeContainer) {
        narrativeContainer.addEventListener('click', (e) => {
            const header = e.target.closest('.narrative-section-header');
            if (header) {
                const targetId = header.dataset.target;
                const content = document.getElementById(targetId);
                const chevron = header.querySelector('.narrative-chevron');

                if (content && chevron) {
                    const isHidden = content.style.maxHeight === '0px' || content.style.maxHeight === '';
                    // Set scrollHeight dynamically for smooth transition
                    const newMaxHeight = isHidden ? `${content.scrollHeight + 50}px` : '0px';
                    const newTransform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';

                    content.style.maxHeight = newMaxHeight;
                    chevron.style.transform = newTransform;
                }
            }
        });
    }

    // Self-initialize only if the DOM is ready, deferring to the Vitals page to call updateAutoNarrative after data fetch
}

// Self-initialize when the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Use a small delay to ensure that global data variables (set in the type="module" script)
    // are available before running the initial narrative generation.
    setTimeout(() => {
        initializeNarrativeGenerator();
        // Initial run is still handled by fetchInitialData in the main .php file
    }, 200);
});