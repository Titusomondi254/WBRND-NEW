<?php
include 'config.php';

$result = $conn->query("SELECT id, status FROM consultations WHERE consultation_type = 'property_viewing' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $request = $result->fetch_assoc();
    echo 'Found viewing request ID: ' . $request['id'] . ', Status: ' . $request['status'] . PHP_EOL;
} else {
    echo 'No viewing requests found' . PHP_EOL;
}
?>