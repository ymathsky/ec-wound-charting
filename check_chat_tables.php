<?php
// Quick test to see if chat tables exist
require_once 'db_connect.php';

echo "<h2>Chat Database Check</h2>";

// Check if tables exist
$tables_to_check = ['chat_rooms', 'chat_messages', 'chat_reactions', 'chat_read_status', 'chat_typing_status'];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        
        // Check columns
        $cols = $conn->query("DESCRIBE $table");
        if ($cols) {
            echo "<ul>";
            while ($col = $cols->fetch_assoc()) {
                echo "<li>{$col['Field']} ({$col['Type']})</li>";
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>✗ Table '$table' MISSING</p>";
    }
}

$conn->close();
?>
