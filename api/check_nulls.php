<?php
require_once '../db_connect.php';
$res = $conn->query("SHOW COLUMNS FROM chat_messages");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " " . $row['Null'] . "\n";
}
?>