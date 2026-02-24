<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE visit_vitals");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?>