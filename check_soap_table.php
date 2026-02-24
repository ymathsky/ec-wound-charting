<?php
require_once 'db_connect.php';
$result = $conn->query("DESCRIBE soap_checklist_items");
while($row = $result->fetch_assoc()) {
    print_r($row);
}
?>