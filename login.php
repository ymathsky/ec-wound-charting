<?php
// Filename: login.php

session_start();
require_once 'db_connect.php'; // --- ADDED: Requires db_connect.php to get $conn ---
require_once 'audit_log_function.php';

// --- Redirect if already logged in ---
// If the user is already logged in, redirect them to the dashboard.
if (isset($_SESSION['ec_user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// --- Include Database Connection ---
// Note: db_connect.php is already included above
// require_once 'db_connect.php';
// require_once 'audit_log_function.php'; // <-- ADDED: Include the audit log helper

$error_message = '';

// --- Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both email and password.";
    } else {
        // --- Brute-Force Protection ---
        // Block IPs with 10+ failed attempts in the last 15 minutes.
        $client_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rate_limit  = 10;
        $window_mins = 15;
        $rate_stmt = $conn->prepare(
            "SELECT COUNT(*) as fail_count FROM audit_log
             WHERE action = 'LOGIN_FAIL'
               AND ip_address = ?
               AND created_at >= NOW() - INTERVAL ? MINUTE"
        );
        if ($rate_stmt) {
            $rate_stmt->bind_param("si", $client_ip, $window_mins);
            $rate_stmt->execute();
            $fail_count = $rate_stmt->get_result()->fetch_assoc()['fail_count'] ?? 0;
            $rate_stmt->close();
            if ($fail_count >= $rate_limit) {
                $error_message = "Too many failed attempts. Please wait $window_mins minutes before trying again.";
                // Skip further processing — just fall through to display the form
                goto show_form;
            }
        }
        // --- Prepare and Execute SQL Statement ---
        // Fetch the user from the database based on the email provided.
        $sql = "SELECT user_id, full_name, password_hash, role FROM users WHERE email = ? AND status = 'active' LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            // --- Verify Password ---
            // Use password_verify() to check if the submitted password matches the stored hash.
            if (password_verify($password, $user['password_hash'])) {
                // --- Prevent Session Fixation ---
                // Regenerate the session ID on successful login to prevent fixation attacks.
                session_regenerate_id(true);

                // --- Set Session Variables ---
                // Password is correct, so create session variables for the user.
                $_SESSION['ec_user_id'] = $user['user_id'];
                $_SESSION['ec_full_name'] = $user['full_name'];
                $_SESSION['ec_role'] = $user['role'];

                // --- ADDED ---
                $user_id = $user['user_id'];
                $user_name = $user['full_name'];
                // --- MODIFIED: Passed $conn as the first argument ---
                log_audit($conn, $user_id, $user_name, 'LOGIN', 'user', $user_id, "User '$user_name' logged in successfully.");
                // --- END MOD ---

                // Redirect to dashboard after login
                header("Location: dashboard.php");
                exit(); // Exit after a successful redirect

            } else {
                // --- THIS IS THE FIX: Invalid password ---
                $error_message = "Invalid email or password.";

                // --- Log failed login attempt (invalid password) ---
                // $email is already defined from $_POST
                log_audit($conn, 0, $email, 'LOGIN_FAIL', 'user', 0, "Failed login attempt for email '$email'. Invalid password.");
            }
        } else {
            // If email is not found or user is inactive
            $error_message = "Invalid email or password.";
            // --- ADDED: Log failed login (user not found) ---
            log_audit($conn, null, $email, 'LOGIN_FAIL', null, null, 'Failed login attempt: User not found or inactive.');
            // --- END ADD ---
        }
        // $stmt is closed here, or if an error occurred before successful redirect.
        if (isset($stmt) && method_exists($stmt, 'close')) {
            $stmt->close();
        }
    }
    // Final check for connection close
    if (isset($conn) && method_exists($conn, 'close')) {
        $conn->close(); // <<< FIX: Close connection after all processing in the POST block
    }
}
// Rate-limit goto target — execution continues here when brute-force limit is hit
show_form:
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EC Wound Charting — Sign In</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }

        .panel-bg {
            background-image: url('https://image.pollinations.ai/prompt/Female%20doctor%20in%20blue%20scrubs%20using%20a%20holographic%20interface%20to%20examine%20a%20wound%20on%20a%20patient%27s%20leg%20in%20a%20modern%20clinic%20at%20night%2C%20futuristic%20medical%20equipment%2C%20glowing%20blue%20light%2C%20cityscape%20background%2C%20detailed%20holograms%2C%20high-tech%20wound%20care?width=1200&height=800&seed=44');
            background-size: cover;
            background-position: center;
        }

        .input-field {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.5rem;
            background: #f8fafc;
            color: #1e293b;
            font-size: 0.9375rem;
            transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
            outline: none;
        }
        .input-field:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
        .input-field::placeholder { color: #94a3b8; }

        .btn-primary {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.8125rem 1rem;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 600;
            font-size: 0.9375rem;
            border-radius: 0.5rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s, box-shadow 0.15s;
            box-shadow: 0 4px 12px rgba(37,99,235,0.35);
        }
        .btn-primary:hover { opacity: 0.92; box-shadow: 0 6px 16px rgba(37,99,235,0.45); }
        .btn-primary:active { transform: scale(0.99); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            font-size: 0.8125rem;
            padding: 0.3125rem 0.75rem;
            border-radius: 9999px;
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex">

    <!-- Left decorative panel (desktop only) -->
    <div class="hidden lg:flex lg:w-1/2 panel-bg relative flex-col items-center justify-between py-14 px-12">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-950/80 via-blue-900/70 to-indigo-900/80"></div>

        <!-- Top logo -->
        <div class="relative z-10 self-start">
            <img src="logo.png" alt="EC Logo" class="h-10 w-auto opacity-90">
        </div>

        <!-- Center content -->
        <div class="relative z-10 text-center">
            <div class="flex justify-center gap-2 mb-6 flex-wrap">
                <span class="badge"><i data-lucide="shield-check" class="w-3.5 h-3.5"></i> HIPAA Compliant</span>
                <span class="badge"><i data-lucide="activity" class="w-3.5 h-3.5"></i> Real-Time Charting</span>
                <span class="badge"><i data-lucide="cpu" class="w-3.5 h-3.5"></i> AI-Powered</span>
            </div>
            <h1 class="text-4xl font-bold text-white leading-tight tracking-tight">
                EC Wound Charting
            </h1>
            <p class="text-blue-200 mt-3 text-lg font-light">Advanced. Efficient. Patient-Centered.</p>
        </div>

        <!-- Bottom tagline -->
        <p class="relative z-10 text-blue-300/70 text-xs">
            &copy; <?= date('Y') ?> EC Wound Charting. All rights reserved.
        </p>
    </div>

    <!-- Right login panel -->
    <div class="w-full lg:w-1/2 flex items-center justify-center bg-white p-6 sm:p-10">
        <div class="w-full max-w-sm">

            <!-- Mobile logo -->
            <div class="flex justify-center mb-8 lg:hidden">
                <img src="logo.png" alt="EC Wound Charting" class="h-16 w-auto">
            </div>

            <!-- Heading -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Welcome back</h2>
                <p class="text-slate-500 mt-1 text-sm">Sign in to your EC Wound Charting account.</p>
            </div>

            <!-- Error alert -->
            <?php if (!empty($error_message)): ?>
            <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3.5 rounded-lg" role="alert">
                <i data-lucide="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-semibold text-sm">Sign-in failed</p>
                    <p class="text-sm mt-0.5"><?= htmlspecialchars($error_message) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" action="login.php" id="loginForm" novalidate>

                <!-- Email -->
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1.5">Email address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </span>
                        <input
                            type="email"
                            name="email"
                            id="email"
                            required
                            autocomplete="email"
                            placeholder="you@example.com"
                            value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                            class="input-field pl-10"
                        >
                    </div>
                </div>

                <!-- Password -->
                <div class="mb-2">
                    <label for="password" class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </span>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="input-field pl-10 pr-11"
                        >
                        <button
                            type="button"
                            id="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                            aria-label="Toggle password visibility"
                        >
                            <i data-lucide="eye" class="w-4 h-4" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Forgot password -->
                <div class="flex justify-end mb-6">
                    <a href="forgot_password.php" class="text-xs text-blue-600 hover:text-blue-800 hover:underline font-medium">
                        Forgot password?
                    </a>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-primary" id="submitBtn">
                    <i data-lucide="log-in" class="w-4 h-4" id="btnIcon"></i>
                    <span id="btnText">Sign In</span>
                </button>
            </form>

            <!-- Footer note -->
            <p class="mt-8 text-center text-xs text-slate-400">
                Having trouble? Contact your administrator.
            </p>
        </div>
    </div>

<script>
    lucide.createIcons();

    // Password toggle
    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');

    toggleBtn.addEventListener('click', () => {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        eyeIcon.setAttribute('data-lucide', isHidden ? 'eye-off' : 'eye');
        lucide.createIcons();
    });

    // Loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const btnIcon = document.getElementById('btnIcon');
        btn.disabled = true;
        btnText.textContent = 'Signing in…';
        btnIcon.setAttribute('data-lucide', 'loader');
        btnIcon.style.animation = 'spin 1s linear infinite';
        lucide.createIcons();
    });
</script>
<style>
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
</body>
</html>

