<?php
// Filename: ec/api/manage_order.php
require_once '../db_connect.php';

header('Content-Type: application/json');

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 1. SMART AUTHENTICATION CHECK ---
// We check multiple common session keys to find the logged-in user
$user_id = null;

if (isset($_SESSION['ec_user_id'])) {
    $user_id = $_SESSION['ec_user_id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['id'])) {
    $user_id = $_SESSION['id'];
}

// If no user ID found, return Unauthorized
if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: User not logged in.',
        'debug' => array_keys($_SESSION) // Helps debug what keys ARE set
    ]);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    // --- 2. CREATE NEW ORDER ---
    if ($action === 'create_order') {
        $patient_id = intval($_POST['patient_id']);
        $wound_id = !empty($_POST['wound_id']) ? intval($_POST['wound_id']) : NULL;
        $order_type = $_POST['order_type'];
        $order_name = $_POST['order_name'];
        $priority = $_POST['priority'];

        if (!$patient_id) throw new Exception("Patient ID is required.");

        // Debug logging
        error_log("Creating Order: Patient=$patient_id, User=$user_id, Type=$order_type, Priority=$priority");

        $stmt = $conn->prepare("INSERT INTO patient_orders (patient_id, wound_id, user_id, order_type, order_name, priority, status) VALUES (?, ?, ?, ?, ?, ?, 'Ordered')");

        if (!$stmt) throw new Exception("Database prepare failed: " . $conn->error);

        $stmt->bind_param("iissss", $patient_id, $wound_id, $user_id, $order_type, $order_name, $priority);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Order created successfully']);
        } else {
            error_log("Order Creation Failed: " . $stmt->error);
            throw new Exception("Database execution failed: " . $stmt->error);
        }
    }

    // --- 3. GET ORDERS (With Wound Links) ---
    elseif ($action === 'get_orders') {
        $patient_id = intval($_POST['patient_id']);

        // Fetch orders with joined Wound and User details
        $query = "SELECT po.*, 
                         w.location as wound_location, 
                         w.wound_type as wound_type_desc, 
                         u.full_name as ordered_by 
                  FROM patient_orders po 
                  LEFT JOIN wounds w ON po.wound_id = w.wound_id 
                  LEFT JOIN users u ON po.user_id = u.user_id
                  WHERE po.patient_id = ? 
                  ORDER BY po.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $orders = $result->fetch_all(MYSQLI_ASSOC);

        echo json_encode(['success' => true, 'data' => $orders]);
    }

    // --- 4. UPLOAD RESULT ---
    elseif ($action === 'upload_result') {
        $order_id = intval($_POST['order_id']);
        $result_notes = $_POST['result_notes'] ?? '';
        $file_path = NULL;

        // File Upload Logic
        if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] == 0) {
            $upload_dir = '../uploads/patient_documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_ext = pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION);
            $new_name = 'lab_' . $order_id . '_' . uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['result_file']['tmp_name'], $target_file)) {
                $file_path = 'uploads/patient_documents/' . $new_name;
            }
        }

        if ($file_path) {
            $stmt = $conn->prepare("UPDATE patient_orders SET status = 'Results Received', result_notes = ?, result_document_path = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->bind_param("ssi", $result_notes, $file_path, $order_id);
        } else {
            $stmt = $conn->prepare("UPDATE patient_orders SET status = 'Results Received', result_notes = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->bind_param("si", $result_notes, $order_id);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Result uploaded']);
        } else {
            throw new Exception("Update failed.");
        }
    }

    // --- 5. UPDATE STATUS ---
    elseif ($action === 'update_status') {
        $order_id = intval($_POST['order_id']);
        $status = $_POST['status'];

        $stmt = $conn->prepare("UPDATE patient_orders SET status = ? WHERE order_id = ?");
        $stmt->bind_param("si", $status, $order_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Status update failed.");
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>