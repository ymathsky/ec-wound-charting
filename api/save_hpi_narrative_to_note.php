<?php
// Filename: api/save_hpi_narrative_to_note.php
// This API saves the AI-generated HPI narrative to the
// new 'visit_hpi_narratives' table.

session_start(); // Start session to get user_id

header("Content-Type: application/json; charset=UTF-8");

// Use __DIR__ for robust file inclusion
require_once __DIR__ . '/../db_connect.php';
// We don't need audit_log_function.php for this file

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true);

$appointment_id = $data['appointment_id'] ?? 0;
$patient_id = $data['patient_id'] ?? 0;
$narrative_text = $data['narrative_text'] ?? '';
// Get user_id from the JSON body, or fall back to session
$user_id = $data['user_id'] ?? (isset($_SESSION['ec_user_id']) ? $_SESSION['ec_user_id'] : null);

if ($appointment_id <= 0 || $patient_id <= 0 || empty($narrative_text)) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Invalid data provided (appointment, patient, or narrative missing)."]);
    exit();
}

// --- FIX: Use the global $conn variable from db_connect.php ---
try {
    // Access the $conn variable defined in db_connect.php
    global $conn;

    // Check if the connection was successful
    if ($conn->connect_error) {
        throw new Exception("Database connection error: " . $conn->connect_error);
    }

    // Use INSERT ... ON DUPLICATE KEY UPDATE to perform an "UPSERT".
    // This requires the 'appointment_id' column to have a UNIQUE index.
    $sql = "
        INSERT INTO visit_hpi_narratives 
        (appointment_id, patient_id, user_id, narrative_text, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
        narrative_text = VALUES(narrative_text),
        user_id = VALUES(user_id),
        updated_at = NOW()
    ";

    // Prepare and bind the statement using mysqli
    $stmt = $conn->prepare($sql);

    // "iiis" - Integer, Integer, Integer, String
    $stmt->bind_param("iiis",
        $appointment_id,
        $patient_id,
        $user_id,
        $narrative_text
    );

    $stmt->execute();

    $message = "HPI narrative saved successfully.";

    $stmt->close();
    // $conn->close(); // Don't close the global connection, other scripts might need it.

    echo json_encode(["success" => true, "message" => $message]);

} catch (Exception $e) { // Catch generic Exceptions for mysqli
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>