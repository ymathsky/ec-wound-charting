<?php
require_once 'db_connect.php';

echo "Database: " . $db_name . "\n";
echo "Host: " . $db_host . "\n";

$tables = ['superbill_services', 'cpt_codes'];

foreach ($tables as $table) {
    echo "Checking table $table... ";
    $check = $conn->query("DESCRIBE $table");
    if ($check) {
        echo "OK\n";
    } else {
        echo "MISSING or ERROR: " . $conn->error . "\n";
    }
}
?>