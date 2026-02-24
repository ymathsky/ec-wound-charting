<?php
// Filename: api/get_cpt_codes.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    $sql = "SELECT code, description, category FROM cpt_codes ORDER BY category, code ASC";
    $result = $conn->query($sql);

    // Explicitly check for query failure
    if ($result === false) {
        // This will be caught by the catch block below
        throw new Exception("Database query failed: " . $conn->error);
    }

    $codes = $result->fetch_all(MYSQLI_ASSOC);

    // Group by category
    $grouped_codes = [];
    foreach ($codes as $code) {
        // Ensure category exists and is not null before grouping
        $category = $code['category'] ?? 'General';
        $grouped_codes[$category][] = $code;
    }

    http_response_code(200);
    echo json_encode($grouped_codes);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "An error occurred while fetching CPT codes.", "error" => $e->getMessage()]);
}

$conn->close();
?>
