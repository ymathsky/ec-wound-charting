<?php
require_once 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM wound_assessments");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>