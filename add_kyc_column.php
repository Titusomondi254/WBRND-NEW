<?php
require_once 'config.php';

try {
    // Check if column exists
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'kyc_verified'");
    if ($result->num_rows == 0) {
        // Column doesn't exist, add it
        $sql = "ALTER TABLE users ADD COLUMN kyc_verified BOOLEAN DEFAULT FALSE AFTER kyc_status";
        if ($conn->query($sql) === TRUE) {
            echo "Column kyc_verified added successfully\n";
        } else {
            echo "Error adding column: " . $conn->error . "\n";
        }
    } else {
        echo "Column kyc_verified already exists\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$conn->close();
?>