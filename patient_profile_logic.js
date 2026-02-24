// Filename: patient_profile_logic.js
// Description: Manages the new tabbed UI, wound dashboard, and demographics for the patient profile page.

document.addEventListener('DOMContentLoaded', function () {
    // Retrieve variables passed from PHP
    const patientId = window.patientId;
    const userRole = window.userRole;

    const profileContainer = document.getElementById('profile-container');
    const patientNameHeader = document.getElementById('patient-name-header');
    const pageMessage = document.getElementById('page-message');
    const quickActionsContainer = document.getElementById('quick-actions-container');

    // --- Constants for Searchable Lists ---
    const WOUND_LOCATIONS = [
        "Head/Scalp", "Face", "Neck", "Chest (Left)", "Chest (Right)", "Arm (Left Upper)", "Arm (Right Upper)",
        "Abdomen (Left Upper Quadrant)", "Abdomen (Left Lower Quadrant)", "Groin (Left)", "Groin (Right)",
        "Thigh (Left Anterior)", "Thigh (Right Anterior)", "Knee (Left)", "Knee (Right)", "Shin (Left)", "Shin (Right)",
        "Ankle (Left)", "Ankle (Right)", "Foot (Left Dorsum)", "Foot (Right Dorsum)",
        "Head/Scalp (Posterior)", "Neck (Posterior)", "Shoulder (Left)", "Shoulder (Right)",
        "Back (Upper)", "Elbow (Left)", "Elbow (Right)", "Back (Mid)", "Back (Lower)",
        "Coccyx/Sacrum", "Buttock (Left)", "Buttock (Right)", "Thigh (Left Posterior)", "Thigh (Right Posterior)",
        "Knee (Left Posterior)", "Knee (Right Posterior)", "Calf (Left)", "Calf (Right)",
        "Heel (Left)", "Heel (Right)", "Foot (Left Plantar)", "Foot (Right Plantar)"
    ];

    const WOUND_TYPES = [
        "Arterial Ulcer", "Burn (Specify Degree)", "Diabetic Foot Ulcer", "Fungating Wound", "Incontinence Associated Dermatitis (IAD)",
        "Kennedy Terminal Ulcer", "Malignant Wound", "Moisture Associated Skin Damage (MASD)", "Pressure Injury (HAPU)",
        "Pressure Injury (Community Acquired)", "Pressure Injury (Unstageable)", "Surgical Dehiscence", "Surgical Site Infection",
        "Skin Tear", "Traumatic Wound", "Venous Stasis Ulcer", "Other (Specify in Notes)"
    ];

    // Add Wound Modal
    const addWoundModal = document.getElementById('addWoundModal');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const addWoundForm = document.getElementById('addWoundForm');
    const modalMessage = document.getElementById('modal-message');

    // --- Add Wound Form Submit Handler ---
    if (addWoundForm) {
        addWoundForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = addWoundForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';

            const formData = new FormData(addWoundForm);
            const payload = Object.fromEntries(formData.entries());
            // Ensure patient_id is set
            payload.patient_id = patientId;

            try {
                const response = await fetch('api/create_wound.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();

                if (response.ok && result.message) {
                    showMessage(pageMessage, 'Wound added successfully.', 'success');
                    addWoundModal.classList.add('hidden');
                    addWoundModal.classList.remove('flex');
                    fetchPatientProfile(); // Refresh
                } else {
                    throw new Error(result.message || 'Failed to add wound.');
                }
            } catch (error) {
                showMessage(modalMessage, error.message, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        });
    }

    // --- FIX: Add event listeners for closing the wound modal ---
    const closeWoundModal = () => {
        if (addWoundModal) {
            addWoundModal.classList.add('hidden');
            addWoundModal.classList.remove('flex');
        }
    };

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeWoundModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeWoundModal);

    // Insurance Modal Elements
    const insuranceModal = document.getElementById('insuranceModal');
    const closeInsuranceModalBtn = document.getElementById('closeInsuranceModalBtn');
    const cancelInsuranceModalBtn = document.getElementById('cancelInsuranceModalBtn');
    const insuranceForm = document.getElementById('insuranceForm');
    const insuranceModalMessage = document.getElementById('insurance-modal-message');

    // Upload Document Modal Elements
    const uploadDocumentModal = document.getElementById('uploadDocumentModal');
    const closeUploadModalBtn = document.getElementById('closeUploadModalBtn');
    const cancelUploadModalBtn = document.getElementById('cancelUploadModalBtn');
    const uploadDocumentForm = document.getElementById('uploadDocumentForm');
    const uploadModalMessage = document.getElementById('upload-modal-message');

    // Log Communication Modal Elements
    const logCommunicationModal = document.getElementById('logCommunicationModal');
    const closeCommModalBtn = document.getElementById('closeCommModalBtn');
    const cancelCommModalBtn = document.getElementById('cancelCommModalBtn');
    const logCommunicationForm = document.getElementById('logCommunicationForm');
    const commModalMessage = document.getElementById('comm-modal-message');


    // Insurance Search Fields
    const providerNameSearch = document.getElementById('provider_name_search');
    const providerNameHidden = document.getElementById('provider_name');
    const providerListContainer = document.getElementById('provider_list_container');

    // Diagnosis Helper Modal
    const diagnosisModal = document.getElementById('diagnosisHelperModal');
    const openDiagnosisHelperBtn = document.getElementById('openDiagnosisHelperBtn');
    const closeDiagnosisHelperBtn = document.getElementById('closeDiagnosisHelperBtn');
    const applyDiagnosisBtn = document.getElementById('applyDiagnosisBtn');
    const clearDiagnosisHelperBtn = document.getElementById('clearDiagnosisHelperBtn');
    const diagnosisChecklistForm = document.getElementById('diagnosisChecklistForm');
    const diagnosisInput = document.getElementById('diagnosis');

    // Chart Modal
    const chartModal = document.getElementById('progressChartModal');
    const closeChartModalBtn = document.getElementById('closeChartModalBtn');
    const chartModalTitle = document.getElementById('chart-modal-title');
    let woundProgressChart = null;
    let clinicalTrendChart = null;

    // --- SVG Body Map String ---
    const BODY_MAP_SVG = `
    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 200 400" xml:space="preserve">
        <g id="body-map-svg-group">
            <path class="body-map-svg-path" d="M100.3,64.2c-0.4-0.2-0.8-0.2-1.2,0c-1.3,0.5-2.7,0.8-4.2,0.8c-1.5,0-2.9-0.3-4.2-0.8c-0.4-0.2-0.8-0.2-1.2,0c-1.2,0.5-2.3,1.3-3.2,2.3c-1.2,1.4-2.1,3.1-2.5,4.9c-0.1,0.5,0,1,0.3,1.4c0.1,0.1,0.1,0.2,0.2,0.3c0.4,0.4,1,0.6,1.5,0.4c0.2,0,0.5-0.1,0.7-0.1c0.8-0.3,1.5-0.7,2.2-1.1c0.6-0.4,1.1-0.9,1.6-1.4c0.2-0.2,0.4-0.4,0.6-0.6c0.6-0.6,1.4-1,2.3-1.2c0.7-0.1,1.3-0.1,2,0c0.7,0.1,1.3,0.3,2,0.7c0.6,0.4,1.1,0.9,1.6,1.4c0.2-0.2,0.4-0.4,0.6-0.6c0.6,0.6,1.4,1,2.3,1.2c0.7,0.1,1.3,0.1,2,0c0.7-0.1,1.3-0.3,2-0.7c0.4-0.2,0.8-0.5,1.2-0.8c0.2-0.1,0.3-0.3,0.5-0.4c0.2-0.2,0.4-0.5,0.4-0.8c0-0.3-0.1-0.6-0.3-0.9c-0.8-1.4-1.9-2.5-3.2-3.3C102.6,65.5,101.5,64.7,100.3,64.2z M93.6,56.7c0,2.6-2.1,4.7-4.7,4.7c-2.6,0-4.7-2.1-4.7-4.7c0-2.6,2.1-4.7,4.7-4.7C91.5,52,93.6,54.1,93.6,56.7z M82.1,56.7c0,2.6-2.1,4.7-4.7,4.7s-4.7-2.1-4.7-4.7c0-2.6,2.1-4.7,4.7-4.7S82.1,54.1,82.1,56.7z M70.6,56.7c0,2.6-2.1,4.7-4.7,4.7c-2.6,0-4.7-2.1-4.7-4.7c0-2.6,2.1-4.7,4.7-4.7C68.5,52,70.6,54.1,70.6,56.7z M56.7,35.8c0,5.4-4.4,9.8-9.8,9.8c-5.4,0-9.8-4.4-9.8-9.8c0-5.4,4.4-9.8,9.8-9.8C52.3,26,56.7,30.4,56.7,35.8z M65,81.4c-0.2,1-0.5,2-0.8,3c-0.3,0.8-0.6,1.6-1,2.4c-1.3,2.6-2.9,5-4.8,7.3c-1.2,1.4-2.5,2.7-3.9,4c-1,0.9-2,1.7-3.1,2.5c-0.2,0.1-0.3,0.2-0.5,0.3c-0.4,0.3-0.8,0.5-1.2,0.8c-1.5,1-3.2,1.8-5,2.3c-1.1,0.3-2.2,0.5-3.3,0.5c-1.1,0-2.2-0.2-3.3-0.5c-1.8-0.5-3.5-1.3-5-2.3c-0.4-0.3-0.8-0.5-1.2-0.8c-0.2-0.1-0.3-0.2-0.5-0.3c-1.1-0.8-2.1-1.6-3.1-2.5c-1.4-1.3-2.7-2.6-3.9-4c-1.9-2.2-3.5-4.7-4.8-7.3c-0.4-0.8-0.7-1.6-1-2.4c-0.3-1-0.6-2-0.8-3c-0.5-2.1-0.7-4.2-0.7-6.3c0-1,0.1-2,0.2-3c0.1-0.8,0.2-1.5,0.4-2.2c0.1-0.5,0.2-0.9,0.3-1.4c0-0.1,0.1-0.2,0.1-0.3c0.1-0.5,0.3-0.9,0.5-1.3c0.1-0.2,0.2-0.4,0.3-0.6c0.2-0.5,0.6-1,1-1.4c1.1-1.1,2.6-1.8,4.1-2c0.4-0.1,0.8-0.1,1.2-0.1c1.5-0.2,3-0.2,4.5,0c0.3,0,0.6,0.1,0.9,0.1c1.2,0.2,2.4,0.6,3.5,1.1c0.3,0.1,0.5,0.2,0.8,0.4c0.2,0.1,0.4,0.2,0.6,0.3c0.8,0.6,1.6,1.3,2.4,2.1c0.3,0.3,0.7,0.7,1,1.1c0.1,0.1,0.1,0.2,0.2,0.3c0.2,0.2,0.3,0.4,0.5,0.6c0.2,0.2,0.3,0.4,0.5,0.6c0.1,0.1,0.1,0.2,0.2,0.3c0.3,0.3,0.7,0.7,1,1.1c0.8,0.8,1.6,1.5,2.4,2.1c0.2,0.1,0.4,0.2,0.6,0.3c0.3,0.1,0.5,0.2,0.8,0.4c1.1,0.5,2.3,0.9,3.5,1.1c0.3,0.1,0.6,0.1,0.9,0.1c1.5,0.2,3,0.2,4.5,0c0.4,0,0.8,0,1.2-0.1c1.5-0.2,3-0.8,4.1-2c0.4-0.4,0.7-0.9,1-1.4c0.1-0.2,0.2-0.4,0.3-0.6c0.2-0.4,0.4-0.8,0.5-1.3c0.1-0.1,0.1-0.2,0.1-0.3c0.1-0.4,0.2-0.9,0.3-1.4c0.1-0.7,0.3-1.4,0.4-2.2c0.1-1,0.2-2,0.2-3C65.7,77.2,65.5,79.3,65,81.4z M97.5,150c-0.2-1.3-0.4-2.6-0.7-3.9c-0.2-1-0.5-2-0.8-2.9c-1-3.2-2.3-6.2-3.8-9.1c-1-1.9-2.1-3.7-3.3-5.5c-0.9-1.4-1.9-2.7-2.9-4c-0.8-1-1.6-2-2.5-2.9c-0.5-0.5-1-1-1.5-1.5c-1-1-2-1.9-3.1-2.7c-1.3-1-2.7-1.8-4.1-2.5c-0.9-0.5-1.8-0.9-2.7-1.3c-0.4-0.2-0.7-0.3-1.1-0.5c-0.7-0.3-1.4-0.5-2-0.7c-1.1-0.4-2.2-0.6-3.4-0.7c-0.6,0-1.1-0.1-1.7-0.1c-0.6,0-1.1,0-1.7,0.1c-1.1,0.1-2.3,0.3-3.4,0.7c-0.7,0.2-1.4,0.4-2,0.7c-0.4,0.1-0.7,0.3-1.1,0.5c-0.9,0.4-1.8,0.8-2.7,1.3c-1.4,0.7-2.8,1.5-4.1,2.5c-1.1,0.8-2.1,1.7-3.1,2.7c-0.5,0.5-1,1-1.5,1.5c-0.9,0.9-1.7,1.9-2.5,2.9c-1.1,1.3-2.1,2.6-2.9,4c-1.2,1.8-2.3,3.6-3.3,5.5c-1.6,2.9-2.9,5.9-3.8,9.1c-0.3,1-0.6,1.9-0.8,2.9c-0.3,1.3-0.5,2.6-0.7,3.9c-0.2,1.7-0.3,3.4-0.3,5.1v19c0,1,0.1,2,0.1,3c0.1,0.9,0.2,1.8,0.4,2.7c0.1,0.5,0.2,1,0.3,1.5c0.1,0.7,0.3,1.4,0.4,2.1c0.2,0.7,0.3,1.3,0.5,2c0.2,0.6,0.4,1.2,0.6,1.8c0.1,0.3,0.2,0.6,0.3,0.9c0.2,0.6,0.5,1.2,0.8,1.7c0.1,0.2,0.2,0.4,0.4,0.6c0.3,0.5,0.6,1,0.9,1.5c0.2,0.3,0.4,0.6,0.6,0.9c0.3,0.5,0.7,0.9,1.1,1.3c0.2,0.2,0.4,0.4,0.6,0.6c0.4,0.4,0.8,0.8,1.2,1.1c0.3,0.2,0.5,0.4,0.8,0.5c0.5,0.3,1,0.6,1.6,0.8c0.4,0.2,0.8,0.3,1.2,0.4c0.6,0.2,1.3,0.4,1.9,0.5c0.5,0.1,1.1,0.2,1.6,0.3c0.7,0.1,1.4,0.1,2.1,0.1c0.7,0,1.4,0,2.1-0.1c0.5-0.1,1.1-0.1,1.6-0.3c0.6-0.1,1.3-0.3,1.9-0.5c0.4-0.1,0.8-0.3,1.2-0.4c0.5-0.2,1.1-0.5,1.6-0.8c0.3-0.1,0.5-0.3,0.8-0.5c0.4-0.3,0.8-0.7,1.2-1.1c0.2-0.2,0.4-0.4,0.6-0.6c0.4-0.4,0.8-0.8,1.1-1.3c0.2-0.3,0.4-0.6,0.6-0.9c0.3-0.5,0.6-1,0.9-1.5c0.1-0.2,0.2-0.4,0.4-0.6c0.3-0.5,0.5-1.1,0.8-1.7c0.1-0.3,0.2-0.6,0.3-0.9c0.2-0.6,0.4-1.2,0.6-1.8c0.2-0.7,0.3-1.3,0.5-2c0.2-0.7,0.3-1.4,0.4-2.1c0.1-0.5,0.2-1,0.3-1.5c0.1-0.9,0.2-1.8,0.4-2.7c0.1-1,0.1-2,0.1-3v-19C97.8,153.4,97.7,151.7,97.5,150z M93.1,235.2c-0.2,1.2-0.4,2.3-0.7,3.5c-0.2,1-0.5,2-0.8,2.9c-0.3,0.9-0.7,1.8-1,2.7c-0.4,0.9-0.8,1.8-1.2,2.6c-0.4,0.8-0.8,1.6-1.3,2.4c-0.5,0.8-1,1.5-1.5,2.2c-0.5,0.7-1,1.3-1.6,2c-0.6,0.6-1.1,1.2-1.7,1.7c-0.6,0.5-1.2,1-1.8,1.5c-0.6,0.4-1.2,0.8-1.9,1.2c-0.6,0.4-1.3,0.7-1.9,1c-0.7,0.3-1.4,0.5-2.1,0.7c-0.7,0.2-1.4,0.3-2.1,0.4c-0.7,0.1-1.3,0.1-2,0.1c-0.7,0-1.3,0-2-0.1c-0.7-0.1-1.4-0.2-2.1-0.4c-0.7-0.2-1.4-0.4-2.1-0.7c-0.7-0.3-1.3-0.6-1.9-1c-0.7-0.4-1.3-0.8-1.9-1.2c-0.6-0.5-1.2-0.9-1.8-1.5c-0.6-0.5-1.2-1.1-1.7-1.7c-0.6-0.6-1.1-1.3-1.6-2c-0.5-0.7-1-1.4-1.5-2.2c-0.5-0.8-0.9-1.6-1.3-2.4c-0.4-0.8-0.8-1.7-1.2-2.6c-0.4-0.9-0.7-1.8-1-2.7c-0.3-0.9-0.5-2.3-0.7-3.5c-0.2-1.3-0.3-2.7-0.3-4v-1.8c0-2,0.1-4,0.3-6c0.2-1.3,0.4-2.7,0.7-4c0.3-1.3,0.6-2.6,1-3.9c0.4-1.3,0.8-2.5,1.2-3.8c0.9-2.4,1.9-4.8,3-7.1c1.1-2.2,2.3-4.4,3.5-6.5c0.6-1.1,1.3-2.1,1.9-3.2c0.6-1,1.3-2,1.9-2.9c0.7-0.9,1.3-1.8,2-2.7c0.7-0.8,1.4-1.6,2.2-2.4c0.4-0.4,0.8-0.8,1.2-1.1c0.4-0.3,0.8-0.6,1.2-0.9c0.4-0.3,0.8-0.5,1.2-0.7c0.4-0.2,0.9-0.4,1.3-0.6c0.5-0.2,0.9-0.3,1.4-0.4c0.5-0.1,1-0.2,1.5-0.3c0.5-0.1,1.1-0.1,1.6-0.1c0.5,0,1,0,1.6,0.1c0.5,0.1,1,0.2,1.5-0.3c0.5,0.1,0.9,0.2,1.4,0.4c0.4,0.2,0.9,0.3,1.3,0.6c0.4,0.2,0.8,0.4,1.2,0.7c0.4,0.3,0.8,0.6,1.2,0.9c0.4,0.4,0.8,0.8,1.2,1.1c0.8,0.8,1.5,1.6,2.2,2.4c0.7,0.9,1.3,1.8,2,2.7c0.7,0.9,1.3,1.9,1.9,2.9c0.6,1,1.3,2.1,1.9,3.2c1.2,2.1,2.4,4.3,3.5,6.5c1.1,2.3,2.1,4.7,3,7.1c0.5,1.2,0.9,2.5,1.2,3.8c0.4,1.3,0.7,2.6,1,3.9c0.3,1.3,0.5,2.7,0.7,4c0.2,2,0.3,4,0.3,6v1.8C93.4,232.5,93.3,233.9,93.1,235.2z"/>
            <path class="body-map-svg-path" d="M129.4,35.8c0,5.4,4.4,9.8,9.8,9.8c5.4,0,9.8-4.4,9.8-9.8c0-5.4-4.4-9.8-9.8-9.8C133.8,26,129.4,30.4,129.4,35.8z M127.9,56.7c0,2.6,2.1,4.7,4.7,4.7c2.6,0,4.7-2.1,4.7-4.7c0-2.6-2.1-4.7-4.7-4.7C130,52,127.9,54.1,127.9,56.7z M139.4,56.7c0,2.6,2.1,4.7,4.7,4.7s4.7-2.1,4.7-4.7c0-2.6-2.1-4.7-4.7-4.7S139.4,54.1,139.4,56.7z M150.9,56.7c0,2.6,2.1,4.7,4.7,4.7c2.6,0,4.7-2.1,4.7-4.7c0-2.6-2.1-4.7-4.7-4.7C153,52,150.9,54.1,150.9,56.7z M167.7,73.8c-0.1-0.5-0.3-0.9-0.5-1.3c-0.1-0.2-0.2-0.4-0.3-0.6c-0.2-0.5-0.6-1-1-1.4c-1.1-1.1-2.6-1.8-4.1-2c-0.4-0.1-0.8-0.1-1.2-0.1c-1.5-0.2-3-0.2-4.5,0c-0.3,0-0.6,0.1-0.9,0.1c-1.2,0.2-2.4,0.6-3.5,1.1c-0.3,0.1-0.5,0.2-0.8,0.4c-0.2,0.1-0.4,0.2-0.6,0.3c-0.8,0.6-1.6,1.3-2.4,2.1c-0.3,0.3-0.7,0.7-1,1.1c-0.1,0.1-0.1,0.2-0.2,0.3c-0.2,0.2-0.3,0.4-0.5,0.6c-0.2,0.2-0.3,0.4-0.5,0.6c-0.1,0.1-0.1,0.2-0.2,0.3c-0.3,0.3-0.7,0.7-1,1.1c-0.8,0.8-1.6,1.5-2.4,2.1c-0.2,0.1-0.4,0.2-0.6,0.3c-0.3,0.1-0.5,0.2-0.8,0.4c-1.1,0.5-2.3,0.9-3.5,1.1c-0.3,0.1-0.6,0.1-0.9,0.1c-1.5,0.2-3,0.2-4.5,0c-0.4,0-0.8,0-1.2-0.1c-1.5-0.2-3-0.8-4.1-2c-0.4-0.4-0.7-0.9-1-1.4c-0.1-0.2-0.2-0.4-0.3-0.6c-0.2-0.4-0.4-0.8-0.5-1.3c-0.1-0.1-0.1-0.2-0.1-0.3c-0.1-0.4-0.2-0.9-0.3-1.4c-0.1-0.7-0.3-1.4-0.4-2.2c-0.1-1-0.2-2-0.2-3c0-2.1,0.2-4.2,0.7-6.3c0.2-1,0.5-2,0.8-3c0.3-0.8,0.6-1.6,1-2.4c1.3-2.6,2.9-5,4.8-7.3c1.2-1.4,2.5-2.7,3.9-4c1-0.9,2-1.7,3.1-2.5c0.2-0.1,0.3-0.2,0.5-0.3c0.4-0.3,0.8-0.5,1.2-0.8c1.5-1,3.2-1.8,5-2.3c1.1-0.3,2.2-0.5,3.3-0.5c1.1,0,2.2,0.2,3.3,0.5c1.8,0.5,3.5,1.3,5,2.3c0.4,0.3,0.8,0.5,1.2-0.8c0.2,0.1,0.3,0.2,0.5,0.3c1.1,0.8,2.1,1.6,3.1,2.5c1.4,1.3,2.7,2.6,3.9,4c1.9,2.2,3.5,4.7,4.8,7.3c0.4,0.8,0.7,1.6,1,2.4c0.3,1,0.6,2,0.8,3c0.5,2.1,0.7,4.2,0.7,6.3c0,1-0.1,2-0.2,3c-0.1,0.8-0.2,1.5-0.4,2.2c-0.1,0.5-0.2,0.9-0.3,1.4c0,0.1-0.1,0.2-0.1,0.3z M165.7,233.4v-1.8c0-2-0.1-4-0.3-6c-0.2-1.3-0.4-2.7-0.7-4c-0.3-1.3-0.6-2.6-1-3.9c-0.4-1.3-0.8-2.5-1.2-3.8c-0.9-2.4-1.9-4.8-3-7.1c-1.1-2.2-2.3-4.4-3.5-6.5c-0.6-1.1-1.3-2.1-1.9-3.2c-0.6-1-1.3-2-1.9-2.9c-0.7-0.9-1.3-1.8-2-2.7c-0.7-0.8-1.4-1.6-2.2-2.4c-0.4-0.4-0.8-0.8-1.2-1.1c-0.4-0.3-0.8-0.6-1.2-0.9c-0.4-0.3-0.8-0.5-1.2-0.7c-0.4-0.2-0.9-0.4-1.3-0.6c-0.5-0.2-0.9-0.3-1.4-0.4c-0.5-0.1-1-0.2-1.5-0.3c-0.5-0.1-1.1-0.1-1.6-0.1c-0.5,0-1,0-1.6,0.1c-0.5,0.1-1,0.2-1.5-0.3c-0.5,0.1-0.9,0.2-1.4,0.4c-0.4,0.2-0.9,0.3-1.3,0.6c-0.4,0.2-0.8,0.4-1.2,0.7c-0.4,0.3-0.8,0.6-1.2,0.9c-0.4,0.4-0.8,0.8-1.2,1.1c-0.8,0.8-1.5,1.6-2.2,2.4c-0.7,0.9-1.3,1.8-2,2.7c-0.7,0.9-1.3,1.9-1.9,2.9c-0.6,1-1.3,2.1-1.9,3.2c-1.2,2.1-2.4,4.3-3.5,6.5c-1.1,2.3-2.1,4.7-3,7.1c-0.5,1.2-0.9,2.5-1.2,3.8c-0.4,1.3-0.7,2.6-1,3.9c-0.3,1.3-0.5,2.7-0.7,4c-0.2,2-0.3,4-0.3,6v1.8c0,1.3,0.1,2.7,0.3,4c0.2,1.2,0.4,2.3,0.7,3.5c0.2,1,0.5,2,0.8,2.9c0.3,0.9,0.7,1.8,1,2.7c0.4,0.9,0.8,1.8,1.2,2.6c0.4,0.8,0.8,1.6,1.3,2.4c0.5,0.8,1,1.5,1.5,2.2c0.5,0.7,1,1.3,1.6,2c0.6,0.6,1.1,1.2,1.7,1.7c0.6,0.5,1.2,1,1.8,1.5c0.6,0.4,1.2,0.8,1.9,1.2c0.6,0.4,1.3,0.7,1.9,1c0.7,0.3,1.4,0.5,2.1,0.7c0.7,0.2,1.4,0.3,2.1,0.4c0.7,0.1,1.3,0.1,2,0.1c0.7,0,1.4,0,2.1-0.1c0.5-0.1,1.1-0.1,1.6-0.3c0.6-0.1,1.3-0.3,1.9-0.5c0.4-0.1,0.8-0.3,1.2-0.4c0.5-0.2,1.1-0.5,1.6-0.8c0.3-0.1,0.5-0.3,0.8-0.5c0.4-0.3,0.8-0.7,1.2-1.1c0.2-0.2,0.4-0.4,0.6-0.6c0.4-0.4,0.8-0.8,1.1-1.3c0.2-0.3,0.4-0.6,0.6-0.9c0.3-0.5,0.6-1,0.9-1.5c0.1-0.2,0.2-0.4,0.4-0.6c0.3-0.5,0.5-1.1,0.8-1.7c0.1-0.3,0.2-0.6,0.3-0.9c0.2-0.6,0.4-1.2,0.6-1.8c0.2-0.7,0.3-1.3,0.5-2c0.2-0.7,0.3-1.4,0.4-2.1c0.1-0.5,0.2-1,0.3-1.5c0.1-0.9,0.2-1.8,0.4-2.7c0.1-1,0.1-2,0.1-3v-19C170.5,153.4,170.4,151.7,170.2,150z"/>
        </g>
    </svg>
    `;

    // --- Core Data: Comprehensive list of major US insurance providers ---
    const US_INSURANCE_PROVIDERS = [
        "Aetna", "Aetna Better Health", "Anthem", "Blue Cross Blue Shield (BCBS)", "Cigna",
        "Clover Health", "Centene", "Elevance Health (Anthem)",
        "Humana", "Kaiser Permanente", "Molina Healthcare", "Oscar Health",
        "UnitedHealth Group (UHC)", "WellCare", "Medicare", "Medicaid"
    ];

    // --- Global variable for policies ---
    let mockInsurancePolicies = []; // Renamed to keep logic consistent with minimal changes, but now holds REAL data

    // --- REAL Insurance API functions ---

    // 1. Fetch Policies from DB
    async function fetchInsurance(patientId) {
        try {
            const response = await fetch(`api/get_patient_insurance.php?patient_id=${patientId}`);
            if (!response.ok) throw new Error('Failed to fetch insurance data');
            return await response.json();
        } catch (error) {
            console.error("Insurance fetch error:", error);
            return []; // Return empty array on failure to prevent UI crash
        }
    }

    // 2. Manage (Create/Update/Delete) Policies
    async function manageInsurance(payload) {
        const response = await fetch('api/manage_insurance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Database error');
        return result;
    }

    // Helper function for UI messages
    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = 'p-3 my-3 rounded-md';
        if (type === 'error') element.classList.add('bg-red-100', 'text-red-800');
        else if (type === 'success') element.classList.add('bg-green-100', 'text-green-800');
        else element.classList.add('bg-blue-100', 'text-blue-800');
        element.classList.remove('hidden');
        setTimeout(() => element.classList.add('hidden'), 3000);
    }

    // --- Searchable Dropdown Logic ---
    function setupSearchableList(searchInput, hiddenInput, listContainer, dataList) {
        searchInput.addEventListener('input', () => {
            const searchTerm = searchInput.value.toLowerCase();
            if (!searchTerm) {
                listContainer.classList.add('hidden');
                return;
            }

            const filteredList = dataList.filter(item => item.toLowerCase().includes(searchTerm));
            listContainer.innerHTML = '';

            if (filteredList.length > 0) {
                filteredList.forEach(item => {
                    const itemElement = document.createElement('div');
                    itemElement.className = 'custom-select-item p-2 hover:bg-blue-50 cursor-pointer';

                    // Highlight the matching part
                    const regex = new RegExp(`(${searchTerm})`, 'gi');
                    itemElement.innerHTML = item.replace(regex, '<strong>$1</strong>');

                    itemElement.addEventListener('click', () => {
                        searchInput.value = item;
                        hiddenInput.value = item;
                        listContainer.classList.add('hidden');
                    });
                    listContainer.appendChild(itemElement);
                });
                listContainer.classList.remove('hidden');
            } else {
                listContainer.classList.add('hidden');
            }
        });

        // Set hidden input value on blur if text matches
        searchInput.addEventListener('blur', () => {
            // Delay to allow click event to register
            setTimeout(() => {
                const exactMatch = dataList.find(item => item.toLowerCase() === searchInput.value.toLowerCase());
                if (exactMatch) {
                    hiddenInput.value = exactMatch;
                    searchInput.value = exactMatch; // Correct capitalization
                } else {
                    // Allow custom values
                    hiddenInput.value = searchInput.value;
                }
                listContainer.classList.add('hidden');
            }, 200);
        });
    }

    // --- Insurance Specific Functions ---
    function renderProviderList(searchTerm = '') {
        const filteredProviders = US_INSURANCE_PROVIDERS.filter(provider =>
            provider.toLowerCase().includes(searchTerm.toLowerCase())
        );

        providerListContainer.innerHTML = '';
        if (filteredProviders.length > 0) {
            filteredProviders.forEach(provider => {
                const item = document.createElement('div');
                item.className = 'custom-select-item hover:bg-blue-50';
                item.textContent = provider;
                item.dataset.value = provider;
                item.addEventListener('click', () => {
                    providerNameSearch.value = provider;
                    providerNameHidden.value = provider;
                    providerListContainer.classList.add('hidden');
                });
                providerListContainer.appendChild(item);
            });
            providerListContainer.classList.remove('hidden');
        } else {
            providerListContainer.classList.add('hidden');
        }
    }

    function openInsuranceModal(policy = null) {
        insuranceForm.reset();
        insuranceModalMessage.classList.add('hidden');
        document.getElementById('modal_insurance_id').value = '';
        providerNameSearch.value = '';
        providerNameHidden.value = '';
        providerListContainer.classList.add('hidden');

        if (policy) {
            document.getElementById('insuranceModalTitle').textContent = `Edit Policy: ${policy.provider_name}`;
            document.getElementById('modal_insurance_id').value = policy.insurance_id;
            providerNameSearch.value = policy.provider_name;
            providerNameHidden.value = policy.provider_name;
            document.getElementById('policy_number').value = policy.policy_number;
            document.getElementById('group_number').value = policy.group_number;
            document.getElementById('priority').value = policy.priority;
        } else {
            document.getElementById('insuranceModalTitle').textContent = 'Add New Insurance Policy';
        }
        insuranceModal.classList.remove('hidden');
        insuranceModal.classList.add('flex');
        renderProviderList('');
    }

    providerNameSearch.addEventListener('input', () => {
        renderProviderList(providerNameSearch.value);
        providerNameHidden.value = providerNameSearch.value.trim();
    });

    providerNameSearch.addEventListener('focus', () => {
        if (providerNameSearch.value.trim() === '') {
            renderProviderList('');
        } else {
            renderProviderList(providerNameSearch.value);
        }
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.custom-select-container')) {
            providerListContainer.classList.add('hidden');
        }
    });

    function renderInsuranceList(policies) {
        if (!policies || policies.length === 0) {
            return '<div class="text-center text-gray-500 py-4">No insurance policies recorded.</div>';
        }
        const listHtml = policies.map(policy => {
            let buttonsHtml = '';
            if (userRole !== 'facility') {
                buttonsHtml = `
                    <div class="space-x-2">
                        <button type="button" data-policy-id="${policy.insurance_id}" class="edit-policy-btn text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                        <button type="button" data-policy-id="${policy.insurance_id}" class="delete-policy-btn text-red-600 hover:text-red-800 text-sm">Delete</button>
                    </div>`;
            }
            return `
            <div class="p-3 border-b border-gray-100 hover:bg-gray-50 flex justify-between items-center">
                <div>
                    <p class="font-semibold text-gray-800">${policy.provider_name} <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800">${policy.priority}</span></p>
                    <p class="text-sm text-gray-600">Policy: ${policy.policy_number} | Group: ${policy.group_number || 'N/A'}</p>
                </div>
                ${buttonsHtml}
            </div>`;
        }).join('');
        return `<div class="insurance-list-container">${listHtml}</div>`;
    }

    async function handleInsuranceFormSubmit(event) {
        event.preventDefault();
        if (userRole === 'facility') return;

        const formData = new FormData(insuranceForm);
        const data = Object.fromEntries(formData.entries());
        const saveBtn = insuranceForm.querySelector('button[type="submit"]');

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const policyData = {
            action: 'save', // Default action
            patient_id: patientId,
            insurance_id: data.insurance_id ? parseInt(data.insurance_id) : null,
            provider_name: data.provider_name,
            policy_number: data.policy_number,
            group_number: data.group_number,
            priority: data.priority
        };

        try {
            const result = await manageInsurance(policyData); // Call real API
            showMessage(insuranceModalMessage, result.message, 'success');

            // Refresh list from DB
            const updatedPolicies = await fetchInsurance(patientId);
            document.getElementById('insurance-list-container').innerHTML = renderInsuranceList(updatedPolicies);

            // Update global store for edit clicks
            mockInsurancePolicies = updatedPolicies;

            setTimeout(() => insuranceModal.classList.add('hidden'), 500);
        } catch (error) {
            showMessage(insuranceModalMessage, `Error saving policy: ${error.message}`, 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Policy';
        }
    }

    closeInsuranceModalBtn.addEventListener('click', () => insuranceModal.classList.add('hidden'));
    cancelInsuranceModalBtn.addEventListener('click', () => insuranceModal.classList.add('hidden'));
    insuranceForm.addEventListener('submit', handleInsuranceFormSubmit);

    document.body.addEventListener('click', async (e) => {
        if (userRole === 'facility') {
            if (e.target.classList.contains('edit-policy-btn') || e.target.classList.contains('delete-policy-btn')) {
                e.preventDefault();
                return;
            }
        }
        if (e.target.classList.contains('edit-policy-btn')) {
            const policyId = parseInt(e.target.dataset.policyId);
            const policyToEdit = mockInsurancePolicies.find(p => p.insurance_id === policyId);
            if (policyToEdit) openInsuranceModal(policyToEdit);
        } else if (e.target.classList.contains('delete-policy-btn')) {
            const policyId = parseInt(e.target.dataset.policyId);
            if (confirm('Are you sure you want to permanently delete this policy?')) {
                try {
                    // Call real API with 'delete' action
                    await manageInsurance({ action: 'delete', insurance_id: policyId });

                    showMessage(pageMessage, 'Policy deleted successfully.', 'success');

                    // Refresh UI
                    const updatedPolicies = await fetchInsurance(patientId);
                    mockInsurancePolicies = updatedPolicies; // Update global var
                    document.getElementById('insurance-list-container').innerHTML = renderInsuranceList(updatedPolicies);
                } catch (error) {
                    showMessage(pageMessage, `Error deleting: ${error.message}`, 'error');
                }
            }
        }
    });

    // --- `generateAssignmentForm` ---
    function generateAssignmentForm(patient, clinicians, facilities) {
        const clinicianOptions = clinicians.map(c =>
            `<option value="${c.user_id}" ${patient.primary_user_id == c.user_id ? 'selected' : ''}>${c.full_name}</option>`
        ).join('');

        const facilityOptions = facilities.map(f =>
            `<option value="${f.facility_id}" ${patient.facility_id == f.facility_id ? 'selected' : ''}>${f.name}</option>`
        ).join('');

        return `
            <form id="assignment-form" class="space-y-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Assigned Doctor</label>
                    <select name="primary_user_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="">-- Select Doctor --</option>
                        ${clinicianOptions}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Assigned Facility</label>
                    <select name="facility_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                        <option value="">-- Select Facility --</option>
                        ${facilityOptions}
                    </select>
                </div>
                <button type="submit" id="save-assignment-btn" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Update Assignment
                </button>
            </form>
        `;
    }

    // --- `setupAssignmentForm` ---
    function setupAssignmentForm(patient) {
        const form = document.getElementById('assignment-form');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            const payload = {
                patient_id: patient.patient_id,
                primary_user_id: formData.get('primary_user_id'),
                facility_id: formData.get('facility_id')
            };

            const btn = document.getElementById('save-assignment-btn');
            const msg = document.getElementById('assignment-message');

            btn.disabled = true;
            btn.textContent = 'Updating...';

            try {
                const response = await fetch('api/update_patient_details.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();
                if (!response.ok) throw new Error(result.message);

                showMessage(msg, 'Assignment updated successfully.', 'success');
            } catch (error) {
                showMessage(msg, `Error: ${error.message}`, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Update Assignment';
            }
        });
    }

    // --- Profile Fetch & Render ---
    async function fetchPatientProfile() {
        if (patientId <= 0) {
            profileContainer.innerHTML = `<div class="bg-red-100 text-red-800 p-4 rounded-lg shadow text-center"><h2 class="font-bold text-lg">Invalid Patient ID</h2><p class="mt-2">No patient ID was provided in the URL. Please select a patient from the list.</p><a href="view_patients.php" class="mt-4 inline-block bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700">View Patients</a></div>`;
            patientNameHeader.querySelector('span').textContent = 'Invalid Patient';
            return;
        }
        try {
            const response = await fetch(`api/get_patient_profile_data.php?id=${patientId}`);
            if (!response.ok) throw new Error((await response.json()).message || 'Failed to fetch data');
            const data = await response.json();

            // FETCH REAL INSURANCE HERE
            mockInsurancePolicies = await fetchInsurance(patientId);

            renderProfile(data);

            // Fetch History & Gallery Data
            fetchPatientHistory();
        } catch (error) {
            profileContainer.innerHTML = `<div class="bg-red-100 text-red-800 p-4 rounded-lg shadow">${error.message}</div>`;
        }
    }

    function renderProfile(data, isEditMode = false) {
        const patient = data.details;
        patientNameHeader.querySelector('span').textContent = `${patient.first_name} ${patient.last_name}`;

        // Add Quick Action buttons to header
        if (userRole !== 'facility') {
            quickActionsContainer.innerHTML = `
                <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-2 w-full md:w-auto">
                    <button onclick="openVisitModeModal(${patient.patient_id}, null, window.userId)" class="bg-green-600 text-white px-4 py-3 md:py-2 rounded-md hover:bg-green-700 font-semibold text-sm flex items-center justify-center w-full md:w-auto shadow-sm">
                        <i data-lucide="stethoscope" class="w-5 h-5 md:w-4 md:h-4 mr-2"></i>Start Visit
                    </button>
                    <a href="add_appointment.php?patient_id=${patient.patient_id}" class="bg-indigo-600 text-white px-4 py-3 md:py-2 rounded-md hover:bg-indigo-700 font-semibold text-sm flex items-center justify-center w-full md:w-auto shadow-sm">
                        <i data-lucide="calendar-plus" class="w-5 h-5 md:w-4 md:h-4 mr-2"></i>New Appt
                    </a>
                    <button id="log-comm-btn" class="bg-gray-700 text-white px-4 py-3 md:py-2 rounded-md hover:bg-gray-800 font-semibold text-sm flex items-center justify-center w-full md:w-auto shadow-sm">
                        <i data-lucide="phone" class="w-5 h-5 md:w-4 md:h-4 mr-2"></i>Log Call
                    </button>
                </div>
            `;
            // Attach listener immediately after creation
            document.getElementById('log-comm-btn').addEventListener('click', () => {
                logCommunicationForm.reset();
                commModalMessage.classList.add('hidden');
                logCommunicationModal.classList.add('flex');
                logCommunicationModal.classList.remove('hidden');
            });
        } else {
            quickActionsContainer.innerHTML = ''; // No actions for facility
        }


        let editButtonHtml = '';
        if (userRole === 'admin' || userRole === 'clinician') {
            editButtonHtml = isEditMode
                ? `<div class="flex space-x-2"><button id="save-demographics-btn" class="text-sm bg-green-600 text-white py-1 px-3 rounded-md hover:bg-green-700">Save</button><button id="cancel-demographics-btn" class="text-sm bg-gray-300 text-gray-800 py-1 px-3 rounded-md hover:bg-gray-400">Cancel</button></div>`
                : `<button id="edit-demographics-btn" class="text-sm bg-blue-600 text-white py-1 px-3 rounded-md hover:bg-blue-700">Edit</button>`;
        }

        let assignmentHtml = (userRole === 'admin' || userRole === 'clinician') ? generateAssignmentForm(patient, data.clinicians, data.facilities) : `
            <div class="text-sm space-y-2 mb-4">
                <p><strong>Assigned Doctor:</strong> <span class="font-semibold text-blue-700">${patient.primary_doctor_name || 'Unassigned'}</span></p>
                <p><strong>Assigned Facility:</strong> <span class="font-semibold text-indigo-700">${patient.facility_name || 'Unassigned'}</span></p>
            </div>
        `;

        let addWoundButtonHtml = '';
        if (userRole === 'admin' || userRole === 'clinician') {
            addWoundButtonHtml = `<button id="addWoundBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition"><svg class="h-5 w-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Add New Wound</button>`;
        }

        let addPolicyButtonHtml = '';
        if (userRole !== 'facility') {
            addPolicyButtonHtml = `<button type="button" id="addInsurancePolicyBtn" class="text-sm bg-blue-600 text-white py-1 px-3 rounded-md hover:bg-blue-700">+ Add Policy</button>`;
        }

        // Tab structure
        profileContainer.innerHTML = `
            <div class="mb-6">
                <div class="border-b border-gray-200 overflow-x-auto">
                    <nav class="-mb-px flex space-x-6 min-w-max" aria-label="Tabs">
                        <button class="tab-btn active-tab whitespace-nowrap py-3 px-1 border-b-2" data-tab="overview">
                            Overview & Wounds
                        </button>
                        <button class="tab-btn inactive-tab whitespace-nowrap py-3 px-1 border-b-2" data-tab="history">
                            History & Docs
                        </button>
                        <button class="tab-btn inactive-tab whitespace-nowrap py-3 px-1 border-b-2" data-tab="activity">
                            Activity Timeline
                        </button>
                    </nav>
                </div>
            </div>

            <div id="overview-tab" class="tab-content space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-1 space-y-6">
                        
                        <div id="ai-insights-card" class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                                    <i data-lucide="sparkles" class="w-5 h-5 mr-2 text-purple-600"></i>
                                    Clinical Insights
                                </h3>
                            </div>
                            <div id="ai-insights-content">
                                <p class="text-sm text-gray-500 text-center mb-3">Click the button to generate AI-powered observations for this patient.</p>
                                <button id="generate-insights-btn" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 font-semibold flex items-center justify-center">
                                    <i data-lucide="wand-2" class="w-4 h-4 mr-2"></i>Generate Insights
                                </button>
                            </div>
                        </div>

                        <div id="at-a-glance" class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h3 class="text-xl font-semibold text-gray-800">At-a-Glance</h3>
                                ${editButtonHtml}
                            </div>
                            <div id="demographics-content" class="space-y-4 text-gray-700">
                                ${isEditMode ? generateDemographicsForm(patient) : renderDemographicsView(patient)}
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex justify-between items-center mb-4 border-b pb-2">
                                <h3 class="text-xl font-semibold text-gray-800">Insurance Policies</h3>
                                ${addPolicyButtonHtml}
                            </div>
                            <div id="insurance-list-container">
                                ${renderInsuranceList(mockInsurancePolicies)} 
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h3 class="text-xl font-semibold mb-4 border-b pb-2 text-gray-800">Doctor & Facility</h3>
                            <div id="assignment-message" class="hidden p-3 my-3 rounded-md"></div>
                            ${assignmentHtml}
                        </div>
                    </div>

                    <div id="wound-dashboard" class="lg:col-span-2 bg-white rounded-lg shadow-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-800">Wound Dashboard</h3>
                            ${addWoundButtonHtml}
                        </div>

                        <!-- Wound map removed -->

                        <div id="wounds-list-container" class="space-y-4">
                            ${renderWoundDashboard(data.wounds)}
                        </div>
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div id="history-tab" class="tab-content hidden space-y-6">
                <!-- Card 0: Assessment History -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Assessment History</h3>
                    <div id="assessment-history-container" class="overflow-x-auto">
                        <p class="text-gray-500 text-sm">Loading history...</p>
                    </div>
                </div>

                <!-- Card 0.5: Photo Gallery -->
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Wound Photo Gallery</h3>
                    <div id="patient-gallery-container" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <p class="text-gray-500 text-sm col-span-full">Loading gallery...</p>
                    </div>
                </div>

                 <!-- Card 1: Medical History -->
                 <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Medical History</h3>
                    <div id="history-content" class="space-y-4 text-gray-700">
                        ${renderHistoryView(patient)} 
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-xl font-semibold text-gray-800">Medications</h3>
                        ${userRole !== 'facility' ? `<a href="patient_medication.php?id=${patient.patient_id}" class="text-sm bg-blue-600 text-white py-1 px-3 rounded-md hover:bg-blue-700 flex items-center"><i data-lucide="edit-3" class="w-3 h-3 mr-1"></i> Manage Meds</a>` : ''}
                    </div>
                    <div id="medications-list-container">
                        ${renderMedicationsList(data.medications)}
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-xl font-semibold text-gray-800">Patient Documents</h3>
                        ${userRole !== 'facility' ? `<button type="button" id="upload-doc-btn" class="text-sm bg-blue-600 text-white py-1 px-3 rounded-md hover:bg-blue-700 flex items-center"><i data-lucide="upload" class="w-3 h-3 mr-1"></i> Upload Doc</button>` : ''}
                    </div>
                    <div id="documents-list-container">
                        ${renderDocumentsList(data.documents)}
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-xl font-semibold text-gray-800">Key Clinical Trends</h3>
                        <select id="trend-chart-metric" class="form-input bg-white w-48 text-sm py-1 px-2 h-auto">
                            <option value="Weight">Weight</option>
                            <option value="Albumin">Albumin</option>
                            <option value="HbA1c">HbA1c</option>
                            <option value="WBC">WBC Count</option>
                        </select>
                    </div>
                    <div id="trend-chart-container" class="relative h-72">
                        <canvas id="clinicalTrendChart"></canvas>
                        <div id="trend-chart-message" class="absolute inset-0 flex items-center justify-center text-gray-500 hidden bg-white bg-opacity-75">
                            </div>
                    </div>
                </div>
            </div>

            <div id="activity-tab" class="tab-content hidden space-y-6">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div class="flex justify-between items-center mb-4 border-b pb-2">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                            <i data-lucide="phone-call" class="w-5 h-5 mr-2 text-blue-600"></i>Communication Log
                        </h3>
                        <div class="flex items-center space-x-2">
                            <button id="print-comm-log-btn" class="bg-gray-100 text-gray-700 text-sm px-3 py-1.5 rounded-md hover:bg-gray-200 font-semibold flex items-center border border-gray-300">
                                <i data-lucide="printer" class="w-4 h-4 mr-1"></i> Print
                            </button>
                            <button id="activity-log-comm-btn" class="bg-blue-600 text-white text-sm px-3 py-1.5 rounded-md hover:bg-blue-700 font-semibold flex items-center">
                                <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Log Communication
                            </button>
                        </div>
                    </div>
                    <div id="comm-log-list">
                        <div class="flex justify-center py-4"><div class="spinner"></div></div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">Clinical Activity Timeline</h3>
                    <div id="timeline-container" class="timeline">
                        ${renderActivityTimeline(data.timeline_events)}
                    </div>
                </div>
            </div>
        `;

        // Render the Interactive Map
        renderInteractiveWoundMap(data.wounds);

        // Attach dynamic event listeners after innerHTML is set
        attachEventListeners(data, isEditMode);

        // Attach event listener for the new policy button
        const addPolicyBtn = document.getElementById('addInsurancePolicyBtn');
        if (addPolicyBtn) {
            addPolicyBtn.addEventListener('click', () => openInsuranceModal());
        }

        // Attach listener for the Upload Document button
        const uploadDocBtn = document.getElementById('upload-doc-btn');
        if (uploadDocBtn) {
            uploadDocBtn.addEventListener('click', () => {
                uploadDocumentForm.reset();
                uploadModalMessage.classList.add('hidden');
                uploadDocumentModal.classList.add('flex');
                uploadDocumentModal.classList.remove('hidden');
            });
        }

        // Attach listener for the Generate Insights button
        const generateInsightsBtn = document.getElementById('generate-insights-btn');
        if (generateInsightsBtn) {
            generateInsightsBtn.addEventListener('click', handleGenerateInsights);
        }

        // Tab switching logic
        const tabButtons = profileContainer.querySelectorAll('.tab-btn');
        const tabContents = profileContainer.querySelectorAll('.tab-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.dataset.tab;
                tabButtons.forEach(btn => btn.classList.replace('active-tab', 'inactive-tab'));
                button.classList.replace('inactive-tab', 'active-tab');
                tabContents.forEach(content => {
                    content.id === `${tabId}-tab` ? content.classList.remove('hidden') : content.classList.add('hidden');
                });
                // Load Communication Logs when Activity tab is clicked
                if (tabId === 'activity') {
                    loadCommunicationLog();
                }
            });
        });

        // Re-render icons for the whole profile
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    // --- AI Insights Handler ---
    async function handleGenerateInsights() {
        const btn = document.getElementById('generate-insights-btn');
        const contentEl = document.getElementById('ai-insights-content');
        if (!btn || !contentEl) return;

        btn.disabled = true;
        contentEl.innerHTML = '<div class="spinner-container"><div class="spinner"></div></div>'; // Show spinner

        try {
            const response = await fetch(`api/generate_patient_summary.php?patient_id=${patientId}`);
            const data = await response.json();

            if (!response.ok) {
                let detail = data.error ? ` (Detail: ${data.error})` : '';
                throw new Error((data.message || 'Failed to generate insights.') + detail);
            }

            // Render the formatted insights
            contentEl.innerHTML = renderInsights(data.insights);

        } catch (error) {
            contentEl.innerHTML = `<p class="error-message">Error: ${error.message}</p>`;
        } finally {
            // Restore button
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="wand-2" class="w-4 h-4 mr-2"></i>Regenerate Insights';
            lucide.createIcons();
        }
    }

    // --- Helper to format AI insights ---
    function renderInsights(insightsText) {
        // Convert markdown-style bullets (* or -) into an HTML list
        const listItems = insightsText.split('\n')
            .map(line => line.trim())
            .filter(line => line.startsWith('* ') || line.startsWith('- '))
            .map(line => `<li>${line.substring(2)}</li>`) // Remove '* ' or '- '
            .join('');

        return `<ul>${listItems}</ul>`;
    }

    // --- `renderWoundDashboard` ---
    function renderWoundDashboard(wounds) {
        if (!wounds || wounds.length === 0) {
            return `
                <div class="text-center py-8 bg-gray-50 rounded-lg border border-dashed border-gray-300">
                    <p class="text-gray-500">No wounds recorded for this patient.</p>
                    <p class="text-sm text-gray-400 mt-1">Click "Add New Wound" to get started.</p>
                </div>
            `;
        }

        return wounds.map(wound => {
            const lastAssessmentDate = wound.latest_assessment_date ? new Date(wound.latest_assessment_date).toLocaleDateString() : 'None';
            const statusColor = wound.status === 'Active' ? 'bg-red-100 text-red-800' : (wound.status === 'Healed' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800');

            // Image handling
            const imageHtml = wound.latest_image_path
                ? `<img src="${wound.latest_image_path}" alt="Wound Image" class="w-16 h-16 object-cover rounded-md border border-gray-200">`
                : `<div class="w-16 h-16 bg-gray-200 rounded-md flex items-center justify-center text-gray-400"><i data-lucide="image-off" class="w-6 h-6"></i></div>`;

            // Delete button logic: Only show if no assessment exists and user is not facility
            let deleteButtonHtml = '';
            if (!wound.latest_assessment_id && userRole !== 'facility') {
                deleteButtonHtml = `
                    <button class="delete-wound-btn text-red-500 hover:text-red-700 p-1" data-wound-id="${wound.wound_id}" title="Delete Wound">
                        <i data-lucide="trash-2" class="w-5 h-5"></i>
                    </button>
                `;
            }

            return `
                <div id="wound-card-${wound.wound_id}" class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition flex flex-col sm:flex-row gap-4">
                    <div class="flex-shrink-0">
                        ${imageHtml}
                    </div>
                    <div class="flex-grow">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-bold text-gray-800 text-lg flex items-center">
                                    ${wound.location}
                                    <span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full ${statusColor}">${wound.status}</span>
                                </h4>
                                <p class="text-sm text-gray-600 font-medium">${wound.wound_type}</p>
                            </div>
                            <div class="flex items-center space-x-2">
                                ${deleteButtonHtml}
                            </div>
                        </div>
                        
                        <div class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm text-gray-600">
                            <p><span class="font-medium text-gray-500">Dimensions:</span> ${wound.latest_dimensions || 'N/A'}</p>
                            <!-- Last Assess removed -->
                            <p class="col-span-2 truncate"><span class="font-medium text-gray-500">Latest Note:</span> ${wound.latest_assessment_text || 'No notes yet.'}</p>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // --- `renderActivityTimeline` ---
    function renderActivityTimeline(events) {
        if (!events || events.length === 0) {
            return '<p class="text-gray-500 text-sm">No recent activity.</p>';
        }

        return events.map(event => {
            const date = new Date(event.timestamp).toLocaleDateString();
            const time = new Date(event.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

            let icon = '';
            let content = '';
            let colorClass = '';

            if (event.type === 'appointment') {
                icon = '<i data-lucide="calendar" class="w-5 h-5 text-white"></i>';
                colorClass = 'bg-blue-500';
                content = `
                    <p class="font-semibold text-gray-800">Appointment: ${event.appointment_type}</p>
                    <p class="text-sm text-gray-600">Status: ${event.status} | with ${event.clinician_name || 'Unknown'}</p>
                `;
            } else if (event.type === 'document') {
                icon = '<i data-lucide="file-text" class="w-5 h-5 text-white"></i>';
                colorClass = 'bg-indigo-500';
                content = `
                    <p class="font-semibold text-gray-800">Document Uploaded: ${event.document_type}</p>
                    <a href="${event.file_path}" target="_blank" class="text-sm text-indigo-600 hover:underline flex items-center mt-1">
                        ${event.file_name} <i data-lucide="external-link" class="w-3 h-3 ml-1"></i>
                    </a>
                `;
            } else {
                // Fallback
                icon = '<i data-lucide="activity" class="w-5 h-5 text-white"></i>';
                colorClass = 'bg-gray-500';
                content = `<p class="text-gray-800">Unknown Activity</p>`;
            }

            return `
                <div class="flex gap-4 pb-6 relative last:pb-0">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 rounded-full ${colorClass} flex items-center justify-center shadow-md z-10 relative">
                            ${icon}
                        </div>
                        <!-- Vertical line -->
                        <div class="absolute top-10 left-5 w-0.5 h-full bg-gray-200 -ml-px -z-0"></div>
                    </div>
                    <div class="flex-grow pt-1">
                        <div class="flex justify-between items-start">
                            <div>${content}</div>
                            <div class="text-right text-xs text-gray-500">
                                <p>${date}</p>
                                <p>${time}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // --- `renderInteractiveWoundMap` ---
    function renderInteractiveWoundMap(wounds) {
        const container = document.getElementById('wound-map-container');
        if (!container) return;

        container.innerHTML = BODY_MAP_SVG; // Inject the SVG
        const svg = container.querySelector('svg');
        if (!svg) return;

        const svgGroup = svg.getElementById('body-map-svg-group');

        wounds.forEach(wound => {
            if (wound.map_x != null && wound.map_y != null) {
                const circle = document.createElementNS("http://www.w3.org/2000/svg", "circle");

                // Convert percentage coordinates back to SVG viewbox coordinates
                // Our viewbox is 200 wide and 400 high
                const svgX = (wound.map_x / 100) * 200;
                const svgY = (wound.map_y / 100) * 400;

                circle.setAttribute("cx", svgX);
                circle.setAttribute("cy", svgY);
                circle.setAttribute("r", "4"); // Dot size
                circle.setAttribute("class", "wound-dot");
                circle.setAttribute("data-wound-id", wound.wound_id);

                // Set color based on status
                if (wound.status === 'Active') {
                    circle.setAttribute("fill", "#EF4444"); // red-500
                } else if (wound.status === 'Healed') {
                    circle.setAttribute("fill", "#22C55E"); // green-500
                } else {
                    circle.setAttribute("fill", "#6B7280"); // gray-500
                }

                svgGroup.appendChild(circle);
            }
        });

        // Add click listener for scrolling
        svg.addEventListener('click', (e) => {
            if (e.target && e.target.classList.contains('wound-dot')) {
                const woundId = e.target.dataset.woundId;
                const woundCard = document.getElementById(`wound-card-${woundId}`);
                if (woundCard) {
                    woundCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Add a temporary highlight
                    woundCard.classList.add('wound-card-highlight');
                    setTimeout(() => {
                        woundCard.classList.remove('wound-card-highlight');
                    }, 2000); // Highlight for 2 seconds
                }
            }
        });
    }

    // --- `setupWoundMapModal` ---
    function setupWoundMapModal() {
        const woundMapModal = document.getElementById('woundMapModal');
        const closeWoundMapBtn = document.getElementById('closeWoundMapBtn');
        const mapTabs = document.querySelectorAll('.map-tab');
        const mapPanes = document.querySelectorAll('.map-pane');
        const hotspots = document.querySelectorAll('.map-hotspot');
        
        const locationSearchInput = document.getElementById('location_search');
        const locationHiddenInput = document.getElementById('location');

        if (!woundMapModal) return;

        // Close button
        if (closeWoundMapBtn) {
            // Remove old listener to avoid duplicates
            const newCloseBtn = closeWoundMapBtn.cloneNode(true);
            closeWoundMapBtn.parentNode.replaceChild(newCloseBtn, closeWoundMapBtn);
            
            newCloseBtn.addEventListener('click', () => {
                woundMapModal.classList.add('hidden');
                woundMapModal.classList.remove('flex');
            });
        }

        // Tab switching
        mapTabs.forEach(tab => {
            tab.addEventListener('click', () => {
                // Deactivate all tabs
                mapTabs.forEach(t => {
                    t.classList.remove('bg-indigo-600', 'text-white');
                    t.classList.add('bg-white', 'text-gray-600', 'hover:text-gray-800');
                });
                // Deactivate all panes
                mapPanes.forEach(pane => pane.classList.add('hidden'));

                // Activate clicked tab
                tab.classList.add('bg-indigo-600', 'text-white');
                tab.classList.remove('bg-white', 'text-gray-600', 'hover:text-gray-800');
                // Activate target pane
                const targetPane = document.getElementById(tab.dataset.target);
                if (targetPane) {
                    targetPane.classList.remove('hidden');
                }
            });
        });

        // Hotspot click
        hotspots.forEach(hotspot => {
            hotspot.addEventListener('click', () => {
                const location = hotspot.dataset.location;
                if (locationSearchInput && locationHiddenInput) {
                    locationSearchInput.value = location;
                    locationHiddenInput.value = location;
                }
                woundMapModal.classList.add('hidden');
                woundMapModal.classList.remove('flex');
            });
        });
    }


    // --- `calculateAge` ---
    function calculateAge(dob) {
        if (!dob) return 'N/A';
        const birthDate = new Date(dob);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }

    // --- `renderDemographicsView` for "At-a-Glance" card ---
    function renderDemographicsView(patient) {
        return `
            <h4 class="font-bold text-gray-700 border-b pb-2 mb-2">General Information</h4>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
                <p><strong>Code:</strong> ${patient.patient_code || 'N/A'}</p>
                <p><strong>DOB:</strong> ${patient.date_of_birth} (${calculateAge(patient.date_of_birth)} yrs)</p>
                <p><strong>Gender:</strong> ${patient.gender}</p>
                <p><strong>Contact:</strong> ${patient.contact_number || 'N/A'}</p>
                <p class="col-span-2"><strong>Email:</strong> ${patient.email || 'N/A'}</p>
                <p class="col-span-2"><strong>Address:</strong> <span class="whitespace-pre-wrap">${patient.address || 'N/A'}</span></p>
            </div>

            <h4 class="font-bold text-gray-700 border-b pb-2 pt-4 mb-2">History</h4>
            <div class="text-sm space-y-2">
                <p><strong>Social History:</strong> ${patient.social_history || 'N/A'}</p>
            </div>

            <h4 class="font-bold text-gray-700 border-b pb-2 pt-4 mb-2">Emergency Contact</h4>
            <div class="grid grid-cols-2 gap-y-2 text-sm">
                <p class="col-span-2"><strong>Name:</strong> ${patient.emergency_contact_name || 'N/A'}</p>
                <p><strong>Relationship:</strong> ${patient.emergency_contact_relationship || 'N/A'}</p>
                <p><strong>Phone:</strong> ${patient.emergency_contact_phone || 'N/A'}</p>
            </div>
        `;
    }

    // --- `renderHistoryView` for the "History & Docs" tab ---
    function renderHistoryView(patient) {
        return `
            <div class="space-y-2 text-sm">
                <h4 class="font-bold text-gray-700 text-base mb-2">Allergies</h4>
                <p class="whitespace-pre-wrap p-3 bg-red-50 border border-red-200 rounded-md text-red-800">
                    ${patient.allergies || 'No known allergies.'}
                </p>
            </div>
            <div class="space-y-2 text-sm mt-4">
                <h4 class="font-bold text-gray-700 text-base mb-2">Past Medical History</h4>
                <p class="whitespace-pre-wrap p-3 bg-gray-50 border border-gray-200 rounded-md">
                    ${patient.past_medical_history || 'N/A'}
                </p>
            </div>
        `;
    }

    // --- `renderMedicationsList` ---
    function renderMedicationsList(medications) {
        if (!medications || medications.length === 0) {
            return '<div class="text-center text-gray-500 py-4">No medications on file.</div>';
        }

        const listHtml = medications.map(med => {
            let statusClass = 'bg-gray-100 text-gray-800';
            if (med.status === 'Active') {
                statusClass = 'bg-green-100 text-green-800';
            } else if (med.status === 'Discontinued') {
                statusClass = 'bg-red-100 text-red-800';
            }

            return `
            <div class="p-3 border-b border-gray-100 hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <p class="font-semibold text-gray-800">${med.drug_name}</p>
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full ${statusClass}">${med.status}</span>
                </div>
                <p class="text-sm text-gray-600">${med.dosage} | ${med.frequency}</p>
                <p class="text-xs text-gray-500 mt-1">Start: ${med.start_date} ${med.end_date ? '| End: ' + med.end_date : ''}</p>
            </div>
            `;
        }).join('');
        return `<div class="medications-list-container">${listHtml}</div>`;
    }

    // --- `renderDocumentsList` ---
    function renderDocumentsList(documents) {
        if (!documents || documents.length === 0) {
            return '<div class="text-center text-gray-500 py-4">No documents on file.</div>';
        }

        const listHtml = documents.map(doc => {
            return `
            <a href="${doc.file_path}" target="_blank" class="p-3 border-b border-gray-100 hover:bg-gray-50 flex justify-between items-center group">
                <div class="flex items-center">
                    <i data-lucide="file-text" class="w-5 h-5 mr-3 text-indigo-600"></i>
                    <div>
                        <p class="font-semibold text-gray-800 group-hover:text-blue-700">${doc.file_name}</p>
                        <p class="text-sm text-gray-600">${doc.document_type} - Uploaded: ${doc.upload_date}</p>
                    </div>
                </div>
                <i data-lucide="download" class="w-5 h-5 text-gray-400 group-hover:text-blue-700"></i>
            </a>
            `;
        }).join('');

        setTimeout(() => lucide.createIcons(), 0);

        return `<div class="documents-list-container">${listHtml}</div>`;
    }


    // --- `generateDemographicsForm` ---
    function generateDemographicsForm(patient) {
        return `
            <form id="demographics-form" class="space-y-4">
                <h4 class="font-bold text-gray-700 border-b pb-2 mb-4">General Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="form-label">First Name</label><input type="text" name="first_name" class="form-input" value="${patient.first_name}" required></div>
                    <div><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-input" value="${patient.last_name}" required></div>
                    <div><label class="form-label">Patient Code</label><input type="text" name="patient_code" class="form-input" value="${patient.patient_code || ''}"></div>
                    <div><label class="form-label">DOB</label><input type="date" name="date_of_birth" class="form-input" value="${patient.date_of_birth}" required></div>
                    <div><label class="form-label">Gender</label>
                        <select name="gender" class="form-input bg-white" required>
                            <option value="Male" ${patient.gender === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${patient.gender === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${patient.gender === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                    <div><label class="form-label">Contact</label><input type="text" name="contact_number" class="form-input" value="${patient.contact_number || ''}"></div>
                </div>
                <div><label class="form-label">Email</label><input type="email" name="email" class="form-input" value="${patient.email || ''}"></div>
                <div><label class="form-label">Address</label><textarea name="address" class="form-input" rows="3">${patient.address || ''}</textarea></div>

                <h4 class="font-bold text-gray-700 border-b pb-2 pt-4 mb-4">Medical & History</h4>
                <div><label class="form-label">Past Medical History</label><textarea name="past_medical_history" class="form-input" rows="3">${patient.past_medical_history || ''}</textarea></div>
                <div><label class="form-label">Social History</label><textarea name="social_history" class="form-input" rows="3">${patient.social_history || ''}</textarea></div>
                <div><label class="form-label">Allergies (Comma-separated)</label><textarea name="allergies" class="form-input" rows="3">${patient.allergies || ''}</textarea></div>

                <h4 class="font-bold text-gray-700 border-b pb-2 pt-4 mb-4">Emergency Contact</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2"><label class="form-label">Contact Name</label><input type="text" name="emergency_contact_name" class="form-input" value="${patient.emergency_contact_name || ''}"></div>
                    <div><label class="form-label">Relationship</label><input type="text" name="emergency_contact_relationship" class="form-input" value="${patient.emergency_contact_relationship || ''}"></div>
                    <div><label class="form-label">Phone</label><input type="text" name="emergency_contact_phone" class="form-input" value="${patient.emergency_contact_phone || ''}"></div>
                </div>
            </form>
            `;
    }

    function attachEventListeners(data, isEditMode) {
        if (isEditMode) {
            document.getElementById('save-demographics-btn').addEventListener('click', () => handleSaveDemographics(data));
            document.getElementById('cancel-demographics-btn').addEventListener('click', () => renderProfile(data, false));
        } else if (userRole !== 'facility') {
            const editBtn = document.getElementById('edit-demographics-btn');
            if (editBtn) editBtn.addEventListener('click', () => renderProfile(data, true));
        }

        const addWoundBtn = document.getElementById('addWoundBtn');
        if (addWoundBtn) {
            addWoundBtn.addEventListener('click', () => {
                addWoundModal.classList.remove('hidden');
                addWoundModal.classList.add('flex');
                
                // Initialize Searchable Lists
                const locationSearchInput = document.getElementById('location_search');
                const locationHiddenInput = document.getElementById('location');
                const locationListContainer = document.getElementById('location_list_container');
                
                const woundTypeSearchInput = document.getElementById('wound_type_search');
                const woundTypeHiddenInput = document.getElementById('wound_type');
                const woundTypeListContainer = document.getElementById('wound_type_list_container');

                if (locationSearchInput && locationHiddenInput && locationListContainer) {
                    setupSearchableList(locationSearchInput, locationHiddenInput, locationListContainer, WOUND_LOCATIONS);
                }
                if (woundTypeSearchInput && woundTypeHiddenInput && woundTypeListContainer) {
                    setupSearchableList(woundTypeSearchInput, woundTypeHiddenInput, woundTypeListContainer, WOUND_TYPES);
                }

                // Initialize Map Modal Logic
                setupWoundMapModal();
                
                // Map Open Button
                const openWoundMapBtn = document.getElementById('openWoundMapBtn');
                if (openWoundMapBtn) {
                    // Remove old listeners to avoid duplicates if opened multiple times
                    const newBtn = openWoundMapBtn.cloneNode(true);
                    openWoundMapBtn.parentNode.replaceChild(newBtn, openWoundMapBtn);
                    
                    newBtn.addEventListener('click', () => {
                        const woundMapModal = document.getElementById('woundMapModal');
                        if (woundMapModal) {
                            woundMapModal.classList.remove('hidden');
                            woundMapModal.classList.add('flex');
                            if (typeof lucide !== 'undefined') lucide.createIcons();
                        }
                    });
                }
            });
        }

        if (userRole === 'admin' || userRole === 'clinician') {
            setupAssignmentForm(data.details);
        }

        // --- Trend Chart Event Listener ---
        const trendChartMetric = document.getElementById('trend-chart-metric');
        if (trendChartMetric) {
            // Load initial chart
            updateTrendChart(trendChartMetric.value);
            // Add listener for changes
            trendChartMetric.addEventListener('change', (e) => {
                updateTrendChart(e.target.value);
            });
        }
    }

    // --- Communication Log Functions ---

    async function loadCommunicationLog() {
        const container = document.getElementById('comm-log-list');
        if (!container) return;
        container.innerHTML = '<div class="flex justify-center py-4"><div class="spinner"></div></div>';

        try {
            const res = await fetch(`api/get_communication_log.php?patient_id=${patientId}`);
            const data = await res.json();
            if (!data.success) throw new Error(data.message);
            container.innerHTML = renderCommLogs(data.logs);
        } catch (err) {
            container.innerHTML = `<p class="text-red-600 text-sm p-4">Failed to load communication log: ${err.message}</p>`;
        }
    }

    function renderCommLogs(logs) {
        if (!logs || logs.length === 0) {
            return `<div class="text-center text-gray-500 py-8">
                <i data-lucide="message-square-off" class="w-10 h-10 mx-auto mb-2 text-gray-300"></i>
                <p class="text-sm">No communication logs yet.</p>
                <p class="text-xs text-gray-400 mt-1">Use "Log Communication" to record a call, message, or note.</p>
            </div>`;
        }

        const typeColors = {
            'Phone (Out)': 'bg-blue-100 text-blue-800',
            'Phone (In)': 'bg-green-100 text-green-800',
            'Secure Message': 'bg-purple-100 text-purple-800',
            'Internal Note': 'bg-yellow-100 text-yellow-800',
        };

        const typeIcons = {
            'Phone (Out)': 'phone-outgoing',
            'Phone (In)': 'phone-incoming',
            'Secure Message': 'mail',
            'Internal Note': 'sticky-note',
        };

        return logs.map(log => {
            const colorClass = typeColors[log.communication_type] || 'bg-gray-100 text-gray-800';
            const icon = typeIcons[log.communication_type] || 'message-circle';
            const date = new Date(log.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            const followUpBadge = log.follow_up_needed == 1
                ? `<span class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700 flex items-center"><i data-lucide="flag" class="w-3 h-3 mr-1"></i>Follow-up Needed</span>`
                : '';
            const logDataAttr = JSON.stringify(log).replace(/"/g, '&quot;');

            return `
            <div class="border-b border-gray-100 last:border-0 py-4 px-2 hover:bg-gray-50 transition">
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0 mt-1 w-8 h-8 rounded-full flex items-center justify-center ${colorClass}">
                        <i data-lucide="${icon}" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full ${colorClass}">${log.communication_type}</span>
                            ${followUpBadge}
                        </div>
                        <p class="font-semibold text-gray-800 text-sm">${log.subject}</p>
                        <p class="text-sm text-gray-600 mt-1 whitespace-pre-wrap">${log.note_body}</p>
                        ${log.follow_up_needed == 1 && log.follow_up_action ? `<p class="text-xs text-red-700 mt-1 bg-red-50 rounded p-1.5"><strong>Follow-up:</strong> ${log.follow_up_action}</p>` : ''}
                        ${log.parties_involved ? `<p class="text-xs text-gray-400 mt-1"><strong>Parties:</strong> ${log.parties_involved}</p>` : ''}
                        <p class="text-xs text-gray-400 mt-1.5">${date} &mdash; <span class="font-medium">${log.logged_by || 'Unknown User'}</span></p>
                    </div>
                    <button class="comm-entry-print-btn flex-shrink-0 p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Print this entry" data-log="${logDataAttr}">
                        <i data-lucide="printer" class="w-4 h-4 pointer-events-none"></i>
                    </button>
                </div>
            </div>`;
        }).join('');
    }

    function printCommunicationLog() {
        const container = document.getElementById('comm-log-list');
        const patientNameEl = document.getElementById('patient-name-header');
        const patientName = patientNameEl ? patientNameEl.querySelector('span')?.textContent?.trim() : 'Patient';

        if (!container || container.innerHTML.includes('spinner')) {
            alert('Please open the Activity Timeline tab first to load the logs.');
            return;
        }

        const printWindow = window.open('', '_blank', 'width=900,height=700');
        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Communication Log - ${patientName}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; margin: 0; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 12px; color: #555; margin-bottom: 20px; }
        .entry { border-bottom: 1px solid #ddd; padding: 12px 0; page-break-inside: avoid; }
        .entry:last-child { border-bottom: none; }
        .badge { display: inline-block; font-size: 11px; font-weight: bold; padding: 2px 8px; border-radius: 12px; margin-bottom: 4px; background: #e0e7ff; color: #3730a3; }
        .badge.phone-out { background: #dbeafe; color: #1e40af; }
        .badge.phone-in { background: #dcfce7; color: #166534; }
        .badge.secure { background: #f3e8ff; color: #6b21a8; }
        .badge.internal { background: #fef9c3; color: #854d0e; }
        .subject { font-weight: bold; font-size: 14px; }
        .note { margin: 4px 0; white-space: pre-wrap; }
        .follow-up { background: #fef2f2; border-left: 3px solid #ef4444; padding: 4px 8px; margin-top: 6px; font-size: 12px; color: #b91c1c; }
        .parties { font-size: 11px; color: #555; margin-top: 4px; }
        .dateline { font-size: 11px; color: #777; margin-top: 4px; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <h1>Communication Log</h1>
    <p class="meta">Patient: <strong>${patientName}</strong> &nbsp;|&nbsp; Printed: ${new Date().toLocaleString()}</p>
    <div id="print-entries">
        ${generateCommLogPrintHTML(container)}
    </div>
    <script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`);
        printWindow.document.close();
    }

    function generateCommLogPrintHTML(container) {
        const entries = container.querySelectorAll('.border-b');
        if (!entries.length) return '<p>No communication logs found.</p>';

        return Array.from(entries).map(entry => {
            const badge = entry.querySelector('span.text-xs.font-semibold');
            const subject = entry.querySelector('p.font-semibold');
            const note = entry.querySelector('p.text-sm.text-gray-600');
            const dateline = entry.querySelector('p.text-xs.text-gray-400.mt-1\.5');
            const followUp = entry.querySelector('p.text-xs.text-red-700');
            const parties = entry.querySelector('p.text-xs.text-gray-400.mt-1:not(.mt-1\.5)');
            const badgeType = badge ? badge.textContent.trim() : '';
            const badgeClass = badgeType.includes('Out') ? 'phone-out' : badgeType.includes('In') ? 'phone-in' : badgeType.includes('Secure') ? 'secure' : 'internal';

            return `<div class="entry">
                <span class="badge ${badgeClass}">${badgeType}</span>
                <div class="subject">${subject ? subject.textContent.trim() : ''}</div>
                <div class="note">${note ? note.textContent.trim() : ''}</div>
                ${followUp ? `<div class="follow-up">${followUp.textContent.trim()}</div>` : ''}
                ${parties ? `<div class="parties">${parties.textContent.trim()}</div>` : ''}
                <div class="dateline">${dateline ? dateline.innerHTML : ''}</div>
            </div>`;
        }).join('');
    }

    // Wire up print button via delegation
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('#print-comm-log-btn')) {
            printCommunicationLog();
        }
        if (e.target.closest('.comm-entry-print-btn')) {
            const btn = e.target.closest('.comm-entry-print-btn');
            const log = JSON.parse(btn.dataset.log.replace(/&quot;/g, '"'));
            printSingleCommLog(log);
        }
    });

    function printSingleCommLog(log) {
        const patientNameEl = document.getElementById('patient-name-header');
        const patientName = patientNameEl ? patientNameEl.querySelector('span')?.textContent?.trim() : 'Patient';
        const date = new Date(log.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });

        const followUpSection = log.follow_up_needed == 1
            ? `<div class="follow-up"><strong>Follow-up Required</strong>${log.follow_up_action ? ': ' + log.follow_up_action : ''}</div>`
            : '';
        const partiesSection = log.parties_involved
            ? `<p class="meta-line"><strong>Parties:</strong> ${log.parties_involved}</p>`
            : '';

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Communication Log Entry</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; color: #111; margin: 0; padding: 32px; max-width: 700px; }
        .header { border-bottom: 2px solid #333; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; margin: 0 0 4px; }
        .header p { margin: 0; font-size: 12px; color: #555; }
        .badge { display: inline-block; font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 12px; margin-bottom: 12px; background: #e0e7ff; color: #3730a3; }
        .subject { font-size: 16px; font-weight: bold; margin-bottom: 8px; }
        .note { white-space: pre-wrap; line-height: 1.6; margin-bottom: 12px; padding: 10px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .follow-up { background: #fef2f2; border-left: 4px solid #ef4444; padding: 8px 12px; margin: 12px 0; color: #b91c1c; font-size: 12px; border-radius: 0 4px 4px 0; }
        .meta-line { font-size: 12px; color: #555; margin: 4px 0; }
        .dateline { font-size: 12px; color: #777; margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e7eb; }
        @media print { body { padding: 16px; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>Communication Log Entry</h1>
        <p>Patient: <strong>${patientName}</strong> &nbsp;|&nbsp; Printed: ${new Date().toLocaleString()}</p>
    </div>
    <span class="badge">${log.communication_type}</span>
    <div class="subject">${log.subject}</div>
    <div class="note">${log.note_body}</div>
    ${followUpSection}
    ${partiesSection}
    <div class="dateline">Logged: ${date} &mdash; <strong>${log.logged_by || 'Unknown User'}</strong></div>
    <script>window.onload = function(){ window.print(); }<\/script>
</body>
</html>`);
        printWindow.document.close();
    }

    // Wire up comm modal buttons once at startup
    if (closeCommModalBtn) {
        closeCommModalBtn.addEventListener('click', () => {
            logCommunicationModal.classList.add('hidden');
            logCommunicationModal.classList.remove('flex');
        });
    }
    if (cancelCommModalBtn) {
        cancelCommModalBtn.addEventListener('click', () => {
            logCommunicationModal.classList.add('hidden');
            logCommunicationModal.classList.remove('flex');
        });
    }

    // Dynamically wire the "Log Communication" button inside the activity tab
    document.body.addEventListener('click', (e) => {
        if (e.target.closest('#activity-log-comm-btn')) {
            logCommunicationForm.reset();
            commModalMessage.classList.add('hidden');
            logCommunicationModal.classList.add('flex');
            logCommunicationModal.classList.remove('hidden');
        }
    });

    // Form submit handler
    if (logCommunicationForm) {
        logCommunicationForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(logCommunicationForm);
            const payload = Object.fromEntries(formData.entries());
            payload.follow_up_needed = document.getElementById('follow_up_needed')?.checked ? 1 : 0;

            const saveBtn = logCommunicationForm.querySelector('button[type="submit"]');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const res = await fetch('api/save_communication_log.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (!result.success) throw new Error(result.message);

                showMessage(commModalMessage, 'Communication logged successfully.', 'success');
                setTimeout(() => {
                    logCommunicationModal.classList.add('hidden');
                    logCommunicationModal.classList.remove('flex');
                    logCommunicationForm.reset();
                    // Refresh the log if activity tab is visible
                    loadCommunicationLog();
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                }, 800);
            } catch (err) {
                showMessage(commModalMessage, `Error: ${err.message}`, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Log';
            }
        });
    }

    // --- `updateTrendChart` function ---
    async function updateTrendChart(metric) {
        const container = document.getElementById('trend-chart-container');
        const messageEl = document.getElementById('trend-chart-message');
        const ctx = document.getElementById('clinicalTrendChart').getContext('2d');
        if (!container || !messageEl || !ctx) return;

        // Show loading message
        messageEl.textContent = 'Loading chart data...';
        messageEl.classList.remove('hidden');

        if (clinicalTrendChart) {
            clinicalTrendChart.destroy();
        }

        try {
            const response = await fetch(`api/get_lab_trend.php?patient_id=${patientId}&metric=${metric}`);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Failed to fetch chart data');
            }

            if (data.labels.length === 0) {
                messageEl.textContent = 'No data available for this metric.';
                messageEl.classList.remove('hidden');
                return;
            }

            // Data found, hide message
            messageEl.classList.add('hidden');

            // Render the new chart
            clinicalTrendChart = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: { display: true, text: data.datasets[0].label }
                        },
                        x: {
                            type: 'time',
                            time: { unit: 'day' },
                            title: { display: true, text: 'Date' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function (context) {
                                    // Format the date in the tooltip
                                    return new Date(context[0].parsed.x).toLocaleDateString();
                                }
                            }
                        }
                    }
                }
            });

        } catch (error) {
            messageEl.textContent = `Error: ${error.message}`;
            messageEl.classList.remove('hidden');
        }
    }


    async function handleSaveDemographics(originalData) {
        const form = document.getElementById('demographics-form');
        const formData = new FormData(form);
        const updatedData = Object.fromEntries(formData.entries());
        updatedData.patient_id = patientId;

        try {
            const response = await fetch('api/update_patient_details.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updatedData)
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message);
            fetchPatientProfile();
            showMessage(pageMessage, 'Patient details saved successfully.', 'success');
        } catch (error) {
            showMessage(pageMessage, `Error saving: ${error.message}`, 'error');
        }
    }

    // --- Assessment History & Gallery Logic ---

    async function fetchPatientHistory() {
        const historyContainer = document.getElementById('assessment-history-container');
        const galleryContainer = document.getElementById('patient-gallery-container');

        try {
            const response = await fetch(`api/get_patient_history_gallery.php?patient_id=${patientId}&type=all`);
            if (!response.ok) throw new Error('Failed to fetch history data');
            const data = await response.json();

            if (data.success) {
                renderAssessmentHistoryTable(data.assessments, data.total_assessments, data.assessments_page, data.assessments_limit);
                renderPatientGallery(data.images, data.total_images, data.gallery_page, data.gallery_limit);
            } else {
                throw new Error(data.message || 'Unknown error');
            }
        } catch (error) {
            console.error("History fetch error:", error);
            if (historyContainer) historyContainer.innerHTML = `<p class="text-red-500">Error loading history: ${error.message}</p>`;
            if (galleryContainer) galleryContainer.innerHTML = `<p class="text-red-500">Error loading gallery.</p>`;
        }
    }

    // Pagination Fetchers
    window.fetchAssessments = async function(page = 1) {
        const historyContainer = document.getElementById('assessment-history-container');
        historyContainer.innerHTML = '<p class="text-gray-500 text-sm">Loading...</p>';
        
        try {
            const response = await fetch(`api/get_patient_history_gallery.php?patient_id=${patientId}&type=assessments&page=${page}&limit=5`);
            const data = await response.json();
            if (data.success) {
                renderAssessmentHistoryTable(data.assessments, data.total_assessments, data.assessments_page, data.assessments_limit);
            }
        } catch (error) {
            historyContainer.innerHTML = `<p class="text-red-500">Error: ${error.message}</p>`;
        }
    };

    window.fetchGallery = async function(page = 1) {
        const galleryContainer = document.getElementById('patient-gallery-container');
        galleryContainer.innerHTML = '<p class="text-gray-500 text-sm col-span-full">Loading...</p>';

        try {
            const response = await fetch(`api/get_patient_history_gallery.php?patient_id=${patientId}&type=gallery&page=${page}&limit=8`);
            const data = await response.json();
            if (data.success) {
                renderPatientGallery(data.images, data.total_images, data.gallery_page, data.gallery_limit);
            }
        } catch (error) {
            galleryContainer.innerHTML = `<p class="text-red-500">Error: ${error.message}</p>`;
        }
    };

    function renderPaginationControls(total, page, limit, fetchFunction) {
        const totalPages = Math.ceil(total / limit);
        if (totalPages <= 1) return '';

        return `
            <div class="flex justify-between items-center mt-4 border-t pt-3 w-full">
                <span class="text-sm text-gray-600">Page ${page} of ${totalPages}</span>
                <div class="space-x-2">
                    <button ${page === 1 ? 'disabled' : ''} onclick="${fetchFunction}(${page - 1})" class="px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed bg-white text-gray-700">Prev</button>
                    <button ${page === totalPages ? 'disabled' : ''} onclick="${fetchFunction}(${page + 1})" class="px-3 py-1 text-sm border rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed bg-white text-gray-700">Next</button>
                </div>
            </div>
        `;
    }

    function renderAssessmentHistoryTable(assessments, total, page, limit) {
        const container = document.getElementById('assessment-history-container');
        if (!assessments || assessments.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm">No assessments recorded.</p>';
            return;
        }

        // Group assessments by appointment_id
        const grouped = {};
        assessments.forEach(a => {
            const apptId = a.appointment_id || 'No Appointment';
            if (!grouped[apptId]) grouped[apptId] = [];
            grouped[apptId].push(a);
        });

        // Render each group as a section
        let html = '';
        Object.keys(grouped).forEach(apptId => {
            const group = grouped[apptId];
            // Appointment header
            let apptLabel = apptId === 'No Appointment' ? 'Unlinked Assessments' : `Appointment #${apptId}`;
            html += `<div class="mb-6">
                <h4 class="font-bold text-blue-700 text-base mb-2">${apptLabel}</h4>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Treatment/Plan</th>
                            <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${group.map(assessment => {
                            const date = new Date(assessment.assessment_date).toLocaleDateString();
                            return `
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-2 text-sm text-gray-800">${date}</td>
                                    <td class="px-4 py-2 text-sm text-blue-600 font-medium">${assessment.wound_location}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">${assessment.wound_type}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600 truncate max-w-xs" title="${assessment.treatments_provided || ''}">
                                        ${assessment.treatments_provided ? assessment.treatments_provided.substring(0, 50) + (assessment.treatments_provided.length > 50 ? '...' : '') : '-'}
                                    </td>
                                    <td class="px-4 py-2 text-sm text-right">
                                        <button data-assessment-id="${assessment.assessment_id}" class="view-assessment-btn text-indigo-600 hover:text-indigo-800 font-semibold text-xs border border-indigo-200 px-2 py-1 rounded hover:bg-indigo-50">View</button>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
        });
        
        html += renderPaginationControls(total, page, limit, 'fetchAssessments');
        container.innerHTML = html;
    }

    function renderPatientGallery(images, total, page, limit) {
        const container = document.getElementById('patient-gallery-container');
        if (!images || images.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm col-span-full">No images uploaded.</p>';
            return;
        }

        let html = '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 col-span-full w-full">';
        html += images.map(img => {
            const date = new Date(img.uploaded_at).toLocaleDateString();
            return `
                <div class="group relative aspect-square bg-gray-100 rounded-lg overflow-hidden cursor-pointer border border-gray-200 hover:shadow-md transition"
                     onclick="openGalleryModal('${img.image_path}', '${img.wound_location} - ${date}', '${date}')">
                    <img src="${img.image_path}" alt="Wound Image" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                    <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition duration-300 flex items-end p-2">
                        <span class="text-white text-xs font-bold bg-black bg-opacity-50 px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition">${img.wound_location}</span>
                    </div>
                </div>
            `;
        }).join('');
        html += '</div>';
        
        html += `<div class="col-span-full w-full">${renderPaginationControls(total, page, limit, 'fetchGallery')}</div>`;
        container.innerHTML = html;
    }

    // Simple Lightbox Modal (created dynamically if not exists)
    window.openGalleryModal = function (src, caption, date) {
        let modal = document.getElementById('gallery-lightbox-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'gallery-lightbox-modal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-90 hidden';
            modal.innerHTML = `
                <div class="relative max-w-4xl w-full max-h-screen p-4 flex flex-col items-center">
                    <button id="close-gallery-modal" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                    <img id="gallery-modal-img" src="" class="max-w-full max-h-[80vh] object-contain rounded shadow-lg">
                    <div class="mt-4 text-center">
                        <p id="gallery-modal-caption" class="text-white text-lg font-semibold"></p>
                        <p id="gallery-modal-date" class="text-gray-400 text-sm"></p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            modal.querySelector('#close-gallery-modal').addEventListener('click', () => {
                modal.classList.add('hidden');
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.add('hidden');
            });
        }

        const imgEl = modal.querySelector('#gallery-modal-img');
        const captionEl = modal.querySelector('#gallery-modal-caption');
        const dateEl = modal.querySelector('#gallery-modal-date');

        imgEl.src = src;
        captionEl.textContent = caption;
        dateEl.textContent = date;

        modal.classList.remove('hidden');
    };

    // --- Initial Load ---
    fetchPatientProfile();

    // --- MODAL AND ASSESSMENT DETAILS ---
    const viewAssessmentModal = document.getElementById('viewAssessmentModal');
    const closeViewAssessmentModalBtn = document.getElementById('closeViewAssessmentModalBtn');
    const closeViewAssessmentModalBtnBottom = document.getElementById('closeViewAssessmentModalBtnBottom');
    const viewAssessmentContent = document.getElementById('viewAssessmentContent');

    function openAssessmentModal() {
        viewAssessmentModal.classList.remove('hidden');
        viewAssessmentModal.classList.add('flex');
    }

    function closeAssessmentModal() {
        viewAssessmentModal.classList.add('hidden');
        viewAssessmentModal.classList.remove('flex');
    }

    closeViewAssessmentModalBtn.addEventListener('click', closeAssessmentModal);
    closeViewAssessmentModalBtnBottom.addEventListener('click', closeAssessmentModal);

    async function fetchAndShowAssessmentDetails(assessmentId) {
        openAssessmentModal();
        viewAssessmentContent.innerHTML = '<div class="flex justify-center items-center h-full"><div class="spinner"></div></div>'; // Show spinner

        try {
            const response = await fetch(`api/get_assessment_details.php?id=${assessmentId}`);
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to fetch assessment details');
            }
            const data = await response.json();
            if (data && data.assessment_id) {
                // --- UI ENHANCEMENT: Update modal title ---
                const modalTitle = document.getElementById('viewAssessmentTitle');
                const modalSubtitle = document.getElementById('viewAssessmentSubtitle');
                // Use wound_location, fallback to wound_type or location if missing
                // Use wound_location if available, otherwise fetch wound details for location
                let locationText = data.wound_location;
                if (!locationText && data.wound_id) {
                    // Fetch wound details from new endpoint
                    try {
                        const woundRes = await fetch(`api/get_wound_assessment_for_profile.php?wound_id=${data.wound_id}`);
                        if (woundRes.ok) {
                            const woundData = await woundRes.json();
                            locationText = woundData.wound_location || woundData.location || `Wound #${data.wound_id}`;
                        } else {
                            locationText = `Wound #${data.wound_id}`;
                        }
                    } catch (err) {
                        locationText = `Wound #${data.wound_id}`;
                    }
                }
                if (!locationText) locationText = 'Unknown Location';
                if(modalTitle) modalTitle.textContent = `Assessment for ${locationText}`;
                if(modalSubtitle) modalSubtitle.textContent = `Recorded on ${new Date(data.assessment_date).toLocaleDateString()}`;
                
                viewAssessmentContent.innerHTML = renderAssessmentDetails(data);

                // --- UI ENHANCEMENT: Rerender icons ---
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            } else {
                throw new Error('Could not retrieve valid assessment details from API.');
            }
        } catch (error) {
            viewAssessmentContent.innerHTML = `<div class="text-red-500 p-4">Error: ${error.message}</div>`;
        }
    }

    function renderAssessmentDetails(assessment) {
        if (!assessment) {
            return '<p>Assessment data is not available.</p>';
        }

        const formatText = (text, fallback = 'N/A') => text || fallback;

        // Helper to render a detail item in a definition list format
        const renderDetailItem = (label, value, icon) => {
            const valueHtml = value !== 'N/A' ? `<dd class="mt-1 text-md text-gray-900 sm:mt-0 sm:col-span-2">${value}</dd>` : `<dd class="mt-1 text-md text-gray-500 sm:mt-0 sm:col-span-2">Not recorded</dd>`;
            return `
                <div class="py-3 sm:py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                    <dt class="text-sm font-medium text-gray-500 flex items-center">
                        <i data-lucide="${icon}" class="w-4 h-4 mr-2 text-gray-400"></i>
                        ${label}
                    </dt>
                    ${valueHtml}
                </div>
            `;
        };

        // Helper for large text sections
        const renderTextBlock = (label, text, icon, colorClass = 'gray') => {
            return `
                <div>
                    <h5 class="text-lg font-semibold text-gray-800 flex items-center mb-2">
                        <i data-lucide="${icon}" class="w-5 h-5 mr-2 text-${colorClass}-500"></i>
                        ${label}
                    </h5>
                    <div class="whitespace-pre-wrap bg-${colorClass}-50 p-4 rounded-md border border-${colorClass}-200 text-${colorClass}-800 text-sm">
                        ${formatText(text, 'No information provided.')}
                    </div>
                </div>
            `;
        };
        
        // Pain level badge
        let painBadge = 'N/A';
        if (assessment.pain_level !== null && assessment.pain_level !== undefined) {
            let painColor = 'green';
            if (assessment.pain_level > 3) painColor = 'yellow';
            if (assessment.pain_level > 6) painColor = 'red';
            painBadge = `<span class="px-2.5 py-1 text-sm font-semibold rounded-full bg-${painColor}-100 text-${painColor}-800">${assessment.pain_level} / 10</span>`;
        }

        // Check for multiple possible wound type property names
        const woundType = assessment.wound_type || assessment.type || assessment.assessment_type || assessment.stage || 'N/A';

        const dimensions = (assessment.length_cm != null && assessment.width_cm != null && assessment.depth_cm != null) ?
            `${assessment.length_cm} cm x ${assessment.width_cm} cm x ${assessment.depth_cm} cm` : 'N/A';

        // Construct Tunneling/Undermining string
        let tunnelingInfo = [];
        if (assessment.tunneling_present === 'Yes') {
            let tText = 'Tunneling';
            if (assessment.tunneling_cm) tText += ` ${assessment.tunneling_cm}cm`;
            tunnelingInfo.push(tText);
        }
        if (assessment.undermining_present === 'Yes') {
            let uText = 'Undermining';
            if (assessment.undermining_cm) uText += ` ${assessment.undermining_cm}cm`;
            tunnelingInfo.push(uText);
        }
        const tunnelingUnderminingText = tunnelingInfo.length > 0 ? tunnelingInfo.join(', ') : 'None';

        // Image display
        const imageHtml = assessment.image_path
            ? `<div class="mb-6"><img src="${assessment.image_path}" alt="Wound assessment image" class="rounded-lg max-w-full h-auto mx-auto shadow-md" style="max-height: 400px;"></div>`
            : '<div class="mb-6 p-4 text-center bg-gray-100 rounded-lg text-gray-500 border"><i data-lucide="image-off" class="mx-auto w-10 h-10 mb-2"></i>No image for this assessment.</div>';

        return `
            ${imageHtml}
            <div class="space-y-8">
                <!-- Characteristics Section -->
                <div class="bg-white p-5 rounded-lg border border-gray-200">
                     <h5 class="text-lg font-semibold text-gray-800 flex items-center mb-3 border-b pb-2">
                        <i data-lucide="ruler" class="w-5 h-5 mr-2 text-blue-500"></i>
                        Wound Characteristics
                    </h5>
                    <dl class="divide-y divide-gray-200">
                        ${renderDetailItem('Type', formatText(woundType), 'tag')}
                        ${renderDetailItem('Dimensions (L×W×D)', dimensions, 'move-3d')}
                        ${renderDetailItem('Area', assessment.area_cm2 ? `${assessment.area_cm2} cm²` : 'N/A', 'square')}
                        ${renderDetailItem('Tunneling/Undermining', tunnelingUnderminingText, 'move-horizontal')}
                    </dl>
                </div>

                <!-- Tissue & Drainage Section -->
                <div class="bg-white p-5 rounded-lg border border-gray-200">
                     <h5 class="text-lg font-semibold text-gray-800 flex items-center mb-3 border-b pb-2">
                        <i data-lucide="droplets" class="w-5 h-5 mr-2 text-teal-500"></i>
                        Tissue & Drainage
                    </h5>
                    <dl class="divide-y divide-gray-200">
                        ${renderDetailItem('Tissue Types', formatText(assessment.tissue_types), 'layers')}
                        ${renderDetailItem('Exudate Amount', formatText(assessment.exudate_amount), 'cloud-drizzle')}
                        ${renderDetailItem('Exudate Type', formatText(assessment.exudate_type), 'test-tube-2')}
                        ${renderDetailItem('Odor', formatText(assessment.odor), 'wind')}
                    </dl>
                </div>

                 <!-- Periwound & Pain Section -->
                <div class="bg-white p-5 rounded-lg border border-gray-200">
                     <h5 class="text-lg font-semibold text-gray-800 flex items-center mb-3 border-b pb-2">
                        <i data-lucide="thermometer" class="w-5 h-5 mr-2 text-orange-500"></i>
                        Periwound & Pain
                    </h5>
                    <dl class="divide-y divide-gray-200">
                        ${renderDetailItem('Periwound Condition', formatText(assessment.periwound_condition), 'circle-dashed')}
                        ${renderDetailItem('Pain Level', painBadge, 'siren')}
                    </dl>
                </div>

                <!-- Notes & Plan Section -->
                <div class="space-y-6">
                    ${renderTextBlock('Treatment & Plan', assessment.treatments_provided, 'clipboard-list', 'purple')}
                    ${renderTextBlock('Clinician Notes', assessment.notes, 'message-square-text', 'indigo')}
                </div>
            </div>
        `;
    }

    document.body.addEventListener('click', function(event) {
        const viewButton = event.target.closest('.view-assessment-btn');
        if (viewButton) {
            const assessmentId = viewButton.dataset.assessmentId;
            if (assessmentId) {
                fetchAndShowAssessmentDetails(assessmentId);
            }
        }
    });

    // --- Delete Wound Logic ---
    const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    let woundIdToDelete = null;

    function closeDeleteModal() {
        deleteConfirmationModal.classList.add('hidden');
        deleteConfirmationModal.classList.remove('flex');
        woundIdToDelete = null;
    }

    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', closeDeleteModal);
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', async () => {
            if (!woundIdToDelete) return;

            // Disable button to prevent double clicks
            confirmDeleteBtn.disabled = true;
            confirmDeleteBtn.textContent = 'Deleting...';

            try {
                const response = await fetch('api/delete_wound.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ wound_id: woundIdToDelete })
                });
                const result = await response.json();
                
                if (response.ok) {
                    showMessage(pageMessage, 'Wound deleted successfully.', 'success');
                    fetchPatientProfile(); // Refresh the list
                } else {
                    throw new Error(result.message || 'Failed to delete wound.');
                }
            } catch (error) {
                showMessage(pageMessage, `Error: ${error.message}`, 'error');
            } finally {
                closeDeleteModal();
                confirmDeleteBtn.disabled = false;
                confirmDeleteBtn.textContent = 'Delete';
            }
        });
    }

    document.body.addEventListener('click', (e) => {
        const deleteBtn = e.target.closest('.delete-wound-btn');
        if (deleteBtn) {
            woundIdToDelete = deleteBtn.dataset.woundId;
            deleteConfirmationModal.classList.remove('hidden');
            deleteConfirmationModal.classList.add('flex');
        }
    });

});