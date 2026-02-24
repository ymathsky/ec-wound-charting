<?php
// Filename: manage_medications.php
require_once 'templates/header.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    echo "<div class='flex h-screen bg-gray-100'><main class='flex-1 p-6'><div class='max-w-4xl mx-auto bg-white p-6 rounded-lg shadow'>";
    echo "<h2 class='text-2xl font-bold text-red-600'>Access Denied</h2><p class='mt-4 text-gray-700'>You do not have permission to access this page.</p>";
    echo "</div></main></div>";
    require_once 'templates/footer.php';
    exit();
}
?>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Medication Management</h1>
                <p class="text-sm text-gray-600">Manage the medication library and patient medication logs.</p>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 space-y-6">
            <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>
            <!-- Medication Library Management Section -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Medication Library Management</h2>
                    <button id="addLibraryMedicationBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition text-sm">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add to Library
                    </button>
                </div>
                <div id="medication-library-table-container" class="overflow-x-auto">
                    <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                </div>
            </div>
            <!-- Patient Medication Log Section -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">Patient Medication Log</h2>
                    <button id="addPatientMedicationBtn" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition text-sm">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add New Patient Medication
                    </button>
                </div>
                <div id="patient-medication-table-container" class="overflow-x-auto">
                    <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add/Edit Patient Medication Modal -->
<div id="patientMedicationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-2xl w-full">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 id="patient-medication-modal-title" class="text-xl font-semibold text-gray-800">Add New Patient Medication</h3>
            <button id="closePatientMedicationModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        <form id="patientMedicationForm" class="space-y-4">
            <input type="hidden" name="medication_id" id="patient_medication_id">
            <div>
                <label for="patient_id" class="form-label">Patient</label>
                <select name="patient_id" id="patient_id" required class="form-input bg-white">
                    <option value="">Loading patients...</option>
                </select>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="drug_name_select" class="form-label">Drug Name (from Library)</label>
                    <select name="drug_name" id="drug_name_select" required class="form-input bg-white">
                        <option value="">Select a medication...</option>
                    </select>
                </div>
                <div>
                    <label for="dosage" class="form-label">Dosage (e.g., 500mg)</label>
                    <input type="text" name="dosage" id="dosage" required class="form-input">
                </div>
                <div>
                    <label for="frequency" class="form-label">Frequency (e.g., BID, QDay)</label>
                    <input type="text" name="frequency" id="frequency" required class="form-input">
                </div>
                <div>
                    <label for="route" class="form-label">Route (e.g., PO, IV)</label>
                    <input type="text" name="route" id="route" class="form-input">
                </div>
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancelPatientMedicationModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 font-semibold">Save Medication</button>
            </div>
        </form>
    </div>
</div>

<!-- Add/Edit Medication Library Modal -->
<div id="medicationLibraryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
        <div class="flex justify-between items-center border-b pb-3 mb-4">
            <h3 id="medication-library-modal-title" class="text-xl font-semibold text-gray-800">Add to Medication Library</h3>
            <button id="closeMedicationLibraryModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
        </div>
        <form id="medicationLibraryForm" class="space-y-4">
            <input type="hidden" name="id" id="library_medication_id">
            <div>
                <label for="library_name" class="form-label">Medication Name</label>
                <input type="text" name="name" id="library_name" required class="form-input">
            </div>
            <div>
                <label for="library_description" class="form-label">Description</label>
                <textarea name="description" id="library_description" rows="3" class="form-input"></textarea>
            </div>
            <div>
                <label for="library_default_dosage" class="form-label">Default Dosage</label>
                <input type="text" name="default_dosage" id="library_default_dosage" class="form-input">
            </div>
            <div>
                <label for="library_default_frequency" class="form-label">Default Frequency</label>
                <input type="text" name="default_frequency" id="library_default_frequency" class="form-input">
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancelMedicationLibraryModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 font-semibold">Save to Library</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageMessage = document.getElementById('page-message');

        // --- Patient Medications ---
        const patientMedicationTableContainer = document.getElementById('patient-medication-table-container');
        const patientMedicationModal = document.getElementById('patientMedicationModal');
        const addPatientMedicationBtn = document.getElementById('addPatientMedicationBtn');
        const patientMedicationForm = document.getElementById('patientMedicationForm');
        let allPatientMedications = [];
        document.getElementById('closePatientMedicationModalBtn').addEventListener('click', () => patientMedicationModal.classList.add('hidden'));
        document.getElementById('cancelPatientMedicationModalBtn').addEventListener('click', () => patientMedicationModal.classList.add('hidden'));

        // --- Medication Library ---
        const medicationLibraryTableContainer = document.getElementById('medication-library-table-container');
        const medicationLibraryModal = document.getElementById('medicationLibraryModal');
        const addLibraryMedicationBtn = document.getElementById('addLibraryMedicationBtn');
        const medicationLibraryForm = document.getElementById('medicationLibraryForm');
        let medicationLibrary = [];
        document.getElementById('closeMedicationLibraryModalBtn').addEventListener('click', () => medicationLibraryModal.classList.add('hidden'));
        document.getElementById('cancelMedicationLibraryModalBtn').addEventListener('click', () => medicationLibraryModal.classList.add('hidden'));

        function showPageMessage(message, type) {
            pageMessage.textContent = message;
            pageMessage.className = 'p-3 mb-4 rounded-md';
            if (type === 'error') pageMessage.classList.add('bg-red-100', 'text-red-800');
            else pageMessage.classList.add('bg-green-100', 'text-green-800');
            pageMessage.classList.remove('hidden');
            setTimeout(() => pageMessage.classList.add('hidden'), 5000);
        }

        // --- MEDICATION LIBRARY LOGIC ---
        async function fetchMedicationLibrary() {
            try {
                const response = await fetch('api/get_medication_library.php');
                if (!response.ok) throw new Error('Failed to fetch medication library.');
                medicationLibrary = await response.json();
                renderMedicationLibraryTable(medicationLibrary);
                populateMedicationLibrarySelect();
            } catch (error) {
                medicationLibraryTableContainer.innerHTML = `<p class="text-red-500 text-center">${error.message}</p>`;
            }
        }

        function renderMedicationLibraryTable(meds) {
            medicationLibraryTableContainer.innerHTML = '';
            if (meds.length === 0) {
                medicationLibraryTableContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No medications in the library.</p>';
                return;
            }
            const tableRows = meds.map(med => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 font-semibold">${med.name}</td>
                    <td class="px-6 py-4">${med.description || ''}</td>
                    <td class="px-6 py-4">${med.default_dosage || ''}</td>
                    <td class="px-6 py-4">${med.default_frequency || ''}</td>
                    <td class="px-6 py-4 text-right space-x-4">
                        <button data-id="${med.id}" class="edit-library-med-btn text-blue-600 hover:text-blue-800 font-semibold">Edit</button>
                        <button data-id="${med.id}" class="delete-library-med-btn text-red-600 hover:text-red-800 font-semibold">Delete</button>
                    </td>
                </tr>
            `).join('');
            medicationLibraryTableContainer.innerHTML = `
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Default Dosage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Default Frequency</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>`;
        }

        function openMedicationLibraryModal(med = null) {
            medicationLibraryForm.reset();
            document.getElementById('library_medication_id').value = '';
            if (med) {
                document.getElementById('medication-library-modal-title').textContent = 'Edit Library Medication';
                document.getElementById('library_medication_id').value = med.id;
                document.getElementById('library_name').value = med.name;
                document.getElementById('library_description').value = med.description;
                document.getElementById('library_default_dosage').value = med.default_dosage;
                document.getElementById('library_default_frequency').value = med.default_frequency;
            } else {
                document.getElementById('medication-library-modal-title').textContent = 'Add to Medication Library';
            }
            medicationLibraryModal.classList.remove('hidden');
            medicationLibraryModal.classList.add('flex');
        }
        addLibraryMedicationBtn.addEventListener('click', () => openMedicationLibraryModal());

        medicationLibraryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(medicationLibraryForm).entries());
            try {
                const response = await fetch('api/manage_medication_library.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                showPageMessage(result.message, 'success');
                fetchMedicationLibrary();
                medicationLibraryModal.classList.add('hidden');
            } catch (error) { showPageMessage(error.message, 'error'); }
        });

        medicationLibraryTableContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-library-med-btn')) {
                const medId = e.target.dataset.id;
                const medToEdit = medicationLibrary.find(m => m.id == medId);
                openMedicationLibraryModal(medToEdit);
            }
            if (e.target.classList.contains('delete-library-med-btn')) {
                if (confirm('Are you sure you want to delete this from the library?')) deleteLibraryMedication(e.target.dataset.id);
            }
        });

        async function deleteLibraryMedication(id) {
            try {
                const response = await fetch('api/manage_medication_library.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                showPageMessage(result.message, 'success');
                fetchMedicationLibrary();
            } catch (error) { showPageMessage(error.message, 'error'); }
        }

        // --- PATIENT MEDICATION LOGIC ---
        async function fetchPatientMedications() {
            try {
                const response = await fetch('api/get_all_medications.php');
                if (!response.ok) throw new Error('Failed to fetch patient medications.');
                allPatientMedications = await response.json();
                renderPatientMedicationTable(allPatientMedications);
            } catch (error) {
                patientMedicationTableContainer.innerHTML = `<p class="text-red-500 text-center">${error.message}</p>`;
            }
        }
        function renderPatientMedicationTable(meds) {
            patientMedicationTableContainer.innerHTML = ''; // Clear spinner
            if (meds.length === 0) {
                patientMedicationTableContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No patient medications found.</p>';
                return;
            }
            const tableRows = meds.map(med => `
                 <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap font-semibold">${med.patient_name}</td>
                    <td class="px-6 py-4">${med.drug_name}</td>
                    <td class="px-6 py-4">${med.dosage}</td>
                    <td class="px-6 py-4">${med.frequency}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right space-x-4">
                        <button data-id="${med.medication_id}" class="edit-patient-med-btn text-blue-600 hover:text-blue-800 font-semibold">Edit</button>
                        <button data-id="${med.medication_id}" class="delete-patient-med-btn text-red-600 hover:text-red-800 font-semibold">Delete</button>
                    </td>
                </tr>
            `).join('');
            patientMedicationTableContainer.innerHTML = `
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Drug Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Dosage</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Frequency</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>
            `;
        }

        async function populatePatientSelect() {
            const patientSelect = document.getElementById('patient_id');
            try {
                const response = await fetch('api/get_patients.php');
                if (!response.ok) throw new Error('Failed to fetch patients');
                const patients = await response.json();
                patientSelect.innerHTML = '<option value="">Select a patient</option>';
                patients.forEach(p => {
                    patientSelect.innerHTML += `<option value="${p.patient_id}">${p.last_name}, ${p.first_name}</option>`;
                });
            } catch (error) {
                patientSelect.innerHTML = '<option value="">Could not load patients</option>';
            }
        }

        function openPatientMedicationModal(med = null) {
            patientMedicationForm.reset();
            document.getElementById('patient_medication_id').value = '';
            populatePatientSelect();
            if (med) {
                document.getElementById('patient-medication-modal-title').textContent = 'Edit Patient Medication';
                document.getElementById('patient_medication_id').value = med.medication_id;
                setTimeout(() => { document.getElementById('patient_id').value = med.patient_id; }, 100);
                document.getElementById('drug_name_select').value = med.drug_name;
                document.getElementById('dosage').value = med.dosage;
                document.getElementById('frequency').value = med.frequency;
                document.getElementById('route').value = med.route;
            } else {
                document.getElementById('patient-medication-modal-title').textContent = 'Add New Patient Medication';
            }
            patientMedicationModal.classList.remove('hidden');
            patientMedicationModal.classList.add('flex');
        }

        addPatientMedicationBtn.addEventListener('click', () => openPatientMedicationModal());

        function populateMedicationLibrarySelect() {
            const select = document.getElementById('drug_name_select');
            select.innerHTML = '<option value="">Select from library...</option>';
            medicationLibrary.forEach(med => {
                select.innerHTML += `<option value="${med.name}">${med.name}</option>`;
            });
        }

        document.getElementById('drug_name_select').addEventListener('change', function(e) {
            const medName = e.target.value;
            const med = medicationLibrary.find(m => m.name === medName);
            if (med) {
                document.getElementById('dosage').value = med.default_dosage || '';
                document.getElementById('frequency').value = med.default_frequency || '';
            }
        });

        patientMedicationForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(patientMedicationForm).entries());
            try {
                const response = await fetch('api/manage_medication.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                showPageMessage(result.message, 'success');
                fetchPatientMedications();
                patientMedicationModal.classList.add('hidden');
            } catch (error) { showPageMessage(error.message, 'error'); }
        });

        patientMedicationTableContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-patient-med-btn')) {
                const medId = e.target.dataset.id;
                const medToEdit = allPatientMedications.find(m => m.medication_id == medId);
                openPatientMedicationModal(medToEdit);
            }
            if (e.target.classList.contains('delete-patient-med-btn')) {
                if (confirm('Are you sure you want to delete this medication entry?')) {
                    deletePatientMedication(e.target.dataset.id);
                }
            }
        });

        async function deletePatientMedication(id) {
            try {
                const response = await fetch('api/manage_medication.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ medication_id: id }) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                showPageMessage(result.message, 'success');
                fetchPatientMedications();
            } catch (error) { showPageMessage(error.message, 'error'); }
        }


        // --- Initial Load ---
        fetchCptCodes();
        fetchPatientMedications();
        fetchMedicationLibrary();
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>
