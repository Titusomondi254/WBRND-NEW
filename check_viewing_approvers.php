<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$result = $conn->query('SELECT vr.id, vr.status, vr.approved_by, vr.approved_at, u.user_type, u.first_name, u.last_name FROM viewing_requests vr LEFT JOIN users u ON vr.approved_by = u.id ORDER BY vr.id DESC LIMIT 5');
echo 'Viewing requests with approver info:' . PHP_EOL;
while ($row = $result->fetch_assoc()) {
    $approver = $row['approved_by'] ? "{$row['first_name']} {$row['last_name']} ({$row['user_type']})" : 'Not approved';
    echo "ID: {$row['id']}, Status: {$row['status']}, Approved by: {$approver}, Approved at: {$row['approved_at']}" . PHP_EOL;
}

$conn->close();
?>