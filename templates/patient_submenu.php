<?php // ec/templates/patient_submenu.php
// This submenu assumes $patient_id, $patient_name, and $patient_dob are available in the parent page
if (!isset($patient_id) || !isset($patient_name) || !isset($patient_dob)) {
    // Fallback if variables aren't set, though they should be.
    if (isset($_GET['patient_id'])) {
        $patient_id = (int)$_GET['patient_id'];

        // Fetch minimal details if not provided by parent
        $minimal_sql = "SELECT first_name, last_name, date_of_birth FROM patients WHERE patient_id = ?";
        $minimal_stmt = $conn->prepare($minimal_sql);
        $minimal_stmt->bind_param("i", $patient_id);
        $minimal_stmt->execute();
        $minimal_result = $minimal_stmt->get_result();
        $minimal_patient = $minimal_result->fetch_assoc();

        if ($minimal_patient) {
            $patient_name = htmlspecialchars($minimal_patient['first_name'] . ' ' . $minimal_patient['last_name']);
            $patient_dob = htmlspecialchars($minimal_patient['date_of_birth']);
        } else {
            $patient_name = "Unknown Patient";
            $patient_dob = "N/A";
        }
    } else {
        // No patient ID at all
        echo "<div class='alert alert-danger'>Error: Patient context is missing.</div>";
        return; // Stop rendering the submenu
    }
}

$current_page = basename($_SERVER['PHP_SELF']);

// Calculate Age
$dob = new DateTime($patient_dob);
$now = new DateTime();
$age = $now->diff($dob)->y;
?>

<div class="patient-header-bar sticky-top">
    <div class="patient-info">
        <h3><?php echo $patient_name; ?></h3>
        <p class="mb-0">DOB: <?php echo $patient_dob; ?> (Age: <?php echo $age; ?>) | Patient ID: <?php echo $patient_id; ?></p>
    </div>
    <div class="patient-submenu">
        <a href="patient_profile.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_profile.php') ? 'active' : ''; ?>">
            Profile
        </a>
        <a href="patient_medication.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_medication.php') ? 'active' : ''; ?>">
            Medication
        </a>
        <a href="patient_labs.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_labs.php') ? 'active' : ''; ?>">
            Labs
        </a>
        <a href="patient_communication.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_communication.php') ? 'active' : ''; ?>">
            Communication
        </a>
        <a href="patient_appointments.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_appointments.php') ? 'active' : ''; ?>">
            Appointments
        </a>
        <a href="patient_chart_history.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_chart_history.php') ? 'active' : ''; ?>">
            Chart History
        </a>
        <a href="patient_billing.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_billing.php') ? 'active' : ''; ?>">
            Billing
        </a>
        <a href="patient_emr.php?patient_id=<?php echo $patient_id; ?>"
           class="<?php echo ($current_page == 'patient_emr.php') ? 'active' : ''; ?>">
            Documents
        </a>
    </div>
</div>