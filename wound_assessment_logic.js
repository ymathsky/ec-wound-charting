// Filename: wound_assessment_logic.js
// UPDATED: Removed Graft Audit Modal logic.
// UPDATED: Handles "Apply Graft" link enabling/disabling.

// --- GLOBAL UI HELPERS ---

window.showConfirmation = function(message, title = "Confirm Action", confirmBtnClass = "bg-red-600") {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmationModal');
        const modalTitle = document.getElementById('confirmationModalTitle');
        const modalMessage = document.getElementById('confirmationMessage');
        const confirmBtn = document.getElementById('confirmActionBtn');
        const cancelBtn = document.getElementById('cancelConfirmBtn');

        if (!modal || !modalTitle || !modalMessage || !confirmBtn || !cancelBtn) {
            console.error("Confirmation modal elements missing!");
            resolve(confirm(message));
            return;
        }

        modalTitle.textContent = title;
        modalMessage.textContent = message;
        
        confirmBtn.className = `px-4 py-2 text-white font-bold rounded hover:bg-opacity-90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 ${confirmBtnClass}`;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        const cleanup = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
        };

        confirmBtn.onclick = () => {
            cleanup();
            resolve(true);
        };

        cancelBtn.onclick = () => {
            cleanup();
            resolve(false);
        };
    });
};

window.showPrompt = function(message, defaultValue = "") {
    return new Promise((resolve) => {
        const modal = document.getElementById('promptModal');
        const modalTitle = document.getElementById('promptModalTitle');
        const modalMessage = document.getElementById('promptMessage');
        const input = document.getElementById('promptInput');
        const confirmBtn = document.getElementById('confirmPromptBtn');
        const cancelBtn = document.getElementById('cancelPromptBtn');

        if (!modal || !input || !confirmBtn || !cancelBtn) {
            console.error("Prompt modal elements missing!");
            resolve(prompt(message, defaultValue));
            return;
        }

        if (modalTitle) modalTitle.textContent = "Input Required";
        if (modalMessage) modalMessage.textContent = message;
        input.value = defaultValue;

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        input.focus();

        const cleanup = () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            confirmBtn.onclick = null;
            cancelBtn.onclick = null;
            // Remove enter key listener if added? 
            // Ideally we'd handle Enter key too.
        };

        const handleConfirm = () => {
            const val = input.value;
            cleanup();
            resolve(val);
        };

        const handleCancel = () => {
            cleanup();
            resolve(null);
        };

        confirmBtn.onclick = handleConfirm;
        cancelBtn.onclick = handleCancel;
        
        // Handle Enter key
        input.onkeydown = (e) => {
            if (e.key === 'Enter') handleConfirm();
            if (e.key === 'Escape') handleCancel();
        };
    });
};

window.showFloatingAlert = function(message, type = 'info') {
    const container = document.getElementById('floating-alert-container');
    if (!container) return;

    const alertDiv = document.createElement('div');
    alertDiv.className = `floating-alert ${type}`;
    
    let icon = '';
    if (type === 'success') icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
    else if (type === 'error') icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
    else icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';

    alertDiv.innerHTML = `
        ${icon}
        <div class="flex-1 text-sm font-medium">${message}</div>
        <button type="button" class="ml-3 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="this.parentElement.remove()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;

    container.appendChild(alertDiv);

    setTimeout(() => {
        if (alertDiv.parentElement) {
            alertDiv.classList.add('fade-out');
            alertDiv.addEventListener('animationend', () => {
                if (alertDiv.parentElement) alertDiv.remove();
            });
        }
    }, 5000);
};

document.addEventListener('DOMContentLoaded', function() {
    const woundId = new URLSearchParams(window.location.search).get('id');
    const appointmentId = new URLSearchParams(window.location.search).get('appointment_id');
    // Global State
    let patientId = 0; // CRITICAL: This is set during fetchWoundDetails
    let currentAssessmentId = 0;
    let allAssessmentsHistory = []; // Stores all past assessments for "Copy" feature
    let allImagesGlobal = []; // Stores all images for lookup
    window.currentPhotoFile = null; // Used for AI/Manual measurement modals
    window.ManualMeasurementResults = {}; // Global object to hold results from manual_measurement_logic.js
    // --- DOM ELEMENTS ---
    const woundHeader = document.getElementById('wound-header');
    const historyContainer = document.getElementById('assessment-history-container');
    const imageGallery = document.getElementById('image-gallery');
    const assessmentCard = document.getElementById('assessment-card');
    const assessmentForm = document.getElementById('assessmentForm');
    const finalizeAssessmentBtn = document.getElementById('saveAssessmentBtn');
    const assessmentFormIdInput = document.getElementById('assessment_form_id');
    const formMessage = document.getElementById('form-message');
    const uploadMessage = document.getElementById('upload-message');
    const patientIdInput = document.getElementById('patient_id'); // Hidden input in form
    // Copy Button
    const copyLastAssessmentBtn = document.getElementById('copyLastAssessmentBtn');
    // Apply Graft Link (now just a link, not a modal button)
    const applyGraftBtn = document.getElementById('applyGraftBtn');
    // Photo/Capture Elements
    const photoFileInput = document.getElementById('wound_photo');
    const imagePreview = document.getElementById('preview-img');
    const captureStatus = document.getElementById('capture-status');
    const toggleFileBtn = document.getElementById('toggleFileBtn');
    const toggleCameraBtn = document.getElementById('toggleCameraBtn');
    const captureControls = document.getElementById('capture-controls');
    // Modals & Buttons
    const viewAssessmentModal = document.getElementById('viewAssessmentModal');
    const viewAssessmentContent = document.getElementById('viewAssessmentContent');
    const closeViewModalBtn = document.getElementById('closeViewModalBtn');
    const openAIMeasureModalBtn = document.getElementById('openAIMeasureModalBtn');
    const openManualMeasureModalBtn = document.getElementById('openManualMeasureModalBtn');
    const closeManualModalBtn = document.getElementById('closeManualModalBtn');
    // Tab Buttons
    const tabBtnAssessment = document.getElementById('tabBtn-assessment');
    const tabBtnHistory = document.getElementById('tabBtn-history');
    const tabBtnGallery = document.getElementById('tabBtn-gallery');
    // Tab Containers
    const tabAssessment = document.getElementById('tab-assessment');
    const tabHistory = document.getElementById('tab-history');
    const tabGallery = document.getElementById('tab-gallery');
    // AI Modal Elements
    const aiMeasurementModal = document.getElementById('aiMeasurementModal');
    const closeAIMeasureModalBtn = document.getElementById('closeAIMeasureModalBtn');
    const aiMeasureSubmitBtn = document.getElementById('aiMeasureSubmitBtn');
    const aiResultsDiv = document.getElementById('ai-results');
    const useAIMeasurementsBtn = document.getElementById('useAIMeasurementsBtn');
    const aiImagePreview = document.getElementById('ai-image-preview');
    const aiPlaceholderText = document.getElementById('ai-placeholder-text');
    const aiMeasureForm = document.getElementById('aiMeasureForm');
    // Treatment Plan Elements
    const generatePlanBtn = document.getElementById('generatePlanBtn');
    const treatmentsProvidedTextarea = document.getElementById('treatments_provided');
    // Autosave status element
    const autosaveStatus = document.getElementById('autosave-status');
    // Measurement Fields
    const measurementFields = ['length_cm', 'width_cm', 'depth_cm'];
    const measInputs = measurementFields.map(id => document.getElementById(id));
    // --- API ENDPOINTS (Centralized for Maintainability) ---
    const API_ENDPOINTS = {
        CREATE_ASSESSMENT: 'api/create_assessment.php',
        UPLOAD_PHOTO: 'api/upload_wound_photo.php',
        GET_WOUND_DETAILS: `api/get_wound_details.php?id=${woundId}`,
        GET_ACTIVE_ASSESSMENT: `api/get_current_assessment_by_visit.php?wound_id=${woundId}&appointment_id=${appointmentId}`,
        GET_CHART_DATA: `api/get_wound_progress_data.php?wound_id=${woundId}`,
        GET_ASSESSMENT_DETAILS: `api/get_assessment_details.php`,
        DELETE_PHOTO: 'api/delete_wound_photo.php',
        DELETE_ASSESSMENT: 'api/delete_assessment.php',
        DELETE_WOUND: 'api/delete_wound.php',
        GENERATE_PLAN: 'api/generate_treatment_plan.php',
        AUTO_MEASURE_WOUND: 'api/auto_measure_wound.php',
    };
    // --- UI/STATUS HELPERS ---
    function showConfirmation(message, title = "Confirm Action", confirmBtnClass = "bg-red-600") {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmationModal');
            const modalTitle = document.getElementById('confirmationModalTitle');
            const modalMessage = document.getElementById('confirmationMessage');
            const confirmBtn = document.getElementById('confirmActionBtn');
            const cancelBtn = document.getElementById('cancelConfirmBtn');
            if (!modal || !modalTitle || !modalMessage || !confirmBtn || !cancelBtn) {
                console.error("Confirmation modal elements missing!");
                // Fallback to native confirm if modal is broken
                resolve(confirm(message));
                return;
            }
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            
            // Reset classes and apply new color
            confirmBtn.className = `px-4 py-2 text-white font-bold rounded hover:bg-opacity-90 transition focus:outline-none focus:ring-2 focus:ring-offset-2 ${confirmBtnClass}`;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            const cleanup = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                // Remove listeners to prevent stacking
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
            };
            confirmBtn.onclick = () => {
                cleanup();
                resolve(true);
            };
            cancelBtn.onclick = () => {
                cleanup();
                resolve(false);
            };
        });
    }
    function showPrompt(message, defaultValue = "") {
        return new Promise((resolve) => {
            const modal = document.getElementById('promptModal');
            const modalTitle = document.getElementById('promptModalTitle');
            const modalMessage = document.getElementById('promptMessage');
            const input = document.getElementById('promptInput');
            const confirmBtn = document.getElementById('confirmPromptBtn');
            const cancelBtn = document.getElementById('cancelPromptBtn');
            if (!modal || !input || !confirmBtn || !cancelBtn) {
                console.error("Prompt modal elements missing!");
                resolve(prompt(message, defaultValue));
                return;
            }
            if (modalTitle) modalTitle.textContent = "Input Required";
            if (modalMessage) modalMessage.textContent = message;
            input.value = defaultValue;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            input.focus();
            const cleanup = () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                confirmBtn.onclick = null;
                cancelBtn.onclick = null;
                // Remove enter key listener if added? 
                // Ideally we'd handle Enter key too.
            };
            const handleConfirm = () => {
                const val = input.value;
                cleanup();
                resolve(val);
            };
            const handleCancel = () => {
                cleanup();
                resolve(null);
            };
            confirmBtn.onclick = handleConfirm;
            cancelBtn.onclick = handleCancel;
            
            // Handle Enter key
            input.onkeydown = (e) => {
                if (e.key === 'Enter') handleConfirm();
                if (e.key === 'Escape') handleCancel();
            };
        });
    }
    function showFloatingAlert(message, type = 'info') {
        const container = document.getElementById('floating-alert-container');
        if (!container) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = `floating-alert ${type}`;
        
        let icon = '';
        if (type === 'success') icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        else if (type === 'error') icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        else icon = '<svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';
        alertDiv.innerHTML = `
            ${icon}
            <div class="flex-1 text-sm font-medium">${message}</div>
            <button type="button" class="ml-3 text-gray-400 hover:text-gray-600 focus:outline-none" onclick="this.parentElement.remove()">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        `;
        container.appendChild(alertDiv);
        // Auto dismiss
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.classList.add('fade-out');
                alertDiv.addEventListener('animationend', () => {
                    if (alertDiv.parentElement) alertDiv.remove();
                });
            }
        }, 5000);
    }
    // --- CORE DATA HANDLING ---
    // Debounce function
    const debounce = (func, delay) => {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    };
    // Autosave function
    const debouncedSave = debounce(async () => {
        if (window.isVisitSigned) return;
        
        const formData = new FormData(assessmentForm);
        const data = Object.fromEntries(formData.entries());
        
        // Handle multi-selects
        data.signs_of_infection = Array.from(document.getElementById('signs_of_infection').selectedOptions).map(opt => opt.value);
        data.periwound_condition = Array.from(document.getElementById('periwound_condition').selectedOptions).map(opt => opt.value);
        data.exposed_structures = Array.from(document.querySelectorAll('input[name="exposed_structures[]"]:checked')).map(cb => cb.value);
        
        // Handle Dynamic Locations
        data.tunneling_locations = collectLocationData('tunneling');
        data.undermining_locations = collectLocationData('undermining');

        if (autosaveStatus) autosaveStatus.textContent = 'Saving...';
        try {
            const response = await fetch(API_ENDPOINTS.CREATE_ASSESSMENT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();
            
            if (result.success) {
                if (autosaveStatus) {
                    autosaveStatus.textContent = 'Saved ' + new Date().toLocaleTimeString();
                    setTimeout(() => autosaveStatus.textContent = '', 3000);
                }
                showFloatingAlert('Autosave successful', 'success');
                
                const isNew = !currentAssessmentId && result.assessment_id;
                
                if (result.assessment_id) {
                    currentAssessmentId = result.assessment_id;
                    if (assessmentFormIdInput) assessmentFormIdInput.value = currentAssessmentId;
                }
                
                // If we just created a new assessment, we MUST refresh the history/images list
                // so that findAssessmentIdByType works correctly if the user switches types.
                if (isNew) {
                    // We call fetchWoundDetails but we don't want to disrupt the user's typing.
                    // fetchWoundDetails calls renderPage which updates the history table and gallery.
                    // It does NOT touch the form inputs, so it should be safe.
                    fetchWoundDetails();
                }
            }
        } catch (error) {
            console.error("Autosave error:", error);
            if (autosaveStatus) autosaveStatus.textContent = 'Error saving';
            showFloatingAlert('Autosave failed', 'error');
        }
    }, 1000);
    function parseMultiSelect(value) {
        if (!value) return [];
        if (Array.isArray(value)) return value;
        try {
            return JSON.parse(value);
        } catch (e) {
            return value.split(',').map(s => s.trim()).filter(s => s);
        }
    }
    function showMessage(element, message, type) {
        if (!element) return;
        element.textContent = message;
        element.className = type === 'error' ? 'text-red-600 bg-red-50 p-2 rounded' : 'text-green-600 bg-green-50 p-2 rounded';
        element.classList.remove('hidden');
        setTimeout(() => {
            element.classList.add('hidden');
        }, 5000);
    }
    function setupDynamicFormHandlers() {
        // Exposed Structures Mutual Exclusivity
        const exposedCheckboxes = document.querySelectorAll('input[name="exposed_structures[]"]');
        exposedCheckboxes.forEach(cb => {
            if (cb.dataset.hasExposedListener) return;
            cb.addEventListener('change', function() {
                if (this.value === 'None' && this.checked) {
                    exposedCheckboxes.forEach(other => {
                        if (other !== this) other.checked = false;
                    });
                } else if (this.value !== 'None' && this.checked) {
                    exposedCheckboxes.forEach(other => {
                        if (other.value === 'None') other.checked = false;
                    });
                }
                debouncedSave();
            });
            cb.dataset.hasExposedListener = 'true';
        });

        // Tunneling
        const tunnelingBtns = document.querySelectorAll('#tunneling_present_group .btn-option');
        tunnelingBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.dataset.value;
                document.getElementById('tunneling_present').value = val;
                updateButtonGroup('tunneling_present', val);
                const container = document.getElementById('tunneling_details_container');
                if (val === 'Yes') container.classList.remove('hidden');
                else container.classList.add('hidden');
                debouncedSave();
            });
        });
        // Undermining
        const underminingBtns = document.querySelectorAll('#undermining_present_group .btn-option');
        underminingBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.dataset.value;
                document.getElementById('undermining_present').value = val;
                updateButtonGroup('undermining_present', val);
                const container = document.getElementById('undermining_details_container');
                if (val === 'Yes') container.classList.remove('hidden');
                else container.classList.add('hidden');
                debouncedSave();
            });
        });
        // Debridement
        const debridementBtns = document.querySelectorAll('#debridement_performed_group .btn-option');
        debridementBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const val = btn.dataset.value;
                document.getElementById('debridement_performed').value = val;
                updateButtonGroup('debridement_performed', val);
                const container = document.getElementById('debridement_details');
                if (val === 'Yes') container.classList.remove('hidden');
                else container.classList.add('hidden');
                debouncedSave();
            });
        });
        // Generic Button Groups
        ['pain_level', 'exudate_amount', 'odor_present'].forEach(id => {
            const btns = document.querySelectorAll(`#${id}_group .btn-option`);
            btns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const val = btn.dataset.value;
                    document.getElementById(id).value = val;
                    updateButtonGroup(id, val);
                    debouncedSave();
                });
            });
        });
        // Inputs
        const inputs = assessmentForm.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', debouncedSave);
            input.addEventListener('input', debouncedSave);
        });
    }
    // Helper to update button groups
    function updateButtonGroup(inputId, value) {
        const input = document.getElementById(inputId);
        if (input) {
            // Handle null/undefined by defaulting to empty string or specific defaults
            const safeValue = value !== null && value !== undefined ? value : '';
            input.value = safeValue;
            
            const group = document.getElementById(inputId + '_group');
            if (group) {
                const buttons = group.querySelectorAll('.btn-option');
                buttons.forEach(btn => {
                    // Use loose equality to match "0" with 0
                    if (btn.dataset.value == safeValue) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
            }
        }
    }
    function populateForm(data) {
        // Populate current assessment data
        if (document.getElementById('length_cm')) document.getElementById('length_cm').value = data.length_cm || '';
        if (document.getElementById('width_cm')) document.getElementById('width_cm').value = data.width_cm || '';
        if (document.getElementById('depth_cm')) document.getElementById('depth_cm').value = data.depth_cm || '';
        if (data.assessment_date) {
            if (document.getElementById('assessment_date')) document.getElementById('assessment_date').value = data.assessment_date.substring(0, 10);
        }
        // Button Groups
        updateButtonGroup('pain_level', data.pain_level);
        updateButtonGroup('tunneling_present', data.tunneling_present || 'No');
        updateButtonGroup('undermining_present', data.undermining_present || 'No');
        updateButtonGroup('exudate_amount', data.exudate_amount);
        updateButtonGroup('odor_present', data.odor_present || 'No');
        updateButtonGroup('debridement_performed', data.debridement_performed || 'No');
        // Standard Inputs
        if (document.getElementById('granulation_percent')) document.getElementById('granulation_percent').value = data.granulation_percent || '';
        if (document.getElementById('slough_percent')) document.getElementById('slough_percent').value = data.slough_percent || '';
        if (document.getElementById('eschar_percent')) document.getElementById('eschar_percent').value = data.eschar_percent || '';
        if (document.getElementById('epithelialization_percent')) document.getElementById('epithelialization_percent').value = data.epithelialization_percent || '';
        if (document.getElementById('granulation_color')) document.getElementById('granulation_color').value = data.granulation_color || '';
        if (document.getElementById('granulation_coverage')) document.getElementById('granulation_coverage').value = data.granulation_coverage || '';
        if (document.getElementById('drainage_type')) document.getElementById('drainage_type').value = data.drainage_type || '';
        
        if (document.getElementById('debridement_type')) document.getElementById('debridement_type').value = data.debridement_type || '';
        if (document.getElementById('treatments_provided')) document.getElementById('treatments_provided').value = data.treatments_provided || '';
        // New Fields
        if (document.getElementById('risk_factors')) document.getElementById('risk_factors').value = data.risk_factors || '';
        if (document.getElementById('nutritional_status')) document.getElementById('nutritional_status').value = data.nutritional_status || '';
        if (document.getElementById('braden_score')) document.getElementById('braden_score').value = data.braden_score || '';
        if (document.getElementById('push_score')) document.getElementById('push_score').value = data.push_score || '';
        if (document.getElementById('pre_debridement_notes')) document.getElementById('pre_debridement_notes').value = data.pre_debridement_notes || '';
        if (document.getElementById('medical_necessity')) document.getElementById('medical_necessity').value = data.medical_necessity || '';
        if (document.getElementById('dvt_edema_notes')) document.getElementById('dvt_edema_notes').value = data.dvt_edema_notes || '';
        // Handle multi-selects
        const periwoundValues = parseMultiSelect(data.periwound_condition);
        if (document.getElementById('periwound_condition')) {
            Array.from(document.getElementById('periwound_condition').options).forEach(option => {
                option.selected = periwoundValues.includes(option.value);
            });
        }
        const infectionValues = parseMultiSelect(data.signs_of_infection);
        if (document.getElementById('signs_of_infection')) {
            Array.from(document.getElementById('signs_of_infection').options).forEach(option => {
                option.selected = infectionValues.includes(option.value);
            });
        }
        // Handle Checkboxes for Exposed Structures
        const exposedValues = parseMultiSelect(data.exposed_structures);
        const exposedCheckboxes = document.querySelectorAll('input[name="exposed_structures[]"]');
        exposedCheckboxes.forEach(cb => {
            cb.checked = exposedValues.includes(cb.value);
        });
        // Handle dynamic fields
        if (document.getElementById('tunneling_locations')) document.getElementById('tunneling_locations').innerHTML = '';
        if (document.getElementById('undermining_locations')) document.getElementById('undermining_locations').innerHTML = '';
        try {
            if (data.tunneling_locations && document.getElementById('tunneling_locations')) {
                const locations = typeof data.tunneling_locations === 'string' ? JSON.parse(data.tunneling_locations) : data.tunneling_locations;
                if (Array.isArray(locations)) {
                    locations.forEach(loc => addLocationField('tunneling', loc));
                }
            }
            if (data.undermining_locations && document.getElementById('undermining_locations')) {
                const locations = typeof data.undermining_locations === 'string' ? JSON.parse(data.undermining_locations) : data.undermining_locations;
                if (Array.isArray(locations)) {
                    locations.forEach(loc => addLocationField('undermining', loc));
                }
            }
        } catch(e) {
            console.error("Failed to parse dynamic location fields:", e);
        }
        // FIX 1: Ensure L/W/D fields are always ENABLED for editing and remove visual disabled state
        // UNLESS the visit is signed (read-only mode)
        if (!window.isVisitSigned) {
            measurementFields.forEach(id => {
                const input = document.getElementById(id);
                if (input) {
                    input.disabled = false;
                    input.classList.remove('bg-gray-100');
                }
            });
        }
        // *** NEW: Handle Graft Link Status ***
        if (applyGraftBtn) {
            if (data.graft_attestation_timestamp) {
                // Graft already done
                applyGraftBtn.textContent = 'Graft Completed';
                applyGraftBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                applyGraftBtn.classList.add('bg-gray-400', 'cursor-not-allowed', 'disabled');
            } else {
                // Ready for graft
                applyGraftBtn.textContent = 'Apply Graft';
                applyGraftBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                applyGraftBtn.classList.remove('bg-gray-400', 'cursor-not-allowed', 'disabled');
            }
        }
        // Manually trigger dynamic form layout logic for visibility
        setupDynamicFormHandlers();
        triggerAccordionContentVisibility();
    }
    // Utility to get the most recent non-zero measurement
    function getNearestValidMeasurement(assessments, field) {
        if (!assessments || assessments.length === 0) return '';
        const recent = assessments.find(asm => parseFloat(asm[field]) > 0);
        return recent ? parseFloat(recent[field]) : '';
    }
    // Helper to handle Treatment Plan availability based on Image Type (Placeholder)
    function updateTreatmentPlanState() {
        // Logic to enable/disable fields based on image type can go here.
        // For now, we just ensure it exists to prevent errors.
        const imgType = document.getElementById('image_type');
        if (!imgType) return;
        // console.log("Image Type Changed:", imgType.value);
    }
    // NEW: Helper to load assessment for editing
    async function loadAssessmentForEditing(assessmentId) {
        console.log(`Loading assessment ${assessmentId} for editing...`);
        try {
            // 1. Fetch details
            const response = await fetch(`${API_ENDPOINTS.GET_ASSESSMENT_DETAILS}?id=${assessmentId}`);
            if (!response.ok) throw new Error('Failed to fetch assessment details.');
            const data = await response.json();
            
            console.log("Assessment data loaded:", data);

            if (!data || !data.assessment_id) {
                throw new Error("Invalid assessment data received.");
            }

            // 2. Set as current
            currentAssessmentId = data.assessment_id;
            if (assessmentFormIdInput) assessmentFormIdInput.value = currentAssessmentId;
            
            // 3. Populate Form
            populateForm(data);
            
            // 3b. Load Image
            await loadImageForAssessment(assessmentId);
            
            // 4. Switch to Assessment Tab
            switchTab('assessment');
            
            // 5. Notify User
            if (!data.pain_level && !data.drainage_type && !data.exudate_amount) {
                 showMessage(formMessage, `Loaded Assessment #${assessmentId}. Note: Some AI fields may be empty.`, 'warning');
                 showFloatingAlert(`Loaded Assessment #${assessmentId}. Note: Some AI fields may be empty.`, 'warning');
            } else {
                 showMessage(formMessage, `Loaded Assessment #${assessmentId} for editing.`, 'success');
                 showFloatingAlert(`Loaded Assessment #${assessmentId} for editing.`, 'success');
            }
            
            // 6. Update Autosave status
            if (autosaveStatus) {
                autosaveStatus.textContent = 'Editing loaded assessment...';
            }
            
            // 7. Enable tools if not signed
            if (!window.isVisitSigned) {
                if (typeof enableMeasurementTools === 'function') enableMeasurementTools();
                if (assessmentCard) assessmentCard.classList.remove('assessment-disabled');
            }
        } catch (error) {
            showMessage(uploadMessage, error.message, 'error');
            showFloatingAlert(error.message, 'error');
            console.error("Edit Assessment Error:", error);
        }
    }

    // Expose to window for onclick handlers
    window.loadAssessmentForEditing = loadAssessmentForEditing;

    window.deleteAssessment = async function(assessmentId) {
        const confirmed = await showConfirmation('Are you sure you want to delete this assessment? This action cannot be undone.', 'Delete Assessment', 'bg-red-600');
        if (!confirmed) return;

        try {
            const response = await fetch(API_ENDPOINTS.DELETE_ASSESSMENT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ assessment_id: assessmentId })
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            
            showFloatingAlert(result.message, 'success');
            
            // Close modal if open
            const modal = document.getElementById('viewAssessmentModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
            
            // Refresh data
            fetchWoundDetails();
        } catch (error) {
            showFloatingAlert(`Error: ${error.message}`, 'error');
            console.error("Delete Assessment Error:", error);
        }
    };

    // NEW: Helper to load image for a specific assessment
    async function loadImageForAssessment(assessmentId) {
        if (!allImagesGlobal || allImagesGlobal.length === 0) {
            console.warn("No images available in global cache.");
            return;
        }
        
        const relatedImage = allImagesGlobal.find(img => img.assessment_id == assessmentId);
        
        const imagePreview = document.getElementById('preview-img');
        const container = document.getElementById('imagePreview');
        const captureStatus = document.getElementById('capture-status');
        if (relatedImage) {
            console.log("Loading image for assessment:", relatedImage);
            if (imagePreview) {
                imagePreview.src = relatedImage.image_path;
                imagePreview.classList.remove('hidden');
                
                if (container) {
                    const span = container.querySelector('span');
                    if (span) span.classList.add('hidden');
                }
                
                if (document.getElementById('image_type')) {
                    document.getElementById('image_type').value = relatedImage.image_type;
                    updateTreatmentPlanState();
                }
                // Fetch blob for tools so AI/Manual measurement works
                try {
                    const imgRes = await fetch(relatedImage.image_path);
                    const imgBlob = await imgRes.blob();
                    const filename = relatedImage.image_path.split('/').pop();
                    const file = new File([imgBlob], filename, { type: imgBlob.type });
                    window.currentPhotoFile = file;
                    
                    if (captureStatus) captureStatus.classList.add('hidden');
                } catch (err) {
                    console.error("Failed to load image blob for tools:", err);
                }
            }
        } else {
            // Reset if no image found for this assessment
            if (imagePreview) {
                imagePreview.src = '';
                imagePreview.classList.add('hidden');
            }
            if (container) {
                const span = container.querySelector('span');
                if (span) span.classList.remove('hidden');
            }
            window.currentPhotoFile = null;
        }
    }
    // --- LOAD ACTIVE ASSESSMENT & INITIAL DATA ---
    async function loadActiveAssessment(assessments) {
        try {
            if (!appointmentId || !woundId) { return; }
            const response = await fetch(API_ENDPOINTS.GET_ACTIVE_ASSESSMENT);
            const result = await response.json();
            if (response.ok && result.success && result.assessment) {
                const data = result.assessment;
                currentAssessmentId = data.assessment_id;
                if (assessmentFormIdInput) assessmentFormIdInput.value = currentAssessmentId;
                populateForm(data);
                await loadImageForAssessment(currentAssessmentId);
                if (assessmentCard) assessmentCard.classList.remove('assessment-disabled');
            }
        } catch (error) {
            console.error("Error loading active assessment:", error);
        }
    }
    async function fetchWoundDetails() {
        try {
            const response = await fetch(API_ENDPOINTS.GET_WOUND_DETAILS);
            const data = await response.json();
            
            if (data.success) {
                patientId = data.patient.patient_id;
                if (patientIdInput) patientIdInput.value = patientId;
                
                allAssessmentsHistory = data.assessments;
                allImagesGlobal = data.images;
                
                renderPage(data);
                
                // Check for assessment_id in URL
                const urlAssessmentId = new URLSearchParams(window.location.search).get('assessment_id');
                if (urlAssessmentId) {
                    await loadAssessmentForEditing(urlAssessmentId);
                } else {
                    loadActiveAssessment(data.assessments);
                }
            } else {
                showMessage(uploadMessage, 'Failed to load wound details.', 'error');
                showFloatingAlert('Failed to load wound details.', 'error');
            }
        } catch (error) {
            console.error("Error fetching wound details:", error);
            showMessage(uploadMessage, 'Error loading wound data.', 'error');
            showFloatingAlert('Error loading wound data.', 'error');
        }
    }
    function renderImageGallery(images, assessments) {
        if (!images || images.length === 0) {
            imageGallery.innerHTML = '<p class="text-center text-gray-500 py-8 col-span-full">No photos uploaded.</p>';
            return;
        }
        imageGallery.innerHTML = images.map(img => {
            const dateObj = new Date(img.uploaded_at);
            const dateStr = dateObj.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
            
            return `
            <div class="bg-white rounded-lg shadow-md overflow-hidden border border-gray-200 flex flex-col hover:shadow-lg transition-shadow duration-200">
                <!-- Image Area -->
                <div class="relative h-48 bg-gray-100 group cursor-pointer" onclick="window.open('${img.image_path}', '_blank')">
                    <img src="${img.image_path}" alt="${img.image_type}" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" onerror="this.onerror=null;this.src='https://placehold.co/200x200/cccccc/333333?text=Image+Error';">
                    <!-- Badge -->
                    <span class="absolute top-2 right-2 bg-black bg-opacity-60 text-white text-xs font-bold px-2 py-1 rounded backdrop-blur-sm">
                        ${img.image_type}
                    </span>
                </div>
                
                <!-- Content Area -->
                <div class="p-4 flex-grow flex flex-col">
                    <div class="mb-4">
                        <div class="text-lg font-bold text-gray-900">${dateStr}</div>
                        <div class="text-xs text-gray-500">ID: ${img.assessment_id || img.image_id}</div>
                    </div>
                    
                    <div class="mt-auto space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <button onclick="window.open('${img.image_path}', '_blank')" class="flex items-center justify-center px-3 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                View
                            </button>
                            ${img.assessment_id ? 
                            `<button data-assessment-id="${img.assessment_id}" class="edit-assessment-btn flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                Load
                            </button>` : 
                            `<button disabled class="flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-gray-400 bg-gray-100 cursor-not-allowed">
                                Load
                            </button>`
                            }
                        </div>
                        <button data-image-id="${img.image_id}" class="delete-photo-btn w-full flex items-center justify-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 transition" ${window.isVisitSigned ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            Delete
                        </button>
                    </div>
                </div>
            </div>`;
        }).join('');
    }

    function renderHistoryTable(assessments, images) {
        if (!assessments || assessments.length === 0) {
            historyContainer.innerHTML = '<div class="p-4 text-gray-500 text-center">No assessment history found.</div>';
            return;
        }

        // Group by Appointment ID
        const groups = {};
        assessments.forEach(asm => {
            const key = asm.appointment_id ? `appt_${asm.appointment_id}` : 'other';
            if (!groups[key]) {
                groups[key] = {
                    id: asm.appointment_id,
                    date: asm.assessment_date, // Use the first encountered date as group date
                    items: []
                };
            }
            groups[key].items.push(asm);
        });

        // Sort groups by date desc
        const sortedKeys = Object.keys(groups).sort((a, b) => {
            return new Date(groups[b].date) - new Date(groups[a].date);
        });

        let html = '<div class="overflow-hidden border-b border-gray-200 shadow sm:rounded-lg"><table class="min-w-full divide-y divide-gray-200">';
        
        // Table Header
        html += `
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image Type</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dimensions</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tissue %</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Drainage</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="relative px-6 py-3"><span class="sr-only">View</span></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
        `;

        sortedKeys.forEach(key => {
            const group = groups[key];
            const groupDate = new Date(group.date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            const groupTitle = group.id ? `Appointment #${group.id} | ${groupDate}` : `Other Assessments | ${groupDate}`;

            // Group Header Row
            html += `
                <tr class="bg-gray-100">
                    <td colspan="7" class="px-6 py-2 text-sm font-bold text-gray-700 border-t border-b border-gray-200">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            ${groupTitle}
                        </div>
                    </td>
                </tr>
            `;

            group.items.forEach(asm => {
                const time = new Date(asm.created_at || asm.assessment_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const img = images.find(i => i.assessment_id == asm.assessment_id);
                const imgType = img ? img.image_type : (asm.treatments_provided && asm.treatments_provided.includes('Initial photo') ? 'Wound Photo' : 'Assessment');
                
                const length = parseFloat(asm.length_cm) || 0;
                const width = parseFloat(asm.width_cm) || 0;
                const depth = parseFloat(asm.depth_cm) || 0;
                const area = (length * width).toFixed(1);
                const dims = `${length.toFixed(2)} x ${width.toFixed(2)} x ${depth.toFixed(2)} cm`;
                
                const g = parseInt(asm.granulation_percent) || 0;
                const s = parseInt(asm.slough_percent) || 0;
                const e = parseInt(asm.eschar_percent) || 0;
                
                const drainageType = asm.drainage_type || '-';
                const drainageAmount = asm.exudate_amount || '-';

                html += `
                    <tr class="hover:bg-gray-50 transition border-b border-gray-100 last:border-b-0">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">${time}</div>
                            <div class="text-xs text-gray-500">ID: ${asm.assessment_id}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${imgType}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900 font-mono">${dims}</div>
                            <div class="text-xs text-gray-500">Area: ${area} cm²</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="w-32 h-2 bg-gray-200 rounded-full overflow-hidden flex mb-1">
                                <div class="bg-red-500 h-full" style="width: ${g}%" title="Granulation: ${g}%"></div>
                                <div class="bg-yellow-400 h-full" style="width: ${s}%" title="Slough: ${s}%"></div>
                                <div class="bg-black h-full" style="width: ${e}%" title="Eschar: ${e}%"></div>
                            </div>
                            <div class="text-xs text-gray-500 font-mono">G:${g}% S:${s}% E:${e}%</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">${drainageType}</div>
                            <div class="text-xs text-gray-500">${drainageAmount}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            ${!window.isVisitSigned ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>' : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Signed</span>'}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <button class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1 rounded-md transition view-assessment-btn" data-assessment-id="${asm.assessment_id}">View Details</button>
                        </td>
                    </tr>
                `;
            });
        });

        html += '</tbody></table></div>';
        historyContainer.innerHTML = html;
    }
function renderPage(data) {
woundHeader.textContent = `Wound: ${data.details.location} (${data.details.wound_type})`;
document.getElementById('patient-name-subheader').textContent = `Patient: ${data.patient.first_name} ${data.patient.last_name}`;
if (document.getElementById('wound_type_hidden')) document.getElementById('wound_type_hidden').value = data.details.wound_type || '';
if (document.getElementById('wound_location_hidden')) document.getElementById('wound_location_hidden').value = data.details.location || '';
renderHistoryTable(data.assessments, data.images);
renderImageGallery(data.images, data.assessments);
}
function populateAndShowAssessmentModal(data) {
        const V = (value) => value || 'N/A';

        // Find related image
        const relatedImage = allImagesGlobal.find(img => img.assessment_id == data.assessment_id);
        let imageHTML = '';
        if (relatedImage) {
            imageHTML = `
            <div class="mb-4 bg-white p-3 rounded border border-gray-100 shadow-sm">
                <h4 class="text-xs font-semibold text-gray-500 uppercase block mb-2">Wound Photo</h4>
                <div class="relative h-64 bg-gray-100 rounded overflow-hidden cursor-pointer group" onclick="window.open('${relatedImage.image_path}', '_blank')">
                    <img src="${relatedImage.image_path}" class="w-full h-full object-contain" alt="Wound Photo">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition flex items-center justify-center">
                        <span class="opacity-0 group-hover:opacity-100 bg-black bg-opacity-60 text-white text-xs px-2 py-1 rounded">Click to Enlarge</span>
                    </div>
                </div>
                <div class="mt-1 text-xs text-gray-500 text-center">${relatedImage.image_type}</div>
            </div>
            `;
        }

        const formatJSONList = (jsonString) => {
            const array = parseMultiSelect(jsonString);
            return array.length > 0 ? array.join(', ') : 'None';
        };
        // Helper for sections
        const SectionHeader = (title, icon) => `
        <div class="flex items-center space-x-2 mb-3 border-b pb-2 mt-4">
            ${icon ? '' : ''}
            <h4 class="text-md font-bold text-indigo-700 uppercase tracking-wide">${title}</h4>
        </div>
        `;
        const DetailRow = (label, value, fullWidth = false) => `
        <div class="${fullWidth ? 'col-span-2' : ''} mb-1">
            <span class="text-xs font-semibold text-gray-500 uppercase block">${label}</span>
            <span class="text-sm text-gray-800 font-medium">${value}</span>
        </div>
        `;
        // Tissue Bar Logic
        const g = data.granulation_percent || 0;
        const s = data.slough_percent || 0;
        const e = data.eschar_percent || 0;
        const ep = data.epithelialization_percent || 0;
        const tissueBar = `
        <div class="w-full h-4 bg-gray-200 rounded-full overflow-hidden flex mt-1 border border-gray-300">
            <div class="bg-red-500 h-full flex items-center justify-center text-[10px] text-white font-bold" style="width: ${g}%">${g > 10 ? g+'%' : ''}</div>
            <div class="bg-yellow-400 h-full flex items-center justify-center text-[10px] text-black font-bold" style="width: ${s}%">${s > 10 ? s+'%' : ''}</div>
            <div class="bg-black h-full flex items-center justify-center text-[10px] text-white font-bold" style="width: ${e}%">${e > 10 ? e+'%' : ''}</div>
            <div class="bg-pink-300 h-full flex items-center justify-center text-[10px] text-black font-bold" style="width: ${ep}%">${ep > 10 ? ep+'%' : ''}</div>
        </div>
        <div class="flex justify-between text-xs text-gray-500 mt-1 px-1">
            <span>Granulation: ${g}%</span>
            <span>Slough: ${s}%</span>
            <span>Eschar: ${e}%</span>
            <span>Epithelial: ${ep}%</span>
        </div>
        `;
        // Locations
        let locationsHTML = '';
        if (data.tunneling_present === 'Yes' && data.tunneling_locations) {
        try {
            const locations = JSON.parse(data.tunneling_locations);
            locationsHTML += `<div class="mt-2"><span class="text-xs font-bold text-gray-600">Tunneling:</span> <ul class="list-disc list-inside text-sm text-gray-700 ml-2">${locations.map(loc => `<li>${V(loc.position)} o'clock, ${V(loc.depth)} cm</li>`).join('')}</ul></div>`;
        } catch (e) {}
        }
        if (data.undermining_present === 'Yes' && data.undermining_locations) {
        try {
            const locations = JSON.parse(data.undermining_locations);
            locationsHTML += `<div class="mt-2"><span class="text-xs font-bold text-gray-600">Undermining:</span> <ul class="list-disc list-inside text-sm text-gray-700 ml-2">${locations.map(loc => `<li>${V(loc.position)} o'clock, ${V(loc.depth)} cm</li>`).join('')}</ul></div>`;
        } catch (e) {}
        }
        // Graft Audit
        let graftAuditHTML = '';
        if (data.graft_attestation_timestamp) {
        graftAuditHTML = `
            <div class="mt-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-center mb-2">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <h4 class="text-md font-bold text-green-800">Graft Application Record</h4>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    ${DetailRow('Product', `${V(data.graft_product_name)} (App #${V(data.graft_application_num)})`)}
                    ${DetailRow('Serial / Lot', `${V(data.graft_serial)} / ${V(data.graft_lot)}`)}
                    ${DetailRow('Usage', `${V(data.graft_used_cm)}cm² Used, ${V(data.graft_discarded_cm)}cm² Discarded`)}
                    ${DetailRow('Attested By', `User ID ${V(data.graft_attestation_user_id)}`)}
                    ${DetailRow('Timestamp', new Date(data.graft_attestation_timestamp).toLocaleString(), true)}
                    ${data.graft_serial_photo_path ? `<div class="col-span-2 mt-2"><a href="${data.graft_serial_photo_path}" target="_blank" class="inline-flex items-center px-3 py-1 border border-green-600 text-green-600 rounded hover:bg-green-50 text-xs font-bold">View Serial Photo</a></div>` : ''}
                </div>
            </div>`;
        }
        viewAssessmentContent.innerHTML = `
        <div class="bg-white">
            <!-- Header Info -->
            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4 flex justify-between items-center">
                <div>
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Assessment Date</span>
                    <div class="text-lg font-bold text-gray-800">${new Date(data.assessment_date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
                </div>
                <div class="text-right">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">ID</span>
                    <div class="text-lg font-mono font-bold text-indigo-600">#${V(data.assessment_id)}</div>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Left Column -->
                <div>
                    ${imageHTML}
                    ${SectionHeader('Wound Metrics', '<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>')}
                    <div class="bg-white p-3 rounded border border-gray-100 shadow-sm">
                        <div class="grid grid-cols-2 gap-4 mb-3">
                            ${DetailRow('Dimensions (LxWxD)', `${V(data.length_cm)} x ${V(data.width_cm)} x ${V(data.depth_cm)} cm`)}
                            ${DetailRow('Surface Area', `${(parseFloat(data.length_cm||0) * parseFloat(data.width_cm||0)).toFixed(2)} cm²`)}
                        </div>
                        <div class="mb-3">
                            <span class="text-xs font-semibold text-gray-500 uppercase block mb-1">Tissue Composition</span>
                            ${tissueBar}
                        </div>
                        ${locationsHTML ? `<div class="mt-3 pt-3 border-t border-gray-100">${locationsHTML}</div>` : ''}
                    </div>
                    ${SectionHeader('Characteristics', '<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>')}
                    <div class="grid grid-cols-2 gap-4 bg-white p-3 rounded border border-gray-100 shadow-sm">
                        ${DetailRow('Drainage', `${V(data.exudate_amount)} ${V(data.drainage_type)}`)}
                        ${DetailRow('Odor', V(data.odor_present))}
                        ${DetailRow('Granulation', `${V(data.granulation_color)} (${V(data.granulation_coverage)})`, true)}
                        ${DetailRow('Periwound', formatJSONList(data.periwound_condition), true)}
                        ${DetailRow('Infection Signs', formatJSONList(data.signs_of_infection), true)}
                        ${DetailRow('Exposed Structures', formatJSONList(data.exposed_structures), true)}
                    </div>
                </div>
                <!-- Right Column -->
                <div>
                    ${SectionHeader('Risk & Clinical', '<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>')}
                    <div class="grid grid-cols-2 gap-4 bg-white p-3 rounded border border-gray-100 shadow-sm">
                        ${DetailRow('Pain Level', V(data.pain_level))}
                        ${DetailRow('Nutritional Status', V(data.nutritional_status))}
                        ${DetailRow('Braden Score', V(data.braden_score))}
                        ${DetailRow('PUSH Score', V(data.push_score))}
                        ${DetailRow('Risk Factors', V(data.risk_factors), true)}
                        ${DetailRow('Medical Necessity', V(data.medical_necessity), true)}
                        ${DetailRow('DVT / Edema', V(data.dvt_edema_notes), true)}
                    </div>
                    ${SectionHeader('Debridement', '<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"></path></svg>')}
                    <div class="grid grid-cols-2 gap-4 bg-white p-3 rounded border border-gray-100 shadow-sm">
                        ${DetailRow('Performed?', V(data.debridement_performed))}
                        ${DetailRow('Type', V(data.debridement_type))}
                        ${DetailRow('Pre-Debridement Notes', V(data.pre_debridement_notes), true)}
                    </div>
                </div>
            </div>
            <!-- Full Width Sections -->
            ${SectionHeader('Treatment Plan', '<svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>')}
            <div class="bg-gray-50 p-4 rounded border border-gray-200 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">
                ${V(data.treatments_provided)}
            </div>
            ${graftAuditHTML}
            
            <!-- Action Buttons -->
            <div class="mt-6 pt-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button onclick="deleteAssessment(${data.assessment_id})" 
                    class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-md font-bold transition flex items-center"
                    ${window.isVisitSigned ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : ''}>
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Delete
                </button>
                <button onclick="loadAssessmentForEditing(${data.assessment_id}); document.getElementById('viewAssessmentModal').classList.add('hidden'); document.getElementById('viewAssessmentModal').classList.remove('flex');" 
                    class="px-4 py-2 bg-indigo-600 text-white hover:bg-indigo-700 rounded-md font-bold transition flex items-center shadow-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                    Load & Edit
                </button>
            </div>
        </div>
        `;
        viewAssessmentModal.classList.remove('hidden');
        viewAssessmentModal.classList.add('flex');
}
// --- MEASUREMENT MODAL LOGIC (No changes) ---
if (closeAIMeasureModalBtn) {
closeAIMeasureModalBtn.addEventListener('click', () => aiMeasurementModal.classList.add('hidden'));
}
if (openAIMeasureModalBtn) {
openAIMeasureModalBtn.addEventListener('click', () => {
if (!window.currentPhotoFile) {
    showMessage(uploadMessage, 'Please upload a photo first.', 'error');
    return;
}
aiMeasurementModal.classList.remove('hidden');
aiMeasurementModal.classList.add('flex');
// Initialize canvas after modal is visible
setTimeout(() => {
    initAICanvas();
    loadAIImage();
}, 100);
aiMeasureSubmitBtn.disabled = false;
aiResultsDiv.classList.add('hidden');
useAIMeasurementsBtn.disabled = true;
});
}
if (aiMeasureForm) {
aiMeasureForm.addEventListener('submit', async function(e) {
e.preventDefault();
aiMeasureSubmitBtn.disabled = true;
aiMeasureSubmitBtn.innerHTML = '<div class="ai-spinner"></div> Analyzing...';
const formData = new FormData();
formData.append('wound_photo', window.currentPhotoFile);
// Check for Head Orientation Arrow
if (aiCanvas) {
    const objects = aiCanvas.getObjects();
    const hasArrow = objects.some(obj => 
        obj.type === 'group' && 
        obj.getObjects().some(o => o.type === 'text' && o.text === 'HEAD')
    );
    if (hasArrow) {
        // If arrow exists, we must send the CANVAS image (with the arrow burned in)
        // instead of the raw original photo.
        console.log("Head Arrow detected. Sending annotated canvas to AI.");
        
        // Convert canvas to blob
        // We use a promise to handle the async toBlob
        const blob = await new Promise(resolve => aiCanvas.getElement().toBlob(resolve, 'image/jpeg', 0.9));
        formData.set('wound_photo', blob, 'orientation_image.jpg');
        formData.append('has_orientation_arrow', 'true');
    }
}
try {
    const response = await fetch(API_ENDPOINTS.AUTO_MEASURE_WOUND, { method: 'POST', body: formData });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Failed to get a valid response from the AI model.');
    document.getElementById('aiResLength').textContent = `${result.measurements.length.toFixed(2)} cm`;
    document.getElementById('aiResWidth').textContent = `${result.measurements.width.toFixed(2)} cm`;
    document.getElementById('aiResArea').textContent = `${result.measurements.area.toFixed(2)} cm²`;
    document.getElementById('aiResGranulation').textContent = `${result.tissue_types.granulation}%`;
    document.getElementById('aiResSlough').textContent = `${result.tissue_types.slough}%`;
    document.getElementById('aiResEschar').textContent = `${result.tissue_types.eschar}%`;
    document.getElementById('aiResInfection').textContent = result.infection_risk;
    aiResultsDiv.classList.remove('hidden');
    useAIMeasurementsBtn.disabled = false;
    showFloatingAlert('AI Analysis completed successfully', 'success');
} catch (error) {
    showMessage(uploadMessage, `AI Analysis Failed: ${error.message}`, 'error');
    showFloatingAlert(`AI Analysis Failed: ${error.message}`, 'error');
} finally {
    aiMeasureSubmitBtn.disabled = false;
    aiMeasureSubmitBtn.textContent = 'Run AI Analysis';
}
});
}
if (useAIMeasurementsBtn) {
useAIMeasurementsBtn.addEventListener('click', () => {
document.getElementById('length_cm').value = parseFloat(document.getElementById('aiResLength').textContent);
document.getElementById('width_cm').value = parseFloat(document.getElementById('aiResWidth').textContent);
document.getElementById('depth_cm').value = '';
document.getElementById('granulation_percent').value = parseInt(document.getElementById('aiResGranulation').textContent);
document.getElementById('slough_percent').value = parseInt(document.getElementById('aiResSlough').textContent);
triggerAccordionContentVisibility();
debouncedSave();
aiMeasurementModal.classList.add('hidden');
});
}
// Manual Measurement Modal Logic
const useMeasurementsBtn = document.getElementById('useMeasurementsBtn');
if (openManualMeasureModalBtn) {
openManualMeasureModalBtn.addEventListener('click', () => {
if (!window.currentPhotoFile) {
    showMessage(uploadMessage, 'Please upload a photo before using the measurement tool.', 'error');
    return;
}
const modal = document.getElementById('manualMeasurementModal');
modal.classList.remove('hidden');
modal.classList.add('flex');
if (typeof ManualMeasurement !== 'undefined' && typeof ManualMeasurement.init === 'function') {
    ManualMeasurement.init(window.currentPhotoFile);
} else {
    showMessage(uploadMessage, 'Error: Manual measurement script not loaded correctly.', 'error');
}
document.getElementById('resLength').textContent = 'N/A';
document.getElementById('resWidth').textContent = 'N/A';
document.getElementById('resArea').textContent = 'N/A';
useMeasurementsBtn.disabled = true;
});
}
if (useMeasurementsBtn) {
useMeasurementsBtn.addEventListener('click', () => {
const results = window.ManualMeasurementResults;
if (results && results.length > 0 && results.width > 0) {
    document.getElementById('length_cm').value = results.length.toFixed(2);
    document.getElementById('width_cm').value = results.width.toFixed(2);
    document.getElementById('depth_cm').value = '';
    debouncedSave();
    document.getElementById('manualMeasurementModal').classList.add('hidden');
} else {
    showMessage(uploadMessage, 'Please complete the measurement in the manual tool.', 'error');
}
});
}
if (closeManualModalBtn) {
closeManualModalBtn.addEventListener('click', () => {
document.getElementById('manualMeasurementModal').classList.add('hidden');
});
}
// --- TAB SWITCHING LOGIC ---
function switchTab(tabName) {
// Hide all tabs
if (tabAssessment) tabAssessment.classList.add('hidden');
if (tabHistory) tabHistory.classList.add('hidden');
if (tabGallery) tabGallery.classList.add('hidden');
// Reset button styles
[tabBtnAssessment, tabBtnHistory, tabBtnGallery].forEach(btn => {
if (btn) {
    btn.classList.remove('text-indigo-600', 'font-bold', 'border-b-2', 'border-indigo-600');
    btn.classList.add('text-gray-600', 'font-medium');
}
});
// Show selected tab and style button
if (tabName === 'assessment') {
if (tabAssessment) tabAssessment.classList.remove('hidden');
if (tabBtnAssessment) {
    tabBtnAssessment.classList.remove('text-gray-600', 'font-medium');
    tabBtnAssessment.classList.add('text-indigo-600', 'font-bold', 'border-b-2', 'border-indigo-600');
}
} else if (tabName === 'history') {
if (tabHistory) tabHistory.classList.remove('hidden');
if (tabBtnHistory) {
    tabBtnHistory.classList.remove('text-gray-600', 'font-medium');
    tabBtnHistory.classList.add('text-indigo-600', 'font-bold', 'border-b-2', 'border-indigo-600');
}
} else if (tabName === 'gallery') {
if (tabGallery) tabGallery.classList.remove('hidden');
if (tabBtnGallery) {
    tabBtnGallery.classList.remove('text-gray-600', 'font-medium');
    tabBtnGallery.classList.add('text-indigo-600', 'font-bold', 'border-b-2', 'border-indigo-600');
}
}
}
if (tabBtnAssessment) tabBtnAssessment.addEventListener('click', () => switchTab('assessment'));
if (tabBtnHistory) tabBtnHistory.addEventListener('click', () => switchTab('history'));
if (tabBtnGallery) tabBtnGallery.addEventListener('click', () => switchTab('gallery'));
// --- HISTORY AND GALLERY HANDLERS (No changes) ---
document.body.addEventListener('click', async function(e) {
if (e.target.classList.contains('view-assessment-btn')) {
const assessmentId = e.target.dataset.assessmentId;
try {
    const response = await fetch(`${API_ENDPOINTS.GET_ASSESSMENT_DETAILS}?id=${assessmentId}`);
    if (!response.ok) throw new Error('Failed to fetch assessment details.');
    const data = await response.json();
    populateAndShowAssessmentModal(data);
} catch (error) {
    showMessage(uploadMessage, error.message, 'error');
    console.error("View Assessment Error:", error);
}
}
// NEW: Edit Assessment Button Logic
if (e.target.classList.contains('edit-assessment-btn') || e.target.closest('.edit-assessment-btn')) {
    const btn = e.target.classList.contains('edit-assessment-btn') ? e.target : e.target.closest('.edit-assessment-btn');
    const assessmentId = btn.dataset.assessmentId;
    await loadAssessmentForEditing(assessmentId);
}
if (e.target.classList.contains('delete-photo-btn')) {
const confirmed = await showConfirmation('Are you sure you want to delete this photo? This may also delete its associated assessment.', 'Delete Photo', 'bg-red-600');
if (confirmed) {
    const imageId = e.target.dataset.imageId;
    try {
        const response = await fetch(API_ENDPOINTS.DELETE_PHOTO, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image_id: imageId })
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message);
        showMessage(uploadMessage, result.message, 'success');
        showFloatingAlert(result.message, 'success');
        fetchWoundDetails();
    } catch (error) {
        showMessage(uploadMessage, `Error: ${error.message}`, 'error');
        showFloatingAlert(`Error: ${error.message}`, 'error');
        console.error("Delete Photo Error:", error);
    }
}
}
});
if (closeViewModalBtn) {
closeViewModalBtn.addEventListener('click', () => {
viewAssessmentModal.classList.add('hidden');
viewAssessmentModal.classList.remove('flex');
});
}
// --- AI TREATMENT PLAN (No changes) ---
if (generatePlanBtn) {
generatePlanBtn.addEventListener('click', async () => {
const originalButtonText = generatePlanBtn.innerHTML;
generatePlanBtn.innerHTML = '<div class="spinner-small mx-auto"></div>';
generatePlanBtn.disabled = true;
const formData = new FormData(assessmentForm);
const data = Object.fromEntries(formData.entries());
data.signs_of_infection = Array.from(document.getElementById('signs_of_infection').selectedOptions).map(opt => opt.value);
data.periwound_condition = Array.from(document.getElementById('periwound_condition').selectedOptions).map(opt => opt.value);

// Handle Exposed Structures Checkboxes
data.exposed_structures = Array.from(document.querySelectorAll('input[name="exposed_structures[]"]:checked')).map(cb => cb.value);
try {
    const response = await fetch(API_ENDPOINTS.GENERATE_PLAN, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data),
    });
    if (!response.ok) {
        const errorResult = await response.json();
        throw new Error(errorResult.message || 'Failed to generate plan.');
    }
    const result = await response.json();
    treatmentsProvidedTextarea.value = result.treatment_plan;
    debouncedSave();
    showFloatingAlert('Treatment plan generated successfully', 'success');
} catch (error) {
    showMessage(formMessage, `AI Error: ${error.message}`, 'error');
    showFloatingAlert(`AI Error: ${error.message}`, 'error');
} finally {
       generatePlanBtn.innerHTML = originalButtonText;
    generatePlanBtn.disabled = false;
}
});
}
// --- QUICK TEXT SELECTOR LOGIC ---
document.querySelectorAll('.quick-text-selector').forEach(selector => {
selector.addEventListener('change', function() {
const textToAdd = this.value;
if (!textToAdd) return;
const targetId = this.getAttribute('data-target');
const targetTextarea = document.getElementById(targetId);
if (targetTextarea) {
    const currentText = targetTextarea.value.trim();
    if (currentText) {
        targetTextarea.value = currentText + ', ' + textToAdd;
    } else {
        targetTextarea.value = textToAdd;
    }
    // Trigger autosave
    debouncedSave();
}
// Reset selector
this.value = "";
});
});
// --- PUSH SCORE CALCULATOR LOGIC ---
const pushCalcModal = document.getElementById('pushCalcModal');
const openPushCalcBtn = document.getElementById('openPushCalcBtn');
const closePushCalcBtn = document.getElementById('closePushCalcBtn');
const applyPushScoreBtn = document.getElementById('applyPushScoreBtn');
// Selects
const pushSelectArea = document.getElementById('pushSelectArea');
const pushSelectExudate = document.getElementById('pushSelectExudate');
const pushSelectTissue = document.getElementById('pushSelectTissue');
// Displays
const pushScoreArea = document.getElementById('pushScoreArea');
const pushScoreExudate = document.getElementById('pushScoreExudate');
const pushScoreTissue = document.getElementById('pushScoreTissue');
const pushTotalDisplay = document.getElementById('pushTotalDisplay');
// Value Previews
const pushCalcAreaVal = document.getElementById('pushCalcAreaVal');
const pushCalcExudateVal = document.getElementById('pushCalcExudateVal');
const pushCalcTissueVal = document.getElementById('pushCalcTissueVal');
function calculatePushTotal() {
const s1 = parseInt(pushSelectArea.value) || 0;
const s2 = parseInt(pushSelectExudate.value) || 0;
const s3 = parseInt(pushSelectTissue.value) || 0;
pushScoreArea.textContent = s1;
pushScoreExudate.textContent = s2;
pushScoreTissue.textContent = s3;
pushTotalDisplay.textContent = s1 + s2 + s3;
}
if (openPushCalcBtn) {
openPushCalcBtn.addEventListener('click', () => {
// 1. Auto-Calculate Surface Area Score
const l = parseFloat(document.getElementById('length_cm').value) || 0;
const w = parseFloat(document.getElementById('width_cm').value) || 0;
const area = l * w;
pushCalcAreaVal.textContent = area.toFixed(1);
let areaScore = 0;
if (area > 24) areaScore = 10;
else if (area > 12) areaScore = 9;
else if (area > 8) areaScore = 8;
else if (area > 4) areaScore = 7;
else if (area > 3) areaScore = 6;
else if (area > 2) areaScore = 5;
else if (area > 1) areaScore = 4;
else if (area > 0.6) areaScore = 3;
else if (area > 0.3) areaScore = 2;
else if (area > 0) areaScore = 1;

pushSelectArea.value = areaScore;
// 2. Auto-Calculate Exudate Score
const exudate = document.getElementById('exudate_amount').value || 'None';
pushCalcExudateVal.textContent = exudate;

let exudateScore = 0;
if (exudate === 'Large') exudateScore = 3;
else if (exudate === 'Moderate') exudateScore = 2;
else if (exudate === 'Small' || exudate === 'Scant') exudateScore = 1;

pushSelectExudate.value = exudateScore;
// 3. Auto-Calculate Tissue Score
const eschar = parseFloat(document.getElementById('eschar_percent').value) || 0;
const slough = parseFloat(document.getElementById('slough_percent').value) || 0;
const gran = parseFloat(document.getElementById('granulation_percent').value) || 0;

let tissueScore = 0;
let tissueText = "Closed/Resurfaced";
if (eschar > 0) {
    tissueScore = 4;
    tissueText = "Necrotic Tissue (Eschar)";
} else if (slough > 0) {
    tissueScore = 3;
    tissueText = "Slough";
} else if (gran > 0) {
    tissueScore = 2;
    tissueText = "Granulation Tissue";
} else {
    // Check if wound is open? Assuming if L/W > 0 it is open, but if no other tissue, maybe epithelial?
    // PUSH defines score 1 as "Epithelial Tissue" (superficial ulcers, new pink/shiny skin)
    // If area > 0 but no eschar/slough/granulation, it's likely epithelializing or closed.
    // We'll default to 1 (Epithelial) if area > 0, else 0.
    if (area > 0) {
        tissueScore = 1;
        tissueText = "Epithelial Tissue";
    }
}

pushCalcTissueVal.textContent = tissueText;
pushSelectTissue.value = tissueScore;
// Update UI
calculatePushTotal();
pushCalcModal.classList.remove('hidden');
pushCalcModal.classList.add('flex');
});
}
[pushSelectArea, pushSelectExudate, pushSelectTissue].forEach(el => {
if(el) el.addEventListener('change', calculatePushTotal);
});
if (closePushCalcBtn) {
closePushCalcBtn.addEventListener('click', () => {
pushCalcModal.classList.add('hidden');
pushCalcModal.classList.remove('flex');
});
}
if (applyPushScoreBtn) {
applyPushScoreBtn.addEventListener('click', () => {
const total = pushTotalDisplay.textContent;
document.getElementById('push_score').value = total;
debouncedSave(); // Trigger autosave
pushCalcModal.classList.add('hidden');
pushCalcModal.classList.remove('flex');
});
}
// --- BRADEN SCALE CALCULATOR LOGIC ---
const bradenCalcModal = document.getElementById('bradenCalcModal');
const openBradenCalcBtn = document.getElementById('openBradenCalcBtn');
const closeBradenCalcBtn = document.getElementById('closeBradenCalcBtn');
const applyBradenScoreBtn = document.getElementById('applyBradenScoreBtn');
const bradenTotalDisplay = document.getElementById('bradenTotalDisplay');
const bradenRiskLabel = document.getElementById('bradenRiskLabel');
const bradenInputs = [
'bradenSensory', 'bradenMoisture', 'bradenActivity', 
'bradenMobility', 'bradenNutrition', 'bradenFriction'
];
function calculateBradenTotal() {
let total = 0;
bradenInputs.forEach(id => {
const val = parseInt(document.getElementById(id).value) || 0;
total += val;
});
if (bradenTotalDisplay) bradenTotalDisplay.textContent = total;
// Determine Risk Label
let riskText = "No Risk";
let riskColor = "text-gray-500";
if (total <= 9) {
riskText = "Severe Risk";
riskColor = "text-red-600";
} else if (total <= 12) {
riskText = "High Risk";
riskColor = "text-orange-600";
} else if (total <= 14) {
riskText = "Moderate Risk";
riskColor = "text-yellow-600";
} else if (total <= 18) {
riskText = "Mild Risk";
riskColor = "text-blue-600";
}
if (bradenRiskLabel) {
bradenRiskLabel.textContent = riskText;
bradenRiskLabel.className = `text-xs font-bold ${riskColor}`;
}
return total;
}
if (openBradenCalcBtn) {
openBradenCalcBtn.addEventListener('click', () => {
// Reset to defaults (max score 23) or try to parse current value?
// For now, let's just open it. Ideally we could parse the existing score if needed.
calculateBradenTotal();
bradenCalcModal.classList.remove('hidden');
bradenCalcModal.classList.add('flex');
});
}
if (closeBradenCalcBtn) {
closeBradenCalcBtn.addEventListener('click', () => {
bradenCalcModal.classList.add('hidden');
bradenCalcModal.classList.remove('flex');
});
}
// Recalculate on any change
bradenInputs.forEach(id => {
const el = document.getElementById(id);
if (el) {
el.addEventListener('change', calculateBradenTotal);
}
});
if (applyBradenScoreBtn) {
applyBradenScoreBtn.addEventListener('click', () => {
const total = calculateBradenTotal();
const bradenField = document.getElementById('braden_score');
if (bradenField) {
    bradenField.value = total;
    debouncedSave();
}
bradenCalcModal.classList.add('hidden');
bradenCalcModal.classList.remove('flex');
});
}
// Close modal on outside click
window.addEventListener('click', (e) => {
if (e.target === bradenCalcModal) {
bradenCalcModal.classList.add('hidden');
bradenCalcModal.classList.remove('flex');
}
});
// --- INITIALIZATION ---
if (woundId) {
    fetchWoundDetails();
} else {
    console.error("Wound ID missing in URL");
    if (uploadMessage) showMessage(uploadMessage, 'Error: Wound ID missing.', 'error');
}
setupDynamicFormHandlers();

    if (assessmentForm) {
        assessmentForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (finalizeAssessmentBtn) {
                finalizeAssessmentBtn.disabled = true;
                finalizeAssessmentBtn.textContent = 'Saving...';
            }

            const formData = new FormData(assessmentForm);
            const data = Object.fromEntries(formData.entries());
            
            // Handle multi-selects
            const signsSelect = document.getElementById('signs_of_infection');
            if (signsSelect) data.signs_of_infection = Array.from(signsSelect.selectedOptions).map(opt => opt.value);
            
            const periSelect = document.getElementById('periwound_condition');
            if (periSelect) data.periwound_condition = Array.from(periSelect.selectedOptions).map(opt => opt.value);
            
            data.exposed_structures = Array.from(document.querySelectorAll('input[name="exposed_structures[]"]:checked')).map(cb => cb.value);

            // Handle Dynamic Locations
            data.tunneling_locations = collectLocationData('tunneling');
            data.undermining_locations = collectLocationData('undermining');

            try {
                const response = await fetch(API_ENDPOINTS.CREATE_ASSESSMENT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                
                if (result.success) {
                    showMessage(uploadMessage, 'Assessment saved successfully!', 'success');
                    showFloatingAlert('Assessment finalized and saved successfully!', 'success');
                    if (result.assessment_id) {
                        currentAssessmentId = result.assessment_id;
                        if (assessmentFormIdInput) assessmentFormIdInput.value = currentAssessmentId;
                    }
                    // Refresh data to show updated history without reload
                    fetchWoundDetails();
                } else {
                    throw new Error(result.message || 'Save failed');
                }
            } catch (error) {
                console.error("Save error:", error);
                showMessage(uploadMessage, 'Error saving assessment: ' + error.message, 'error');
                showFloatingAlert('Error saving assessment: ' + error.message, 'error');
            } finally {
                if (finalizeAssessmentBtn) {
                    finalizeAssessmentBtn.disabled = false;
                    finalizeAssessmentBtn.textContent = 'Finalize and Save';
                }
            }
        });
    }

    // --- AI MODAL CANVAS LOGIC ---
    let aiCanvas = null;
    const addAIHeadArrowBtn = document.getElementById('addAIHeadArrowBtn');
    function initAICanvas() {
    if (aiCanvas) {
    aiCanvas.dispose();
    }
    const wrapper = document.getElementById('ai-canvas-wrapper');
    const canvasEl = document.getElementById('aiCanvas');
    // Set canvas dimensions to match wrapper
    canvasEl.width = wrapper.clientWidth;
    canvasEl.height = wrapper.clientHeight;
    aiCanvas = new fabric.Canvas('aiCanvas', {
    width: wrapper.clientWidth,
    height: wrapper.clientHeight,
    selection: true
    });
    }
    function loadAIImage() {
    if (!window.currentPhotoFile) return;
    const reader = new FileReader();
    reader.onload = function(e) {
    fabric.Image.fromURL(e.target.result, function(img) {
        // Scale image to fit canvas while maintaining aspect ratio
        const canvasWidth = aiCanvas.width;
        const canvasHeight = aiCanvas.height;
        
        const scale = Math.min(
            canvasWidth / img.width,
            canvasHeight / img.height
        );
        img.set({
            scaleX: scale,
            scaleY: scale,
            left: (canvasWidth - img.width * scale) / 2,
            top: (canvasHeight - img.height * scale) / 2,
            selectable: false,
            evented: false
        });
        aiCanvas.setBackgroundImage(img, aiCanvas.renderAll.bind(aiCanvas));
    });
    };
    reader.readAsDataURL(window.currentPhotoFile);
    }
    if (addAIHeadArrowBtn) {
    addAIHeadArrowBtn.addEventListener('click', () => {
    if (!aiCanvas) return;
    // Create Arrow Group (Same as Annotation Tool)
    const triangle = new fabric.Triangle({
        width: 20, height: 20, fill: 'blue', left: 0, top: -35, originX: 'center', originY: 'center'
    });
    const line = new fabric.Rect({
        width: 6, height: 60, fill: 'blue', left: 0, top: 5, originX: 'center', originY: 'center'
    });
    const text = new fabric.Text('HEAD', {
        fontSize: 16, fill: 'blue', left: 0, top: -55, originX: 'center', originY: 'center', fontWeight: 'bold'
    });
    const arrowGroup = new fabric.Group([line, triangle, text], {
        left: aiCanvas.width / 2,
        top: aiCanvas.height / 2,
        angle: 0,
        selectable: true,
        hasControls: true,
        hasBorders: true
    });
    aiCanvas.add(arrowGroup);
    aiCanvas.setActiveObject(arrowGroup);
    aiCanvas.renderAll();
    });
    }

    window.addLocationField = function(type, initialData = {}) {
        const container = document.getElementById(`${type}_locations`);
        if (!container) return;
        const index = container.children.length;
        const locationDiv = document.createElement('div');
        locationDiv.className = 'flex items-center space-x-2 my-1';

        let optionsHtml = '<option value="">Location</option>';
        for (let i = 1; i <= 12; i++) {
            optionsHtml += `<option value="${i}">${i} o'clock</option>`;
        }

        const positionValue = initialData.position || '';
        const depthValue = initialData.depth || '';

        // Assign unique IDs for Voice Assistant access
        const posId = `${type}_pos_${index}`;
        const depthId = `${type}_depth_${index}`;

        locationDiv.innerHTML = `
            <div class="flex-1"><select id="${posId}" name="${type}_locations[${index}][position]" class="form-input bg-white text-sm" data-autosave-field="true">${optionsHtml}</select></div>
            <div class="flex-1"><input id="${depthId}" type="number" step="0.1" name="${type}_locations[${index}][depth]" class="form-input text-sm" placeholder="Depth (cm)" value="${depthValue}" data-autosave-field="true"></div>
            <button type="button" class="text-red-500 hover:text-red-700 remove-location-btn">&times;</button>`;
        container.appendChild(locationDiv);

        if (positionValue) {
            locationDiv.querySelector('select').value = positionValue;
        }

        locationDiv.querySelector('.remove-location-btn').addEventListener('click', () => {
            locationDiv.remove();
            if (typeof debouncedSave === 'function') debouncedSave();
        });
        locationDiv.querySelectorAll('[data-autosave-field="true"]').forEach(input => {
            if (typeof debouncedSave === 'function') {
                input.addEventListener('input', debouncedSave);
                input.addEventListener('change', debouncedSave);
            }
        });

        return { posId, depthId };
    };

    // Helper for internal use (renamed from local function)
    // function addLocationField(type, initialData = {}) { ... } -> Removed/Replaced by window.addLocationField


    function collectLocationData(type) {
        const container = document.getElementById(`${type}_locations`);
        if (!container) return [];
        const locations = [];
        Array.from(container.children).forEach(div => {
            const posSelect = div.querySelector(`select`);
            const depthInput = div.querySelector(`input[type="number"]`);
            if (posSelect && depthInput) {
                locations.push({
                    position: posSelect.value,
                    depth: depthInput.value
                });
            }
        });
        return locations;
    }

    function disableMeasurementTools() {
        if (openAIMeasureModalBtn) openAIMeasureModalBtn.disabled = true;
        if (openManualMeasureModalBtn) openManualMeasureModalBtn.disabled = true;
    }

    function enableMeasurementTools() {
        if (openAIMeasureModalBtn) openAIMeasureModalBtn.disabled = false;
        if (openManualMeasureModalBtn) openManualMeasureModalBtn.disabled = false;
    }

    // --- PHOTO CAPTURE/UPLOAD LOGIC ---

    // --- HELPER: Find Assessment by Image Type ---
    function findAssessmentIdByType(imageType) {
        if (!appointmentId || !allAssessmentsHistory.length || !allImagesGlobal.length) return 0;

        // 1. Find all assessment IDs for this appointment
        const visitAssessmentIds = allAssessmentsHistory
            .filter(a => a.appointment_id == appointmentId)
            .map(a => a.assessment_id);
        
        // 2. Check if any of these assessments already have an image of the selected type
        const existingImage = allImagesGlobal.find(img => 
            visitAssessmentIds.includes(img.assessment_id) && 
            img.image_type === imageType
        );

        if (existingImage) {
            return existingImage.assessment_id;
        }
        return 0;
    }

    // --- PHOTO CAPTURE/UPLOAD LOGIC ---

    async function handlePhotoSelection(file) {
        if (!file) return;

        window.currentPhotoFile = file;

        if (!patientId || isNaN(patientId)) {
            showMessage(uploadMessage, 'Error: Patient details not fully loaded. Cannot start assessment.', 'error');
            console.error('Attempted to start assessment before patientId was available.');
            return;
        }

        const imageType = document.getElementById('image_type').value;
        if (!imageType) {
            showMessage(uploadMessage, 'Please select an Image Type before uploading.', 'error');
            // Reset file input so change event fires again if they select same file
            if (photoFileInput) photoFileInput.value = ''; 
            return;
        }

        if (!woundId) {
             showMessage(uploadMessage, 'Error: Wound ID is missing.', 'error');
             return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            imagePreview.src = e.target.result;
            imagePreview.classList.remove('hidden');
            captureStatus.classList.add('hidden');
        };
        reader.readAsDataURL(file);

        captureStatus.classList.remove('hidden');
        captureStatus.textContent = 'Uploading photo and starting assessment...';
        showMessage(uploadMessage, 'Uploading image...', 'info');
        if (captureControls) captureControls.classList.add('assessment-disabled');

        try {
            let assessmentId = currentAssessmentId;
            
            // SMART ASSESSMENT SELECTION:
            // Check if an assessment already exists for this Appointment + Image Type combination.
            const existingId = findAssessmentIdByType(imageType);
            
            if (existingId) {
                console.log(`Found existing assessment #${existingId} for type ${imageType}`);
                assessmentId = existingId;
            } else {
                // If no existing assessment for this type, check if we need to start fresh
                // Strategy: If the currentAssessmentId is associated with a DIFFERENT image type, start fresh.
                const currentAssocImage = allImagesGlobal.find(img => img.assessment_id == currentAssessmentId);
                if (currentAssocImage && currentAssocImage.image_type !== imageType) {
                    console.log(`Current assessment #${currentAssessmentId} is ${currentAssocImage.image_type}. Starting new for ${imageType}.`);
                    assessmentId = 0;
                } else if (!currentAssessmentId) {
                    assessmentId = 0;
                }
                
                // Specific override: Always separate Pre and Post if they don't exist yet
                if (imageType === 'Pre-debridement' || imageType === 'Post-Debridement') {
                     if (!existingId && currentAssessmentId) {
                         // If we are currently editing an assessment that HAS NO image yet, we can use it.
                         // But if we are editing one that has an image of a diff type, we handled it.
                     }
                }
            }

            // let initialMessage = `Initial photo upload: ${document.getElementById('image_type').value}`;

            if (!assessmentId) {
                const initialData = {
                    wound_id: woundId,
                    patient_id: patientId,
                    appointment_id: appointmentId,
                    assessment_date: document.getElementById('assessment_date').value,
                    // treatments_provided: initialMessage
                };

                const res = await fetch(API_ENDPOINTS.CREATE_ASSESSMENT, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(initialData)
                });
                const result = await res.json();
                if (!res.ok || !result.assessment_id) throw new Error(result.message);

                assessmentId = result.assessment_id;
                currentAssessmentId = assessmentId;
                if (assessmentFormIdInput) assessmentFormIdInput.value = currentAssessmentId;
            }

            const formData = new FormData();
            formData.append('wound_photo', file);
            formData.append('wound_id', woundId);
            formData.append('image_type', document.getElementById('image_type').value);
            formData.append('assessment_id', currentAssessmentId);
            formData.append('appointment_id', appointmentId);

            const photoRes = await fetch(API_ENDPOINTS.UPLOAD_PHOTO, { method: 'POST', body: formData });
            const photoResult = await photoRes.json();

            if (!photoRes.ok) throw new Error(photoResult.message);

            showMessage(uploadMessage, 'Photo uploaded successfully. Assessment draft ready.', 'success');

            if (assessmentCard) assessmentCard.classList.remove('assessment-disabled');
            if (autosaveStatus) autosaveStatus.textContent = 'Assessment in progress: changes will autosave.';
            enableMeasurementTools();
            fetchWoundDetails();

        } catch (error) {
            showMessage(uploadMessage, `Assessment Start Failed: ${error.message}`, 'error');
            console.error('Assessment Start Error:', error);
            captureStatus.classList.remove('hidden');
            captureStatus.textContent = 'Photo capture failed. Try again.';
        } finally {
            if (captureControls) captureControls.classList.remove('assessment-disabled');
        }
    }


    // --- IMAGE TYPE CHANGE HANDLER ---
    const imageTypeSelect = document.getElementById('image_type');
    if (imageTypeSelect) {
        imageTypeSelect.addEventListener('change', async () => {
            const newType = imageTypeSelect.value;
            console.log("Image type changed to:", newType);
            
            // Check if we should switch context
            const existingId = findAssessmentIdByType(newType);
            
            if (existingId && existingId !== currentAssessmentId) {
                console.log(`Switching to existing assessment #${existingId} for ${newType}`);
                await loadAssessmentForEditing(existingId);
            } else if (!existingId) {
                // No existing assessment for this type.
                // Should we clear the form?
                // Only if the current assessment is ALREADY associated with a different image type.
                const currentAssocImage = allImagesGlobal.find(img => img.assessment_id == currentAssessmentId);
                
                if (currentAssocImage && currentAssocImage.image_type !== newType) {
                    console.log(`Current assessment #${currentAssessmentId} is for ${currentAssocImage.image_type}. Clearing form for new ${newType} assessment.`);
                    
                    // Reset to "New Assessment" mode
                    currentAssessmentId = 0;
                    if (assessmentFormIdInput) assessmentFormIdInput.value = '';
                    
                    // Clear form fields but keep context IDs
                    populateForm({}); 
                    
                    // Restore hidden IDs that populateForm might have cleared (though populateForm checks for element existence)
                    if (document.getElementById('wound_id')) document.getElementById('wound_id').value = woundId;
                    if (document.getElementById('appointment_id')) document.getElementById('appointment_id').value = appointmentId;
                    if (document.getElementById('patient_id')) document.getElementById('patient_id').value = patientId;
                    
                    // Set default date
                    if (document.getElementById('assessment_date')) document.getElementById('assessment_date').value = new Date().toISOString().split('T')[0];
                    
                    // Clear image preview
                    if (imagePreview) {
                        imagePreview.src = '';
                        imagePreview.classList.add('hidden');
                    }
                    const container = document.getElementById('imagePreview');
                    if (container) {
                        const span = container.querySelector('span');
                        if (span) span.classList.remove('hidden');
                    }
                    window.currentPhotoFile = null;
                    
                    showMessage(formMessage, `Started new ${newType} assessment.`, 'success');
                }
            }
        });
    }

    // --- EVENT LISTENERS ---

    if (toggleFileBtn) {
        toggleFileBtn.addEventListener('click', () => {
            console.log("Upload button clicked");
            const imageType = document.getElementById('image_type').value;
            if (!imageType) {
                showMessage(uploadMessage, 'Please select an Image Type first.', 'error');
                const selectEl = document.getElementById('image_type');
                selectEl.focus();
                selectEl.classList.add('border-red-500', 'ring', 'ring-red-200');
                setTimeout(() => selectEl.classList.remove('border-red-500', 'ring', 'ring-red-200'), 3000);
                return;
            }
            
            if (photoFileInput) {
                photoFileInput.removeAttribute('capture');
                setTimeout(() => {
                    console.log("Triggering file input click for upload");
                    photoFileInput.click();
                }, 50);
            }
        });
    }

    // --- DELETE WOUND LOGIC ---
    const deleteWoundBtn = document.getElementById('deleteWoundBtn');
    if (deleteWoundBtn) {
        deleteWoundBtn.addEventListener('click', async () => {
            if (window.isVisitSigned) {
                showFloatingAlert('Cannot delete wound: Visit is signed.', 'error');
                return;
            }

            const confirmed = await showConfirmation(
                'Are you sure you want to delete this ENTIRE wound record? All assessments and photos will be permanently removed.', 
                'Delete Wound', 
                'bg-red-600'
            );
            
            if (confirmed) {
                try {
                    const response = await fetch(API_ENDPOINTS.DELETE_WOUND, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ wound_id: woundId })
                    });
                    
                    const result = await response.json();
                    
                    if (response.ok) {
                        showFloatingAlert('Wound deleted successfully. Redirecting...', 'success');
                        setTimeout(() => {
                            // Redirect to visit_wounds.php
                            const urlParams = new URLSearchParams(window.location.search);
                            const apptId = urlParams.get('appointment_id');
                            const patId = urlParams.get('patient_id');
                            const userId = urlParams.get('user_id');
                            window.location.href = `visit_wounds.php?appointment_id=${apptId}&patient_id=${patId}&user_id=${userId}`;
                        }, 1500);
                    } else {
                        throw new Error(result.message || 'Failed to delete wound.');
                    }
                } catch (error) {
                    console.error('Delete Wound Error:', error);
                    showFloatingAlert(error.message, 'error');
                }
            }
        });
    }
    // --- PC/WEB CAMERA LOGIC ---
    let videoStream = null;

    async function startCamera() {
        const cameraContainer = document.getElementById('camera-container');
        const video = document.getElementById('camera-stream');
        
        if (!cameraContainer || !video) {
            console.error("Camera elements not found");
            return;
        }

        try {
            // Request camera
            videoStream = await navigator.mediaDevices.getUserMedia({ 
                video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } 
            });
            video.srcObject = videoStream;
            cameraContainer.classList.remove('hidden');
        } catch (err) {
            console.error("Camera access error:", err);
            showMessage(uploadMessage, "Could not access camera. " + err.message, 'error');
            // Fallback to file picker
            if (photoFileInput) {
                photoFileInput.removeAttribute('capture');
                photoFileInput.click();
            }
        }
    }

    function stopCamera() {
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
            videoStream = null;
        }
        const cameraContainer = document.getElementById('camera-container');
        if (cameraContainer) cameraContainer.classList.add('hidden');
    }

    const captureBtn = document.getElementById('captureBtn');
    if (captureBtn) {
        captureBtn.addEventListener('click', () => {
            const video = document.getElementById('camera-stream');
            if (!video || !video.srcObject) return;

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            canvas.toBlob(blob => {
                const timestamp = new Date().getTime();
                const file = new File([blob], `capture_${timestamp}.jpg`, { type: "image/jpeg" });
                handlePhotoSelection(file);
                stopCamera();
            }, 'image/jpeg', 0.9);
        });
    }

    const closeCameraBtn = document.getElementById('closeCameraBtn');
    if (closeCameraBtn) {
        closeCameraBtn.addEventListener('click', (e) => {
            e.preventDefault();
            stopCamera();
        });
    }

    if (toggleCameraBtn) {
        toggleCameraBtn.addEventListener('click', () => {
            console.log("Camera button clicked");
            const imageType = document.getElementById('image_type').value;
            if (!imageType) {
                showMessage(uploadMessage, 'Please select an Image Type first.', 'error');
                const selectEl = document.getElementById('image_type');
                selectEl.focus();
                selectEl.classList.add('border-red-500', 'ring', 'ring-red-200');
                setTimeout(() => selectEl.classList.remove('border-red-500', 'ring', 'ring-red-200'), 3000);
                return;
            }

            // Detect Mobile Device
            const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

            if (isMobile) {
                // Mobile: Use Native Camera Input
                if (photoFileInput) {
                    photoFileInput.setAttribute('capture', 'environment');
                    setTimeout(() => {
                        console.log("Triggering file input click for camera");
                        photoFileInput.click();
                    }, 50);
                }
            } else {
                // PC/Desktop: Use WebRTC Camera
                startCamera();
            }
        });
    }

    if (photoFileInput) {
        photoFileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handlePhotoSelection(e.target.files[0]);
            }
        });
    }

    // Add Location Button Listeners
    const addTunnelingBtn = document.getElementById('addTunnelingLocation');
    if (addTunnelingBtn) {
        addTunnelingBtn.addEventListener('click', () => {
            addLocationField('tunneling');
            debouncedSave();
        });
    }

    const addUnderminingBtn = document.getElementById('addUnderminingLocation');
    if (addUnderminingBtn) {
        addUnderminingBtn.addEventListener('click', () => {
            addLocationField('undermining');
            debouncedSave();
        });
    }

    function triggerAccordionContentVisibility() {
        const grPercent = parseFloat(document.getElementById('granulation_percent').value) || 0;
        const granDetails = document.getElementById('granulation_details');
        if (granDetails) granDetails.classList.toggle('hidden', grPercent <= 0);

        const tunDetails = document.getElementById('tunneling_details_container');
        if (tunDetails) tunDetails.classList.toggle('hidden', document.getElementById('tunneling_present').value !== 'Yes');

        const undDetails = document.getElementById('undermining_details_container');
        if (undDetails) undDetails.classList.toggle('hidden', document.getElementById('undermining_present').value !== 'Yes');

        const debDetails = document.getElementById('debridement_details');
        if (debDetails) debDetails.classList.toggle('hidden', document.getElementById('debridement_performed').value !== 'Yes');
    }
});