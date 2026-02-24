<?php
// Filename: ec/manage_hpi_questions.php
// COMPLETE: This file is the Admin-facing page to manage ALL HPI questions (Global & Personalized).
// UPDATED:
// 1. Added "Order" column to the table.
// 2. Added a "Full Preview" button and modal system.
// 3. Made the Full Preview modal wider (max-w-5xl).
// 4. --- CSS FIX: Reworked Full Preview modal to use flex-col, ensuring the *content* scrolls internally, not the whole page.
// 5. --- JS FIX: Added slight delay to modal opening to prevent overlap.

session_start();
require_once 'templates/header.php';
require_once 'db_connect.php'; // Used for $conn

// --- Admin-only Page ---
// Use robust, case-insensitive check for 'admin'
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
                    <h1 class="text-2xl font-bold text-gray-800">HPI Questionnaire Management</h1>
                </div>
                <div class="flex items-center">
                    <span class="text-sm text-gray-600 mr-4">Logged in as <?php echo htmlspecialchars($_SESSION['ec_full_name']); ?></span>
                </div>
            </header>

            <!-- Page-level Message Bar -->
            <div id="page-message" class="hidden p-4 m-4 rounded-md text-sm"></div>
            <?php require_once 'templates/data_management.php'; ?>
            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="bg-white rounded-lg shadow-lg p-6">

                    <div class="flex justify-between items-center mb-6 border-b pb-4">
                        <h2 class="text-xl font-semibold text-gray-700">Manage All HPI Questions</h2>
                        <div class="flex space-x-2">
                            <!-- NEW: Full Preview Button -->
                            <button id="fullPreviewBtn" class="bg-green-500 hover:bg-teal-600 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center">
                                <i data-lucide="layout-list" class="w-5 h-5 mr-1"></i>
                                Full Preview
                            </button>
                            <button id="addQuestionBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md transition text-sm flex items-center">
                                <i data-lucide="plus" class="w-5 h-5 mr-1"></i>
                                Add Global Question
                            </button>
                        </div>
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
        <div class="relative mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 id="modalTitle" class="text-xl font-bold text-gray-900">Add/Edit Question</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('questionModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4 max-h-[70vh] overflow-y-auto pr-2">
                <form id="questionForm">
                    <input type="hidden" id="question_id" name="question_id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Category -->
                        <div>
                            <label for="category" class="block text-sm font-medium text-gray-700">Category</label>
                            <input type="text" id="category" name="category" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., Pain Assessment" required>
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
                            </select>
                        </div>
                    </div>

                    <!-- Question Text -->
                    <div class="mt-4">
                        <label for="question_text" class="block text-sm font-medium text-gray-700">Question Text</label>
                        <textarea id="question_text" name="question_text" rows="2" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="The question to be asked." required></textarea>
                    </div>

                    <!-- Options Builder -->
                    <div id="optionsBuilderContainer" class="mt-4 p-4 border border-gray-200 rounded-md hidden">
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

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <!-- Narrative Key -->
                        <div>
                            <label for="narrative_key" class="block text-sm font-medium text-gray-700">Narrative Key (Optional)</label>
                            <input type="text" id="narrative_key" name="narrative_key" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" placeholder="e.g., problem_status">
                            <p class="text-xs text-gray-500 mt-1">For Global questions only. Links to auto-narrative.</p>
                        </div>

                        <!-- Display Order -->
                        <div>
                            <label for="display_order" class="block text-sm font-medium text-gray-700">Display Order</label>
                            <input type="number" id="display_order" name="display_order" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" value="0" required>
                            <p class="text-xs text-gray-500 mt-1">Lowest numbers appear first.</p>
                        </div>
                    </div>

                    <!-- Wound Link Checkbox -->
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" id="allow_wound_link" name="allow_wound_link" value="1" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <span class="ml-2 text-sm font-medium text-gray-700">Allow linking to a specific wound?</span>
                        </label>
                        <p class="text-xs text-gray-500 ml-6">Repeats this question for each active wound.</p>
                    </div>

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
                    Are you sure you want to permanently delete this question?
                    <br><br>
                    <strong class="text-red-600">This action cannot be undone.</strong>
                    All answers associated with this question will also be permanently deleted.
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

    <!-- Single Question Preview Modal -->
    <div id="previewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-2xl shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 class="text-xl font-bold text-gray-900">Question Preview</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('previewModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div id="previewModalContent" class="mt-4 max-h-[70vh] overflow-y-auto pr-2 bg-gray-50 p-4 rounded-md">
                <!-- Preview content will be injected here -->
            </div>
            <div class="mt-6 pt-4 border-t flex justify-end">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('previewModal'))">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- NEW: Clinician Selector Modal (for Full Preview) -->
    <div id="clinicianSelectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full flex items-center justify-center z-50 hidden">
        <div class="relative mx-auto p-6 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-start border-b pb-3">
                <h3 class="text-xl font-bold text-gray-900">Full Preview</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('clinicianSelectModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <div class="mt-4">
                <p class="text-sm text-gray-700 mb-4">
                    Please select a clinician to preview their complete questionnaire, including their personalized questions.
                </p>
                <form id="clinicianSelectForm">
                    <div>
                        <label for="clinicianSelect" class="block text-sm font-medium text-gray-700">Clinician</label>
                        <select id="clinicianSelect" name="clinician_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2" required>
                            <option value="">Loading clinicians...</option>
                        </select>
                    </div>
                    <div class="mt-6 pt-4 border-t flex justify-end space-x-3">
                        <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('clinicianSelectModal'))">
                            Cancel
                        </button>
                        <button type="submit" class="bg-green-600 hover:bg-teal-700 text-white font-bold py-2 px-4 rounded-md transition text-sm">
                            Show Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- NEW: Full Preview Modal -->
    <!-- *** CSS FIX: Reworked for internal scrolling *** -->
    <div id="fullPreviewModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-hidden h-full w-full flex justify-center z-[60] pt-12 p-4 hidden"> <!-- Higher z-index, overflow-hidden -->
        <!-- Modal Card: Added flex-col and max-height -->
        <div class="relative mx-auto p-6 border w-full max-w-5xl shadow-lg rounded-md bg-white flex flex-col max-h-[calc(100vh-6rem)]">
            <!-- Modal Header: Added flex-shrink-0 -->
            <div class="flex justify-between items-start border-b pb-3 flex-shrink-0">
                <h3 id="fullPreviewTitle" class="text-xl font-bold text-gray-900">Full Questionnaire Preview</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal(document.getElementById('fullPreviewModal'))">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <!-- Modal Content: Added flex-grow and overflow-y-auto, removed max-h -->
            <div id="fullPreviewContent" class="mt-4 flex-grow overflow-y-auto pr-2 bg-gray-50 p-4 rounded-md">
                <!-- Full preview content will be injected here -->
            </div>
            <!-- Modal Footer: Added flex-shrink-0 -->
            <div class="mt-6 pt-4 border-t flex justify-end flex-shrink-0">
                <button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-md transition text-sm" onclick="closeModal(document.getElementById('fullPreviewModal'))">
                    Close
                </button>
            </div>
        </div>
    </div>


    <!-- Add styles for the new preview feature -->
    <style>
        .wound-context-list {
            margin-top: 0.75rem;
            padding-left: 1rem;
            border-left: 3px solid #e5e7eb;
            space-y: 0.75rem;
        }
        .wound-context-input-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .wound-context-input-row .wound-context-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            flex-shrink: 0;
        }
        .wound-context-label.general-label {
            color: #1d4ed8;
            font-weight: 600;
        }
        .wound-context-input-row .input-wrapper {
            flex-grow: 1;
            max-width: 60%;
        }
        .wound-context-input-column .wound-context-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-section-preview {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        .form-section-preview:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
    </style>

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

            const previewModal = document.getElementById('previewModal');
            const previewModalContent = document.getElementById('previewModalContent');

            // --- NEW Full Preview elements ---
            const fullPreviewBtn = document.getElementById('fullPreviewBtn');
            const clinicianSelectModal = document.getElementById('clinicianSelectModal');
            const clinicianSelectForm = document.getElementById('clinicianSelectForm');
            const clinicianSelect = document.getElementById('clinicianSelect');
            const fullPreviewModal = document.getElementById('fullPreviewModal');
            const fullPreviewContent = document.getElementById('fullPreviewContent');
            const fullPreviewTitle = document.getElementById('fullPreviewTitle');
            // --- END New elements ---

            let allQuestions = []; // Cache for preview

            /**
             * Shows a non-blocking message bar at the top of the page.
             * @param {string} message The message to display.
             * @param {string} type 'success', 'error', or 'info'.
             */
            function showMessage(message, type = 'info') {
                messageBar.textContent = message;
                messageBar.className = 'p-4 m-4 rounded-md text-sm'; // Reset classes
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

            /**
             * Opens a modal.
             * @param {HTMLElement} modal The modal element to open.
             */
            function openModal(modal) {
                modal.classList.remove('hidden');
            }

            /**
             * Closes a modal.
             * @param {HTMLElement} modal The modal element to close.
             */
            window.closeModal = (modal) => { // Make global for onclick attributes
                modal.classList.add('hidden');
            }

            /**
             * Fetches all questions from the API and renders the table.
             */
            async function fetchQuestions() {
                try {
                    // Cache-busting parameter
                    const cacheBuster = `&_=${new Date().getTime()}`;
                    const response = await fetch(`api/manage_hpi_question.php?action=get_all_with_users${cacheBuster}`);
                    if (!response.ok) throw new Error('Failed to fetch data.');

                    const data = await response.json();
                    if (data.success) {
                        allQuestions = data.questions; // Cache for preview
                        renderTable(data.questions);
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                    questionsTableContainer.innerHTML = `<div class="text-center p-8 text-red-500">Failed to load questions: ${error.message}</div>`;
                }
            }

            /**
             * Renders the questions data into an HTML table.
             * @param {Array} questions Array of question objects.
             */
            function renderTable(questions) {
                let tableHtml = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Question Text</th>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <!-- NEW: "Order" column -->
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Owner</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wound Link</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                    </thead>
                    <tbody id="questionsTableBody" class="bg-white divide-y divide-gray-200">
                    `;

            if (questions.length === 0) {
                tableHtml += '<tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No HPI questions found.</td></tr>';
            }

            questions.forEach(q => {
                const owner = q.full_name ? `<span class="text-xs font-semibold text-blue-700">${q.full_name}</span>` : '<span class="text-xs font-semibold text-gray-700">Global (Admin)</span>';
                const status = q.is_active == 1
                    ? '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>'
                    : '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';

                const woundLink = q.allow_wound_link == 1 ? 'Yes' : 'No';

                const toggleBtnText = q.is_active == 1 ? 'Deactivate' : 'Activate';
                const toggleBtnClass = q.is_active == 1 ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200';

                tableHtml += `
                    <tr>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">${q.question_text}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.category}</td>
                    <!-- NEW: "Order" cell -->
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.display_order}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${q.question_type}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${owner}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500">${woundLink}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm">${status}</td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium space-x-1">
                    <button class="preview-btn p-2 rounded-md bg-cyan-100 text-cyan-800 hover:bg-cyan-200" data-id="${q.question_id}" title="Preview"><i data-lucide="eye" class="w-4 h-4"></i></button>
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

        /**
         * Clears and resets the "Add/Edit Question" modal form.
         */
        function resetForm() {
            questionForm.reset();
            questionIdInput.value = '';
            modalTitle.textContent = 'Add Global Question';
            document.getElementById('narrative_key').disabled = false;
            document.getElementById('narrative_key').classList.remove('bg-gray-100');
            optionsList.innerHTML = '';
            optionsBuilderContainer.classList.add('hidden');
        }

        /**
         * Adds a new, empty text input to the options builder.
         * @param {string} value The value to pre-fill (used for editing).
         */
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

        // --- Event Listeners ---

        // Show/Hide Options Builder based on Question Type
        optionsTypeSelect.addEventListener('change', (e) => {
            const type = e.target.value;
            if (['select', 'radio', 'checkbox'].includes(type)) {
                optionsBuilderContainer.classList.remove('hidden');
                if (optionsList.children.length === 0) {
                    addOptionInput(); // Add one by default
                }
            } else {
                optionsBuilderContainer.classList.add('hidden');
            }
        });

        // Add new option input
        addOptionBtn.addEventListener('click', () => addOptionInput());

        // Remove option input (using event delegation)
        optionsList.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.remove-option-btn');
            if (removeBtn) {
                removeBtn.parentElement.remove();
            }
        });

        // Open "Add Global Question" modal
        addQuestionBtn.addEventListener('click', () => {
            resetForm();
            openModal(questionModal);
        });

        // Handle Table Actions (Preview, Edit, Toggle, Delete)
        questionsTableContainer.addEventListener('click', async (e) => {
            const button = e.target.closest('button');
            if (!button) return;

            const id = button.dataset.id;

            // --- PREVIEW ---
            if (button.classList.contains('preview-btn')) {
                const question = allQuestions.find(q => q.question_id == id);
                if (question) {
                    showPreview(question);
                }
            }

            // --- EDIT ---
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
                    document.getElementById('allow_wound_link').checked = (question.allow_wound_link == 1);

                    // If it's a personalized question, disable the narrative key
                    if (question.user_id != null) {
                        document.getElementById('narrative_key').disabled = true;
                        document.getElementById('narrative_key').classList.add('bg-gray-100');
                    }

                    // Show/Hide options builder and populate
                    if (['select', 'radio', 'checkbox'].includes(question.question_type)) {
                        optionsBuilderContainer.classList.remove('hidden');
                        try {
                            const options = JSON.parse(question.options || '[]');
                            if (options.length > 0) {
                                options.forEach(opt => addOptionInput(opt));
                            } else {
                                addOptionInput(); // Add one empty
                            }
                        } catch (err) {
                            addOptionInput(); // Add one empty on error
                        }
                    }

                    openModal(questionModal);
                }
            }

            // --- TOGGLE ACTIVE/INACTIVE ---
            if (button.classList.contains('toggle-btn')) {
                const currentStatus = button.dataset.status;
                const newStatus = currentStatus == 1 ? 0 : 1;

                try {
                    const response = await fetch('api/manage_hpi_question.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'toggle_active',
                            question_id: id,
                            new_status: newStatus
                        })
                    });
                    const data = await response.json();
                    if (data.success) {
                        showMessage(data.message, 'success');
                        fetchQuestions(); // Refresh table
                    } else {
                        throw new Error(data.message);
                    }
                } catch (error) {
                    showMessage(error.message, 'error');
                }
            }

            // --- DELETE ---
            if (button.classList.contains('delete-btn')) {
                deleteQuestionIdInput.value = id;
                openModal(deleteModal);
            }
        });

        // Handle "Add/Edit" Form Submission
        questionForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(questionForm);

            const options = Array.from(formData.getAll('options[]')).filter(opt => opt.trim() !== '');

            const data = {
                action: questionIdInput.value ? 'update' : 'create_global',
                question_id: questionIdInput.value || undefined,
                category: formData.get('category'),
                question_text: formData.get('question_text'),
                question_type: formData.get('question_type'),
                options: JSON.stringify(options),
                narrative_key: formData.get('narrative_key') || null,
                display_order: formData.get('display_order'),
                allow_wound_link: formData.get('allow_wound_link') ? 1 : 0
            };

            try {
                const response = await fetch('api/manage_hpi_question.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (result.success) {
                    showMessage(result.message, 'success');
                    closeModal(questionModal);
                    fetchQuestions(); // Refresh table
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
            const question_id = deleteQuestionIdInput.value;

            try {
                const response = await fetch('api/manage_hpi_question.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete',
                        question_id: question_id
                    })
                });
                const data = await response.json();
                if (data.success) {
                    showMessage(data.message, 'success');
                    closeModal(deleteModal);
                    fetchQuestions(); // Refresh table
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showMessage(error.message, 'error');
            }
        });

        // --- PREVIEW FUNCTIONS (SINGLE AND FULL) ---

        /**
         * Renders a preview of a single question in the preview modal.
         * @param {object} q The question object.
         */
        function showPreview(q) {
            previewModalContent.innerHTML = ''; // Clear old preview

            // Build the contexts for the preview
            const woundContexts = [
                { wound_id: null, label: 'General HPI' }
            ];

            if (q.allow_wound_link == 1) {
                woundContexts.push({
                    wound_id: 'SAMPLE',
                    label: 'Sample Wound (e.g., Left Heel)'
                });
            }

            const questionWrapper = document.createElement('div');
            questionWrapper.className = 'mb-4';

            if (q.allow_wound_link == 1) {
                // --- WOUND-LINKABLE PREVIEW ---
                const mainLabel = document.createElement('label');
                mainLabel.className = 'block text-sm font-medium text-gray-900';
                mainLabel.textContent = q.question_text;
                questionWrapper.appendChild(mainLabel);

                const contextListWrapper = document.createElement('div');
                contextListWrapper.className = 'wound-context-list';

                woundContexts.forEach(wound => {
                    const contextHTML = createWoundContextInput(q, wound.wound_id, wound.label, true);
                    const contextBlock = document.createElement('div');
                    contextBlock.innerHTML = contextHTML;
                    contextListWrapper.appendChild(contextBlock);
                });
                questionWrapper.appendChild(contextListWrapper);
            } else {
                // --- STANDARD QUESTION PREVIEW ---
                const inputId = `preview_${q.question_id}_NULL`;
                const mainLabel = document.createElement('label');
                mainLabel.className = 'block text-sm font-medium text-gray-700';
                mainLabel.htmlFor = inputId;
                mainLabel.textContent = q.question_text;
                questionWrapper.appendChild(mainLabel);

                const inputHTML = createInputHTML(q, 'NULL', true);
                const inputWrapper = document.createElement('div');
                inputWrapper.innerHTML = inputHTML;
                questionWrapper.appendChild(inputWrapper);
            }

            previewModalContent.appendChild(questionWrapper);
            lucide.createIcons();
            openModal(previewModal);
        }

        /**
         * Helper function to generate *only* the HTML for the input part of a question.
         * @param {object} q The question object.
         * @param {string} woundKey The wound_id or 'NULL'.
         * @param {boolean} isPreview Disables inputs if true.
         */
        function createInputHTML(q, woundKey, isPreview = false) {
            const inputName = `preview_${q.question_id}_${woundKey}`;
            const inputId = inputName;
            const disabled = isPreview ? 'disabled' : '';

            let inputHtml = '';

            switch (q.question_type) {
                case 'text':
                    inputHtml = `<input type="text" name="${inputName}" id="${inputId}" ${disabled} class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100">`;
                    break;
                case 'textarea':
                    inputHtml = `<textarea name="${inputName}" id="${inputId}" ${disabled} rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100"></textarea>`;
                    break;
                case 'select':
                    let options = JSON.parse(q.options || '[]').map(opt => `<option value="${opt}">${opt}</option>`).join('');
                    inputHtml = `<select name="${inputName}" id="${inputId}" ${disabled} class="mt-1 block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100">
                    <option value="">-- Select --</option>
                    ${options}
                    </select>`;
                    break;
                case 'radio':
                    let radioOptions = JSON.parse(q.options || '[]').map((opt, i) =>
                        `<label class="flex items-center text-gray-700"><input type="radio" name="${inputName}" id="${inputId}_${i}" value="${opt}" ${disabled} class="h-4 w-4 border-gray-300 text-indigo-600 mr-2">${opt}</label>`
                    ).join('');
                    inputHtml = `<div class="mt-2 flex space-x-4">${radioOptions}</div>`;
                    break;
                case 'checkbox':
                    let checkOptions = JSON.parse(q.options || '[]').map((opt, i) =>
                        `<label class="flex items-center text-gray-700"><input type="checkbox" name="${inputName}[]" id="${inputId}_${i}" value="${opt}" ${disabled} class="h-4 w-4 rounded border-gray-300 text-indigo-600 mr-2">${opt}</label>`
                    ).join('');
                    inputHtml = `<div class="mt-2 checkbox-grid">${checkOptions}</div>`;
                    break;
            }
            return inputHtml;
        }

        /**
         * Helper function to generate the repeating row for wound-linkable questions.
         * @param {object} q The question object.
         * @param {string} wound_id The wound_id or 'SAMPLE' or null.
         * @param {string} wound_label The text label for the row.
         * @param {boolean} isPreview Disables inputs if true.
         */
        function createWoundContextInput(q, wound_id, wound_label, isPreview = false) {
            const woundKey = wound_id ? wound_id : 'NULL';
            const inputName = `preview_${q.question_id}_${woundKey}`;
            const inputId = inputName;
            const disabled = isPreview ? 'disabled' : '';

            let inputHtml = '';
            const labelHtml = `<label for="${inputId}" class="wound-context-label ${woundKey === 'NULL' ? 'general-label' : ''}">${wound_label}:</label>`;

            if (q.question_type === 'radio' || q.question_type === 'checkbox') {
                const options = JSON.parse(q.options || '[]').map((opt, i) => {
                    const optionId = `${inputId}_${i}`;
                    const optionName = q.question_type === 'checkbox' ? `${inputName}[]` : inputName;
                    return `<label class="flex items-center text-gray-700">
                    <input type="${q.question_type}" name="${optionName}" id="${optionId}" value="${opt}" ${disabled} class="h-4 w-4 border-gray-300 text-indigo-600 mr-2">
                    ${opt}
                    </label>`;
                }).join('');
                const gridClass = q.question_type === 'checkbox' ? 'checkbox-grid' : 'flex space-x-4';
                inputHtml = `
                    <div class="wound-context-input-column mt-3">
                    ${labelHtml}
                    <div class="mt-2 ${gridClass}">${options}</div>
                    </div>`;
            } else {
                let fieldHtml = '';
                if (q.question_type === 'text') {
                    fieldHtml = `<input type="text" name="${inputName}" id="${inputId}" ${disabled} class="block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100">`;
                } else if (q.question_type === 'textarea') {
                     fieldHtml = `<textarea name="${inputName}" id="${inputId}" ${disabled} rows="2" class="block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100"></textarea>`;
                } else if (q.question_type === 'select') {
                    let options = JSON.parse(q.options || '[]').map(opt => `<option value="${opt}">${opt}</option>`).join('');
                    fieldHtml = `<select name="${inputName}" id="${inputId}" ${disabled} class="block w-full border-gray-300 rounded-md shadow-sm p-2 bg-gray-100">
                    <option value="">-- Select --</option>
                    ${options}
                    </select>`;
                }
                inputHtml = `
                    <div class="wound-context-input-row">
                    ${labelHtml}
                    <div class="input-wrapper">${fieldHtml}</div>
                    </div>`;
            }
            return inputHtml;
        }

        // --- NEW: FULL PREVIEW EVENT HANDLERS ---

        // 1. Open Clinician Selector
        fullPreviewBtn.addEventListener('click', async () => {
            try {
                // Fetch all users to populate dropdown
                // FIX: Add cache buster
                const cacheBuster = `&_=${new Date().getTime()}`;
                const response = await fetch(`api/get_all_users.php?${cacheBuster}`);
                const data = await response.json();

                if (data.success) {
                    clinicianSelect.innerHTML = '<option value="">-- Select a User --</option>';
                    // Add an option for "Global Only" (Admin)
                    clinicianSelect.innerHTML += `<option value="ADMIN_GLOBAL">Preview as Admin (Global Questions Only)</option>`;

                    data.users.forEach(user => {
                        if(user.role === 'clinician') { // Only list clinicians
                            clinicianSelect.innerHTML += `<option value="${user.user_id}">${user.full_name} (Clinician)</option>`;
                        }
                    });
                    openModal(clinicianSelectModal);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                showMessage(`Error fetching clinicians: ${error.message}`, 'error');
            }
        });

        // 2. Handle Clinician Selection and Fetch Full Preview
        clinicianSelectForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const selectedValue = clinicianSelect.value;
            const selectedText = clinicianSelect.options[clinicianSelect.selectedIndex].text;

            if (!selectedValue) {
                showMessage('Please select a user to preview.', 'error');
                return;
            }

            let clinicianId;
            if (selectedValue === 'ADMIN_GLOBAL') {
                clinicianId = 0; // Use 0 or another non-existent ID to only fetch NULL user_id questions
            } else {
                clinicianId = selectedValue;
            }

            closeModal(clinicianSelectModal);
            fullPreviewTitle.textContent = `Full Preview for: ${selectedText}`;
            fullPreviewContent.innerHTML = `
                    <div class="text-center p-8 text-gray-500">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin inline-block mb-2"></i>
                    <p>Loading questionnaire for ${selectedText}...</p>
                    </div>`;
            lucide.createIcons();

            // --- JS FIX: Add a small delay to prevent modal overlap ---
            setTimeout(() => {
                openModal(fullPreviewModal);
            }, 50); // 50ms delay
            // --- END FIX ---

            // Fetch questions and build the full preview
            await loadFullPreview(clinicianId);
        });

        /**
         * Fetches questions for a user and builds the full preview modal.
         * @param {string|number} clinicianId The ID of the clinician (or '0' for Global only).
         */
        async function loadFullPreview(clinicianId) {
            try {
                const cacheBuster = new Date().getTime();
                // We use the 'get_hpi_questions.php' API from the *visit* workflow,
                // as it's designed to get the exact list a clinician would see.
                const response = await fetch(`api/get_hpi_questions.php?active=true&user_id=${clinicianId}&_=${cacheBuster}`);
                const data = await response.json();

                if (!data.success) throw new Error(data.message);

                const questions = data.questions;
                const sampleWounds = [
                    { wound_id: null, label: 'General HPI' },
                    { wound_id: 'SAMPLE_1', label: 'Sample Wound 1 (e.g., Left Heel)' },
                    { wound_id: 'SAMPLE_2', label: 'Sample Wound 2 (e.g., Right Sacrum)' }
                ];

                buildFullPreview(questions, sampleWounds);

            } catch (error) {
                fullPreviewContent.innerHTML = `<div class="text-center p-8 text-red-500">Failed to load preview: ${error.message}</div>`;
            }
        }

        /**
         * Renders the complete questionnaire into the full preview modal.
         * (Adapted from visit_hpi.php's buildDynamicForm)
         * @param {Array} questions
         * @param {Array} sampleWounds
         */
        function buildFullPreview(questions, sampleWounds) {
            fullPreviewContent.innerHTML = '';
            let currentCategory = '';
            let currentSection = null;

            questions.forEach(q => {
                if (q.category !== currentCategory) {
                    currentCategory = q.category;
                    const sectionDiv = document.createElement('div');
                    sectionDiv.className = 'form-section-preview';
                    sectionDiv.innerHTML = `<h2 class="text-xl font-bold text-gray-700 mb-2">${q.category}</h2>`;
                    fullPreviewContent.appendChild(sectionDiv);
                    currentSection = sectionDiv;
                }

                const questionWrapper = document.createElement('div');
                questionWrapper.className = 'mb-4';

                if (q.allow_wound_link == 1) {
                    const mainLabel = document.createElement('label');
                    mainLabel.className = 'block text-sm font-medium text-gray-900';
                    mainLabel.textContent = q.question_text;
                    questionWrapper.appendChild(mainLabel);

                    const contextListWrapper = document.createElement('div');
                    contextListWrapper.className = 'wound-context-list';

                    sampleWounds.forEach(wound => {
                        const contextHTML = createWoundContextInput(q, wound.wound_id, wound.label, true);
                        const contextBlock = document.createElement('div');
                        contextBlock.innerHTML = contextHTML;
                        contextListWrapper.appendChild(contextBlock);
                    });
                    questionWrapper.appendChild(contextListWrapper);

                } else {
                    const inputId = `preview_${q.question_id}_NULL`;
                    const mainLabel = document.createElement('label');
                    mainLabel.className = 'block text-sm font-medium text-gray-700';
                    mainLabel.htmlFor = inputId;
                    mainLabel.textContent = q.question_text;
                    questionWrapper.appendChild(mainLabel);

                    const inputHTML = createInputHTML(q, 'NULL', true);
                    const inputWrapper = document.createElement('div');
                    inputWrapper.innerHTML = inputHTML;
                    questionWrapper.appendChild(inputWrapper);
                }

                currentSection.appendChild(questionWrapper);
            });

            if (questions.length === 0) {
                 fullPreviewContent.innerHTML = '<p class="text-center p-8 text-gray-500">No active questions found for this user.</p>';
            }

            lucide.createIcons();
        }


        // Initial load
        fetchQuestions();
        lucide.createIcons();
    });
</script>

<?php
require_once 'templates/footer.php';
?>