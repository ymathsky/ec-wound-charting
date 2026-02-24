<?php
require 'db_connect.php';
$res = $conn->query('DESCRIBE visit_notes');
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
