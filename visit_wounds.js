/**
 * Visit Wounds Page Logic
 * Handles:
 * 1. Initial data fetching (patient details, wounds)
 * 2. Wound list rendering
 * 3. "Add Wound" modal (incl. searchable lists and map)
 * 4. "Wound Assessed" visual confirmation
 * 5. "Quick-Change Wound Status"
 * 6. Auto-narrative updates
 */

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

// --- Main script execution after DOM is loaded ---
document.addEventListener('DOMContentLoaded', () => {

    // --- Global Page Data ---
    // This data is passed from the inline script in visit_wounds.php
    if (typeof window.visitData === 'undefined' || !window.visitData.patientId || !window.visitData.appointmentId) {
        console.error("Patient ID or Appointment ID is missing. Cannot initialize page.");
        // Display a user-friendly error on the page
        const container = document.getElementById('wounds-list-container');
        if (container) {
            container.innerHTML = '<p class="text-center text-red-600 font-semibold p-8">Error: Page data is missing. Please go back and try again.</p>';
        }
        return; // Stop execution
    }
    const patientId = window.visitData.patientId;
    const appointmentId = window.visitData.appointmentId;
    const userId = window.visitData.userId;

    // --- Page Elements ---
    const nameHeader = document.getElementById('patient-name-header');
    const woundsListContainer = document.getElementById('wounds-list-container');
    const copyNarrativeBtn = document.getElementById('copyNarrativeBtn');
    const autoNarrativeContent = document.getElementById('auto-narrative-content');
    const narrativeSpinner = document.getElementById('narrative-spinner');
    const pageMessage = document.getElementById('page-message');

    // --- 'Add Wound' Modal Elements ---
    const addWoundModal = document.getElementById('addWoundModal');
    const openAddWoundModalBtn = document.getElementById('openAddWoundModalBtn');
    const closeModalBtn = document.getElementById('closeModalBtn');
    const cancelModalBtn = document.getElementById('cancelModalBtn');
    const addWoundForm = document.getElementById('addWoundForm');
    const modalMessage = document.getElementById('modal-message');
    // *** FIX: Defined submitBtn here ***
    const submitBtn = addWoundForm.querySelector('button[type="submit"]');

    // --- 'Add Wound' Form Fields ---
    const locationSearchInput = document.getElementById('location_search');
    const locationHiddenInput = document.getElementById('location');
    const locationListContainer = document.getElementById('location_list_container');

    const woundTypeSearchInput = document.getElementById('wound_type_search');
    const woundTypeHiddenInput = document.getElementById('wound_type');
    const woundTypeListContainer = document.getElementById('wound_type_list_container');

    // --- 'Wound Map' Modal Elements ---
    const woundMapModal = document.getElementById('woundMapModal');
    const openWoundMapBtn = document.getElementById('openWoundMapBtn');
    const closeWoundMapBtn = document.getElementById('closeWoundMapBtn');
    const mapTabs = document.querySelectorAll('.map-tab');
    const mapPanes = document.querySelectorAll('.map-pane');
    const hotspots = document.querySelectorAll('.map-hotspot');

    // --- Initial Data Fetch ---
    async function fetchInitialData() {
        showNarrativeSpinner(true);
        try {
            // Fetch patient details and their wounds (including assessment status for this visit)
            const response = await fetch(`api/get_patient_details_from_wound_visits.php?id=${patientId}&appointment_id=${appointmentId}`);
            if (!response.ok) {
                throw new Error('Failed to fetch patient details');
            }
            const patientData = await response.json();

            if (patientData && patientData.details) {
                const patient = patientData.details;
                nameHeader.textContent = `${patient.first_name} ${patient.last_name}`;
                // *** FIX: Use patientData.details.wounds ***
                globalWoundsData = patientData.details.wounds || [];
                renderWoundsList();
            } else {
                throw new Error('Invalid data structure from API');
            }

            // Fetch HPI data
            const hpiResponse = await fetch(`api/get_hpi_data.php?appointment_id=${appointmentId}`);
            if(hpiResponse.ok) {
                const hpiResult = await hpiResponse.json();
                if(hpiResult.success && hpiResult.data) {
                    currentHpiData = hpiResult.data;
                }
            }

            // Fetch Vitals data
            const vitalsResponse = await fetch(`api/get_vitals.php?patient_id=${patientId}&appointment_id=${appointmentId}`);
            if(vitalsResponse.ok) {
                const vitalsResult = await vitalsResponse.json();
                if(vitalsResult) { window.currentVitalsData = vitalsResult; }
            }

            // Update narrative after all data is fetched
            if (typeof updateAutoNarrative === 'function') {
                updateAutoNarrative();
            }

        } catch (error) {
            nameHeader.textContent = 'Error Loading Patient';
            woundsListContainer.innerHTML = `<p class="text-center text-red-500 py-8 col-span-full">Error loading patient data: ${error.message}</p>`;
            console.error("Initialization Error:", error);
        } finally {
            showNarrativeSpinner(false);
            // Render all Lucide icons on the page
            lucide.createIcons();
        }
    }

    // --- Render Wound List ---
    function renderWoundsList() {
        if (!globalWoundsData || globalWoundsData.length === 0) {
            woundsListContainer.innerHTML = '<p class="text-center text-gray-500 py-8 col-span-full">No wounds are currently charted for this patient. Click "+ Add New Wound" above to register one.</p>';
            return;
        }

        // --- Sort wounds by date_onset (oldest first) ---
        const sortedWounds = globalWoundsData.sort((a, b) => new Date(a.date_onset) - new Date(b.date_onset));

        // *** NEW: Generate Mobile Card View (hidden on lg and up) ***
        const mobileCardsHTML = sortedWounds.map((wound, index) => {
            const cardClass = wound.assessed_for_this_visit ? 'bg-green-50' : 'bg-white';
            const buttonClass = wound.assessed_for_this_visit ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700';
            const buttonIcon = wound.assessed_for_this_visit ? 'check' : 'edit-3';
            const buttonText = wound.assessed_for_this_visit ? 'View/Edit' : 'Assess';

            // Determine badge color based on status
            let statusColorClass = 'bg-gray-100 text-gray-800';
            if (wound.status === 'Active') {
                statusColorClass = 'bg-red-100 text-red-800';
            } else if (wound.status === 'Healed') {
                statusColorClass = 'bg-green-100 text-green-800';
            } else if (wound.status === 'Inactive') {
                statusColorClass = 'bg-yellow-100 text-yellow-800';
            }

            return `
                <div class="${cardClass} p-4 rounded-lg shadow-md border border-gray-200 space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-lg text-indigo-700">Wound #${index + 1}: ${wound.location}</span>
                    </div>
                    
                    <div class="text-sm space-y-2">
                        <p><strong>Type:</strong> ${wound.wound_type}</p>
                        <p><strong>Onset:</strong> ${wound.date_onset}</p>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t">
                        <select class="status-select-badge ${statusColorClass} text-sm font-semibold rounded-full border-0 py-2 px-4 focus:ring-2 focus:ring-indigo-500 w-full sm:w-auto" data-wound-id="${wound.wound_id}">
                            <option value="Active" ${wound.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Healed" ${wound.status === 'Healed' ? 'selected' : ''}>Healed</option>
                            <option value="Inactive" ${wound.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                        
                        <a href="wound_assessment.php?id=${wound.wound_id}&appointment_id=${appointmentId}&patient_id=${patientId}&user_id=${userId}" 
                           class="${buttonClass} text-white font-bold py-2 px-4 rounded-md text-sm transition inline-flex items-center justify-center shadow-sm w-full sm:w-auto">
                           <i data-lucide="${buttonIcon}" class="w-4 h-4 mr-1.5"></i>
                            ${buttonText}
                        </a>
                    </div>
                </div>
            `;
        }).join('');

        // *** NEW: Generate Desktop Table View (hidden on mobile) ***
        const tableRows = sortedWounds.map((wound, index) => {
            const rowClass = wound.assessed_for_this_visit ? 'bg-green-50' : 'bg-white';
            const buttonClass = wound.assessed_for_this_visit ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700';
            const buttonIcon = wound.assessed_for_this_visit ? 'check' : 'edit-3';
            const buttonText = wound.assessed_for_this_visit ? 'View/Edit' : 'Assess';

            // Determine badge color based on status
            let statusColorClass = 'bg-gray-100 text-gray-800';
            if (wound.status === 'Active') {
                statusColorClass = 'bg-red-100 text-red-800';
            } else if (wound.status === 'Healed') {
                statusColorClass = 'bg-green-100 text-green-800';
            } else if (wound.status === 'Inactive') {
                statusColorClass = 'bg-yellow-100 text-yellow-800';
            }

            return `
            <tr class="${rowClass} border-b border-gray-200 hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4 font-mono text-sm text-gray-700">${index + 1}</td>
                <td class="px-6 py-4 font-semibold text-gray-800">${wound.location}</td>
                <td class="px-6 py-4 text-gray-600">${wound.wound_type}</td>
                <td class="px-6 py-4 text-gray-600">${wound.date_onset}</td>
                <td class="px-6 py-4">
                    <select class="status-select-badge ${statusColorClass} text-xs font-semibold rounded-full border-0 py-1 pl-3 pr-8 focus:ring-2 focus:ring-indigo-500" data-wound-id="${wound.wound_id}">
                        <option value="Active" ${wound.status === 'Active' ? 'selected' : ''}>Active</option>
                        <option value="Healed" ${wound.status === 'Healed' ? 'selected' : ''}>Healed</option>
                        <option value="Inactive" ${wound.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                    </select>
                </td>
                <td class="px-6 py-4 text-right">
                    <a href="wound_assessment.php?id=${wound.wound_id}&appointment_id=${appointmentId}&patient_id=${patientId}&user_id=${userId}" 
                       class="${buttonClass} text-white font-bold py-1 px-3 rounded-md text-sm transition inline-flex items-center shadow-sm">
                       <i data-lucide="${buttonIcon}" class="w-4 h-4 mr-1.5"></i>
                        ${buttonText}
                    </a>
                </td>
            </tr>
        `;
        }).join('');

        // --- Combine both views into the container ---
        woundsListContainer.innerHTML = `
            <!-- Mobile Card View -->
            <div class="block lg:hidden space-y-4">
                ${mobileCardsHTML}
            </div>

            <!-- Desktop Table View -->
            <table class="min-w-full hidden lg:table">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><i data-lucide="hash" class="w-4 h-4 inline-block mr-1.5"></i>Wound #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><i data-lucide="map-pin" class="w-4 h-4 inline-block mr-1.5"></i>Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><i data-lucide="tag" class="w-4 h-4 inline-block mr-1.5"></i>Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><i data-lucide="calendar" class="w-4 h-4 inline-block mr-1.5"></i>Date of Onset</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><i data-lucide="activity" class="w-4 h-4 inline-block mr-1.5"></i>Status</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${tableRows}
                </tbody>
            </table>
        `;

        // --- Add event listeners for the new status dropdowns ---
        attachStatusChangeListeners();
        // --- Re-render Lucide icons ---
        lucide.createIcons();
    }

    // --- Event Listener for Quick Status Change ---
    function attachStatusChangeListeners() {
        document.querySelectorAll('.status-select-badge').forEach(select => {
            // Remove old listener to prevent duplicates
            select.removeEventListener('change', handleStatusChange);
            // Add new listener
            select.addEventListener('change', handleStatusChange);
        });
    }

    async function handleStatusChange(e) {
        const selectElement = e.target;
        const woundId = selectElement.dataset.woundId;
        const newStatus = selectElement.value;

        // Get the current color class to restore it on failure
        const originalColorClass = selectElement.className.match(/(bg-\w+-\d+ text-\w+-\d+)/)[0];

        // Optimistically update UI
        selectElement.className = selectElement.className.replace(originalColorClass, 'bg-gray-100 text-gray-800');

        try {
            const response = await fetch('api/update_wound_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ wound_id: woundId, new_status: newStatus })
            });

            const result = await response.json();

            if (response.ok && result.success) {
                // Success: update UI permanently
                let newColorClass = 'bg-gray-100 text-gray-800';
                if (newStatus === 'Active') {
                    newColorClass = 'bg-red-100 text-red-800';
                } else if (newStatus === 'Healed') {
                    newColorClass = 'bg-green-100 text-green-800';
                } else if (newStatus === 'Inactive') {
                    newColorClass = 'bg-yellow-100 text-yellow-800';
                }
                selectElement.className = selectElement.className.replace('bg-gray-100 text-gray-800', newColorClass);
                showPageMessage('Status updated successfully!', false);

                // Update the global data object
                const wound = globalWoundsData.find(w => w.wound_id == woundId);
                if (wound) {
                    wound.status = newStatus;
                }
                // Regenerate narrative
                if (typeof updateAutoNarrative === 'function') {
                    updateAutoNarrative();
                }

            } else {
                // Failure: revert UI and show error
                throw new Error(result.message || 'Failed to update status');
            }

        } catch (error) {
            // Revert UI on error
            selectElement.className = selectElement.className.replace('bg-gray-100 text-gray-800', originalColorClass);
            selectElement.value = globalWoundsData.find(w => w.wound_id == woundId).status; // Revert selection
            console.error('Error updating status:', error);
            showPageMessage(error.message, true);
        }
    }


    // --- Auto-Narrative Logic ---
    function showNarrativeSpinner(isLoading) {
        if (isLoading) {
            narrativeSpinner.style.display = 'flex';
            autoNarrativeContent.style.display = 'none';
        } else {
            narrativeSpinner.style.display = 'none';
            autoNarrativeContent.style.display = 'block';
        }
    }

    copyNarrativeBtn.addEventListener('click', () => {
        // This function now uses the global data objects
        if (typeof updateAutoNarrative !== 'function') {
            alert('Narrative generation script is not loaded.');
            return;
        }

        // Generate the full narrative text
        const hpiText = generateHpiNarrativeSentence(currentHpiData) || '';
        const vitalsText = (currentVitalsData && currentVitalsData.vitals_id) ? `Latest vitals: BP ${currentVitalsData.blood_pressure}, HR ${currentVitalsData.heart_rate}, Temp ${currentVitalsData.temperature_celsius}°C, O2 Sat ${currentVitalsData.oxygen_saturation}%.` : '';
        const woundText = generateWoundNarrative(globalWoundsData) || '';

        const fullNarrative = [hpiText, vitalsText, woundText].filter(Boolean).join('\n\n');

        if (fullNarrative.trim().length === 0) {
            alert('No content in the Auto-Narrative panel to copy.');
            return;
        }
        copyToClipboard(fullNarrative);
        showPageMessage('Auto-Narrative copied to clipboard!', false);
    });

    // --- 'Add Wound' Modal Logic ---
    function showPageMessage(message, isError = false) {
        pageMessage.textContent = message;
        pageMessage.className = `p-3 mb-4 rounded-md transition-all duration-300 ${isError ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`;
        pageMessage.classList.remove('hidden');
        setTimeout(() => pageMessage.classList.add('hidden'), 4000);
    }

    function showModalMessage(message, isError = false) {
        modalMessage.textContent = message;
        modalMessage.className = `p-3 my-3 rounded-md ${isError ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}`;
        modalMessage.classList.remove('hidden');
    }

    function openAddWoundModal() {
        addWoundForm.reset();
        modalMessage.classList.add('hidden');
        addWoundModal.classList.remove('hidden');
        addWoundModal.classList.add('flex');
        // Render icons inside this modal
        lucide.createIcons();
    }

    function closeAddWoundModal() {
        addWoundModal.classList.add('hidden');
        addWoundModal.classList.remove('flex');
    }

    openAddWoundModalBtn.addEventListener('click', openAddWoundModal);
    closeModalBtn.addEventListener('click', closeAddWoundModal);
    cancelModalBtn.addEventListener('click', closeAddWoundModal);

    addWoundForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Get data from form
        const formData = {
            patient_id: patientId,
            location: locationHiddenInput.value,
            wound_type: woundTypeHiddenInput.value,
            date_onset: document.getElementById('date_onset').value
        };

        submitBtn.disabled = true;
        // *** MODIFICATION: Changed icon for mobile ***
        submitBtn.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div> Saving...';

        try {
            const response = await fetch('api/create_wound.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const result = await response.json();

            if (response.ok && result.message) {
                closeAddWoundModal();
                showPageMessage('Wound successfully registered!', false);
                // Re-fetch data to update the list
                await fetchInitialData();
            } else {
                showModalMessage(result.message || 'Unable to add wound.', true);
            }
        } catch (error) {
            console.error('Error submitting form:', error);
            showModalMessage('An unexpected error occurred. Please try again.', true);
        } finally {
            submitBtn.disabled = false;
            // *** MODIFICATION: Changed icon for mobile ***
            submitBtn.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2"></i> Save Wound';
            // Re-render the icon
            lucide.createIcons();
        }
    });

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
                    itemElement.className = 'custom-select-item';

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

    setupSearchableList(locationSearchInput, locationHiddenInput, locationListContainer, WOUND_LOCATIONS);
    setupSearchableList(woundTypeSearchInput, woundTypeHiddenInput, woundTypeListContainer, WOUND_TYPES);

    // --- 'Wound Map' Modal Logic ---
    function openWoundMapModal() {
        woundMapModal.classList.remove('hidden');
        woundMapModal.classList.add('flex');
        // *** FIX: Render icons when modal is opened ***
        lucide.createIcons();
    }

    function closeWoundMapModal() {
        woundMapModal.classList.add('hidden');
        woundMapModal.classList.remove('flex');
    }

    openWoundMapBtn.addEventListener('click', openWoundMapModal);
    closeWoundMapBtn.addEventListener('click', closeWoundMapModal);

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
            locationSearchInput.value = location;
            locationHiddenInput.value = location;
            closeWoundMapModal();
        });
    });

    // --- Kick-off Initial Data Load ---
    fetchInitialData();
});