<?php
require 'config.php';
$result = $conn->query('SELECT COUNT(*) as total FROM viewing_requests WHERE status = "pending"');
$row = $result->fetch_assoc();
echo 'Pending viewing requests: ' . $row['total'] . PHP_EOL;

// Also check total
$result2 = $conn->query('SELECT COUNT(*) as total FROM viewing_requests');
$row2 = $result2->fetch_assoc();
echo 'Total viewing requests: ' . $row2['total'] . PHP_EOL;

// Check if table exists
$result3 = $conn->query('SHOW TABLES LIKE "viewing_requests"');
if ($result3->num_rows > 0) {
    echo 'Table viewing_requests exists' . PHP_EOL;
} else {
    echo 'Table viewing_requests does NOT exist' . PHP_EOL;
}
?>