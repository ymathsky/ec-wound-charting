<?php
require_once 'db_connect.php';

$sql = file_get_contents('create_clinician_templates_table.sql');

if ($conn->multi_query($sql)) {
    echo "Table 'clinician_templates' created successfully (or already exists).";
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error creating table: " . $conn->error;
}

$conn->close();
?>
