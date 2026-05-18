<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check if user 9 is a super admin
$result = $conn->query('SELECT role FROM admin_users WHERE user_id = 9');
if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo 'User 9 is a super admin with role: ' . $admin['role'] . PHP_EOL;
} else {
    echo 'User 9 is NOT a super admin' . PHP_EOL;
}

// Check all super admins
$admins = $conn->query('SELECT au.user_id, au.role, u.first_name, u.last_name FROM admin_users au JOIN users u ON au.user_id = u.id WHERE au.is_active = 1');
echo 'All active admins:' . PHP_EOL;
while ($admin = $admins->fetch_assoc()) {
    echo "{$admin['first_name']} {$admin['last_name']} (ID: {$admin['user_id']}) - Role: {$admin['role']}" . PHP_EOL;
}

$conn->close();
?>