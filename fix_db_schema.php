<?php
require_once 'db_connect.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Database Migration Tool</h2>";

// Check if column exists
$check_sql = "SHOW COLUMNS FROM wound_assessments LIKE 'assessment_type'";
$result = $conn->query($check_sql);

if ($result && $result->num_rows > 0) {
    echo "<p style='color:green'>Column 'assessment_type' already exists in 'wound_assessments'.</p>";
} else {
    echo "<p>Column 'assessment_type' missing. Attempting to add...</p>";
    
    $alter_sql = "ALTER TABLE wound_assessments ADD COLUMN assessment_type VARCHAR(50) DEFAULT 'Post-Debridement'";
    
    if ($conn->query($alter_sql) === TRUE) {
        echo "<p style='color:green'>Successfully added 'assessment_type' column.</p>";
    } else {
        echo "<p style='color:red'>Error adding column: " . $conn->error . "</p>";
    }
}

echo "<p>Done.</p>";
?>