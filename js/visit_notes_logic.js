// Filename: ec/js/visit_notes_logic.js
// Purpose: Handles main page logic, tabs, autosave, Quick Insert, and Cloning.
// UPDATED: Added styled titles (e.g., "ENCOUNTER DIAGNOSES") with icons to all auto-populated data sections.

console.log("Visit Notes Logic Loaded (Tabs + Checklist + Enhanced UI + Titles + Report Preview + Detailed WNL + Fixed Clone + Safe JSON)");
if(window.phpVars) console.log("Visit Notes PHP Vars:", window.phpVars);

// --- GLOBALS ---
window.fetchedChecklist = window.fetchedChecklist || { subjective: false, objective: false, assessment: false, plan: false };
window.globalDataBundle = window.globalDataBundle || {};
window.checklistSelections = window.checklistSelections || {};
window.checklistData = window.checklistData || {};
window.checklistShowAll = window.checklistShowAll || {};
window.quillEditors = window.quillEditors || {};

let activeChecklistCategory = null;
let autosaveDebounceTimer = null;
let isAutosaving = false;
let floatingAlertElement = null;
window.activeChecklistSection = 'chief_complaint';

// --- LOCAL STORAGE KEYS ---
const BACKUP_KEY = `ec_note_backup_${window.phpVars.appointmentId}`;
const BACKUP_META_KEY = `ec_note_backup_meta_${window.phpVars.appointmentId}`;

// --- WNL TEXT TEMPLATES ---
const WNL_TEMPLATES = {
    subjective: `
    <p><strong>Constitutional:</strong> Good appetite.</p>
    <p><strong>HEENT:</strong> No eye pain and discharge. No nasal congestion, no ear pain and discharge</p>
    <p><strong>Allergy/immune:</strong> No known allergies</p>
    <p><strong>Cardio/Vascular:</strong> No report of chest pain and palpitation</p>
    <p><strong>Respiratory:</strong> (+) History of smoking, no shortness of breath and no cough</p>
    <p><strong>Endocrine:</strong> denies excess thirst</p>
    <p><strong>GI:</strong> Denies abdominal or stomach discomfort. Occasional constipation.</p>
    <p><strong>GU:</strong> Functional urinary incontinence.</p>
    <p><strong>Musculoskeletal:</strong> Limited range of motion and confined to bed.</p>
    <p><strong>Neuro:</strong> No headache and no report of loss consciousness</p>
    <p><strong>Psych:</strong> No behavioral changes, good sleep, and no suicidal ideation</p>
    <p><strong>Skin:</strong> </p>
    `,
    objective: `
    <p><strong>General/constitutional:</strong> well nourished, alert and not distress</p>
    <p><strong>Eyes:</strong> Pupils are equally round and reactive to light.</p>
    <p><strong>ENT/Mouth:</strong> No ear discharge, no nasal congestion, no tonsillar swelling.</p>
    <p><strong>Cardio/vascular:</strong> Normal heart rate and no gallops or murmurs</p>
    <p><strong>Respiratory:</strong> normal breathing, no crackles or</p>
    <p><strong>Lymph:</strong> Normal on both axilla, groin and neck</p>
    <p><strong>Psych:</strong> alert, oriented and no behavioral changes</p>
    <p><strong>GI:</strong> soft abdomen, and non tender abdomen.</p>
    <p><strong>Skin & Subcutaneous Tissue:</strong> </p>
    `
};

// --- MACRO DEFINITIONS ---
const TEXT_MACROS = {
    ".normal": "Patient is alert, oriented x3, and in no acute distress.",
    ".pain": "Patient reports pain is well-controlled with current regimen.",
    ".drainage": "Moderate serosanguinous drainage noted.",
    ".inf": "No signs of infection (erythema, induration, warmth, purulence) observed.",
    ".plan": "Continue current wound care protocol. Monitor for signs of infection."
};

// --- LAZY QUILL INITIALIZATION ---
const initializedTabs = new Set();

function initQuillForSection(section) {
    if (initializedTabs.has(section)) return;

    const containerId = `${section}-editor-container`;
    const container = document.getElementById(containerId);

    if (!container) return;

    if (container.classList.contains('ql-container')) {
        initializedTabs.add(section);
        return;
    }

    console.log(`Initializing Quill for: ${section}`);

    const toolbarOptions = {
        container: [
            ['bold', 'italic', 'underline'],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            ['link'],
            ['undo', 'redo'],
            ['voice'],
            ['ai-magic'],
            ['save-template', 'load-template']
        ],
        handlers: {
            'undo': function() {
                this.quill.history.undo();
            },
            'redo': function() {
                this.quill.history.redo();
            },
            'voice': function() {
                if (window.handleVoiceDictation) {
                    window.handleVoiceDictation.call(this);
                }
            },
            'ai-magic': function() {
                const btn = this.container.querySelector('.ql-ai-magic');
                rewriteWithAI(this.quill, btn);
            },
            'save-template': function() {
                openSaveTemplateModal(this.quill, section);
            },
            'load-template': function() {
                openLoadTemplateModal(this.quill, section);
            }
        }
    };

    try {
        const q = new Quill(`#${containerId}`, {
            theme: 'snow',
            modules: { 
                toolbar: toolbarOptions,
                history: {
                    delay: 2000,
                    maxStack: 500,
                    userOnly: true
                }
            }
        });

        // Inject Icons
        const toolbar = q.getModule('toolbar').container;
        
        // Undo
        const undoBtn = toolbar.querySelector('.ql-undo');
        if (undoBtn) {
            undoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>';
            undoBtn.title = "Undo (Ctrl+Z)";
        }

        // Redo
        const redoBtn = toolbar.querySelector('.ql-redo');
        if (redoBtn) {
            redoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 7v6h-6"/><path d="M3 17a9 9 0 0 1 9-9 9 9 0 0 1 6 2.3l3 2.7"/></svg>';
            redoBtn.title = "Redo (Ctrl+Y)";
        }

        // AI Magic
        const aiBtn = toolbar.querySelector('.ql-ai-magic');
        if (aiBtn) {
            aiBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="indigo" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4"/><path d="M9 3v4"/><path d="M3 5h4"/><path d="M3 9h4"/></svg>';
            aiBtn.title = "Make Professional (AI Rewrite)";
            aiBtn.style.width = "24px";
        }

        // Save Template
        const saveTplBtn = toolbar.querySelector('.ql-save-template');
        if (saveTplBtn) {
            saveTplBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>';
            saveTplBtn.title = "Save as Template";
            saveTplBtn.style.width = "24px";
        }

        // Load Template
        const loadTplBtn = toolbar.querySelector('.ql-load-template');
        if (loadTplBtn) {
            loadTplBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>';
            loadTplBtn.title = "Load Template";
            loadTplBtn.style.width = "24px";
        }

        window.quillEditors[section] = q;
        initializedTabs.add(section);

        // Init Macros
        initMacroExpander(q);

        // Check if note is finalized and disable if so
        if (window.isNoteFinalized) {
            q.disable();
        }

        q.on('text-change', () => {
            updateNoteCompletionChecklist();
            saveToLocalBackup(section); // Backup to LocalStorage instantly
            if (autosaveDebounceTimer) clearTimeout(autosaveDebounceTimer);
            autosaveDebounceTimer = setTimeout(triggerAutosave, 3000);
        });

        const hiddenInput = document.getElementById(`${section}_input`) || document.getElementById(section);
        if (hiddenInput && hiddenInput.value && q.getText().trim().length === 0) {
            q.clipboard.dangerouslyPasteHTML(0, hiddenInput.value, 'api');
        }

    } catch (e) {
        console.error(`Failed to init Quill for ${section}`, e);
    }
}

// --- LOCAL STORAGE BACKUP ---
function saveToLocalBackup(activeSection) {
    const data = {};
    ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'].forEach(key => {
        if (window.quillEditors[key]) {
            data[key] = window.quillEditors[key].root.innerHTML;
        } else {
            const el = document.getElementById(`${key}_input`) || document.getElementById(key);
            if(el) data[key] = el.value;
        }
    });

    localStorage.setItem(BACKUP_KEY, JSON.stringify(data));
    localStorage.setItem(BACKUP_META_KEY, Date.now());
}

function checkLocalBackup() {
    const backup = localStorage.getItem(BACKUP_KEY);
    const meta = localStorage.getItem(BACKUP_META_KEY);

    if (!backup || !meta) return;

    const backupTime = parseInt(meta, 10);
    const timeStr = new Date(backupTime).toLocaleTimeString();

    const msg = document.getElementById('note-message');
    msg.innerHTML = `
        <div class="flex justify-between items-center">
            <span><i data-lucide="alert-circle" class="inline w-4 h-4 mr-1"></i> An unsaved backup from <strong>${timeStr}</strong> was found on this device.</span>
            <button id="restoreBackupBtn" class="bg-white text-blue-600 border border-blue-200 text-xs font-bold py-1 px-3 rounded hover:bg-blue-50 ml-3">Restore Backup</button>
        </div>
    `;
    msg.classList.remove('hidden', 'bg-red-100', 'text-red-800');
    msg.classList.add('bg-blue-100', 'text-blue-800', 'border', 'border-blue-200');

    document.getElementById('restoreBackupBtn').addEventListener('click', (e) => {
        e.preventDefault();
        const data = JSON.parse(backup);

        ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'].forEach(key => {
            if (data[key]) {
                initQuillForSection(key);
                // Use setFieldContent to completely overwrite existing content
                setFieldContent(key, data[key]);
            }
        });
        showFloatingAlert('Backup restored successfully!', 'success');
        msg.classList.add('hidden');
        triggerAutosave();
    });
}

// --- MACRO EXPANDER ---
function initMacroExpander(quill) {
    quill.on('text-change', function(delta, oldDelta, source) {
        if (source !== 'user') return;

        const sel = quill.getSelection();
        if (!sel) return;

        const cursorIndex = sel.index;
        const lookBack = Math.max(0, cursorIndex - 15);
        const textBefore = quill.getText(lookBack, cursorIndex - lookBack);

        const lastChar = textBefore.slice(-1);
        if (!/\s/.test(lastChar)) return;

        const words = textBefore.trim().split(/\s+/);
        const lastWord = words[words.length - 1];

        if (TEXT_MACROS[lastWord]) {
            const expansion = TEXT_MACROS[lastWord];
            const macroLength = lastWord.length;
            const startDelete = cursorIndex - macroLength - 1;

            const checkText = quill.getText(startDelete, macroLength);
            if (checkText === lastWord) {
                quill.deleteText(startDelete, macroLength + 1);
                quill.insertText(startDelete, expansion + ' ');
            }
        }
    });
}

window.insertWNL = function(section) {
    const content = WNL_TEMPLATES[section];
    if (!content) return;

    const editor = window.quillEditors[section];
    if (editor) {
        const len = editor.getLength();
        editor.clipboard.dangerouslyPasteHTML(len - 1, content, 'user');
        showFloatingAlert('Normal findings inserted.', 'success');
    }
};

// --- CLONE LAST VISIT (ROBUST) ---
let previousNoteState = null;

function captureNoteState() {
    const state = {};
    ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'].forEach(key => {
        if (window.quillEditors[key]) {
            state[key] = window.quillEditors[key].root.innerHTML;
        } else {
            const el = document.getElementById(`${key}_input`) || document.getElementById(key);
            if(el) state[key] = el.value;
        }
    });
    return state;
}

function showUndoToast() {
    let toast = document.getElementById('undo-note-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'undo-note-toast';
        toast.className = 'fixed bottom-24 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white px-6 py-3 rounded-lg shadow-xl flex items-center gap-4 z-50 hidden transition-all duration-300';
        toast.innerHTML = `
            <span><i data-lucide="info" class="inline w-4 h-4 mr-2 text-blue-400"></i> Note cloned from previous visit.</span>
            <button id="undoNoteCloneBtn" class="bg-gray-700 hover:bg-gray-600 text-white text-sm font-bold py-1 px-3 rounded border border-gray-600 transition">Undo</button>
            <button id="dismissNoteUndoBtn" class="text-gray-400 hover:text-white"><i data-lucide="x" class="w-4 h-4"></i></button>
        `;
        document.body.appendChild(toast);
        
        // Add listeners
        document.getElementById('undoNoteCloneBtn').addEventListener('click', () => {
            if (previousNoteState) {
                ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'].forEach(key => {
                    if (previousNoteState[key] !== undefined) {
                        initQuillForSection(key);
                        setFieldContent(key, previousNoteState[key]);
                    }
                });
                showFloatingAlert('Restored previous note state.', 'success');
                toast.classList.add('hidden');
                toast.classList.remove('flex');
                triggerAutosave();
            }
        });
        
        document.getElementById('dismissNoteUndoBtn').addEventListener('click', () => {
            toast.classList.add('hidden');
            toast.classList.remove('flex');
        });
        
        if(window.lucide) window.lucide.createIcons({ nodes: [toast] });
    }
    
    toast.classList.remove('hidden');
    toast.classList.add('flex');
    setTimeout(() => {
        toast.classList.add('hidden');
        toast.classList.remove('flex');
    }, 10000);
}

window.cloneLastVisit = async function() {
    const { patientId, appointmentId } = window.phpVars;
    const btn = document.getElementById('cloneLastVisitBtn');
    if(btn) btn.disabled = true;

    showFloatingAlert('Fetching previous visit data...', 'info');

    try {
        // Capture state before overwriting
        previousNoteState = captureNoteState();

        const response = await fetch(`api/get_last_visit_note.php?patient_id=${patientId}&current_appointment_id=${appointmentId}`, {
            credentials: 'include'
        });

        // Read raw text first to detect HTML errors
        const text = await response.text();
        let json;
        try {
            json = JSON.parse(text);
        } catch (err) {
            console.error("Clone JSON Parse Error. Raw Response:", text);
            throw new Error("Server returned invalid data. See console.");
        }

        if (json.success && json.data) {
            const note = json.data;
            let clonedCount = 0;

            const populate = (field, content) => {
                if (!content || content === '<p><br></p>' || content.trim() === '') return;
                initQuillForSection(field);
                const editor = window.quillEditors[field];
                if (editor) {
                    const currentLen = editor.getLength();
                    if (currentLen <= 1) {
                        editor.clipboard.dangerouslyPasteHTML(0, content, 'api');
                    } else {
                        const dateStr = json.data.appointment_date || 'Previous';
                        editor.insertText(currentLen - 1, `\n\n--- PREVIOUS VISIT (${dateStr}) ---\n`, { 'bold': true, 'color': '#6b7280' });
                        const newLen = editor.getLength();
                        editor.clipboard.dangerouslyPasteHTML(newLen - 1, content, 'api');
                    }
                    clonedCount++;
                }
            };

            populate('chief_complaint', note.chief_complaint);
            populate('subjective', note.subjective);
            populate('objective', note.objective);
            populate('assessment', note.assessment);
            populate('plan', note.plan);
            populate('lab_orders', note.lab_orders);
            populate('imaging_orders', note.imaging_orders);
            populate('skilled_nurse_orders', note.skilled_nurse_orders);

            if (clonedCount > 0) {
                showFloatingAlert(`Data cloned from ${json.data.appointment_date}`, 'success');
                triggerAutosave();
                showUndoToast();
            } else {
                showFloatingAlert('Previous visit found, but notes were empty.', 'warning');
            }
        } else {
            showFloatingAlert(json.message || 'No previous visit found.', 'warning');
        }
    } catch (e) {
        console.error("Clone error:", e);
        showFloatingAlert('Failed to clone visit.', 'error');
    } finally {
        if(btn) btn.disabled = false;
    }
};


// --- TAB SWITCHING LOGIC ---
window.switchTab = function(tabName) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active', 'bg-gray-50'));

    const content = document.getElementById(`tab-content-${tabName}`);
    if (content) content.classList.remove('hidden');

    const btn = document.getElementById(`tab-btn-${tabName}`);
    if (btn) btn.classList.add('active');

    if (tabName !== 'finalize') {
        window.activeChecklistSection = tabName;
        if (tabName === 'orders') {
            setTimeout(() => {
                initQuillForSection('lab_orders');
                initQuillForSection('imaging_orders');
                initQuillForSection('skilled_nurse_orders');
            }, 50);
        } else {
            setTimeout(() => initQuillForSection(tabName), 50);
        }
    }
};

// --- HELPER FUNCTIONS ---
function calculateAge(dobString) {
    if (!dobString) return 'N/A';
    try {
        const birthDate = new Date(dobString);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) age--;
        return age;
    } catch (e) { return 'N/A'; }
}

function cleanHtmlContent(html) {
    if (!html) return '';
    let clean = html.trim();
    
    // Remove whitespace between tags to prevent layout gaps
    clean = clean.replace(/>\s+</g, '><');
    
    // Aggressively remove leading empty paragraphs (<p><br></p>, <p>&nbsp;</p>, <p> </p>)
    // We use a loop to ensure we strip multiple empty lines at the start
    while (/^(<p>(\s*<br\s*\/?>\s*|&nbsp;|\s+)*<\/p>\s*)/i.test(clean)) {
        clean = clean.replace(/^(<p>(\s*<br\s*\/?>\s*|&nbsp;|\s+)*<\/p>\s*)/i, '');
    }
    
    // Also remove leading <br> tags not in p
    while (/^(<br\s*\/?>\s*)/i.test(clean)) {
        clean = clean.replace(/^(<br\s*\/?>\s*)/i, '');
    }

    // Aggressively remove trailing empty paragraphs
    while (/(<p>(\s*<br\s*\/?>\s*|&nbsp;|\s+)*<\/p>\s*)$/i.test(clean)) {
        clean = clean.replace(/(<p>(\s*<br\s*\/?>\s*|&nbsp;|\s+)*<\/p>\s*)$/i, '');
    }

    return clean;
}

// --- ENHANCED UI FORMATTERS WITH TITLES ---

function formatVitalsHTML(vitals) {
    const titleHtml = `<div class="mb-3 border-b border-green-100 pb-2"><span class="text-xs font-bold text-green-800 uppercase tracking-wide flex items-center"><i data-lucide="activity" class="w-4 h-4 mr-2"></i> Vitals Summary</span></div>`;

    if (!vitals) return `${titleHtml}<p class="text-gray-400 italic text-sm">No vitals recorded.</p>`;

    const temp_f = vitals.temperature_celsius ? (parseFloat(vitals.temperature_celsius) * 9/5) + 32 : null;
    const temp_display = temp_f ? `${temp_f.toFixed(1)}°F` : '--';

    const vCard = (label, val, unit) => `
        <div class="bg-white p-2 rounded border border-green-100 shadow-sm text-center flex flex-col justify-center h-full">
            <span class="text-[10px] text-gray-400 uppercase font-bold tracking-wider block mb-1">${label}</span>
            <div class="text-green-800 font-bold text-lg leading-none mb-1">${val || '--'}</div>
            <span class="text-[10px] text-gray-400">${unit}</span>
        </div>
    `;

    return `
        ${titleHtml}
        <div class="grid grid-cols-4 gap-2">
            ${vCard('BP', vitals.blood_pressure, 'mmHg')}
            ${vCard('Pulse', vitals.heart_rate, 'bpm')}
            ${vCard('Resp', vitals.respiratory_rate, 'rpm')}
            ${vCard('O2 Sat', vitals.oxygen_saturation, '%')}
            ${vCard('Temp', temp_display, '')}
            ${vCard('BMI', vitals.bmi, 'kg/m²')}
            ${vCard('Height', vitals.height_cm, 'cm')}
            ${vCard('Weight', vitals.weight_kg, 'kg')}
        </div>
    `;
}

function formatDiagnosesHTML(diagnoses) {
    const titleHtml = `<div class="mb-3 border-b border-orange-100 pb-2"><span class="text-xs font-bold text-orange-800 uppercase tracking-wide flex items-center"><i data-lucide="stethoscope" class="w-4 h-4 mr-2"></i> Encounter Diagnoses</span></div>`;

    if (!diagnoses || diagnoses.length === 0) return `${titleHtml}<p class="text-gray-400 italic text-sm">No diagnoses recorded.</p>`;

    let html = `${titleHtml}<div class="space-y-2">`;
    diagnoses.forEach(d => {
        const isPri = d.is_primary == 1;
        html += `
            <div class="flex items-start p-2 bg-white rounded border border-orange-100 shadow-sm">
                <div class="flex-shrink-0 mr-3 mt-0.5">
                    <span class="inline-block bg-orange-100 text-orange-700 text-xs font-bold px-2 py-1 rounded border border-orange-200">
                        ${d.icd10_code}
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="text-sm font-medium text-gray-800 leading-snug">
                        ${d.description}
                        ${isPri ? '<span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-600 text-white">PRIMARY</span>' : ''}
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function formatWoundsHTML(wounds) {
    const titleHtml = `<div class="mb-3 border-b border-orange-100 pb-2"><span class="text-xs font-bold text-orange-800 uppercase tracking-wide flex items-center"><i data-lucide="target" class="w-4 h-4 mr-2"></i> Wound Assessments</span></div>`;

    if (!wounds || wounds.length === 0) return `${titleHtml}<p class="text-gray-400 italic text-sm">No wound assessments recorded.</p>`;

    let html = `${titleHtml}<div class="space-y-3">`;
    wounds.forEach((asm, index) => {
        console.log("Wound Assessment:", asm);
        let debridementHtml = '';
        if (asm.debridement_performed === 'Yes') {
            // Determine debridement label based on type
            let debridementLabel = 'Surgical debridement';
            if (asm.debridement_type) {
                const typeLower = asm.debridement_type.toLowerCase().trim();
                console.log("Debridement Type (Lower):", typeLower);
                if (typeLower.includes('mechanical')) debridementLabel = 'Mechanical debridement';
                else if (typeLower.includes('autolytic')) debridementLabel = 'Autolytic debridement';
                else if (typeLower.includes('enzymatic')) debridementLabel = 'Enzymatic debridement';
                else if (typeLower.includes('biological')) debridementLabel = 'Biological debridement';
                else if (typeLower.includes('maggot')) debridementLabel = 'Biological debridement';
                else if (typeLower.includes('sharp')) debridementLabel = 'Sharp debridement';
            }

            // Use stored narrative if available, otherwise generate default
            const defaultNarrative = `Discussed with patient the procedure today. The purpose of this ${debridementLabel.toLowerCase()} is to remove dead or necrotic tissue and biofilm. A ${asm.wound_type} was noted on the ${asm.location}. ${debridementLabel} of necrotic tissue was performed using ${asm.debridement_type}. Patient tolerated the procedure well. Hemostasis was achieved.`;
            
            const currentNarrative = asm.debridement_narrative && asm.debridement_narrative.trim() !== '' ? asm.debridement_narrative : defaultNarrative;

            debridementHtml = `
                <div class="mt-2 pt-2 border-t border-orange-100 group relative">
                    <div class="flex justify-between items-center mb-1">
                        <p class="text-xs font-bold text-orange-800 uppercase">${debridementLabel} to ${asm.location}:</p>
                        <button class="edit-debridement-btn text-xs bg-white border border-orange-200 text-orange-600 hover:bg-orange-50 px-2 py-0.5 rounded shadow-sm flex items-center transition-colors opacity-0 group-hover:opacity-100" data-id="${asm.assessment_id}">
                            <i data-lucide="edit-2" class="w-3 h-3 mr-1"></i> Edit
                        </button>
                    </div>
                    
                    <div id="debridement-display-${asm.assessment_id}" class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">${currentNarrative}</div>
                    
                    <div id="debridement-edit-${asm.assessment_id}" class="hidden mt-2">
                        <textarea id="debridement-textarea-${asm.assessment_id}" class="w-full p-2 border border-orange-300 rounded text-sm focus:ring-2 focus:ring-orange-500 focus:border-transparent bg-white" rows="4">${currentNarrative}</textarea>
                        <div class="flex justify-end gap-2 mt-2">
                            <button class="cancel-debridement-btn text-xs bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1 rounded transition-colors" data-id="${asm.assessment_id}">Cancel</button>
                            <button class="save-debridement-btn text-xs bg-orange-600 text-white hover:bg-orange-700 px-3 py-1 rounded shadow-sm flex items-center transition-colors" data-id="${asm.assessment_id}">
                                <i data-lucide="save" class="w-3 h-3 mr-1"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        html += `
            <div class="bg-white rounded border border-orange-200 p-3 shadow-sm">
                <div class="flex justify-between items-center mb-1 border-b border-orange-100 pb-1">
                    <span class="font-bold text-xs text-orange-800 uppercase">Wound #${index + 1}</span>
                    <span class="text-xs text-gray-500 font-medium">${asm.location}</span>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed mt-1">
                    ${generateWoundSummarySentence(asm)}
                </p>
                ${debridementHtml}
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function formatProceduresHTML(procedures) {
    const titleHtml = `<div class="mb-3 border-b border-indigo-100 pb-2"><span class="text-xs font-bold text-indigo-800 uppercase tracking-wide flex items-center"><i data-lucide="zap" class="w-4 h-4 mr-2"></i> Procedures Performed</span></div>`;

    if (!procedures || procedures.length === 0) return `${titleHtml}<p class="text-gray-400 italic text-sm">No procedures recorded.</p>`;

    let html = `${titleHtml}<div class="space-y-2">`;
    procedures.forEach(p => {
        html += `
            <div class="flex items-center p-2 bg-white rounded border border-indigo-100 shadow-sm">
                <span class="flex-shrink-0 bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-mono font-bold px-2 py-1 rounded mr-3">
                    ${p.cpt_code}
                </span>
                <div class="flex-grow min-w-0">
                    <p class="text-sm font-medium text-gray-800 truncate">${p.description}</p>
                    <p class="text-xs text-gray-500">Units: ${p.units}</p>
                </div>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function formatMedicationsHTML(meds) {
    const titleHtml = `<div class="mb-3 border-b border-indigo-100 pb-2"><span class="text-xs font-bold text-indigo-800 uppercase tracking-wide flex items-center"><i data-lucide="pill" class="w-4 h-4 mr-2"></i> Active Medications</span></div>`;

    if (!meds || meds.length === 0) return `${titleHtml}<p class="text-gray-400 italic text-sm">No active medications.</p>`;

    let html = `${titleHtml}<div class="grid grid-cols-1 md:grid-cols-2 gap-2">`;
    meds.forEach(m => {
        html += `
            <div class="flex items-start p-2 bg-white rounded border border-indigo-100 shadow-sm">
                <div class="mr-3 mt-1 text-indigo-400">
                    <i data-lucide="pill" class="w-4 h-4"></i>
                </div>
                <div>
                    <div class="text-sm font-bold text-gray-800">${m.drug_name}</div>
                    <div class="text-xs text-gray-500">${m.dosage} • ${m.frequency}</div>
                    ${m.start_date ? `<div class="text-xs text-gray-400 mt-0.5">Start: ${m.start_date}${m.end_date ? ' • End: ' + m.end_date : ''}</div>` : ''}
                </div>
            </div>
        `;
    });
    html += '</div>';
    return html;
}

function formatWoundPlansHTML(wounds) {
    const titleHtml = `<div class="mb-3 border-b border-indigo-100 pb-2"><span class="text-xs font-bold text-indigo-800 uppercase tracking-wide flex items-center"><i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i> Wound Treatment Plans</span></div>`;

    if (!wounds || wounds.length === 0) return `${titleHtml}<p class="text-gray-400 italic text-sm">No specific wound plans.</p>`;

    let html = `${titleHtml}<div class="space-y-3">`;
    let hasPlans = false;
    wounds.forEach((asm, index) => {
        const planText = asm.treatments_provided || asm.clinician_plan;
        if (planText && planText.trim() !== "") {
            html += `
                <div class="bg-indigo-50 rounded border border-indigo-200 p-3">
                    <h5 class="text-xs font-bold text-indigo-800 uppercase mb-1">Plan for ${asm.location}</h5>
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">${planText}</p>
                </div>
            `;
            hasPlans = true;
        }
    });
    html += '</div>';

    if (!hasPlans) return `${titleHtml}<p class="text-gray-400 italic text-sm">No specific wound plans recorded.</p>`;
    return html;
}

// --- HELPERS FOR GENERATING SUMMARY STRINGS ---
function generateWoundSummarySentence(asm) {
    if (!asm) return "No assessment data provided.";
    let parts = [];
    if (asm.length_cm && asm.width_cm) parts.push(`Measures <strong>${asm.length_cm}x${asm.width_cm}x${asm.depth_cm || '-'} cm</strong>.`);
    else parts.push("Assessed (no measurements).");

    if (asm.granulation_percent > 0 || asm.slough_percent > 0) {
        parts.push(`Bed: ${asm.granulation_percent || 0}% granulation, ${asm.slough_percent || 0}% slough.`);
    }

    if (asm.exudate_amount && asm.exudate_amount.toLowerCase() !== 'none') {
        parts.push(`Exudate: ${asm.exudate_amount.toLowerCase()} ${asm.exudate_type || ''}.`);
    } else {
        parts.push("No exudate.");
    }

    if (asm.odor_present && asm.odor_present.toLowerCase() === 'yes') parts.push("Odor present.");

    return parts.join(' ');
}

function setFieldContent(id, html) {
    // Ensure we don't paste undefined/null
    html = html || '';

    if (window.quillEditors && window.quillEditors[id]) {
        const editor = window.quillEditors[id];
        // Clear current content to avoid appending or weird merging
        editor.setContents([]); 
        // Use pasteHTML which is safer and handles Quill's internal format better than innerHTML
        editor.clipboard.dangerouslyPasteHTML(0, html, 'api');
    }
    const el = document.getElementById(id + '_input') || document.getElementById(id);
    if (el) el.value = html;
}

function getFieldContent(id) {
    if (window.quillEditors && window.quillEditors[id]) {
        return window.quillEditors[id].root.innerHTML;
    }
    const el = document.getElementById(id + '_input') || document.getElementById(id);
    return el ? el.value : '';
}

function syncQuillToInputs() {
    ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'].forEach(key => {
        const inputId = key === 'chief_complaint' ? 'chief_complaint_input' : key;
        const el = document.getElementById(inputId);
        if (window.quillEditors && window.quillEditors[key] && el) {
            el.value = window.quillEditors[key].root.innerHTML;
        }
    });
}

// --- DATA POPULATION ---
function autoPopulatePage(data) {
    const savedNote = data.visit.saved_note || {};
    const patient = data.patient || {};
    const profile = data.profile || {};

    // Check for finalized status
    if (savedNote.status === 'finalized') {
        handleFinalizedState(savedNote, data.visit.addendums || []);
    }

    const age = calculateAge(patient.date_of_birth);
    if(document.getElementById('demographics-name')) document.getElementById('demographics-name').textContent = `${patient.first_name || ''} ${patient.last_name || ''}`;
    if(document.getElementById('demographics-dob-age')) document.getElementById('demographics-dob-age').textContent = `${patient.date_of_birth || 'N/A'} (${age} yrs)`;

    const allergies = profile.allergies || 'No Known Drug Allergies';
    const allergiesEl = document.getElementById('demographics-allergies');
    if (allergiesEl) {
        allergiesEl.textContent = allergies;
        if (allergies.toLowerCase().includes('no known') || allergies.toLowerCase() === 'nkda') {
            allergiesEl.classList.replace('bg-red-50', 'bg-gray-50');
            allergiesEl.classList.replace('border-red-100', 'border-gray-100');
        }
    }
    if(document.getElementById('demographics-pmh')) document.getElementById('demographics-pmh').innerHTML = `<p>${(profile.medical_history?.conditions || 'No significant history').replace(/\n/g, '<br>')}</p>`;

    const savedCC = savedNote.chief_complaint;
    let ccContent = (savedCC && savedCC !== "0" && savedCC.trim() !== "") ? savedCC : (data.visit.generated_cc || 'Could not generate CC.');
    setFieldContent('chief_complaint', cleanHtmlContent(ccContent));

    // HPI with enhanced container
    const hpiText = data.visit.hpi_narrative || 'No HPI narrative recorded.';
    const hpiContainer = document.getElementById('auto-populated-hpi');

    if (hpiContainer) {
        hpiContainer.innerHTML = `
            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r shadow-sm group relative">
                <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <button id="edit-hpi-btn" class="text-xs bg-white border border-blue-200 text-blue-600 hover:bg-blue-100 px-2 py-1 rounded shadow-sm flex items-center transition-colors">
                        <i data-lucide="edit-2" class="w-3 h-3 mr-1"></i> Edit
                    </button>
                </div>
                <div class="flex items-start">
                    <i data-lucide="message-square" class="w-5 h-5 text-blue-500 mr-3 mt-0.5 flex-shrink-0"></i>
                    <div class="text-blue-900 text-sm leading-relaxed w-full">
                        <div class="mb-2 border-b border-blue-100 pb-1"><span class="text-xs font-bold text-blue-800 uppercase tracking-wide">HPI Narrative</span></div>
                        <div id="hpi-text-content" class="whitespace-pre-wrap">${hpiText}</div>
                        <div id="hpi-edit-container" class="hidden mt-2">
                            <textarea id="hpi-edit-textarea" class="w-full p-2 border border-blue-300 rounded text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white" rows="6"></textarea>
                            <div class="flex justify-end gap-2 mt-2">
                                <button id="cancel-hpi-btn" class="text-xs bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 px-3 py-1 rounded transition-colors">Cancel</button>
                                <button id="save-hpi-btn" class="text-xs bg-blue-600 text-white hover:bg-blue-700 px-3 py-1 rounded shadow-sm flex items-center transition-colors">
                                    <i data-lucide="save" class="w-3 h-3 mr-1"></i> Save
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Attach Event Listeners for HPI Edit
        const editBtn = document.getElementById('edit-hpi-btn');
        const saveBtn = document.getElementById('save-hpi-btn');
        const cancelBtn = document.getElementById('cancel-hpi-btn');
        const textContent = document.getElementById('hpi-text-content');
        const editContainer = document.getElementById('hpi-edit-container');
        const textarea = document.getElementById('hpi-edit-textarea');

        if (editBtn) {
            editBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // Use innerText to get the visible text, preserving newlines
                textarea.value = textContent.innerText; 
                textContent.classList.add('hidden');
                editContainer.classList.remove('hidden');
                editBtn.classList.add('hidden');
                textarea.focus();
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                editContainer.classList.add('hidden');
                textContent.classList.remove('hidden');
                editBtn.classList.remove('hidden');
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const newText = textarea.value.trim();
                if (!newText) return;

                // Show loading state
                const originalBtnContent = saveBtn.innerHTML;
                saveBtn.innerHTML = '<span class="ai-spinner w-3 h-3 border-white border-t-transparent"></span>';
                saveBtn.disabled = true;

                try {
                    const response = await fetch('api/save_hpi_narrative_to_note.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            appointment_id: window.phpVars.appointmentId,
                            patient_id: window.phpVars.patientId,
                            user_id: window.phpVars.userId,
                            narrative_text: newText
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        textContent.innerText = newText;
                        editContainer.classList.add('hidden');
                        textContent.classList.remove('hidden');
                        editBtn.classList.remove('hidden');
                        showFloatingAlert('HPI Narrative updated.', 'success');
                    } else {
                        showFloatingAlert('Failed to update HPI: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (err) {
                    console.error(err);
                    showFloatingAlert('Network error saving HPI.', 'error');
                } finally {
                    saveBtn.innerHTML = originalBtnContent;
                    saveBtn.disabled = false;
                    if(window.lucide) window.lucide.createIcons({ nodes: [saveBtn] });
                }
            });
        }
    }
    setFieldContent('subjective', cleanHtmlContent(savedNote.subjective || ''));

    if(document.getElementById('auto-populated-vitals')) document.getElementById('auto-populated-vitals').innerHTML = formatVitalsHTML(data.visit.vitals);
    setFieldContent('objective', cleanHtmlContent(savedNote.objective || ''));

    if(document.getElementById('auto-populated-diagnoses')) document.getElementById('auto-populated-diagnoses').innerHTML = formatDiagnosesHTML(data.visit.diagnoses);
    if(document.getElementById('auto-populated-wounds')) {
        document.getElementById('auto-populated-wounds').innerHTML = formatWoundsHTML(data.visit.wound_assessments);
        
        // Attach event listeners for debridement editing
        document.querySelectorAll('.edit-debridement-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                document.getElementById(`debridement-display-${id}`).classList.add('hidden');
                document.getElementById(`debridement-edit-${id}`).classList.remove('hidden');
                btn.classList.add('hidden');
            });
        });

        document.querySelectorAll('.cancel-debridement-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                document.getElementById(`debridement-edit-${id}`).classList.add('hidden');
                document.getElementById(`debridement-display-${id}`).classList.remove('hidden');
                // Find the edit button and show it again
                const editBtn = document.querySelector(`.edit-debridement-btn[data-id="${id}"]`);
                if(editBtn) editBtn.classList.remove('hidden');
            });
        });

        document.querySelectorAll('.save-debridement-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                const textarea = document.getElementById(`debridement-textarea-${id}`);
                const newText = textarea.value.trim();
                
                if (!newText) return;

                // Show loading
                const originalContent = btn.innerHTML;
                btn.innerHTML = '<span class="ai-spinner w-3 h-3 border-white border-t-transparent"></span>';
                btn.disabled = true;

                try {
                    const response = await fetch('api/save_debridement_narrative.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            assessment_id: id,
                            narrative: newText
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        document.getElementById(`debridement-display-${id}`).innerText = newText;
                        document.getElementById(`debridement-edit-${id}`).classList.add('hidden');
                        document.getElementById(`debridement-display-${id}`).classList.remove('hidden');
                        const editBtn = document.querySelector(`.edit-debridement-btn[data-id="${id}"]`);
                        if(editBtn) editBtn.classList.remove('hidden');
                        showFloatingAlert('Debridement note updated.', 'success');
                    } else {
                        showFloatingAlert('Failed to save: ' + (result.message || 'Unknown error'), 'error');
                    }
                } catch (err) {
                    console.error(err);
                    showFloatingAlert('Network error saving note.', 'error');
                } finally {
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                    if(window.lucide) window.lucide.createIcons({ nodes: [btn] });
                }
            });
        });
    }
    setFieldContent('assessment', cleanHtmlContent(savedNote.assessment || ''));

    if(document.getElementById('auto-populated-procedures')) document.getElementById('auto-populated-procedures').innerHTML = formatProceduresHTML(data.visit.procedures);
    if(document.getElementById('auto-populated-medications')) document.getElementById('auto-populated-medications').innerHTML = formatMedicationsHTML(data.visit.medications);
    if(document.getElementById('auto-populated-diagnoses-plan')) document.getElementById('auto-populated-diagnoses-plan').innerHTML = formatDiagnosesHTML(data.visit.diagnoses);
    if(document.getElementById('auto-populated-wound-plans')) document.getElementById('auto-populated-wound-plans').innerHTML = formatWoundPlansHTML(data.visit.wound_assessments);
    setFieldContent('plan', cleanHtmlContent(savedNote.plan || ''));

    setFieldContent('lab_orders', cleanHtmlContent(savedNote.lab_orders || ''));
    setFieldContent('imaging_orders', cleanHtmlContent(savedNote.imaging_orders || ''));
    setFieldContent('skilled_nurse_orders', cleanHtmlContent(savedNote.skilled_nurse_orders || ''));

    const signatureData = savedNote.signature_data;
    const sigInput = document.getElementById('signature_data');
    if (signatureData && sigInput) {
        sigInput.value = signatureData;
        const canvas = document.getElementById('signature-pad');
        if (canvas) {
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.onload = function() { ctx.drawImage(img, 0, 0); };
            img.src = signatureData;
        }
    }

    if(window.lucide) window.lucide.createIcons();
}

// --- UI UTILITIES ---
function showMessage(element, message, type) {
    if (!element) return;
    element.textContent = message;
    element.className = 'p-3 my-3 rounded-lg font-medium shadow mt-4';
    if (type === 'error') element.classList.add('bg-red-100', 'text-red-800');
    else if (type === 'success') element.classList.add('bg-green-100', 'text-green-800');
    else element.classList.add('bg-blue-100', 'text-blue-800');
    element.classList.remove('hidden');
    setTimeout(() => element.classList.add('hidden'), 5000);
}

function showFloatingAlert(message, type = 'info') {
    const container = document.getElementById('floating-alert-container');
    if (!container) return null;
    if(floatingAlertElement) removeFloatingAlert(floatingAlertElement);

    const alertElement = document.createElement('div');
    alertElement.className = `floating-alert alert-${type}`;
    let icon = type === 'info' ? '<span class="ai-spinner mr-2"></span>' :
        (type === 'success' ? '<i data-lucide="check-circle" class="inline-block w-5 h-5 mr-2"></i>' :
            '<i data-lucide="alert-triangle" class="inline-block w-5 h-5 mr-2"></i>');

    alertElement.innerHTML = `${icon}${message}`;
    container.appendChild(alertElement);
    floatingAlertElement = alertElement;
    lucide.createIcons({ nodes: [alertElement.querySelector('i')] });
    setTimeout(() => alertElement.classList.add('is-visible'), 10);
    return alertElement;
}

function removeFloatingAlert(alertElement) {
    if (!alertElement) return;
    alertElement.classList.remove('is-visible');
    alertElement.classList.add('is-fading');
    if (floatingAlertElement === alertElement) floatingAlertElement = null;
    setTimeout(() => alertElement.remove(), 300);
}

// --- MODAL UTILITIES ---
function showCustomModal({ title, message, type = 'info', showCancel = false, confirmText = 'OK', cancelText = 'Cancel', onConfirm = null }) {
    const modal = document.getElementById('messageModal');
    const titleEl = document.getElementById('msg-modal-title');
    const bodyEl = document.getElementById('msg-modal-body');
    const iconBg = document.getElementById('msg-modal-icon-bg');
    const icon = document.getElementById('msg-modal-icon');
    const confirmBtn = document.getElementById('msg-modal-confirm-btn');
    const cancelBtn = document.getElementById('msg-modal-cancel-btn');

    if (!modal) return;

    titleEl.textContent = title;
    bodyEl.innerHTML = message.replace(/\n/g, '<br>');
    confirmBtn.textContent = confirmText;
    cancelBtn.textContent = cancelText;

    // Icon & Color styling
    if (type === 'error' || type === 'warning') {
        iconBg.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10';
        icon.className = 'h-6 w-6 text-red-600';
        icon.setAttribute('data-lucide', 'alert-triangle');
        confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors';
    } else if (type === 'success') {
        iconBg.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10';
        icon.className = 'h-6 w-6 text-green-600';
        icon.setAttribute('data-lucide', 'check-circle');
        confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors';
    } else {
        iconBg.className = 'mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10';
        icon.className = 'h-6 w-6 text-blue-600';
        icon.setAttribute('data-lucide', 'info');
        confirmBtn.className = 'w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors';
    }

    if (showCancel) {
        cancelBtn.classList.remove('hidden');
    } else {
        cancelBtn.classList.add('hidden');
    }

    if(window.lucide) window.lucide.createIcons();

    // Event Handlers
    const close = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    // Clone buttons to remove old listeners
    const newConfirm = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
    
    const newCancel = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);

    newConfirm.addEventListener('click', () => {
        close();
        if (onConfirm) onConfirm();
    });

    newCancel.addEventListener('click', close);

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function openModal(modal, dialog) {
    if (!modal || !dialog) return;
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => dialog.classList.add('show-modal'), 10);
}

function closeModal(modal, dialog) {
    if (!modal || !dialog) return;
    dialog.classList.remove('show-modal');
    setTimeout(() => {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
        modal.style.display = '';
        const content = document.getElementById('preview-modal-content');
        if(content) content.innerHTML = '<p class="text-gray-500 p-4">Loading content...</p>';
    }, 300);
}

// --- AUTOSAVE LOGIC ---
async function triggerAutosave() {
    if (isAutosaving) return;
    isAutosaving = true;

    const headerStatus = document.getElementById('header-autosave-status');
    if(headerStatus) {
        headerStatus.classList.remove('opacity-0');
        headerStatus.innerHTML = '<span class="ai-spinner w-3 h-3 mr-2 border-gray-400 border-t-blue-600"></span> <span class="text-gray-500 text-xs italic">Saving...</span>';
    }

    syncQuillToInputs();
    const noteForm = document.getElementById('noteForm');
    if (!noteForm) { isAutosaving = false; return; }

    const formData = new FormData(noteForm);
    const payload = {};
    for (const [key, value] of formData.entries()) payload[key] = value;
    payload.user_id = window.phpVars.userId;

    try {
        const response = await fetch('api/save_visit_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (response.ok && result.success) {
            const now = new Date();
            const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            if(headerStatus) {
                headerStatus.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4 text-green-500 mr-2"></i> <span class="text-green-700 text-xs font-semibold">Saved (${timeString})</span>`;
                if(window.lucide) lucide.createIcons({ nodes: [headerStatus.querySelector('i')] });

                setTimeout(() => {
                    if(headerStatus.textContent.includes(timeString)) headerStatus.classList.add('opacity-0');
                }, 4000);
            }
        } else {
            throw new Error(result.message || 'Server returned error');
        }
    } catch (error) {
        console.warn('Autosave failed', error);
        if(headerStatus) {
            headerStatus.innerHTML = '<i data-lucide="cloud-off" class="w-4 h-4 text-amber-500 mr-2"></i> <span class="text-amber-600 text-xs font-semibold">Saved Locally (Offline)</span>';
            if(window.lucide) lucide.createIcons({ nodes: [headerStatus.querySelector('i')] });
        }
    }
    finally { isAutosaving = false; }
}

// --- CHECKLIST RENDERING LOGIC (UI Enhanced) ---

function sanitizeId(str) {
    return (str || '').replace(/\s+/g, '_').replace(/[^\w\-]/g, '').toLowerCase();
}

function escapeHtml(unsafe) {
    return (unsafe || '').replace(/[&<>"'`=\/]/g, function (s) {
        return ({'&': '&amp;','<': '&lt;','>': '&gt;','"': '&quot;',"'": '&#39;','/': '&#x2F;','`': '&#x60;','=': '&#x3D;'})[s];
    });
}

function updateBadgeCount(section, categoryName) {
    const badge = document.getElementById(`badge-${section}-${sanitizeId(categoryName)}`);
    if (!badge) return;
    const cnt = (window.checklistSelections[section] && window.checklistSelections[section][categoryName]) ? window.checklistSelections[section][categoryName].size : 0;
    badge.textContent = cnt > 0 ? `${cnt}` : '';
    badge.className = cnt > 0 ? 'badge badge-count-active' : 'badge badge-count-empty';
}

function toggleCheckAllForCategory(section, categoryName) {
    if (!window.checklistData[section]) return;
    const cat = window.checklistData[section].find(c => c.category_name === categoryName);
    if (!cat) return;
    const items = Array.isArray(cat.items) ? cat.items.map(i => (typeof i === 'string' ? i : (i.item_text || i.text || ''))) : [];
    if (!window.checklistSelections[section]) window.checklistSelections[section] = {};
    if (!window.checklistSelections[section][categoryName]) window.checklistSelections[section][categoryName] = new Set();

    const selectedSet = window.checklistSelections[section][categoryName];
    if (selectedSet.size < items.length) {
        items.forEach(t => selectedSet.add(t)); // select all
    } else {
        selectedSet.clear(); // clear all
    }

    if (window.checklistShowAll[section]) renderAllCategoriesItems(section);
    else renderChecklistItems(cat.items, section, categoryName);
    updateBadgeCount(section, categoryName);
}

function renderChecklistItems(items, section, categoryName) {
    const itemsDiv = document.getElementById('checklist-items');
    if (!itemsDiv) return;
    itemsDiv.innerHTML = '';

    // Insert a header for the current view
    const viewHeader = document.createElement('div');
    viewHeader.className = 'mb-4 flex items-center justify-between border-b pb-2';
    viewHeader.innerHTML = `
        <h4 class="font-bold text-lg text-gray-800">${categoryName}</h4>
        <span class="text-xs text-gray-500">${items.length} items available</span>
    `;
    itemsDiv.appendChild(viewHeader);

    if (!window.checklistSelections[section]) window.checklistSelections[section] = {};
    if (!window.checklistSelections[section][categoryName]) window.checklistSelections[section][categoryName] = new Set();
    const selectedSet = window.checklistSelections[section][categoryName];

    if (!items || items.length === 0) {
        itemsDiv.innerHTML += '<p class="text-gray-500 mt-4 text-center italic">No items in this category.</p>';
        return;
    }

    const colorMap = { 
        subjective: 'checklist-color-blue', 
        objective: 'checklist-color-green', 
        assessment: 'checklist-color-orange', 
        plan: 'checklist-color-indigo',
        lab_orders: 'checklist-color-teal',
        imaging_orders: 'checklist-color-teal',
        skilled_nurse_orders: 'checklist-color-teal'
    };
    const checkColor = colorMap[section] || 'checklist-color-blue';

    // Group items by title
    const grouped = {};
    const noTitle = [];

    items.forEach(item => {
        const text = typeof item === 'string' ? item : (item.item_text || item.text || '');
        const title = (typeof item === 'object' && item.title) ? item.title : null;
        
        if (title) {
            if (!grouped[title]) grouped[title] = [];
            grouped[title].push(text);
        } else {
            noTitle.push(text);
        }
    });

    const renderGrid = (itemList, container) => {
        const grid = document.createElement('div');
        grid.className = 'checklist-grid';
        
        itemList.forEach(text => {
            const label = document.createElement('label');
            label.className = 'checklist-item-label';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'checklist-item-input';
            checkbox.value = text;
            if (selectedSet.has(text)) checkbox.checked = true;

            checkbox.addEventListener('change', (e) => {
                if (e.target.checked) selectedSet.add(text);
                else selectedSet.delete(text);
                updateBadgeCount(section, categoryName);
            });

            const box = document.createElement('div');
            box.className = `checklist-item-box ${checkColor}`;
            const span = document.createElement('span');
            span.className = 'checklist-item-text';
            span.textContent = text;

            label.appendChild(checkbox);
            label.appendChild(box);
            label.appendChild(span);
            grid.appendChild(label);
        });
        container.appendChild(grid);
    };

    if (noTitle.length > 0) {
        renderGrid(noTitle, itemsDiv);
    }

    Object.keys(grouped).forEach(title => {
        const titleEl = document.createElement('h5');
        titleEl.className = 'font-semibold text-sm text-gray-600 mb-2 mt-4 border-b border-gray-100 pb-1';
        titleEl.textContent = title;
        itemsDiv.appendChild(titleEl);
        renderGrid(grouped[title], itemsDiv);
    });
}

function renderAllCategoriesItems(section) {
    const itemsDiv = document.getElementById('checklist-items');
    if (!itemsDiv) return;
    itemsDiv.innerHTML = '';
    const categories = window.checklistData[section] || [];

    const container = document.createElement('div');
    container.className = 'flex flex-col gap-6 pr-2 pl-1';

    categories.forEach(cat => {
        const catName = cat.category_name || 'Uncategorized';
        if (!window.checklistSelections[section]) window.checklistSelections[section] = {};
        if (!window.checklistSelections[section][catName]) window.checklistSelections[section][catName] = new Set();

        const catContainer = document.createElement('div');
        catContainer.className = 'bg-gray-50 rounded-lg p-3 border border-gray-100';

        const header = document.createElement('div');
        header.className = 'flex items-center justify-between border-b border-gray-200 pb-2 mb-2';
        header.innerHTML = `<div class="font-bold text-gray-700">${catName}</div>`;

        const btn = document.createElement('button');
        btn.className = 'text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium';
        btn.textContent = 'Check All';
        btn.onclick = (e) => {
            e.preventDefault();
            toggleCheckAllForCategory(section, catName);
        };

        header.appendChild(btn);
        catContainer.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'checklist-grid';

        const colorMap = { 
            subjective: 'checklist-color-blue', 
            objective: 'checklist-color-green', 
            assessment: 'checklist-color-orange', 
            plan: 'checklist-color-indigo',
            lab_orders: 'checklist-color-teal',
            imaging_orders: 'checklist-color-teal',
            skilled_nurse_orders: 'checklist-color-teal'
        };
        const checkColor = colorMap[section] || 'checklist-color-blue';
        const items = Array.isArray(cat.items) ? cat.items : [];
        const selectedSet = window.checklistSelections[section][catName];

        // Group items by title
        const grouped = {};
        const noTitle = [];

        items.forEach(item => {
            const text = typeof item === 'string' ? item : (item.item_text || item.text || '');
            const title = (typeof item === 'object' && item.title) ? item.title : null;
            if (title) {
                if (!grouped[title]) grouped[title] = [];
                grouped[title].push(text);
            } else {
                noTitle.push(text);
            }
        });

        const renderGrid = (itemList, container) => {
            const grid = document.createElement('div');
            grid.className = 'checklist-grid';
            
            itemList.forEach(text => {
                const label = document.createElement('label');
                label.className = 'checklist-item-label';
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'checklist-item-input';
                checkbox.value = text;
                checkbox.checked = selectedSet.has(text);
                checkbox.addEventListener('change', (e) => {
                    if (e.target.checked) selectedSet.add(text);
                    else selectedSet.delete(text);
                    updateBadgeCount(section, catName);
                });
                const box = document.createElement('div');
                box.className = `checklist-item-box ${checkColor}`;
                const span = document.createElement('span');
                span.className = 'checklist-item-text';
                span.textContent = text;
                label.appendChild(checkbox);
                label.appendChild(box);
                label.appendChild(span);
                grid.appendChild(label);
            });
            container.appendChild(grid);
        };

        if (noTitle.length > 0) renderGrid(noTitle, catContainer);

        Object.keys(grouped).forEach(title => {
            const titleEl = document.createElement('h5');
            titleEl.className = 'font-semibold text-xs text-gray-500 mb-1 mt-3 border-b border-gray-100 pb-1';
            titleEl.textContent = title;
            catContainer.appendChild(titleEl);
            renderGrid(grouped[title], catContainer);
        });

        container.appendChild(catContainer);
    });
    itemsDiv.appendChild(container);
}

function renderChecklistCategoriesFromStore(section) {
    const categoriesDiv = document.getElementById('checklist-categories');
    if (!categoriesDiv) return;
    const categories = window.checklistData[section] || [];
    categoriesDiv.innerHTML = '';

    if (categories.length === 0) {
        categoriesDiv.innerHTML = '<p class="text-xs text-gray-400 p-2">No categories.</p>';
        return;
    }

    // Controls
    const controlsRow = document.createElement('div');
    controlsRow.className = 'mb-3 flex items-center gap-2 pb-2 border-b border-gray-100';

    const showAllBtn = document.createElement('button');
    showAllBtn.className = 'w-full py-2 text-sm font-semibold text-gray-600 hover:bg-gray-100 rounded-md transition-colors text-left pl-2 flex items-center';

    const isShowAll = window.checklistShowAll[section];
    showAllBtn.innerHTML = isShowAll
        ? '<i data-lucide="minimize-2" class="w-4 h-4 mr-2"></i> Collapse Groups'
        : '<i data-lucide="maximize-2" class="w-4 h-4 mr-2"></i> Show All Groups';

    showAllBtn.onclick = () => {
        window.checklistShowAll[section] = !window.checklistShowAll[section];
        if(window.checklistShowAll[section]) {
            renderAllCategoriesItems(section);
            // Deselect active category highlighting
            document.querySelectorAll('.checklist-category').forEach(el => el.classList.remove('active'));
        } else {
            const first = categories[0] ? categories[0].category_name : null;
            if(first) {
                activeChecklistCategory = first;
                renderChecklistItems(categories[0].items, section, first);
                document.querySelectorAll('.checklist-category').forEach(el => el.classList.toggle('active', el.dataset.category === first));
            }
        }
        renderChecklistCategoriesFromStore(section); // Re-render to update button text
    };
    controlsRow.appendChild(showAllBtn);
    categoriesDiv.appendChild(controlsRow);

    // List Categories
    const listContainer = document.createElement('div');
    listContainer.className = 'flex flex-col gap-1';

    categories.forEach((cat, index) => {
        const catName = cat.category_name || 'Uncategorized';
        const catDiv = document.createElement('div');

        // Determine active state
        const isActive = !window.checklistShowAll[section] && (activeChecklistCategory === catName || (activeChecklistCategory === null && index === 0));
        if (isActive && activeChecklistCategory === null) activeChecklistCategory = catName; // default init

        catDiv.className = `checklist-category ${isActive ? 'active' : ''}`;
        catDiv.dataset.category = catName;
        catDiv.innerHTML = `<span class="truncate flex-1">${catName}</span>`;

        // Badge
        const badge = document.createElement('span');
        badge.id = `badge-${section}-${sanitizeId(catName)}`;
        // Badge class init
        const cnt = (window.checklistSelections[section] && window.checklistSelections[section][catName]) ? window.checklistSelections[section][catName].size : 0;
        badge.textContent = cnt > 0 ? `${cnt}` : '';
        badge.className = cnt > 0 ? 'badge badge-count-active' : 'badge badge-count-empty';

        catDiv.appendChild(badge);

        catDiv.onclick = () => {
            window.checklistShowAll[section] = false;
            activeChecklistCategory = catName;
            renderChecklistCategoriesFromStore(section); // re-render sidebar active state
            renderChecklistItems(cat.items, section, catName);
        };
        listContainer.appendChild(catDiv);
    });
    categoriesDiv.appendChild(listContainer);

    // If show all mode, ensure we render the content
    if (window.checklistShowAll[section]) {
        renderAllCategoriesItems(section);
    } else if (activeChecklistCategory) {
        // Ensure items are rendered for active category if not "show all"
        const activeCatData = categories.find(c => c.category_name === activeChecklistCategory);
        if (activeCatData) renderChecklistItems(activeCatData.items, section, activeChecklistCategory);
    }

    // Re-init icons
    if (window.lucide) window.lucide.createIcons();
}

function handleInsertChecklist() {
    if (!window.activeChecklistSection) return;
    const selections = window.checklistSelections[window.activeChecklistSection] || {};
    const sectionData = window.checklistData[window.activeChecklistSection] || [];

    // Helper to find item title
    const findItemTitle = (catName, itemText) => {
        const cat = sectionData.find(c => c.category_name === catName);
        if (!cat || !cat.items) return null;
        const item = cat.items.find(i => {
            const t = typeof i === 'string' ? i : (i.item_text || i.text || '');
            return t === itemText;
        });
        return (item && typeof item === 'object') ? item.title : null;
    };

    const groups = {};
    let hasSelection = false;

    Object.keys(selections).forEach(catName => {
        const set = selections[catName];
        if (set && set.size) {
            hasSelection = true;
            set.forEach(text => {
                const title = findItemTitle(catName, text);
                // Use Title if available, else Category Name
                const groupName = (title && title.trim() !== '') ? title : catName;
                
                if (!groups[groupName]) groups[groupName] = [];
                groups[groupName].push(text);
            });
        }
    });

    if (!hasSelection) {
        showFloatingAlert('No items selected.', 'info');
        return;
    }

    let html = '';
    const isOrderSection = ['lab_orders', 'imaging_orders', 'skilled_nurse_orders'].includes(window.activeChecklistSection);

    if (isOrderSection) {
        // Flatten all items from all groups into one list without headers
        let allItems = [];
        Object.keys(groups).forEach(groupName => {
            allItems = allItems.concat(groups[groupName]);
        });
        
        if (allItems.length > 0) {
            html += '<ol>';
            allItems.forEach(it => { html += `<li>${escapeHtml(it)}</li>`; });
            html += '</ol>';
        }
    } else {
        // Standard behavior with headers
        Object.keys(groups).forEach(groupName => {
            html += `<p><strong>${escapeHtml(groupName)}:</strong></p><ol>`;
            groups[groupName].forEach(it => { html += `<li>${escapeHtml(it)}</li>`; });
            html += '</ol>';
        });
    }

    // Use the editor for the active section (which should be initialized by now)
    const editor = window.quillEditors[window.activeChecklistSection];
    if (editor) {
        editor.clipboard.dangerouslyPasteHTML(editor.getLength(), html, 'user');
        updateNoteCompletionChecklist();
        showFloatingAlert('Items inserted!', 'success');
    } else {
        const hiddenInput = document.getElementById(window.activeChecklistSection + '_input') || document.getElementById(window.activeChecklistSection);
        if (hiddenInput) {
            hiddenInput.value += html;
            showFloatingAlert('Items inserted (to raw input)', 'info');
        }
    }

    closeModal(document.getElementById('checklistModal'), document.getElementById('checklistModalDialog'));
}

// --- OPEN MODAL ---
window.openChecklistModal = async function(section) {
    console.log("Opening checklist modal for:", section);

    if (!section) section = window.activeChecklistSection;
    if (!section) section = 'subjective';
    window.activeChecklistSection = section;

    const modal = document.getElementById('checklistModal');
    const modalDialog = document.getElementById('checklistModalDialog');
    const title = document.getElementById('checklist-modal-title');
    const categoriesDiv = document.getElementById('checklist-categories');
    const itemsDiv = document.getElementById('checklist-items');

    if (!modal) { console.error("Checklist Modal not found!"); return; }

    // Theme Application
    const themeColors = {
        chief_complaint: 'theme-yellow',
        subjective: 'theme-blue',
        objective: 'theme-green',
        assessment: 'theme-orange',
        plan: 'theme-indigo',
        lab_orders: 'theme-teal',
        imaging_orders: 'theme-teal',
        skilled_nurse_orders: 'theme-teal'
    };
    const themeClass = themeColors[section] || 'theme-gray';

    // Reset classes
    modalDialog.classList.remove('theme-yellow', 'theme-blue', 'theme-green', 'theme-orange', 'theme-indigo', 'theme-gray', 'theme-teal');
    modalDialog.classList.add(themeClass);

    if (title) {
        const pretty = section.charAt(0).toUpperCase() + section.slice(1).replace('_',' ');
        title.innerHTML = `<span class="opacity-70 font-normal">Quick Insert:</span> ${pretty}`;
    }

    categoriesDiv.innerHTML = '<div class="flex justify-center p-4"><div class="ai-spinner border-gray-400"></div></div>';
    itemsDiv.innerHTML = '';

    openModal(modal, modalDialog);

    try {
        const response = await fetch(`api/get_soap_checklist.php?section=${encodeURIComponent(section)}`);
        const json = await response.json();
        console.log("Checklist API response:", json);

        let dataToRender = [];
        if (json && json.checklist && json.checklist[section]) {
            dataToRender = json.checklist[section];
        } else if (json && typeof json === 'object') {
            dataToRender = json;
        }

        if (!Array.isArray(dataToRender)) {
            const converted = [];
            if (dataToRender && typeof dataToRender === 'object') {
                Object.keys(dataToRender).forEach(k => {
                    if (k === 'success' || k === 'message') return;
                    converted.push({ category_name: k, items: dataToRender[k] });
                });
            }
            dataToRender = converted;
        }

        window.checklistData[section] = dataToRender;
        renderChecklistCategoriesFromStore(section);

    } catch (e) {
        console.warn("Checklist fetch failed", e);
        if (categoriesDiv) categoriesDiv.innerHTML = "<p class='text-red-500 p-2'>Failed to load options.</p>";
    }
};

// --- INITIALIZATION & EVENTS ---
async function fetchInitialData() {
    const { patientId, appointmentId } = window.phpVars;
    const nameHeader = document.getElementById('patient-name-header');
    try {
        const response = await fetch(`api/get_visit_bundle.php?patient_id=${patientId}&appointment_id=${appointmentId}`);
        if (!response.ok) throw new Error(`Status: ${response.status}`);
        const data = await response.json();
        window.globalDataBundle = data;

        if (data.patient && nameHeader) nameHeader.textContent = `${data.patient.first_name} ${data.patient.last_name}`;

        autoPopulatePage(data);
        updateNoteCompletionChecklist();

        checkLocalBackup();

    } catch (error) { console.error(error); }
}

async function handleSaveNote(e) {
    e.preventDefault();

    // Validation: Check for incomplete sections (except Orders)
    const checks = getChecklistStatus();
    const missing = checks.filter(c => !c.isDone && !c.isOptional);

    if (missing.length > 0) {
        const missingNames = missing.map(s => s.name).join('\n- ');
        showCustomModal({
            title: 'Incomplete Sections',
            message: `Cannot finalize note. The following sections are incomplete:\n\n${missingNames}\n\nPlease complete these sections before signing.`,
            type: 'error',
            confirmText: 'OK, I will fix it'
        });
        return;
    }

    // Confirmation for Finalization
    showCustomModal({
        title: 'Finalization Warning',
        message: "You are about to sign and finalize this note.\nThis will make the record READ-ONLY.\n\nPlease confirm that you have PREVIEWED and REVIEWED the note content.",
        type: 'warning',
        showCancel: true,
        confirmText: 'Yes, Finalize Note',
        cancelText: 'Cancel & Review',
        onConfirm: async () => {
            await performSaveNote();
        }
    });
}

async function performSaveNote() {
    const noteForm = document.getElementById('noteForm');
    const noteMessage = document.getElementById('note-message');
    syncQuillToInputs();

    const canvas = document.getElementById('signature-pad');
    const sigInput = document.getElementById('signature_data');
    if (canvas && sigInput && canvas.toDataURL) sigInput.value = canvas.toDataURL('image/png');

    const formData = new FormData(noteForm);
    const payload = {};
    for (const [key, value] of formData.entries()) payload[key] = value;
    payload.user_id = window.phpVars.userId;

    if(noteMessage) {
        noteMessage.classList.remove('hidden');
        noteMessage.innerHTML = '<span class="ai-spinner mr-2"></span> Saving note...';
    }

    try {
        const response = await fetch('api/save_visit_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (response.ok && result.success) {
            showMessage(noteMessage, result.message || 'Note saved successfully!', 'success');
            try { window.dispatchEvent(new CustomEvent('noteSaved', { detail: { appointmentId: window.phpVars.appointmentId, response: result } })); } catch (e) {}
            
            // Reload to show finalized state
            setTimeout(() => location.reload(), 1500);
        } else {
            showMessage(noteMessage, `Note Save Error: ${result.message}`, 'error');
        }
    } catch (error) {
        showMessage(noteMessage, `Network Error: Could not reach the server.`, 'error');
    }
}

function getChecklistStatus() {
    const hasContent = (id) => {
        const content = getFieldContent(id);
        return content && content.replace(/<[^>]*>/g, '').trim().length > 0;
    };

    const visitData = window.globalDataBundle?.visit || {};
    
    const hasVitals = () => {
        const v = visitData.vitals;
        return !!(v && (v.blood_pressure || v.heart_rate || v.respiratory_rate || v.temperature_celsius || v.height_cm || v.weight_kg));
    };

    const hasHPI = () => {
        return !!(visitData.hpi_narrative && visitData.hpi_narrative.trim().length > 0);
    };

    const hasWounds = () => {
        return Array.isArray(visitData.wound_assessments) && visitData.wound_assessments.length > 0;
    };

    const hasDiagnoses = () => {
        return Array.isArray(visitData.diagnoses) && visitData.diagnoses.length > 0;
    };

    const hasProcedures = () => {
        return Array.isArray(visitData.procedures) && visitData.procedures.length > 0;
    };

    const hasMedications = () => {
        return Array.isArray(visitData.medications) && visitData.medications.length > 0;
    };

    return [
        { id: 'vitals', name: 'Vitals', isDone: hasVitals() },
        { id: 'hpi', name: 'HPI', isDone: hasHPI() },
        { id: 'chief_complaint', name: 'Chief Complaint', isDone: hasContent('chief_complaint') },
        { id: 'subjective', name: 'Subjective Note', isDone: hasContent('subjective') },
        { id: 'wounds', name: 'Wound Assessment', isDone: hasWounds() },
        { id: 'objective', name: 'Objective Note', isDone: hasContent('objective') },
        { id: 'diagnoses', name: 'Diagnoses', isDone: hasDiagnoses() },
        { id: 'assessment', name: 'Assessment Note', isDone: hasContent('assessment') },
        { id: 'procedures', name: 'Procedures', isDone: hasProcedures() },
        { id: 'medications', name: 'Medications', isDone: hasMedications() },
        { id: 'plan', name: 'Plan Note', isDone: hasContent('plan') },
        { id: 'orders', name: 'Orders', isDone: hasContent('lab_orders') || hasContent('imaging_orders') || hasContent('skilled_nurse_orders'), isOptional: true }
    ];
}

function updateNoteCompletionChecklist() {
    const checklistEl = document.getElementById('note-completion-checklist');
    if (!checklistEl) return;
    
    const checks = getChecklistStatus();
    const total = checks.length;
    const completed = checks.filter(c => c.isDone).length;
    const percent = Math.round((completed / total) * 100);

    let html = `
        <div class="mb-4">
            <div class="flex justify-between text-xs font-semibold text-gray-500 mb-1">
                <span>Progress</span>
                <span>${percent}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500" style="width: ${percent}%"></div>
            </div>
        </div>
        <div class="space-y-2">
    `;

    checks.forEach(check => {
        const isDone = check.isDone;
        const icon = isDone ? 'check-circle-2' : 'circle';
        const colorClass = isDone ? 'text-green-500 bg-green-50 border-green-200' : 'text-gray-400 bg-gray-50 border-gray-200 hover:bg-gray-100';
        const iconColor = isDone ? 'text-green-600' : 'text-gray-300';
        const textColor = isDone ? 'text-gray-700 font-medium' : 'text-gray-500';
        
        // Map section IDs to tab names for navigation
        const tabMap = {
            'vitals': 'objective',
            'hpi': 'subjective',
            'chief_complaint': 'chief_complaint',
            'subjective': 'subjective',
            'wounds': 'assessment',
            'objective': 'objective',
            'diagnoses': 'assessment',
            'assessment': 'assessment',
            'procedures': 'plan',
            'medications': 'plan',
            'plan': 'plan',
            'orders': 'orders'
        };
        const targetTab = tabMap[check.id] || check.id;

        html += `
            <div class="checklist-item flex items-center p-2 rounded-lg border ${colorClass} transition-all cursor-pointer group" onclick="switchTab('${targetTab}')">
                <div class="mr-3 flex-shrink-0 ${iconColor}">
                    <i data-lucide="${icon}" class="w-5 h-5 transition-transform group-hover:scale-110"></i>
                </div>
                <span class="text-sm ${textColor} flex-grow">${check.name}</span>
                ${!isDone ? '<i data-lucide="chevron-right" class="w-4 h-4 text-gray-300 opacity-0 group-hover:opacity-100 transition-opacity"></i>' : ''}
            </div>
        `;
    });
    html += '</div>';
    
    checklistEl.innerHTML = html;
    if(window.lucide) window.lucide.createIcons();
}

// --- FINALIZED NOTE HANDLING ---
function handleFinalizedState(note, addendums) {
    console.log("Note is finalized. Locking UI.");
    
    // 1. Show Banner
    const banner = document.createElement('div');
    banner.className = 'bg-red-600 text-white px-4 py-3 shadow-md mb-4 rounded-lg flex items-center justify-between';
    banner.innerHTML = `
        <div class="flex items-center">
            <i data-lucide="lock" class="w-5 h-5 mr-2"></i>
            <span class="font-bold">This note is FINALIZED and READ-ONLY.</span>
        </div>
        <span class="text-sm bg-red-700 px-2 py-1 rounded">Signed by User #${note.finalized_by || '?'} on ${note.finalized_at || 'Unknown Date'}</span>
    `;
    const mainCol = document.getElementById('visit-workflow-form');
    if(mainCol) mainCol.insertBefore(banner, mainCol.firstChild);

    // 2. Disable Editors
    window.isNoteFinalized = true;
    
    // Disable existing editors
    Object.values(window.quillEditors).forEach(q => q.disable());
    
    // Disable textareas just in case
    document.querySelectorAll('textarea').forEach(el => el.disabled = true);
    
    // Hide Save Button
    const saveBtn = document.getElementById('saveNoteBtn');
    if(saveBtn) {
        saveBtn.style.display = 'none';
        // Also hide the container if needed, or replace with "Signed" message
        const container = saveBtn.parentElement;
        if(container) {
            const msg = document.createElement('div');
            msg.className = 'text-green-700 font-bold text-lg flex items-center bg-green-50 p-4 rounded border border-green-200 w-full justify-center';
            msg.innerHTML = `<i data-lucide="check-circle" class="w-6 h-6 mr-2"></i> Note Signed & Finalized`;
            container.appendChild(msg);
        }
    }
    
    // Hide Autosave Status
    const asStatus = document.getElementById('header-autosave-status');
    if(asStatus) asStatus.style.display = 'none';
    
    // Hide Quick Insert Buttons
    document.querySelectorAll('.quick-insert-btn').forEach(btn => btn.style.display = 'none');
    document.querySelectorAll('.btn-sm').forEach(btn => btn.style.display = 'none'); // Insert Normal buttons

    // 3. Show Addendum Section
    const addendumContainer = document.getElementById('addendum-container');
    if(addendumContainer) {
        addendumContainer.classList.remove('hidden');
        
        // Render Addendums
        const list = document.getElementById('addendum-list');
        if(list && addendums.length > 0) {
            list.innerHTML = addendums.map(a => `
                <div class="bg-gray-50 border-l-4 border-purple-500 p-4 rounded shadow-sm">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-bold text-purple-900 text-sm">Addendum by ${a.username || 'Unknown'}</span>
                        <span class="text-xs text-gray-500">${a.created_at}</span>
                    </div>
                    <div class="prose prose-sm max-w-none text-gray-800">
                        ${a.note_text}
                    </div>
                </div>
            `).join('');
        } else if (list) {
            list.innerHTML = '<p class="text-gray-500 italic text-sm">No addendums yet.</p>';
        }
        
        // Init Addendum Editor
        if(!window.quillEditors['addendum']) {
             const q = new Quill('#addendum-editor-container', {
                theme: 'snow',
                modules: { toolbar: [['bold', 'italic', 'underline'], ['link']] }
            });
            window.quillEditors['addendum'] = q;
        }
        
        // Bind Save Addendum Button
        const saveAddendumBtn = document.getElementById('saveAddendumBtn');
        if(saveAddendumBtn) {
            saveAddendumBtn.onclick = async () => {
                const content = window.quillEditors['addendum'].root.innerHTML;
                if(!content || content.trim() === '<p><br></p>') {
                    showFloatingAlert('Addendum cannot be empty.', 'error');
                    return;
                }
                
                saveAddendumBtn.disabled = true;
                saveAddendumBtn.innerHTML = '<span class="ai-spinner w-4 h-4"></span> Saving...';
                
                try {
                    const res = await fetch('api/save_addendum.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            appointment_id: window.phpVars.appointmentId,
                            note_text: content
                        })
                    });
                    const json = await res.json();
                    if(json.success) {
                        showFloatingAlert('Addendum saved!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showFloatingAlert(json.message || 'Error saving addendum', 'error');
                        saveAddendumBtn.disabled = false;
                        saveAddendumBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Sign & Save Addendum';
                        if(window.lucide) lucide.createIcons();
                    }
                } catch(e) {
                    console.error(e);
                    showFloatingAlert('Network error', 'error');
                    saveAddendumBtn.disabled = false;
                }
            };
        }
    }
    
    if(window.lucide) window.lucide.createIcons();
}

function generateProcedureNarrative() {
    const procedures = window.globalDataBundle?.visit?.procedures;
    if (!procedures || procedures.length === 0) {
        showFloatingAlert('No billed procedures found for this visit.', 'warning');
        return;
    }

    let narrative = "<p><strong>Procedure Note:</strong></p><ul>";
    procedures.forEach(proc => {
        narrative += `<li>Procedure performed: <strong>${proc.description || 'Procedure'}</strong> (CPT: ${proc.cpt_code}). Units: ${proc.units}. Patient tolerated the procedure well.</li>`;
    });
    narrative += "</ul>";

    const editor = window.quillEditors['plan'];
    if (editor) {
        const len = editor.getLength();
        editor.clipboard.dangerouslyPasteHTML(len, narrative, 'user');
        showFloatingAlert('Procedure narrative generated.', 'success');
    } else {
        showFloatingAlert('Plan editor not initialized.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.switchTab === 'function') window.switchTab('chief_complaint');
    else console.error("switchTab missing");

    fetchInitialData();
    const noteForm = document.getElementById('noteForm');
    if(noteForm) noteForm.addEventListener('submit', handleSaveNote);

    document.body.addEventListener('click', function(e) {
        try {
            const btn = e.target.closest('.quick-insert-btn');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const section = btn.getAttribute('data-section');
                if (typeof openChecklistModal === 'function') openChecklistModal(section);
            }
        } catch (err) { console.error(err); }
    });

    const insertBtn = document.getElementById('insertChecklistBtn');
    if(insertBtn) insertBtn.addEventListener('click', handleInsertChecklist);

    const procBtn = document.getElementById('generateProcNarrativeBtn');
    if(procBtn) procBtn.addEventListener('click', (e) => {
        e.preventDefault();
        generateProcedureNarrative();
    });

    // Add Preview Note Button Event Listener
    const previewBtn = document.getElementById('previewNoteBtn');
    if (previewBtn) {
        previewBtn.addEventListener('click', async function() {
            console.log("Preview Note button clicked.");
            try {
                // Trigger autosave first to ensure DB has latest data
                console.log("Triggering autosave before preview...");
                await triggerAutosave();

                const patientId = window.phpVars.patientId;
                const appointmentId = window.phpVars.appointmentId;
                const userId = window.phpVars.userId;

                console.log("Opening preview modal for:", { patientId, appointmentId });

                const iframe = document.createElement('iframe');
                iframe.src = `visit_report.php?appointment_id=${appointmentId}&patient_id=${patientId}&user_id=${userId}&mode=preview`;
                iframe.style.width = "100%";
                iframe.style.height = "100%";
                iframe.style.border = "none";

                const container = document.getElementById('preview-modal-content');
                if(container) {
                    container.innerHTML = ''; // Clear loading text
                    container.appendChild(iframe);
                }

                openModal(document.getElementById('previewModal'), document.getElementById('previewModalDialog'));
            } catch (e) {
                console.error("Error opening preview:", e);
            }
        });
    } else {
        console.warn("Preview Note button element not found in DOM.");
    }

    // New listener for "Clone Last Visit"
    const cloneBtn = document.getElementById('cloneLastVisitBtn');
    if(cloneBtn) {
        cloneBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const modal = document.getElementById('cloneConfirmationModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        });
    }

    // Clone Confirmation Modal Actions
    const confirmCloneBtn = document.getElementById('confirmCloneBtn');
    const cancelCloneBtn = document.getElementById('cancelCloneBtn');
    const cloneModal = document.getElementById('cloneConfirmationModal');

    if (confirmCloneBtn) {
        confirmCloneBtn.addEventListener('click', function() {
            if (cloneModal) {
                cloneModal.classList.add('hidden');
                cloneModal.classList.remove('flex');
            }
            cloneLastVisit();
        });
    }

    if (cancelCloneBtn) {
        cancelCloneBtn.addEventListener('click', function() {
            if (cloneModal) {
                cloneModal.classList.add('hidden');
                cloneModal.classList.remove('flex');
            }
        });
    }

    const closeIds = ['closeChecklistModalBtn', 'closeChecklistBtn', 'closePreviewModalBtn'];
    closeIds.forEach(id => {
        const el = document.getElementById(id);
        if(el) el.addEventListener('click', () => {
            closeModal(document.getElementById('checklistModal'), document.getElementById('checklistModalDialog'));
            closeModal(document.getElementById('previewModal'), document.getElementById('previewModalDialog'));
        });
    });

    // --- NEW: Listen for "Copy Note" messages from History Iframe ---
    window.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'copyNote') {
            const noteData = event.data.data;
            console.log('Received copyNote request:', noteData);

            if (!noteData) return;

            // Capture state for Undo (re-using the variable from Clone Last Visit)
            previousNoteState = captureNoteState();

            let copiedCount = 0;
            const fields = ['chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 'lab_orders', 'imaging_orders', 'skilled_nurse_orders'];

            fields.forEach(field => {
                // Check if the field exists in the copied note and is not just empty HTML
                if (noteData[field] && noteData[field].replace(/<[^>]*>/g, '').trim() !== '') {
                    initQuillForSection(field);
                    
                    const editor = window.quillEditors[field];
                    if (editor) {
                        const currentLen = editor.getLength();
                        const content = noteData[field];
                        
                        // If editor is effectively empty (length 1 is just the newline), replace.
                        // Otherwise, append with a separator.
                        if (currentLen <= 1) {
                            editor.clipboard.dangerouslyPasteHTML(0, content, 'api');
                        } else {
                            const dateStr = noteData.visit_date || 'History';
                            editor.insertText(currentLen - 1, `\n\n--- COPIED FROM ${dateStr} ---\n`, { 'bold': true, 'color': '#6b7280' });
                            const newLen = editor.getLength();
                            editor.clipboard.dangerouslyPasteHTML(newLen - 1, content, 'api');
                        }
                        copiedCount++;
                    }
                }
            });

            if (copiedCount > 0) {
                showFloatingAlert('Note copied from history successfully!', 'success');
                triggerAutosave();
                if (typeof showUndoToast === 'function') {
                    showUndoToast();
                }
            } else {
                showFloatingAlert('Selected note was empty or had no content to copy.', 'warning');
            }
        }
    });

    // --- AI Rewrite Logic ---
    window.closeAiModal = function() {
        document.getElementById('ai-review-modal').classList.add('hidden');
    };

    window.rewriteWithAI = async function(quill, button) {
        const originalIcon = button.innerHTML;
        
        // Get text to rewrite (selection or full text)
        const range = quill.getSelection();
        let textToRewrite = '';
        let isSelection = false;

        if (range && range.length > 0) {
            textToRewrite = quill.getText(range.index, range.length);
            isSelection = true;
        } else {
            textToRewrite = quill.getText();
        }

        if (!textToRewrite.trim()) {
            alert("Please type some text first.");
            return;
        }

        // Show loading state
        button.innerHTML = '<i data-lucide="loader" class="w-4 h-4 animate-spin text-indigo-600"></i>';
        if (typeof lucide !== 'undefined') lucide.createIcons();
        button.disabled = true;

        try {
            const response = await fetch('api/ai_rewrite.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text: textToRewrite })
            });

            const result = await response.json();

            if (result.success) {
                const rewrittenText = result.rewritten_text;
                
                // Compute Diff
                const dmp = new diff_match_patch();
                const diffs = dmp.diff_main(textToRewrite, rewrittenText);
                dmp.diff_cleanupSemantic(diffs);
                const html = dmp.diff_prettyHtml(diffs);

                // Show Modal
                const modal = document.getElementById('ai-review-modal');
                const content = document.getElementById('ai-diff-content');
                const acceptBtn = document.getElementById('ai-accept-btn');

                content.innerHTML = html;
                modal.classList.remove('hidden');

                // Set Accept Action
                acceptBtn.onclick = function() {
                    if (isSelection) {
                        quill.deleteText(range.index, range.length);
                        quill.insertText(range.index, rewrittenText);
                    } else {
                        quill.setText(rewrittenText);
                    }
                    showFloatingAlert('Text rewritten professionally', 'success');
                    closeAiModal();
                };

            } else {
                alert('AI Error: ' + (result.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('AI Request failed:', error);
            alert('Failed to connect to AI service.');
        } finally {
            button.innerHTML = originalIcon;
            button.disabled = false;
        }
    };
});

// --- Template Management ---
let currentQuillForTemplate = null;
let currentSectionForTemplate = null;

function openSaveTemplateModal(quill, section) {
    currentQuillForTemplate = quill;
    currentSectionForTemplate = section;
    document.getElementById('save-template-modal').classList.remove('hidden');
    document.getElementById('template-name-input').value = '';
    document.getElementById('template-name-input').focus();
}

    function closeSaveTemplateModal() {
        document.getElementById('save-template-modal').classList.add('hidden');
        currentQuillForTemplate = null;
        currentSectionForTemplate = null;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const confirmBtn = document.getElementById('confirm-save-template-btn');
        if (confirmBtn) {
            confirmBtn.onclick = async function() {
                const name = document.getElementById('template-name-input').value.trim();
                if (!name) {
                    alert('Please enter a template name.');
                    return;
                }

                const content = currentQuillForTemplate.root.innerHTML;
                
                try {
                    const response = await fetch('api/save_template.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            section_type: currentSectionForTemplate,
                            template_name: name,
                            template_content: content
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        alert('Template saved successfully!');
                        // Use existing toast if available, or alert
                        if (typeof showFloatingAlert === 'function') {
                            showFloatingAlert('Template saved successfully', 'success');
                        }
                        closeSaveTemplateModal();
                    } else {
                        alert('Error saving template: ' + result.error);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Network error');
                }
            };
        }
    });function openLoadTemplateModal(quill, section) {
    currentQuillForTemplate = quill;
    currentSectionForTemplate = section;
    document.getElementById('load-template-modal').classList.remove('hidden');
    loadTemplates(section);
}

function closeLoadTemplateModal() {
    document.getElementById('load-template-modal').classList.add('hidden');
    currentQuillForTemplate = null;
    currentSectionForTemplate = null;
}

async function loadTemplates(section) {
    const list = document.getElementById('template-list');
    list.innerHTML = '<p class="text-gray-500 text-sm italic">Loading...</p>';

    try {
        const response = await fetch(`api/get_templates.php?section_type=${section}`);
        const result = await response.json();

        if (result.success) {
            if (result.templates.length === 0) {
                list.innerHTML = '<p class="text-gray-500 text-sm italic">No templates found for this section.</p>';
                return;
            }

            list.innerHTML = '';
            result.templates.forEach(tpl => {
                const div = document.createElement('div');
                div.className = 'flex justify-between items-center p-2 hover:bg-gray-100 rounded cursor-pointer border-b last:border-0';
                div.innerHTML = `
                    <span class="font-medium text-gray-800">${tpl.template_name}</span>
                    <button class="text-red-500 hover:text-red-700 p-1" title="Delete" onclick="event.stopPropagation(); deleteTemplate(${tpl.id}, '${section}')">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                `;
                div.onclick = () => {
                    currentQuillForTemplate.clipboard.dangerouslyPasteHTML(currentQuillForTemplate.getLength(), tpl.template_content);
                    closeLoadTemplateModal();
                };
                list.appendChild(div);
            });
            if (window.lucide) lucide.createIcons();
        } else {
            list.innerHTML = '<p class="text-red-500 text-sm">Error loading templates.</p>';
        }
    } catch (e) {
        console.error(e);
        list.innerHTML = '<p class="text-red-500 text-sm">Network error.</p>';
    }
}

async function deleteTemplate(id, section) {
    if (!confirm('Are you sure you want to delete this template?')) return;

    try {
        const response = await fetch('api/delete_template.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: id })
        });
        const result = await response.json();
        if (result.success) {
            loadTemplates(section); // Reload list
        } else {
            alert('Error deleting template: ' + result.error);
        }
    } catch (e) {
        console.error(e);
        alert('Network error');
    }
}