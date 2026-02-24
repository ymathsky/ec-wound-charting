<?php
// Filename: ec/api/get_soap_checklist.php
header("Content-Type: application/json; charset=UTF-8");

// Adjust path if necessary depending on your server structure
// Assuming this file is in ec/api/ and db_connect is in ec/
require_once '../db_connect.php';

try {
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT soap_section, category, title, item_text 
            FROM soap_checklist_items
            WHERE is_active = 1
            ORDER BY soap_section, category, title, display_order";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $items = $result->fetch_all(MYSQLI_ASSOC);

    // Initialize structure
    $checklist_data = [
        'chief_complaint' => [],
        'subjective' => [],
        'objective' => [],
        'assessment' => [],
        'plan' => [],
        'lab_orders' => [],
        'imaging_orders' => [],
        'skilled_nurse_orders' => []
    ];

    foreach ($items as $item) {
        $section = strtolower($item['soap_section']); // Ensure lowercase matches keys
        $category = $item['category'];

        // Initialize section if it doesn't exist (dynamic handling)
        if (!isset($checklist_data[$section])) {
            $checklist_data[$section] = [];
        }

        if (!isset($checklist_data[$section][$category])) {
            $checklist_data[$section][$category] = [];
        }
        // Return object with title
        $checklist_data[$section][$category][] = [
            'text' => $item['item_text'],
            'title' => $item['title']
        ];
    }

    echo json_encode(["success" => true, "checklist" => $checklist_data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Server Error",
        "error" => $e->getMessage()
    ]);
}
$conn->close();
?>