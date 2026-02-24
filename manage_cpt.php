<?php
// Filename: manage_cpt.php
require_once 'templates/header.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    // Duplicating the access denied message for standalone page security
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
                <h1 class="text-2xl font-bold text-gray-800">CPT Code Management</h1>
                <p class="text-sm text-gray-600">Add, edit, or delete CPT codes from the system.</p>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div id="page-message" class="hidden p-3 mb-4 rounded-md"></div>
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-800">System CPT Codes</h2>
                    <button id="addCodeBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md flex items-center transition text-sm">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Add New CPT Code
                    </button>
                </div>
                <div id="cpt-table-container" class="overflow-x-auto">
                    <div class="flex justify-center items-center h-64"><div class="spinner"></div></div>
                </div>
            </div>
        </main>
    </div>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pageMessage = document.getElementById('page-message');
        const tableContainer = document.getElementById('cpt-table-container');
        const modal = document.getElementById('cptModal');
        const modalTitle = document.getElementById('cpt-modal-title');
        const closeBtn = document.getElementById('closeCptModalBtn');
        const cancelBtn = document.getElementById('cancelCptModalBtn');
        const addBtn = document.getElementById('addCodeBtn');
        const form = document.getElementById('cptForm');
        let allCptCodes = [];

        function showPageMessage(message, type) {
            pageMessage.textContent = message;
            pageMessage.className = 'p-3 mb-4 rounded-md';
            if (type === 'error') pageMessage.classList.add('bg-red-100', 'text-red-800');
            else if (type === 'success') pageMessage.classList.add('bg-green-100', 'text-green-800');
            pageMessage.classList.remove('hidden');
            setTimeout(() => pageMessage.classList.add('hidden'), 5000);
        }

        async function fetchCptCodes() {
            try {
                const response = await fetch('api/get_all_cpt_codes_flat.php');
                if (!response.ok) throw new Error('Failed to fetch CPT codes.');
                allCptCodes = await response.json();
                renderTable(allCptCodes);
            } catch (error) {
                tableContainer.innerHTML = `<p class="text-red-500 text-center">${error.message}</p>`;
            }
        }

        function renderTable(codes) {
            tableContainer.innerHTML = '';
            if (codes.length === 0) {
                tableContainer.innerHTML = '<p class="text-center text-gray-500 py-8">No CPT codes found.</p>';
                return;
            }
            const tableRows = codes.map(code => `
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap font-mono">${code.code}</td>
                    <td class="px-6 py-4">${code.description}</td>
                    <td class="px-6 py-4 whitespace-nowrap">${code.category}</td>
                    <td class="px-6 py-4 whitespace-nowrap">$${parseFloat(code.fee).toFixed(2)}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-right space-x-4">
                        <button data-id="${code.id}" class="edit-cpt-btn text-blue-600 hover:text-blue-800 font-semibold">Edit</button>
                        <button data-id="${code.id}" class="delete-cpt-btn text-red-600 hover:text-red-800 font-semibold">Delete</button>
                    </td>
                </tr>
            `).join('');
            tableContainer.innerHTML = `
                <table class="min-w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider">Fee</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        ${tableRows}
                    </tbody>
                </table>`;
        }

        function openModal(code = null) {
            form.reset();
            document.getElementById('cpt_id').value = '';
            if (code) {
                modalTitle.textContent = 'Edit CPT Code';
                document.getElementById('cpt_id').value = code.id;
                document.getElementById('code').value = code.code;
                document.getElementById('description').value = code.description;
                document.getElementById('category').value = code.category;
                document.getElementById('fee').value = code.fee;
            } else {
                modalTitle.textContent = 'Add New CPT Code';
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        addBtn.addEventListener('click', () => openModal());
        closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
        cancelBtn.addEventListener('click', () => modal.classList.add('hidden'));

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            try {
                const response = await fetch('api/manage_cpt_code.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message);
                showPageMessage(result.message, 'success');
                fetchCptCodes();
                modal.classList.add('hidden');
            } catch (error) { showPageMessage(error.message, 'error'); }
        });

        tableContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-cpt-btn')) {
                const codeId = e.target.dataset.id;
                const codeToEdit = allCptCodes.find(c => c.id == codeId);
                openModal(codeToEdit);
            }
            if (e.target.classList.contains('delete-cpt-btn')) {
                if(confirm('Are you sure you want to delete this CPT code?')) {
                    deleteCptCode(e.target.dataset.id);
                }
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

        fetchCptCodes();
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>

<?php require_once 'templates/footer.php'; ?>
