<?php
require_once 'db_connect.php';

$sql = "CREATE TABLE IF NOT EXISTS `visit_ai_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `sender` enum('user','ai') NOT NULL,
  `message` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `appointment_idx` (`appointment_id`),
  KEY `patient_idx` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql) === TRUE) {
    echo "Table visit_ai_messages created successfully";
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>