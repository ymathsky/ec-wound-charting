<?php
// Filename: api/check_procedure_tables.php
require_once '../db_connect.php';

$tables = ['superbill_services', 'cpt_codes'];
$results = [];

foreach ($tables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows > 0) {
        $results[$table] = "Exists";
        // Get columns
        $cols = $conn->query("SHOW COLUMNS FROM $table");
        $columns = [];
        while ($row = $cols->fetch_assoc()) {
            $columns[] = $row['Field'] . " (" . $row['Type'] . ")";
        }
        $results[$table . '_columns'] = $columns;
    } else {
        $results[$table] = "MISSING";
    }
}

header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
