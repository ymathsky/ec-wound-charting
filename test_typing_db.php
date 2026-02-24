<?php
require_once 'db_connect.php';

// 1. Create a dummy entry
$user_id = 99999;
$room_id = 99999;

// Clean up first
$conn->query("DELETE FROM chat_typing_status WHERE user_id = $user_id AND room_id = $room_id");

// Insert
$sql = "INSERT INTO chat_typing_status (user_id, room_id, last_typed_at) VALUES ($user_id, $room_id, NOW())";
if ($conn->query($sql)) {
    echo "Inserted typing status.\n";
} else {
    echo "Insert failed: " . $conn->error . "\n";
}

// Check immediately
$check_sql = "SELECT (TIMESTAMPDIFF(SECOND, last_typed_at, NOW()) < 4) as is_typing, last_typed_at, NOW() as db_now FROM chat_typing_status WHERE user_id = $user_id AND room_id = $room_id";
$res = $conn->query($check_sql);
if ($row = $res->fetch_assoc()) {
    echo "Immediate check: is_typing=" . $row['is_typing'] . " (Time: " . $row['last_typed_at'] . " vs " . $row['db_now'] . ")\n";
} else {
    echo "Immediate check failed: Row not found.\n";
}

// Sleep 5 seconds
echo "Sleeping 5 seconds...\n";
sleep(5);

// Check again
$res = $conn->query($check_sql);
if ($row = $res->fetch_assoc()) {
    echo "Delayed check: is_typing=" . $row['is_typing'] . " (Time: " . $row['last_typed_at'] . " vs " . $row['db_now'] . ")\n";
}

// Clean up
$conn->query("DELETE FROM chat_typing_status WHERE user_id = $user_id AND room_id = $room_id");
?>
