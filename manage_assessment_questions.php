<?php
// Filename: ec/manage_assessment_questions.php
// NEW PAGE: Admin-only page to manage the 'wound_assessment_questions' library.

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
                    <h1 class="text-2xl font-bold text-gray-800">Assessment Questionnaire Management</h1>
                </div>
            </header>

            <!-- Page-level Message Bar -->
            <div id="page-message" class="hidden p-4 m-4 rounded-md text-sm"></div>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">

                    <div class="flex justify-between items-center mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-gray-700">Manage Assessment Questions</h2>
                        <button id="addQuestionBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center">
                            <i data-lucide="plus" class="w-5 h-5 mr-1"></i>
                            Add Question
                        </button>
                    </div>

                    <!-- Table Container -->
                    <div id="questions-table-container" class="overflow-x-auto">
                        <!-- Loading state -->
                        <div class="text-center p-8 text-gray-500">
                            <i data-lucide="loader-2" class="w-8 h-8 animate-spin inline-block mb-2"></i>
                            <p>Loading questions...</p>
                        </div>
                    </div>

                </div>
            </main>
        </div>
    </div>

    <!-- =================================================================
         MODALS
    ================================================================== -->

    <!-- Add/Edit Question Modal -->
    <div id="questionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-3xl shadow-lg rounded-md bg-white"> <!-- Wider Modal -->
            <div class="flex justify-between items-start border-b pb-3">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add Question</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('questionModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4 max-h-[70vh] overflow-y-auto pr-2">
                <form id="questionForm" class="space-y-4">
                    <input type="hidden" id="question_id" name="question_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Category -->
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <input type="text" id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Wound Bed, Exudate" required>
                        </div>

                        <!-- Question Type -->
                        <div>
                            <label for="question_type" class="block text-sm font-medium text-gray-700">Question Type</label>
                            <select id="question_type" name="question_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" required>
                                <option value="text">Text (Single Line)</option>
                                <option value="textarea">Text (Paragraph)</option>
                                <option value="select">Select (Dropdown)</option>
                                <option value="radio">Radio Buttons</option>
                                <option value="checkbox">Checkboxes</option>
                                <option value="number_percent">Number (Percentage)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Question Text -->
                    <div>
                        <label for="question_text" class="block text-sm font-medium text-gray-700">Question Text</label>
                        <textarea id="question_text" name="question_text" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="The question to be asked." required></textarea>
                    </div>

                    <!-- Options Builder -->
                    <div id="optionsBuilderContainer" class="p-4 border border-gray-200 rounded-md hidden">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Answer Options</label>
                            <button type="button" id="addOptionBtn" class="text-sm bg-indigo-100 text-indigo-700 hover:bg-indigo-200 py-1 px-3 rounded-md flex items-center">
                                <i data-lucide="plus" class="w-4 h-4 mr-1"></i> Add Option
                            </button>
                        </div>
                        <div id="optionsList" class="space-y-2">
                            <!-- Dynamic options will be added here -->
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Narrative Key -->
                        <div>
                            <label for="narrative_key" class="block text-sm font-medium text-gray-700">Narrative Key (Optional)</label>
                            <input type="text" id="narrative_key" name="narrative_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., exudate_type">
                            <p class="text-xs text-gray-500 mt-1">Links to auto-narrative & conditional logic.</p>
                        </div>

                        <!-- Display Order -->
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" value="0" required>
                            <p class="text-xs text-gray-500 mt-1">Lowest numbers appear first.</p>
                        </div>
                    </div>

                    <!-- --- NEW: Conditional Logic Fields --- -->
                    <div class="mt-4 p-4 border border-blue-200 bg-blue-50 rounded-md">
                        <h4 class="text-md font-semibold text-blue-800">Conditional Logic (Optional)</h4>
                        <p class="text-xs text-gray-600 mb-3">Show this question ONLY IF another question has a specific answer.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="condition_key" class="block text-sm font-medium text-gray-700">If Question (Narrative Key):</label>
                                <input type="text" id="condition_key" name="condition_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., odor_present">
                            </div>
                            <div>
                                <label for="condition_value" class="block text-sm font-medium text-gray-700">Has Answer:</label>
                                <input type="text" id="condition_value" name="condition_value" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Yes">
                            </div>
                        </div>
                    </div>
                    <!-- --- END: Conditional Logic Fields --- -->

                    <!-- Form Actions -->
                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('questionModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Save Question
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
                    Are you sure you want to permanently delete this question? This action cannot be undone.
                </p>
                <form id="deleteForm">
                    <input type="hidden" id="delete_question_id" name="delete_question_id">
                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('deleteModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Yes, Delete Question
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const questionModal = document.getElementById('questionModal');
            const modalTitle = document.getElementById('modalTitle');
            const questionForm = document.getElementById('questionForm');
            const questionIdInput = document.getElementById('question_id');
            const addQuestionBtn = document.getElementById('addQuestionBtn');
            const messageBar = document.getElementById('page-message');
            const questionsTableContainer = document.getElementById('questions-table-container');

            const optionsTypeSelect = document.getElementById('question_type');
            const optionsBuilderContainer = document.getElementById('optionsBuilderContainer');
            const optionsList = document.getElementById('optionsList');
            const addOptionBtn = document.getElementById('addOptionBtn');

            const deleteModal = document.getElementById('deleteModal');
            const deleteForm = document.getElementById('deleteForm');
            const deleteQuestionIdInput = document.getElementById('delete_question_id');

            let allQuestions = []; // Cache for editing

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

            async function fetchQuestions() {
                try {
                    const cacheBuster = `&_=${new Date().getTime()}`;
                    const response = await fetch(`api/manage_assessment_question.php?action=get_all${cacheBuster}`);
                    if (!response.ok) throw new Error('Failed to fetch data.');

                    const data = await response.json();
                    if (data.success) {
                        allQuestions = data.questions;
                        renderTable(data.questions);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                    questionsTableContainer.innerHTML = `<div class="text-center p-8 text-red-500">Failed to load questions: ${error.message}</div>`;
                }
            }

            function renderTable(questions) {
                let tableHtml = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conditional On</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="questionsTableBody" class="bg-white divide-y divide-gray-200">
            `;

                if (questions.length === 0) {
                    tableHtml += '<tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No assessment questions found.</td></tr>';
                }

                questions.forEach(q => {
                    const status = q.is_active == 1
                        ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
                        : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';

                    const conditional = q.condition_key ? `<span class="text-xs text-blue-700">${q.condition_key} = ${q.condition_value}</span>` : '<span class="text-xs text-gray-400">N/A</span>';

                    const toggleBtnText = q.is_active == 1 ? 'Deactivate' : 'Activate';
                    const toggleBtnClass = q.is_active == 1 ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200';

                    tableHtml += `
                    <tr>
                        <td class="px-4 py-4 text-sm font-medium text-gray-900" style="white-space: normal;">${q.question_text}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.category}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.display_order}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.question_type}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${conditional}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm">${status}</td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-1">
                            <button class="edit-btn p-2 rounded-md bg-blue-100 text-blue-800 hover:bg-blue-200" data-id="${q.question_id}" title="Edit"><i data-lucide="edit" class="w-4 h-4"></i></button>
                            <button class="toggle-btn p-2 rounded-md ${toggleBtnClass}" data-id="${q.question_id}" data-status="${q.is_active}" title="${toggleBtnText}">${toggleBtnText === 'Deactivate' ? '<i data-lucide="toggle-right" class="w-4 h-4"></i>' : '<i data-lucide="toggle-left" class="w-4 h-4"></i>'}</button>
                            <button class="delete-btn p-2 rounded-md bg-red-100 text-red-800 hover:bg-red-200" data-id="${q.question_id}" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                        </td>
                    </tr>
                `;
                });

                tableHtml += '</tbody></table>';
                questionsTableContainer.innerHTML = tableHtml;
                lucide.createIcons();
            }

            function resetForm() {
                questionForm.reset();
                questionIdInput.value = '';
                modalTitle.textContent = 'Add Question';
                optionsList.innerHTML = '';
                optionsBuilderContainer.classList.add('hidden');
            }

            function addOptionInput(value = '') {
                const div = document.createElement('div');
                div.className = 'flex items-center space-x-2';
                div.innerHTML = `
                <input type="text" value="${value}" name="options[]" class="block w-full border-gray-300 rounded-md shadow-sm p-2 text-sm" placeholder="Option value" required>
                <button type="button" class="remove-option-btn text-red-500 hover:text-red-700 p-1">
                    <i data-lucide="x-circle" class="w-5 h-5"></i>
                </button>
            `;
                optionsList.appendChild(div);
                lucide.createIcons();
            }

            optionsTypeSelect.addEventListener('change', (e) => {
                const type = e.target.value;
                if (['select', 'radio', 'checkbox'].includes(type)) {
                    optionsBuilderContainer.classList.remove('hidden');
                    if (optionsList.children.length === 0) {
                        addOptionInput();
                    }
                } else {
                    optionsBuilderContainer.classList.add('hidden');
                }
            });

            addOptionBtn.addEventListener('click', () => addOptionInput());

            optionsList.addEventListener('click', (e) => {
                const removeBtn = e.target.closest('.remove-option-btn');
                if (removeBtn) {
                    removeBtn.parentElement.remove();
                }
            });

            addQuestionBtn.addEventListener('click', () => {
                resetForm();
                openModal(questionModal);
            });

            questionsTableContainer.addEventListener('click', async (e) => {
                const button = e.target.closest('button');
                if (!button) return;

                const id = button.dataset.id;

                if (button.classList.contains('edit-btn')) {
                    const question = allQuestions.find(q => q.question_id == id);
                    if (question) {
                        resetForm();
                        modalTitle.textContent = 'Edit Question';
                        questionIdInput.value = question.question_id;
                        document.getElementById('category').value = question.category;
                        document.getElementById('question_text').value = question.question_text;
                        document.getElementById('question_type').value = question.question_type;
                        document.getElementById('narrative_key').value = question.narrative_key;
                        document.getElementById('display_order').value = question.display_order;
                        document.getElementById('condition_key').value = question.condition_key;
                        document.getElementById('condition_value').value = question.condition_value;

                        if (['select', 'radio', 'checkbox'].includes(question.question_type)) {
                            optionsBuilderContainer.classList.remove('hidden');
                            try {
                                const options = JSON.parse(question.options || '[]');
                                options.forEach(opt => addOptionInput(opt));
                            } catch (err) {}
                        }
                        openModal(questionModal);
                    }
                }

                if (button.classList.contains('toggle-btn')) {
                    const currentStatus = button.dataset.status;
                    const newStatus = currentStatus == 1 ? 0 : 1;

                    await submitFormApi({
                        action: 'toggle_active',
                        question_id: id,
                        new_status: newStatus
                    });
                }

                if (button.classList.contains('delete-btn')) {
                    deleteQuestionIdInput.value = id;
                    openModal(deleteModal);
                }
            });

            async function submitFormApi(data) {
                try {
                    const response = await fetch('api/manage_assessment_question.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    if (result.success) {
                        showMessage(result.message, 'success');
                        closeModal(questionModal);
                        closeModal(deleteModal);
                        fetchQuestions(); // Refresh table
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                }
            }

            questionForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(questionForm);
                const options = Array.from(formData.getAll('options[]')).filter(opt => opt.trim() !== '');

                const data = {
                    action: questionIdInput.value ? 'update' : 'create',
                    question_id: questionIdInput.value || undefined,
                    category: formData.get('category'),
                    question_text: formData.get('question_text'),
                    question_type: formData.get('question_type'),
                    options: JSON.stringify(options),
                    narrative_key: formData.get('narrative_key') || null,
                    display_order: formData.get('display_order'),
                    condition_key: formData.get('condition_key') || null,
                    condition_value: formData.get('condition_value') || null
                };

                await submitFormApi(data);
            });

            deleteForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                await submitFormApi({
                    action: 'delete',
                    question_id: deleteQuestionIdInput.value
                });
            });

            fetchQuestions();
            lucide.createIcons();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>