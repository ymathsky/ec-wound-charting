<?php
// Simple test to verify modal mode
require_once 'templates/header.php';
?>

<div class="p-8">
    <h1 class="text-2xl font-bold mb-4">Modal Mode Test</h1>
    <div class="bg-blue-100 p-4 rounded">
        <p><strong>Current URL:</strong> <?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?></p>
        <p><strong>Layout Parameter:</strong> <?php echo isset($_GET['layout']) ? htmlspecialchars($_GET['layout']) : 'NOT SET'; ?></p>
        <p><strong>Is MDI Mode:</strong> <?php echo $is_mdi_mode ? 'YES' : 'NO'; ?></p>
    </div>
    
    <div class="mt-4">
        <a href="test_modal_mode.php" class="text-blue-600 hover:underline">Regular Link</a> |
        <a href="test_modal_mode.php?layout=modal" class="text-blue-600 hover:underline">With ?layout=modal</a>
    </div>
</div>

<?php
require_once 'templates/sidebar.php';
require_once 'templates/footer.php';
?>
