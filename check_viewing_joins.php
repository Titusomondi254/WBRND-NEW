<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check if viewing requests have valid property and user references
$result = $conn->query('
    SELECT vr.id, vr.property_id, vr.user_id, vr.status,
           p.id as prop_exists, u.id as user_exists
    FROM viewing_requests vr
    LEFT JOIN properties p ON vr.property_id = p.id
    LEFT JOIN users u ON vr.user_id = u.id
');

echo 'Viewing requests with join validation:' . PHP_EOL;
while ($row = $result->fetch_assoc()) {
    $prop_valid = $row['prop_exists'] ? 'YES' : 'NO';
    $user_valid = $row['user_exists'] ? 'YES' : 'NO';
    echo "ID: {$row['id']}, Property: {$row['property_id']} ({$prop_valid}), User: {$row['user_id']} ({$user_valid}), Status: {$row['status']}" . PHP_EOL;
}

$conn->close();
?>