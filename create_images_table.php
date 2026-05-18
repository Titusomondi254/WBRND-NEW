<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS offplan_project_images (
    id INT(11) PRIMARY KEY AUTO_INCREMENT,
    project_id INT(11) NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_type ENUM('main','gallery','floor_plan','rendering','construction') DEFAULT 'gallery',
    display_order INT(11) DEFAULT 0,
    uploaded_by INT(11) NULL,
    verification_status ENUM('pending','verified','rejected') DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_image_type (image_type),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "✅ offplan_project_images table created successfully!\n";
} else {
    echo "❌ Error creating table: " . $conn->error . "\n";
}
?>