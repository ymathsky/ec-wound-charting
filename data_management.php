<?php
// Filename: data_management.php
require_once 'templates/header.php';
require_once 'db_connect.php';
// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    echo "<div class='flex h-screen bg-gray-100'>";
    require_once 'templates/sidebar.php';
    echo "<div class='flex-1 flex flex-col overflow-hidden'>";
    echo "<header class='w-full bg-white p-4 flex justify-between items-center shadow-md'><h1>Access Denied</h1></header>";
    echo "<main class='flex-1 overflow-y-auto bg-gray-100 p-6'><div class='max-w-4xl mx-auto bg-white p-6 rounded-lg shadow'>";
    echo "<h2 class='text-2xl font-bold text-red-600'>Access Denied</h2>";
    echo "<p class='mt-4 text-gray-700'>You do not have permission to access this page.</p>";
    echo "</div></main></div></div>";
    require_once 'templates/footer.php';
    exit();
}
?>

    <div class="flex h-screen bg-gray-100">
<?php require_once 'templates/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <!-- START: UPDATED HEADER STYLE -->
        <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">

                    <i data-lucide="database" class="w-7 h-7 mr-3 text-indigo-600"></i>
                    Data Management
                </h1>
                <p class="text-sm text-gray-500 mt-1">Manage CPT codes, medication libraries, and core system data.</p>

            </div>
            <!-- Empty conditional section removed as requested template didn't include it -->
        </header>
        <!-- END: UPDATED HEADER STYLE -->
        <?php require_once 'templates/data_management.php'; ?>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6 space-y-6">
            <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>

            <!-- TAB CONTROLS AND CONTENT -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200">
                <div class="flex border-b">
                    <button data-tab="cpt" class="tab-button active-tab flex-1 py-3 px-4 text-lg font-semibold text-center transition">
                        <i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> CPT Codes
                    </button>
                    <button data-tab="medication" class="tab-button flex-1 py-3 px-4 text-lg font-semibold text-center transition border-l">
                        <i data-lucide="pill" class="w-5 h-5 inline mr-2"></i> Medication Library
                    </button>
                </div>

                <!-- TAB CONTENT CONTAINER -->
                <div class="p-6">

                    <!-- CPT Code Management Section -->
                    <div id="tab-cpt" class="tab-content active-content space-y-6">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-semibold text-gray-800">CPT Code Index</h2>
                            <button id="addCodeBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition text-sm shadow-md">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add New CPT Code
                            </button>
                        </div>

                        <!-- NEW: Search and Filter Row -->
                        <div class="flex space-x-4">
                            <input type="text" id="cptSearch" placeholder="Search CPT codes by code, description, or category..." class="form-input w-full px-3 py-2 border rounded-lg shadow-sm">
                            <select id="cptStatusFilter" class="form-input px-3 py-2 border rounded-lg shadow-sm w-40">
                                <option value="all">All Statuses</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div id="cpt-table-container" class="overflow-x-auto border rounded-lg">
                            <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                        </div>
                    </div>

                    <!-- Medication Library Management Section -->
                    <div id="tab-medication" class="tab-content hidden space-y-6">
                        <div class="flex justify-between items-center">
                            <h2 class="text-xl font-semibold text-gray-800">Medication Library Index</h2>
                            <button id="addLibraryMedicationBtn" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition text-sm shadow-md">
                                <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add to Library
                            </button>
                        </div>
                        <!-- NEW: Search Input for Medication -->
                        <input type="text" id="medicationSearch" placeholder="Search medications by name, dosage, or frequency..." class="form-input w-full px-3 py-2 border rounded-lg shadow-sm">

                        <div id="medication-library-table-container" class="overflow-x-auto border rounded-lg">
                            <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit CPT Code Modal -->
    <div id="cptModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl p-6 max-w-lg w-full">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h3 id="cpt-modal-title" class="text-xl font-semibold text-gray-800">Add New CPT Code</h3>
                <button id="closeCptModalBtn" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            <form id="cptForm" class="space-y-4">
                <input type="hidden" name="id" id="cpt_id">
                <div>
                    <label for="code" class="form-label">CPT Code</label>
                    <input type="text" name="code" id="code" required class="form-input">
                </div>
                <div>
                    <label for="description" class="form-label">Description</label>
                    <textarea name="description" id="description" required rows="3" class="form-input"></textarea>
                </div>
                <div>
                    <label for="category" class="form-label">Category</label>
                    <input type="text" name="category" id="category" required class="form-input" placeholder="e.g., Evaluation & Management">
                </div>
                <div>
                    <label for="fee" class="form-label">Fee</label>
                    <input type="number" step="0.01" name="fee" id="fee" required class="form-input">
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelCptModalBtn" class="bg-gray-300 text-gray-800 px-4 py-2 rounded-md hover:bg-gray-400 font-semibold">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-semibold">Save Code</button>
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

    <!-- NEW: Custom Confirmation Modal (Replaces JS confirm) -->
    <div id="customConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
        <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-sm m-4 transform transition-all">
            <h4 id="confirm-modal-title" class="text-xl font-bold text-gray-900 mb-3">Confirm Deletion</h4>
            <p id="confirm-modal-body" class="text-gray-700 mb-6"></p>
            <div class="flex justify-end space-x-3">
                <button type="button" id="modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                <button type="button" id="modal-confirm" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Delete</button>
            </div>
        </div>
    </div>


    <script>
        // --- Debounce Utility ---
        const debounce = (func, delay) => {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), delay);
            };
        };

        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            const pageMessage = document.getElementById('page-message');

            // --- CPT Code ---
            const cptTableContainer = document.getElementById('cpt-table-container');
            const cptModal = document.getElementById('cptModal');
            const addCodeBtn = document.getElementById('addCodeBtn');
            const cptForm = document.getElementById('cptForm');
            const cptSearchInput = document.getElementById('cptSearch');
            // NEW: Status Filter
            const cptStatusFilter = document.getElementById('cptStatusFilter');

            let allCptCodes = [];
            document.getElementById('closeCptModalBtn').addEventListener('click', () => cptModal.classList.add('hidden'));
            document.getElementById('cancelCptModalBtn').addEventListener('click', () => cptModal.classList.add('hidden'));

            // --- Medication Library ---
            const medicationLibraryTableContainer = document.getElementById('medication-library-table-container');
            const medicationLibraryModal = document.getElementById('medicationLibraryModal');
            const addLibraryMedicationBtn = document.getElementById('addLibraryMedicationBtn');
            const medicationLibraryForm = document.getElementById('medicationLibraryForm');
            const medicationSearchInput = document.getElementById('medicationSearch');
            let medicationLibrary = [];
            document.getElementById('closeMedicationLibraryModalBtn').addEventListener('click', () => medicationLibraryModal.classList.add('hidden'));
            document.getElementById('cancelMedicationLibraryModalBtn').addEventListener('click', () => medicationLibraryModal.classList.add('hidden'));

            // --- Confirm Modal Logic (Replaces window.confirm) ---
            const confirmModal = document.getElementById('customConfirmModal');
            const confirmTitle = document.getElementById('confirm-modal-title');
            const confirmBody = document.getElementById('confirm-modal-body');
            const modalConfirmBtn = document.getElementById('modal-confirm');
            const modalCancelBtn = document.getElementById('modal-cancel');
            let deleteCallback = null;

            function showCustomConfirm(title, message, callback) {
                confirmTitle.textContent = title;
                confirmBody.textContent = message;
                modalConfirmBtn.textContent = 'Delete';
                modalConfirmBtn.classList.remove('bg-red-600');
                modalConfirmBtn.classList.add('bg-red-600');
                deleteCallback = callback;
                confirmModal.classList.remove('hidden');
                confirmModal.classList.add('flex');
            }

            modalCancelBtn.addEventListener('click', () => {
                confirmModal.classList.remove('flex');
                confirmModal.classList.add('hidden');
            });

            modalConfirmBtn.addEventListener('click', () => {
                confirmModal.classList.remove('flex');
                confirmModal.classList.add('hidden');
                if (deleteCallback) {
                    deleteCallback();
                }
            });


            // --- Tab Logic ---
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            function setActiveTab(tabId) {
                tabButtons.forEach(button => {
                    button.classList.remove('active-tab');
                    button.style.backgroundColor = 'white';
                    button.style.color = '#4b5563';
                });
                tabContents.forEach(content => {
                    content.classList.add('hidden');
                });

                const activeButton = document.querySelector(`.tab-button[data-tab="${tabId}"]`);
                const activeContent = document.getElementById(`tab-${tabId}`);

                if (activeButton && activeContent) {
                    activeButton.classList.add('active-tab');
                    activeButton.style.backgroundColor = '#6366f1'; /* indigo-500 */
                    activeButton.style.color = 'white';
                    activeContent.classList.remove('hidden');
                }
            }

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    setActiveTab(button.dataset.tab);
                });
            });

            // Initial Tab setup
            setActiveTab('cpt');
            // --- End Tab Logic ---


            function showPageMessage(message, type) {
                pageMessage.textContent = message;
                pageMessage.className = 'p-3 mb-4 rounded-md';
                if (type === 'error') pageMessage.classList.add('bg-red-100', 'text-red-800');
                else pageMessage.classList.add('bg-green-100', 'text-green-800');
                pageMessage.classList.remove('hidden');
                setTimeout(() => pageMessage.classList.add('hidden'), 5000);
            }

            // --- CPT CODE LOGIC ---
            async function fetchCptCodes() {
                try {
                    const response = await fetch('api/get_all_cpt_codes_flat.php');
                    if (!response.ok) throw new Error('Failed to fetch CPT codes.');

                    // FIX 1: Await JSON parsing before mapping
                    const data = await response.json();

                    // FIX 2: Ensure data is an array before mapping
                    if (!Array.isArray(data)) {
                        throw new Error("CPT API returned non-array data.");
                    }

                    allCptCodes = data.map(code => ({
                        ...code,
                        // Ensure status exists and is lowercase for robust filtering
                        status: code.status ? code.status.toLowerCase() : 'active'
                    }));
                    filterAndRenderCptTable();
                } catch (error) {
                    cptTableContainer.innerHTML = `<p class="text-red-500 text-center">${error.message}</p>`;
                }
            }

            // Function to get status badge for CPT codes (assumed logic)
            function getCptStatusBadge(status) {
                if (status === 'active') {
                    return '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800">ACTIVE</span>';
                }
                return '<span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-200 text-gray-600">INACTIVE</span>';
            }


            function filterAndRenderCptTable() {
                const searchTerm = cptSearchInput.value.toLowerCase();
                const statusTerm = cptStatusFilter.value;

                const filteredCodes = allCptCodes.filter(code => {
                    const matchesSearch = code.code.toLowerCase().includes(searchTerm) ||
                        code.description.toLowerCase().includes(searchTerm) ||
                        code.category.toLowerCase().includes(searchTerm);

                    const matchesStatus = statusTerm === 'all' || code.status === statusTerm;

                    return matchesSearch && matchesStatus;
                });
                renderCptTable(filteredCodes);
            }

            // Debounced search/filter trigger
            const debouncedCptFilter = debounce(filterAndRenderCptTable, 300);
            cptSearchInput.addEventListener('input', debouncedCptFilter);
            cptStatusFilter.addEventListener('change', filterAndRenderCptTable);


            function renderCptTable(codes) {
                cptTableContainer.innerHTML = '';
                if (codes.length === 0) {
                    cptTableContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No CPT codes found matching criteria.</p>';
                    return;
                }
                const tableRows = codes.map(code => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap font-mono">${code.code}</td>
                    <td class="px-6 py-4">${code.description}</td>
                    <td class="px-6 py-4 whitespace-nowrap">${code.category}</td>
                    <td class="px-6 py-4 whitespace-nowrap">$${parseFloat(code.fee).toFixed(2)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-center">${getCptStatusBadge(code.status || 'inactive')}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right space-x-4">
                        <button data-id="${code.id}" class="edit-cpt-btn text-blue-600 hover:text-blue-800 font-semibold p-1 rounded-full hover:bg-blue-100 transition" title="Edit Code">
                            <i data-lucide="edit-3" class="w-5 h-5"></i>
                        </button>
                        <button data-id="${code.id}" class="delete-cpt-btn text-red-600 hover:text-red-800 font-semibold p-1 rounded-full hover:bg-red-100 transition" title="Delete Code">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
                cptTableContainer.innerHTML = `
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>`;
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }


            function openCptModal(code = null) {
                cptForm.reset();
                document.getElementById('cpt_id').value = '';
                if (code) {
                    document.getElementById('cpt-modal-title').textContent = 'Edit CPT Code';
                    document.getElementById('cpt_id').value = code.id;
                    document.getElementById('code').value = code.code;
                    document.getElementById('description').value = code.description;
                    document.getElementById('category').value = code.category;
                    document.getElementById('fee').value = code.fee;
                } else {
                    document.getElementById('cpt-modal-title').textContent = 'Add New CPT Code';
                }
                cptModal.classList.remove('hidden');
                cptModal.classList.add('flex');
            }

            addCodeBtn.addEventListener('click', () => openCptModal());

            cptForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const data = Object.fromEntries(new FormData(cptForm).entries());
                try {
                    const response = await fetch('api/manage_cpt_code.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    showPageMessage(result.message, 'success');
                    fetchCptCodes();
                    cptModal.classList.add('hidden');
                } catch (error) { showPageMessage(error.message, 'error'); }
            });

            cptTableContainer.addEventListener('click', function(e) {
                const target = e.target.closest('button');
                if (!target) return;

                if (target.classList.contains('edit-cpt-btn')) {
                    const codeId = target.dataset.id;
                    const codeToEdit = allCptCodes.find(c => c.id == codeId);
                    openCptModal(codeToEdit);
                }
                if (target.classList.contains('delete-cpt-btn')) {
                    const codeId = target.dataset.id;
                    const code = allCptCodes.find(c => c.id == codeId);
                    showCustomConfirm(
                        'Confirm CPT Deletion',
                        `Permanently delete CPT code: ${code.code} - ${code.description}? This cannot be undone.`,
                        () => deleteCptCode(codeId)
                    );
                }
            });

            async function deleteCptCode(id) {
                try {
                    const response = await fetch('api/manage_cpt_code.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message);
                    showPageMessage(result.message, 'success');
                    fetchCptCodes();
                } catch (error) { showPageMessage(error.message, 'error'); }
            }

            // --- MEDICATION LIBRARY LOGIC ---
            async function fetchMedicationLibrary() {
                try {
                    const response = await fetch('api/get_medication_library.php');
                    if (!response.ok) throw new Error('Failed to fetch medication library.');

                    // FIX 3: Await JSON parsing before assigning
                    const data = await response.json();
                    if (!Array.isArray(data)) {
                        throw new Error("Medication API returned non-array data.");
                    }

                    medicationLibrary = data;
                    filterAndRenderMedicationLibraryTable();
                } catch (error) {
                    medicationLibraryTableContainer.innerHTML = `<p class="text-red-500 text-center">${error.message}</p>`;
                }
            }

            function filterAndRenderMedicationLibraryTable() {
                const searchTerm = medicationSearchInput.value.toLowerCase();
                const filteredMeds = medicationLibrary.filter(med =>
                    (med.name && med.name.toLowerCase().includes(searchTerm)) ||
                    (med.description && med.description.toLowerCase().includes(searchTerm)) ||
                    (med.default_dosage && med.default_dosage.toLowerCase().includes(searchTerm)) ||
                    (med.default_frequency && med.default_frequency.toLowerCase().includes(searchTerm))
                );
                renderMedicationLibraryTable(filteredMeds);
            }

            // Debounced search/filter trigger for medication
            const debouncedMedicationFilter = debounce(filterAndRenderMedicationLibraryTable, 300);
            medicationSearchInput.addEventListener('input', debouncedMedicationFilter);


            function renderMedicationLibraryTable(meds) {
                medicationLibraryTableContainer.innerHTML = '';
                if (meds.length === 0) {
                    medicationLibraryTableContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No medications found matching criteria.</p>';
                    return;
                }
                const tableRows = meds.map(med => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 font-semibold">${med.name}</td>
                    <td class="px-6 py-4">${med.description || ''}</td>
                    <td class="px-6 py-4">${med.default_dosage || ''}</td>
                    <td class="px-6 py-4">${med.default_frequency || ''}</td>
                    <td class="px-6 py-4 text-right space-x-4">
                        <button data-id="${med.id}" class="edit-library-med-btn text-blue-600 hover:text-blue-800 font-semibold p-1 rounded-full hover:bg-blue-100 transition" title="Edit Medication">
                            <i data-lucide="edit-3" class="w-5 h-5"></i>
                        </button>
                        <button data-id="${med.id}" class="delete-library-med-btn text-red-600 hover:text-red-800 font-semibold p-1 rounded-full hover:bg-red-100 transition" title="Delete Medication">
                            <i data-lucide="trash-2" class="w-5 h-5"></i>
                        </button>
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
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
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
                const target = e.target.closest('button');
                if (!target) return;

                if (target.classList.contains('edit-library-med-btn')) {
                    const medId = target.dataset.id;
                    const medToEdit = medicationLibrary.find(m => m.id == medId);
                    openMedicationLibraryModal(medToEdit);
                }
                if (target.classList.contains('delete-library-med-btn')) {
                    const medId = target.dataset.id;
                    const med = medicationLibrary.find(m => m.id == medId);
                    showCustomConfirm(
                        'Confirm Medication Deletion',
                        `Permanently delete medication from library: ${med.name}? This cannot be undone.`,
                        () => deleteLibraryMedication(medId)
                    );
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

            // --- Initial Load ---
            fetchCptCodes();
            fetchMedicationLibrary();
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>

<?php require_once 'templates/footer.php'; ?>