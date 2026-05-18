<?php
require_once 'config.php';

echo "Creating missing database tables...\n\n";

$tables = [
    "property_bids" => "CREATE TABLE IF NOT EXISTS property_bids (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        property_id BIGINT UNSIGNED NOT NULL,
        buyer_id BIGINT UNSIGNED NOT NULL,
        bid_amount DECIMAL(15,2) NOT NULL,
        bid_status VARCHAR(50) DEFAULT 'pending',
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id),
        INDEX idx_buyer_id (buyer_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "user_activity" => "CREATE TABLE IF NOT EXISTS user_activity (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(100) NOT NULL,
        property_id BIGINT UNSIGNED,
        reference_id BIGINT UNSIGNED,
        reference_type VARCHAR(100),
        details JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "user_sessions" => "CREATE TABLE IF NOT EXISTS user_sessions (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        session_token VARCHAR(255) UNIQUE,
        remember_token VARCHAR(255),
        ip_address VARCHAR(45),
        user_agent TEXT,
        expires_at DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_session_token (session_token),
        INDEX idx_remember_token (remember_token),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "admin_audit_logs" => "CREATE TABLE IF NOT EXISTS admin_audit_logs (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        admin_id BIGINT UNSIGNED NOT NULL,
        action VARCHAR(255) NOT NULL,
        target_type VARCHAR(100),
        target_id BIGINT UNSIGNED,
        changes JSON,
        ip_address VARCHAR(45),
        reason TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_admin_id (admin_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "blocked_users" => "CREATE TABLE IF NOT EXISTS blocked_users (
        id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL UNIQUE,
        reason VARCHAR(255),
        blocked_by BIGINT UNSIGNED,
        blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_blocked_at (blocked_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $name => $sql) {
    if ($conn->query($sql)) {
        echo "✅ $name - Created\n";
    } else {
        echo "❌ $name - Error: " . $conn->error . "\n";
    }
}

echo "\n✅ All missing tables created!\n";
echo "Run verify_database.php to confirm.\n";
?>
