<?php
require_once 'config.php';
$result = $conn->query('DESCRIBE roommate_requests');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . PHP_EOL;
}
?>