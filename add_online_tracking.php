<?php
/**
 * ADD ONLINE TRACKING FIELDS TO USERS TABLE
 * Adds last_activity and is_online fields for tracking user online status
 */

require_once 'config.php';

echo "Adding online tracking fields to users table...\n";

// Add last_activity column
$query1 = "ALTER TABLE users ADD COLUMN last_activity TIMESTAMP NULL DEFAULT NULL";
$result1 = $conn->query($query1);

if ($result1) {
    echo "✅ Added last_activity column\n";
} else {
    echo "⚠️  last_activity column may already exist or failed to add: " . $conn->error . "\n";
}

// Add is_online column
$query2 = "ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE";
$result2 = $conn->query($query2);

if ($result2) {
    echo "✅ Added is_online column\n";
} else {
    echo "⚠️  is_online column may already exist or failed to add: " . $conn->error . "\n";
}

// Add index for performance
$query3 = "ALTER TABLE users ADD INDEX idx_last_activity (last_activity), ADD INDEX idx_is_online (is_online)";
$result3 = $conn->query($query3);

if ($result3) {
    echo "✅ Added performance indexes\n";
} else {
    echo "⚠️  Indexes may already exist or failed to add: " . $conn->error . "\n";
}

echo "\n✅ Online tracking setup completed!\n";
?>