<?php
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS `visit_drafts` (
  `draft_id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `draft_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `last_saved_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`draft_id`),
  UNIQUE KEY `appointment_idx` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table visit_drafts created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>