<?php
require 'config.php';
$result = mysqli_query($conn, 'SELECT COUNT(*) as count FROM properties WHERE status = "verified"');
$row = mysqli_fetch_assoc($result);
echo 'Verified properties: ' . $row['count'] . PHP_EOL;
?>