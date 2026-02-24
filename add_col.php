<?php
require_once 'db_connect.php';
try {
    $conn->query("ALTER TABLE wound_assessments ADD COLUMN debridement_narrative TEXT");
    echo "Column added successfully.";
} catch (Exception $e) {
    echo "Error (might already exist): " . $e->getMessage();
}
?>