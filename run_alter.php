<?php
require_once 'db_connect.php';
try {
    $conn->query("ALTER TABLE soap_checklist_items ADD COLUMN title VARCHAR(255) DEFAULT NULL AFTER category");
    echo "Column added successfully";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>