<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE visit_notes");
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>