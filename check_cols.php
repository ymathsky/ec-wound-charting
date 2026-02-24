<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE wound_assessments");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>