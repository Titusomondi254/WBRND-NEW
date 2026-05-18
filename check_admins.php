<?php
require_once 'config.php';

$query = "SELECT u.email, a.role FROM users u LEFT JOIN admin_users a ON u.id = a.user_id WHERE a.role = 'super_admin'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "Super admins:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "Email: {$row['email']}, Role: {$row['role']}<br>";
    }
} else {
    echo "No super admins found.";
}
?>