<?php
// Filename: api/create_vitals.php
// FINALIZED: Uses REPLACE INTO to reliably enforce one vital record per visit (appointment_id).
// FIX: Updated to expect Imperial units (in, lbs, °F) from client and perform a single conversion to Metric (cm, kg, °C) before storage.

error_reporting(0); // Suppress PHP notices and warnings that break JSON output

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
require_once '../db_connect.php';

$data = json_decode(file_get_contents("php://input"));

// Sanitize and prepare IDs first
$patient_id = isset($data->patient_id) ? intval($data->patient_id) : 0;
$appointment_id = isset($data->appointment_id) ? intval($data->appointment_id) : 0;

// Essential validation: Reject if IDs are missing or invalid (0) to prevent the #1062 'Duplicate entry 0' error.
if ($patient_id <= 0 || $appointment_id <= 0) {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete or Invalid IDs. Patient ID ($patient_id) and Appointment ID ($appointment_id) must be greater than 0. Autosave stopped."]);
    exit();
}

// --- Unit Conversion Functions (Server-Side) ---
// These functions convert the Imperial inputs from the client into Metric values for the database.
/** Converts pounds (lbs) to kilograms (kg). */
function toKg($lbs) {
    return $lbs * 0.453592;
}
/** Converts inches (in) to centimeters (cm). */
function toCm($inches) {
    return $inches * 2.54;
}
/** Converts Fahrenheit (°F) to Celsius (°C). */
function toCelsius($fahrenheit) {
    return ($fahrenheit - 32) * 5/9;
}
// -------------------------------------------------

try {
    // --- Collect IMPERIAL Data from Client (Client sends what is in the form) ---
    // UPDATED: Parameters match the new form field names (height_in, weight_lbs, temperature_f)
    $height_in = isset($data->height_in) ? floatval($data->height_in) : null;
    $weight_lbs = isset($data->weight_lbs) ? floatval($data->weight_lbs) : null;
    $temperature_f = isset($data->temperature_f) ? floatval($data->temperature_f) : null;

    // Non-converted data
    $blood_pressure = isset($data->blood_pressure) ? htmlspecialchars(strip_tags($data->blood_pressure)) : null;
    $heart_rate = isset($data->heart_rate) ? intval($data->heart_rate) : null;
    $respiratory_rate = isset($data->respiratory_rate) ? intval($data->respiratory_rate) : null;
    $oxygen_saturation = isset($data->oxygen_saturation) ? intval($data->oxygen_saturation) : null;
    $visit_datetime = isset($data->visit_date) ? htmlspecialchars(strip_tags($data->visit_date)) : date("Y-m-d H:i:s");

    // BMI is calculated client-side in Imperial, so we pass it straight through if available.
    $bmi = isset($data->bmi) && is_numeric($data->bmi) ? floatval($data->bmi) : null;

    // --- CONVERSION: Imperial (Input) to Metric (DB Storage) ---
    // Only call conversion function once on the Imperial inputs.
    $height_cm = $height_in > 0 ? round(toCm($height_in), 2) : null;
    $weight_kg = $weight_lbs > 0 ? round(toKg($weight_lbs), 2) : null;
    $temperature_celsius = $temperature_f !== null && $temperature_f > 0 ? round(toCelsius($temperature_f), 2) : null;

    // --- REPLACE INTO LOGIC ---
    // This statement will replace the existing row that shares the same UNIQUE KEY (appointment_id).
    $sql = "REPLACE INTO patient_vitals 
            (patient_id, appointment_id, visit_date, height_cm, weight_kg, bmi, blood_pressure, heart_rate, respiratory_rate, temperature_celsius, oxygen_saturation) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    // Bind parameters
    $stmt->bind_param(
        "iisdddsiidi",
        $patient_id,
        $appointment_id,
        $visit_datetime,
        $height_cm,             // BIND: CONVERTED CM VALUE
        $weight_kg,             // BIND: CONVERTED KG VALUE
        $bmi,                   // BIND: BMI (PASSED THROUGH)
        $blood_pressure,
        $heart_rate,
        $respiratory_rate,
        $temperature_celsius,   // BIND: CONVERTED °C VALUE
        $oxygen_saturation
    );

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Vitals successfully replaced/updated (Autosave)."]);
    } else {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Unable to record vitals. (Server Error)", "error" => $e->getMessage()]);
}

$conn->close();
?>