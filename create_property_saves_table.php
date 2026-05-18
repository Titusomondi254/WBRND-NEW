<?php
require_once "config.php";

// Create property_saves table if it doesn't exist
$create_property_saves_table = "
    CREATE TABLE IF NOT EXISTS property_saves (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        property_id INT NOT NULL,
        property_title VARCHAR(255),
        saved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_property (user_id, property_id),
        INDEX idx_user_id (user_id),
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

if ($conn->query($create_property_saves_table)) {
    echo "Property saves table created successfully\n";
} else {
    echo "Error creating property saves table: " . $conn->error . "\n";
}
?>