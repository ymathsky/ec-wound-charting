<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE patient_medications");
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Table not found or error: " . $conn->error;
}
?>