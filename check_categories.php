<?php
require_once 'config.php';

// Check category values
echo "=== CATEGORY VALUES IN DATABASE ===\n";
$result = $conn->query("SELECT DISTINCT category FROM properties WHERE verification_status = 'verified' LIMIT 10");
while ($row = $result->fetch_assoc()) {
    echo "Category: " . ($row['category'] ?: 'NULL') . "\n";
}

echo "\n=== ALL PROPERTIES WITH CATEGORIES ===\n";
$result = $conn->query("SELECT id, location, bedrooms, category FROM properties WHERE verification_status = 'verified'");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}, Location: {$row['location']}, Bedrooms: {$row['bedrooms']}, Cat: {$row['category']}\n";
}
?>