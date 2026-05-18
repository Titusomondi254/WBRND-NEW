<?php
require 'config.php';

// Create property_viewing_requests table
$sql = "CREATE TABLE IF NOT EXISTS property_viewing_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    property_id INT NOT NULL,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    viewing_date DATE NOT NULL,
    viewing_time TIME NOT NULL,
    description TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_property_id (property_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
)";

if ($conn->query($sql) === TRUE) {
    echo "Table property_viewing_requests created successfully!" . PHP_EOL;
} else {
    echo "Error creating table: " . $conn->error . PHP_EOL;
}

$conn->close();
?>