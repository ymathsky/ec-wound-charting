<?php
require_once '../db_connect.php';

echo "Fixing database schema...\n";

// Fix chat_rooms
echo "Modifying chat_rooms.room_id to AUTO_INCREMENT...\n";
$sql = "ALTER TABLE chat_rooms MODIFY room_id INT(11) NOT NULL AUTO_INCREMENT";
if ($conn->query($sql) === TRUE) {
    echo "Success: chat_rooms.room_id is now AUTO_INCREMENT.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

// Fix chat_messages
echo "Modifying chat_messages.message_id to AUTO_INCREMENT...\n";
$sql = "ALTER TABLE chat_messages MODIFY message_id INT(11) NOT NULL AUTO_INCREMENT";
if ($conn->query($sql) === TRUE) {
    echo "Success: chat_messages.message_id is now AUTO_INCREMENT.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "Done.\n";
?>