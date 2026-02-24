<?php
// ec/api/update_order.php
session_start();
include '../db_connect.php';

// Check user authentication and authorization
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = $_POST['order_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $result_notes = $_POST['result_notes'] ?? '';

    if (!$order_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields: order_id or status']);
        exit;
    }

    // Begin transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Handle File Upload (PDF/Image of result)
        $file_path_sql = "";
        $params = [$status, $result_notes];
        $types = "ss";

        // Check if a file was uploaded without errors
        if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/order_results/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate a unique filename to prevent overwrites
            $fileExtension = pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION);
            $fileName = time() . '_' . $order_id . '.' . $fileExtension;
            $targetPath = $uploadDir . $fileName;

            // Allowed file types
            $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array(strtolower($fileExtension), $allowedTypes)) {
                throw new Exception('Invalid file type. Only PDF, JPG, and PNG are allowed.');
            }

            if (move_uploaded_file($_FILES['result_file']['tmp_name'], $targetPath)) {
                // Save relative path for DB
                $dbPath = 'uploads/order_results/' . $fileName;
                $file_path_sql = ", result_document_path = ?";
                $params[] = $dbPath;
                $types .= "s";
            } else {
                throw new Exception('Failed to move uploaded file.');
            }
        }

        // Add order_id to params
        $params[] = $order_id;
        $types .= "i";

        // Prepare the update statement
        $sql = "UPDATE patient_orders SET status = ?, result_notes = ?, updated_at = NOW() $file_path_sql WHERE order_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Order updated successfully';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request method';
}

echo json_encode($response);
?>