<?php
require 'config.php';
$result = $conn->query("SELECT status, COUNT(*) as count FROM consultations WHERE consultation_type = 'digital_installation' GROUP BY status");
while ($row = $result->fetch_assoc()) {
    echo $row['status'] . ': ' . $row['count'] . PHP_EOL;
}
?>