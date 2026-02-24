<?php
require_once 'db_connect.php';

try {
    // Change soap_section to VARCHAR(50) to allow any section name
    $sql = "ALTER TABLE soap_checklist_items MODIFY COLUMN soap_section VARCHAR(50) NOT NULL";
    
    if ($conn->query($sql) === TRUE) {
        echo "Successfully updated 'soap_section' column to VARCHAR(50).\n";
    } else {
        echo "Error updating column: " . $conn->error . "\n";
    }

    // Optional: Update any empty records that might have failed previously if you know what they should be.
    // For now, we just fix the schema.

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$conn->close();
?>
