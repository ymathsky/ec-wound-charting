<?php
// Filename: templates/header.php

// Start session if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, redirect to login page if not
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// --- Security Headers (sent before any HTML output) ---
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-XSS-Protection: 1; mode=block");

// Get user info from session for display
$user_full_name = isset($_SESSION['ec_full_name']) ? htmlspecialchars($_SESSION['ec_full_name']) : 'User';
$user_role = isset($_SESSION['ec_role']) ? htmlspecialchars($_SESSION['ec_role']) : 'Role';

// FIX: Ensure necessary variables are defined for the Firebase script block below
$user_id = isset($_SESSION['ec_user_id']) ? $_SESSION['ec_user_id'] : null;
$user_profile_pic_url = 'path/to/default/profile.png'; // Placeholder if needed
$app_id = 'default-app-id'; // Placeholder if not defined elsewhere
$auth_token = ''; // Placeholder if not defined elsewhere
$firebase_config_json = '{}'; // Placeholder if not defined elsewhere

// Assume these variables are populated elsewhere if needed, otherwise use placeholders

// MDI Mode Detection: Check if we're being loaded in a tab (modal layout)
$is_mdi_mode = isset($_GET['layout']) && $_GET['layout'] === 'modal';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EC Wound Charting</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />

    <!-- Standard jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Tailwind CSS (Development Build) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <!-- Driver.js for Onboarding Tour -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
    <script src="js/onboarding_tour.js"></script>

    <style>
        /* Base styles */
        body { font-family: 'Inter', sans-serif; }

        /* Custom styles for new table UI */
        .table-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            padding: 1.5rem;
        }

        .table-header {
            background-color: #1F2937; /* bg-gray-800 */
            color: white;
        }
        .table-header th {
            padding: 0.75rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .table-row {
            border-bottom: 1px solid #E5E7EB; /* border-gray-200 */
        }
        .table-row:hover {
            background-color: #F9FAFB; /* bg-gray-50 */
        }
        .table-cell {
            padding: 1rem 1.5rem;
            white-space: nowrap;
        }

        /* --- GLOBAL MOBILE OPTIMIZATION STYLES --- */
        /* 1. Form input global style for larger touch targets (48px min-height) */
        .form-input, .form-button {
            margin-top: 0.25rem;
            display: block;
            width: 100%;
            padding: 0.75rem 1rem; /* Increased padding for touch */
            border: 1px solid #D1D5DB;
            border-radius: 0.375rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            min-height: 48px; /* Standard touch target size */
            box-sizing: border-box;
        }
        .form-input:focus {
            outline: none;
            --tw-ring-color: #3B82F6; /* ring-blue-500 */
            box-shadow: 0 0 0 2px var(--tw-ring-color);
            border-color: #3B82F6;
        }
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151; /* text-gray-700 */
        }

        /* 2. Hide number input stepper arrows for cleaner mobile typing */
        /* Chrome, Safari, Edge, Opera */
        input[type="number"]::-webkit-outer-spin-button,
        input[type="number"]::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        /* Firefox */
        input[type="number"] {
            -moz-appearance: textfield;
        }

        /* 3. TOAST NOTIFICATION STYLES (for success/error feedback) */
        #toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.06);
            opacity: 0;
            transition: opacity 0.3s ease-in-out, bottom 0.3s ease-in-out;
            min-width: 250px;
            text-align: center;
        }
        #toast-notification.show {
            opacity: 1;
            bottom: 30px;
        }
        #toast-notification.success {
            background-color: #10B981; /* Green-500 */
        }
        #toast-notification.error {
            background-color: #EF4444; /* Red-500 */
        }
        #toast-notification.info {
            background-color: #3B82F6; /* Blue-500 */
        }

        /* Spinner for loading states */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #09f;
            animation: spin 1s ease infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <?php require_once __DIR__ . '/global_styles.php'; ?>
    
    <?php if ($is_mdi_mode): ?>
    <!-- MDI Navigation Helper (for tab interception) -->
    <script src="js/mdi_navigation.js?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
</head>

<body class="bg-gray-100<?php echo $is_mdi_mode ? ' mdi-embedded' : ''; ?>">

<!-- Global Toast Notification Container -->
<div id="toast-notification" aria-live="assertive" role="alert"></div>

<?php if ($is_mdi_mode): ?>
<!-- MDI Mode: Minimal wrapper for tab content -->
<style>
    body.mdi-embedded {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: auto;
    }
    /* Hide sidebar in MDI mode if it exists */
    body.mdi-embedded .sidebar,
    body.mdi-embedded #sidebar {
        display: none !important;
    }
</style>

<!-- Detect if we're in an iframe and hide sidebar automatically -->
<script>
(function() {
    // Check if we're inside an iframe
    if (window.self !== window.top) {
        // We're in an iframe - add class to body and hide sidebar
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('mdi-embedded');
            
            // Hide sidebar elements immediately
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.style.display = 'none';
                console.log('[Header] Sidebar hidden - page is in iframe');
            }
        });
    }
})();
</script>
<?php endif; ?>

<!-- jQuery and Bootstrap JS are already included in the head section without integrity checks to avoid CDN issues -->