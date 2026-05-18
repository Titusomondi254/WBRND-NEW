<?php
require_once 'config.php';

echo "Checking if profile_picture column exists...\n";

$result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($result && $result->num_rows > 0) {
    echo "✓ profile_picture column exists in users table\n";
    $row = $result->fetch_assoc();
    echo "Column details: " . $row['Field'] . " (" . $row['Type'] . ")\n";
} else {
    echo "✗ profile_picture column does NOT exist in users table\n";
    echo "Adding the column...\n";

    $alter_result = $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255)");
    if ($alter_result) {
        echo "✓ Column added successfully\n";
    } else {
        echo "✗ Failed to add column: " . $conn->error . "\n";
    }
}

$conn->close();
?>