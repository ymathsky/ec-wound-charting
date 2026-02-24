<?php
// Filename: patient_medication.php
session_start();

// Basic validation to ensure IDs are passed
if (!isset($_GET['patient_id'])) {
    die("Patient ID is required.");
}
$patient_id = htmlspecialchars($_GET['patient_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medication Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .form-input {
            margin-top: 0.25rem;
            display: block;
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border-left-color: #09f;
            animation: spin 1s ease infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-100 p-4 sm:p-6">
<div class="max-w-4xl w-full mx-auto bg-white rounded-xl shadow-lg p-6">
    <div id="message-box" class="hidden fixed top-5 right-5 p-4 rounded-lg text-white z-50 shadow-lg" role="alert"></div>

    <!-- New Medication Order Form -->
    <div class="mb-6 pb-6 border-b">
        <h3 class="text-xl font-semibold mb-4 text-gray-800">New Medication Order</h3>
        <div id="meds-message" class="hidden p-3 my-3 rounded-md"></div>
        <form id="medicationForm" class="space-y-4">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            <input type="hidden" name="medication_id" id="medication_id" value=""> <!-- For updates -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="drug_name" class="form-label">Drug Name</label>
                    <select name="drug_name" id="drug_name" required class="form-input bg-white">
                        <option value="">Loading library...</option>
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
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" name="start_date" id="start_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label for="end_date" class="form-label">End Date (Optional)</label>
                    <input type="date" name="end_date" id="end_date" class="form-input">
                </div>
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-input bg-white">
                        <option value="Active">Active</option>
                        <option value="Discontinued">Discontinued</option>
                        <option value="Completed">Completed</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="notes_meds" class="form-label">Order Notes</label>
                <textarea name="notes" id="notes_meds" rows="2" class="form-input"></textarea>
            </div>
            <div class="flex justify-end pt-4">
                <button type="submit" id="saveMedBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md transition">Save Medication</button>
            </div>
        </form>
    </div>

    <!-- Medication History Section -->
    <h3 class="text-xl font-semibold mb-4 text-gray-800">Medication History</h3>
    <div id="meds-history-container" class="overflow-x-auto">
        <p class="text-center text-gray-500 py-8">Loading medication history...</p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const patientId = <?php echo $patient_id; ?>;
        const medicationForm = document.getElementById('medicationForm');
        const medsMessage = document.getElementById('meds-message');
        const medsHistoryContainer = document.getElementById('meds-history-container');
        let globalMedsData = [];
        let medicationLibrary = []; // Store the library data

        function showMessage(element, message, type) {
            element.textContent = message;
            element.className = 'p-3 my-3 rounded-md mt-4';
            if (type === 'error') element.classList.add('bg-red-100', 'text-red-800');
            else if (type === 'success') element.classList.add('bg-green-100', 'text-green-800');
            else element.classList.add('bg-blue-100', 'text-blue-800');
            element.classList.remove('hidden');
            setTimeout(() => element.classList.add('hidden'), 5000);
        }

        // --- NEW: Fetch and Populate Medication Library ---
        async function populateMedicationLibrarySelect() {
            try {
                const response = await fetch('api/get_medication_library.php');
                if (!response.ok) throw new Error('Failed to fetch medication library.');
                medicationLibrary = await response.json(); // Store in global variable

                const drugNameSelect = document.getElementById('drug_name');
                drugNameSelect.innerHTML = '<option value="">Select from library...</option>';
                medicationLibrary.forEach(med => {
                    const option = document.createElement('option');
                    option.value = med.name;
                    option.textContent = med.name;
                    drugNameSelect.appendChild(option);
                });
            } catch (error) {
                document.getElementById('drug_name').innerHTML = '<option value="">Could not load library</option>';
                console.error("Error populating medication library:", error);
            }
        }

        // --- NEW: Event listener to auto-populate fields ---
        document.getElementById('drug_name').addEventListener('change', function() {
            const selectedMedName = this.value;
            const selectedMed = medicationLibrary.find(med => med.name === selectedMedName);

            if (selectedMed) {
                document.getElementById('dosage').value = selectedMed.default_dosage || '';
                document.getElementById('frequency').value = selectedMed.default_frequency || '';
            }
        });

        // --- Fetch and Render Logic ---
        async function fetchMedicationHistory() {
            medsHistoryContainer.innerHTML = '<div class="flex justify-center items-center py-8"><div class="spinner"></div></div>';

            try {
                const response = await fetch(`api/get_medications.php?patient_id=${patientId}`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`Failed to fetch medication history. Status: ${response.status}. Response: ${errorText.substring(0, 50)}...`);
                }

                globalMedsData = await response.json();
                renderMedicationHistory(globalMedsData);
            } catch (error) {
                medsHistoryContainer.innerHTML = `<p class="text-red-500 py-8">Error loading medications: ${error.message}</p>`;
                showMessage(medsMessage, `Error loading history. Check console.`, 'error');
                console.error("Error fetching medications:", error);
            }
        }

        function renderMedicationHistory(meds) {
            if (!meds || meds.length === 0) {
                medsHistoryContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No medications currently recorded for this patient.</p>';
                return;
            }

            const tableRows = meds.map(med => {
                const statusClass = med.status === 'Active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                return `
                <tr class="border-b border-gray-200 hover:bg-gray-50 text-sm" data-med-id="${med.medication_id}">
                    <td class="px-4 py-3 font-medium">${med.drug_name}</td>
                    <td class="px-4 py-3">${med.dosage} / ${med.frequency}</td>
                    <td class="px-4 py-3 whitespace-nowrap">${med.start_date}</td>
                    <td class="px-4 py-3 whitespace-nowrap">
                         <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                            ${med.status}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" data-med-id="${med.medication_id}" class="edit-med-btn text-indigo-600 hover:text-indigo-800 font-semibold text-xs">Edit</button>
                    </td>
                </tr>
                `
            }).join('');

            medsHistoryContainer.innerHTML = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Drug Name</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Dosage/Frequency</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Start Date</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>
            `;

            document.querySelectorAll('.edit-med-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const medId = parseInt(this.dataset.medId);
                    const med = globalMedsData.find(m => m.medication_id === medId);
                    if (med) loadMedicationForEdit(med);
                });
            });
        }

        function loadMedicationForEdit(med) {
            document.getElementById('medication_id').value = med.medication_id;
            document.getElementById('drug_name').value = med.drug_name;
            document.getElementById('dosage').value = med.dosage;
            document.getElementById('frequency').value = med.frequency;
            document.getElementById('start_date').value = med.start_date;
            document.getElementById('end_date').value = med.end_date || '';
            document.getElementById('status').value = med.status;
            document.getElementById('notes_meds').value = med.notes || '';

            document.getElementById('saveMedBtn').textContent = 'Update Medication';
            showMessage(medsMessage, `Editing medication: ${med.drug_name}. Click save to update.`, 'info');
        }

        // --- Form Submission Logic ---
        medicationForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(medicationForm);
            const data = Object.fromEntries(formData.entries());

            const saveBtn = document.getElementById('saveMedBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = data.medication_id ? 'Updating...' : 'Saving...';

            try {
                const response = await fetch('api/create_medication.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);

                showMessage(medsMessage, result.message, 'success');
                medicationForm.reset();
                document.getElementById('medication_id').value = ''; // Clear for new entry
                document.getElementById('saveMedBtn').textContent = 'Save Medication';
                fetchMedicationHistory();
            } catch (error) {
                showMessage(medsMessage, `Error: ${error.message}`, 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        // Initial data load on page ready
        fetchMedicationHistory();
        populateMedicationLibrarySelect();
    });
</script>
</body>
</html>

