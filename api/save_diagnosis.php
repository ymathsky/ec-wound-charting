<?php
// Filename: api/save_diagnosis.php
// Handles Create, Update, Delete, and Toggle Primary for diagnoses
// UPDATED: Added 'update' action for editing notes/wound links

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) $input = $_POST;

$action = $input['action'] ?? '';
$appointment_id = isset($input['appointment_id']) ? intval($input['appointment_id']) : 0;
$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;

// For delete/update, we might not strictly need appointment_id in the check,
// but it's good for validation.
if ($appointment_id <= 0 && $action !== 'delete' && $action !== 'update') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing Appointment ID']);
    exit();
}

try {
    if ($action === 'add') {
        // --- ADD DIAGNOSIS ---
        $icd10_code = $input['icd10_code'] ?? '';
        $description = $input['description'] ?? '';
        $wound_id = !empty($input['wound_id']) ? intval($input['wound_id']) : null;
        $is_primary = !empty($input['is_primary']) ? 1 : 0;
        $notes = isset($input['notes']) ? trim($input['notes']) : null;
        $user_id = !empty($input['user_id']) ? intval($input['user_id']) : null;

        if (empty($icd10_code)) throw new Exception("ICD-10 Code is required.");

        // --- AUTO-ADD TO MASTER DATABASE IF MISSING ---
        // Check if code exists in master list
        $check_sql = "SELECT icd10_code FROM icd10_codes WHERE icd10_code = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $icd10_code);
        $check_stmt->execute();
        $check_stmt->store_result();
        
        if ($check_stmt->num_rows == 0) {
            // Code not found, add it to master database
            $check_stmt->close();
            $insert_master_sql = "INSERT INTO icd10_codes (icd10_code, description) VALUES (?, ?)";
            $insert_master_stmt = $conn->prepare($insert_master_sql);
            $insert_master_stmt->bind_param("ss", $icd10_code, $description);
            // We suppress errors here in case of race conditions or other issues, 
            // as the primary goal is adding to the visit.
            @$insert_master_stmt->execute(); 
            $insert_master_stmt->close();
        } else {
            $check_stmt->close();
        }
        // ----------------------------------------------

        // If primary, uncheck others
        if ($is_primary) {
            $reset_sql = "UPDATE visit_diagnoses SET is_primary = 0 WHERE appointment_id = ?";
            $stmt = $conn->prepare($reset_sql);
            $stmt->bind_param("i", $appointment_id);
            $stmt->execute();
            $stmt->close();
        }

        $sql = "INSERT INTO visit_diagnoses (appointment_id, patient_id, wound_id, icd10_code, description, is_primary, user_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiissiis", $appointment_id, $patient_id, $wound_id, $icd10_code, $description, $is_primary, $user_id, $notes);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Diagnosis added.']);
        } else {
            throw new Exception("Failed to add diagnosis: " . $stmt->error);
        }
        $stmt->close();

    } elseif ($action === 'update') {
        // --- UPDATE DIAGNOSIS (Edit Notes/Wound Link) ---
        $diagnosis_id = intval($input['diagnosis_id']);
        $wound_id = !empty($input['wound_id']) ? intval($input['wound_id']) : null;
        $notes = isset($input['notes']) ? trim($input['notes']) : null;

        if ($diagnosis_id <= 0) throw new Exception("Invalid Diagnosis ID");

        $sql = "UPDATE visit_diagnoses SET wound_id = ?, notes = ? WHERE visit_diagnosis_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $wound_id, $notes, $diagnosis_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Diagnosis updated.']);
        } else {
            throw new Exception("Failed to update diagnosis: " . $stmt->error);
        }
        $stmt->close();

    } elseif ($action === 'delete') {
        // --- DELETE DIAGNOSIS ---
        $diagnosis_id = intval($input['diagnosis_id']);
        $sql = "DELETE FROM visit_diagnoses WHERE visit_diagnosis_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $diagnosis_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Diagnosis deleted.']);
        } else {
            throw new Exception("Failed to delete diagnosis.");
        }
        $stmt->close();

    } elseif ($action === 'set_primary') {
        // --- TOGGLE PRIMARY ---
        $diagnosis_id = intval($input['diagnosis_id']);
        $conn->begin_transaction();

        $reset_sql = "UPDATE visit_diagnoses SET is_primary = 0 WHERE appointment_id = ?";
        $stmt1 = $conn->prepare($reset_sql);
        $stmt1->bind_param("i", $appointment_id);
        $stmt1->execute();
        $stmt1->close();

        $set_sql = "UPDATE visit_diagnoses SET is_primary = 1 WHERE visit_diagnosis_id = ?";
        $stmt2 = $conn->prepare($set_sql);
        $stmt2->bind_param("i", $diagnosis_id);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Primary diagnosis updated.']);

    } elseif ($action === 'copy_previous') {
        // Use legacy copy logic if needed, or rely on frontend single-add
        // This block can remain or be expanded as needed.
    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>