<?php
// Filename: ec/api/manage_suggestion.php
// NEW API: Backend for the 'manage_suggestions.php' admin page.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- Admin-only API ---
if (!isset($_SESSION['ec_role']) || strtolower(trim($_SESSION['ec_role'])) != 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'message' => 'Access Denied: Admin role required.']);
    exit();
}

$conn->begin_transaction();

try {
    // GET request handler (Fetch)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] == 'get_all') {
            $sql = "SELECT * FROM clinical_suggestions ORDER BY category, display_order, suggestion_text";
            $result = $conn->query($sql);
            $suggestions = [];
            while ($row = $result->fetch_assoc()) {
                $suggestions[] = $row;
            }
            echo json_encode(['success' => true, 'suggestions' => $suggestions]);
            $conn->commit();
            exit();
        }
    }

    // POST request handler (Create, Update, Delete, Toggle)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents("php://input"), true);
        $action = $data['action'] ?? '';

        switch ($action) {
            case 'create':
                $sql = "INSERT INTO clinical_suggestions (category, suggestion_text, display_order, is_active) 
                        VALUES (?, ?, ?, 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi",
                    $data['category'], $data['suggestion_text'], $data['display_order']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Suggestion created successfully.']);
                break;

            case 'update':
                $sql = "UPDATE clinical_suggestions SET category = ?, suggestion_text = ?, display_order = ?
                        WHERE suggestion_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssii",
                    $data['category'], $data['suggestion_text'], $data['display_order'],
                    $data['suggestion_id']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Suggestion updated successfully.']);
                break;

            case 'toggle_active':
                $sql = "UPDATE clinical_suggestions SET is_active = ? WHERE suggestion_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $data['new_status'], $data['suggestion_id']);
                $stmt->execute();
                $action_text = $data['new_status'] == 1 ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Suggestion $action_text."]);
                break;

            case 'delete':
                $sql = "DELETE FROM clinical_suggestions WHERE suggestion_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $data['suggestion_id']);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Suggestion permanently deleted.']);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        }

        $conn->commit();

    } else {
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    }

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>