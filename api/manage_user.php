<?php
// Filename: api/manage_user.php
// Description: API endpoint to manage user status (activate/deactivate) and deletion.

header('Content-Type: application/json');
session_start();
require_once '../db_connect.php';
require_once '../audit_log_function.php';

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit();
}

// Ensure the request comes from an authenticated admin
if (!isset($_SESSION['ec_user_id']) || $_SESSION['ec_role'] !== 'admin') {
    echo json_encode(["success" => false, "message" => "Authorization failed. Must be an administrator."]);
    exit();
}

// Read and decode the JSON payload
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

$action = $data['action'] ?? null;
$user_id = $data['user_id'] ?? null;
$admin_id = $_SESSION['ec_user_id'] ?? 0;
$admin_name = $_SESSION['ec_full_name'] ?? 'Admin Session';

if (empty($action) || empty($user_id)) {
    echo json_encode(["success" => false, "message" => "Missing required parameters (action or user_id)."]);
    exit();
}

// Security Check: Prevent admin from deactivating or deleting themselves
if ((int)$user_id === (int)$admin_id && ($action === 'delete' || $data['status'] === 'inactive')) {
    echo json_encode(["success" => false, "message" => "Security Alert: You cannot deactivate or delete your own admin account while logged in."]);
    exit();
}

$success = false;
$message = "Action completed.";

switch ($action) {
    case 'update_status':
        $new_status = $data['status'] ?? null; // Expects 'active' or 'inactive'

        if (!in_array($new_status, ['active', 'inactive'])) {
            $message = "Invalid status provided.";
            break;
        }

        $sql = "UPDATE users SET status = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $message = "Database prepare failed: " . $conn->error;
            break;
        }

        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = true;
                $audit_detail = "User ID $user_id status changed to '$new_status'.";
                log_audit($conn, $admin_id, $admin_name, 'UPDATE_STATUS', 'user', $user_id, $audit_detail);
                $message = "User status successfully updated to " . ucfirst($new_status) . ".";
            } else {
                $message = "No change needed or user not found.";
                $success = true; // Technically successful execution
            }
        } else {
            $message = "Status update execution failed: " . $stmt->error;
        }
        $stmt->close();
        break;

    case 'delete':
        $sql = "DELETE FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $message = "Database prepare failed: " . $conn->error;
            break;
        }

        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = true;
                $audit_detail = "User ID $user_id permanently deleted.";
                log_audit($conn, $admin_id, $admin_name, 'DELETE', 'user', $user_id, $audit_detail);
                $message = "User successfully deleted.";
            } else {
                $message = "User not found or already deleted.";
            }
        } else {
            $message = "Delete execution failed: " . $stmt->error;
        }
        $stmt->close();
        break;

    default:
        $message = "Invalid action specified.";
        break;
}

// Final response
echo json_encode(["success" => $success, "message" => $message]);
// $conn is closed implicitly or by another script.
?>
