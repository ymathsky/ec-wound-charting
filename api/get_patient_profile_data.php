<?php
// Filename: ec/api/get_patient_profile_data.php

// --- API Headers ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// --- Start Session & Check Authentication ---
session_start();
if (!isset($_SESSION['ec_user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized access. Please log in."));
    exit();
}

// --- Include Database Connection ---
require_once '../db_connect.php';

// --- Get Patient ID from Request ---
$patient_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($patient_id <= 0) {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid patient ID."));
    exit();
}

try {
    // --- Fetch Patient Details ---
    $patient_sql = "SELECT 
                        p.*, 
                        u.full_name as primary_doctor_name
                    FROM patients p
                    LEFT JOIN users u ON p.primary_user_id = u.user_id
                    WHERE p.patient_id = ? LIMIT 1";
    $stmt = $conn->prepare($patient_sql);
    if (!$stmt) throw new Exception("SQL Prepare failed for patient details: " . $conn->error);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Patient not found."));
        $stmt->close();
        $conn->close();
        exit();
    }
    $patient_details = $result->fetch_assoc();
    $stmt->close();

    // --- Authorization Check for Facility Users ---
    if (isset($_SESSION['ec_role']) && $_SESSION['ec_role'] === 'facility') {
        // Ensure the patient is assigned to this facility
        if ($patient_details['facility_id'] != $_SESSION['ec_user_id']) {
            http_response_code(403);
            echo json_encode(array("message" => "Forbidden: You do not have permission to view this patient."));
            $conn->close();
            exit();
        }
    }

    // --- OPTIMIZED: Fetch Wounds with Latest Assessment Data via JOIN ---
    // Instead of correlated subqueries for each field, we join on the latest assessment ID per wound.

    $wounds_sql = "
        SELECT 
            w.wound_id, w.location, w.wound_type, w.diagnosis, w.date_onset, w.status,
            wa.assessment_id as latest_assessment_id,
            wa.assessment_date as latest_assessment_date,
            CONCAT('L:', IFNULL(wa.length_cm, '-'), ' W:', IFNULL(wa.width_cm, '-'), ' D:', IFNULL(wa.depth_cm, '-')) as latest_dimensions,
            CONCAT(IFNULL(wa.exudate_amount, ''), ' ', IFNULL(wa.exudate_type, '')) as latest_exudate_summary,
            wa.periwound_condition as latest_periwound,
            wa.clinician_assessment as latest_assessment_text,
            wi.image_path as latest_image_path
        FROM wounds w
        -- 1. Find the ID of the latest assessment for each wound
        LEFT JOIN (
            SELECT wound_id, MAX(assessment_id) as max_assessment_id
            FROM wound_assessments
            GROUP BY wound_id
        ) latest_wa_ids ON w.wound_id = latest_wa_ids.wound_id
        -- 2. Join back to get the actual assessment details
        LEFT JOIN wound_assessments wa ON latest_wa_ids.max_assessment_id = wa.assessment_id
        -- 3. Find the ID of the latest image for each wound
        LEFT JOIN (
            SELECT wound_id, image_path 
            FROM wound_images 
            WHERE (wound_id, uploaded_at) IN (
                SELECT wound_id, MAX(uploaded_at)
                FROM wound_images
                GROUP BY wound_id
            )
        ) wi ON w.wound_id = wi.wound_id
        WHERE w.patient_id = ? 
        ORDER BY w.created_at DESC";

    $stmt_wounds = $conn->prepare($wounds_sql);
    if (!$stmt_wounds) throw new Exception("SQL Prepare failed for wounds: " . $conn->error);

    $stmt_wounds->bind_param("i", $patient_id);
    $stmt_wounds->execute();
    $wounds_result = $stmt_wounds->get_result();
    $wounds = $wounds_result->fetch_all(MYSQLI_ASSOC);
    $stmt_wounds->close();

    // --- Fetch Clinicians/Facilities ---
    $sql_users = "SELECT user_id, full_name FROM users WHERE role = 'clinician' AND status = 'active' ORDER BY full_name ASC";
    $result_users = $conn->query($sql_users);
    $clinicians = $result_users ? $result_users->fetch_all(MYSQLI_ASSOC) : [];

    $sql_facilities = "SELECT user_id, full_name FROM users WHERE role = 'facility' AND status = 'active' ORDER BY full_name ASC";
    $result_facilities = $conn->query($sql_facilities);
    $facility_users_raw = $result_facilities ? $result_facilities->fetch_all(MYSQLI_ASSOC) : [];

    $facilities = array_map(function($user) {
        return [
            'facility_id' => $user['user_id'],
            'name' => $user['full_name']
        ];
    }, $facility_users_raw);

    // Resolve Facility Name
    $facility_name = 'N/A';
    if ($patient_details['facility_id']) {
        foreach($facilities as $facility) {
            if ($facility['facility_id'] == $patient_details['facility_id']) {
                $facility_name = $facility['name'];
                break;
            }
        }
    }
    $patient_details['facility_name'] = $facility_name;

    // --- Fetch Timeline Data (Aggregated) ---
    // This was previously missing or mocked. Here is a real aggregation query.
    $timeline_events = [];

    // 1. Appointments
    $sql_appts = "SELECT appointment_id, appointment_date as timestamp, 'appointment' as type, status, appointment_type, 
                  (SELECT full_name FROM users WHERE user_id = a.user_id) as clinician_name 
                  FROM appointments a WHERE patient_id = ? ORDER BY appointment_date DESC LIMIT 5";
    $stmt = $conn->prepare($sql_appts);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $timeline_events[] = $row;

    // 2. Documents
    $sql_docs = "SELECT document_id, upload_date as timestamp, 'document' as type, document_type, file_name, file_path 
                 FROM patient_documents WHERE patient_id = ? ORDER BY upload_date DESC LIMIT 5";
    $stmt = $conn->prepare($sql_docs);
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()) $timeline_events[] = $row;

    // Sort merged timeline
    usort($timeline_events, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Combine all data
    $response_data = array(
        "details" => $patient_details,
        "wounds" => $wounds,
        "clinicians" => $clinicians,
        "facilities" => $facilities,
        "timeline_events" => array_slice($timeline_events, 0, 10) // Return top 10 recent events
    );

    http_response_code(200);
    echo json_encode($response_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Server Error fetching patient details.", "error" => $e->getMessage()));
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>