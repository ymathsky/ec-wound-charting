<?php
require_once 'db_connect.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM visit_notes LIKE 'live_note'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE visit_notes ADD COLUMN live_note LONGTEXT DEFAULT NULL AFTER plan";
    if ($conn->query($sql)) {
        echo "Column 'live_note' added successfully to visit_notes table.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'live_note' already exists.";
}
?>