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
    <title>EC Wound Charting - Login</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .login-bg {
            background-image: url('https://image.pollinations.ai/prompt/Female%20doctor%20in%20blue%20scrubs%20using%20a%20holographic%20interface%20to%20examine%20a%20wound%20on%20a%20patient\'s%20leg%20in%20a%20modern%20clinic%20at%20night%2C%20futuristic%20medical%20equipment%2C%20glowing%20blue%20light%2C%20cityscape%20background%2C%20detailed%20holograms%2C%20high-tech%20wound%20care?width=1200&height=800&seed=44');
            background-size: cover;
            background-position: center;
        }
    </style>
</head>
<body class="bg-gray-100">

<div class="min-h-screen flex">
    <div class="hidden lg:flex w-1/2 login-bg relative items-center justify-center">
        <div class="absolute bg-black opacity-60 inset-0 z-0"></div>
        <div class="w-full max-w-md z-10 text-center relative">
            <h1 class="text-5xl font-bold text-white tracking-wider">EC Wound Charting</h1>
            <p class="text-gray-300 mt-4 text-lg">Advanced. Efficient. Patient-Centered.</p>

        </div>
    </div>

    <div class="w-full lg:w-1/2 flex items-center justify-center bg-white p-8">
        <div class="w-full max-w-md">
            <div class="text-center mb-10">
                <img src="logo.png" alt="EC Wound Charting Logo" class="mx-auto h-24 w-auto">
            </div>

            <div class="text-center lg:text-left mb-10">
                <h2 class="text-3xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-gray-600 mt-2">Please sign in to access your account.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-md" role="alert">
                    <p class="font-bold">Login Failed</p>
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="mt-6">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" id="email" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" id="password" required
                           class="mt-1 block w-full px-4 py-3 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Sign In
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>

