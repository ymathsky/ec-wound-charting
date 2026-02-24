<?php
// Filename: ec/manage_soap_checklist.php
// NEW PAGE: Admin-only page to manage the 'soap_checklist_items' library.

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
                    <h1 class="text-2xl font-bold text-gray-800">SOAP Note Checklist Management</h1>
                </div>
            </header>

            <!-- Page-level Message Bar -->
            <div id="page-message" class="hidden p-4 m-4 rounded-md text-sm"></div>
            <?php require_once 'templates/data_management.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">

                    <div class="flex justify-between items-center mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-gray-700">Manage Checklist Items</h2>
                        <button id="addItemBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center">
                            <i data-lucide="plus" class="w-5 h-5 mr-1"></i>
                            Add Checklist Item
                        </button>
                    </div>

                    <!-- Table Container -->
                    <div id="items-table-container" class="overflow-x-auto">
                        <!-- Loading state -->
                        <div class="text-center p-8 text-gray-500">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin inline-block mb-2"></i>
                            <p>Loading checklist items...</p>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- =================================================================
         MODALS
    ================================================================== -->

    <!-- Add/Edit Item Modal -->
    <div id="itemModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add Checklist Item</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('itemModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4 max-h-[70vh] overflow-y-auto pr-2">
                <form id="itemForm">
                    <input type="hidden" id="item_id" name="item_id">

                    <div class="space-y-4">
                        <!-- SOAP Section -->
                        <div>
                            <label for="soap_section" class="block text-sm font-medium text-gray-700">SOAP Section</label>
                            <select id="soap_section" name="soap_section" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" required>
                                <option value="">-- Select Section --</option>
                                <option value="subjective">Subjective (S)</option>
                                <option value="objective">Objective (O)</option>
                                <option value="assessment">Assessment (A)</option>
                                <option value="plan">Plan (P)</option>
                                <option value="lab_orders">Lab Orders</option>
                                <option value="imaging_orders">Imaging Orders</option>
                                <option value="skilled_nurse_orders">Skilled Nurse Orders</option>
                            </select>
                        </div>

                        <!-- Category -->
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <input type="text" id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., General, Pain, Wound Appearance" required>
                        </div>

                        <!-- Title (New) -->
                        <div>
                            <label for="title" class="block text-sm font-medium text-gray-700">Title (Optional Grouping)</label>
                            <input type="text" id="title" name="title" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Dressing Type">
                        </div>

                        <!-- Item Text (Single - for Edit) -->
                        <div id="single-item-container">
                            <label for="item_text" class="block text-sm font-medium text-gray-700">Option / Item Text</label>
                            <input type="text" id="item_text" name="item_text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="The text snippet to be inserted...">
                        </div>

                        <!-- Options List (Multiple - for Create) -->
                        <div id="multi-options-container" class="hidden">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Options / Checklist Items</label>
                            <div id="options-list" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 1">
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 2">
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 3">
                                </div>
                            </div>
                            <button type="button" id="addOptionBtn" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium flex items-center">
                                <i data-lucide="plus-circle" class="w-4 h-4 mr-1"></i> Add Another Option
                            </button>
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
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('itemModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Save Item
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
                    Are you sure you want to permanently delete this item?
                    <br><br>
                    <strong class="text-red-600">This action cannot be undone.</strong>
                </p>
                <form id="deleteForm">
                    <input type="hidden" id="delete_item_id" name="delete_item_id">
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
            const itemModal = document.getElementById('itemModal');
            const modalTitle = document.getElementById('modalTitle');
            const itemForm = document.getElementById('itemForm');
            const itemIdInput = document.getElementById('item_id');
            const addItemBtn = document.getElementById('addItemBtn');
            const messageBar = document.getElementById('page-message');
            const itemsTableContainer = document.getElementById('items-table-container');
            const singleItemContainer = document.getElementById('single-item-container');
            const multiOptionsContainer = document.getElementById('multi-options-container');
            const optionsList = document.getElementById('options-list');
            const addOptionBtn = document.getElementById('addOptionBtn');

            const deleteModal = document.getElementById('deleteModal');
            const deleteForm = document.getElementById('deleteForm');
            const deleteItemIdInput = document.getElementById('delete_item_id');

            let allItems = [];

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

            async function fetchItems() {
                try {
                    const cacheBuster = `&_=${new Date().getTime()}`;
                    const response = await fetch(`api/manage_soap_checklist.php?action=get_all${cacheBuster}`);
                    if (!response.ok) throw new Error('Failed to fetch data.');

                    const data = await response.json();
                    if (data.success) {
                        allItems = data.items;
                        renderTable(data.items);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                    itemsTableContainer.innerHTML = `<div class="text-center p-8 text-red-500">Failed to load items: ${error.message}</div>`;
                }
            }

            function renderTable(items) {
                let tableHtml = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Text</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody" class="bg-white divide-y divide-gray-200">
            `;

                if (items.length === 0) {
                    tableHtml += '<tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No checklist items found.</td></tr>';
                }

                items.forEach(item => {
                    const status = item.is_active == 1
                        ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
                        : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';

                    const toggleBtnText = item.is_active == 1 ? 'Deactivate' : 'Activate';
                    const toggleBtnClass = item.is_active == 1 ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200';

                    const sectionBadge = {
                        'subjective': 'bg-blue-100 text-blue-800',
                        'objective': 'bg-purple-100 text-purple-800',
                        'assessment': 'bg-yellow-100 text-yellow-800',
                        'plan': 'bg-green-100 text-green-800',
                        'lab_orders': 'bg-teal-100 text-teal-800',
                        'imaging_orders': 'bg-teal-100 text-teal-800',
                        'skilled_nurse_orders': 'bg-teal-100 text-teal-800'
                    };

                    tableHtml += `
                    <tr>
                        <td class="px-4 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${sectionBadge[item.soap_section] || 'bg-gray-100 text-gray-800'}">
                                ${item.soap_section.toUpperCase()}
                            </span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${item.category}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 font-medium">${item.title || '-'}</td>
                        <td class="px-4 py-4 text-sm font-medium text-gray-900" style="white-space: normal;">${item.item_text}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${item.display_order}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm">${status}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-1">
                            <button class="edit-btn p-2 rounded-md bg-blue-100 text-blue-800 hover:bg-blue-200" data-id="${item.item_id}" title="Edit"><i data-lucide="edit" class="w-4 h-4"></i></button>
                            <button class="toggle-btn p-2 rounded-md ${toggleBtnClass}" data-id="${item.item_id}" data-status="${item.is_active}" title="${toggleBtnText}">${toggleBtnText === 'Deactivate' ? '<i data-lucide="toggle-right" class="w-4 h-4"></i>' : '<i data-lucide="toggle-left" class="w-4 h-4"></i>'}</button>
                            <button class="delete-btn p-2 rounded-md bg-red-100 text-red-800 hover:bg-red-200" data-id="${item.item_id}" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </td>
                    </tr>
                `;
                });

                tableHtml += '</tbody></table>';
                itemsTableContainer.innerHTML = tableHtml;
                lucide.createIcons();
            }

            function resetForm() {
                itemForm.reset();
                itemIdInput.value = '';
                modalTitle.textContent = 'Add Checklist Item';
                
                // Show Multi-Options, Hide Single Item
                if(singleItemContainer) singleItemContainer.classList.add('hidden');
                if(multiOptionsContainer) multiOptionsContainer.classList.remove('hidden');
                const itemTextEl = document.getElementById('item_text');
                if(itemTextEl) itemTextEl.removeAttribute('required');
                
                // Reset options list
                if(optionsList) {
                    optionsList.innerHTML = `
                        <div class="flex items-center gap-2"><input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 1"></div>
                        <div class="flex items-center gap-2"><input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 2"></div>
                        <div class="flex items-center gap-2"><input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option 3"></div>
                    `;
                }
            }

            if(addOptionBtn) {
                addOptionBtn.addEventListener('click', () => {
                    const div = document.createElement('div');
                    div.className = 'flex items-center gap-2';
                    div.innerHTML = `
                        <input type="text" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="New Option">
                        <button type="button" class="text-red-500 hover:text-red-700" onclick="this.parentElement.remove()"><i data-lucide="trash" class="w-4 h-4"></i></button>
                    `;
                    optionsList.appendChild(div);
                    lucide.createIcons();
                });
            }

            // Open "Add Item" modal
            addItemBtn.addEventListener('click', () => {
                resetForm();
                openModal(itemModal);
            });

            // Handle Table Actions
            itemsTableContainer.addEventListener('click', async (e) => {
                const button = e.target.closest('button');
                if (!button) return;

                const id = button.dataset.id;

                // --- EDIT ---
                if (button.classList.contains('edit-btn')) {
                    const item = allItems.find(i => i.item_id == id);
                    if (item) {
                        resetForm();
                        modalTitle.textContent = 'Edit Checklist Item';
                        itemIdInput.value = item.item_id;
                        document.getElementById('category').value = item.category;
                        document.getElementById('soap_section').value = item.soap_section;
                        document.getElementById('title').value = item.title || '';
                        document.getElementById('display_order').value = item.display_order;
                        
                        // Show Single Item, Hide Multi-Options
                        if(singleItemContainer) singleItemContainer.classList.remove('hidden');
                        if(multiOptionsContainer) multiOptionsContainer.classList.add('hidden');
                        
                        const itemTextEl = document.getElementById('item_text');
                        if(itemTextEl) {
                            itemTextEl.value = item.item_text;
                            itemTextEl.setAttribute('required', 'required');
                        }

                        openModal(itemModal);
                    }
                }

                // --- TOGGLE ACTIVE/INACTIVE ---
                if (button.classList.contains('toggle-btn')) {
                    const currentStatus = button.dataset.status;
                    const newStatus = currentStatus == 1 ? 0 : 1;

                    try {
                        const response = await fetch('api/manage_soap_checklist.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'toggle_active',
                                item_id: id,
                                new_status: newStatus
                            })
                        });
                        const data = await response.json();
                        if (data.success) {
                            showMessage(data.message, 'success');
                            fetchItems(); // Refresh table
                        } else {
                            throw new Error(data.message);
                        }
                    } catch (error) {
                        showMessage(error.message, 'error');
                    }
                }

                // --- DELETE ---
                if (button.classList.contains('delete-btn')) {
                    deleteItemIdInput.value = id;
                    openModal(deleteModal);
                }
            });

            // Handle "Add/Edit" Form Submission
            itemForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(itemForm);

                // Collect options manually
                const options = [];
                document.querySelectorAll('input[name="options[]"]').forEach(input => {
                    if(input.value.trim()) options.push(input.value.trim());
                });

                const data = {
                    action: itemIdInput.value ? 'update' : 'create',
                    item_id: itemIdInput.value || undefined,
                    soap_section: formData.get('soap_section'),
                    category: formData.get('category'),
                    title: formData.get('title'),
                    item_text: formData.get('item_text'),
                    display_order: formData.get('display_order'),
                    options: options
                };

                try {
                    const response = await fetch('api/manage_soap_checklist.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMessage(result.message, 'success');
                        closeModal(itemModal);
                        fetchItems(); // Refresh table
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
                const item_id = deleteItemIdInput.value;

                try {
                    const response = await fetch('api/manage_soap_checklist.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete',
                            item_id: item_id
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage(data.message, 'success');
                        closeModal(deleteModal);
                        fetchItems(); // Refresh table
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                }
            });

            // Initial load
            fetchItems();
            lucide.createIcons();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>