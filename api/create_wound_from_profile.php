<?php
// Filename: ec/api/create_wound_from_profile.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- SECURITY CHECK: Enforce Role-Based Access Control ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Session expired. Please log in again."]);
    exit();
}

// Prevent 'facility' users from creating new data via this profile-specific endpoint
if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
    http_response_code(403);
    echo json_encode(["message" => "Permission denied. Facility users cannot create new wounds."]);
    exit();
}
// --- END SECURITY CHECK ---

$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input data."]);
    exit();
}

// Validate required fields
// Note: 'location', 'wound_type', and 'date_onset' are critical for a valid wound record.
if (empty($input['patient_id']) || empty($input['location']) || empty($input['wound_type']) || empty($input['date_onset'])) {
    http_response_code(400);
    echo json_encode(["message" => "Missing required fields: Patient ID, Location, Wound Type, or Onset Date."]);
    exit();
}

try {
    $patient_id = intval($input['patient_id']);
    $location = strip_tags($input['location']);
    $wound_type = strip_tags($input['wound_type']);
    $date_onset = strip_tags($input['date_onset']);
    $diagnosis = isset($input['diagnosis']) ? strip_tags($input['diagnosis']) : null;

    // Map Coordinates removed from this API call entirely as we are no longer tracking them
    // Set map_x and map_y to NULL explicitly or rely on table defaults if they allow NULL
    $map_x = null;
    $map_y = null;

    // Prepare SQL Statement
    $sql = "INSERT INTO wounds (patient_id, location, wound_type, diagnosis, date_onset, status, map_x, map_y) VALUES (?, ?, ?, ?, ?, 'Active', ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind Parameters: i=integer, s=string, d=double/float
    // Parameters: patient_id (i), location (s), wound_type (s), diagnosis (s), date_onset (s), map_x (d - NULL), map_y (d - NULL)
    // Note: Even if NULL, bind_param usually expects a type. 'd' is fine for NULL if the DB allows it.
    $stmt->bind_param("issssdd", $patient_id, $location, $wound_type, $diagnosis, $date_onset, $map_x, $map_y);

    if ($stmt->execute()) {
        $new_wound_id = $stmt->insert_id;

        // Log the creation in audit_log
        $user_id = $_SESSION['ec_user_id'];
        $audit_details = "New wound created from Profile Page. Location: $location, Type: $wound_type";

        $audit_sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, 'CREATE', 'wound', ?, ?)";
        $audit_stmt = $conn->prepare($audit_sql);
        if ($audit_stmt) {
            $audit_stmt->bind_param("iis", $user_id, $new_wound_id, $audit_details);
            $audit_stmt->execute();
            $audit_stmt->close();
        }

        http_response_code(201); // 201 Created
        echo json_encode([
            "message" => "Wound registered successfully.",
            "wound_id" => $new_wound_id
        ]);

    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Unable to create wound.", "error" => $e->getMessage()]);
}

$conn->close();
?>