<?php
// Filename: api/update_wound_status.php
// API to update the status of a single wound.

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once '../db_connect.php';

// Get posted data
$data = json_decode(file_get_contents("php://input"));

// --- Validation ---
if (empty($data->wound_id) || empty($data->new_status)) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Wound ID and new status are required."));
    exit();
}

// Validate the status against the allowed enum values
$allowed_statuses = ['Active', 'Healed', 'Inactive'];
if (!in_array($data->new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Invalid status provided."));
    exit();
}

$wound_id = intval($data->wound_id);
$new_status = $data->new_status;

try {
    // --- Prepare and Execute SQL ---
    $sql = "UPDATE wounds SET status = ? WHERE wound_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $wound_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            http_response_code(200);
            echo json_encode(array("success" => true, "message" => "Wound status updated successfully."));
        } else {
            // Query ran, but no rows were changed (e.g., status was already set to that)
            http_response_code(200);
            echo json_encode(array("success" => true, "message" => "Wound status was already set."));
        }
    } else {
        throw new Exception("Database query failed.");
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "Server error.", "error" => $e->getMessage()));
}
?>