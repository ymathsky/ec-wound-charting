<?php
// Filename: ec/api/delete_wound.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- SECURITY CHECK: Enforce Role-Based Access Control ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Session expired. Please log in again."]);
    exit();
}

// Prevent 'facility' users from deleting data
if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
    http_response_code(403);
    echo json_encode(["message" => "Permission denied. Facility users cannot delete records."]);
    exit();
}
// --- END SECURITY CHECK ---

$data = json_decode(file_get_contents("php://input"));

if (empty($data->wound_id)) {
    http_response_code(400);
    echo json_encode(array("message" => "Wound ID is required."));
    exit();
}

$wound_id = intval($data->wound_id);

try {
    // Optional: Verify ownership or additional constraints here if needed

    // Delete the wound (Cascading delete in DB should handle related records, 
    // but explicit deletion logic can be added here if CASCADE isn't set up)
    $sql = "DELETE FROM wounds WHERE wound_id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $wound_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(array("message" => "Wound deleted successfully."));

            // Log the deletion in audit_log
            $user_id = $_SESSION['ec_user_id'];
            $audit_sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, 'DELETE', 'wound', ?, 'Wound deleted via API')";
            $audit_stmt = $conn->prepare($audit_sql);
            if ($audit_stmt) {
                $audit_stmt->bind_param("ii", $user_id, $wound_id);
                $audit_stmt->execute();
                $audit_stmt->close();
            }
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Wound not found."));
        }
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Unable to delete wound.", "error" => $e->getMessage()));
}

$conn->close();
?>