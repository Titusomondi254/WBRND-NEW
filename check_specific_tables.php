<?php
require_once 'config.php';

$tables_to_check = ['admin_logs', 'viewing_requests', 'design_images'];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    echo "$table: " . ($result->num_rows > 0 ? 'EXISTS' : 'MISSING') . "\n";
}

$conn->close();
