<?php
require 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$result = $conn->query('DESCRIBE users');
$existing = [];
while ($row = $result->fetch_assoc()) {
    $existing[] = $row['Field'];
}
echo 'Users table columns: ' . implode(', ', $existing) . "\n";

$result2 = $conn->query('DESCRIBE properties');
$existing2 = [];
while ($row = $result2->fetch_assoc()) {
    $existing2[] = $row['Field'];
}
echo 'Properties table columns: ' . implode(', ', $existing2) . "\n";
$conn->close();
?>