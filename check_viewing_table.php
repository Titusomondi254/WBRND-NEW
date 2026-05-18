<?php
require 'config.php';
$result = mysqli_query($conn, 'DESCRIBE property_viewing_requests');
if ($result && mysqli_num_rows($result) > 0) {
    echo 'Table exists' . PHP_EOL;
} else {
    echo 'Table not found' . PHP_EOL;
}
$conn->close();
?>