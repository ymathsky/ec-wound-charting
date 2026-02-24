<?php
require_once 'db_connect.php';

function executeQuery($conn, $sql, $description) {
    if ($conn->query($sql) === TRUE) {
        echo "[SUCCESS] $description\n";
    } else {
        // Ignore "Duplicate column" errors if we run this multiple times
        if (strpos($conn->error, "Duplicate column") !== false || strpos($conn->error, "already exists") !== false) {
             echo "[INFO] $description (Already exists)\n";
        } else {
            echo "[ERROR] $description: " . $conn->error . "\n";
        }
    }
}

echo "--- Starting Schema Update for Note Versioning ---\n";

// 1. Update visit_notes table
$sql = "ALTER TABLE visit_notes 
        ADD COLUMN status ENUM('draft', 'finalized') DEFAULT 'draft',
        ADD COLUMN finalized_at DATETIME NULL,
        ADD COLUMN finalized_by INT NULL";
executeQuery($conn, $sql, "Adding status, finalized_at, finalized_by to visit_notes");

// 2. Create visit_note_addendums table
$sql = "CREATE TABLE IF NOT EXISTS visit_note_addendums (
    addendum_id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT NOT NULL,
    user_id INT NOT NULL,
    note_text TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (appointment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
executeQuery($conn, $sql, "Creating visit_note_addendums table");

echo "--- Schema Update Complete ---\n";
?>
