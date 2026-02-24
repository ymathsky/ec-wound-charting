<?php
// Filename: api/get_suggestions.php
// NEW API: Fetches all *active* clinical suggestions for the visit notes modal.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // Select all active suggestions, grouped by category
    $sql = "SELECT category, suggestion_text 
            FROM clinical_suggestions 
            WHERE is_active = 1 
            ORDER BY category, display_order, suggestion_text";

    $result = $conn->query($sql);

    $grouped_suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $grouped_suggestions[$row['category']][] = $row['suggestion_text'];
    }

    echo json_encode(['success' => true, 'suggestions' => $grouped_suggestions]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>