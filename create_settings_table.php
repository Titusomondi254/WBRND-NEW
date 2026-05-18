<?php
require_once 'config.php';

$sql = "CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'float', 'json', 'boolean') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    INDEX idx_setting_key (setting_key)
)";

if ($conn->query($sql)) {
    echo 'system_settings table created successfully';

    // Insert default fee settings
    $defaultFees = [
        ['service_fee_high_end_1_bed', '2000', 'int', 'Service fee for 1 bedroom in high-end areas'],
        ['service_fee_high_end_2_bed', '2500', 'int', 'Service fee for 2 bedrooms in high-end areas'],
        ['service_fee_high_end_3_bed', '3500', 'int', 'Service fee for 3+ bedrooms in high-end areas'],
        ['service_fee_mid_tier_1_bed', '1500', 'int', 'Service fee for 1 bedroom in mid-tier areas'],
        ['service_fee_mid_tier_2_bed', '2000', 'int', 'Service fee for 2 bedrooms in mid-tier areas'],
        ['service_fee_mid_tier_3_bed', '2500', 'int', 'Service fee for 3+ bedrooms in mid-tier areas'],
        ['service_fee_affordable_1_bed', '1000', 'int', 'Service fee for 1 bedroom in affordable areas'],
        ['service_fee_affordable_2_bed', '1500', 'int', 'Service fee for 2 bedrooms in affordable areas'],
        ['service_fee_affordable_3_bed', '2000', 'int', 'Service fee for 3+ bedrooms in affordable areas']
    ];

    $insertSql = "INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";

    $stmt = $conn->prepare($insertSql);
    foreach ($defaultFees as $fee) {
        $stmt->bind_param("ssss", $fee[0], $fee[1], $fee[2], $fee[3]);
        $stmt->execute();
    }
    $stmt->close();

    echo ' and default fee settings inserted';
} else {
    echo 'Error creating table: ' . $conn->error;
}
?>