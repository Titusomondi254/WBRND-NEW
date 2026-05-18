CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value VARCHAR(255),
    description TEXT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('service_fee_high_end_1_bed', '3.5', 'Service fee percentage for high-end 1 bedroom properties'),
('service_fee_high_end_2_bed', '3.0', 'Service fee percentage for high-end 2 bedroom properties'),
('service_fee_high_end_3_bed', '2.5', 'Service fee percentage for high-end 3+ bedroom properties'),
('service_fee_mid_tier_1_bed', '4.0', 'Service fee percentage for mid-tier 1 bedroom properties'),
('service_fee_mid_tier_2_bed', '3.5', 'Service fee percentage for mid-tier 2 bedroom properties'),
('service_fee_mid_tier_3_bed', '3.0', 'Service fee percentage for mid-tier 3+ bedroom properties'),
('service_fee_affordable_1_bed', '5.0', 'Service fee percentage for affordable 1 bedroom properties'),
('service_fee_affordable_2_bed', '4.5', 'Service fee percentage for affordable 2 bedroom properties'),
('service_fee_affordable_3_bed', '4.0', 'Service fee percentage for affordable 3+ bedroom properties')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
