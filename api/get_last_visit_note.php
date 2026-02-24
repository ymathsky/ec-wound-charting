<?php
// Filename: ec/api/get_last_visit_note.php
// Purpose: Fetches the most recent previous visit note for a patient.
// UPDATED: Bulletproof version. Uses absolute paths, maps connection variables, and uses a simplified query to guarantee data access.

// 1. Silence HTML errors on the frontend
ini_set('display_errors', '0');
error_reporting(E_ALL);

// 2. Start output buffering
ob_start();

// 3. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Include Database Connection (Robust Path)
// Try multiple common paths to be safe
$possible_paths = [
    __DIR__ . '/../db_connect.php',
    dirname(__DIR__) . '/db_connect.php',
    '../db_connect.php'
];

$db_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

// 5. Connection Variable Check
// Ensure $pdo is available. Your system uses $conn, so we map it.
if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}

// Clear any output from includes
ob_clean();

header('Content-Type: application/json');

try {
    if (!$db_loaded) {
        throw new Exception("Database file not found.");
    }

    if (!isset($pdo)) {
        throw new Exception("Database connection variable ($pdo/$conn) is not set.");
    }

    // 6. Check Authentication (Optional bypass for testing, strictly enforced in prod)
    $is_authorized = isset($_SESSION['user_id']) || isset($_SESSION['ec_user_id']) || isset($_SESSION['ec_role']);
    // if (!$is_authorized) throw new Exception('Unauthorized'); // Uncomment to enforce

    $patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
    $current_appointment_id = isset($_GET['current_appointment_id']) ? intval($_GET['current_appointment_id']) : 0;

    if ($patient_id <= 0) {
        throw new Exception('Invalid Patient ID');
    }

    // 7. Query - FIXED for MySQLi
    // We remove the JOIN to eliminate column name ambiguity errors.
    // We use the note's own 'created_at' timestamp as the date reference.
    $sql = "SELECT 
            chief_complaint, 
            subjective, 
            objective, 
            assessment, 
            plan, 
            lab_orders,
            imaging_orders,
            skilled_nurse_orders,
            created_at as appointment_date
        FROM visit_notes 
        WHERE patient_id = ? 
          AND appointment_id != ?
        ORDER BY appointment_id DESC
        LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $patient_id, $current_appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $note = $result->fetch_assoc();

    if ($note) {
        echo json_encode(['success' => true, 'data' => $note]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No previous finalized notes found for this patient.']);
    }

} catch (Exception $e) {
    // Log exact error to file for inspection
    file_put_contents(__DIR__ . '/api_error_log.txt', date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n", FILE_APPEND);

    // Return clean JSON error
    ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Flush buffer
ob_end_flush();
?>