<?php
// Filename: ec/api/get_suggestions_for_notes.php
// API Endpoint to fetch active clinical suggestions.
// Updated to handle Session issues and CORS for local development.

// Allow access from the same origin (and potential local dev variations)
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

require_once '../db_connect.php';

// Ensure session is started to read login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debugging: Log session state if needed (check your php error logs)
// error_log("Session User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not Set'));

// Check authentication
// We check for user_id OR ec_role to be consistent with visit_notes.php access control
if (!isset($_SESSION['user_id']) && !isset($_SESSION['ec_role'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized - Session not found',
        'debug_session' => session_id() ? 'active' : 'none'
    ]);
    exit();
}

try {
    // Select all active suggestions, ordered by category and then display order
    $sql = "SELECT category, suggestion_text 
            FROM clinical_suggestions 
            WHERE is_active = 1 
            ORDER BY category ASC, display_order ASC, suggestion_text ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception($conn->error);
    }

    $grouped_suggestions = [];

    while ($row = $result->fetch_assoc()) {
        $category = $row['category'];
        $text = $row['suggestion_text'];

        if (!isset($grouped_suggestions[$category])) {
            $grouped_suggestions[$category] = [];
        }

        // Avoid duplicates
        if (!in_array($text, $grouped_suggestions[$category])) {
            $grouped_suggestions[$category][] = $text;
        }
    }

    echo json_encode([
        'success' => true,
        'count' => $result->num_rows,
        'suggestions' => $grouped_suggestions
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>