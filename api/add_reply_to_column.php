<?php
require_once '../db_connect.php';

try {
    // Check if column exists first
    $check = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'reply_to_id'");
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE chat_messages ADD COLUMN reply_to_id INT NULL DEFAULT NULL AFTER room_id";
        if ($conn->query($sql) === TRUE) {
            echo "Column reply_to_id added successfully";
        } else {
            echo "Error adding column: " . $conn->error;
        }
    } else {
        echo "Column reply_to_id already exists";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>