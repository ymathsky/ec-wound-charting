<?php
// Filename: add_patient_form.php

require_once 'templates/header.php';
?>

<style>
    /* Simple transition for accordion */
    .accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease-out;
    }
</style>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Register New Patient</h1>
                <p class="text-sm text-gray-600">Enter the patient's information below.</p>
            </div>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="max-w-3xl mx-auto">
                <div id="form-message" class="hidden p-3 mb-4 rounded-md"></div>
                <form id="addPatientForm" class="space-y-4">

                    <!-- Section 1: Demographics -->
                    <div class="bg-white rounded-lg shadow-md">
                        <button type="button" class="accordion-header w-full flex justify-between items-center p-4 text-left font-semibold text-gray-800">
                            1. Patient Demographics & Contact
                            <i data-lucide="chevron-down" class="w-5 h-5 transition-transform transform rotate-180"></i>
                        </button>
                        <div class="accordion-content" style="max-height: 1000px;">
                            <div class="p-4 border-t space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="first_name" class="form-label">First Name</label>
                                        <input type="text" name="first_name" id="first_name" required class="form-input">
                                    </div>
                                    <div>
                                        <label for="last_name" class="form-label">Last Name</label>
                                        <input type="text" name="last_name" id="last_name" required class="form-input">
                                    </div>
                                </div>
                                <div>
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" required class="form-input">
                                </div>
                                <div>
                                    <label for="gender" class="form-label">Gender</label>
                                    <select name="gender" id="gender" required class="form-input bg-white">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="contact_number" class="form-label">Contact Number</label>
                                    <input type="tel" name="contact_number" id="contact_number" class="form-input">
                                </div>
                                <div>
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" name="email" id="email" class="form-input">
                                </div>
                                <div>
                                    <label for="address" class="form-label">Address</label>
                                    <textarea name="address" id="address" rows="3" class="form-input"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Clinical Information -->
                    <div class="bg-white rounded-lg shadow-md">
                        <button type="button" class="accordion-header w-full flex justify-between items-center p-4 text-left font-semibold text-gray-800">
                            2. Clinical Information
                            <i data-lucide="chevron-down" class="w-5 h-5 transition-transform"></i>
                        </button>
                        <div class="accordion-content">
                            <div class="p-4 border-t space-y-4">
                                <div>
                                    <label for="allergies" class="form-label">Known Allergies</label>
                                    <textarea name="allergies" id="allergies" rows="3" class="form-input" placeholder="e.g., Penicillin, Aspirin..."></textarea>
                                </div>
                                <div>
                                    <label for="past_medical_history" class="form-label">Significant Past Medical History</label>
                                    <textarea name="past_medical_history" id="past_medical_history" rows="4" class="form-input" placeholder="e.g., Hypertension, Diabetes Mellitus Type 2..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 3: Assignments -->
                    <div class="bg-white rounded-lg shadow-md">
                        <button type="button" class="accordion-header w-full flex justify-between items-center p-4 text-left font-semibold text-gray-800">
                            3. Assignments
                            <i data-lucide="chevron-down" class="w-5 h-5 transition-transform"></i>
                        </button>
                        <div class="accordion-content">
                            <div class="p-4 border-t space-y-4">
                                <div>
                                    <label for="primary_user_id" class="form-label">Assign Primary Clinician</label>
                                    <select name="primary_user_id" id="primary_user_id" class="form-input bg-white">
                                        <option value="">Loading clinicians...</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="facility_id" class="form-label">Assign Facility</label>
                                    <select name="facility_id" id="facility_id" class="form-input bg-white">
                                        <option value="">Loading facilities...</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 4: Emergency Contact -->
                    <div class="bg-white rounded-lg shadow-md">
                        <button type="button" class="accordion-header w-full flex justify-between items-center p-4 text-left font-semibold text-gray-800">
                            4. Emergency Contact
                            <i data-lucide="chevron-down" class="w-5 h-5 transition-transform"></i>
                        </button>
                        <div class="accordion-content">
                            <div class="p-4 border-t space-y-4">
                                <div>
                                    <label for="emergency_contact_name" class="form-label">Contact Name</label>
                                    <input type="text" name="emergency_contact_name" id="emergency_contact_name" class="form-input">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="emergency_contact_relationship" class="form-label">Relationship</label>
                                        <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" class="form-input">
                                    </div>
                                    <div>
                                        <label for="emergency_contact_phone" class="form-label">Contact Phone</label>
                                        <input type="tel" name="emergency_contact_phone" id="emergency_contact_phone" class="form-input">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section 5: Insurance Details -->
                    <div class="bg-white rounded-lg shadow-md">
                        <button type="button" class="accordion-header w-full flex justify-between items-center p-4 text-left font-semibold text-gray-800">
                            5. Insurance Details
                            <i data-lucide="chevron-down" class="w-5 h-5 transition-transform"></i>
                        </button>
                        <div class="accordion-content">
                            <div class="p-4 border-t space-y-4">
                                <div>
                                    <label for="insurance_provider" class="form-label">Insurance Provider</label>
                                    <input type="text" name="insurance_provider" id="insurance_provider" class="form-input">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="insurance_policy_number" class="form-label">Policy Number</label>
                                        <input type="text" name="insurance_policy_number" id="insurance_policy_number" class="form-input">
                                    </div>
                                    <div>
                                        <label for="insurance_group_number" class="form-label">Group Number</label>
                                        <input type="text" name="insurance_group_number" id="insurance_group_number" class="form-input">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition">
                            Save Patient Record
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('addPatientForm');
        const messageDiv = document.getElementById('form-message');
        const submitButton = form.querySelector('button[type="submit"]');

        // Accordion functionality
        document.querySelectorAll('.accordion-header').forEach(header => {
            header.addEventListener('click', () => {
                const content = header.nextElementSibling;
                const icon = header.querySelector('i');
                const isOpen = content.style.maxHeight && content.style.maxHeight !== '0px';

                if (isOpen) {
                    content.style.maxHeight = '0px';
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    content.style.maxHeight = content.scrollHeight + 'px';
                    icon.style.transform = 'rotate(180deg)';
                }
            });
        });

        // --- Real-time Duplicate Check ---
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const dobInput = document.getElementById('date_of_birth');

        async function checkForDuplicates() {
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const dob = dobInput.value;

            if (firstName && lastName && dob) {
                try {
                    const response = await fetch(`api/check_patient_duplicate.php?first_name=${encodeURIComponent(firstName)}&last_name=${encodeURIComponent(lastName)}&date_of_birth=${encodeURIComponent(dob)}`);
                    const result = await response.json();

                    if (result.exists) {
                        messageDiv.textContent = 'Warning: A patient with this name and date of birth already exists.';
                        messageDiv.className = 'p-3 mb-4 rounded-md bg-yellow-100 text-yellow-800';
                        messageDiv.classList.remove('hidden');
                        submitButton.disabled = true;
                        submitButton.classList.add('bg-gray-400', 'cursor-not-allowed');
                    } else {
                        messageDiv.classList.add('hidden');
                        submitButton.disabled = false;
                        submitButton.classList.remove('bg-gray-400', 'cursor-not-allowed');
                    }
                } catch (error) {
                    console.error('Duplicate check failed:', error);
                    submitButton.disabled = false;
                }
            }
        }
        [firstNameInput, lastNameInput, dobInput].forEach(input => input.addEventListener('blur', checkForDuplicates));

        // --- Populate Dropdowns ---
        async function populateSelect(selectElement, url, placeholder, nameField, valueField) {
            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`Failed to fetch ${placeholder}`);
                const data = await response.json();

                selectElement.innerHTML = `<option value="">Select a ${placeholder}</option>`;
                data.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item[valueField];
                    option.textContent = item[nameField];
                    selectElement.appendChild(option);
                });
            } catch (error) {
                selectElement.innerHTML = `<option value="">Could not load ${placeholder}</option>`;
            }
        }

        populateSelect(document.getElementById('primary_user_id'), 'api/get_users.php', 'clinician', 'full_name', 'user_id');
        populateSelect(document.getElementById('facility_id'), 'api/get_facilities.php', 'facility', 'name', 'facility_id');

        // --- Form Submission ---
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            const patientData = Object.fromEntries(new FormData(form).entries());

            try {
                const response = await fetch('api/create_patient.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(patientData)
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);

                messageDiv.textContent = result.message;
                messageDiv.className = 'p-3 mb-4 rounded-md bg-green-100 text-green-800';
                messageDiv.classList.remove('hidden');
                form.reset();

                setTimeout(() => { window.location.href = 'view_patients.php'; }, 1500);

            } catch (error) {
                messageDiv.textContent = `Error: ${error.message}`;
                messageDiv.className = 'p-3 mb-4 rounded-md bg-red-100 text-red-800';
                messageDiv.classList.remove('hidden');
            }
        });

        lucide.createIcons();
    });
</script>

<?php
require_once 'templates/footer.php';
?>
