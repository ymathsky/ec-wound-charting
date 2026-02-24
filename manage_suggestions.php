<?php
// Filename: ec/manage_suggestions.php
// NEW PAGE: Admin-only page to manage the 'clinical_suggestions' library.

session_start();
require_once 'templates/header.php';

// --- Admin-only Page ---
$user_role = isset($_SESSION['ec_role']) ? strtolower(trim($_SESSION['ec_role'])) : '';
if ($user_role != 'admin') {
    echo "<div class='p-8 text-center text-red-500'>Access denied. You must be an admin to view this page.</div>";
    require_once 'templates/footer.php';
    exit();
}
?>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <div class="flex items-center">
                    <button id="mobile-menu-btn" onclick="openSidebar()" class="md:hidden text-gray-800 focus:outline-none mr-4">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-gray-800">Clinical Suggestions Management</h1>
                </div>
            </header>

            <!-- Page-level Message Bar -->
            <div id="page-message" class="hidden p-4 m-4 rounded-md text-sm"></div>
            <?php require_once 'templates/data_management.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">

                    <div class="flex justify-between items-center mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-gray-700">Manage Suggestions Library</h2>
                        <button id="addSuggestionBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center">
                            <i data-lucide="plus" class="w-5 h-5 mr-1"></i>
                            Add Suggestion
                        </button>
                    </div>

                    <!-- Table Container -->
                    <div id="suggestions-table-container" class="overflow-x-auto">
                        <!-- Loading state -->
                        <div class="text-center p-8 text-gray-500">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin inline-block mb-2"></i>
                            <p>Loading suggestions...</p>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- =================================================================
         MODALS
    ================================================================== -->

    <!-- Add/Edit Suggestion Modal -->
    <div id="suggestionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add Suggestion</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('suggestionModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4 max-h-[70vh] overflow-y-auto pr-2">
                <form id="suggestionForm">
                    <input type="hidden" id="suggestion_id" name="suggestion_id">

                    <div class="space-y-4">
                        <!-- Category -->
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <input type="text" id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Orders, Referrals" required>
                        </div>

                        <!-- Suggestion Text -->
                        <div>
                            <label for="suggestion_text" class="block text-sm font-medium text-gray-700">Suggestion Text</label>
                            <textarea id="suggestion_text" name="suggestion_text" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="The clinical suggestion text..." required></textarea>
                        </div>

                        <!-- Display Order -->
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" value="0" required>
                            <p class="text-xs text-gray-500 mt-1">Lowest numbers appear first within a category.</p>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('suggestionModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Save Suggestion
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 class="text-xl font-bold text-red-700">Confirm Deletion</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('deleteModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-700">
                    Are you sure you want to permanently delete this suggestion?
                    <br><br>
                    <strong class="text-red-600">This action cannot be undone.</strong>
                </p>
                <form id="deleteForm">
                    <input type="hidden" id="delete_suggestion_id" name="delete_suggestion_id">
                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('deleteModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Yes, Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const suggestionModal = document.getElementById('suggestionModal');
            const modalTitle = document.getElementById('modalTitle');
            const suggestionForm = document.getElementById('suggestionForm');
            const suggestionIdInput = document.getElementById('suggestion_id');
            const addSuggestionBtn = document.getElementById('addSuggestionBtn');
            const messageBar = document.getElementById('page-message');
            const suggestionsTableContainer = document.getElementById('suggestions-table-container');

            const deleteModal = document.getElementById('deleteModal');
            const deleteForm = document.getElementById('deleteForm');
            const deleteSuggestionIdInput = document.getElementById('delete_suggestion_id');

            let allSuggestions = [];

            function showMessage(message, type = 'info') {
                messageBar.textContent = message;
                messageBar.className = 'p-4 m-4 rounded-md text-sm'; // Reset
                if (type === 'success') {
                    messageBar.classList.add('bg-green-100', 'text-green-800');
                } else if (type === 'error') {
                    messageBar.classList.add('bg-red-100', 'text-red-800');
                } else {
                    messageBar.classList.add('bg-blue-100', 'text-blue-800');
                }
                messageBar.classList.remove('hidden');
                setTimeout(() => {
                    messageBar.classList.add('hidden');
                }, 3000);
            }

            window.openModal = (modal) => modal.classList.remove('hidden');
            window.closeModal = (modal) => modal.classList.add('hidden');

            async function fetchSuggestions() {
                try {
                    const cacheBuster = `&_=${new Date().getTime()}`;
                    const response = await fetch(`api/manage_suggestion.php?action=get_all${cacheBuster}`);
                    if (!response.ok) throw new Error('Failed to fetch data.');

                    const data = await response.json();
                    if (data.success) {
                        allSuggestions = data.suggestions;
                        renderTable(data.suggestions);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                    suggestionsTableContainer.innerHTML = `<div class="text-center p-8 text-red-500">Failed to load suggestions: ${error.message}</div>`;
                }
            }

            function renderTable(suggestions) {
                let tableHtml = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Suggestion Text</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="suggestionsTableBody" class="bg-white divide-y divide-gray-200">
            `;

                if (suggestions.length === 0) {
                    tableHtml += '<tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No suggestions found.</td></tr>';
                }

                suggestions.forEach(s => {
                    const status = s.is_active == 1
                        ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
                        : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';

                    const toggleBtnText = s.is_active == 1 ? 'Deactivate' : 'Activate';
                    const toggleBtnClass = s.is_active == 1 ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200';

                    tableHtml += `
                    <tr>
                        <td class="px-4 py-4 text-sm font-medium text-gray-900" style="white-space: normal;">${s.suggestion_text}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${s.category}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${s.display_order}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm">${status}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-1">
                            <button class="edit-btn p-2 rounded-md bg-blue-100 text-blue-800 hover:bg-blue-200" data-id="${s.suggestion_id}" title="Edit"><i data-lucide="edit" class="w-4 h-4"></i></button>
                            <button class="toggle-btn p-2 rounded-md ${toggleBtnClass}" data-id="${s.suggestion_id}" data-status="${s.is_active}" title="${toggleBtnText}">${toggleBtnText === 'Deactivate' ? '<i data-lucide="toggle-right" class="w-4 h-4"></i>' : '<i data-lucide="toggle-left" class="w-4 h-4"></i>'}</button>
                            <button class="delete-btn p-2 rounded-md bg-red-100 text-red-800 hover:bg-red-200" data-id="${s.suggestion_id}" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </td>
                    </tr>
                `;
                });

                tableHtml += '</tbody></table>';
                suggestionsTableContainer.innerHTML = tableHtml;
                lucide.createIcons();
            }

            function resetForm() {
                suggestionForm.reset();
                suggestionIdInput.value = '';
                modalTitle.textContent = 'Add Suggestion';
            }

            // Open "Add Suggestion" modal
            addSuggestionBtn.addEventListener('click', () => {
                resetForm();
                openModal(suggestionModal);
            });

            // Handle Table Actions
            suggestionsTableContainer.addEventListener('click', async (e) => {
                const button = e.target.closest('button');
                if (!button) return;

                const id = button.dataset.id;

                // --- EDIT ---
                if (button.classList.contains('edit-btn')) {
                    const suggestion = allSuggestions.find(s => s.suggestion_id == id);
                    if (suggestion) {
                        resetForm();
                        modalTitle.textContent = 'Edit Suggestion';
                        suggestionIdInput.value = suggestion.suggestion_id;
                        document.getElementById('category').value = suggestion.category;
                        document.getElementById('suggestion_text').value = suggestion.suggestion_text;
                        document.getElementById('display_order').value = suggestion.display_order;
                        openModal(suggestionModal);
                    }
                }

                // --- TOGGLE ACTIVE/INACTIVE ---
                if (button.classList.contains('toggle-btn')) {
                    const currentStatus = button.dataset.status;
                    const newStatus = currentStatus == 1 ? 0 : 1;

                    try {
                        const response = await fetch('api/manage_suggestion.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'toggle_active',
                                suggestion_id: id,
                                new_status: newStatus
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            showMessage(data.message, 'success');
                            fetchSuggestions(); // Refresh table
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (error) {
                        showMessage(error.message, 'error');
                    }
                }

                // --- DELETE ---
                if (button.classList.contains('delete-btn')) {
                    deleteSuggestionIdInput.value = id;
                    openModal(deleteModal);
                }
            });

            // Handle "Add/Edit" Form Submission
            suggestionForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(suggestionForm);

                const data = {
                    action: suggestionIdInput.value ? 'update' : 'create',
                    suggestion_id: suggestionIdInput.value || undefined,
                    category: formData.get('category'),
                    suggestion_text: formData.get('suggestion_text'),
                    display_order: formData.get('display_order')
                };

                try {
                    const response = await fetch('api/manage_suggestion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMessage(result.message, 'success');
                        closeModal(suggestionModal);
                        fetchSuggestions(); // Refresh table
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                }
            });

            // Handle "Delete" Form Submission
            deleteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const suggestion_id = deleteSuggestionIdInput.value;

                try {
                    const response = await fetch('api/manage_suggestion.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete',
                            suggestion_id: suggestion_id
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage(data.message, 'success');
                        closeModal(deleteModal);
                        fetchSuggestions(); // Refresh table
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                }
            });

            // Initial load
            fetchSuggestions();
            lucide.createIcons();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>