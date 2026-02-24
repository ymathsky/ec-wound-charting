<?php
require_once 'db_connect.php';
$r = $conn->query("SHOW TABLES");
if ($r) {
    while($row = $r->fetch_row()) {
        echo $row[0] . "\n";
    }
}
?>