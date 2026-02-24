<?php
// Filename: api/get_patient_details.php

// --- API Headers ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

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
    // --- Fetch Patient Demographics including Doctor/Facility IDs, PMH, and Allergies ---
    $patient_sql = "SELECT 
                        p.patient_id, p.first_name, p.last_name, p.date_of_birth, p.gender, 
                        p.contact_number, p.email, p.address,
                        p.primary_user_id, p.facility_id,
                        -- ENSURING CORE DATA (NAME/DOB) AND NEW FIELDS (PMH/ALLERGIES) ARE SELECTED
                        p.past_medical_history, 
                        p.allergies,
                        u.full_name as primary_doctor_name
                    FROM patients p
                    LEFT JOIN users u ON p.primary_user_id = u.user_id
                    WHERE p.patient_id = ? LIMIT 1";
    $stmt = $conn->prepare($patient_sql);

    if (!$stmt) {
        throw new Exception("SQL Prepare failed for patient details: " . $conn->error);
    }

    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(array("message" => "Patient not found."));
        // Ensure statement is closed if exiting early
        $stmt->close();
        exit();
    }

    $patient_details = $result->fetch_assoc();
    $stmt->close(); // Close statement immediately after use

    // --- Fetch Patient Wounds ---
    $wounds_sql = "SELECT 
                        w.wound_id, w.location, w.wound_type, w.date_onset, w.status,
                        (SELECT wa.assessment_id FROM wound_assessments wa WHERE wa.wound_id = w.wound_id ORDER BY wa.assessment_date DESC, wa.created_at DESC LIMIT 1) as latest_assessment_id,
                        (SELECT CONCAT('L:', IFNULL(wa.length_cm, '-'), ' W:', IFNULL(wa.width_cm, '-'), ' D:', IFNULL(wa.depth_cm, '-')) FROM wound_assessments wa WHERE wa.wound_id = w.wound_id ORDER BY wa.assessment_date DESC, wa.created_at DESC LIMIT 1) as latest_dimensions,
                        (SELECT CONCAT(IFNULL(wa.exudate_amount, ''), ' ', IFNULL(wa.exudate_type, '')) FROM wound_assessments wa WHERE wa.wound_id = w.wound_id ORDER BY wa.assessment_date DESC, wa.created_at DESC LIMIT 1) as latest_exudate_summary,
                        (SELECT wa.periwound_condition FROM wound_assessments wa WHERE wa.wound_id = w.wound_id ORDER BY wa.assessment_date DESC, wa.created_at DESC LIMIT 1) as latest_periwound
                    FROM wounds w
                    WHERE w.patient_id = ? 
                    ORDER BY w.created_at DESC";

    $stmt_wounds = $conn->prepare($wounds_sql);
    if (!$stmt_wounds) {
        throw new Exception("SQL Prepare failed for wounds: " . $conn->error);
    }

    $stmt_wounds->bind_param("i", $patient_id);
    $stmt_wounds->execute();
    $wounds_result = $stmt_wounds->get_result();

    $wounds = array();
    if ($wounds_result->num_rows > 0) {
        while($row = $wounds_result->fetch_assoc()) {
            $row['latest_exudate_summary'] = trim($row['latest_exudate_summary']);
            $wounds[] = $row;
        }
    }
    $stmt_wounds->close(); // Close statement immediately after use

    // --- Fetch ALL Clinicians for Dropdown ---
    $sql_users = "SELECT user_id, full_name FROM users WHERE role = 'clinician' AND status = 'active' ORDER BY full_name ASC";
    $result_users = $conn->query($sql_users);
    $clinicians = $result_users->fetch_all(MYSQLI_ASSOC);

    // --- Fetch ALL Facilities from Users Table (role = 'facility') ---
    $sql_facilities = "SELECT user_id, full_name FROM users WHERE role = 'facility' AND status = 'active' ORDER BY full_name ASC";
    $result_facilities = $conn->query($sql_facilities);
    $facility_users_raw = $result_facilities ? $result_facilities->fetch_all(MYSQLI_ASSOC) : [];

    // Map facility users to expected facility format (facility_id, name)
    $facilities = array_map(function($user) {
        return [
            'facility_id' => $user['user_id'], // Using user_id as the facility_id
            'name' => $user['full_name']
        ];
    }, $facility_users_raw);


    // Determine the patient's assigned facility name for display
    $facility_name = 'N/A';
    if ($patient_details['facility_id']) {
        $found_facility = array_filter($facilities, function($f) use ($patient_details) {
            return $f['facility_id'] == $patient_details['facility_id'];
        });
        $found_facility = array_values($found_facility);
        if (!empty($found_facility)) {
            $facility_name = $found_facility[0]['name'];
        }
    }
    $patient_details['facility_name'] = $facility_name;


    // Combine all data into a single response object
    $response_data = array(
        "details" => $patient_details,
        "wounds" => $wounds,
        "clinicians" => $clinicians,
        "facilities" => $facilities  // Now contains data fetched from users table
    );


    // --- Send Response ---
    http_response_code(200);
    echo json_encode($response_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Server Error fetching patient details.", "error" => $e->getMessage()));
} finally {
    // Ensure the connection is always closed
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
}
?>
