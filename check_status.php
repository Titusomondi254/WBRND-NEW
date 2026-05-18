<?php
require 'config.php';
$result = mysqli_query($conn, 'SELECT COUNT(*) as count FROM properties WHERE status = "verified"');
$row = mysqli_fetch_assoc($result);
echo 'Verified properties: ' . $row['count'] . PHP_EOL;

$result2 = mysqli_query($conn, 'SELECT COUNT(*) as count FROM properties');
$row2 = mysqli_fetch_assoc($result2);
echo 'Total properties: ' . $row2['count'] . PHP_EOL;

// Also check if user is logged in
session_start();
echo 'User logged in: ' . (isset($_SESSION['user_id']) ? 'Yes' : 'No') . PHP_EOL;

$conn->close();
?>