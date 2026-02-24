<?php
require_once 'db_connect.php';
print_r($conn->query('DESCRIBE visit_diagnoses')->fetch_all(MYSQLI_ASSOC));
?>