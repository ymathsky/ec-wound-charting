<?php
// Filename: api/search_cpt.php
// Purpose: Search the cpt_codes table for matching codes or descriptions.

session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['ec_user_id'])) {
    echo json_encode([]);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (strlen($term) < 2) {
    echo json_encode([]); // Require at least 2 chars to search
    exit;
}

try {
    // Search by code (starts with) OR description (contains)
    // Limit to 20 results for performance
    $sql = "SELECT id, code, description, fee 
            FROM cpt_codes 
            WHERE code LIKE ? OR description LIKE ? 
            ORDER BY code ASC 
            LIMIT 20";

    $stmt = $conn->prepare($sql);

    $likeTerm = "%" . $term . "%";
    $codeStart = $term . "%"; // Prioritize codes starting with term

    // Actually, let's just use %term% for both for simplicity, or specific logic
    // Your request implies a general search.
    $stmt->bind_param("ss", $codeStart, $likeTerm);

    $stmt->execute();
    $result = $stmt->get_result();

    $results = [];
    while ($row = $result->fetch_assoc()) {
        $results[] = [
            'id' => $row['id'],
            'code' => $row['code'],
            'label' => $row['code'] . ' - ' . $row['description'], // For UI display
            'description' => $row['description'],
            'fee' => $row['fee']
        ];
    }

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>