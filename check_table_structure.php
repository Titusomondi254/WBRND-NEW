<?php
require_once 'config.php';

echo "=== INTERIOR_DESIGNS TABLE STRUCTURE ===\n";

$result = $conn->query('DESCRIBE interior_designs');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . "\n";
    }
} else {
    echo "Failed to get table structure\n";
}

$conn->close();
