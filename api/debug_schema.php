<?php
require_once '../db_connect.php';

function checkTable($tableName, $conn) {
    echo "--- Table: $tableName ---\n";
    $res = $conn->query("DESCRIBE $tableName");
    if ($res) {
        while($row = $res->fetch_assoc()) {
            echo $row['Field'] . " | " . $row['Type'] . " | " . $row['Key'] . " | " . $row['Extra'] . "\n";
        }
    } else {
        echo "Error describing table: " . $conn->error . "\n";
    }
    echo "\n";
}

checkTable('chat_messages', $conn);
checkTable('chat_rooms', $conn);
?>