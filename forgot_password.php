<?php
// Filename: ec/forgot_password.php
// NOTE: Assuming there's no header/footer/sidebar required, just the login container style.
require_once 'db_connect.php'; // Required for database connection setup
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - EMR</title>
    <!-- Assuming Tailwind CSS is included in the main template or via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.15s ease-in-out;
        }
        .form-input:focus {
            border-color: #3b82f6;
            ring: 3px;
            outline: none;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="w-full max-w-md">
    <div class="bg-white rounded-xl shadow-2xl overflow-hidden p-8 space-y-6">
        <div class="text-center">
            <!-- Assuming logo exists at ec/logo.png -->
            <img src="logo.png" alt="Logo" class="mx-auto h-12 w-auto mb-4">
            <h2 class="text-2xl font-bold text-gray-900">Reset Your Password</h2>
            <p class="text-sm text-gray-500 mt-1">Enter your email address and we'll send you a link to reset your password.</p>
        </div>

        <div id="form-message" class="hidden p-3 rounded-md"></div>

        <form id="forgotPasswordForm" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                <input type="email" name="email" id="email" required class="form-input" placeholder="you@example.com">
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition disabled:opacity-50" data-original-text="Send Reset Link">
                Send Reset Link
            </button>
        </form>

        <div class="text-center text-sm">
            <a href="login.php" class="text-blue-600 hover:text-blue-500 font-medium">Back to Login</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        lucide.createIcons();

        const form = document.getElementById('forgotPasswordForm');
        const messageDiv = document.getElementById('form-message');
        const submitButton = form.querySelector('button[type="submit"]');

        function showMessage(message, type) {
            messageDiv.textContent = message;
            messageDiv.className = 'p-3 rounded-md border';
            if (type === 'error') {
                messageDiv.classList.add('bg-red-100', 'text-red-800', 'border-red-400');
            } else {
                messageDiv.classList.add('bg-green-100', 'text-green-800', 'border-green-400');
            }
            messageDiv.classList.remove('hidden');
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            messageDiv.classList.add('hidden');

            const email = document.getElementById('email').value;

            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="animate-spin inline-block mr-2"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9 9 0 0 0-9 9m9-9v2"></path></svg></span>Sending...`;

            try {
                const response = await fetch('api/send_reset_link.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });

                const result = await response.json();

                if (!response.ok) {
                    throw new Error(result.message || 'Failed to send reset link.');
                }

                // Show success message, regardless of whether the email exists (for security)
                showMessage(result.message, 'success');
                form.reset();

            } catch (error) {
                // Show a generic, safe error message to prevent account enumeration
                showMessage('An error occurred. Please try again later.', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = submitButton.getAttribute('data-original-text');
            }
        });
    });
</script>
</body>
</html>