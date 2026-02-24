<?php
// Simple redirect stub to preserve any links to the old visit_patient.php
// Replace $target with your new visit workflow start page (e.g., 'todays_visit.php' or 'appointments.php')
$target = '/todays_visit.php'; // <-- change this to the correct new start page

// Optional: record in server logs for a while so you can spot traffic still using the old path
error_log("visit_patient.php redirecting to {$target} - REQUEST_URI=" . ($_SERVER['REQUEST_URI'] ?? '') . " - REMOTE_ADDR=" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Use a 302 while transition is in progress; switch to 301 if permanent later
header("Location: {$target}", true, 302);
exit;