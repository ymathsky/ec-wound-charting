<?php
// Filename: api/update_visit_summary.php
// Purpose: Update visit data (Notes, Vitals, HPI) from the Visit Summary page.

// Ensure no output before JSON
ob_start();

header('Content-Type: application/json');

// Turn off error display, log them instead
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        // If a fatal error occurred, clear buffer and output JSON error
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Fatal Error: ' . $error['message']]);
    }
});

require_once '../db_connect.php';

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit;
}

$appointment_id = isset($input['appointment_id']) ? intval($input['appointment_id']) : 0;
$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;

if ($appointment_id <= 0) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid Appointment ID']);
    exit;
}

$response = ['success' => true, 'messages' => []];

try {
    $conn->begin_transaction();

    // 1. Update Visit Notes
    if (isset($input['visit_notes']) && is_array($input['visit_notes']) && !empty($input['visit_notes'])) {
        $notes = $input['visit_notes'];
        
        // Allowed fields whitelist
        $allowed_fields = [
            'chief_complaint', 'subjective', 'objective', 'assessment', 'plan', 
            'procedure_note', 'skilled_nurse_orders', 'lab_orders', 'imaging_orders'
        ];

        $updates = [];
        $types = "";
        $params = [];

        foreach ($notes as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $updates[] = "$field = ?";
                $types .= "s";
                $params[] = $value;
            }
        }

        if (!empty($updates)) {
            // Check if record exists
            $check_stmt = $conn->prepare("SELECT note_id FROM visit_notes WHERE appointment_id = ?");
            $check_stmt->bind_param("i", $appointment_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            $exists = $result->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                $sql = "UPDATE visit_notes SET " . implode(", ", $updates) . " WHERE appointment_id = ?";
                $types .= "i";
                $params[] = $appointment_id;
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            } else {
                // For INSERT, we need to handle partial data carefully or just insert what we have
                // But since this is an edit on an existing view, the record likely exists.
                // If not, we create it with the provided fields.
                
                // Re-build for INSERT to be safe
                $insert_fields = ['appointment_id', 'patient_id'];
                $insert_placeholders = ['?', '?'];
                $insert_types = "ii";
                $insert_params = [$appointment_id, $patient_id];

                foreach ($notes as $field => $value) {
                    if (in_array($field, $allowed_fields)) {
                        $insert_fields[] = $field;
                        $insert_placeholders[] = '?';
                        $insert_types .= "s";
                        $insert_params[] = $value;
                    }
                }

                $sql = "INSERT INTO visit_notes (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_placeholders) . ")";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($insert_types, ...$insert_params);
                $stmt->execute();
                $stmt->close();
            }
            $response['messages'][] = "Visit notes updated.";
        }
    }

    // 2. Update Vitals
    if (isset($input['vitals']) && is_array($input['vitals']) && !empty($input['vitals'])) {
        $vitals = $input['vitals'];
        
        $allowed_fields = [
            'blood_pressure' => 's', 
            'heart_rate' => 'i', 
            'respiratory_rate' => 'd', 
            'temperature_celsius' => 'd', 
            'oxygen_saturation' => 'i'
        ];

        $updates = [];
        $types = "";
        $params = [];

        foreach ($vitals as $field => $value) {
            if (array_key_exists($field, $allowed_fields)) {
                $updates[] = "$field = ?";
                $types .= $allowed_fields[$field];
                $params[] = $value;
            }
        }

        if (!empty($updates)) {
            // Check if record exists
            $check_stmt = $conn->prepare("SELECT vital_id FROM patient_vitals WHERE appointment_id = ?");
            $check_stmt->bind_param("i", $appointment_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                $sql = "UPDATE patient_vitals SET " . implode(", ", $updates) . " WHERE appointment_id = ?";
                $types .= "i";
                $params[] = $appointment_id;
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            } else {
                $insert_fields = ['appointment_id', 'patient_id'];
                $insert_placeholders = ['?', '?'];
                $insert_types = "ii";
                $insert_params = [$appointment_id, $patient_id];

                foreach ($vitals as $field => $value) {
                    if (array_key_exists($field, $allowed_fields)) {
                        $insert_fields[] = $field;
                        $insert_placeholders[] = '?';
                        $insert_types .= $allowed_fields[$field];
                        $insert_params[] = $value;
                    }
                }

                $sql = "INSERT INTO patient_vitals (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_placeholders) . ")";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($insert_types, ...$insert_params);
                $stmt->execute();
                $stmt->close();
            }
            $response['messages'][] = "Vitals updated.";
        }
    }

    // 3. Update HPI Narrative
    if (isset($input['hpi']) && is_array($input['hpi']) && !empty($input['hpi'])) {
        $hpi = $input['hpi'];
        
        if (isset($hpi['narrative_text'])) {
            // Check if record exists
            $check_stmt = $conn->prepare("SELECT narrative_id FROM visit_hpi_narratives WHERE appointment_id = ?");
            $check_stmt->bind_param("i", $appointment_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                $sql = "UPDATE visit_hpi_narratives SET narrative_text = ? WHERE appointment_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $hpi['narrative_text'], $appointment_id);
                $stmt->execute();
                $stmt->close();
            } else {
                $sql = "INSERT INTO visit_hpi_narratives (appointment_id, patient_id, narrative_text) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iis", $appointment_id, $patient_id, $hpi['narrative_text']);
                $stmt->execute();
                $stmt->close();
            }
            $response['messages'][] = "HPI updated.";
        }
    }

    // 4. Update Signature
    if (isset($input['signature_data'])) {
        $signature_data = $input['signature_data'];
        
        // Check if record exists
        $check_stmt = $conn->prepare("SELECT note_id FROM visit_notes WHERE appointment_id = ?");
        $check_stmt->bind_param("i", $appointment_id);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->num_rows > 0;
        $check_stmt->close();

        if ($exists) {
            $sql = "UPDATE visit_notes SET signature_data = ?, signed_at = NOW(), is_signed = 1, status = 'finalized', finalized_at = NOW() WHERE appointment_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $signature_data, $appointment_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $sql = "INSERT INTO visit_notes (appointment_id, patient_id, signature_data, signed_at, is_signed, status, finalized_at) VALUES (?, ?, ?, NOW(), 1, 'finalized', NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iis", $appointment_id, $patient_id, $signature_data);
            $stmt->execute();
            $stmt->close();
        }

        // Update Appointment Status to 'completed'
        $updAppt = $conn->prepare("UPDATE appointments SET status = 'completed' WHERE appointment_id = ?");
        $updAppt->bind_param('i', $appointment_id);
        $updAppt->execute();
        $updAppt->close();

        $response['messages'][] = "Signature updated and note finalized.";
    }

    $conn->commit();
    
    // Clear buffer and output JSON
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
