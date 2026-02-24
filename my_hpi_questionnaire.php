<?php
// Filename: ec/my_hpi_questionnaire.php
// COMPLETE: This file allows clinicians to manage *their own* personalized HPI questions.
// 1. Checks for a valid logged-in user.
// 2. A non-blocking message bar (replaces alert())
// 3. A dynamic table to show only this user's questions
// 4. A modal with the user-friendly "Add Option" builder
// 5. The "Allow linking to a specific wound?" checkbox
// 6. JavaScript to handle CRUD, activation, and deletion.
// 7. FIX: Removed the blocking `confirm()` pop-up to allow the table to refresh.

session_start();
require_once 'templates/header.php';
require_once 'db_connect.php';

// Any logged-in user can access this, but the API will enforce they only see/edit their own questions.
if (!isset($_SESSION['ec_user_id'])) {
    echo "<div class='p-8'>Access denied. You must be logged in to view this page.</div>";
    require_once 'templates/footer.php';
    exit();
}

$user_id = intval($_SESSION['ec_user_id']);
?>

    <style>
        /* Non-blocking Page Message */
        #page-message {
            position: fixed;
            top: 4.5rem; /* Below the main header */
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            color: white;
            font-weight: 500;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #page-message.visible {
            opacity: 1;
            visibility: visible;
        }
        #page-message.success {
            background-color: #10B981; /* green-500 */
        }
        #page-message.error {
            background-color: #EF4444; /* red-500 */
        }
    </style>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <h1 class="text-2xl font-bold text-gray-800">My HPI Questionnaire</h1>
                <button id="add-question-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition flex items-center">
                    <i data-lucide="plus" class="w-5 h-5 mr-2"></i>
                    Add My Question
                </button>
            </header>

            <!-- Non-blocking Page Message container -->
            <div id="page-message"></div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <p class="mb-4 text-gray-600">Manage your personalized HPI questions. These questions will be added to the end of the Global HPI form during your patient visits.</p>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wound Link</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody id="questions-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be injected by JavaScript -->
                            <tr><td colspan="7" class="p-8 text-center text-gray-500">Loading your questions...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Question Modal -->
    <div id="question-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-10 mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-lg bg-white">
            <h3 id="modal-title" class="text-xl font-bold text-gray-800 mb-4">Add My Question</h3>
            <form id="question-form" class="space-y-4">
                <input type="hidden" id="question_id" name="question_id">
                <input type="hidden" id="action" name="action" value="create">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                        <input type="text" id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., My Personal Questions" required>
                    </div>
                    <div>
                        <label for="question_type" class="block text-sm font-medium text-gray-700">Question Type</label>
                        <select id="question_type" name="question_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="text">Text (Single Line)</option>
                            <option value="textarea">Text Area (Multi-line)</option>
                            <option value="select">Dropdown (Select)</option>
                            <option value="radio">Radio Buttons</option>
                            <option value="checkbox">Checkboxes</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="question_text" class="block text-sm font-medium text-gray-700">Question Text</label>
                    <textarea id="question_text" name="question_text" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., How is the patient's appetite?" required></textarea>
                </div>

                <div id="options-container" class="hidden">
                    <label class="block text-sm font-medium text-gray-700">Options</label>
                    <div id="dynamic-options-builder" class="space-y-2 mt-1">
                        <!-- Option inputs will be added here by JS -->
                    </div>
                    <button type="button" id="add-option-btn" class="mt-2 bg-green-100 hover:bg-green-200 text-green-800 text-sm font-medium py-2 px-3 rounded-md flex items-center">
                        <i data-lucide="plus" class="w-4 h-4 mr-1"></i>
                        Add Option
                    </button>
                    <textarea id="options" name="options" class="hidden" rows="3"></textarea>
                    <p class="text-xs text-gray-500 mt-2">Add, remove, or re-order the options for your question.</p>
                </div>

                <div>
                    <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                    <input type="number" id="display_order" name="display_order" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" value="10">
                </div>

                <!-- Narrative Key is hidden/disabled for clinicians -->
                <input type="hidden" id="narrative_key" name="narrative_key" value="">

                <div>
                    <label for="allow_wound_link" class="flex items-center text-sm font-medium text-gray-700">
                        <input type="checkbox" id="allow_wound_link" name="allow_wound_link" value="1" class="h-4 w-4 rounded border-gray-300 text-blue-600 mr-2">
                        Allow linking to a specific wound?
                    </label>
                    <p class="text-xs text-gray-500 mt-1">If checked, you can answer this question for each active wound.</p>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button type="button" id="cancel-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition">Cancel</button>
                    <button type="submit" id="save-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition">Save Question</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Confirmation Delete Modal -->
    <div id="delete-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-6 border w-full max-w-md shadow-lg rounded-lg bg-white">
            <h3 id="delete-modal-title" class="text-xl font-bold text-red-700 mb-4">Confirm Deletion</h3>
            <p id="delete-modal-text" class="mb-4 text-gray-700">Are you sure you want to permanently delete this question? This action cannot be undone.</p>

            <div class="flex justify-end space-x-3">
                <button type="button" id="delete-cancel-btn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition">Cancel</button>
                <button type="button" id="delete-confirm-btn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Modals
            const modal = document.getElementById('question-modal');
            const modalTitle = document.getElementById('modal-title');
            const deleteModal = document.getElementById('delete-modal');
            const deleteConfirmBtn = document.getElementById('delete-confirm-btn');
            const deleteCancelBtn = document.getElementById('delete-cancel-btn');

            // Buttons
            const addBtn = document.getElementById('add-question-btn');
            const cancelBtn = document.getElementById('cancel-btn');

            // Forms & Table
            const questionForm = document.getElementById('question-form');
            const questionTypeSelect = document.getElementById('question_type');
            const optionsContainer = document.getElementById('options-container');
            const tableBody = document.getElementById('questions-table-body');

            // Options Builder
            const optionsBuilder = document.getElementById('dynamic-options-builder');
            const addOptionBtn = document.getElementById('add-option-btn');
            const hiddenOptionsTextarea = document.getElementById('options');

            // Messaging
            const pageMessage = document.getElementById('page-message');
            let messageTimer;
            let questionToDeleteId = null;

            /**
             * Shows a non-blocking message at the top of the page.
             * @param {string} message The text to display.
             * @param {string} type 'success' or 'error'.
             */
            function showMessage(message, type = 'success') {
                if (messageTimer) clearTimeout(messageTimer);
                pageMessage.textContent = message;
                pageMessage.className = type === 'success' ? 'success visible' : 'error visible';

                messageTimer = setTimeout(() => {
                    pageMessage.className = pageMessage.className.replace('visible', '');
                }, 3000);
            }

            // Modal Functions
            const showModal = () => modal.classList.remove('hidden');
            const hideModal = () => modal.classList.add('hidden');
            const showDeleteModal = (id) => {
                questionToDeleteId = id;
                deleteModal.classList.remove('hidden');
            };
            const hideDeleteModal = () => {
                deleteModal.classList.add('hidden');
                questionToDeleteId = null;
            };

            const resetForm = () => {
                questionForm.reset();
                document.getElementById('question_id').value = '';
                modalTitle.textContent = 'Add My Question';
                document.getElementById('action').value = 'create';
                optionsContainer.classList.add('hidden');
                document.getElementById('allow_wound_link').checked = false;
                optionsBuilder.innerHTML = '';
                hiddenOptionsTextarea.value = '[]';
            };

            addBtn.addEventListener('click', () => {
                resetForm();
                showModal();
            });
            cancelBtn.addEventListener('click', hideModal);
            deleteCancelBtn.addEventListener('click', hideDeleteModal);

            questionTypeSelect.addEventListener('change', () => {
                const type = questionTypeSelect.value;
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    optionsContainer.classList.remove('hidden');
                    if (optionsBuilder.children.length === 0) {
                        addOptionInput('');
                    }
                } else {
                    optionsContainer.classList.add('hidden');
                }
            });

            // --- Dynamic Options Builder Functions ---

            function updateHiddenOptionsTextarea() {
                const inputs = optionsBuilder.querySelectorAll('input[type="text"]');
                const optionsArray = Array.from(inputs)
                    .map(input => input.value)
                    .filter(val => val.trim() !== '');
                hiddenOptionsTextarea.value = JSON.stringify(optionsArray);
            }

            function addOptionInput(value = '') {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'flex items-center space-x-2';

                optionDiv.innerHTML = `
            <i data-lucide="grip-vertical" class="w-5 h-5 text-gray-400 cursor-move"></i>
            <input type="text" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" value="${value}" placeholder="Option text...">
            <button type="button" class="remove-option-btn bg-red-100 hover:bg-red-200 text-red-700 p-2 rounded-md">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
        `;

                optionDiv.querySelector('input').addEventListener('input', updateHiddenOptionsTextarea);

                optionDiv.querySelector('.remove-option-btn').addEventListener('click', () => {
                    optionDiv.remove();
                    updateHiddenOptionsTextarea();
                });

                optionsBuilder.appendChild(optionDiv);
                if (typeof lucide !== 'undefined') { lucide.createIcons(); }
            }

            function loadOptionsToBuilder(jsonString) {
                optionsBuilder.innerHTML = '';
                let options = [];
                try {
                    options = JSON.parse(jsonString || '[]');
                } catch (e) {
                    console.error("Invalid JSON options provided:", jsonString);
                    hiddenOptionsTextarea.value = jsonString; // Keep original invalid string
                    return;
                }

                if (options.length === 0) {
                    addOptionInput(''); // Start with one empty box
                } else {
                    options.forEach(opt => addOptionInput(opt));
                }
                updateHiddenOptionsTextarea(); // Sync textarea
            }

            addOptionBtn.addEventListener('click', () => {
                addOptionInput('');
            });

            // --- Data Fetching and Table Rendering ---

            async function fetchQuestions() {
                try {
                    // Add cache-busting parameter
                    const cacheBuster = new Date().getTime();
                    const response = await fetch(`api/manage_my_hpi_question.php?action=get_my_questions&_=${cacheBuster}`);

                    if (!response.ok) throw new Error('Failed to fetch');
                    const result = await response.json();

                    if (result.success) {
                        renderTable(result.questions);
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-500">${result.message}</td></tr>`;
                    }
                } catch (error) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-red-500">Error: ${error.message}</td></tr>`;
                }
            }

            function renderTable(questions) {
                if (questions.length === 0) {
                    tableBody.innerHTML = `<tr><td colspan="7" class="p-8 text-center text-gray-500">You have not created any personalized questions yet.</td></tr>`;
                    return;
                }

                tableBody.innerHTML = '';
                questions.forEach(q => {
                    const status = q.is_active == 1
                        ? `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>`
                        : `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>`;

                    const woundLink = q.allow_wound_link == 1
                        ? `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Enabled</span>`
                        : `<span class="text-xs text-gray-400">Disabled</span>`;

                    const actionText = q.is_active == 1 ? 'Deactivate' : 'Activate';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">${q.display_order}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${q.category}</td>
                <td class="px-4 py-3 max-w-xs truncate text-sm text-gray-900" title="${q.question_text}">${q.question_text}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-700">${q.question_type}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm">${woundLink}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm">${status}</td>
                <td class="px-4 py-3 whitespace-nowrap text-sm font-medium space-x-2">
                    <button class="edit-btn text-indigo-600 hover:text-indigo-900" data-id="${q.question_id}">Edit</button>
                    <button class="toggle-btn text-gray-600 hover:text-gray-900" data-id="${q.question_id}" data-status="${q.is_active}">${actionText}</button>
                    <button class="delete-btn text-red-600 hover:text-red-900" data-id="${q.question_id}">Delete</button>
                </td>
            `;

                    tableBody.appendChild(tr);
                });

                // Add event listeners to new buttons
                tableBody.querySelectorAll('.edit-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = btn.dataset.id;
                        const question = questions.find(q => q.question_id == id);
                        if (question) {
                            resetForm();
                            modalTitle.textContent = `Edit My Question (ID: ${question.question_id})`;
                            document.getElementById('action').value = 'update';
                            document.getElementById('question_id').value = question.question_id;
                            document.getElementById('category').value = question.category;
                            document.getElementById('question_type').value = question.question_type;
                            document.getElementById('question_text').value = question.question_text;
                            document.getElementById('display_order').value = question.display_order;
                            document.getElementById('allow_wound_link').checked = (question.allow_wound_link == 1);

                            questionTypeSelect.dispatchEvent(new Event('change'));
                            loadOptionsToBuilder(question.options);
                            showModal();
                        }
                    });
                });

                tableBody.querySelectorAll('.toggle-btn').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.id;
                        const newStatus = btn.dataset.status == '1' ? 0 : 1;

                        // --- FIX: Remove the blocking confirm() call ---
                        await submitForm({
                            action: 'toggle_active',
                            question_id: id,
                            new_status: newStatus
                        });
                        // --- END FIX ---
                    });
                });

                // Add listener for new delete buttons
                tableBody.querySelectorAll('.delete-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const id = btn.dataset.id;
                        showDeleteModal(id);
                    });
                });
            }

            // Main API submit function
            async function submitForm(data) {
                try {
                    const response = await fetch('api/manage_my_hpi_question.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    if (!response.ok) throw new Error('Server responded with an error');
                    const result = await response.json();

                    if (result.success) {
                        showMessage(result.message, 'success');
                        hideModal();
                        hideDeleteModal();
                        fetchQuestions(); // Refresh the table
                    } else {
                        showMessage(result.message, 'error');
                    }
                } catch (error) {
                    showMessage('An error occurred: ' + error.message, 'error');
                }
            }

            // Listener for the form (Add/Edit)
            questionForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                updateHiddenOptionsTextarea(); // Sync dynamic options to hidden textarea

                const formData = new FormData(questionForm);
                const data = Object.fromEntries(formData.entries());

                // Get checkbox value correctly
                data.allow_wound_link = document.getElementById('allow_wound_link').checked ? 1 : 0;

                // Validate options if needed
                const type = data.question_type;
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    try {
                        const options = JSON.parse(data.options);
                        if (!Array.isArray(options) || options.length === 0 || (options.length === 1 && options[0] === '')) {
                            showMessage('Please add at least one valid option for this question type.', 'error');
                            return;
                        }
                    } catch (jsonError) {
                        showMessage('Invalid JSON format for Options. This should not happen with the new builder.', 'error');
                        return;
                    }
                }

                await submitForm(data);
            });

            // Listener for the Delete confirmation
            deleteConfirmBtn.addEventListener('click', async () => {
                if (questionToDeleteId) {
                    await submitForm({
                        action: 'delete',
                        question_id: questionToDeleteId
                    });
                }
            });

            // Initial fetch
            fetchQuestions();
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
        });
    </script>

<?php require_once 'templates/footer.php'; ?>