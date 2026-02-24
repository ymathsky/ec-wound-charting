<?php
// Filename: api/get_assessment_details.php
// Purpose: Fetches all detailed information for a single wound assessment record.

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

session_start();
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

require_once '../db_connect.php';

$assessment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assessment_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid Assessment ID provided."));
    exit();
}

try {
    // Select ALL fields from the wound_assessments table.
    // The front-end logic handles parsing JSON fields (like tunneling/periwound_condition)
    // JOIN with wounds and patients to get facility_id for auth check
    $sql = "SELECT wa.*, MIN(wi.image_path) as image_path, p.facility_id, w.location as wound_location, w.wound_type
            FROM wound_assessments wa
            JOIN wounds w ON wa.wound_id = w.wound_id
            JOIN patients p ON w.patient_id = p.patient_id
            LEFT JOIN wound_images wi ON wa.assessment_id = wi.assessment_id
            WHERE wa.assessment_id = ?
            GROUP BY wa.assessment_id";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $assessment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assessment_data = $result->fetch_assoc();
    $stmt->close();

    if ($assessment_data) {
        // Authorization Check for Facility Users
        if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
            if ($assessment_data['facility_id'] != $_SESSION['ec_user_id']) {
                http_response_code(403);
                echo json_encode(array("message" => "Forbidden"));
                exit();
            }
        }

        http_response_code(200);
        // The front-end is expecting the direct associative array data.
        echo json_encode($assessment_data);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Assessment record not found."));
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "An error occurred: " . $e->getMessage()));
}
$conn->close();
?>