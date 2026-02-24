<?php
// Filename: templates/header_chat.php
// This file is based on your working ec/templates/header.php
// It opens the HTML and the main app wrapper.

// Session check
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['ec_user_id'])) {
    header("Location: login.php");
    exit();
}

// --- FIX: INCLUDE DB FOR PROFILE PIC ---
// We need the DB connection to fetch the user's profile picture.
// We include this first to ensure $conn is available.
include_once 'db_connect.php';

// Get user info from session
$user_id = $_SESSION['ec_user_id'];
$user_full_name = isset($_SESSION['ec_full_name']) ? htmlspecialchars($_SESSION['ec_full_name']) : 'User';
$user_role = isset($_SESSION['ec_role']) ? htmlspecialchars($_SESSION['ec_role']) : 'Role';

// --- FIX: Fetch Current User's Profile Picture ---
$user_profile_pic_url = null;
if ($conn) {
    // Uses the correct column name 'profile_image_url' from your users.sql
    $sql = "SELECT profile_image_url FROM users WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_profile_pic_url = $row['profile_image_url'];
        }
        $stmt->close();
    }
    // We don't close $conn here, as chat.php will need it.
}
// ------------------------------------


// --- "Bilingual" Environment Setup ---
// This code detects if it's on a local server or a production server.

// --- 1. Get Firebase Config ---
// UPDATED LOGIC: Default to local config, override if production var exists.
$is_production = defined('__firebase_config');

if ($is_production) {
    // --- PRODUCTION (LIVE) CONFIG ---
    $firebase_config_json = __firebase_config;
} else {
    // --- LOCAL (XAMPP) CONFIG ---
    // Use the hardcoded config object you provided.
    $firebase_config_json = '{
        "apiKey": "AIzaSyA7h9sK3B9p16BYU-gDvWdVxaVGjRuDUx0",
        "authDomain": "ec-wound-charting.firebaseapp.com",
        "projectId": "ec-wound-charting",
        "storageBucket": "ec-wound-charting.firebasestorage.app",
        "messagingSenderId": "1006211897893",
        "appId": "1:1006211897893:web:bbeedba694e943fcdd3dcc",
        "measurementId": "G-9B9D7F1B4T"
    }';
}

// --- 2. Get App ID ---
$app_id = $is_production ? (defined('__app_id') ? __app_id : 'default-app-id') : 'default-app-id';

// --- 3. Get Auth Token ---
$auth_token = $is_production ? (defined('__initial_auth_token') ? __initial_auth_token : '') : '';
// --- End Bilingual Setup ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EC Wound Charting - Chat</title>
    <link rel="shortcut icon" type="image/png" href="logo.png" />
    <!-- FIX: Use the correct v2 stylesheet link -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <style>
        /* Base styles from your main header */
        body { font-family: 'Inter', sans-serif; }
        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background-color: #ffffff;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            position: sticky;
            top: 0;
            z-index: 20;
        }
        
        /* Spinner */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #4F46E5;
            animation: spin 1s ease infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* --- NEW: Style for Unread Badge --- */
        .unread-badge {
            min-width: 24px;
            height: 24px;
            border-radius: 9999px;
            background-color: #EF4444; /* bg-red-500 */
            color: white;
            font-size: 0.75rem; /* 12px */
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 6px;
        }
    </style>
</head>
<body class="bg-gray-50">
<!-- BEGIN MAIN APP WRAPPER (flex h-screen) -->
<!-- This tag is opened here and closed in chat.php -->
<div id="app-wrapper" class="flex h-screen overflow-hidden">

    <!--
      The <aside> sidebar is included by `sidebar_chat.php`
      The main content column is created in `chat.php`
      This ensures they are direct siblings of #app-wrapper
    -->

    <!-- PHP Variables for Chat -->
    <script>
        window.PHP_USER_ID = <?php echo json_encode($user_id ?? null); ?>;
        window.PHP_USER_FULL_NAME = <?php echo json_encode($user_full_name); ?>;
        window.PHP_USER_PROFILE_PIC = <?php echo json_encode($user_profile_pic_url); ?>;
    </script>