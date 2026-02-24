<?php
require_once 'db_connect.php';
session_start();

// --- Authentication Check ---
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// Basic validation to ensure IDs are passed
if (!isset($_GET['patient_id']) || !isset($_GET['appointment_id'])) {
    die("Patient ID and Appointment ID are required.");
}
// intval() for proper numeric sanitization of IDs
$patient_id = intval($_GET['patient_id']);
$appointment_id = intval($_GET['appointment_id']);

if ($patient_id <= 0 || $appointment_id <= 0) {
    die("Invalid Patient ID or Appointment ID.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History of Present Illness</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .form-section {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 0.75rem;
        }
    </style>
</head>
<body class="bg-gray-100 p-4 sm:p-8">
<div class="max-w-4xl w-full mx-auto bg-white rounded-xl shadow-2xl p-6 sm:p-8">
    <div id="message-box" class="hidden fixed top-5 right-5 p-4 rounded-lg text-white z-50 shadow-lg" role="alert"></div>
    <h1 class="text-3xl font-extrabold text-center text-gray-800 mb-2">History of Present Illness (HPI)</h1>
    <p class="text-center text-gray-600 mb-8">
        Patient ID: <?php echo $patient_id; ?> | Appointment ID: <?php echo $appointment_id; ?>
    </p>

    <form id="hpi-form" class="space-y-6">
        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">

        <!-- Problem Status & Impact -->
        <div class="form-section">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Problem Status & Impact</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="problem_status" class="block text-sm font-medium text-gray-700">What is the problem’s status?</label>
                    <select name="problem_status" id="problem_status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <option>Worsening</option>
                        <option>Improving</option>
                        <option>Unchanged</option>
                        <option>Resolved</option>
                        <option>New Problem</option>
                    </select>
                </div>
                <div>
                    <label for="functional_capacity" class="block text-sm font-medium text-gray-700">How is it affecting functional capacity or quality of life?</label>
                    <select name="functional_capacity" id="functional_capacity" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <option>No Impact</option>
                        <option>Limited</option>
                        <option>Significant</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="self_limited" class="block text-sm font-medium text-gray-700">Is this problem likely self-limited?</label>
                    <select name="self_limited" id="self_limited" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <option>Yes, but not progressing as expected</option>
                        <option>No</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Pain Assessment -->
        <div class="form-section">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Pain Assessment</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="pain_duration" class="block text-sm font-medium text-gray-700">How long have you had pain?</label>
                    <input type="text" name="pain_duration" id="pain_duration" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., 1 month, 2 weeks">
                </div>
                <div>
                    <label for="pain_temporal_nature" class="block text-sm font-medium text-gray-700">Is there any temporal nature to your pain?</label>
                    <select name="pain_temporal_nature" id="pain_temporal_nature" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <option>Occasional</option>
                        <option>Constant</option>
                        <option>With activity</option>
                        <option>At rest</option>
                    </select>
                </div>
                <div>
                    <label for="pain_rating" class="block text-sm font-medium text-gray-700">How do you rate your pain?</label>
                    <select name="pain_rating" id="pain_rating" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2">
                        <?php for ($i = 0; $i <= 10; $i++) echo "<option value='{$i}'>{$i}</option>"; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Contributing Factors & History -->
        <div class="form-section">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Contributing Factors & History</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">What event was the pressure injury related to?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="injury_event[]" value="Medical Device" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Medical Device</label>
                        <label class="flex items-center"><input type="checkbox" name="injury_event[]" value="Nursing home stay" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Nursing home stay</label>
                        <label class="flex items-center"><input type="checkbox" name="injury_event[]" value="Operative procedure" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Operative procedure</label>
                        <label class="flex items-center"><input type="checkbox" name="injury_event[]" value="Paralysis or immobility" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Paralysis or immobility</label>
                        <label class="flex items-center"><input type="checkbox" name="injury_event[]" value="Rehabilitation facility" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Rehabilitation facility</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">What risk factors contributed to its development?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="risk_factors[]" value="Dementia" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Dementia</label>
                        <label class="flex items-center"><input type="checkbox" name="risk_factors[]" value="Episode of low blood pressure" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Episode of low blood pressure</label>
                        <label class="flex items-center"><input type="checkbox" name="risk_factors[]" value="Poor nutrition or deceased food intake" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Poor nutrition / deceased food intake</label>
                        <label class="flex items-center"><input type="checkbox" name="risk_factors[]" value="Problems with mattress" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Problems with mattress</label>
                        <label class="flex items-center"><input type="checkbox" name="risk_factors[]" value="Reduced level of consciousness" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Reduced level of consciousness</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Symptoms and Infection -->
        <div class="form-section">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Symptoms & Infection</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">What other symptoms have you had?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="other_symptoms[]" value="Wound pain" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Wound pain</label>
                        <label class="flex items-center"><input type="checkbox" name="other_symptoms[]" value="Cellulitis" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Cellulitis</label>
                        <label class="flex items-center"><input type="checkbox" name="other_symptoms[]" value="Exposed bone" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Exposed bone</label>
                        <label class="flex items-center"><input type="checkbox" name="other_symptoms[]" value="Fever" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Fever</label>
                        <label class="flex items-center"><input type="checkbox" name="other_symptoms[]" value="Purulent Drainage" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Purulent Drainage</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Are there signs of infection today?</label>
                    <div class="mt-2 flex space-x-4">
                        <label class="flex items-center"><input type="radio" name="signs_of_infection" value="Yes" class="h-4 w-4 border-gray-300 text-blue-600 mr-2">Yes</label>
                        <label class="flex items-center"><input type="radio" name="signs_of_infection" value="No" class="h-4 w-4 border-gray-300 text-blue-600 mr-2">No</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Limb threatening?</label>
                    <div class="mt-2 flex space-x-4">
                        <label class="flex items-center"><input type="radio" name="limb_threatening" value="Yes" class="h-4 w-4 border-gray-300 text-blue-600 mr-2">Yes</label>
                        <label class="flex items-center"><input type="radio" name="limb_threatening" value="No" class="h-4 w-4 border-gray-300 text-blue-600 mr-2">No</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Interventions and Treatments -->
        <div class="form-section">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Interventions & Treatments</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">What evaluations or interventions have been required for this ulcer?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="interventions[]" value="Intravenous antibiotics" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Intravenous antibiotics</label>
                        <label class="flex items-center"><input type="checkbox" name="interventions[]" value="MRI" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">MRI</label>
                        <label class="flex items-center"><input type="checkbox" name="interventions[]" value="Operative debridement" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Operative debridement</label>
                        <label class="flex items-center"><input type="checkbox" name="interventions[]" value="Oral antibiotics" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Oral antibiotics</label>
                        <label class="flex items-center"><input type="checkbox" name="interventions[]" value="Plastic surgery" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Plastic surgery</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">What additional factors contribute to non-healing?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Lack of adherence to medical recommendation" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Lack of adherence</label>
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Osteomyelitis" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Osteomyelitis</label>
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Poor nutrition" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Poor nutrition</label>
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Renal failure" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Renal failure</label>
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Urinary incontinence" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Urinary incontinence</label>
                        <label class="flex items-center"><input type="checkbox" name="non_healing_factors[]" value="Wound infection" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Wound infection</label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">What treatment have been provided?</label>
                    <div class="mt-2 checkbox-grid">
                        <label class="flex items-center"><input type="checkbox" name="provided_treatments[]" value="Urine management by supra pubic" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Urine management by supra pubic</label>
                        <label class="flex items-center"><input type="checkbox" name="provided_treatments[]" value="Urine management via adult briefs" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Urine management via adult briefs</label>
                        <label class="flex items-center"><input type="checkbox" name="provided_treatments[]" value="Vitamin D" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Vitamin D</label>
                        <label class="flex items-center"><input type="checkbox" name="provided_treatments[]" value="Wound Care by nurses in care setting" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Wound Care by nurses in care setting</label>
                        <label class="flex items-center"><input type="checkbox" name="provided_treatments[]" value="Wound care by skilled home health" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">Wound care by skilled home health</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-4 mt-8 border-t">
            <button type="submit" class="bg-green-600 text-white font-bold py-2 px-6 rounded-lg shadow-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition duration-150 ease-in-out">
                Save HPI
            </button>
        </div>
    </form>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('hpi-form');
        const messageBox = document.getElementById('message-box');

        const showMessage = (message, type = 'info') => {
            if (messageBox) {
                messageBox.textContent = message;
                messageBox.classList.remove('hidden', 'bg-red-500', 'bg-green-500', 'bg-blue-500');
                if (type === 'error') messageBox.classList.add('bg-red-500');
                else if (type === 'success') messageBox.classList.add('bg-green-500');
                else messageBox.classList.add('bg-blue-500');

                messageBox.classList.remove('hidden');
                setTimeout(() => { messageBox.classList.add('hidden'); }, 3000);
            }
        };

        const populateForm = (data) => {
            for (const key in data) {
                if (data.hasOwnProperty(key)) {
                    const value = data[key];
                    const elements = form.elements[key];
                    const arrayElements = form.elements[key + '[]'];

                    // FIX for TypeError: (hpiData.injury_event || "").split is not a function
                    // Ensure the value is a string before splitting. If it's null/undefined/non-string, treat it as an empty array.
                    if (arrayElements && arrayElements.length > 0) {
                        // Safe splitting, handling null/non-string values gracefully
                        const values = (typeof value === 'string' && value) ? value.split(', ') : [];
                        arrayElements.forEach(checkbox => {
                            if (values.includes(checkbox.value)) {
                                checkbox.checked = true;
                            }
                        });
                    } else if (elements) { // Handle radio and other inputs
                        if (elements.length && elements[0].type === 'radio') {
                            const radioToSelect = Array.from(elements).find(rb => rb.value === value);
                            if (radioToSelect) radioToSelect.checked = true;
                        } else {
                            if(elements.value !== undefined){
                                elements.value = value;
                            }
                        }
                    }
                }
            }
        };

        const fetchHpiData = async () => {
            const appointmentId = "<?php echo $appointment_id; ?>";
            try {
                const response = await fetch(`api/get_hpi_data.php?appointment_id=${appointmentId}`);
                if (!response.ok) {
                    throw new Error('Failed to fetch HPI data. The endpoint returned a ' + response.status + ' status.');
                }

                const result = await response.json();
                if (result.success && result.data) {
                    populateForm(result.data);
                    showMessage('Existing HPI data loaded.', 'info');
                } else if (!result.data) {
                    console.log('No existing HPI data for this appointment.');
                }
            } catch (error) {
                console.error("Error fetching HPI data:", error);
                // We don't show an error message to the user here,
                // as it's normal for a new visit to have no HPI data.
            }
        };

        fetchHpiData();

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    if (!data[cleanKey]) data[cleanKey] = [];
                    data[cleanKey].push(value);
                } else {
                    data[key] = value;
                }
            }

            try {
                const response = await fetch('api/save_hpi.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                if(!response.ok){
                    const errorText = await response.text();
                    throw new Error(errorText);
                }

                const result = await response.json();
                if (result.success) {
                    showMessage(result.message, 'success');
                    // Post message to parent window with the saved data
                    window.parent.postMessage({
                        type: 'hpiSaved',
                        data: result.data
                    }, '*'); // Use a specific origin in production
                } else {
                    showMessage(result.message, 'error');
                }
            } catch (error) {
                showMessage('An error occurred while saving the data. Check the console for details.', 'error');
                console.error('Save error:', error);
            }
        });
    });
</script>

</body>
</html>
