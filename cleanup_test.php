<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$conn->query('DELETE FROM viewing_requests WHERE id = 8');
echo 'Test request deleted' . PHP_EOL;

$conn->close();
?>