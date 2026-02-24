<?php
// Filename: api/get_quick_snippets.php
// Fetches clinical suggestions grouped by category for quick insertion into notes.

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

try {
    // Fetch all active clinical suggestions
    $sql = "SELECT category, suggestion_text AS item_text 
            FROM clinical_suggestions 
            WHERE is_active = 1
            ORDER BY category, display_order, suggestion_text";

    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();

    $grouped_items = [];
    while ($row = $result->fetch_assoc()) {
        // Group items by category (General, Orders, Referrals, etc.)
        $grouped_items[$row['category']][] = $row['item_text'];
    }

    echo json_encode(['success' => true, 'items' => $grouped_items]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>