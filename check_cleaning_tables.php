<?php
require 'config.php';
$result = $conn->query('SHOW TABLES LIKE "cleaning_%"');
if ($result->num_rows > 0) {
    echo 'Cleaning tables exist';
} else {
    echo 'Cleaning tables do not exist';
}
?>