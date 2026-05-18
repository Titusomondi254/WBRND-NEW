<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check status counts
$statuses = ['pending', 'approved', 'rejected', 'completed'];
foreach ($statuses as $status) {
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM viewing_requests WHERE status = ?');
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "{$status}: {$count}" . PHP_EOL;
    $stmt->close();
}

// Check if there are any viewing requests at all
$total = $conn->query('SELECT COUNT(*) as total FROM viewing_requests')->fetch_assoc()['total'];
echo "Total viewing requests: {$total}" . PHP_EOL;

// Check recent pending requests
echo PHP_EOL . "Recent pending requests:" . PHP_EOL;
$pending = $conn->query('SELECT id, property_id, user_id, status, created_at FROM viewing_requests WHERE status = "pending" ORDER BY created_at DESC LIMIT 5');
if ($pending) {
    while ($row = $pending->fetch_assoc()) {
        echo "ID: {$row['id']}, Property: {$row['property_id']}, User: {$row['user_id']}, Status: {$row['status']}, Created: {$row['created_at']}" . PHP_EOL;
    }
}

$conn->close();
?>