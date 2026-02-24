<?php
// Filename: ec/api/manage_assessment_question.php
// NEW API: Backend for the 'manage_assessment_questions.php' admin page.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- Admin-only API ---
if (!isset($_SESSION['ec_role']) || strtolower(trim($_SESSION['ec_role'])) != 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access Denied: Admin role required.']);
    exit();
}

$conn->begin_transaction();

try {
    // GET request handler (Fetch)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] == 'get_all') {
            $sql = "SELECT * FROM wound_assessment_questions ORDER BY category, display_order, question_text";
            $result = $conn->query($sql);
            $questions = [];
            while ($row = $result->fetch_assoc()) {
                $questions[] = $row;
            }
            echo json_encode(['success' => true, 'questions' => $questions]);
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
                $sql = "INSERT INTO wound_assessment_questions 
                            (category, question_text, question_type, options, display_order, is_active, narrative_key, condition_key, condition_value) 
                        VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssisss",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $data['narrative_key'],
                    $data['condition_key'], $data['condition_value']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Question created successfully.']);
                break;

            case 'update':
                $sql = "UPDATE wound_assessment_questions SET 
                            category = ?, question_text = ?, question_type = ?, 
                            options = ?, display_order = ?, narrative_key = ?, 
                            condition_key = ?, condition_value = ?
                        WHERE question_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssisssi",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $data['narrative_key'],
                    $data['condition_key'], $data['condition_value'],
                    $data['question_id']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Question updated successfully.']);
                break;

            case 'toggle_active':
                $sql = "UPDATE wound_assessment_questions SET is_active = ? WHERE question_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $data['new_status'], $data['question_id']);
                $stmt->execute();
                $action_text = $data['new_status'] == 1 ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Question $action_text."]);
                break;

            case 'delete':
                // Note: The ON DELETE CASCADE in the DB will handle deleting answers
                $sql = "DELETE FROM wound_assessment_questions WHERE question_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $data['question_id']);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Question permanently deleted.']);
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