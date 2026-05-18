<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$result = $conn->query('SELECT id, status, fee_paid, payment_reference, created_at FROM viewing_requests ORDER BY id DESC LIMIT 5');
echo 'Recent viewing requests:' . PHP_EOL;
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Status: {$row['status']}, Fee Paid: {$row['fee_paid']}, Payment Ref: {$row['payment_reference']}, Created: {$row['created_at']}" . PHP_EOL;
}

$conn->close();
?>