<?php
// Filename: ec/api/manage_my_hpi_question.php
// COMPLETE: This file handles all of a clinician's personal HPI question CRUD.
// 1. Checks for a valid session user_id on ALL actions.
// 2. 'get_my_questions': Fetches only questions belonging to this user.
// 3. 'create', 'update', 'toggle_active', 'delete': All are secured with "WHERE user_id = ?"
// 4. FIX: Removed all `alert()` and `confirm()` calls.

session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- Security Check: Must be a logged-in user ---
if (!isset($_SESSION['ec_user_id']) || empty($_SESSION['ec_user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Access Denied: You must be logged in.']);
    exit();
}

$user_id = intval($_SESSION['ec_user_id']);

$conn->begin_transaction();

try {
    // GET request handler (Fetch)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action']) && $_GET['action'] == 'get_my_questions') {

            $sql = "SELECT * FROM hpi_questions 
                    WHERE user_id = ? 
                    ORDER BY category, display_order, question_id";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

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
                // Creates a new question linked to this user.
                // Narrative key is intentionally set to NULL for personalized questions.
                $sql = "INSERT INTO hpi_questions (category, question_text, question_type, options, display_order, is_active, user_id, narrative_key, allow_wound_link) 
                        VALUES (?, ?, ?, ?, ?, 1, ?, NULL, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiii",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $user_id, $data['allow_wound_link']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Personalized question created.']);
                break;

            case 'update':
                // Updates a question, but *only* if this user owns it.
                $sql = "UPDATE hpi_questions SET category = ?, question_text = ?, question_type = ?, 
                        options = ?, display_order = ?, allow_wound_link = ?
                        WHERE question_id = ? AND user_id = ?"; // Security check
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssiiii",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $data['allow_wound_link'],
                    $data['question_id'], $user_id
                );
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Question updated successfully.']);
                } else {
                    // This can happen if the question_id is wrong, or if they try to edit a question that isn't theirs.
                    throw new Exception('Could not update question. It may not exist or you may not be the owner.');
                }
                break;

            case 'toggle_active':
                // Activates or deactivates a question, *only* if this user owns it.
                $sql = "UPDATE hpi_questions SET is_active = ? WHERE question_id = ? AND user_id = ?"; // Security check
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iii", $data['new_status'], $data['question_id'], $user_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    $action_text = $data['new_status'] == 1 ? 'activated' : 'deactivated';
                    echo json_encode(['success' => true, 'message' => "Question $action_text."]);
                } else {
                    throw new Exception('Could not toggle question. It may not exist or you may not be the owner.');
                }
                break;

            case 'delete':
                // Permanently deletes a question, *only* if this user owns it.
                $question_id = $data['question_id'];

                $sql = "DELETE FROM hpi_questions WHERE question_id = ? AND user_id = ?"; // Security check
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $question_id, $user_id);
                $stmt->execute();

                if ($stmt->affected_rows > 0) {
                    echo json_encode(['success' => true, 'message' => 'Question permanently deleted.']);
                } else {
                    throw new Exception('Could not delete question. It may not exist or you may not be the owner.');
                }
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