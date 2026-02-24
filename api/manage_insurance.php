<?php
// Filename: ec/api/manage_insurance.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// Read JSON input
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input data."]);
    exit;
}

// Determine Action
$action = isset($input['action']) ? $input['action'] : 'save'; // 'save' (create/update) or 'delete'
$insurance_id = isset($input['insurance_id']) ? intval($input['insurance_id']) : null;

try {
    if ($action === 'delete') {
        if (!$insurance_id) {
            throw new Exception("Insurance ID required for deletion.");
        }

        $stmt = $conn->prepare("DELETE FROM patient_insurance WHERE insurance_id = ?");
        $stmt->bind_param("i", $insurance_id);

        if (!$stmt->execute()) {
            throw new Exception("Failed to delete policy.");
        }

        echo json_encode(["message" => "Policy deleted successfully."]);
        $stmt->close();

    } else {
        // --- SAVE (Create or Update) ---

        // Validate required fields
        if (empty($input['patient_id']) || empty($input['provider_name']) || empty($input['policy_number'])) {
            throw new Exception("Provider name and Policy number are required.");
        }

        $patient_id = intval($input['patient_id']);
        $provider = strip_tags($input['provider_name']);
        $policy_num = strip_tags($input['policy_number']);
        $group_num = isset($input['group_number']) ? strip_tags($input['group_number']) : null;
        $priority = isset($input['priority']) ? strip_tags($input['priority']) : 'Primary';

        if ($insurance_id) {
            // Update existing policy
            $sql = "UPDATE patient_insurance SET provider_name=?, policy_number=?, group_number=?, priority=? WHERE insurance_id=?";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("ssssi", $provider, $policy_num, $group_num, $priority, $insurance_id);
        } else {
            // Create new policy
            $sql = "INSERT INTO patient_insurance (patient_id, provider_name, policy_number, group_number, priority) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
            $stmt->bind_param("issss", $patient_id, $provider, $policy_num, $group_num, $priority);
        }

        if (!$stmt->execute()) {
            throw new Exception("Database error: " . $stmt->error);
        }

        echo json_encode(["message" => "Policy saved successfully."]);
        $stmt->close();
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => $e->getMessage()]);
}

$conn->close();
?>