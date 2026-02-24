<?php
// Filename: api/get_cpt_suggestions.php
// Purpose: Analyzes wound assessments for an appointment and suggests CPT codes.

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
    // 1. Fetch all wound assessments for this visit
    // We need Wound ID, Location, Dimensions, Debridement details
    $sql = "SELECT wa.*, w.location, w.wound_type 
            FROM wound_assessments wa
            JOIN wounds w ON wa.wound_id = w.wound_id
            WHERE wa.appointment_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $suggestions = [];
    $total_sq_cm_by_depth = [
        'subq' => 0,
        'muscle' => 0,
        'bone' => 0,
        'biofilm' => 0
    ];

    $processed_wounds = [];

    while ($row = $result->fetch_assoc()) {
        // Calculate Area
        $length = floatval($row['length_cm']);
        $width = floatval($row['width_cm']);
        $area = $length * $width;

        // Only analyze if debridement was performed
        if ($row['debridement_performed'] === 'Yes') {
            $type = strtolower($row['debridement_type'] ?? '');
            // Mapping logic based on text fields - Assuming standard dropdown values
            // You might need to adjust these string matches based on your exact dropdown values in wound_assessment.php

            // Note: In a real scenario, Depth might be a specific field or inferred from "Tissue Removed".
            // Since wound_assessment.php has 'depth_cm' but not explicitly "Tissue Depth Removed" type (e.g. Muscle vs SubQ),
            // we will infer based on 'debridement_type' or assume a logic.
            // *** CRITICAL ASSUMPTION ***:
            // For this assistant to be accurate, we ideally need a "Debridement Level" field.
            // Since we don't have it, I will infer based on a hypothetical keyword match in 'treatments_provided'
            // OR simply default to 'Subcutaneous' (11042) if Sharp is selected, as a safe baseline.
            // Let's check 'treatments_provided' for keywords like "muscle", "bone", "fascia".

            $notes = strtolower($row['treatments_provided'] ?? '');
            $is_sharp = (strpos($type, 'sharp') !== false || strpos($type, 'excisional') !== false);

            if ($is_sharp) {
                if (strpos($notes, 'bone') !== false) {
                    $total_sq_cm_by_depth['bone'] += $area;
                    $processed_wounds[] = "Wound {$row['location']} ({$area} cm²) - Bone";
                } elseif (strpos($notes, 'muscle') !== false || strpos($notes, 'fascia') !== false) {
                    $total_sq_cm_by_depth['muscle'] += $area;
                    $processed_wounds[] = "Wound {$row['location']} ({$area} cm²) - Muscle";
                } else {
                    // Default to Subcutaneous for Sharp
                    $total_sq_cm_by_depth['subq'] += $area;
                    $processed_wounds[] = "Wound {$row['location']} ({$area} cm²) - SubQ";
                }
            } elseif (strpos($type, 'mechanical') !== false || strpos($type, 'enzymatic') !== false || strpos($type, 'autolytic') !== false) {
                // Non-excisional debridement usually 97597
                $total_sq_cm_by_depth['biofilm'] += $area;
                $processed_wounds[] = "Wound {$row['location']} ({$area} cm²) - Selective/Biofilm";
            }
        }
    }

    // 2. Generate Codes based on Aggregated Totals (CPT rules aggregate area by depth)

    // Rule: Subcutaneous (11042 / 11045)
    if ($total_sq_cm_by_depth['subq'] > 0) {
        $sq = $total_sq_cm_by_depth['subq'];
        $suggestions[] = [
            'code' => '11042',
            'description' => 'Debridement, subcutaneous tissue (first 20 sq cm)',
            'reason' => "Total Sharp SubQ area: {$sq} cm²",
            'quantity' => 1
        ];
        if ($sq > 20) {
            $add_on_units = ceil(($sq - 20) / 20);
            $suggestions[] = [
                'code' => '11045',
                'description' => 'Debridement, subq tissue (each addl 20 sq cm)',
                'reason' => "Additional area > 20cm²",
                'quantity' => $add_on_units
            ];
        }
    }

    // Rule: Muscle (11043 / 11046)
    if ($total_sq_cm_by_depth['muscle'] > 0) {
        $sq = $total_sq_cm_by_depth['muscle'];
        $suggestions[] = [
            'code' => '11043',
            'description' => 'Debridement, muscle/fascia (first 20 sq cm)',
            'reason' => "Total Muscle area: {$sq} cm²",
            'quantity' => 1
        ];
        if ($sq > 20) {
            $add_on_units = ceil(($sq - 20) / 20);
            $suggestions[] = [
                'code' => '11046',
                'description' => 'Debridement, muscle/fascia (each addl 20 sq cm)',
                'reason' => "Additional area > 20cm²",
                'quantity' => $add_on_units
            ];
        }
    }

    // Rule: Bone (11044 / 11047)
    if ($total_sq_cm_by_depth['bone'] > 0) {
        $sq = $total_sq_cm_by_depth['bone'];
        $suggestions[] = [
            'code' => '11044',
            'description' => 'Debridement, bone (first 20 sq cm)',
            'reason' => "Total Bone area: {$sq} cm²",
            'quantity' => 1
        ];
        if ($sq > 20) {
            $add_on_units = ceil(($sq - 20) / 20);
            $suggestions[] = [
                'code' => '11047',
                'description' => 'Debridement, bone (each addl 20 sq cm)',
                'reason' => "Additional area > 20cm²",
                'quantity' => $add_on_units
            ];
        }
    }

    // Rule: Selective (97597 / 97598)
    if ($total_sq_cm_by_depth['biofilm'] > 0) {
        $sq = $total_sq_cm_by_depth['biofilm'];
        $suggestions[] = [
            'code' => '97597',
            'description' => 'Debridement, open wound (first 20 sq cm)',
            'reason' => "Total Selective area: {$sq} cm²",
            'quantity' => 1
        ];
        if ($sq > 20) {
            $add_on_units = ceil(($sq - 20) / 20);
            $suggestions[] = [
                'code' => '97598',
                'description' => 'Debridement, open wound (each addl 20 sq cm)',
                'reason' => "Additional area > 20cm²",
                'quantity' => $add_on_units
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'suggestions' => $suggestions,
        'debug_wounds' => $processed_wounds
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>