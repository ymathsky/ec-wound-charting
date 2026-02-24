<?php
// Filename: view_users.php

session_start();
require_once 'db_connect.php';
// Redirect non-admin users or users who are not logged in
if (!isset($_SESSION['ec_user_id']) || $_SESSION['ec_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Ensure output buffering is started for reliable header redirection, even on included files
if (ob_get_level() === 0) ob_start();

// Include header template
require_once 'templates/header.php';
?>

    <div class="flex h-screen bg-gray-100 font-sans">
        <?php require_once 'templates/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- START: UPDATED HEADER STYLE -->
            <header class="w-full bg-white p-4 flex justify-between items-center sticky top-0 z-10 shadow-lg border-b border-indigo-100">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center">
                        <i data-lucide="shield" class="w-7 h-7 mr-3 text-indigo-600"></i>
                        Manage System Users
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Add, edit, and control access for all application users.</p>
                </div>
                <!-- Button to open the Add User modal -->
                <button id="openAddUserModalBtn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 px-6 rounded-xl flex items-center transition transform hover:scale-105 shadow-md">
                    <i data-lucide="user-plus" class="w-5 h-5 mr-2"></i>
                    Add New User
                </button>
            </header>
            <!-- END: UPDATED HEADER STYLE -->

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-8">

                <div class="bg-white p-6 rounded-xl shadow-xl border border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 mb-6 border-b pb-3 flex items-center">
                        <i data-lucide="users" class="w-5 h-5 mr-2 text-blue-500"></i>
                        All Registered Users
                    </h3>

                    <!-- User Search and Filter -->
                    <div class="mb-4 flex space-x-4">
                        <input type="text" id="userSearchInput" placeholder="Filter by Name, Email, or Role..." class="w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition">
                        <select type="text" id="roleFilter" class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition">
                            <option value="all">All Roles</option>
                            <!-- ROLES FROM DATABASE ENUM -->
                            <option value="admin">Admin</option>
                            <option value="clinician">Clinician</option>
                            <option value="scheduler">Scheduler</option>
                            <option value="facility">Facility</option>
                        </select>
                        <select type="text" id="statusFilter" class="px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition">
                            <option value="all">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- User Table Container -->
                    <div class="overflow-x-auto rounded-lg shadow-inner">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                            <tr>
                                <!-- Increased header font size: text-xs -> text-sm -->
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Full Name</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody id="userTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows populated by JavaScript -->
                            <tr><td colspan="5" class="text-center p-6 text-gray-500">Loading users...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Loading/Error Messages -->
                    <div id="userMessage" class="text-center mt-4 text-sm text-gray-500"></div>
                </div>

            </main>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 1. ADD NEW USER MODAL -->
    <!-- ========================================================================= -->
    <div id="addUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg m-4 transform transition-all">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h4 class="text-2xl font-bold text-gray-900">Add New User</h4>
                <button id="closeAddUserModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="addUserForm">
                <div id="addUserMessage" class="mb-4 hidden p-3 rounded-lg text-sm"></div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label for="fullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="fullName" id="fullName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label for="newEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="newEmail" id="newEmail" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label for="newPassword" class="block text-sm font-medium text-gray-700 mb-1">Password (Default)</label>
                    <input type="password" name="newPassword" id="newPassword" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Users should be instructed to change this immediately.</p>
                </div>

                <div class="mt-4">
                    <label for="newUserRole" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="newUserRole" id="newUserRole" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="clinician">Clinician</option>
                        <option value="scheduler">Scheduler</option>
                        <option value="facility">Facility</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelAddUserBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" id="saveUserBtn" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                        <i data-lucide="save" class="w-4 h-4 mr-2 inline-block"></i>
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- END ADD NEW USER MODAL -->


    <!-- ========================================================================= -->
    <!-- 2. EDIT USER MODAL (New Structure) -->
    <!-- ========================================================================= -->
    <div id="editUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
        <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-lg m-4 transform transition-all">
            <div class="flex justify-between items-center border-b pb-3 mb-4">
                <h4 class="text-2xl font-bold text-gray-900">Edit User Profile</h4>
                <button id="closeEditUserModalBtn" class="text-gray-400 hover:text-gray-600">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <form id="editUserForm">
                <input type="hidden" name="user_id" id="editUserId">
                <div id="editUserMessage" class="mb-4 hidden p-3 rounded-lg text-sm"></div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2 sm:col-span-1">
                        <label for="editFullName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="full_name" id="editFullName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label for="editEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="editEmail" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label for="editUserRole" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" id="editUserRole" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="clinician">Clinician</option>
                        <option value="scheduler">Scheduler</option>
                        <option value="facility">Facility</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <!-- Optional Password Change -->
                <div class="mt-4 border-t pt-4">
                    <label for="editNewPassword" class="block text-sm font-medium text-gray-700 mb-1">New Password (Optional)</label>
                    <input type="password" name="new_password" id="editNewPassword" placeholder="Leave blank to keep current password" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-red-500 focus:border-red-500">
                    <p class="text-xs text-gray-500 mt-1">If set, the user's password will be updated immediately.</p>
                </div>

                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" id="cancelEditUserBtn" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                    <button type="submit" id="updateUserBtn" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition">
                        <i data-lucide="check-circle" class="w-4 h-4 mr-2 inline-block"></i>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- END EDIT USER MODAL -->


    <!-- Ensure Lucide icons are available -->
    <script>
        document.write('<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></sc' + 'ript>');
    </script>

    <script>
        // Global variable to hold the complete user list
        let allUsers = [];
        const userTableBody = document.getElementById('userTableBody');
        const userMessage = document.getElementById('userMessage');

        // Add New User Modal elements
        const addUserModal = document.getElementById('addUserModal');
        const addUserForm = document.getElementById('addUserForm');
        const addUserMessage = document.getElementById('addUserMessage');

        // Edit User Modal elements
        const editUserModal = document.getElementById('editUserModal');
        const editUserForm = document.getElementById('editUserForm');
        const editUserMessage = document.getElementById('editUserMessage');

        // -----------------------------------------------------------
        // UTILITY FUNCTIONS
        // -----------------------------------------------------------

        // Function to map status to a badge style
        function getStatusBadge(status) {
            if (status === 'active') {
                // Updated text size to match table font
                return '<span class="px-3 py-1 inline-flex text-base leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>';
            }
            // Updated text size to match table font
            return '<span class="px-3 py-1 inline-flex text-base leading-5 font-semibold rounded-full bg-red-100 text-red-800">Inactive</span>';
        }

        // Function to map role to a color-coded badge style
        function getRoleBadge(role) {
            const roleUpper = role.toUpperCase();
            // Base classes: Increased font size to text-base
            let classes = 'px-3 py-1 inline-flex text-base leading-5 font-semibold rounded-full border';

            switch (role) {
                case 'admin':
                    classes += ' bg-blue-100 text-blue-800 border-blue-300';
                    break;
                case 'clinician':
                    classes += ' bg-cyan-100 text-cyan-800 border-cyan-300';
                    break;
                case 'scheduler':
                    classes += ' bg-indigo-100 text-indigo-800 border-indigo-300';
                    break;
                case 'facility':
                    classes += ' bg-amber-100 text-amber-800 border-amber-300';
                    break;
                default:
                    classes += ' bg-gray-100 text-gray-800 border-gray-300';
                    break;
            }
            return `<span class="${classes}">${roleUpper}</span>`;
        }

        // Modal helpers for Add User
        function showAddUserModal() {
            addUserModal.classList.remove('hidden');
            addUserForm.reset();
            addUserMessage.classList.add('hidden');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function hideAddUserModal() {
            addUserModal.classList.add('hidden');
        }

        function displayAddUserMessage(type, message) {
            addUserMessage.textContent = message;
            addUserMessage.classList.remove('hidden', 'bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800');
            if (type === 'error') {
                addUserMessage.classList.add('bg-red-100', 'text-red-800');
            } else if (type === 'success') {
                addUserMessage.classList.add('bg-green-100', 'text-green-800');
            }
        }

        // Modal helpers for Edit User
        function showEditUserModal() {
            editUserModal.classList.remove('hidden');
            editUserMessage.classList.add('hidden');
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function hideEditUserModal() {
            editUserModal.classList.add('hidden');
        }

        function displayEditUserMessage(type, message) {
            editUserMessage.textContent = message;
            editUserMessage.classList.remove('hidden', 'bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800');
            if (type === 'error') {
                editUserMessage.classList.add('bg-red-100', 'text-red-800');
            } else if (type === 'success') {
                editUserMessage.classList.add('bg-green-100', 'text-green-800');
            }
        }

        // -----------------------------------------------------------
        // DATA FETCHING
        // -----------------------------------------------------------

        async function fetchUsers() {
            userTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-6 text-gray-500 animate-pulse">Loading users...</td></tr>';
            userMessage.textContent = '';

            try {
                const response = await fetch('api/get_all_users.php');

                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status} (${response.statusText})`);
                }

                const data = await response.json();

                if (data.success && data.users) {
                    allUsers = data.users;
                    applyFiltersAndRenderTable();
                } else {
                    userTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-6 text-red-500">API Data Error: Received invalid data or ' + (data.message || 'database error') + '</td></tr>';
                    userMessage.textContent = 'Data check failed. ' + (data.message || 'No user accounts found.');
                }

            } catch (error) {
                console.error('Error fetching users:', error);
                userTableBody.innerHTML = `<tr><td colspan="5" class="text-center p-6 text-red-500">Connection Error: ${error.message}</td></tr>`;
                userMessage.textContent = `Failed to connect or process data from API. Please check 'api/get_all_users.php'.`;
            }
        }

        // -----------------------------------------------------------
        // FILTERING AND RENDERING
        // -----------------------------------------------------------

        function applyFiltersAndRenderTable() {
            const searchTerm = document.getElementById('userSearchInput').value.toLowerCase();
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;

            const filteredUsers = allUsers.filter(user => {
                const matchesSearch = user.full_name.toLowerCase().includes(searchTerm) ||
                    user.email.toLowerCase().includes(searchTerm) ||
                    user.role.toLowerCase().includes(searchTerm);

                const matchesRole = roleFilter === 'all' || user.role === roleFilter;
                const matchesStatus = statusFilter === 'all' || user.status === statusFilter;

                return matchesSearch && matchesRole && matchesStatus;
            });

            renderTable(filteredUsers);
        }

        function renderTable(users) {
            if (users.length === 0) {
                userTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-6 text-gray-500">No users match the current filter criteria.</td></tr>';
                userMessage.textContent = 'Total Users: ' + allUsers.length;
                return;
            }

            const rowsHtml = users.map(user => `
            <tr class="hover:bg-gray-50 transition duration-150" data-user-id="${user.user_id}" data-full-name="${user.full_name}" data-email="${user.email}" data-role="${user.role}">
                <!-- Increased table font size: text-sm -> text-base -->
                <td class="px-6 py-4 whitespace-nowrap text-base font-medium text-gray-900">${user.full_name}</td>
                <td class="px-6 py-4 whitespace-nowrap text-base text-gray-600">${user.email}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${getRoleBadge(user.role)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center">${getStatusBadge(user.status)}</td>
                <td class="px-6 py-4 whitespace-nowrap text-center text-base font-medium space-x-2">
                <!-- Action: Edit Profile - NOW OPENS MODAL -->
                <button type="button" title="Edit User" data-action="edit" data-user-id="${user.user_id}"
                class="edit-user-btn inline-flex items-center text-blue-600 hover:text-blue-800 p-1 rounded-full transition">
                <i data-lucide="edit" class="w-5 h-5"></i>
                </button>

                <!-- Action: Toggle Status (Activate/Deactivate) -->
                <button type="button" title="${user.status === 'active' ? 'Deactivate User' : 'Activate User'}"
                data-action="toggle-status" data-user-id="${user.user_id}" data-current-status="${user.status}"
                class="status-toggle-btn p-1 rounded-full transition
                ${user.status === 'active' ? 'text-red-600 hover:text-white hover:bg-red-500' : 'text-green-600 hover:text-white hover:bg-green-500'}">
                <i data-lucide="${user.status === 'active' ? 'lock' : 'unlock'}" class="w-5 h-5"></i>
                </button>

                <!-- Action: Delete User -->
                <button type="button" title="Delete User"
                data-action="delete" data-user-id="${user.user_id}"
                class="delete-user-btn text-gray-400 hover:text-red-600 p-1 rounded-full transition">
                <i data-lucide="trash-2" class="w-5 h-5"></i>
                </button>
                </td>
                </tr>
                `).join('');

        userTableBody.innerHTML = rowsHtml;
        userMessage.textContent = `Showing ${users.length} of ${allUsers.length} total users.`;

        // Re-create lucide icons after rendering
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        attachActionListeners();
    }

    // -----------------------------------------------------------
    // EVENT LISTENERS
    // -----------------------------------------------------------

    // Attach listeners for filter/search changes
    document.getElementById('userSearchInput').addEventListener('input', applyFiltersAndRenderTable);
    document.getElementById('roleFilter').addEventListener('change', applyFiltersAndRenderTable);
    document.getElementById('statusFilter').addEventListener('change', applyFiltersAndRenderTable);

    // Add User Modal listeners
    document.getElementById('openAddUserModalBtn').addEventListener('click', showAddUserModal);
    document.getElementById('closeAddUserModalBtn').addEventListener('click', hideAddUserModal);
    document.getElementById('cancelAddUserBtn').addEventListener('click', hideAddUserModal);
    document.getElementById('addUserForm').addEventListener('submit', handleAddUserSubmit);

    // Edit User Modal listeners
    document.getElementById('closeEditUserModalBtn').addEventListener('click', hideEditUserModal);
    document.getElementById('cancelEditUserBtn').addEventListener('click', hideEditUserModal);
    document.getElementById('editUserForm').addEventListener('submit', handleUpdateUserSubmit);


    function attachActionListeners() {
        // Edit button listener
        document.querySelectorAll('.edit-user-btn').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const userId = button.dataset.userId;
                const user = allUsers.find(u => u.user_id == userId);
                if (user) {
                    populateEditForm(user);
                    showEditUserModal();
                }
            });
        });

        // Status Toggle listener
        document.querySelectorAll('.status-toggle-btn').forEach(button => {
            button.onclick = (e) => {
                e.preventDefault();
                const userId = button.dataset.userId;
                const currentStatus = button.dataset.currentStatus;
                if (currentStatus === 'active') {
                    showCustomConfirm('Deactivate User', 'Are you sure you want to deactivate this user? They will not be able to log in.', () => toggleUserStatus(userId, 'inactive'));
                } else {
                    showCustomConfirm('Activate User', 'Are you sure you want to activate this user?', () => toggleUserStatus(userId, 'active'));
                }
            };
        });

        // Delete button listener
        document.querySelectorAll('.delete-user-btn').forEach(button => {
            button.onclick = (e) => {
                e.preventDefault();
                const userId = button.dataset.userId;
                showCustomConfirm('Delete User', 'WARNING: Are you absolutely sure you want to permanently delete this user?', () => deleteUser(userId));
            };
        });
    }

    function populateEditForm(user) {
        document.getElementById('editUserId').value = user.user_id;
        document.getElementById('editFullName').value = user.full_name;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editUserRole').value = user.role;
        document.getElementById('editNewPassword').value = ''; // Always clear password field
        displayEditUserMessage('', ''); // Clear message
    }


    // -----------------------------------------------------------
    // API ACTIONS
    // -----------------------------------------------------------

    async function handleAddUserSubmit(e) {
        e.preventDefault();
        const saveButton = document.getElementById('saveUserBtn');
        saveButton.disabled = true;
        saveButton.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 inline-block animate-spin"></i> Creating...';
        lucide.createIcons();

        const formData = new FormData(addUserForm);

        // Explicitly map JavaScript names (camelCase) to PHP/Database names (snake_case)
        const userData = {
            full_name: formData.get('fullName'),
            email: formData.get('newEmail'),
            password: formData.get('newPassword'),
            role: formData.get('newUserRole')
        };

        // Client-side validation: Check for empty fields (redundant due to 'required' but good fail-safe)
        if (!userData.full_name || !userData.email || !userData.password || !userData.role) {
             displayAddUserMessage('error', 'Incomplete user data. All fields are required.');
             saveButton.disabled = false;
             saveButton.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2 inline-block"></i> Create User';
             lucide.createIcons();
             return;
        }


        try {
            const response = await fetch('api/create_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(userData)
            });
            const result = await response.json();

            if (result.success) {
                displayAddUserMessage('success', 'User created successfully! The list will now refresh.');
                setTimeout(hideAddUserModal, 1500);
                await fetchUsers();
            } else {
                displayAddUserMessage('error', result.message || 'Failed to create user. Check the server logs.');
            }
        } catch (error) {
            console.error('API Error:', error);
            displayAddUserMessage('error', 'A network error occurred while attempting to create the user.');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i data-lucide="save" class="w-4 h-4 mr-2 inline-block"></i> Create User';
            lucide.createIcons();
        }
    }

    async function handleUpdateUserSubmit(e) {
        e.preventDefault();
        const updateButton = document.getElementById('updateUserBtn');
        updateButton.disabled = true;
        updateButton.innerHTML = '<i data-lucide="loader-circle" class="w-4 h-4 mr-2 inline-block animate-spin"></i> Saving...';
        lucide.createIcons();

        const formData = new FormData(editUserForm);

        // Explicitly map fields. Only include new_password if provided.
        const userData = {
            user_id: formData.get('user_id'),
            full_name: formData.get('full_name'),
            email: formData.get('email'),
            role: formData.get('role'),
        };

        const newPassword = formData.get('new_password');
        if (newPassword) {
             userData.new_password = newPassword;
        }

        try {
            // Note: This API endpoint likely expects an 'action' parameter for its logic
            const response = await fetch('api/update_user_info_on_mange_user_view.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(userData)
            });
            const result = await response.json();

            if (result.success) {
                displayEditUserMessage('success', 'User profile updated successfully! Refreshing list...');
                setTimeout(hideEditUserModal, 1500);
                await fetchUsers();
            } else {
                displayEditUserMessage('error', result.message || 'Failed to update user profile. Check the server logs.');
            }
        } catch (error) {
            console.error('API Error:', error);
            displayEditUserMessage('error', 'A network error occurred while attempting to update the user.');
        } finally {
            updateButton.disabled = false;
            updateButton.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4 mr-2 inline-block"></i> Save Changes';
            lucide.createIcons();
        }
    }

    async function toggleUserStatus(userId, newStatus) {
        // Find the user row and display a temporary message
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (row) {
            row.style.opacity = '0.5'; // Visual feedback for processing
        }

        try {
            const response = await fetch('api/manage_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', user_id: userId, status: newStatus })
            });
            const result = await response.json();

            if (result.success) {
                // Success: Refresh the user list
                await fetchUsers();
                userMessage.textContent = `User status updated successfully to ${newStatus}.`;
            } else {
                alert(`Error updating user status: ${result.message || 'Unknown error.'}`);
            }
        } catch (error) {
            console.error('API Error:', error);
            alert('A network error occurred while attempting to update user status.');
        } finally {
            if (row) {
                row.style.opacity = '1';
            }
        }
    }

    async function deleteUser(userId) {
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (row) {
            row.style.opacity = '0.5';
        }

        try {
            const response = await fetch('api/manage_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', user_id: userId })
            });
            const result = await response.json();

            if (result.success) {
                // Success: Refresh the user list
                await fetchUsers();
                userMessage.textContent = `User deleted successfully.`;
            } else {
                alert(`Error deleting user: ${result.message || 'Unknown error.'}`);
            }
        } catch (error) {
            console.error('API Error:', error);
            alert('A network error occurred while attempting to delete the user.');
        } finally {
             if (row) {
                row.style.opacity = '1';
            }
        }
    }

    // -----------------------------------------------------------
    // CUSTOM CONFIRM MODAL (Replaces banned alert/confirm)
    // -----------------------------------------------------------

    // Create necessary modal HTML once
    const customConfirmModalHtml = `
                <div id="customModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden" aria-modal="true" role="dialog">
                <div class="bg-white p-6 rounded-xl shadow-2xl w-full max-w-sm m-4 transform transition-all">
                <h4 id="modalTitle" class="text-xl font-bold text-gray-900 mb-3"></h4>
                <p id="modalBody" class="text-gray-700 mb-6"></p>
                <div class="flex justify-end space-x-3">
                <button type="button" id="modalCancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                <button type="button" id="modalConfirm" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition">Confirm</button>
                </div>
                </div>
                </div>
                `;
    document.body.insertAdjacentHTML('beforeend', customConfirmModalHtml);

    let modalConfirmCallback = () => {};

    function showCustomConfirm(title, message, callback) {
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').textContent = message;
        modalConfirmCallback = callback;
        document.getElementById('customModal').classList.remove('hidden');
    }

    document.getElementById('modalCancel').addEventListener('click', () => {
        document.getElementById('customModal').classList.add('hidden');
    });

    document.getElementById('modalConfirm').addEventListener('click', () => {
        document.getElementById('customModal').classList.add('hidden');
        modalConfirmCallback();
    });


    // -----------------------------------------------------------
    // INITIALIZATION
    // -----------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
        fetchUsers();
    });
</script>

<?php
// Include footer template
require_once 'templates/footer.php';
// Flush the output buffer and send content to the browser
ob_end_flush();
?>