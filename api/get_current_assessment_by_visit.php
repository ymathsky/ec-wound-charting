<?php
// Filename: api/get_current_assessment_by_visit.php
// Description: Fetches the most recent, in-progress wound assessment draft for a specific wound and appointment.

header("Content-Type: application/json; charset=UTF-8");
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Unauthorized."));
    exit();
}

// --- Input Validation ---
$wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($wound_id <= 0 || $appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Invalid Wound ID or Appointment ID provided."));
    exit();
}

try {
    // --- Authorization Check ---
    if ($_SESSION['ec_role'] === 'facility') {
        $sql_check = "SELECT p.facility_id 
                      FROM wounds w 
                      JOIN patients p ON w.patient_id = p.patient_id 
                      WHERE w.wound_id = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("i", $wound_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        $check_data = $result_check->fetch_assoc();
        $stmt_check->close();

        if (!$check_data || $check_data['facility_id'] != $_SESSION['ec_user_id']) {
            http_response_code(403);
            echo json_encode(array("success" => false, "message" => "Forbidden. You do not have access to this wound."));
            exit();
        }
    }

    // --- Query to find the most recent assessment for the given wound and appointment ---
    // We order by assessment_id DESC or created_at DESC to get the latest draft.
    // Assuming the 'create_assessment.php' handles both initial creation and subsequent updates,
    // the latest entry should represent the current state/draft.

    $sql = "SELECT wa.*, 
                   (SELECT image_id FROM wound_images wi WHERE wi.assessment_id = wa.assessment_id ORDER BY uploaded_at DESC LIMIT 1) AS image_id
            FROM wound_assessments wa 
            WHERE wa.wound_id = ? AND wa.appointment_id = ?
            ORDER BY wa.created_at DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $wound_id, $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment_data = $result->fetch_assoc();
    $stmt->close();

    if ($assessment_data) {
        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "message" => "Active assessment draft found.",
            "assessment" => $assessment_data
        ));
    } else {
        // No assessment found for this specific wound/appointment combination
        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "message" => "No active assessment draft found for this visit.",
            "assessment" => null
        ));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("success" => false, "message" => "An error occurred on the server.", "error" => $e->getMessage()));
}
$conn->close();
?>
