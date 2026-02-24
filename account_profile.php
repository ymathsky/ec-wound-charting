<?php
// Filename: ec/account_profile.php
session_start(); // ADDED: Ensure session is started before accessing session variables
require_once 'templates/header.php';
require_once 'db_connect.php';
?>

    <div class="flex h-screen bg-gray-100">
        <?php require_once 'templates/sidebar.php'; ?>

        <div class="flex-1 flex flex-col overflow-hidden main-content">
            <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">My Profile</h1>
                    <p class="text-sm text-gray-600">Manage your profile, credentials, and security settings.</p>
                </div>
                <button id="sidebar-toggle" class="text-gray-600 hover:text-gray-800 focus:outline-none">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </header>

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
                <div class="max-w-4xl mx-auto">
                    <div id="form-message" class="hidden p-3 mb-4 rounded-md"></div>

                    <div class="bg-white rounded-xl shadow-2xl overflow-hidden p-8 space-y-8">

                        <!-- Profile Header Section (New Layout) -->
                        <div class="flex flex-col md:flex-row items-center border-b pb-6 space-y-4 md:space-y-0 md:space-x-6">

                            <!-- Profile Picture -->
                            <div id="profile-img-container" class="relative group w-32 h-32 flex-shrink-0">
                                <img id="profile-img-display" class="w-full h-full object-cover rounded-full border-4 border-blue-100 shadow-md"
                                     src="https://placehold.co/128x128/9CA3AF/FFFFFF?text=User"
                                     alt="Profile Picture">
                                <label for="profile-picture-upload" class="absolute inset-0 bg-black bg-opacity-30 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition duration-300 cursor-pointer">
                                    <i data-lucide="camera" class="w-6 h-6 text-white"></i>
                                </label>
                                <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/png, image/jpeg" class="hidden">
                                <div id="upload-status" class="absolute -bottom-6 w-full text-center text-xs pt-1"></div>

                                <!-- Remove Picture Button -->
                                <button id="remove-picture-btn" type="button" class="absolute -right-2 -top-2 bg-red-500 hover:bg-red-600 text-white p-1 rounded-full shadow-md w-8 h-8 flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 transform scale-0 group-hover:scale-100" title="Remove Profile Picture">
                                    <i data-lucide="x" class="w-4 h-4"></i>
                                </button>
                            </div>

                            <!-- User Info and Type -->
                            <div class="text-center md:text-left">
                                <h2 id="user-full-name-display" class="text-3xl font-bold text-gray-900">Loading Name...</h2>
                                <p id="user-type-display" class="text-blue-600 font-semibold text-lg">Loading Role...</p>
                                <p id="user-email-display" class="text-gray-500 text-sm">Loading Email...</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                            <!-- Column 1: Personal & Credentials -->
                            <div class="space-y-6">

                                <!-- Update Personal Information & Credentials -->
                                <form id="updateProfileForm">
                                    <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Core Information</h3>
                                    <div class="space-y-4">
                                        <div>
                                            <label for="full_name" class="form-label">Full Name</label>
                                            <input type="text" name="full_name" id="full_name" required class="form-input">
                                        </div>
                                        <div>
                                            <label for="email" class="form-label">Email Address</label>
                                            <input type="email" name="email" id="email" required class="form-input">
                                        </div>
                                        <!-- Credentials Field -->
                                        <div>
                                            <label for="credentials" class="form-label">Professional Credentials / Background</label>
                                            <textarea name="credentials" id="credentials" rows="3" class="form-input" placeholder="e.g., M.D., Wound Care Specialist, 15 years experience"></textarea>
                                        </div>
                                        <div class="flex justify-end pt-2">
                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-md transition disabled:opacity-50" data-original-text="Save Changes">
                                                Save Changes
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- Column 2: Security & Password -->
                            <div class="space-y-6">

                                <!-- Change Password -->
                                <form id="updatePasswordForm">
                                    <h3 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Security Settings</h3>
                                    <div class="space-y-4">
                                        <!-- Current Password -->
                                        <div class="relative">
                                            <label for="current_password" class="form-label">Current Password</label>
                                            <input type="password" name="current_password" id="current_password" required class="form-input pr-10">
                                            <span class="absolute right-3 top-8 cursor-pointer text-gray-500 hover:text-gray-700" onclick="togglePasswordVisibility('current_password')">
                                            <i data-lucide="eye" class="w-5 h-5 toggle-icon"></i>
                                        </span>
                                        </div>
                                        <!-- New Password -->
                                        <div class="relative">
                                            <label for="new_password" class="form-label">New Password (Min 8 characters)</label>
                                            <input type="password" name="new_password" id="new_password" required class="form-input pr-10">
                                            <span class="absolute right-3 top-8 cursor-pointer text-gray-500 hover:text-gray-700" onclick="togglePasswordVisibility('new_password')">
                                            <i data-lucide="eye" class="w-5 h-5 toggle-icon"></i>
                                        </span>
                                            <p id="password-strength-message" class="text-xs mt-1"></p>
                                        </div>
                                        <!-- Confirm Password -->
                                        <div class="relative">
                                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                                            <input type="password" name="confirm_password" id="confirm_password" required class="form-input pr-10">
                                            <span class="absolute right-3 top-8 cursor-pointer text-gray-500 hover:text-gray-700" onclick="togglePasswordVisibility('confirm_password')">
                                            <i data-lucide="eye" class="w-5 h-5 toggle-icon"></i>
                                        </span>
                                        </div>

                                        <div class="flex justify-end pt-2">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-6 rounded-md transition disabled:opacity-50" data-original-text="Update Password">
                                                Update Password
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Load Lucide icons for JS manipulation -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            lucide.createIcons();

            const profileForm = document.getElementById('updateProfileForm');
            const passwordForm = document.getElementById('updatePasswordForm');
            const uploadInput = document.getElementById('profile-picture-upload');
            const removeBtn = document.getElementById('remove-picture-btn');
            const messageDiv = document.getElementById('form-message');
            const imgDisplay = document.getElementById('profile-img-display');
            const uploadStatusDiv = document.getElementById('upload-status');

            const defaultImageUrl = 'https://placehold.co/128x128/9CA3AF/FFFFFF?text=User';

            /**
             * Displays status messages (success/error) to the user.
             */
            function showMessage(message, type, target = messageDiv) {
                target.textContent = message;
                target.className = 'p-3 mb-4 rounded-md';

                if (target === messageDiv) {
                    target.classList.add('p-3', 'mb-4', 'rounded-md', 'border');
                } else {
                    // For smaller status messages like image upload
                    target.classList.remove('hidden', 'bg-red-100', 'text-red-800', 'bg-green-100', 'text-green-800', 'text-gray-600');
                }

                if (type === 'error') {
                    target.classList.add('bg-red-100', 'text-red-800', 'border-red-400');
                } else if (type === 'success') {
                    target.classList.add('bg-green-100', 'text-green-800', 'border-green-400');
                } else {
                    target.classList.add('text-gray-600', 'text-sm'); // For loading/info
                }
                target.classList.remove('hidden');

                // Auto-hide main messages after 5 seconds
                if (target === messageDiv) {
                    setTimeout(() => {
                        target.classList.add('hidden');
                    }, 5000);
                }
            }

            /**
             * Clears the message div.
             */
            function clearMessage(target = messageDiv) {
                target.classList.add('hidden');
                target.textContent = '';
            }

            /**
             * Toggles the visibility of the remove button based on the current image.
             */
            function toggleRemoveButton(imageUrl) {
                if (imageUrl && imageUrl !== defaultImageUrl) {
                    removeBtn.classList.remove('opacity-0', 'scale-0');
                } else {
                    removeBtn.classList.add('opacity-0', 'scale-0');
                }
            }

            /**
             * Fetches and pre-fills user details upon page load.
             */
            async function fetchUserDetails() {
                try {
                    const response = await fetch('api/get_user_details.php');
                    if (response.status === 401) {
                        showMessage('Session expired. Please log in again.', 'error');
                        return;
                    }

                    const user = await response.json();
                    if (!response.ok) {
                        throw new Error(user.message || 'Failed to retrieve user details.');
                    }

                    // Fill form inputs
                    document.getElementById('full_name').value = user.full_name || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('credentials').value = user.credentials || '';

                    // Fill display fields
                    document.getElementById('user-full-name-display').textContent = user.full_name || 'User Profile';
                    document.getElementById('user-type-display').textContent = user.user_type || 'User';
                    document.getElementById('user-email-display').textContent = user.email || '';

                    // Set profile picture and update remove button visibility
                    const finalImageUrl = user.profile_image_url && user.profile_image_url !== 'null'
                        ? user.profile_image_url
                        : defaultImageUrl;
                    imgDisplay.src = finalImageUrl;
                    imgDisplay.onerror = () => { imgDisplay.src = defaultImageUrl; }; // Fallback
                    toggleRemoveButton(finalImageUrl);

                } catch (error) {
                    showMessage(`Initialization error: ${error.message}`, 'error');
                }
            }

            /**
             * Main handler for form submissions (Profile Update and Password Change).
             */
            async function handleSubmit(e, formId) {
                e.preventDefault();
                clearMessage(); // Clear old main message on new submission

                const form = document.getElementById(formId);
                const submitButton = form.querySelector('button[type="submit"]');
                const originalText = submitButton.getAttribute('data-original-text');

                // Disable button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = `<span class="animate-spin inline-block mr-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9 9 0 0 0-9 9m9-9v2"></path></svg></span>${formId === 'updateProfileForm' ? 'Saving...' : 'Updating...'}`;

                let endpoint = '';
                let data = {};

                if (formId === 'updateProfileForm') {
                    endpoint = 'api/update_user_profile.php';
                    data = Object.fromEntries(new FormData(form).entries());
                } else if (formId === 'updatePasswordForm') {
                    endpoint = 'api/update_password.php';
                    const newPassword = document.getElementById('new_password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;

                    if (newPassword !== confirmPassword) {
                        showMessage('New passwords do not match. Please try again.', 'error');
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                        return;
                    }

                    if (newPassword.length < 8) {
                        showMessage('New password must be at least 8 characters long.', 'error');
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                        return;
                    }

                    data = Object.fromEntries(new FormData(form).entries());
                }

                try {
                    const response = await fetch(endpoint, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'An error occurred during update.');
                    }

                    showMessage(result.message, 'success');

                    if (formId === 'updateProfileForm') {
                        // Update display fields instantly
                        document.getElementById('user-full-name-display').textContent = data.full_name;
                        document.getElementById('user-email-display').textContent = data.email;
                    }

                    if (formId === 'updatePasswordForm') {
                        form.reset(); // Clear fields after successful password change
                    }

                } catch (error) {
                    showMessage(`Update failed: ${error.message}`, 'error');
                } finally {
                    // Re-enable button and restore original text
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            }

            /**
             * Handles the profile picture file upload.
             */
            async function handleProfilePictureUpload() {
                const file = uploadInput.files[0];
                if (!file) return;

                // Simple client-side check
                if (file.size > 5 * 1024 * 1024) {
                    showMessage('File size limit exceeded (5MB).', 'error', uploadStatusDiv);
                    return;
                }

                clearMessage(uploadStatusDiv);
                showMessage('Uploading...', 'info', uploadStatusDiv);

                const formData = new FormData();
                formData.append('profile_picture', file);

                try {
                    const response = await fetch('api/upload_profile_picture.php', {
                        method: 'POST',
                        body: formData // Note: fetch handles Content-Type for FormData automatically
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Upload failed.');
                    }

                    // Update image source on success and show remove button
                    imgDisplay.src = result.image_url;
                    toggleRemoveButton(result.image_url);

                    showMessage('Picture updated!', 'success', uploadStatusDiv);
                    setTimeout(() => clearMessage(uploadStatusDiv), 3000);

                } catch (error) {
                    showMessage(`Upload error: ${error.message}`, 'error', uploadStatusDiv);
                    setTimeout(() => clearMessage(uploadStatusDiv), 5000);
                } finally {
                    uploadInput.value = ''; // Clear file input
                }
            }

            /**
             * Handles removing the profile picture.
             */
            async function handleRemovePicture() {
                if (!confirm("Are you sure you want to remove your profile picture?")) {
                    return;
                }

                clearMessage();
                removeBtn.disabled = true;
                showMessage('Removing picture...', 'info', uploadStatusDiv);

                try {
                    const response = await fetch('api/delete_profile_picture.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({})
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Failed to remove picture.');
                    }

                    // Update image source to default and hide remove button
                    imgDisplay.src = defaultImageUrl;
                    toggleRemoveButton(defaultImageUrl);

                    showMessage('Picture successfully removed.', 'success', uploadStatusDiv);
                    setTimeout(() => clearMessage(uploadStatusDiv), 3000);

                } catch (error) {
                    showMessage(`Removal error: ${error.message}`, 'error', uploadStatusDiv);
                    setTimeout(() => clearMessage(uploadStatusDiv), 5000);
                } finally {
                    removeBtn.disabled = false;
                }
            }

            /**
             * Toggles the visibility of a password input field.
             */
            window.togglePasswordVisibility = function(fieldId) {
                const input = document.getElementById(fieldId);
                const iconContainer = input.nextElementSibling;
                if (!iconContainer) return;

                const icon = iconContainer.querySelector('i');

                if (input.type === 'password') {
                    input.type = 'text';
                    icon.setAttribute('data-lucide', 'eye-off');
                } else {
                    input.type = 'password';
                    icon.setAttribute('data-lucide', 'eye');
                }
                lucide.createIcons();
            };


            // Attach event listeners
            profileForm.addEventListener('submit', (e) => handleSubmit(e, 'updateProfileForm'));
            passwordForm.addEventListener('submit', (e) => handleSubmit(e, 'updatePasswordForm'));
            uploadInput.addEventListener('change', handleProfilePictureUpload);
            removeBtn.addEventListener('click', handleRemovePicture);

            // Initial data fetch
            fetchUserDetails();
        });
    </script>

<?php
require_once 'templates/footer.php';
?>