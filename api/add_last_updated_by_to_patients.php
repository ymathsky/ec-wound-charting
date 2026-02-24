<?php
require_once '../db_connect.php';

header('Content-Type: text/plain;');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Starting database migration...\n\n";

// 1. Add the 'last_updated_by' column
$column_check_sql = "SHOW COLUMNS FROM `patients` LIKE 'last_updated_by'";
$result = $conn->query($column_check_sql);

if ($result->num_rows > 0) {
    echo "SUCCESS: Column 'last_updated_by' already exists in 'patients' table.\n";
} else {
    $sql_add_column = "ALTER TABLE `patients` ADD `last_updated_by` INT(11) NULL DEFAULT NULL AFTER `insurance_group_number`, ADD INDEX `idx_last_updated_by` (`last_updated_by`)";
    if ($conn->query($sql_add_column) === TRUE) {
        echo "SUCCESS: Column 'last_updated_by' added successfully to 'patients' table.\n";
    } else {
        die("ERROR: Could not add column 'last_updated_by'. " . $conn->error . "\n");
    }
}

// 2. Add the foreign key constraint
$fk_check_sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'patients' AND CONSTRAINT_NAME = 'fk_patients_last_updated_by'";
$fk_result = $conn->query($fk_check_sql);

if ($fk_result->num_rows > 0) {
    echo "SUCCESS: Foreign key 'fk_patients_last_updated_by' already exists.\n";
} else {
    $sql_add_fk = "ALTER TABLE `patients` ADD CONSTRAINT `fk_patients_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
    if ($conn->query($sql_add_fk) === TRUE) {
        echo "SUCCESS: Foreign key 'fk_patients_last_updated_by' added successfully.\n";
    } else {
        echo "ERROR: Could not add foreign key. " . $conn->error . "\n";
        echo "Please ensure the 'users' table exists and there are no orphaned 'last_updated_by' values before retrying.\n";
    }
}

echo "\nMigration script finished.\n";

$conn->close();
?>
