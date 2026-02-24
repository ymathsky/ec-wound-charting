<?php // ec/api/get_all_orders.php
session_start();
include '../db_connect.php';

// Check user authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Optional: Filter by status
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    $sql = "SELECT 
                po.order_id,
                po.patient_id,
                po.appointment_id,
                po.order_type,
                po.order_name,
                po.status,
                po.result_notes,
                po.result_document_path,
                DATE_FORMAT(po.created_at, '%Y-%m-%d %H:%i') as order_date,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                u.first_name AS provider_first_name,
                u.last_name AS provider_last_name
            FROM 
                patient_orders po
            JOIN 
                patients p ON po.patient_id = p.patient_id
            LEFT JOIN 
                users u ON po.ordered_by_user_id = u.user_id";

    if (!empty($status_filter)) {
        $sql .= " WHERE po.status = ?";
    }

    $sql .= " ORDER BY po.created_at DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    if (!empty($status_filter)) {
        $stmt->bind_param("s", $status_filter);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);

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
