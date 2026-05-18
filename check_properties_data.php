<?php
require_once 'config.php';

echo "=== PROPERTIES ===\n";
$result = $conn->query("SELECT id, property_code, property_type, location FROM properties LIMIT 5");
while ($row = $result->fetch_assoc()) {
    echo "ID: " . $row['id'] . " | Code: " . $row['property_code'] . " | Type: " . $row['property_type'] . " | Location: " . $row['location'] . "\n";
}

echo "\n=== PROPERTY IMAGES/VIDEOS ===\n";
$result = $conn->query("SELECT property_id, image_path FROM property_images LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo "Property ID: " . $row['property_id'] . " | Path: " . $row['image_path'] . "\n";
}
?>
