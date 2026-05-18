<?php
require_once 'config.php';

$result = $conn->query("SELECT id, email, user_type FROM users LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['email'] . ' | ' . $row['user_type'] . "\n";
}
?>
