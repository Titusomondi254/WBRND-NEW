<?php
/**
 * Add units_available column to properties table
 */

require_once 'config.php';

if (!$conn) {
    echo "Connection failed: " . mysqli_connect_error();
    exit;
}

// Check if column exists
$result = $conn->query("SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'properties' AND COLUMN_NAME = 'units_available'");

if ($result && $result->num_rows > 0) {
    echo "✓ Column 'units_available' already exists in properties table.\n";
} else {
    // Add the column
    $sql = "ALTER TABLE properties ADD COLUMN units_available INT DEFAULT 1 AFTER size_sqm";
    
    if ($conn->query($sql)) {
        echo "✓ Successfully added 'units_available' column to properties table.\n";
        echo "✓ Default value set to 1 unit per property.\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
        exit;
    }
}

// Verify column was added
$result = $conn->query("DESCRIBE properties");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

if (in_array('units_available', $columns)) {
    echo "\n✓ Verification successful - units_available column is now in properties table.\n";
    echo "✓ All " . count($columns) . " columns present in properties table.\n";
} else {
    echo "\n✗ Verification failed - units_available column not found.\n";
}

$conn->close();
?>
