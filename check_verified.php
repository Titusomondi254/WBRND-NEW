<?php
require 'config.php';
$result = mysqli_query($conn, 'SELECT COUNT(*) as count FROM properties WHERE verification_status = "verified"');
$row = mysqli_fetch_assoc($result);
echo $row['count'];
$conn->close();
?>