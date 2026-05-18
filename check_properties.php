<?php
require 'config.php';
$result = $conn->query('SELECT id, property_type, location FROM properties LIMIT 5');
while ($row = $result->fetch_assoc()) {
    echo $row['id'] . ': ' . $row['property_type'] . ' in ' . $row['location'] . PHP_EOL;
}
$conn->close();