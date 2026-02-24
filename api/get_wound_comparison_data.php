<?php
// Filename: ec/api/get_wound_comparison_data.php
// Purpose: Fetches all assessment and image history for a specific wound ID, structured for client-side comparison.

require_once(__DIR__ . '/../db_connect.php');
header('Content-Type: application/json');

$wound_id = isset($_GET['wound_id']) ? intval($_GET['wound_id']) : 0;

if ($wound_id <= 0) {
    echo json_encode(["success" => false, "message" => "Invalid Wound ID."]);
    exit();
}

$conn->begin_transaction();
$response = ["success" => false, "message" => "", "location" => "", "wound_type" => "", "assessments" => []];

try {
    // --- 1. Fetch Wound Details ---
    $sql_wound = "SELECT location, wound_type FROM wounds WHERE wound_id = ?";
    $stmt_wound = $conn->prepare($sql_wound);
    $stmt_wound->bind_param("i", $wound_id);
    $stmt_wound->execute();
    $wound_details = $stmt_wound->get_result()->fetch_assoc();
    $stmt_wound->close();

    if (!$wound_details) {
        $response["message"] = "Wound not found.";
        echo json_encode($response);
        exit();
    }

    $response["location"] = $wound_details['location'];
    $response["wound_type"] = $wound_details['wound_type'];

    // --- 2. Fetch All Assessments for the Wound ---
    $sql_assessments = "
        SELECT assessment_id, assessment_date, length_cm, width_cm, depth_cm, computed_area_cm2, 
               granulation_percent, slough_percent, eschar_percent, exudate_type, exudate_amount,
               odor_present, periwound_condition, signs_of_infection
        FROM wound_assessments
        WHERE wound_id = ?
        ORDER BY assessment_date DESC";

    $stmt_assessments = $conn->prepare($sql_assessments);
    $stmt_assessments->bind_param("i", $wound_id);
    $stmt_assessments->execute();
    $assessments = $stmt_assessments->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_assessments->close();

    if (empty($assessments)) {
        $response["success"] = true;
        $response["message"] = "Wound found, but no assessments recorded.";
        $response["assessments"] = [];
        echo json_encode($response);
        exit();
    }

    // --- 3. Nest Images into Assessments ---
    $assessment_ids = array_column($assessments, 'assessment_id');
    $images_map = [];

    // Fetch all images relevant to these assessments
    $placeholders = implode(',', array_fill(0, count($assessment_ids), '?'));
    $types = str_repeat('i', count($assessment_ids));

    $sql_images = "SELECT image_id, assessment_id, image_path, image_type
                   FROM wound_images
                   WHERE assessment_id IN ($placeholders)
                   ORDER BY uploaded_at ASC";

    $stmt_images = $conn->prepare($sql_images);
    $stmt_images->bind_param($types, ...$assessment_ids);
    $stmt_images->execute();
    $images_result = $stmt_images->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_images->close();

    // Organize images by assessment_id
    foreach ($images_result as $image) {
        $images_map[$image['assessment_id']][] = $image;
    }

    // Attach images to their respective assessments
    foreach ($assessments as &$assessment) {
        $assessment['wound_images'] = $images_map[$assessment['assessment_id']] ?? [];
    }
    unset($assessment); // Break reference to last element

    // --- 4. Final Success Response ---
    $conn->commit();
    $response["success"] = true;
    $response["message"] = "Assessment history loaded successfully.";
    $response["assessments"] = $assessments;
    echo json_encode($response);

} catch (Exception $e) {
    $conn->rollback();
    $response["message"] = "Database error: " . $e->getMessage();
    http_response_code(500);
    echo json_encode($response);
}

$conn->close();
?>