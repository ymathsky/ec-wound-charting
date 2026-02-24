<?php
// Filename: api/update_chat_schema_v2.php
require_once '../db_connect.php';

// Add is_deleted column
$sql1 = "ALTER TABLE chat_messages ADD COLUMN is_deleted TINYINT(1) DEFAULT 0";
if ($conn->query($sql1) === TRUE) {
    echo "Column is_deleted added successfully.<br>";
} else {
    echo "Error adding is_deleted: " . $conn->error . "<br>";
}

// Add edited_at column
$sql2 = "ALTER TABLE chat_messages ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL";
if ($conn->query($sql2) === TRUE) {
    echo "Column edited_at added successfully.<br>";
} else {
    echo "Error adding edited_at: " . $conn->error . "<br>";
}

$conn->close();
?>
