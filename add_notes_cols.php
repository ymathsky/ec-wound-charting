<?php
require_once 'db_connect.php';
try {
    $conn->query("ALTER TABLE visit_notes ADD COLUMN lab_orders TEXT");
    $conn->query("ALTER TABLE visit_notes ADD COLUMN imaging_orders TEXT");
    $conn->query("ALTER TABLE visit_notes ADD COLUMN skilled_nurse_orders TEXT");
    echo "Columns added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>