<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$result = $conn->query('SELECT status, COUNT(*) as count FROM viewing_requests GROUP BY status');
echo "Viewing request status counts:" . PHP_EOL;
while ($row = $result->fetch_assoc()) {
    echo $row['status'] . ': ' . $row['count'] . PHP_EOL;
}

$conn->close();
?>