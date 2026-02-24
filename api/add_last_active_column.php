<?php
require_once '../db_connect.php';

try {
    $sql = "ALTER TABLE users ADD COLUMN last_active_at TIMESTAMP NULL DEFAULT NULL";
    if ($conn->query($sql) === TRUE) {
        echo "Column last_active_at added successfully";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>