<?php // ec/api/create_order.php
session_start();
include '../db_connect.php';

// Check user authentication and authorization
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Get user_id from session
$user_id = $_SESSION['user_id'];

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true);

// Basic validation
if (
    !isset($data['patient_id']) ||
    !isset($data['appointment_id']) ||
    !isset($data['order_type']) ||
    !isset($data['order_name'])
) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields: patient_id, appointment_id, order_type, or order_name'
    ]);
    exit;
}

$patient_id = $data['patient_id'];
$appointment_id = $data['appointment_id'];
$order_type = $data['order_type'];
$order_name = $data['order_name'];

try {
    // Prepare the SQL statement
    $sql = "INSERT INTO patient_orders (patient_id, appointment_id, user_id, order_type, order_name, status) 
            VALUES (?, ?, ?, ?, ?, 'Ordered')";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    // Bind parameters
    // 'iiiss' corresponds to the data types:
    // patient_id (int), appointment_id (int), user_id (int), order_type (string), order_name (string)
    $stmt->bind_param("iiiss", $patient_id, $appointment_id, $user_id, $order_type, $order_name);

    // Execute the statement
    if ($stmt->execute()) {
        http_response_code(201); // 201 Created
        echo json_encode([
            'success' => true,
            'message' => 'Order created successfully',
            'order_id' => $stmt->insert_id // Return the new order's ID
        ]);
    } else {
        throw new Exception('Failed to execute statement: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>

