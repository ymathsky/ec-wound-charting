// Filename: auto_narrative_hpi.js
// This file reads global data objects (set by visit_hpi.php)
// and generates the first-person CLINICIAN narrative for the HPI page.

// Global variable to hold the most recent AI narrative
window.currentAiNarrative = "";

/**
 * Main function called by visit_hpi.php to refresh the narrative.
 * This function handles the "Static" sections (Vitals, Wounds)
 * while the AI handles the HPI text separately.
 */
window.updateAutoNarrative = function() {
    // Vitals and Wounds sections have been removed.
    // This function is kept to prevent errors in visit_hpi.php calls.
};

/**
 * Helper function to create a standard narrative section.
 */
function createNarrativeSection(title, contentHtml, id = null, contentOnly = false) {
    if (!contentHtml.trim()) {
        contentHtml = '<p>No data recorded for this section.</p>';
    }

    const content = `
        <h3 class="narrative-section-header font-semibold text-gray-800 text-sm border-b pb-1 mb-2">
            <span>${title}</span>
        </h3>
        <div class="narrative-section-content">
            <div class="prose prose-sm max-w-none text-gray-700">
                ${contentHtml}
            </div>
        </div>
    `;

    if (contentOnly) {
        return content;
    }

    const idAttribute = id ? `id="${id}"` : '';

    return `
        <div ${idAttribute} class="narrative-section mb-4">
            ${content}
        </div>
    `;
}

/**
 * Simulates a typewriter effect for the AI response.
 */
function typewriterEffect(element, text, speed = 20, onComplete) {
    let i = 0;
    element.innerHTML = "";

    function type() {
        if (i < text.length) {
            if (text.substring(i, i + 1) === '\n') {
                element.innerHTML += '<br>';
            } else {
                element.innerHTML += text.charAt(i);
            }
            i++;
            setTimeout(type, speed);
        } else {
            if (onComplete) {
                onComplete();
            }
        }
    }
    type();
}

/**
 * CALLS THE AI API.
 * UPDATED: Now grabs Patient Name from DOM to prevent AI hallucination.
 */
async function fetchAiHpiNarrative() {
    let hpiSection = document.getElementById('hpi-narrative-section');

    // Create placeholder if missing
    if (!hpiSection) {
        const narrativeContainer = document.getElementById('auto-narrative-content');
        if (!narrativeContainer) return;

        // Remove initial placeholder if it exists
        const placeholder = document.getElementById('narrative-placeholder');
        if (placeholder) placeholder.remove();

        const placeholderHtml = createNarrativeSection("History of Present Illness (HPI)", "", 'hpi-narrative-section');
        narrativeContainer.insertAdjacentHTML('afterbegin', placeholderHtml);
        hpiSection = document.getElementById('hpi-narrative-section');
    }

    const contentDiv = hpiSection.querySelector('.narrative-section-content');
    if (!contentDiv) return;

    // 1. Show loading spinner
    contentDiv.innerHTML = `
        <div class="flex items-center justify-center p-4 text-sm text-indigo-600">
            <i data-lucide="loader-2" class="w-5 h-5 animate-spin mr-2"></i>
            Generating narrative...
        </div>
    `;
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const saveBtn = document.getElementById('aiSaveBtn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.classList.add('opacity-50', 'cursor-not-allowed');
        saveBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
    }

    try {
        // --- FIX: GATHER PATIENT CONTEXT ---
        // We get the basic form data
        const aiData = window.getAllHpiDataForAI() || {};

        // We grab the patient Name directly from the header element
        const nameHeader = document.getElementById('patient-name-header');
        if (nameHeader) {
            aiData['Patient Name'] = nameHeader.innerText.trim();
        }

        // Grab the selected AI Style
        const styleSelect = document.getElementById('aiStyleSelect');
        if (styleSelect) {
            aiData['style'] = styleSelect.value;
        }

        // Add global IDs for context (if the API is updated to use them)
        if (window.patientId) aiData['patient_id'] = window.patientId;
        if (window.appointmentId) aiData['appointment_id'] = window.appointmentId;
        // -----------------------------------

        // 2. Fetch from API
        const response = await fetch('api/generate_hpi_narrative.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(aiData) // Now includes "Patient Name"
        });

        if (!response.ok) {
            const err = await response.json();
            throw new Error(err.message || "AI service failed to respond.");
        }

        const result = await response.json();

        // 3. Display the AI-generated text
        if (result.success && result.narrative) {
            window.currentAiNarrative = result.narrative;

            const proseDiv = document.createElement('div');
            proseDiv.className = 'prose prose-sm max-w-none text-gray-700';

            const aiHtml = document.createElement('p');
            proseDiv.appendChild(aiHtml);
            contentDiv.innerHTML = '';
            contentDiv.appendChild(proseDiv);

            // Direct assignment instead of typewriter effect
            aiHtml.innerHTML = result.narrative.replace(/\n/g, '<br>');
            
            // Trigger Autosave immediately after generation
            saveAiNarrativeToNote(true);

        } else {
            throw new Error(result.message || "AI returned an empty response.");
        }

    } catch (error) {
        console.error("Error fetching AI HPI:", error);
        contentDiv.innerHTML = `
            <div class="prose prose-sm max-w-none text-red-600 p-2 bg-red-50 rounded-md">
                <p><strong>Error:</strong> ${error.message}</p>
            </div>
        `;
    }
}

// --- Function to save the AI narrative to the database ---
async function saveAiNarrativeToNote(isAutosave = false) {
    const saveBtn = document.getElementById('aiSaveBtn');
    
    // If manual click, check if disabled.
    if (!isAutosave && (!saveBtn || saveBtn.disabled)) return;

    if (!window.currentAiNarrative) {
        if(!isAutosave) console.error("Save clicked, but no AI narrative is available.");
        return;
    }

    const originalContent = `<i data-lucide="save" class="w-4 h-4 mr-1.5"></i>Save`;

    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.classList.remove('opacity-50', 'cursor-not-allowed'); // Ensure it looks active
        
        if (isAutosave) {
             saveBtn.innerHTML = `<i data-lucide="refresh-cw" class="w-4 h-4 mr-1.5 animate-spin"></i>Saving...`;
        } else {
             saveBtn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-1.5 animate-spin"></i>Saving...`;
        }
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    try {
        const response = await fetch('api/save_hpi_narrative_to_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: window.appointmentId,
                patient_id: window.patientId,
                narrative_text: window.currentAiNarrative,
                user_id: window.clinicianId
            })
        });

        const result = await response.json();

        if (response.ok && result.success) {
            if (saveBtn) {
                saveBtn.innerHTML = `<i data-lucide="check" class="w-4 h-4 mr-1.5"></i>Saved`;
                if(typeof lucide !== 'undefined') lucide.createIcons();
                saveBtn.classList.remove('bg-green-600', 'hover:bg-green-700', 'bg-red-600');
                saveBtn.classList.add('bg-green-600');
                
                setTimeout(() => {
                    saveBtn.innerHTML = originalContent;
                    saveBtn.disabled = false;
                    saveBtn.classList.add('hover:bg-green-700');
                    if(typeof lucide !== 'undefined') lucide.createIcons();
                }, 2500);
            }
        } else {
            throw new Error(result.message || "Failed to save.");
        }

    } catch (error) {
        console.error("Error saving HPI narrative:", error);
        if (saveBtn) {
            saveBtn.innerHTML = `<i data-lucide="x" class="w-4 h-4 mr-1.5"></i>Error`;
            if(typeof lucide !== 'undefined') lucide.createIcons();
            saveBtn.classList.remove('bg-green-600');
            saveBtn.classList.add('bg-red-600');

            setTimeout(() => {
                saveBtn.innerHTML = originalContent;
                saveBtn.disabled = false;
                saveBtn.classList.remove('bg-red-600');
                saveBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                if(typeof lucide !== 'undefined') lucide.createIcons();
            }, 3000);
        }
    }
}


// --- INITIALIZATION ---
document.addEventListener('DOMContentLoaded', () => {
    const toggles = document.querySelectorAll('.narrative-toggle-checkbox');
    toggles.forEach(toggle => {
        toggle.addEventListener('change', () => {
            if (typeof window.updateAutoNarrative === 'function') {
                window.updateAutoNarrative();
            }
        });
    });

    const aiBtn = document.getElementById('aiRegenerateBtn');
    if (aiBtn) {
        aiBtn.addEventListener('click', fetchAiHpiNarrative);
    }

    const saveBtn = document.getElementById('aiSaveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', saveAiNarrativeToNote);
    }
});