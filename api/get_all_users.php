<?php
// Filename: ec/api/get_all_users.php
// Description: Fetches all user data from the 'users' table.

header('Content-Type: application/json');
require_once '../db_connect.php'; // Path to database connection

$response = [
    'success' => false,
    'users' => [],
    'message' => 'An unknown error occurred.'
];

// Check if database connection is available
if (!isset($conn) || $conn->connect_error) {
    $response['message'] = 'Database connection failed: ' . ($conn->connect_error ?? 'N/A');
    echo json_encode($response);
    exit();
}

try {
    // Select necessary user data for the table view
    // IMPORTANT: Never fetch or expose password hashes
    $sql = "SELECT user_id, full_name, email, role, status FROM users ORDER BY full_name ASC";
    $result = $conn->query($sql);

    if ($result) {
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $response['success'] = true;
        $response['users'] = $users;
        $response['message'] = 'Users fetched successfully.';
    } else {
        $response['message'] = 'SQL query failed: ' . $conn->error;
    }

} catch (Exception $e) {
    // Catch any exceptions during the process
    $response['message'] = 'Server exception: ' . $e->getMessage();
} finally {
    // Close connection if it was successfully opened
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}

// Output the final JSON response
echo json_encode($response);
?>
