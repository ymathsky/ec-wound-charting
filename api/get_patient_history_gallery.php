<?php
// Filename: api/get_patient_history_gallery.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

session_start();
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

require_once '../db_connect.php';

$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid patient ID."));
    exit();
}

try {
    // --- Authorization Check for Facility Users ---
    if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
        $check_sql = "SELECT facility_id FROM patients WHERE patient_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $patient_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        
        if ($check_res->num_rows === 0) {
             // Patient not found, let the subsequent queries handle empty results or return 404 now
             // But for auth check, if patient doesn't exist, they definitely don't have access.
             http_response_code(404);
             echo json_encode(array("message" => "Patient not found."));
             exit();
        }
        
        $p_data = $check_res->fetch_assoc();
        if ($p_data['facility_id'] != $_SESSION['ec_user_id']) {
            http_response_code(403);
            echo json_encode(array("message" => "Forbidden"));
            exit();
        }
        $check_stmt->close();
    }

    // Defaults
    $type = isset($_GET['type']) ? $_GET['type'] : 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;

    $response = array("success" => true);

    // --- Helper to get count ---
    function getCount($conn, $sql, $types, $params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        return $row['total'];
    }

    // --- Assessments Logic ---
    if ($type === 'all' || $type === 'assessments') {
        $assess_limit = ($type === 'all') ? 5 : $limit;
        $assess_page = ($type === 'all') ? 1 : $page;
        $assess_offset = ($assess_page - 1) * $assess_limit;

        // Count
        $count_sql = "SELECT COUNT(*) as total FROM wound_assessments wa JOIN wounds w ON wa.wound_id = w.wound_id WHERE w.patient_id = ?";
        $total_assessments = getCount($conn, $count_sql, "i", [$patient_id]);

        // Data
        $sql_assessments = "
            SELECT 
                wa.*,
                w.location as wound_location,
                w.wound_type
            FROM wound_assessments wa
            JOIN wounds w ON wa.wound_id = w.wound_id
            WHERE w.patient_id = ?
            ORDER BY wa.assessment_date DESC, wa.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql_assessments);
        $stmt->bind_param("iii", $patient_id, $assess_limit, $assess_offset);
        $stmt->execute();
        $assessments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response['assessments'] = $assessments;
        $response['total_assessments'] = $total_assessments;
        $response['assessments_page'] = $assess_page;
        $response['assessments_limit'] = $assess_limit;
    }

    // --- Gallery Logic ---
    if ($type === 'all' || $type === 'gallery') {
        $gallery_limit = ($type === 'all') ? 8 : $limit;
        $gallery_page = ($type === 'all') ? 1 : $page;
        $gallery_offset = ($gallery_page - 1) * $gallery_limit;

        // Count
        $count_sql = "SELECT COUNT(*) as total FROM wound_images wi JOIN wounds w ON wi.wound_id = w.wound_id WHERE w.patient_id = ?";
        $total_images = getCount($conn, $count_sql, "i", [$patient_id]);

        // Data
        $sql_images = "
            SELECT 
                wi.*,
                w.location as wound_location,
                w.wound_type
            FROM wound_images wi
            JOIN wounds w ON wi.wound_id = w.wound_id
            WHERE w.patient_id = ?
            ORDER BY wi.uploaded_at DESC
            LIMIT ? OFFSET ?
        ";
        $stmt = $conn->prepare($sql_images);
        $stmt->bind_param("iii", $patient_id, $gallery_limit, $gallery_offset);
        $stmt->execute();
        $images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $response['images'] = $images;
        $response['total_images'] = $total_images;
        $response['gallery_page'] = $gallery_page;
        $response['gallery_limit'] = $gallery_limit;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Server Error: " . $e->getMessage()));
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>