<?php
// Filename: user_manual.php

require_once 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100">
    <?php require_once 'templates/sidebar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="w-full bg-white p-4 flex justify-between items-center shadow-md">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">User Manual</h1>
                <p class="text-sm text-gray-600">A complete guide to using the EC Wound Charting system.</p>
            </div>
        </header>
        <main class="flex-1 flex flex-col overflow-hidden bg-gray-100 p-6">
            <!-- The iframe container will grow to fill the available space -->
            <div class="flex-grow bg-white rounded-lg shadow-lg overflow-hidden">
                <iframe src="https://app.guidemaker.com/guide/8c0b7cdd-61c8-4063-9ef6-13f950946be5?layout=PAGED" width="100%" height="100%" frameborder="0">
                </iframe>
            </div>
        </main>
    </div>
</div>

<?php
require_once 'templates/footer.php';
?>
