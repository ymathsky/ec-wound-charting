<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal Login | EC Wound Charting</title>
    <link rel="stylesheet" href="css/portal.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
<div class="login-container">
    <div class="login-card">
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="bg-indigo-100 p-3 rounded-full">
                    <i data-lucide="activity" class="w-8 h-8 text-indigo-600"></i>
                </div>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Patient Portal</h1>
            <p class="text-sm text-gray-500 mt-2">Securely access your wound care progress</p>
        </div>

        <div id="error-message" class="hidden bg-red-50 text-red-700 p-3 rounded-md text-sm mb-4 text-center border border-red-200"></div>

        <form id="loginForm" class="space-y-4">
            <div>
                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-input" placeholder="e.g. Smith" required>
            </div>

            <div>
                <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                <input type="date" id="date_of_birth" name="date_of_birth" class="form-input" required>
            </div>

            <button type="submit" class="btn-primary mt-4">
                Access My Records
            </button>
        </form>

        <p class="text-xs text-center text-gray-400 mt-6">
            &copy; <?php echo date("Y"); ?> EC Wound Charting. All rights reserved.
        </p>
    </div>
</div>

<script>
    lucide.createIcons();

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button');
        const msg = document.getElementById('error-message');

        btn.disabled = true;
        btn.innerHTML = '<i data-lucide="loader-circle" class="w-5 h-5 mr-2 animate-spin"></i> Verifying...';
        lucide.createIcons();
        msg.classList.add('hidden');

        const formData = {
            last_name: document.getElementById('last_name').value,
            date_of_birth: document.getElementById('date_of_birth').value
        };

        try {
            const res = await fetch('api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            const data = await res.json();

            if (res.ok && data.success) {
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Login failed');
            }
        } catch (error) {
            msg.textContent = error.message;
            msg.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = 'Access My Records';
            lucide.createIcons();
        }
    });
</script>
</body>
</html>