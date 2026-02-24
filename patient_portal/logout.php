<?php
session_start();
// Unset only portal variables to avoid killing clinician session if testing in same browser
unset($_SESSION['portal_patient_id']);
unset($_SESSION['portal_patient_name']);
header("Location: login.php");
exit();
?>