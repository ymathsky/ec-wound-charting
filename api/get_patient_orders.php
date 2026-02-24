<?php // ec/api/get_patient_orders.php
session_start();
include '../db_connect.php';

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if patient_id is provided
if (!isset($_GET['patient_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit;
}

$patient_id = intval($_GET['patient_id']);

try {
    // Prepare the SQL statement
    // We join with the users table to get the provider's name
    $sql = "SELECT 
                po.order_id,
                po.patient_id,
                po.appointment_id,
                po.order_type,
                po.order_name,
                po.status,
                po.result_notes,
                po.result_document_path,
                po.created_at,
                u.first_name AS provider_first_name,
                u.last_name AS provider_last_name
            FROM 
                patient_orders po
            LEFT JOIN 
                users u ON po.user_id = u.user_id
            WHERE 
                po.patient_id = ?
            ORDER BY 
                po.created_at DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("i", $patient_id);

    // Execute the statement
    $stmt->execute();

    $result = $stmt->get_result();
    $orders = [];

    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $orders]);

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

