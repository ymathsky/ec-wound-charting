<?php
require_once 'db_connect.php';
$result = $conn->query("SHOW COLUMNS FROM users");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>