// Filename: js/visit_ai_assistant_logic.js
// Purpose: Ported logic from visit_notes_logic.js to fetch and format clinical data for the AI Assistant.

console.log("Visit AI Assistant Logic Loaded");

// --- FORMATTING FUNCTIONS (Ported from visit_notes_logic.js) ---

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
        let debridementHtml = '';
        if (asm.debridement_performed === 'Yes') {
            // Determine debridement label based on type
            let debridementLabel = 'Surgical debridement';
            if (asm.debridement_type) {
                const typeLower = asm.debridement_type.toLowerCase().trim();
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
                    </div>
                    <div class="text-sm text-gray-700 leading-relaxed whitespace-pre-wrap">${currentNarrative}</div>
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

// --- FETCHING LOGIC ---

async function fetchAndPopulateClinicalData() {
    const patientId = window.visitContext.patientId;
    const appointmentId = window.visitContext.appointmentId;

    if (!patientId || !appointmentId) {
        console.error("Missing patientId or appointmentId for clinical data fetch.");
        return;
    }

    try {
        const [visitResponse, contextResponse] = await Promise.all([
            fetch(`api/get_visit_bundle.php?patient_id=${patientId}&appointment_id=${appointmentId}`),
            fetch(`api/get_clinical_context.php?patient_id=${patientId}&appointment_id=${appointmentId}`)
        ]);

        const data = await visitResponse.json();
        const contextData = await contextResponse.json();

        if (!data || !data.visit) {
            console.error("Invalid data structure returned from get_visit_bundle.php");
            return;
        }

        // Populate HPI
        const hpiContainer = document.getElementById('auto-populated-hpi');
        if (hpiContainer) {
            const hpiText = data.visit.hpi_narrative || 'No HPI narrative recorded.';
            hpiContainer.innerHTML = `
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r shadow-sm">
                    <div class="flex items-start">
                        <i data-lucide="message-square" class="w-5 h-5 text-blue-500 mr-3 mt-0.5 flex-shrink-0"></i>
                        <div class="text-blue-900 text-sm leading-relaxed w-full">
                            <div class="mb-2 border-b border-blue-100 pb-1"><span class="text-xs font-bold text-blue-800 uppercase tracking-wide">HPI Narrative</span></div>
                            <div class="whitespace-pre-wrap">${hpiText}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Populate Vitals
        if(document.getElementById('auto-populated-vitals')) {
            document.getElementById('auto-populated-vitals').innerHTML = formatVitalsHTML(data.visit.vitals);
        }

        // Populate Diagnoses
        if(document.getElementById('auto-populated-diagnoses')) {
            document.getElementById('auto-populated-diagnoses').innerHTML = formatDiagnosesHTML(data.visit.diagnoses);
        }

        // Populate Wounds
        if(document.getElementById('auto-populated-wounds')) {
            document.getElementById('auto-populated-wounds').innerHTML = formatWoundsHTML(data.visit.wound_assessments);
        }

        // Populate Procedures
        if(document.getElementById('auto-populated-procedures')) {
            document.getElementById('auto-populated-procedures').innerHTML = formatProceduresHTML(data.visit.procedures);
        }

        // Populate Medications
        if(document.getElementById('auto-populated-medications')) {
            document.getElementById('auto-populated-medications').innerHTML = formatMedicationsHTML(data.visit.medications);
        }

        // Populate Wound Plans
        if(document.getElementById('auto-populated-wound-plans')) {
            document.getElementById('auto-populated-wound-plans').innerHTML = formatWoundPlansHTML(data.visit.wound_assessments);
        }

        // Populate Wound Select in Annotation Modal
        const woundSelect = document.getElementById('img-wound-select');
        const woundsToPopulate = data.visit.active_wounds && data.visit.active_wounds.length > 0 
            ? data.visit.active_wounds 
            : (data.visit.wound_assessments || []);

        if (woundSelect) {
            woundSelect.innerHTML = '<option value="">-- Select Wound --</option>' + 
                woundsToPopulate.map(w => `<option value="${w.wound_id}">Wound ${w.location} (${w.wound_type})</option>`).join('');
        }

        // --- PRELOAD LIVE NOTE DRAFT (AI INSIGHT) ---
        const liveNoteContent = document.getElementById('live-note-content');
        // Use the global context to check if there's a real saved draft
        const hasSavedDraft = window.visitContext && window.visitContext.liveNoteDraft && window.visitContext.liveNoteDraft.trim() !== "";

        if (liveNoteContent && !hasSavedDraft) {
            
            let insightHtml = `<div class="mb-4 p-3 bg-indigo-50 border border-indigo-100 rounded-lg text-sm text-indigo-900">
                <div class="font-bold mb-2 flex items-center"><i data-lucide="sparkles" class="w-4 h-4 mr-2 text-indigo-600"></i> AI Clinical Insight</div>`;
            
            let hasInsightData = false;

            // Chief Complaint / Reason for Visit
            if (data.visit.generated_cc && data.visit.generated_cc.trim() !== "") {
                insightHtml += `<p class="mb-2"><strong>Reason for Visit:</strong> ${data.visit.generated_cc}</p>`;
                hasInsightData = true;
            }

            // Allergies
            if (data.profile && data.profile.allergies) {
                insightHtml += `<p class="mb-2"><strong>Allergies:</strong> ${data.profile.allergies}</p>`;
                hasInsightData = true;
            }

            // HPI
            if (data.visit.hpi_narrative && data.visit.hpi_narrative.trim() !== "") {
                insightHtml += `<p class="mb-2"><strong>HPI:</strong> ${data.visit.hpi_narrative}</p>`;
                hasInsightData = true;
            }

            // Recent History (from previous visits)
            if (contextData && contextData.success && contextData.notes && contextData.notes.length > 0) {
                const lastNote = contextData.notes[0];
                
                if (lastNote.chief_complaint && lastNote.chief_complaint.trim() !== "") {
                    insightHtml += `<p class="mb-2"><strong>Recent Chief Complaint (${lastNote.note_date}):</strong> ${lastNote.chief_complaint}</p>`;
                    hasInsightData = true;
                }

                if (lastNote.subjective && lastNote.subjective.trim() !== "") {
                    insightHtml += `<p class="mb-2"><strong>Recent HPI (${lastNote.note_date}):</strong> ${lastNote.subjective}</p>`;
                    hasInsightData = true;
                }
            }

            // Vitals
            if (data.visit.vitals) {
                const v = data.visit.vitals;
                const vitalsStr = [
                    v.blood_pressure ? `BP: ${v.blood_pressure}` : null,
                    v.heart_rate ? `HR: ${v.heart_rate}` : null,
                    v.temperature_celsius ? `Temp: ${v.temperature_celsius}°C` : null
                ].filter(Boolean).join(', ');
                if (vitalsStr) {
                    insightHtml += `<p class="mb-2"><strong>Vitals:</strong> ${vitalsStr}</p>`;
                    hasInsightData = true;
                }
            }

            // Wounds
            if (data.visit.wound_assessments && data.visit.wound_assessments.length > 0) {
                insightHtml += `<p class="mb-2"><strong>Active Wounds:</strong> ${data.visit.wound_assessments.length} wound(s) recorded.</p>`;
                hasInsightData = true;
            }

            insightHtml += `</div>`;
            
            // Only inject if we have some data
            if (hasInsightData) {
                liveNoteContent.innerHTML = insightHtml + "<p><br></p>"; // Add break for typing
            }
        }

        // Re-initialize icons
        if(window.lucide) window.lucide.createIcons();

    } catch (error) {
        console.error("Error fetching clinical data:", error);
    }
}

// Expose function globally
window.fetchAndPopulateClinicalData = fetchAndPopulateClinicalData;
