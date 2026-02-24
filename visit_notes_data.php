<?php
// Filename: visit_notes_data.php
// Purpose: Contains PHP functions and logic to fetch required data and construct
// the COMPREHENSIVE Chief Complaint (CC) for the visit_notes.php page.

// Ensure DB connection is available. Assumes db_connect.php is in the same directory.
if (!isset($conn) || $conn === null) {
    require_once 'db_connect.php';
}

// --- PHP Age Calculation Helper ---
function calculateAge($dob_string) {
    if (empty($dob_string)) return 'Age N/A';
    try {
        $birthDate = new DateTime($dob_string);
        $today = new DateTime('today');

        if ($birthDate > $today) {
            return '0';
        }

        $age = $birthDate->diff($today)->y;
        return $age;
    } catch (Exception $e) {
        return 'Age N/A';
    }
}

/**
 * Fetches patient data and constructs the Chief Complaint sentence.
 *
 * @param mysqli $conn The database connection object.
 * @param int $patient_id The ID of the current patient.
 * @return string The fully constructed Chief Complaint sentence.
 */
function getChiefComplaintSentence($conn, $patient_id) {
    $patient_details = [];
    $wounds = [];
    $social_history = []; // For social history
    $pmh = []; // For PMH

    try {
        // 1. Fetch Patient Demographics, PMH, Social, and Allergies
        // We fetch the JSON strings for PMH and Social History
        $patient_sql = "SELECT
                            first_name, last_name, date_of_birth,
                            past_medical_history, allergies, social_history
                        FROM patients
                        WHERE patient_id = ? LIMIT 1";
        $stmt = $conn->prepare($patient_sql);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient_details = $stmt->get_result()->fetch_assoc() ?? [];
        $stmt->close();

        // Decode JSON data
        if (!empty($patient_details['past_medical_history'])) {
            $pmh = json_decode($patient_details['past_medical_history'], true);
        }
        if (!empty($patient_details['social_history'])) {
            $social_history = json_decode($patient_details['social_history'], true);
        }


        // 2. Fetch Active Wounds
        $wounds_sql = "SELECT location, wound_type
                       FROM wounds
                       WHERE patient_id = ? AND status = 'Active'";
        $stmt_wounds = $conn->prepare($wounds_sql);
        $stmt_wounds->bind_param("i", $patient_id);
        $stmt_wounds->execute();
        $wounds = $stmt_wounds->get_result()->fetch_all(MYSQLI_ASSOC) ?? [];
        $stmt_wounds->close();

    } catch (Exception $e) {
        error_log("Database error during CC generation: " . $e->getMessage());
    }

    // --- Process Data ---
    $patient_first_name = $patient_details['first_name'] ?? '';
    $patient_last_name = $patient_details['last_name'] ?? '';

    if (empty($patient_first_name) && empty($patient_last_name)) {
        $patient_name = 'The Patient';
    } else {
        $patient_name = trim($patient_first_name . ' ' . $patient_last_name);
    }

    $patient_age = calculateAge($patient_details['date_of_birth'] ?? null);

    // --- Process Wounds ---
    $wound_types = [];
    if (!empty($wounds)) {
        foreach ($wounds as $wound) {
            if (!empty($wound['wound_type']) && !empty($wound['location'])) {
                $wound_types[] = $wound['wound_type'] . ' on the ' . $wound['location'];
            }
        }
    }

    $wound_summary = '';
    if (!empty($wound_types)) {
        $wound_list = '';
        if (count($wound_types) > 1) {
            $last_wound = array_pop($wound_types);
            $wound_list = implode(', ', $wound_types) . ', and a ' . $last_wound;
        } else {
            $wound_list = $wound_types[0];
        }
        $wound_summary = 'Presents for evaluation of existing wounds, including: ' . $wound_list . '.';
    } else {
        $wound_summary = 'No active wounds are currently documented.';
    }

    // --- Process PMH (from JSON) ---
    $pmh_text = '';
    // Check if $pmh is an array and the 'conditions' key exists and is not empty
    if (is_array($pmh) && !empty($pmh['conditions'])) {
        $pmh_text = 'Past Medical History includes: ' . str_replace("\n", ", ", $pmh['conditions']) . '.';
    } else if (!is_array($pmh) && !empty($pmh)) {
        // Fallback for old string data
        $pmh_text = 'Past Medical History includes: ' . str_replace("\n", ", ", $pmh) . '.';
    }

    // --- Process Allergies (simple string) ---
    $allergies_raw = trim($patient_details['allergies'] ?? '');
    $allergies_text = '';
    if (!empty($allergies_raw) && strtolower($allergies_raw) !== 'none') {
        $allergies_text = 'Known Allergies: ' . str_replace("\n", ", ", $allergies_raw) . '.';
    } else {
        $allergies_text = 'No Known Drug Allergies (NKDA).';
    }

    // --- Process Social History (from JSON) ---
    $social_text = '';
    $social_parts = [];
    if (is_array($social_history)) {
        if (!empty($social_history['tobacco_use'])) {
            $social_parts[] = 'Tobacco: ' . $social_history['tobacco_use'];
        }
        if (!empty($social_history['alcohol_use'])) {
            $social_parts[] = 'Alcohol: ' . $social_history['alcohol_use'];
        }
    }
    if (!empty($social_parts)) {
        $social_text = 'Social History: ' . implode('; ', $social_parts) . '.';
    }


    // --- Construct the Chief Complaint (CC) Sentence ---
    $cc_subject = $patient_age !== 'Age N/A' ? "{$patient_name}, a {$patient_age}-year-old," : "{$patient_name},";

    $cc_parts = [];
    $cc_parts[] = "The patient, {$cc_subject} is here for a scheduled visit.";
    $cc_parts[] = $wound_summary;

    if (!empty($pmh_text)) {
        $cc_parts[] = $pmh_text;
    }

    if (!empty($social_text)) {
        $cc_parts[] = $social_text;
    }

    if (!empty($allergies_text)) {
        $cc_parts[] = $allergies_text;
    }

    $chief_complaint_sentence = implode(' ', $cc_parts);

    // FINAL CLEANUP: Remove periods that might create double spaces and trim excess
    return preg_replace('/\s+/', ' ', trim($chief_complaint_sentence));
}

// Execute the function and set the variable used by visit_notes.php
$chief_complaint_sentence = getChiefComplaintSentence($conn, $patient_id);
?>