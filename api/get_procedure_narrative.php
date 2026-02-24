<?php
// Filename: api/get_procedure_narrative.php
// Purpose: Generates a natural language narrative of procedures based on saved CPT codes.

session_start();
require_once '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['ec_user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

if ($appointment_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Appointment ID']);
    exit;
}

try {
    // Fetch services linked to this appointment, joined with CPT descriptions
    $sql = "SELECT s.cpt_code, s.units, c.description 
            FROM superbill_services s
            LEFT JOIN cpt_codes c ON s.cpt_code = c.code
            WHERE s.appointment_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $services = [];
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    if (empty($services)) {
        echo json_encode(['success' => true, 'narrative' => 'No procedures recorded for this visit.']);
        exit;
    }

    // --- NARRATIVE GENERATION LOGIC ---
    $narrative_parts = [];

    // Group codes to handle primary + add-ons together
    $codes = array_column($services, 'cpt_code');

    // 1. Debridement Logic
    $debridement_performed = false;
    $depth = '';
    $area = 0;

    // Check for specific debridement codes
    foreach ($services as $s) {
        $code = $s['cpt_code'];
        $units = intval($s['units']);

        if ($code === '11042') {
            $debridement_performed = true;
            $depth = 'subcutaneous tissue';
            $area += 20; // Base
        } elseif ($code === '11043') {
            $debridement_performed = true;
            $depth = 'muscle and/or fascia';
            $area += 20;
        } elseif ($code === '11044') {
            $debridement_performed = true;
            $depth = 'bone';
            $area += 20;
        } elseif ($code === '11045') {
            $area += ($units * 20); // Add-on for SubQ
        } elseif ($code === '11046') {
            $area += ($units * 20); // Add-on for Muscle
        } elseif ($code === '11047') {
            $area += ($units * 20); // Add-on for Bone
        } elseif ($code === '97597') {
            $debridement_performed = true;
            $depth = 'biofilm/devitalized tissue (selective)';
            $area += 20;
        } elseif ($code === '97598') {
            $area += ($units * 20); // Add-on for Selective
        }
    }

    if ($debridement_performed) {
        $method = (strpos($depth, 'selective') !== false) ? "Selective debridement" : "Sharp excisional debridement";
        $narrative_parts[] = "$method was performed down to the level of $depth. Devitalized tissue was removed from a total wound surface area of approximately $area sq cm using scalpel/curette/forceps. Hemostasis was achieved.";
    }

    // 2. Skin Substitute Logic
    $skin_sub_performed = false;
    $site = '';
    $sub_area = 0;

    foreach ($services as $s) {
        $code = $s['cpt_code'];
        $units = intval($s['units']);

        if ($code === '15271' || $code === '15275') {
            $skin_sub_performed = true;
            $site = ($code === '15275') ? 'face/scalp/feet' : 'trunk/arms/legs';
            $sub_area += 25;
        }
        // Add logic for add-on codes 15272, 15276 if used later
    }

    if ($skin_sub_performed) {
        $narrative_parts[] = "Application of skin substitute graft was performed on the $site. The wound bed was prepared, and the graft was applied and secured.";
    }

    // 3. Evaluation & Management (E/M)
    foreach ($services as $s) {
        if (in_array($s['cpt_code'], ['99201','99202','99203','99204','99205','99211','99212','99213','99214','99215'])) {
            $narrative_parts[] = "Evaluation and management service ({$s['cpt_code']}) was provided, including history taking, examination, and medical decision making.";
        }
    }

    // 4. Catch-all for other procedures
    // If we haven't covered a code in the specific logic above, list it generically.
    $processed_codes = ['11042','11043','11044','11045','11046','11047','97597','97598','15271','15275','99201','99202','99203','99204','99205','99211','99212','99213','99214','99215'];

    foreach ($services as $s) {
        if (!in_array($s['cpt_code'], $processed_codes)) {
            $desc = !empty($s['description']) ? $s['description'] : "Procedure {$s['cpt_code']}";
            $narrative_parts[] = "$desc was performed.";
        }
    }

    $final_narrative = implode("\n\n", $narrative_parts);

    echo json_encode(['success' => true, 'narrative' => $final_narrative]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>