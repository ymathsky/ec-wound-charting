<?php
// Filename: superbill.php
require_once 'templates/header.php';
require_once 'db_connect.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    // If the user is not an admin, show an access denied message and exit
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 flex flex-col overflow-hidden'>";
    echo "<header class='w-full bg-white p-4 flex justify-between items-center shadow-md'><h1>Access Denied</h1></header>";
    echo "<main class='flex-1 overflow-y-auto bg-gray-100 p-6'><div class='max-w-6xl mx-auto bg-white p-6 rounded-lg shadow'>";
    echo "<h2 class='text-2xl font-bold text-red-600'>Access Denied</h2>";
    echo "<p class='mt-4 text-gray-700'>You do not have the required permissions to view this page. Please contact an administrator if you believe this is an error.</p>";
    echo "</div></main></div></div>";
    require_once 'templates/footer.php';
    exit(); // Stop further script execution
}


$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;
?>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Superbill Generator</h1>
                <p id="patient-subheader" class="text-sm text-gray-600">Loading details for appointment #<?php echo $appointment_id; ?>...</p>
            </div>
            <button id="saveSuperbillBtn" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-md hover:bg-blue-700">Save Superbill</button>
        </header>
        <main class="flex-1 overflow-y-auto bg-gray-100 p-6">
            <div class="max-w-6xl mx-auto space-y-6">
                <!-- Patient Info Header -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-center text-2xl font-bold mb-4 text-gray-800">SUPERBILL</h2>
                    <div id="patient-info" class="border-t pt-4">
                        <div class="flex justify-center items-center h-24"><div class="spinner"></div></div>
                    </div>
                </div>

                <!-- Main Content Grid -->
                <div id="superbill-container" class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                    <!-- Superbill Preview (Selected Services) -->
                    <div class="lg:col-span-3 bg-white p-6 rounded-lg shadow">
                        <div id="superbill-preview">
                            <h3 class="font-bold mb-2 text-xl text-gray-800">Services Rendered:</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full">
                                    <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 uppercase tracking-wider">CPT Code</th>
                                        <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="text-left py-2 px-2 text-xs font-medium text-gray-500 uppercase tracking-wider">Units</th>
                                        <th class="py-2 px-2"></th>
                                    </tr>
                                    </thead>
                                    <tbody id="service-list" class="divide-y divide-gray-200">
                                    <!-- Services will be rendered here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- CPT Code Selection -->
                    <div class="lg:col-span-2 bg-white p-4 rounded-lg shadow">
                        <h2 class="text-lg font-semibold mb-2 text-gray-800">Available Services (CPT Codes)</h2>
                        <div class="mb-4">
                            <input type="text" id="cpt-search" placeholder="Search codes or descriptions..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div id="cpt-code-list" class="space-y-4 max-h-96 overflow-y-auto">
                            <div class="flex justify-center items-center h-32"><div class="spinner"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const appointmentId = <?php echo $appointment_id; ?>;
        const patientSubheader = document.getElementById('patient-subheader');
        const patientInfo = document.getElementById('patient-info');
        const serviceList = document.getElementById('service-list');
        const cptCodeList = document.getElementById('cpt-code-list');
        const saveSuperbillBtn = document.getElementById('saveSuperbillBtn');
        const cptSearchInput = document.getElementById('cpt-search');
        let allCptCodes = {};
        let selectedServices = [];

        async function initializeSuperbill() {
            try {
                // Fetch CPT codes
                const codesResponse = await fetch('api/get_cpt_codes.php');
                if (!codesResponse.ok) {
                    let errorMsg = `HTTP error! status: ${codesResponse.status}`;
                    try {
                        const errorData = await codesResponse.json();
                        errorMsg = errorData.message || JSON.stringify(errorData);
                    } catch (e) { /* Response not JSON */ }
                    throw new Error(`Failed to load CPT codes. ${errorMsg}`);
                }
                allCptCodes = await codesResponse.json();
                renderCptCodeList();

                // Fetch Superbill data
                const superbillResponse = await fetch(`api/get_superbill_data.php?appointment_id=${appointmentId}`);
                if (!superbillResponse.ok) {
                    throw new Error('Failed to load superbill data.');
                }
                const data = await superbillResponse.json();

                const details = data.details;
                patientSubheader.textContent = `For ${details.first_name} ${details.last_name} on ${new Date(details.appointment_date).toLocaleDateString()}`;
                patientInfo.innerHTML = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div><strong class="block text-gray-500">Patient:</strong> <span class="font-semibold text-gray-800">${details.first_name} ${details.last_name}</span></div>
                    <div><strong class="block text-gray-500">DOB:</strong> <span class="font-semibold text-gray-800">${details.date_of_birth}</span></div>
                    <div><strong class="block text-gray-500">Date of Service:</strong> <span class="font-semibold text-gray-800">${new Date(details.appointment_date).toLocaleDateString()}</span></div>
                    <div><strong class="block text-gray-500">Clinician:</strong> <span class="font-semibold text-gray-800">${details.clinician_name || 'N/A'}</span></div>
                </div>
                `;
                selectedServices = data.services || [];
                renderServiceList();
            } catch (error) {
                console.error(error);
                patientSubheader.textContent = "Error loading data.";
                cptCodeList.innerHTML = `<p class="text-red-600 p-2">${error.message}</p>`;
                patientInfo.innerHTML = `<p class="text-red-600 p-2 text-center">${error.message}</p>`;
            }
        }

        function renderCptCodeList(searchTerm = '') {
            let content = Object.keys(allCptCodes).map(category => {
                const filteredCodes = allCptCodes[category].filter(code =>
                    code.code.toLowerCase().includes(searchTerm) ||
                    code.description.toLowerCase().includes(searchTerm)
                );

                if (filteredCodes.length === 0) {
                    return '';
                }

                return `
                <div>
                    <h4 class="font-semibold text-gray-700">${category}</h4>
                    <div class="pl-2 space-y-1 mt-1">
                        ${filteredCodes.map(code => `
                            <div class="flex items-center justify-between text-sm p-2 rounded-md hover:bg-gray-100">
                                <span class="flex-grow pr-2">${code.code} - ${code.description}</span>
                                <button data-code="${code.code}" class="add-service-btn bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded hover:bg-green-200 flex-shrink-0">+</button>
                            </div>
                        `).join('')}
                    </div>
                </div>
                `;
            }).join('');

            if (content.trim() === '') {
                cptCodeList.innerHTML = `<p class="text-center text-gray-500 py-4">No matching codes found.</p>`;
            } else {
                cptCodeList.innerHTML = content;
            }
        }


        function renderServiceList() {
            if (selectedServices.length === 0) {
                serviceList.innerHTML = '<tr><td colspan="4" class="text-center text-gray-500 py-8">No services added.</td></tr>';
                return;
            }
            serviceList.innerHTML = selectedServices.map((service, index) => `
            <tr class="hover:bg-gray-50">
                <td class="py-3 px-2 font-mono">${service.cpt_code}</td>
                <td class="py-3 px-2 text-sm">${service.description}</td>
                <td class="py-3 px-2"><input type="number" value="${service.units}" min="1" class="w-16 p-1 border rounded units-input" data-index="${index}"></td>
                <td class="py-3 px-2 text-right"><button data-index="${index}" class="remove-service-btn text-red-500 hover:text-red-700 text-xs font-bold">Remove</button></td>
            </tr>
            `).join('');
        }

        cptSearchInput.addEventListener('input', () => {
            renderCptCodeList(cptSearchInput.value.toLowerCase());
        });

        cptCodeList.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-service-btn')) {
                const code = e.target.dataset.code;
                const existing = selectedServices.find(s => s.cpt_code === code);
                if (existing) {
                    existing.units++;
                } else {
                    const category = Object.keys(allCptCodes).find(cat => allCptCodes[cat].some(c => c.code === code));
                    const codeDetails = allCptCodes[category].find(c => c.code === code);
                    selectedServices.push({ cpt_code: code, description: codeDetails.description, units: 1 });
                }
                renderServiceList();
            }
        });

        serviceList.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-service-btn')) {
                selectedServices.splice(e.target.dataset.index, 1);
                renderServiceList();
            }
        });

        serviceList.addEventListener('change', function(e) {
            if (e.target.classList.contains('units-input')) {
                selectedServices[e.target.dataset.index].units = parseInt(e.target.value);
            }
        });

        saveSuperbillBtn.addEventListener('click', async function() {
            saveSuperbillBtn.textContent = 'Saving...';
            saveSuperbillBtn.disabled = true;
            try {
                const response = await fetch('api/save_superbill_services.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ appointment_id: appointmentId, services: selectedServices })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                alert(result.message);
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                saveSuperbillBtn.textContent = 'Save Superbill';
                saveSuperbillBtn.disabled = false;
            }
        });

        initializeSuperbill();
    });
</script>

<?php require_once 'templates/footer.php'; ?>

