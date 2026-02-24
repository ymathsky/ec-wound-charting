<?php
require_once 'db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$columns = [
    "ADD COLUMN exposed_structures TEXT DEFAULT NULL",
    "ADD COLUMN risk_factors TEXT DEFAULT NULL",
    "ADD COLUMN nutritional_status TEXT DEFAULT NULL",
    "ADD COLUMN braden_score INT DEFAULT NULL",
    "ADD COLUMN push_score INT DEFAULT NULL",
    "ADD COLUMN pre_debridement_notes TEXT DEFAULT NULL",
    "ADD COLUMN medical_necessity TEXT DEFAULT NULL",
    "ADD COLUMN dvt_edema_notes TEXT DEFAULT NULL"
];

foreach ($columns as $col) {
    $sql = "ALTER TABLE wound_assessments $col";
    if ($conn->query($sql) === TRUE) {
        echo "Executed: $sql\n";
    } else {
        echo "Error executing $sql: " . $conn->error . "\n";
    }
}

echo "Schema update completed.\n";
?>
