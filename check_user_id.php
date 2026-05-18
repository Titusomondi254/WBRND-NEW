<?php
require_once 'config.php';
$id = 1;
$result = $conn->query("SELECT id, email FROM users WHERE id = $id");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "FOUND: {$row['id']} {$row['email']}\n";
} else {
    echo "NOT FOUND\n";
}
$conn->close();
