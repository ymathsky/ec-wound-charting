<?php
// Filename: api/setup_chat_db.php
// Description: Ensures the database schema is ready for the SQL-based chat.

require_once '../db_connect.php';

echo "Checking database schema for Chat System...\n";

// 1. Check if 'read_at' column exists in 'chat_messages'
$check_col = $conn->query("SHOW COLUMNS FROM chat_messages LIKE 'read_at'");
if ($check_col->num_rows == 0) {
    echo "Adding 'read_at' column to 'chat_messages' table...\n";
    $sql = "ALTER TABLE chat_messages ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at";
    if ($conn->query($sql) === TRUE) {
        echo "Success: 'read_at' column added.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "'read_at' column already exists.\n";
}

// 2. Check if 'chat_rooms' table exists (it should from the dump, but good to verify)
$check_table = $conn->query("SHOW TABLES LIKE 'chat_rooms'");
if ($check_table->num_rows == 0) {
    echo "Creating 'chat_rooms' table...\n";
    $sql = "CREATE TABLE `chat_rooms` (
      `room_id` int(11) NOT NULL AUTO_INCREMENT,
      `user1_id` int(11) NOT NULL,
      `user2_id` int(11) NOT NULL,
      `last_message_at` timestamp NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`room_id`),
      UNIQUE KEY `unique_room` (`user1_id`,`user2_id`),
      KEY `fk_chat_user1` (`user1_id`),
      KEY `fk_chat_user2` (`user2_id`),
      CONSTRAINT `fk_chat_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `fk_chat_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if ($conn->query($sql) === TRUE) {
        echo "Success: 'chat_rooms' table created.\n";
    } else {
        echo "Error creating table: " . $conn->error . "\n";
    }
} else {
    echo "'chat_rooms' table already exists.\n";
}

echo "Database setup complete.\n";
?>
