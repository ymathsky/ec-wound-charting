<?php
require_once '../db_connect.php';

// Check if column exists
$check = $conn->query("SHOW COLUMNS FROM appointments LIKE 'check_in_time'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE appointments ADD COLUMN check_in_time DATETIME NULL DEFAULT NULL AFTER status";
    if ($conn->query($sql)) {
        echo "Column check_in_time added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column check_in_time already exists.";
}
?>