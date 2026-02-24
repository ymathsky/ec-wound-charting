<?php
require_once 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM appointments");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>