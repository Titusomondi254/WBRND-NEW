<?php
require 'config.php';
$result = $conn->query("SELECT id, status, created_at, property_id, user_id FROM viewing_requests ORDER BY created_at DESC LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Status: {$row['status']}, Created: {$row['created_at']}, Property: {$row['property_id']}, User: {$row['user_id']}\n";
}
?>