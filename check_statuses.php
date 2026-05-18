<?php
require 'config.php';
$result = $conn->query('SELECT status, COUNT(*) as count FROM viewing_requests GROUP BY status');
while ($row = $result->fetch_assoc()) {
    echo $row['status'] . ': ' . $row['count'] . PHP_EOL;
}
?>