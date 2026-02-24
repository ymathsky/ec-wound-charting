<?php
// Filename: api/manage_cpt_code.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- Role-based Access Control ---
if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

try {
    switch ($method) {
        case 'POST': // Handles both Create and Update
            if (empty($data->code) || empty($data->description) || empty($data->category) || !isset($data->fee)) {
                throw new Exception("All fields are required.");
            }

            $code = htmlspecialchars(strip_tags($data->code));
            $description = htmlspecialchars(strip_tags($data->description));
            $category = htmlspecialchars(strip_tags($data->category));
            $fee = floatval($data->fee);

            if (isset($data->id) && !empty($data->id)) {
                // Update
                $id = intval($data->id);
                $sql = "UPDATE cpt_codes SET code=?, description=?, category=?, fee=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssdi", $code, $description, $category, $fee, $id);
                $message = "CPT code updated successfully.";
            } else {
                // Create
                $sql = "INSERT INTO cpt_codes (code, description, category, fee) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssd", $code, $description, $category, $fee);
                $message = "CPT code added successfully.";
            }

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => $message]);
            } else {
                throw new Exception("Database operation failed.");
            }
            $stmt->close();
            break;

        case 'DELETE': // Delete
            if (empty($data->id)) {
                throw new Exception("ID is required for deletion.");
            }
            $id = intval($data->id);
            $sql = "DELETE FROM cpt_codes WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => "CPT code deleted successfully."]);
            } else {
                throw new Exception("Database deletion failed.");
            }
            $stmt->close();
            break;

        default:
            http_response_code(405);
            echo json_encode(["message" => "Method not allowed."]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage()]);
}

$conn->close();
?>
