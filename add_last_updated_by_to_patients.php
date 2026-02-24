<?php
require 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the column already exists
$result = $conn->query("SHOW COLUMNS FROM `patients` LIKE 'last_updated_by'");
if ($result->num_rows > 0) {
    echo "Column 'last_updated_by' already exists in 'patients' table.\n";
} else {
    // Add the column after insurance_group_number
    $sql_add_column = "ALTER TABLE `patients` ADD `last_updated_by` INT(11) NULL DEFAULT NULL AFTER `insurance_group_number`";
    if ($conn->query($sql_add_column) === TRUE) {
        echo "Column 'last_updated_by' added successfully.\n";
    } else {
        die("Error adding column: " . $conn->error . "\n");
    }
}

// Check if the foreign key already exists
$fk_check_sql = "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = 'patients' AND COLUMN_NAME = 'last_updated_by' AND REFERENCED_TABLE_NAME = 'users';";
$fk_result = $conn->query($fk_check_sql);

if ($fk_result->num_rows > 0) {
    echo "Foreign key 'fk_patients_last_updated_by' already exists.\n";
} else {
    // Add the foreign key constraint
    $sql_add_fk = "ALTER TABLE `patients` ADD CONSTRAINT `fk_patients_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL";
    if ($conn->query($sql_add_fk) === TRUE) {
        echo "Foreign key 'fk_patients_last_updated_by' added successfully.\n";
    } else {
        echo "Error adding foreign key constraint: " . $conn->error . "\n";
    }
}

$conn->close();
?>
