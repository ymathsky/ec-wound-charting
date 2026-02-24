<?php
// Filename: ec/api/manage_hpi_question.php
// COMPLETE: This file handles all Admin-level CRUD for HPI questions.
// 1. Checks for Admin role on all actions.
// 2. Handles 'create_global', 'update', 'toggle_active', and 'get_all_with_users'.
// 3. UPDATE: 'delete' action now allows deleting ANY question (Global or Personalized).

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
        if (isset($_GET['action']) && $_GET['action'] == 'get_all_with_users') {
            // Join with users table to get the owner's name
            $sql = "SELECT q.*, u.full_name 
                    FROM hpi_questions q
                    LEFT JOIN users u ON q.user_id = u.user_id
                    ORDER BY q.category, q.display_order, q.question_id";
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
            case 'create_global':
                // Creates a new GLOBAL question (user_id is NULL)
                $sql = "INSERT INTO hpi_questions (category, question_text, question_type, options, display_order, is_active, user_id, narrative_key, allow_wound_link) 
                        VALUES (?, ?, ?, ?, ?, 1, NULL, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssisi",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $data['narrative_key'], $data['allow_wound_link']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Global question created successfully.']);
                break;

            case 'update':
                // Updates any question (Global or Personalized)
                // Check if it's a Global question being updated
                $user_id_sql = "SELECT user_id FROM hpi_questions WHERE question_id = ?";
                $stmt_check = $conn->prepare($user_id_sql);
                $stmt_check->bind_param("i", $data['question_id']);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $question = $result_check->fetch_assoc();

                if ($question && $question['user_id'] != NULL) {
                    // It's a personalized question, admin cannot set narrative key
                    $data['narrative_key'] = null;
                }

                $sql = "UPDATE hpi_questions SET category = ?, question_text = ?, question_type = ?, 
                        options = ?, display_order = ?, narrative_key = ?, allow_wound_link = ?
                        WHERE question_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssisii",
                    $data['category'], $data['question_text'], $data['question_type'],
                    $data['options'], $data['display_order'], $data['narrative_key'],
                    $data['allow_wound_link'], $data['question_id']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Question updated successfully.']);
                break;

            case 'toggle_active':
                // Activates or deactivates any question
                $sql = "UPDATE hpi_questions SET is_active = ? WHERE question_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $data['new_status'], $data['question_id']);
                $stmt->execute();
                $action_text = $data['new_status'] == 1 ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Question $action_text."]);
                break;

            case 'delete':
                // --- UPDATE ---
                // Allows admin to delete ANY question (Global or Personalized)
                $question_id = $data['question_id'];

                // First, check if the question exists
                $check_sql = "SELECT question_id FROM hpi_questions WHERE question_id = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("i", $question_id);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
                $question = $result->fetch_assoc();

                if ($question) {
                    // Question exists, proceed with deletion
                    // Note: FOREIGN KEY constraints (ON DELETE CASCADE) in `patient_hpi_answers`
                    // will handle deleting all associated answers.
                    $sql = "DELETE FROM hpi_questions WHERE question_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $question_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Question permanently deleted.']);
                    } else {
                        throw new Exception("Question found but could not be deleted.");
                    }
                } else {
                    // Question not found
                    throw new Exception("Question not found.");
                }
                // --- END UPDATE ---
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