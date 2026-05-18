-- ============================================================================
-- CLEANING & HOME SERVICES MARKETPLACE - DATABASE SCHEMA
-- ============================================================================

-- ============================================================================
-- CLEANING_CATEGORIES TABLE - Service categories
-- ============================================================================
CREATE TABLE IF NOT EXISTS cleaning_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category_group ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
    description TEXT,
    icon VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SERVICE_PROVIDERS TABLE - Cleaners, house helps, caregivers profiles
-- ============================================================================
CREATE TABLE IF NOT EXISTS service_providers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    id_number VARCHAR(50) UNIQUE NOT NULL,
    id_type ENUM('national_id', 'passport', 'alien_id') DEFAULT 'national_id',
    id_front_path VARCHAR(255),
    id_back_path VARCHAR(255),
    profile_photo VARCHAR(255),
    bio TEXT,
    services JSON NOT NULL,
    experience_years INT DEFAULT 0,
    experience_level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
    location VARCHAR(255) NOT NULL,
    service_areas JSON,
    availability JSON,
    hourly_rate DECIMAL(10,2),
    rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    is_approved BOOLEAN DEFAULT FALSE,
    rejection_reason TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    background_check_status ENUM('pending', 'cleared', 'failed') DEFAULT 'pending',
    background_check_date DATETIME,
    documents_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_approved (is_approved),
    INDEX idx_location (location),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CLEANING_REQUESTS TABLE - Client service requests
-- ============================================================================
CREATE TABLE IF NOT EXISTS cleaning_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
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

-- ============================================================================
-- SERVICE_ASSIGNMENTS TABLE - Links requests to providers
-- ============================================================================
CREATE TABLE IF NOT EXISTS service_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    assigned_by_admin_id INT,
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

-- ============================================================================
-- PROVIDER_REVIEWS TABLE - Customer reviews for service providers
-- ============================================================================
CREATE TABLE IF NOT EXISTS provider_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    provider_id INT NOT NULL,
    customer_id INT NOT NULL,
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

-- ============================================================================
-- INSERT INITIAL CATEGORIES
-- ============================================================================
INSERT INTO cleaning_categories (name, category_group, description, icon) VALUES
-- Residential Services
('Live-in House Help', 'residential', 'Full-time house help services', '👩‍💼'),
('Live-out House Help', 'residential', 'Part-time house help services', '👩‍💼'),
('Deep Cleaning Specialist', 'residential', 'Professional deep cleaning service', '✨'),
('Move-in / Move-out Cleaning', 'residential', 'Comprehensive cleaning for moving', '📦'),
('Upholstery Cleaning', 'residential', 'Sofas, carpets, curtains, mattresses cleaning', '🛋️'),

-- Commercial Services
('Office Cleaning', 'commercial', 'Cleaning for offices and workspaces', '🏢'),
('Post-Construction Cleaning', 'commercial', 'Specialized post-construction cleanup', '🔨'),
('Public Area Cleaning', 'commercial', 'Cleaning for apartments, hotels, lobbies', '🏨'),

-- ============================================================================
-- PAYMENTS TABLE - M-Pesa transactions
-- ============================================================================
CREATE TABLE IF NOT EXISTS cleaning_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    transaction_code VARCHAR(50),
    status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method ENUM('mpesa', 'card', 'cash') DEFAULT 'mpesa',
    mpesa_receipt_number VARCHAR(50),
    mpesa_transaction_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES cleaning_requests(id) ON DELETE CASCADE,
    INDEX idx_request_id (request_id),
    INDEX idx_status (status),
    INDEX idx_transaction_code (transaction_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ESCROW TABLE - Held funds
-- ============================================================================
CREATE TABLE IF NOT EXISTS cleaning_escrow (
    id INT PRIMARY KEY AUTO_INCREMENT,
    payment_id INT NOT NULL,
    held_amount DECIMAL(10,2) NOT NULL,
    released BOOLEAN DEFAULT FALSE,
    released_date DATETIME,
    released_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES cleaning_payments(id) ON DELETE CASCADE,
    FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_payment_id (payment_id),
    INDEX idx_released (released)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PROVIDER_EARNINGS TABLE - Track provider earnings
-- ============================================================================
CREATE TABLE IF NOT EXISTS provider_earnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    request_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    paid_date DATETIME,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE CASCADE,
    FOREIGN KEY (request_id) REFERENCES cleaning_requests(id) ON DELETE CASCADE,
    INDEX idx_provider_id (provider_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- LOCATION_AREAS TABLE - Nairobi segmentation
-- ============================================================================
CREATE TABLE IF NOT EXISTS location_areas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    area_name VARCHAR(100) NOT NULL,
    category ENUM('high_end', 'upper_middle', 'lower_middle', 'eastlands', 'others', 'satellite') NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Nairobi areas
INSERT INTO location_areas (area_name, category) VALUES
-- High-End
('Karen', 'high_end'),
('Runda', 'high_end'),
('Muthaiga', 'high_end'),
('Gigiri', 'high_end'),
('Kitisuru', 'high_end'),
('Lavington', 'high_end'),
('Riverside', 'high_end'),
('Rosslyn', 'high_end'),
('Nyari', 'high_end'),
('Spring Valley', 'high_end'),
('Kyuna', 'high_end'),

-- Upper-Middle
('Kilimani', 'upper_middle'),
('Kileleshwa', 'upper_middle'),
('Westlands', 'upper_middle'),
('Parklands', 'upper_middle'),
('Ridgeways', 'upper_middle'),
('Woodley', 'upper_middle'),
('Hurlingham', 'upper_middle'),
('Lang\'ata', 'upper_middle'),

-- Lower-Middle
('Ruaka', 'lower_middle'),
('Donholm', 'lower_middle'),
('Kasarani', 'lower_middle'),
('Imara Daima', 'lower_middle'),
('Syokimau', 'lower_middle'),
('Utawala', 'lower_middle'),
('Dagoretti', 'lower_middle'),
('Buruburu', 'lower_middle'),
('Roysambu', 'lower_middle'),

-- Eastlands
('Eastleigh', 'eastlands'),
('Umoja', 'eastlands'),
('Kayole', 'eastlands'),
('Dandora', 'eastlands'),
('Kariobangi', 'eastlands'),
('Komarock', 'eastlands'),
('Pangani', 'eastlands'),

-- Others
('South B', 'others'),
('South C', 'others'),
('Ongata Rongai', 'others'),
('Kikuyu', 'others'),
('Ngong', 'others'),
('Ruaraka', 'others'),
('Madaraka', 'others'),
('Kahawa West', 'others'),

-- Satellite
('Tatu City', 'satellite'),
('Kiambu Road Estates', 'satellite'),
('Athi River', 'satellite'),
('Kitengela', 'satellite');
