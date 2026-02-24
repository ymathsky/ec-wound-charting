<?php
// Filename: api/update_chat_schema_typing.php
require_once '../db_connect.php';

// Create table for typing status
// We use MEMORY engine if possible for speed, but InnoDB is fine for persistence/safety
$sql = "CREATE TABLE IF NOT EXISTS `chat_typing_status` (
    `user_id` int(11) NOT NULL,
    `room_id` int(11) NOT NULL,
    `last_typed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`user_id`, `room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table chat_typing_status created successfully.<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

$conn->close();
?>
