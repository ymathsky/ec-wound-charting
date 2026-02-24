<?php
// Filename: delete_todays_appointments.php
// Description: Delete all appointments scheduled for today
require_once 'db_connect.php';

// Get application timezone from config
$app_timezone = 'America/New_York'; // Default, adjust if different

// Get today's date in the application timezone
$today = new DateTime('now', new DateTimeZone($app_timezone));
$today_start = $today->format('Y-m-d 00:00:00');
$today_end = $today->format('Y-m-d 23:59:59');

echo "<h2>Delete Today's Appointments</h2>";
echo "<p>Today's date: " . $today->format('Y-m-d') . " (Timezone: $app_timezone)</p>";

// First, check how many appointments exist for today
$check_sql = "SELECT COUNT(*) as count FROM appointments 
              WHERE appointment_date >= ? AND appointment_date <= ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('ss', $today_start, $today_end);
$check_stmt->execute();
$result = $check_stmt->get_result();
$row = $result->fetch_assoc();
$count = $row['count'];

echo "<p>Found <strong>$count</strong> appointments for today.</p>";

if ($count > 0) {
    // Show the appointments before deleting
    $list_sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_type, 
                        p.first_name, p.last_name, u.full_name as clinician_name
                 FROM appointments a
                 LEFT JOIN patients p ON a.patient_id = p.patient_id
                 LEFT JOIN users u ON a.user_id = u.user_id
                 WHERE a.appointment_date >= ? AND a.appointment_date <= ?
                 ORDER BY a.appointment_date";
    $list_stmt = $conn->prepare($list_sql);
    
    if (!$list_stmt) {
        die("Error preparing statement: " . $conn->error);
    }
    
    $list_stmt->bind_param('ss', $today_start, $today_end);
    $list_stmt->execute();
    $list_result = $list_stmt->get_result();
    
    echo "<h3>Appointments to be deleted:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Date/Time</th><th>Patient</th><th>Clinician</th><th>Type</th></tr>";
    
    while ($appt = $list_result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($appt['appointment_id']) . "</td>";
        echo "<td>" . htmlspecialchars($appt['appointment_date']) . "</td>";
        echo "<td>" . htmlspecialchars($appt['first_name'] . ' ' . $appt['last_name']) . "</td>";
        echo "<td>" . htmlspecialchars($appt['clinician_name']) . "</td>";
        echo "<td>" . htmlspecialchars($appt['appointment_type']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Delete the appointments
    $delete_sql = "DELETE FROM appointments WHERE appointment_date >= ? AND appointment_date <= ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('ss', $today_start, $today_end);
    
    if ($delete_stmt->execute()) {
        $deleted = $delete_stmt->affected_rows;
        echo "<p style='color: green; font-weight: bold;'>✓ Successfully deleted $deleted appointments for today.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Error deleting appointments: " . $conn->error . "</p>";
    }
    
    $delete_stmt->close();
    $list_stmt->close();
} else {
    echo "<p>No appointments to delete.</p>";
}

$check_stmt->close();
$conn->close();

echo "<br><a href='appointments_calendar.php'>← Back to Calendar</a>";
?>
