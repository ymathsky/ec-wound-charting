<?php
require_once '../db_connect.php';
$res = $conn->query("DESCRIBE audit_log");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . " | " . $row['Type'] . "\n";
    }
} else {
    echo "Table audit_log not found or error.";
}
?>