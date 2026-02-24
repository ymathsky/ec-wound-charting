<?php
// Filename: api/check_patient_duplicate.php
header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$first_name = isset($_GET['first_name']) ? $_GET['first_name'] : '';
$last_name = isset($_GET['last_name']) ? $_GET['last_name'] : '';
$dob = isset($_GET['date_of_birth']) ? $_GET['date_of_birth'] : '';

if (empty($first_name) || empty($last_name) || empty($dob)) {
    // This case is handled by the frontend JS, but it's good practice to have it.
    // We return 'exists: false' so the form isn't blocked if fields are temporarily empty.
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $sql = "SELECT patient_id FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $first_name, $last_name, $dob);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['exists' => true]);
    } else {
        echo json_encode(['exists' => false]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed.']);
}

$conn->close();
?>
