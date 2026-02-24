<?php
// Filename: ec/api/update_patient_details.php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

// --- SECURITY CHECK: Enforce Role-Based Access Control ---
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(["message" => "Session expired. Please log in again."]);
    exit();
}

// Prevent 'facility' users from updating sensitive patient data
if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
    http_response_code(403);
    echo json_encode(["message" => "Permission denied. Facility users cannot update patient demographics."]);
    exit();
}
// --- END SECURITY CHECK ---

$data = json_decode(file_get_contents("php://input"));

if (empty($data->patient_id)) {
    http_response_code(400);
    echo json_encode(["message" => "Patient ID is required for update."]);
    exit();
}

try {
    // Sanitize all incoming data
    $patient_id = intval($data->patient_id);

    // --- Build the SQL query dynamically based on provided fields ---
    $fields_to_update = [];
    $params = [];
    $types = '';

    // Helper to check and add field
    // Note: We use a simple array mapping for clearer code structure,
    // but sticking to your original procedural style for consistency is fine.

    if (isset($data->patient_code)) {
        $fields_to_update[] = "patient_code = ?";
        $params[] = !empty($data->patient_code) ? htmlspecialchars(strip_tags($data->patient_code)) : null;
        $types .= 's';
    }
    if (isset($data->first_name)) {
        $fields_to_update[] = "first_name = ?";
        $params[] = htmlspecialchars(strip_tags($data->first_name));
        $types .= 's';
    }
    if (isset($data->last_name)) {
        $fields_to_update[] = "last_name = ?";
        $params[] = htmlspecialchars(strip_tags($data->last_name));
        $types .= 's';
    }
    if (isset($data->date_of_birth)) {
        $fields_to_update[] = "date_of_birth = ?";
        $params[] = htmlspecialchars(strip_tags($data->date_of_birth));
        $types .= 's';
    }
    if (isset($data->gender)) {
        $fields_to_update[] = "gender = ?";
        $params[] = htmlspecialchars(strip_tags($data->gender));
        $types .= 's';
    }
    if (isset($data->contact_number)) {
        $fields_to_update[] = "contact_number = ?";
        $params[] = htmlspecialchars(strip_tags($data->contact_number));
        $types .= 's';
    }
    if (isset($data->email)) {
        $fields_to_update[] = "email = ?";
        $params[] = htmlspecialchars(strip_tags($data->email));
        $types .= 's';
    }
    if (isset($data->address)) {
        $fields_to_update[] = "address = ?";
        $params[] = htmlspecialchars(strip_tags($data->address));
        $types .= 's';
    }
    if (isset($data->allergies)) {
        $fields_to_update[] = "allergies = ?";
        $params[] = htmlspecialchars(strip_tags($data->allergies));
        $types .= 's';
    }
    if (isset($data->past_medical_history)) {
        $fields_to_update[] = "past_medical_history = ?";
        $params[] = htmlspecialchars(strip_tags($data->past_medical_history));
        $types .= 's';
    }
    if (isset($data->social_history)) {
        $fields_to_update[] = "social_history = ?";
        $params[] = htmlspecialchars(strip_tags($data->social_history));
        $types .= 's';
    }
    if (isset($data->emergency_contact_name)) {
        $fields_to_update[] = "emergency_contact_name = ?";
        $params[] = htmlspecialchars(strip_tags($data->emergency_contact_name));
        $types .= 's';
    }
    if (isset($data->emergency_contact_relationship)) {
        $fields_to_update[] = "emergency_contact_relationship = ?";
        $params[] = htmlspecialchars(strip_tags($data->emergency_contact_relationship));
        $types .= 's';
    }
    if (isset($data->emergency_contact_phone)) {
        $fields_to_update[] = "emergency_contact_phone = ?";
        $params[] = htmlspecialchars(strip_tags($data->emergency_contact_phone));
        $types .= 's';
    }

    // --- Assignments (Nullable Integers) ---
    if (isset($data->primary_user_id)) {
        $fields_to_update[] = "primary_user_id = ?";
        $params[] = !empty($data->primary_user_id) ? intval($data->primary_user_id) : null;
        $types .= 'i';
    }
    if (isset($data->facility_id)) {
        $fields_to_update[] = "facility_id = ?";
        $params[] = !empty($data->facility_id) ? intval($data->facility_id) : null;
        $types .= 'i';
    }

    // --- ALWAYS ADD: Track which user is making the update ---
    $fields_to_update[] = "last_updated_by = ?";
    $params[] = intval($_SESSION['ec_user_id']);
    $types .= 'i';

    if (empty($fields_to_update)) {
        http_response_code(400);
        echo json_encode(["message" => "No data provided to update."]);
        exit();
    }

    $sql = "UPDATE patients SET " . implode(', ', $fields_to_update) . " WHERE patient_id = ?";
    $params[] = $patient_id;
    $types .= 'i';

    $stmt = $conn->prepare($sql);

    // --- Safe Dynamic Binding ---
    $bind_params = [];
    $bind_params[] = $types; // First element is types string

    for ($i = 0; $i < count($params); $i++) {
        $bind_params[] = &$params[$i]; // Pass by reference
    }

    call_user_func_array([$stmt, 'bind_param'], $bind_params);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Patient details updated successfully."]);

        // Log the update
        $user_id = $_SESSION['ec_user_id'];
        $audit_sql = "INSERT INTO audit_log (user_id, action, entity_type, entity_id, details) VALUES (?, 'UPDATE', 'patient', ?, 'Patient details updated via API')";
        $audit_stmt = $conn->prepare($audit_sql);
        if ($audit_stmt) {
            $audit_stmt->bind_param("ii", $user_id, $patient_id);
            $audit_stmt->execute();
            $audit_stmt->close();
        }

    } else {
        throw new Exception("Database update failed. " . $conn->error);
    }

} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(["message" => "Unable to update patient details.", "error" => $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
$conn->close();
?>