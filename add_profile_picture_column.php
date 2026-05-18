<?php
require_once 'config.php';

try {
    // Check if profile_picture column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255)";
        if ($conn->query($sql) === TRUE) {
            echo "✓ Column profile_picture added successfully to users table\n";
        } else {
            echo "✗ Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "✓ Column profile_picture already exists in users table\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$conn->close();
?>