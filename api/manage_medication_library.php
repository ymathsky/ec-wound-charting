<?php
// Filename: api/manage_medication_library.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

if (!isset($_SESSION['ec_role']) || $_SESSION['ec_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(["message" => "Access Denied."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

try {
    switch ($method) {
        case 'POST':
            if (empty($data->name)) {
                throw new Exception("Medication name is required.");
            }
            $name = htmlspecialchars(strip_tags($data->name));
            $description = isset($data->description) ? htmlspecialchars(strip_tags($data->description)) : null;
            $default_dosage = isset($data->default_dosage) ? htmlspecialchars(strip_tags($data->default_dosage)) : null;
            $default_frequency = isset($data->default_frequency) ? htmlspecialchars(strip_tags($data->default_frequency)) : null;

            if (isset($data->id) && !empty($data->id)) {
                $id = intval($data->id);
                $sql = "UPDATE medications_library SET name=?, description=?, default_dosage=?, default_frequency=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $name, $description, $default_dosage, $default_frequency, $id);
                $message = "Library medication updated successfully.";
            } else {
                $sql = "INSERT INTO medications_library (name, description, default_dosage, default_frequency) VALUES (?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $name, $description, $default_dosage, $default_frequency);
                $message = "Medication added to library successfully.";
            }
            if (!$stmt->execute()) throw new Exception("Database operation failed.");
            $stmt->close();
            http_response_code(200);
            echo json_encode(["message" => $message]);
            break;

        case 'DELETE':
            if (empty($data->id)) throw new Exception("ID is required for deletion.");
            $id = intval($data->id);
            $sql = "DELETE FROM medications_library WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) throw new Exception("Database deletion failed.");
            $stmt->close();
            http_response_code(200);
            echo json_encode(["message" => "Library medication deleted successfully."]);
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
