<?php
// ec/db_connect.php
// --- Environment & Database Connection --

// 1. --- NEW: Securely Load Environment Variables ---
/**
 * Loads environment variables from a .env file.
 * @param string $path The path to the .env file.
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        // In a production environment, you should die() or throw an Exception here
        // if the .env file is mission-critical.
        error_log(".env file not found at path: " . $path);
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Check if the line contains an '=' character before trying to explode it.
        if (strpos($line, '=') === false) {
            continue; // Skip this line, it's not a valid key-value pair
        }

        // Split at the first '='
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Remove surrounding quotes (if any)
        if (strlen($value) > 1 && $value[0] == '"' && $value[strlen($value) - 1] == '"') {
            $value = substr($value, 1, -1);
        }

        // Set as environment variable for this script execution
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load the .env file (assuming it's in the same 'ec' directory)
loadEnv(__DIR__ . '/.env');


// 2. Define Connection Variables from Environment
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'ec_wound';

// 3. Define API Configuration from Environment
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY'));
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY'));


// 4. Create a New MySQLi Connection
// Disable default exception throwing for mysqli to allow manual error handling below (PHP 8.1+ compatibility)
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// 5. Check for Connection Errors
if ($conn->connect_error) {
    // Log the error for debugging
    error_log('Database Connection Error (' . $conn->connect_errno . '): ' . $conn->connect_error);

    // In case of a full connection failure, we set a default timezone
    date_default_timezone_set('UTC');
    // We intentionally do not exit here to allow non-DB-reliant pages to load.
}

// Optional: Set character set to utf8mb4 for full Unicode support
if ($conn->connect_error === null) {
    $conn->set_charset("utf8mb4");
}

// 6. --- NEW: Fetch and Set Global Timezone from Database ---

// Fallback to a safe default 'UTC'
$app_timezone = 'UTC';

if ($conn->connect_error === null) {
    try {
        // Query the settings table for the timezone
        $result = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'app_timezone'");

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $db_timezone = $row['setting_value'];

            // Use the database value if it is valid. @date_default_timezone_set prevents warnings for bad values.
            if (!empty($db_timezone) && @date_default_timezone_set($db_timezone)) {
                $app_timezone = $db_timezone;
            }
        }
    } catch (mysqli_sql_exception $e) {
        // This catch block specifically handles the "Table 'ec_wound.settings' doesn't exist" error.
        error_log("Timezone retrieval failed: " . $e->getMessage() . ". Using default timezone: " . $app_timezone);
        // Fallback is already set to 'UTC', so we do nothing here except log the warning.
    }
}

// Apply the determined timezone globally for all PHP date/time functions
date_default_timezone_set($app_timezone);

// IMPORTANT: The $conn variable remains open for use in other files.
// All subsequent PHP date() and DateTime functions will use $app_timezone.