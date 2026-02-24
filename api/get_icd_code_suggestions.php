<?php
// Filename: api/get_icd_code_suggestions.php
// Search ICD-10 codes server-side
// UPDATED: Improved search logic and error handling

header("Content-Type: application/json; charset=UTF-8");
require_once '../db_connect.php';

$query = isset($_GET['query']) ? trim($_GET['query']) : '';

// Allow shorter queries for codes (e.g. "E11"), but require more for text to prevent massive dumps
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

try {
    // Check if table exists and has data (Optional debugging step, remove in production if slow)
    /*
    $check = $conn->query("SELECT count(*) as cnt FROM icd10_codes");
    if ($check) {
        $row = $check->fetch_assoc();
        if ($row['cnt'] == 0) {
             echo json_encode(['success' => true, 'results' => [], 'debug_message' => 'Table icd10_codes is empty.']);
             exit();
        }
    }
    */

    // Prepare SQL to search both code and description
    // We use wildcard % around the query
    $sql = "SELECT icd10_code, description 
            FROM icd10_codes 
            WHERE icd10_code LIKE ? OR description LIKE ? 
            ORDER BY icd10_code ASC 
            LIMIT 50";

    $searchTerm = "%" . $query . "%";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Database Prepare Error: " . $conn->error);
    }

    $stmt->bind_param("ss", $searchTerm, $searchTerm);
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = [
            'code' => $row['icd10_code'],
            'description' => $row['description'],
            'display' => $row['icd10_code'] . ' - ' . $row['description']
        ];
    }

    echo json_encode(['success' => true, 'results' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($stmt)) $stmt->close();
$conn->close();
?>