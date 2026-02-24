<?php
require_once 'db_connect.php';

$sql = "ALTER TABLE visit_notes ADD COLUMN procedure_note TEXT DEFAULT NULL AFTER plan";

if ($conn->query($sql) === TRUE) {
    echo "Column procedure_note added successfully";
} else {
    echo "Error adding column: " . $conn->error;
}

$conn->close();
?>
