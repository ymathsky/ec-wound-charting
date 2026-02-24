<?php
// visit_status_check.php
// Checks if the current appointment is signed/finalized.
// Requires $conn and $appointment_id to be set before inclusion.

$is_visit_signed = false;
$signed_by_user = '';
$signed_at_date = '';

if (isset($conn) && isset($appointment_id) && $appointment_id > 0) {
    $stmt_status = $conn->prepare("SELECT is_signed, finalized_by, finalized_at FROM visit_notes WHERE appointment_id = ?");
    if ($stmt_status) {
        $stmt_status->bind_param("i", $appointment_id);
        $stmt_status->execute();
        $res_status = $stmt_status->get_result();
        if ($row_status = $res_status->fetch_assoc()) {
            $is_visit_signed = (bool)$row_status['is_signed'];
            $signed_by_user = $row_status['finalized_by'];
            $signed_at_date = $row_status['finalized_at'];
        }
        $stmt_status->close();
    }
}
?>