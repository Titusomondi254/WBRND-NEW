<?php
require_once 'config.php';

$table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
if ($table_check->num_rows == 0) {
    $create_table = "
    CREATE TABLE admin_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        details JSON,
        user_id INT,
        resource_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_id),
        INDEX idx_action (action),
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if ($conn->query($create_table)) {
        echo "admin_logs table created successfully!";
    } else {
        echo "Error creating admin_logs table: " . $conn->error;
    }
} else {
    echo "admin_logs table already exists.";
}
?>