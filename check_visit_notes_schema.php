<?php
require_once 'db_connect.php';

$table = 'visit_notes';
$result = $conn->query("DESCRIBE $table");

echo "Table: $table\n";
echo str_pad("Field", 20) . str_pad("Type", 20) . str_pad("Null", 6) . str_pad("Key", 6) . str_pad("Default", 20) . "\n";
echo str_repeat("-", 80) . "\n";

while ($row = $result->fetch_assoc()) {
    echo str_pad($row['Field'], 20) . 
         str_pad($row['Type'], 20) . 
         str_pad($row['Null'], 6) . 
         str_pad($row['Key'], 6) . 
         str_pad($row['Default'] ?? 'NULL', 20) . "\n";
}

echo "\nIndexes:\n";
$index_res = $conn->query("SHOW INDEX FROM $table");
while ($row = $index_res->fetch_assoc()) {
    echo "Key_name: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
}
?>
