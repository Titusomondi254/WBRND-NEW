<?php
require_once 'admin_auth.php';

echo "=== EXTENDED PROPERTY AUDIT ===\n\n";

// Check ALL properties, not just verified
echo "All Properties (Including Unverified):\n";
echo "=====================================\n";
$result = $conn->query("SELECT id, location, bedrooms, verification_status, category FROM properties ORDER BY id DESC LIMIT 20");

while ($row = $result->fetch_assoc()) {
    echo "ID {$row['id']}: {$row['location']} ({$row['bedrooms']}BR) - Status: {$row['verification_status']} - Cat: {$row['category']}\n";
}

// Check for properties missing bedrooms data
echo "\n\nProperties with NULL or 0 Bedrooms:\n";
echo "===================================\n";
$result = $conn->query("SELECT id, location, bedrooms, verification_status FROM properties WHERE bedrooms IS NULL OR bedrooms = 0 LIMIT 10");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bedrooms = isset($row['bedrooms']) ? $row['bedrooms'] : 'NULL';
        echo "ID {$row['id']}: {$row['location']} - Bedrooms: {$bedrooms} - Status: {$row['verification_status']}\n";
    }
} else {
    echo "None found - All properties have valid bedroom data\n";
}

// Check database table structure
echo "\n\nDatabase Table Structure:\n";
echo "========================\n";
$result = $conn->query("DESCRIBE properties");
$columns = [];
while ($row = $result->fetch_assoc()) {
    if (in_array($row['Field'], ['id', 'location', 'bedrooms', 'category', 'verification_status'])) {
        echo "✓ {$row['Field']}: {$row['Type']}\n";
        $columns[$row['Field']] = $row['Type'];
    }
}

echo "\n";
?>