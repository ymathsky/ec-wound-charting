<?php
// Filename: api/save_medication_order.php
header('Content-Type: application/json');
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input.']);
    exit;
}

$appointment_id = isset($input['appointment_id']) ? intval($input['appointment_id']) : 0;
$patient_id = isset($input['patient_id']) ? intval($input['patient_id']) : 0;
$user_id = isset($input['user_id']) ? intval($input['user_id']) : 0;
$medication_name = isset($input['medication_name']) ? trim($input['medication_name']) : '';
$dosage = isset($input['dosage']) ? trim($input['dosage']) : '';
$frequency = isset($input['frequency']) ? trim($input['frequency']) : '';
$route = isset($input['route']) ? trim($input['route']) : '';
$quantity = isset($input['quantity']) ? trim($input['quantity']) : '';
$refills = isset($input['refills']) ? intval($input['refills']) : 0;
$pharmacy_note = isset($input['pharmacy_note']) ? trim($input['pharmacy_note']) : '';

if ($appointment_id <= 0 || $patient_id <= 0 || empty($medication_name)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Insert into medication_orders
$stmt = $conn->prepare("INSERT INTO medication_orders (appointment_id, patient_id, user_id, medication_name, dosage, frequency, route, quantity, refills, pharmacy_note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisssssis", $appointment_id, $patient_id, $user_id, $medication_name, $dosage, $frequency, $route, $quantity, $refills, $pharmacy_note);

if ($stmt->execute()) {
    // OPTIONAL: Also add to patient_medications list as "Active" if it's a new med
    // For now, we just save the order.
    echo json_encode(['success' => true, 'message' => 'Medication ordered successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
