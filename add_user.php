<?php
// Filename: add_user.php
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    header("Location: todays_visit.php");
    exit();
}
require_once 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Create New User</h1>
                <p class="text-sm text-gray-600">Enter the new user's details and assign a role.</p>
            </div>
        </header>
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="max-w-2xl mx-auto">
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <div id="form-message" class="hidden p-3 mb-4 rounded-md"></div>
                    <form id="addUserForm" class="space-y-6">
                        <div>
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" required class="form-input">
                        </div>

                        <div>
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" required class="form-input">
                        </div>

                        <div>
                            <label for="password" class="form-label">Password</label>
                            <input type="password" name="password" id="password" required class="form-input">
                        </div>

                        <div>
                            <label for="role" class="form-label">Role</label>
                            <select name="role" id="role" required class="form-input bg-white">
                                <option value="clinician">Clinician</option>
                                <option value="admin">Administrator</option>
                                <option value="facility">Facility/Portal User</option>
                            </select>
                        </div>

                        <div class="pt-4">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md transition">
                                Create User Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('addUserForm');
        const messageDiv = document.getElementById('form-message');

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            const userData = Object.fromEntries(new FormData(form).entries());

            try {
                const response = await fetch('api/create_user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(userData)
                });
                const result = await response.json();

                if (!response.ok) throw new Error(result.message);

                messageDiv.textContent = result.message;
                messageDiv.className = 'p-3 mb-4 rounded-md bg-green-100 text-green-800';
                messageDiv.classList.remove('hidden');
                form.reset();

                setTimeout(() => {
                    window.location.href = 'view_users.php';
                }, 1500);

            } catch (error) {
                messageDiv.textContent = `Error: ${error.message}`;
                messageDiv.className = 'p-3 mb-4 rounded-md bg-red-100 text-red-800';
                messageDiv.classList.remove('hidden');
            }
        });
    });
</script>

<?php
require_once 'templates/footer.php';
?>
