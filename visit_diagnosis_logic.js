// Filename: visit_diagnosis_logic.js
// Handles diagnosis search from DB, management, history fetching, bulk actions, and EDITING.

document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('icdSearchInput');
    const searchResults = document.getElementById('searchResults');
    const addBtn = document.getElementById('btnAddDiagnosis');
    const woundSelect = document.getElementById('woundSelect');
    const diagnosisNote = document.getElementById('diagnosisNote');

    const previewDiv = document.getElementById('selectedCodePreview');
    const previewCode = document.getElementById('previewCode');
    const previewDesc = document.getElementById('previewDesc');
    const clearSelectionBtn = document.getElementById('clearSelection');

    const tableBody = document.getElementById('diagnosisTableBody');
    const historyList = document.getElementById('historyList');
    const historyActions = document.getElementById('historyActions');
    const selectAllHistory = document.getElementById('selectAllHistory');
    const addSelectedHistoryBtn = document.getElementById('addSelectedHistoryBtn');

    // --- AI SUGGESTION ELEMENTS ---
    const btnAiSuggest = document.getElementById('btnAiSuggest');
    const aiSuggestionsContainer = document.getElementById('aiSuggestionsContainer');
    const aiResults = document.getElementById('aiResults');
    const aiLoading = document.getElementById('aiLoading');
    const closeAiSuggestions = document.getElementById('closeAiSuggestions');

    // --- MANUAL ENTRY ELEMENTS ---
    const searchModeContainer = document.getElementById('searchModeContainer');
    const manualModeContainer = document.getElementById('manualModeContainer');
    const toggleManualModeBtn = document.getElementById('toggleManualMode');
    const toggleSearchModeBtn = document.getElementById('toggleSearchMode');
    const manualCodeInput = document.getElementById('manualCodeInput');
    const manualDescInput = document.getElementById('manualDescInput');

    let selectedDiagnosis = null;
    let isManualMode = false;
    let debounceTimer;
    let currentDiagnosesCodes = new Set(); // Cache for duplicates

    // --- EDIT STATE VARIABLES ---
    let isEditing = false;
    let editingDiagnosisId = null;

    // --- TOAST NOTIFICATION ---
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const bgColor = type === 'success' ? 'bg-green-600' : (type === 'error' ? 'bg-red-600' : 'bg-blue-600');
        const icon = type === 'success' ? 'check-circle' : (type === 'error' ? 'alert-circle' : 'info');

        toast.className = `${bgColor} text-white px-4 py-3 rounded shadow-lg flex items-center gap-3 transform transition-all duration-300 translate-y-10 opacity-0`;
        toast.innerHTML = `
            <i data-lucide="${icon}" class="w-5 h-5"></i>
            <span class="font-medium text-sm">${message}</span>
        `;

        container.appendChild(toast);
        if(typeof lucide !== 'undefined') lucide.createIcons();

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-10', 'opacity-0');
        });

        // Remove after 3 seconds
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // --- 1. Search Logic (Auto-Populate from DB) ---
    searchInput.addEventListener('input', (e) => {
        if (isEditing) return; // Disable search while editing existing

        const query = e.target.value.trim();
        clearTimeout(debounceTimer);

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            return;
        }

        debounceTimer = setTimeout(async () => {
            try {
                const response = await fetch(`api/get_icd_code_suggestions.php?query=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success && data.results && data.results.length > 0) {
                    renderSearchResults(data.results);
                } else {
                    searchResults.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">No results found in database.</div>';
                    searchResults.classList.remove('hidden');
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }, 300);
    });

    function renderSearchResults(results) {
        searchResults.innerHTML = '';

        results.forEach(item => {
            const div = document.createElement('div');
            div.className = 'cursor-pointer hover:bg-indigo-50 px-4 py-2 border-b border-gray-100 last:border-0 text-sm text-gray-700';
            div.innerHTML = `<span class="font-bold text-indigo-700">${item.code}</span> - ${item.description}`;
            div.addEventListener('click', () => selectDiagnosis(item));
            searchResults.appendChild(div);
        });

        searchResults.classList.remove('hidden');
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    function selectDiagnosis(item) {
        selectedDiagnosis = item;
        searchInput.value = '';
        searchResults.classList.add('hidden');

        previewCode.textContent = item.code;
        previewDesc.textContent = item.description;
        previewDiv.classList.remove('hidden');
        addBtn.disabled = false;
    }

    // Modified Clear Selection to also handle Cancel Edit
    clearSelectionBtn.addEventListener('click', () => {
        if (isEditing) {
            cancelEdit();
        } else {
            selectedDiagnosis = null;
            previewDiv.classList.add('hidden');
            addBtn.disabled = true;
        }
    });

    // --- MANUAL MODE LOGIC ---
    toggleManualModeBtn.addEventListener('click', () => {
        isManualMode = true;
        searchModeContainer.classList.add('hidden');
        manualModeContainer.classList.remove('hidden');
        
        // Reset search selection if any
        selectedDiagnosis = null;
        previewDiv.classList.add('hidden');
        
        validateManualEntry();
    });

    toggleSearchModeBtn.addEventListener('click', () => {
        isManualMode = false;
        searchModeContainer.classList.remove('hidden');
        manualModeContainer.classList.add('hidden');
        
        // Reset manual inputs
        manualCodeInput.value = '';
        manualDescInput.value = '';
        
        // Re-evaluate button state
        addBtn.disabled = true;
    });

    function validateManualEntry() {
        if (isManualMode) {
            const code = manualCodeInput.value.trim();
            const desc = manualDescInput.value.trim();
            addBtn.disabled = !(code.length > 0 && desc.length > 0);
        }
    }

    manualCodeInput.addEventListener('input', validateManualEntry);
    manualDescInput.addEventListener('input', validateManualEntry);

    // --- AI SUGGESTIONS LOGIC ---
    btnAiSuggest.addEventListener('click', async () => {
        aiSuggestionsContainer.classList.remove('hidden');
        aiResults.innerHTML = '';
        aiLoading.classList.remove('hidden');
        
        try {
            const response = await fetch(`api/suggest_diagnosis_ai.php?appointment_id=${window.appointmentId}&patient_id=${window.patientId}`);
            const data = await response.json();
            
            aiLoading.classList.add('hidden');
            
            if (data.success && data.suggestions.length > 0) {
                renderAiSuggestions(data.suggestions);
            } else {
                aiResults.innerHTML = '<div class="text-sm text-gray-500 text-center">No suggestions found based on current data.</div>';
            }
        } catch (error) {
            console.error(error);
            aiLoading.classList.add('hidden');
            aiResults.innerHTML = '<div class="text-sm text-red-500 text-center">Failed to load suggestions.</div>';
        }
    });

    closeAiSuggestions.addEventListener('click', () => {
        aiSuggestionsContainer.classList.add('hidden');
    });

    function renderAiSuggestions(suggestions) {
        aiResults.innerHTML = '';
        suggestions.forEach(item => {
            const div = document.createElement('div');
            div.className = 'bg-white p-3 rounded border border-purple-100 hover:border-purple-300 cursor-pointer transition shadow-sm group';
            div.innerHTML = `
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-bold text-purple-700 flex items-center">
                            ${item.code}
                            <span class="ml-2 text-xs bg-purple-100 text-purple-800 px-1.5 py-0.5 rounded font-normal">AI Match</span>
                        </div>
                        <div class="text-sm text-gray-800 font-medium">${item.description}</div>
                        <div class="text-xs text-gray-500 mt-1 italic">"${item.reason}"</div>
                    </div>
                    <button class="text-purple-600 opacity-0 group-hover:opacity-100 transition">
                        <i data-lucide="plus-circle" class="w-5 h-5"></i>
                    </button>
                </div>
            `;
            
            div.addEventListener('click', () => {
                // Populate the search/add form
                selectDiagnosis({ code: item.code, description: item.description });
                // Optional: Auto-fill the note with the reason
                diagnosisNote.value = `AI Suggestion: ${item.reason}`;
                // Scroll to form
                document.getElementById('icdSearchInput').scrollIntoView({ behavior: 'smooth' });
            });
            
            aiResults.appendChild(div);
        });
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    // --- 2. Add / Update Logic ---
    addBtn.addEventListener('click', async () => {
        let success = false;

        if (isEditing) {
            // --- UPDATE FLOW ---
            success = await updateDiagnosis();
            if (success) cancelEdit();
        } else {
            // --- ADD FLOW ---
            let finalCode, finalDesc;

            if (isManualMode) {
                finalCode = manualCodeInput.value.trim();
                finalDesc = manualDescInput.value.trim();
                if (!finalCode || !finalDesc) return;
            } else {
                if (!selectedDiagnosis) return;
                finalCode = selectedDiagnosis.code;
                finalDesc = selectedDiagnosis.description;
            }

            if (currentDiagnosesCodes.has(finalCode)) {
                alert(`Diagnosis code ${finalCode} is already added to this visit.`);
                return;
            }

            success = await saveDiagnosisToVisit(finalCode, finalDesc, woundSelect.value, diagnosisNote.value);

            if (success) {
                // Reset UI based on mode
                if (isManualMode) {
                    manualCodeInput.value = '';
                    manualDescInput.value = '';
                    diagnosisNote.value = '';
                    woundSelect.value = '';
                    
                    // Reset button visual state
                    addBtn.innerHTML = '<i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add to Visit';
                    addBtn.disabled = true;
                    if(typeof lucide !== 'undefined') lucide.createIcons();
                } else {
                    cancelEdit(); // Resets search form
                }
            }
        }
    });

    // Save Function (Create)
    async function saveDiagnosisToVisit(code, desc, woundId, notes) {
        const payload = {
            action: 'add',
            appointment_id: window.appointmentId,
            patient_id: window.patientId,
            user_id: window.userId,
            icd10_code: code,
            description: desc,
            wound_id: woundId,
            notes: notes,
            is_primary: 0
        };
        return await sendApiRequest(payload);
    }

    // Update Function (Edit)
    async function updateDiagnosis() {
        if (!editingDiagnosisId) return false;

        const payload = {
            action: 'update',
            diagnosis_id: editingDiagnosisId,
            wound_id: woundSelect.value,
            notes: diagnosisNote.value
        };
        return await sendApiRequest(payload);
    }

    // Generic API Sender
    async function sendApiRequest(payload) {
        const originalText = addBtn.innerHTML;
        let success = false;

        try {
            // Show loading state
            addBtn.disabled = true;
            addBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin mr-2"></i> Saving...';
            if(typeof lucide !== 'undefined') lucide.createIcons();

            const response = await fetch('api/save_diagnosis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const res = await response.json();

            if (res.success) {
                loadData();
                success = true;
                showToast(res.message || "Saved successfully", 'success');
            } else {
                alert("Error: " + res.message);
            }
        } catch (error) {
            console.error(error);
            alert("Failed to save.");
        } finally {
            // If failed, restore button immediately so user can retry
            if (!success) {
                addBtn.innerHTML = originalText;
                addBtn.disabled = false;
                if(typeof lucide !== 'undefined') lucide.createIcons();
            }
        }
        return success;
    }

    // --- 3. Load Data (Current & History) ---
    async function loadData() {
        try {
            const response = await fetch(`api/get_diagnosis_data.php?appointment_id=${window.appointmentId}&patient_id=${window.patientId}`);
            const json = await response.json();

            if (json.success) {
                renderTable(json.data);
                renderHistory(json.history);
            }
        } catch (error) {
            console.error(error);
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-red-500 py-4">Error loading data.</td></tr>';
        }
    }

    function renderTable(data) {
        currentDiagnosesCodes.clear();

        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-gray-400 py-8">No diagnoses added yet.</td></tr>';
            return;
        }

        tableBody.innerHTML = '';
        data.forEach(row => {
            currentDiagnosesCodes.add(row.icd10_code);

            const tr = document.createElement('tr');

            let woundDisplay = '<span class="text-gray-400 italic">General</span>';
            if (row.wound_id && row.wound_location) {
                woundDisplay = `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    ${row.wound_location}
                                </span>`;
            }

            const checked = row.is_primary == 1 ? 'checked' : '';
            // Safe encoding for passing to JS function
            const safeRow = encodeURIComponent(JSON.stringify(row));

            tr.innerHTML = `
                <td class="px-4 py-4 whitespace-nowrap text-center">
                    <input type="radio" name="primary_diagnosis" 
                           class="h-4 w-4 text-indigo-600 border-gray-300 cursor-pointer"
                           ${checked}
                           onchange="setPrimary(${row.visit_diagnosis_id})">
                </td>
                <td class="px-4 py-4 text-sm text-gray-900">
                    <div class="font-bold">${row.icd10_code}</div>
                    <div class="text-gray-500 text-xs">${row.description}</div>
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-sm">
                    ${woundDisplay}
                </td>
                <td class="px-4 py-4 text-sm text-gray-600">
                    ${row.notes || '<span class="text-gray-300">-</span>'}
                </td>
                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <button onclick="startEditing('${safeRow}')" class="text-indigo-600 hover:text-indigo-900 mr-3" title="Edit Comment/Link">
                        <i data-lucide="edit-2" class="w-4 h-4"></i>
                    </button>
                    <button onclick="deleteDiagnosis(${row.visit_diagnosis_id})" class="text-red-600 hover:text-red-900" title="Delete">
                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                    </button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    // --- 4. Edit Logic (New) ---

    window.startEditing = (encodedRow) => {
        const row = JSON.parse(decodeURIComponent(encodedRow));

        isEditing = true;
        editingDiagnosisId = row.visit_diagnosis_id;

        // Ensure we are in Search Mode view for editing (uses preview div)
        isManualMode = false;
        searchModeContainer.classList.remove('hidden');
        manualModeContainer.classList.add('hidden');

        // 1. Pre-fill Form
        previewCode.textContent = row.icd10_code;
        previewDesc.textContent = row.description;
        previewDiv.classList.remove('hidden');

        woundSelect.value = row.wound_id || "";
        diagnosisNote.value = row.notes || "";

        // 2. Change Button State
        addBtn.innerHTML = '<i data-lucide="save" class="w-5 h-5 mr-2"></i> Update Diagnosis';
        addBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
        addBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        addBtn.disabled = false;

        // 3. Disable search to prevent changing code (logic constraint)
        searchInput.disabled = true;
        searchInput.placeholder = "Editing active... (Cancel to search new)";

        // 4. Scroll to top
        document.querySelector('main').scrollTop = 0;
        if(typeof lucide !== 'undefined') lucide.createIcons();
    };

    window.cancelEdit = () => {
        isEditing = false;
        editingDiagnosisId = null;

        // Reset UI
        previewDiv.classList.add('hidden');
        woundSelect.value = "";
        diagnosisNote.value = "";
        searchInput.value = "";
        searchInput.disabled = false;
        searchInput.placeholder = "Type code (e.g. E11) or description (e.g. Diabetes)...";
        selectedDiagnosis = null;

        // Reset Button
        addBtn.innerHTML = '<i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add to Visit';
        addBtn.classList.remove('bg-amber-600', 'hover:bg-amber-700');
        addBtn.classList.add('bg-green-600', 'hover:bg-green-700');
        addBtn.disabled = true;

        if(typeof lucide !== 'undefined') lucide.createIcons();
    };

    // --- 5. Other Actions ---

    function renderHistory(data) {
        historyList.innerHTML = '';

        if (!data || data.length === 0) {
            historyList.innerHTML = '<div class="text-center text-gray-400 text-sm py-4">No historical diagnoses found.</div>';
            historyActions.classList.add('hidden');
            return;
        }

        historyActions.classList.remove('hidden');

        data.forEach((item, index) => {
            const isAdded = currentDiagnosesCodes.has(item.icd10_code);

            const div = document.createElement('div');
            div.className = `bg-gray-50 p-3 rounded border border-gray-200 flex items-start gap-3 group hover:border-indigo-300 transition ${isAdded ? 'opacity-50' : ''}`;
            const safeDesc = item.description.replace(/'/g, "\\'");

            const disabledAttr = isAdded ? 'disabled' : '';
            const pointerClass = isAdded ? 'cursor-not-allowed' : 'cursor-pointer';

            let woundBadge = '';
            if (item.wound_id && item.wound_location) {
                woundBadge = `<span class="block mt-1 text-xs font-medium text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded inline-block">
                                Linked to: ${item.wound_location}
                               </span>`;
            }

            div.innerHTML = `
                <div class="flex items-center h-full pt-1">
                     <input type="checkbox" class="history-checkbox h-4 w-4 text-indigo-600 border-gray-300 rounded ${pointerClass}" 
                            data-code="${item.icd10_code}" 
                            data-desc="${safeDesc}"
                            data-wound="${item.wound_id || ''}"
                            ${disabledAttr}>
                </div>
                <div class="flex-grow">
                    <div class="font-medium text-sm text-gray-800 flex items-center justify-between">
                        <span>${item.icd10_code}</span>
                        ${isAdded ? '<span class="text-xs bg-green-100 text-green-800 px-1.5 py-0.5 rounded">Added</span>' : ''}
                    </div>
                    <div class="text-xs text-gray-500 line-clamp-2">${item.description}</div>
                    ${woundBadge}
                </div>
                <button class="text-indigo-600 hover:text-indigo-800 p-1 opacity-60 group-hover:opacity-100 transition ${pointerClass}" 
                        onclick="addSingleHistory('${item.icd10_code}', '${safeDesc}', '${item.wound_id || ''}')"
                        title="Add to Current Visit"
                        ${disabledAttr}>
                    <i data-lucide="plus-circle" class="w-5 h-5"></i>
                </button>
            `;
            historyList.appendChild(div);
        });

        document.querySelectorAll('.history-checkbox').forEach(cb => {
            if (!cb.disabled) {
                cb.addEventListener('change', updateBulkButtonState);
            }
        });

        if(typeof lucide !== 'undefined') lucide.createIcons();
    }

    selectAllHistory.addEventListener('change', (e) => {
        const checkboxes = document.querySelectorAll('.history-checkbox:not([disabled])');
        checkboxes.forEach(cb => cb.checked = e.target.checked);
        updateBulkButtonState();
    });

    function updateBulkButtonState() {
        const checkedCount = document.querySelectorAll('.history-checkbox:checked').length;
        addSelectedHistoryBtn.disabled = checkedCount === 0;
        addSelectedHistoryBtn.textContent = checkedCount > 0 ? `Add Selected (${checkedCount})` : 'Add Selected';
    }

    addSelectedHistoryBtn.addEventListener('click', async () => {
        const checkboxes = document.querySelectorAll('.history-checkbox:checked');
        if(checkboxes.length === 0) return;

        addSelectedHistoryBtn.disabled = true;
        addSelectedHistoryBtn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Adding...';
        if(typeof lucide !== 'undefined') lucide.createIcons();

        for (const cb of checkboxes) {
            const code = cb.dataset.code;
            const desc = cb.dataset.desc;
            const wound = cb.dataset.wound;
            await saveDiagnosisToVisit(code, desc, wound, "");
        }

        selectAllHistory.checked = false;
        loadData();
        showToast(`Added ${checkboxes.length} diagnoses from history`, 'success');
    });


    window.deleteDiagnosis = async (id) => {
        if (!confirm("Remove this diagnosis?")) return;
        try {
            const response = await fetch('api/save_diagnosis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', diagnosis_id: id })
            });
            const res = await response.json();
            if (res.success) {
                loadData();
                showToast("Diagnosis removed", 'success');
            }
        } catch (e) { console.error(e); }
    };

    window.setPrimary = async (id) => {
        try {
            const response = await fetch('api/save_diagnosis.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_primary',
                    diagnosis_id: id,
                    appointment_id: window.appointmentId
                })
            });
            const res = await response.json();
            if (!res.success) loadData();
            else showToast("Primary diagnosis updated", 'success');
        } catch (e) { console.error(e); loadData(); }
    };

    window.addSingleHistory = async (code, desc, woundId) => {
        if (currentDiagnosesCodes.has(code)) {
            alert(`Diagnosis ${code} is already added.`);
            return;
        }
        await saveDiagnosisToVisit(code, desc, woundId, "");
        loadData();
    };

    // Initial Load
    loadData();
});