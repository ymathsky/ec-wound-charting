// Filename: wound_assessment_logic.js
// UPDATED: Added logic for Graft Audit Checklist Modal

/**
 * UTILITY FUNCTIONS (Global scope for accessibility)
 */

// Debounce function to limit how often the autosave runs
const debounce = (func, delay) => {
    let timeout;
    return (...args) => {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), delay);
    };
};

// Function to safely parse multi-select fields (handles both array and comma-separated string formats from DB)
function parseMultiSelect(value) {
    if (!value) return [];
    if (Array.isArray(value)) return value;
    // Attempt to parse JSON array strings from DB
    try {
        const jsonParsed = JSON.parse(value);
        if (Array.isArray(jsonParsed)) return jsonParsed;
    } catch (e) {
        // Fallback for comma-separated string
        if (typeof value === 'string') {
            return value.split(',').map(v => v.trim()).filter(v => v !== '');
        }
    }
    return [];
}

document.addEventListener('DOMContentLoaded', function() {
    const woundId = new URLSearchParams(window.location.search).get('id');
    const appointmentId = new URLSearchParams(window.location.search).get('appointment_id');

    // Global State
    let patientId = 0; // CRITICAL: This is set during fetchWoundDetails
    let currentAssessmentId = 0;
    window.currentPhotoFile = null; // Used for AI/Manual measurement modals
    window.graftPhotoFile = null; // *** NEW: For Graft Serial Photo ***
    let woundProgressChart = null;
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

    // Photo/Capture Elements
    const photoFileInput = document.getElementById('wound_photo');
    const imagePreview = document.getElementById('image-preview');
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

    // AI Modal Elements
    const aiMeasurementModal = document.getElementById('aiMeasurementModal');
    const closeAIMeasureModalBtn = document.getElementById('closeAIMeasureModalBtn');
    const aiMeasureSubmitBtn = document.getElementById('aiMeasureSubmitBtn');
    const aiResultsDiv = document.getElementById('ai-results');
    const useAIMeasurementsBtn = document.getElementById('useAIMeasurementsBtn');
    const aiImagePreview = document.getElementById('ai-image-preview');
    const aiPlaceholderText = document.getElementById('ai-placeholder-text');

    // Treatment Plan Elements
    const generatePlanBtn = document.getElementById('generatePlanBtn');
    const treatmentsProvidedTextarea = document.getElementById('treatments_provided');

    // Autosave status element
    const autosaveStatus = document.getElementById('autosave-status');

    // Measurement Fields
    const measurementFields = ['length_cm', 'width_cm', 'depth_cm'];
    const measInputs = measurementFields.map(id => document.getElementById(id));

    // --- NEW: Graft Audit Modal Elements ---
    const graftAuditModal = document.getElementById('graftAuditModal');
    const openGraftAuditModalBtn = document.getElementById('openGraftAuditModalBtn');
    const closeGraftAuditModalBtn = document.getElementById('closeGraftAuditModalBtn');
    const cancelGraftAuditBtn = document.getElementById('cancelGraftAuditBtn');
    const submitGraftAuditBtn = document.getElementById('submitGraftAuditBtn');
    const graftAuditForm = document.getElementById('graftAuditForm');
    const graftModalMessage = document.getElementById('graft-modal-message');
    const graftAuditStatus = document.getElementById('graft-audit-status');

    // Graft "Smart Check" Elements
    const graftCheckConservative = document.getElementById('graft-check-conservative-care');
    const graftCheckInfectionContainer = document.getElementById('graft-check-infection-container');
    const graftCheckInfection = document.getElementById('graft-check-infection');
    const graftCheckNecrosisContainer = document.getElementById('graft-check-necrosis-container');
    const graftCheckNecrosis = document.getElementById('graft-check-necrosis');
    const graftCheckSizeContainer = document.getElementById('graft-check-size-container');
    const graftCheckSize = document.getElementById('graft-check-size');
    const graftCheckConditionsManaged = document.getElementById('graft-check-conditions-managed');

    // Graft Data Fields
    const graftProductName = document.getElementById('graft_product_name');
    const graftApplicationNum = document.getElementById('graft_application_num');
    const graftSerial = document.getElementById('graft_serial');
    const graftLot = document.getElementById('graft_lot');
    const graftBatch = document.getElementById('graft_batch');
    const graftSerialPhotoInput = document.getElementById('graft_serial_photo');
    const graftSerialPhotoBtn = document.getElementById('graft-serial-photo-btn');
    const graftPhotoStatus = document.getElementById('graft-photo-status');
    const graftUsedCm = document.getElementById('graft_used_cm');
    const graftDiscardedCm = document.getElementById('graft_discarded_cm');
    const graftJustification = document.getElementById('graft_justification');
    const graftAttestationCheckbox = document.getElementById('graft-attestation-checkbox');

    // All fields that require validation
    const allGraftAuditFields = document.querySelectorAll('.graft-audit-field');


    // --- API ENDPOINTS (Centralized for Maintainability) ---
    const API_ENDPOINTS = {
        CREATE_ASSESSMENT: 'api/create_assessment.php',
        UPLOAD_PHOTO: 'api/upload_wound_photo.php',
        GET_WOUND_DETAILS: `api/get_wound_details.php?id=${woundId}`,
        GET_ACTIVE_ASSESSMENT: `api/get_current_assessment_by_visit.php?wound_id=${woundId}&appointment_id=${appointmentId}`,
        GET_CHART_DATA: `api/get_wound_progress_data.php?wound_id=${woundId}`,
        GET_ASSESSMENT_DETAILS: `api/get_assessment_details.php`,
        DELETE_PHOTO: 'api/delete_wound_photo.php',
        GENERATE_PLAN: 'api/generate_treatment_plan.php',
        AUTO_MEASURE_WOUND: 'api/auto_measure_wound.php',
        SAVE_GRAFT_AUDIT: 'api/save_graft_audit.php' // *** NEW API ***
    };

    // --- UI/STATUS HELPERS ---

    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = 'p-3 my-3 rounded-md';
        if (type === 'error') element.classList.add('bg-red-100', 'text-red-800');
        else if (type === 'success') element.classList.add('bg-green-100', 'text-green-800');
        else element.classList.add('bg-blue-100', 'text-blue-800');
        element.classList.remove('hidden');
    }

    function setAutosaveStatus(message, isError = false) {
        autosaveStatus.textContent = message;
        autosaveStatus.className = 'text-xs italic ml-4';
        if (isError) {
            autosaveStatus.classList.add('text-red-600', 'font-semibold');
        } else {
            autosaveStatus.classList.add('text-gray-500');
        }
    }

    function enableCapture() {
        photoFileInput.disabled = false;
        captureControls.classList.remove('assessment-disabled');
        captureStatus.textContent = 'Select a file or capture a photo to begin.';
    }

    function disableMeasurementTools() {
        openAIMeasureModalBtn.disabled = true;
        openManualMeasureModalBtn.disabled = true;
    }

    function enableMeasurementTools() {
        openAIMeasureModalBtn.disabled = false;
        openManualMeasureModalBtn.disabled = false;
    }

    // --- CORE DATA HANDLING ---

    function populateForm(data, prevMeasurements = {}) {
        // Populate current assessment data
        document.getElementById('length_cm').value = data.length_cm || prevMeasurements.length_cm || '';
        document.getElementById('width_cm').value = data.width_cm || prevMeasurements.width_cm || '';
        document.getElementById('depth_cm').value = data.depth_cm || prevMeasurements.depth_cm || '';

        document.getElementById('assessment_date').value = data.assessment_date ? data.assessment_date.substring(0, 10) : new Date().toISOString().substring(0, 10);

        document.getElementById('tunneling_present').value = data.tunneling_present || 'No';
        document.getElementById('undermining_present').value = data.undermining_present || 'No';
        document.getElementById('granulation_percent').value = data.granulation_percent || '';
        document.getElementById('slough_percent').value = data.slough_percent || '';

        document.getElementById('granulation_color').value = data.granulation_color || '';
        document.getElementById('granulation_coverage').value = data.granulation_coverage || '';
        document.getElementById('drainage_type').value = data.drainage_type || '';
        document.getElementById('exudate_amount').value = data.exudate_amount || '';
        document.getElementById('odor_present').value = data.odor_present || 'No';
        document.getElementById('debridement_performed').value = data.debridement_performed || 'No';
        document.getElementById('debridement_type').value = data.debridement_type || '';
        document.getElementById('treatments_provided').value = data.treatments_provided || '';

        // Handle multi-selects
        const periwoundValues = parseMultiSelect(data.periwound_condition);
        Array.from(document.getElementById('periwound_condition').options).forEach(option => {
            option.selected = periwoundValues.includes(option.value);
        });

        const infectionValues = parseMultiSelect(data.signs_of_infection);
        Array.from(document.getElementById('signs_of_infection').options).forEach(option => {
            option.selected = infectionValues.includes(option.value);
        });

        // Handle dynamic fields
        document.getElementById('tunneling_locations').innerHTML = '';
        document.getElementById('undermining_locations').innerHTML = '';

        try {
            if (data.tunneling_locations) {
                const locations = typeof data.tunneling_locations === 'string' ? JSON.parse(data.tunneling_locations) : data.tunneling_locations;
                locations.forEach(loc => addLocationField('tunneling', loc));
            }
            if (data.undermining_locations) {
                const locations = typeof data.undermining_locations === 'string' ? JSON.parse(data.undermining_locations) : data.undermining_locations;
                locations.forEach(loc => addLocationField('undermining', loc));
            }
        } catch(e) {
            console.error("Failed to parse dynamic location fields:", e);
        }

        // FIX 1: Ensure L/W/D fields are always ENABLED for editing and remove visual disabled state
        measurementFields.forEach(id => {
            const input = document.getElementById(id);
            input.disabled = false;
            input.classList.remove('bg-gray-100');
        });

        // *** NEW: Handle Graft Audit Status ***
        if (data.graft_attestation_timestamp) {
            // If a graft audit is already signed for this assessment
            openGraftAuditModalBtn.disabled = true;
            openGraftAuditModalBtn.textContent = 'Graft Audit Completed';
            openGraftAuditModalBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
            openGraftAuditModalBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
            graftAuditStatus.textContent = `Audit signed on ${new Date(data.graft_attestation_timestamp).toLocaleString()}`;
            graftAuditStatus.classList.remove('hidden');
        } else {
            // Reset to default state (will be enabled once assessment is active)
            openGraftAuditModalBtn.disabled = true; // Disabled until assessment is loaded
            openGraftAuditModalBtn.textContent = 'Apply Graft / Complete Audit';
            openGraftAuditModalBtn.classList.add('bg-green-600', 'hover:bg-green-700');
            openGraftAuditModalBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
            graftAuditStatus.classList.add('hidden');
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

    // --- LOAD ACTIVE ASSESSMENT & INITIAL DATA ---
    async function loadActiveAssessment(assessments) {
        try {
            if (!appointmentId || !woundId) { return; }

            const response = await fetch(API_ENDPOINTS.GET_ACTIVE_ASSESSMENT);
            const result = await response.json();

            // Find the nearest non-zero measurements from history
            const prevMeasurements = {
                length_cm: getNearestValidMeasurement(assessments, 'length_cm'),
                width_cm: getNearestValidMeasurement(assessments, 'width_cm'),
                depth_cm: getNearestValidMeasurement(assessments, 'depth_cm'),
            };

            if (response.ok && result.success && result.assessment) {
                const data = result.assessment;
                currentAssessmentId = data.assessment_id;
                assessmentFormIdInput.value = currentAssessmentId;

                populateForm(data, prevMeasurements);

                assessmentCard.classList.remove('assessment-disabled');
                setAutosaveStatus('Draft loaded. Changes will autosave.');
                enableMeasurementTools();
                // Enable graft button *if* it's not already signed
                if (!data.graft_attestation_timestamp) {
                    openGraftAuditModalBtn.disabled = false;
                }
                console.log('Active assessment draft loaded successfully:', currentAssessmentId);

            } else {
                console.log('No existing draft found for this visit. Starting fresh.');
                populateForm({}, prevMeasurements); // Load previous measurements into fields
                disableMeasurementTools(); // Disable tools initially
                openGraftAuditModalBtn.disabled = true; // Disable graft button initially
            }

        } catch (error) {
            console.error('Error fetching active assessment:', error);
        }
    }


    async function fetchWoundDetails() {
        try {
            const response = await fetch(API_ENDPOINTS.GET_WOUND_DETAILS);
            if (!response.ok) throw new Error(`API Error (${response.status}): ${(await response.json()).message || 'Failed to fetch details'}`);
            const data = await response.json();

            // CRITICAL CHECK: Ensure patient ID is present
            if (!data.patient || !data.patient.patient_id) {
                console.error("API response missing patient ID:", data);
                throw new Error("Patient metadata missing from API response. Cannot proceed.");
            }
            patientId = parseInt(data.patient.patient_id);
            patientIdInput.value = patientId; // Set the hidden input in the form

            renderPage(data);
            enableCapture(); // Enable interaction only after patientId is set
            await loadActiveAssessment(data.assessments);
            // Measurement tools status is handled by loadActiveAssessment result

        } catch (error) {
            captureStatus.classList.remove('text-gray-500');
            captureStatus.classList.add('text-red-600', 'font-semibold');
            captureStatus.textContent = `CRITICAL ERROR: ${error.message}`;
            historyContainer.innerHTML = `<div class="flex justify-center items-center h-64 bg-red-50 p-4 rounded-lg shadow col-span-full"><svg class="w-6 h-6 text-red-800 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${error.message}</div>`;
            imageGallery.innerHTML = `<div class="flex justify-center items-center h-64 bg-red-50 p-4 rounded-lg shadow col-span-full"><svg class="w-6 h-6 text-red-800 mr-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>${error.message}</div>`;
            console.error('Initial Load Error:', error);
        }
    }


    // --- AUTOSAVE LOGIC ---

    async function saveAssessmentData(isFinalSave = false) {
        if (!currentAssessmentId) {
            if (!isFinalSave) return;
            showMessage(formMessage, 'Please upload a photo first to create an assessment entry.', 'error');
            return;
        }
        if (!patientId || isNaN(patientId)) {
            if (!isFinalSave) return;
            showMessage(formMessage, 'Error: Patient details not fully loaded. Cannot save assessment.', 'error');
            return;
        }

        if (!isFinalSave) { setAutosaveStatus('Autosaving...'); }
        else {
            finalizeAssessmentBtn.disabled = true;
            finalizeAssessmentBtn.textContent = 'Finalizing...';
            setAutosaveStatus('');
        }

        const data = {};

        data.assessment_id = currentAssessmentId;
        data.wound_id = woundId;
        data.patient_id = patientId;
        data.appointment_id = appointmentId;
        data.assessment_date = document.getElementById('assessment_date').value;

        data.length_cm = parseFloat(document.getElementById('length_cm').value) || null;
        data.width_cm = parseFloat(document.getElementById('width_cm').value) || null;
        data.depth_cm = parseFloat(document.getElementById('depth_cm').value) || null;
        data.granulation_percent = parseFloat(document.getElementById('granulation_percent').value) || null;
        data.slough_percent = parseFloat(document.getElementById('slough_percent').value) || null;

        data.granulation_color = document.getElementById('granulation_color').value || null;
        data.granulation_coverage = document.getElementById('granulation_coverage').value || null;
        data.drainage_type = document.getElementById('drainage_type').value || null;
        data.exudate_amount = document.getElementById('exudate_amount').value || null;
        data.odor_present = document.getElementById('odor_present').value || 'No';
        data.debridement_performed = document.getElementById('debridement_performed').value || 'No';
        data.debridement_type = document.getElementById('debridement_type').value || null;
        data.treatments_provided = document.getElementById('treatments_provided').value || null;
        data.tunneling_present = document.getElementById('tunneling_present').value || 'No';
        data.undermining_present = document.getElementById('undermining_present').value || 'No';

        data.periwound_condition = Array.from(document.getElementById('periwound_condition').selectedOptions).map(opt => opt.value);
        data.signs_of_infection = Array.from(document.getElementById('signs_of_infection').selectedOptions).map(opt => opt.value);

        data.tunneling_locations = Array.from(document.querySelectorAll('#tunneling_locations .flex')).map(div => ({
            position: div.querySelector('select').value,
            depth: div.querySelector('input').value
        }));
        data.undermining_locations = Array.from(document.querySelectorAll('#undermining_locations .flex')).map(div => ({
            position: div.querySelector('select').value,
            depth: div.querySelector('input').value
        }));


        try {
            const res = await fetch(API_ENDPOINTS.CREATE_ASSESSMENT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await res.json();

            if (!res.ok) throw new Error(result.message);

            if (!isFinalSave) {
                setAutosaveStatus(`Autosaved at ${new Date().toLocaleTimeString()}`);
            } else {
                showMessage(formMessage, 'Assessment finalized and saved successfully.', 'success');
                fetchWoundDetails();
                assessmentCard.classList.add('assessment-disabled');
                assessmentForm.reset();
                currentAssessmentId = 0;
                window.currentPhotoFile = null;
                disableMeasurementTools();
                openGraftAuditModalBtn.disabled = true; // Disable on finalize
            }

        } catch (error) {
            if (!isFinalSave) {
                setAutosaveStatus('Autosave failed!', true);
                console.error("Autosave Error:", error);
            } else {
                showMessage(formMessage, `Final Save Error: ${error.message}`, 'error');
            }
        } finally {
            if (isFinalSave) {
                finalizeAssessmentBtn.disabled = false;
                finalizeAssessmentBtn.textContent = 'Finalize Assessment';
            }
        }
    }

    const debouncedSave = debounce(saveAssessmentData, 2000);


    // --- PHOTO CAPTURE/UPLOAD LOGIC ---

    async function handlePhotoSelection(file) {
        if (!file) return;

        window.currentPhotoFile = file;

        if (!patientId || isNaN(patientId)) {
            showMessage(uploadMessage, 'Error: Patient details not fully loaded. Cannot start assessment.', 'error');
            console.error('Attempted to start assessment before patientId was available.');
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
        captureControls.classList.add('assessment-disabled');

        try {
            let assessmentId = currentAssessmentId;
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
                assessmentFormIdInput.value = currentAssessmentId;
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

            assessmentCard.classList.remove('assessment-disabled');
            setAutosaveStatus('Assessment in progress: changes will autosave.');
            enableMeasurementTools();
            openGraftAuditModalBtn.disabled = false; // *** NEW: Enable graft button ***
            fetchWoundDetails();

        } catch (error) {
            showMessage(uploadMessage, `Assessment Start Failed: ${error.message}`, 'error');
            console.error('Assessment Start Error:', error);
            captureStatus.classList.remove('hidden');
            captureStatus.textContent = 'Photo capture failed. Try again.';
        } finally {
            captureControls.classList.remove('assessment-disabled');
        }
    }


    // --- EVENT LISTENERS ---

    toggleFileBtn.addEventListener('click', () => {
        photoFileInput.removeAttribute('capture');
        photoFileInput.click();
    });
    toggleCameraBtn.addEventListener('click', () => {
        photoFileInput.setAttribute('capture', 'environment');
        photoFileInput.click();
    });

    photoFileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handlePhotoSelection(e.target.files[0]);
        }
    });


    // 1. L/W/D is now always editable, autosave triggers on input
    assessmentForm.addEventListener('input', (e) => {
        if (currentAssessmentId > 0) {
            debouncedSave();
        }
    });

    // Final submission button
    assessmentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        saveAssessmentData(true);
    });

    // Toggle Accordion functionality
    document.querySelectorAll('.accordion-header').forEach(header => {
        header.addEventListener('click', function() {
            const content = this.nextElementSibling;
            const isVisible = content.style.maxHeight && content.style.maxHeight !== '0px';

            document.querySelectorAll('.accordion-content').forEach(c => {
                if (c !== content) {
                    c.style.maxHeight = '0px';
                    c.previousElementSibling.querySelector('svg').style.transform = 'rotate(0deg)';
                }
            });

            // Toggle Content
            if (isVisible) {
                content.style.maxHeight = '0px';
                this.querySelector('svg').style.transform = 'rotate(0deg)';
            } else {
                content.style.maxHeight = content.scrollHeight + 50 + 'px';
                this.querySelector('svg').style.transform = 'rotate(180deg)';
            }
        });
    });

    // Auto-trigger accordion visibility based on form values
    function triggerAccordionContentVisibility() {
        const grPercent = parseFloat(document.getElementById('granulation_percent').value) || 0;
        document.getElementById('granulation_details').classList.toggle('hidden', grPercent <= 0);

        document.getElementById('tunneling_details_container').classList.toggle('hidden', document.getElementById('tunneling_present').value !== 'Yes');

        document.getElementById('undermining_details_container').classList.toggle('hidden', document.getElementById('undermining_present').value !== 'Yes');

        document.getElementById('debridement_details').classList.toggle('hidden', document.getElementById('debridement_performed').value !== 'Yes');

    }


    // --- DYNAMIC FORM LOGIC (No changes needed) ---

    function setupDynamicFormHandlers() {
        const dynamicChangeHandler = () => {
            triggerAccordionContentVisibility();
            debouncedSave();
        };

        document.getElementById('granulation_percent').oninput = dynamicChangeHandler;
        document.getElementById('tunneling_present').onchange = dynamicChangeHandler;
        document.getElementById('undermining_present').onchange = dynamicChangeHandler;
        document.getElementById('debridement_performed').onchange = dynamicChangeHandler;

        document.getElementById('addTunnelingLocation').onclick = () => {
            addLocationField('tunneling');
            debouncedSave();
        };
        document.getElementById('addUnderminingLocation').onclick = () => {
            addLocationField('undermining');
            debouncedSave();
        };
    }

    function addLocationField(type, initialData = {}) {
        const container = document.getElementById(`${type}_locations`);
        const index = container.children.length;
        const locationDiv = document.createElement('div');
        locationDiv.className = 'flex items-center space-x-2 my-1';

        let optionsHtml = '<option value="">Location</option>';
        for (let i = 1; i <= 12; i++) {
            optionsHtml += `<option value="${i}">${i} o'clock</option>`;
        }

        const positionValue = initialData.position || '';
        const depthValue = initialData.depth || '';

        locationDiv.innerHTML = `
            <div class="flex-1"><select name="${type}_locations[${index}][position]" class="form-input bg-white text-sm" data-autosave-field="true">${optionsHtml}</select></div>
            <div class="flex-1"><input type="number" step="0.1" name="${type}_locations[${index}][depth]" class="form-input text-sm" placeholder="Depth (cm)" value="${depthValue}" data-autosave-field="true"></div>
            <button type="button" class="text-red-500 hover:text-red-700 remove-location-btn">&times;</button>`;
        container.appendChild(locationDiv);

        if (positionValue) {
            locationDiv.querySelector('select').value = positionValue;
        }

        locationDiv.querySelector('.remove-location-btn').addEventListener('click', () => {
            locationDiv.remove();
            debouncedSave();
        });
        locationDiv.querySelectorAll('[data-autosave-field="true"]').forEach(input => {
            input.addEventListener('input', debouncedSave);
            input.addEventListener('change', debouncedSave);
        });
    }

    // --- CHART/HISTORY RENDERING (No changes needed) ---

    function renderImageGallery(images) {
        if (!images || images.length === 0) {
            imageGallery.innerHTML = '<p class="text-center text-gray-500 py-8 col-span-full">No photos uploaded.</p>';
            return;
        }
        imageGallery.innerHTML = images.map(img => `
            <div class="image-gallery-card">
                <a href="${img.image_path}" target="_blank"><img src="${img.image_path}" alt="${img.image_type}" onerror="this.onerror=null;this.src='https://placehold.co/200x200/cccccc/333333?text=Image+Error';"></a>
                <div class="p-3 bg-gray-50 border-t flex-grow flex flex-col">
                    <h5 class="font-bold text-gray-800 flex-grow">${img.image_type}</h5>
                    <p class="text-xs text-gray-500">Assessment ID: ${img.assessment_id || 'N/A'}</p>
                    <div class="mt-2 space-y-2">
                        ${img.assessment_id ? `<button data-assessment-id="${img.assessment_id}" class="view-assessment-btn w-full bg-blue-100 text-blue-800 font-bold py-2 px-4 rounded-md hover:bg-blue-200 transition text-sm">View Assessment</button>` : ''}
                        <button data-image-id="${img.image_id}" class="delete-photo-btn w-full bg-red-100 text-red-800 font-bold py-2 px-4 rounded-md hover:bg-red-200 transition text-sm">Delete Photo</button>
                    </div>
                </div>
            </div>`).join('');
    }

    function renderHistoryTable(assessments) {
        if (!assessments || assessments.length === 0) {
            historyContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No assessments recorded.</p>';
            return;
        }
        const tableRows = assessments.map(asm => `
            <tr class="border-b border-gray-200 hover:bg-gray-50 text-sm">
                <td class="px-4 py-3 whitespace-nowrap">${asm.assessment_date}</td>
                <td class="px-4 py-3">${asm.length_cm || '-'}x${asm.width_cm || '-'}<br><small>${asm.depth_cm || '-'}d</small></td>
                <td class="px-4 py-3">${asm.drainage_type || 'N/A'} (${asm.exudate_amount || 'N/A'})</td>
                <td class="px-4 py-3">${asm.graft_attestation_timestamp ? '<span class="font-bold text-green-700">[Graft Applied]</span><br>' : ''}${asm.treatments_provided ? asm.treatments_provided.substring(0, 50) + '...' : 'No treatments recorded.'}</td>
                <td class="px-4 py-3 whitespace-nowrap">
                    <button data-assessment-id="${asm.assessment_id}" class="view-assessment-btn text-blue-600 hover:text-blue-800 text-xs font-semibold">View</button>
                </td>
            </tr>`).join('');

        historyContainer.innerHTML = `<table class="min-w-full"><thead class="bg-gray-800 text-white"><tr><th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Date</th><th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">L x W (D)</th><th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Drainage</th><th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Treatments</th><th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Action</th></tr></thead><tbody class="bg-white">${tableRows}</tbody></table>`;
    }

    function renderPage(data) {
        woundHeader.textContent = `Wound: ${data.details.location} (${data.details.wound_type})`;
        document.getElementById('patient-name-subheader').textContent = `Patient: ${data.patient.first_name} ${data.patient.last_name}`;

        renderHistoryTable(data.assessments);
        renderImageGallery(data.images);
    }

    function populateAndShowAssessmentModal(data) {
        const V = (value) => value || 'N/A';

        const formatJSONList = (jsonString) => {
            const array = parseMultiSelect(jsonString);
            return array.length > 0 ? array.join(', ') : 'None';
        };

        let locationsHTML = '';
        if (data.tunneling_present === 'Yes' && data.tunneling_locations) {
            try {
                const locations = JSON.parse(data.tunneling_locations);
                locationsHTML += `<h6>Tunneling Locations:</h6><ul class="list-disc list-inside text-sm">${locations.map(loc => `<li>${V(loc.position)} o'clock, ${V(loc.depth)} cm deep</li>`).join('')}</ul>`;
            } catch (e) { console.error("Could not parse tunneling locations", e); }
        }
        if (data.undermining_present === 'Yes' && data.undermining_locations) {
            try {
                const locations = JSON.parse(data.undermining_locations);
                locationsHTML += `<h6 class="mt-2">Undermining Locations:</h6><ul class="list-disc list-inside text-sm">${locations.map(loc => `<li>${V(loc.position)} o'clock, ${V(loc.depth)} cm deep</li>`).join('')}</ul>`;
            } catch (e) { console.error("Could not parse undermining locations", e); }
        }

        // *** NEW: Graft Audit Details ***
        let graftAuditHTML = '';
        if (data.graft_attestation_timestamp) {
            graftAuditHTML = `
                <div class="md:col-span-2 border-t pt-2 mt-2 bg-green-50 p-3 rounded-lg border border-green-300">
                    <h5 class="font-semibold text-green-800 mb-1">Graft Audit Details (Signed)</h5>
                    <p><span class="detail-label">Product:</span><span class="detail-value">${V(data.graft_product_name)} (App #${V(data.graft_application_num)})</span></p>
                    <p><span class="detail-label">Serial/Lot:</span><span class="detail-value">${V(data.graft_serial)} / ${V(data.graft_lot)}</span></p>
                    <p><span class="detail-label">Usage:</span><span class="detail-value">${V(data.graft_used_cm)}cm² Used, ${V(data.graft_discarded_cm)}cm² Discarded</span></p>
                    <p><span class="detail-label">Signed By:</span><span class="detail-value">Clinician ID ${V(data.graft_attestation_user_id)} at ${new Date(data.graft_attestation_timestamp).toLocaleString()}</span></p>
                    ${data.graft_serial_photo_path ? `<p><span class="detail-label">Serial Photo:</span><a href="${data.graft_serial_photo_path}" target="_blank" class="text-blue-600 hover:underline ml-2">View Image</a></p>` : ''}
                </div>`;
        }

        viewAssessmentContent.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div><span class="detail-label">Date:</span><span class="detail-value">${V(data.assessment_date)}</span></div>
                <div><span class="detail-label">Appointment ID:</span><span class="detail-value">${V(data.appointment_id)}</span></div>
                <div class="md:col-span-2 border-t pt-2 mt-2">
                    <h5 class="font-semibold text-gray-700 mb-1">Measurements</h5>
                    <p><span class="detail-label">L x W x D:</span><span class="detail-value">${V(data.length_cm)} x ${V(data.width_cm)} x ${V(data.depth_cm)} cm</span></p>
                    ${locationsHTML}
                </div>
                <div class="md:col-span-2 border-t pt-2 mt-2">
                    <h5 class="font-semibold text-gray-700 mb-1">Characteristics</h5>
                    <p><span class="detail-label">Tissue (G/S):</span><span class="detail-value">${V(data.granulation_percent)}% Granulation, ${V(data.slough_percent)}% Slough</span></p>
                    <p><span class="detail-label">Granulation Details:</span><span class="detail-value">${V(data.granulation_color)} (${V(data.granulation_coverage)})</span></p>
                    <p><span class="detail-label">Drainage:</span><span class="detail-value">${V(data.exudate_amount)} ${V(data.drainage_type)}</span></p>
                    <p><span class="detail-label">Odor:</span><span class="detail-value">${V(data.odor_present)}</span></p>
                    <p><span class="detail-label">Periwound:</span><span class="detail-value">${formatJSONList(data.periwound_condition)}</span></p>
                    <p><span class="detail-label">Infection Signs:</span><span class="detail-value">${formatJSONList(data.signs_of_infection)}</span></p>
                </div>
                 <div class="md:col-span-2 border-t pt-2 mt-2">
                    <h5 class="font-semibold text-gray-700 mb-1">Debridement</h5>
                    <p><span class="detail-label">Performed:</span><span class="detail-value">${V(data.debridement_performed)}</span></p>
                    <p><span class="detail-label">Type:</span><span class="detail-value">${V(data.debridement_type)}</span></p>
                </div>
                <div class="md:col-span-2 border-t pt-2 mt-2">
                    <h5 class="font-semibold text-gray-700 mb-1">Treatment Plan</h5>
                    <p class="text-gray-800 whitespace-pre-wrap">${V(data.treatments_provided)}</p>
                </div>
                ${graftAuditHTML} <!-- *** NEW: Display Graft Info *** -->
            </div>`;
        viewAssessmentModal.classList.remove('hidden');
        viewAssessmentModal.classList.add('flex');
    }


    // --- MEASUREMENT MODAL LOGIC (No changes) ---

    closeAIMeasureModalBtn.addEventListener('click', () => aiMeasurementModal.classList.add('hidden'));

    openAIMeasureModalBtn.addEventListener('click', () => {
        if (!window.currentPhotoFile) {
            showMessage(uploadMessage, 'Please upload a photo first.', 'error');
            return;
        }
        aiMeasurementModal.classList.remove('hidden');
        aiMeasurementModal.classList.add('flex');

        const reader = new FileReader();
        reader.onload = function(e) {
            aiImagePreview.src = e.target.result;
            aiImagePreview.classList.remove('hidden');
            aiPlaceholderText.classList.add('hidden');
        }
        reader.readAsDataURL(window.currentPhotoFile);

        aiMeasureSubmitBtn.disabled = false;
        aiResultsDiv.classList.add('hidden');
        useAIMeasurementsBtn.disabled = true;
    });

    aiMeasureForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        aiMeasureSubmitBtn.disabled = true;
        aiMeasureSubmitBtn.innerHTML = '<div class="ai-spinner"></div> Analyzing...';

        const formData = new FormData();
        formData.append('wound_photo', window.currentPhotoFile);

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
        } catch (error) {
            showMessage(uploadMessage, `AI Analysis Failed: ${error.message}`, 'error');
        } finally {
            aiMeasureSubmitBtn.disabled = false;
            aiMeasureSubmitBtn.textContent = 'Run AI Analysis';
        }
    });

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

    // Manual Measurement Modal Logic
    const useMeasurementsBtn = document.getElementById('useMeasurementsBtn');

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

    closeManualModalBtn.addEventListener('click', () => {
        document.getElementById('manualMeasurementModal').classList.add('hidden');
    });


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
        if (e.target.classList.contains('delete-photo-btn')) {
            if (confirm('Are you sure you want to delete this photo? This may also delete its associated assessment.')) {
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
                    fetchWoundDetails();
                } catch (error) {
                    showMessage(uploadMessage, `Error: ${error.message}`, 'error');
                    console.error("Delete Photo Error:", error);
                }
            }
        }
    });

    closeViewModalBtn.addEventListener('click', () => {
        viewAssessmentModal.classList.add('hidden');
        viewAssessmentModal.classList.remove('flex');
    });

    // --- AI Treatment Plan (No changes) ---
    generatePlanBtn.addEventListener('click', async () => {
        const originalButtonText = generatePlanBtn.innerHTML;
        generatePlanBtn.innerHTML = '<div class="spinner-small mx-auto"></div>';
        generatePlanBtn.disabled = true;

        const formData = new FormData(assessmentForm);
        const data = Object.fromEntries(formData.entries());
        data.signs_of_infection = Array.from(document.getElementById('signs_of_infection').selectedOptions).map(opt => opt.value);
        data.periwound_condition = Array.from(document.getElementById('periwound_condition').selectedOptions).map(opt => opt.value);

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
        } catch (error) {
            showMessage(formMessage, `AI Error: ${error.message}`, 'error');
        } finally {
            generatePlanBtn.innerHTML = originalButtonText;
            generatePlanBtn.disabled = false;
        }
    });


    // --- *** NEW: GRAFT AUDIT MODAL LOGIC *** ---

    function setSmartCheck(container, checkbox, isVerified, text) {
        checkbox.checked = isVerified;
        container.classList.remove('verified', 'failed', 'bg-gray-50', 'border-gray-300');
        if (isVerified) {
            container.classList.add('verified');
            checkbox.nextElementSibling.textContent = `${text} (Verified)`;
        } else {
            container.classList.add('failed');
            checkbox.nextElementSibling.textContent = `${text} (Not Met)`;
        }
    }

    function validateGraftAuditForm() {
        let allValid = true;

        // 1. Check all required checkboxes
        if (!graftCheckConservative.checked) allValid = false;
        if (!graftCheckInfection.checked) allValid = false;
        if (!graftCheckNecrosis.checked) allValid = false;
        if (!graftCheckSize.checked) allValid = false;
        if (!graftCheckConditionsManaged.checked) allValid = false;
        if (!graftAttestationCheckbox.checked) allValid = false;

        // 2. Check all required text/number fields
        if (graftProductName.value.trim() === '') allValid = false;
        if (graftApplicationNum.value.trim() === '') allValid = false;
        if (graftSerial.value.trim() === '') allValid = false;
        if (graftLot.value.trim() === '') allValid = false;
        if (graftUsedCm.value.trim() === '') allValid = false;
        if (graftDiscardedCm.value.trim() === '') allValid = false;

        // 3. Check for serial photo
        if (!window.graftPhotoFile) allValid = false;

        // Enable/disable the submit button
        submitGraftAuditBtn.disabled = !allValid;
    }

    openGraftAuditModalBtn.addEventListener('click', () => {
        // --- 1. Run the "Smart Checklist" ---

        // Check for infection
        const infectionValues = Array.from(document.getElementById('signs_of_infection').selectedOptions).map(opt => opt.value);
        const hasInfection = infectionValues.length > 0;
        setSmartCheck(graftCheckInfectionContainer, graftCheckInfection, !hasInfection, 'No Active Infection');

        // Check for necrosis
        const sloughPercent = parseFloat(document.getElementById('slough_percent').value) || 0;
        const hasNecrosis = sloughPercent > 0;
        setSmartCheck(graftCheckNecrosisContainer, graftCheckNecrosis, !hasNecrosis, 'No Necrotic Debris or Exudate');

        // Check for size
        const length = parseFloat(document.getElementById('length_cm').value) || 0;
        const width = parseFloat(document.getElementById('width_cm').value) || 0;
        const size = length * width;
        const isSizeMet = size >= 1.0;
        setSmartCheck(graftCheckSizeContainer, graftCheckSize, isSizeMet, 'Ulcer Size ≥1cm²');

        // --- 2. Reset form and show modal ---
        graftAuditForm.reset();
        window.graftPhotoFile = null;
        graftPhotoStatus.textContent = 'Click to upload photo...';
        graftPhotoStatus.classList.remove('text-green-700', 'font-semibold');
        graftModalMessage.classList.add('hidden');

        // Re-check the smart fields since reset() clears them
        graftCheckInfection.checked = !hasInfection;
        graftCheckNecrosis.checked = !hasNecrosis;
        graftCheckSize.checked = isSizeMet;

        validateGraftAuditForm(); // Run validation to set initial button state
        graftAuditModal.classList.remove('hidden');
        graftAuditModal.classList.add('flex');
    });

    function closeGraftModal() {
        graftAuditModal.classList.add('hidden');
        graftAuditModal.classList.remove('flex');
    }
    closeGraftAuditModalBtn.addEventListener('click', closeGraftModal);
    cancelGraftAuditBtn.addEventListener('click', closeGraftModal);

    // Add validation listeners to all fields in the graft modal
    allGraftAuditFields.forEach(field => {
        field.addEventListener('input', validateGraftAuditForm);
        field.addEventListener('change', validateGraftAuditForm);
    });

    // Handle Graft Serial Photo Upload Button
    graftSerialPhotoBtn.addEventListener('click', () => {
        graftSerialPhotoInput.click();
    });

    graftSerialPhotoInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            window.graftPhotoFile = e.target.files[0];
            graftPhotoStatus.textContent = window.graftPhotoFile.name;
            graftPhotoStatus.classList.add('text-green-700', 'font-semibold');
        } else {
            window.graftPhotoFile = null;
            graftPhotoStatus.textContent = 'Click to upload photo...';
            graftPhotoStatus.classList.remove('text-green-700', 'font-semibold');
        }
        validateGraftAuditForm(); // Re-validate after file change
    });


    // Handle Final Graft Audit Submission
    submitGraftAuditBtn.addEventListener('click', async () => {
        submitGraftAuditBtn.disabled = true;
        submitGraftAuditBtn.innerHTML = '<div class="ai-spinner"></div> Signing...';

        try {
            const formData = new FormData();

            // Add all form fields
            formData.append('assessment_id', currentAssessmentId);
            formData.append('graft_conservative_care_failed', graftCheckConservative.checked);
            formData.append('graft_conditions_managed', graftCheckConditionsManaged.checked);
            formData.append('graft_product_name', graftProductName.value);
            formData.append('graft_application_num', graftApplicationNum.value);
            formData.append('graft_serial', graftSerial.value);
            formData.append('graft_lot', graftLot.value);
            formData.append('graft_batch', graftBatch.value);
            formData.append('graft_used_cm', graftUsedCm.value);
            formData.append('graft_discarded_cm', graftDiscardedCm.value);
            formData.append('graft_justification', graftJustification.value);
            // Add the file
            if (window.graftPhotoFile) {
                formData.append('graft_serial_photo', window.graftPhotoFile);
            }

            const response = await fetch(API_ENDPOINTS.SAVE_GRAFT_AUDIT, {
                method: 'POST',
                body: formData // No Content-Type header needed, browser sets it for FormData
            });

            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.message || 'An unknown error occurred.');
            }

            // Success!
            showMessage(formMessage, 'Graft audit saved and signed successfully.', 'success');
            closeGraftModal();
            fetchWoundDetails(); // Refresh the whole page to update history and lock the button

        } catch (error) {
            console.error('Graft Audit Save Error:', error);
            showMessage(graftModalMessage, `Error: ${error.message}`, 'error');
        } finally {
            submitGraftAuditBtn.disabled = false;
            submitGraftAuditBtn.innerHTML = 'Submit and Sign';
        }
    });


    // --- INITIALIZATION ---
    fetchWoundDetails();
    setupDynamicFormHandlers();
});