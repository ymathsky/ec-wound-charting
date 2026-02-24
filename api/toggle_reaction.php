<?php
// Filename: api/toggle_reaction.php
ini_set('display_errors', 0);
session_start();
header('Content-Type: application/json');
require_once '../db_connect.php';

try {
    if (!isset($_SESSION['ec_user_id'])) {
        throw new Exception('Unauthorized', 401);
    }

    $user_id = $_SESSION['ec_user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    $message_id = isset($input['message_id']) ? intval($input['message_id']) : 0;
    $emoji = isset($input['emoji']) ? trim($input['emoji']) : '';

    if ($message_id <= 0 || empty($emoji)) {
        throw new Exception('Invalid input');
    }

    // Check if reaction exists
    $check_sql = "SELECT reaction_id FROM chat_reactions WHERE message_id = ? AND user_id = ? AND emoji = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("iis", $message_id, $user_id, $emoji);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Reaction exists, remove it (toggle off)
        $del_sql = "DELETE FROM chat_reactions WHERE reaction_id = ?";
        $del_stmt = $conn->prepare($del_sql);
        $del_stmt->bind_param("i", $row['reaction_id']);
        $del_stmt->execute();
        $action = 'removed';
    } else {
        // Reaction doesn't exist, add it (toggle on)
        $add_sql = "INSERT INTO chat_reactions (message_id, user_id, emoji) VALUES (?, ?, ?)";
        $add_stmt = $conn->prepare($add_sql);
        $add_stmt->bind_param("iis", $message_id, $user_id, $emoji);
        $add_stmt->execute();
        $action = 'added';
    }

    echo json_encode(['status' => 'success', 'action' => $action]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
?>