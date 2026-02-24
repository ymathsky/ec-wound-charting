<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE patient_medications");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error describing table: " . $conn->error;
}
?>