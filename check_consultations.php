<?php
require 'config.php';
$result = $conn->query("SELECT consultation_type, status, COUNT(*) as count FROM consultations GROUP BY consultation_type, status");
while ($row = $result->fetch_assoc()) {
    echo $row['consultation_type'] . ' - ' . $row['status'] . ': ' . $row['count'] . PHP_EOL;
}
?>