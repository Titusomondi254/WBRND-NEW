<?php
/**
 * Create Property Favorites Table
 */

require_once 'config.php';

if ($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS property_favorites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        property_id BIGINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, property_id),
        INDEX idx_user_id (user_id),
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($sql) === TRUE) {
        echo "Property favorites table created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} else {
    echo "Database connection failed.";
}
?>