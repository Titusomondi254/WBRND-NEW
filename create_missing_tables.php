<?php
require_once 'config.php';

echo "=== CREATING MISSING TABLES ===\n";

$errors = [];

// Create mover_bookings table
$mover_sql = "
CREATE TABLE IF NOT EXISTS mover_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    employee_name VARCHAR(255) NOT NULL,
    employee_contact VARCHAR(20) NOT NULL,
    verification_status ENUM('pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
    id_document_url VARCHAR(500) DEFAULT NULL,
    verification_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES mover_groups(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_contact (employee_contact)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    location_from VARCHAR(500) NOT NULL,
    location_to VARCHAR(500) NOT NULL,
    pickup_county VARCHAR(100) DEFAULT NULL,
    destination_county VARCHAR(100) DEFAULT NULL,
    moving_date DATE NOT NULL,
    moving_time TIME NOT NULL,
    house_type ENUM('1_bedroom', '2_3_bedroom', '4_bedroom_plus') NOT NULL,
    distance_km DECIMAL(10, 2) NOT NULL,
    total_cost DECIMAL(12, 2) NOT NULL,
    service_type ENUM('within_nairobi', 'outside_nairobi') NOT NULL,
    recipient_name VARCHAR(255) DEFAULT NULL,
    recipient_email VARCHAR(255) DEFAULT NULL,
    recipient_phone VARCHAR(20) DEFAULT NULL,
    recipient_gender ENUM('male', 'female', 'other') DEFAULT NULL,
    recipient_photo_url VARCHAR(500) DEFAULT NULL,
    budget_min DECIMAL(12, 2) DEFAULT NULL,
    budget_max DECIMAL(12, 2) DEFAULT NULL,
    items_description TEXT DEFAULT NULL,
    terms_accepted BOOLEAN NOT NULL DEFAULT FALSE,
    insurance_selected BOOLEAN NOT NULL DEFAULT FALSE,
    insurance_policy_id INT DEFAULT NULL,
    status ENUM('pending', 'payment_pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_group_id INT,
    additional_notes TEXT,
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_group_id) REFERENCES mover_groups(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_date (moving_date),
    INDEX idx_assigned_group (assigned_group_id),
    INDEX idx_created (created_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    booking_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES mover_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_booking (booking_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    group_id INT DEFAULT NULL,
    device_id VARCHAR(100) DEFAULT NULL,
    latitude DECIMAL(10, 6) NOT NULL,
    longitude DECIMAL(10, 6) NOT NULL,
    accuracy INT DEFAULT NULL,
    tracking_status ENUM('awaiting_pickup', 'en_route', 'paused', 'delivered', 'offline') DEFAULT 'awaiting_pickup',
    location_label VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES mover_groups(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_group (group_id),
    INDEX idx_device (device_id),
    INDEX idx_status (tracking_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    group_id INT DEFAULT NULL,
    customer_name VARCHAR(255) DEFAULT NULL,
    rating TINYINT NOT NULL,
    punctuality_rating TINYINT DEFAULT NULL,
    professionalism_rating TINYINT DEFAULT NULL,
    handling_rating TINYINT DEFAULT NULL,
    review_text TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES mover_groups(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_group (group_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_wallets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    balance DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_wallet_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    wallet_id INT NOT NULL,
    booking_id INT DEFAULT NULL,
    amount DECIMAL(14, 2) NOT NULL,
    type ENUM('top_up','payment','refund','commission') NOT NULL,
    status ENUM('pending','completed','failed') DEFAULT 'pending',
    reference VARCHAR(255) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES mover_wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE SET NULL,
    INDEX idx_wallet (wallet_id),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_disputes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    issue_type ENUM('damaged','delayed','lost','payment','other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open','under_review','resolved','rejected') DEFAULT 'open',
    requested_refund DECIMAL(14, 2) DEFAULT 0.00,
    resolution_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status),
    INDEX idx_issue_type (issue_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_insurance_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    policy_number VARCHAR(100) NOT NULL UNIQUE,
    coverage_amount DECIMAL(14, 2) NOT NULL,
    premium_amount DECIMAL(14, 2) NOT NULL,
    status ENUM('active','expired','claimed','cancelled') DEFAULT 'active',
    approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
    INDEX idx_booking (booking_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mover_pricing_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    house_type ENUM('1_bedroom', '2_3_bedroom', '4_bedroom_plus') NOT NULL UNIQUE,
    within_nairobi_cost DECIMAL(12, 2) NOT NULL,
    outside_nairobi_rate_per_km DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mover_pricing_rules (house_type, within_nairobi_cost, outside_nairobi_rate_per_km) VALUES
('1_bedroom', 13000.00, 600.00),
('2_3_bedroom', 25000.00, 600.00),
('4_bedroom_plus', 35000.00, 600.00)
ON DUPLICATE KEY UPDATE
    within_nairobi_cost = VALUES(within_nairobi_cost),
    outside_nairobi_rate_per_km = VALUES(outside_nairobi_rate_per_km);
";

if ($conn->multi_query($mover_sql)) {
    echo "✅ Mover tables created successfully\n";
    // Consume all results
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    $errors[] = "Failed to create mover tables: " . $conn->error;
}

// Create cleaning_services table
$cleaning_sql = "
CREATE TABLE IF NOT EXISTS cleaning_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category_group ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
    description TEXT,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cleaning_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    location_area VARCHAR(100),
    sqm INT,
    bedrooms INT,
    bathrooms INT,
    service_types JSON NOT NULL,
    preferred_date DATE NOT NULL,
    preferred_time TIME,
    budget DECIMAL(10,2) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_provider_id INT,
    completion_date DATETIME,
    rating TINYINT,
    review TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_provider_id) REFERENCES service_providers(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_location (location_area),
    INDEX idx_preferred_date (preferred_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    assigned_by_admin_id BIGINT UNSIGNED,
    status ENUM('assigned', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'assigned',
    assignment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    completion_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES cleaning_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by_admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_request_id (request_id),
    INDEX idx_provider_id (provider_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS provider_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT NOT NULL,
    review_text TEXT,
    punctuality_rating TINYINT,
    quality_rating TINYINT,
    professionalism_rating TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES cleaning_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_provider_id (provider_id),
    INDEX idx_customer_id (customer_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cleaning_categories (name, category_group, description, icon) VALUES
('Live-in House Help', 'residential', 'Full-time house help services', '👩‍💼'),
('Live-out House Help', 'residential', 'Part-time house help services', '👩‍💼'),
('Deep Cleaning Specialist', 'residential', 'Professional deep cleaning service', '✨'),
('Move-in / Move-out Cleaning', 'residential', 'Comprehensive cleaning for moving', '📦'),
('Upholstery Cleaning', 'residential', 'Sofas, carpets, curtains, mattresses cleaning', '🛋️'),
('Office Cleaning', 'commercial', 'Cleaning for offices and workspaces', '🏢'),
('Post-Construction Cleaning', 'commercial', 'Specialized post-construction cleanup', '🔨'),
('Public Area Cleaning', 'commercial', 'Cleaning for apartments, hotels, lobbies', '🏨')
ON DUPLICATE KEY UPDATE name = VALUES(name);
";

if ($conn->multi_query($cleaning_sql)) {
    echo "✅ Cleaning tables created successfully\n";
    // Consume all results
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    $errors[] = "Failed to create cleaning tables: " . $conn->error;
}

if (empty($errors)) {
    echo "\n✅ ALL MISSING TABLES CREATED SUCCESSFULLY\n";

    // Verify tables exist
    $tables_to_check = ['mover_bookings', 'cleaning_requests'];
    echo "\n=== VERIFICATION ===\n";
    foreach ($tables_to_check as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✅ $table: EXISTS\n";
        } else {
            echo "❌ $table: STILL MISSING\n";
        }
    }
} else {
    echo "\n❌ ERRORS OCCURRED:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

$conn->close();
