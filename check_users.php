<?php
require_once 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check user 9 details
$user_result = $conn->query('SELECT id, first_name, last_name, user_type, email FROM users WHERE id = 9');
$user = $user_result->fetch_assoc();
echo 'User 9 details: ' . $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['user_type'] . ') - ' . $user['email'] . PHP_EOL;

// Check who approved the requests
$approver_result = $conn->query('SELECT DISTINCT approved_by FROM viewing_requests WHERE approved_by IS NOT NULL');
echo 'Approvers: ';
while ($row = $approver_result->fetch_assoc()) {
    $approver_user = $conn->query('SELECT first_name, last_name, user_type FROM users WHERE id = ' . $row['approved_by'])->fetch_assoc();
    echo $approver_user['first_name'] . ' ' . $approver_user['last_name'] . ' (' . $approver_user['user_type'] . '), ';
}
echo PHP_EOL;

$conn->close();
?>