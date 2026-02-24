<?php
require_once '../db_connect.php';

echo "Tables in database:\n";
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

echo "\nColumns in chat_messages:\n";
$res = $conn->query("SHOW COLUMNS FROM chat_messages");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
