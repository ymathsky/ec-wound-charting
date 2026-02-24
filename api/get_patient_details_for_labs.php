<?php
// Filename: ec/api/get_patient_details_for_labs.php
require_once '../db_connect.php';

header('Content-Type: application/json');

// 1. Validate Request
if (!isset($_POST['patient_id']) || empty($_POST['patient_id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit;
}

$patient_id = intval($_POST['patient_id']);

try {
    // 2. Fetch Specific Patient Data
    // We only select the fields needed for the Labs header to keep it efficient
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE patient_id = ?");

    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();

        // 3. Return Data
        echo json_encode([
            'success' => true,
            'data' => [
                'patient_id' => $patient['patient_id'],
                'first_name' => $patient['first_name'],
                'last_name' => $patient['last_name'],
                'full_name' => $patient['first_name'] . ' ' . $patient['last_name']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
    }

    $stmt->close();

} catch (Exception $e) {
    // 4. Handle Errors
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
?>