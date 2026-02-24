<?php
require_once 'db_connect.php';
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    echo $row[0] . "\n";
}
?>