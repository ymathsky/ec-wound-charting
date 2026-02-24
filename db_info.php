<?php
// db_info.php

// Include the database connection file
require 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all tables
$tables_result = $conn->query("SHOW TABLES");

if (!$tables_result) {
    die("Error getting tables: " . $conn->error);
}

echo "Database Tables:\n\n";

while ($table_row = $tables_result->fetch_row()) {
    $table_name = $table_row[0];
    echo "--------------------
";
    echo "Table: " . $table_name . "\n";
    echo "--------------------
";

    // Describe the table
    $describe_result = $conn->query("DESCRIBE `" . $table_name . "`");
    if ($describe_result) {
        // Print header
        echo str_pad("Field", 30) . str_pad("Type", 30) . str_pad("Null", 10) . str_pad("Key", 10) . str_pad("Default", 20) . "Extra\n";
        echo str_repeat("-", 120) . "\n";

        // Print rows
        while ($column_row = $describe_result->fetch_assoc()) {
            echo str_pad($column_row['Field'], 30) .
                 str_pad($column_row['Type'], 30) .
                 str_pad($column_row['Null'], 10) .
                 str_pad($column_row['Key'], 10) .
                 str_pad($column_row['Default'] ?? 'NULL', 20) .
                 $column_row['Extra'] . "\n";
        }
        $describe_result->free();
    } else {
        echo "Could not describe table: " . $conn->error . "\n";
    }
    echo "\n";
}

$tables_result->free();
$conn->close();

?>
