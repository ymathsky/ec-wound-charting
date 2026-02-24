<?php
require 'db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$describe_result = $conn->query("DESCRIBE `patients`");
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
$conn->close();
?>