<?php
// Filename: ec/api/manage_soap_checklist.php
// NEW API: Backend for the 'manage_soap_checklist.php' admin page.

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
            $sql = "SELECT * FROM soap_checklist_items ORDER BY soap_section, category, title, display_order, item_text";
            $result = $conn->query($sql);
            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            echo json_encode(['success' => true, 'items' => $items]);
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
                // Check if 'options' array is provided for bulk creation
                if (isset($data['options']) && is_array($data['options'])) {
                    $sql = "INSERT INTO soap_checklist_items (soap_section, category, title, item_text, display_order, is_active) 
                            VALUES (?, ?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($sql);
                    
                    foreach ($data['options'] as $opt) {
                        if (trim($opt) === '') continue;
                        $stmt->bind_param("ssssi",
                            $data['soap_section'], $data['category'], $data['title'], $opt, $data['display_order']
                        );
                        $stmt->execute();
                    }
                    echo json_encode(['success' => true, 'message' => 'Checklist items created successfully.']);
                } else {
                    // Single item creation
                    $sql = "INSERT INTO soap_checklist_items (soap_section, category, title, item_text, display_order, is_active) 
                            VALUES (?, ?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssssi",
                        $data['soap_section'], $data['category'], $data['title'], $data['item_text'], $data['display_order']
                    );
                    $stmt->execute();
                    echo json_encode(['success' => true, 'message' => 'Checklist item created successfully.']);
                }
                break;

            case 'update':
                $sql = "UPDATE soap_checklist_items SET soap_section = ?, category = ?, title = ?, item_text = ?, display_order = ?
                        WHERE item_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssii",
                    $data['soap_section'], $data['category'], $data['title'], $data['item_text'], $data['display_order'],
                    $data['item_id']
                );
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Checklist item updated successfully.']);
                break;

            case 'toggle_active':
                $sql = "UPDATE soap_checklist_items SET is_active = ? WHERE item_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $data['new_status'], $data['item_id']);
                $stmt->execute();
                $action_text = $data['new_status'] == 1 ? 'activated' : 'deactivated';
                echo json_encode(['success' => true, 'message' => "Item $action_text."]);
                break;

            case 'delete':
                $sql = "DELETE FROM soap_checklist_items WHERE item_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $data['item_id']);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Item permanently deleted.']);
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