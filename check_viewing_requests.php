<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$result = $conn->query('DESCRIBE viewing_requests');
if ($result) {
    echo 'viewing_requests table structure:' . PHP_EOL;
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ' - ' . ($row['Default'] ?? 'NO DEFAULT') . PHP_EOL;
    }
} else {
    echo 'Table does not exist or error: ' . $conn->error . PHP_EOL;
}

// Check for recent viewing requests
echo PHP_EOL . 'Recent viewing requests (last 10):' . PHP_EOL;
$recent = $conn->query('SELECT id, property_id, user_id, status, created_at FROM viewing_requests ORDER BY created_at DESC LIMIT 10');
if ($recent) {
    while ($row = $recent->fetch_assoc()) {
        echo "ID: {$row['id']}, Property: {$row['property_id']}, User: {$row['user_id']}, Status: {$row['status']}, Created: {$row['created_at']}" . PHP_EOL;
    }
}

$conn->close();
?>