<?php
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CREATING ALL MISSING TABLES ===\n\n";

// All table creation SQL
$sql_queries = [
    // From database_schema_updated.sql
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        middle_name VARCHAR(100),
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        phone VARCHAR(15) NOT NULL,
        phone_alternative VARCHAR(15),
        password_hash VARCHAR(255) NOT NULL,
        password VARCHAR(255),
        linkedin_profile VARCHAR(255),
        id_type VARCHAR(50),
        id_number VARCHAR(100),
        id_front_path VARCHAR(255),
        id_back_path VARCHAR(255),
        kyc_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        kyc_verified_at TIMESTAMP NULL,
        kyc_verified BOOLEAN DEFAULT false,
        user_type ENUM('buyer', 'seller', 'agent', 'investor', 'admin') DEFAULT 'buyer',
        is_active BOOLEAN DEFAULT true,
        is_verified BOOLEAN DEFAULT false,
        is_online BOOLEAN DEFAULT false,
        status VARCHAR(50) DEFAULT 'active',
        profile_picture VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        last_activity TIMESTAMP NULL,
        INDEX idx_email (email),
        INDEX idx_user_type (user_type),
        INDEX idx_kyc_status (kyc_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        role ENUM('super_admin', 'admin', 'moderator', 'support') DEFAULT 'admin',
        permissions JSON,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS properties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        property_type VARCHAR(100) NOT NULL,
        category VARCHAR(100),
        main_category VARCHAR(50),
        sub_category VARCHAR(100),
        location VARCHAR(255) NOT NULL,
        price DECIMAL(15, 2) NOT NULL,
        is_negotiable BOOLEAN DEFAULT false,
        bedrooms INT,
        bathrooms INT,
        size_sqm DECIMAL(10, 2),
        description TEXT,
        features JSON,
        design_plan VARCHAR(255),
        budget DECIMAL(15, 2),
        status ENUM('draft', 'pending_verification', 'verified', 'sold', 'rented', 'delisted') DEFAULT 'pending_verification',
        verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        verified_at TIMESTAMP NULL,
        verified_by INT,
        verification_notes TEXT,
        image_count INT DEFAULT 0,
        video_count INT DEFAULT 0,
        document_count INT DEFAULT 0,
        view_count INT DEFAULT 0,
        for_sale BOOLEAN DEFAULT false,
        for_rent BOOLEAN DEFAULT false,
        for_lease BOOLEAN DEFAULT false,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_seller_id (seller_id),
        INDEX idx_location (location),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        image_type ENUM('exterior', 'interior', 'floor_plan') DEFAULT 'interior',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        video_path VARCHAR(255) NOT NULL,
        video_type ENUM('walkthrough', 'exterior', 'interior') DEFAULT 'walkthrough',
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_legal_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        document_type ENUM('title_deed', 'sales_agreement', 'transfer_documents', 'valuation_report', 'stamp_duty_receipt', 'identity_documents', 'kra_documents', 'property_green_card') NOT NULL,
        document_path VARCHAR(500) NOT NULL,
        verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        notes TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id),
        INDEX idx_document_type (document_type),
        INDEX idx_verification_status (verification_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS ownership_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        document_type ENUM('title_deed', 'lease_certificate', 'sectional_deed', 'search_certificate', 'sales_agreement', 'allotment_letter', 'share_certificate', 'public_records') NOT NULL,
        document_path VARCHAR(255) NOT NULL,
        verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        verified_by INT,
        verified_at TIMESTAMP NULL,
        verification_notes TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS bids (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        buyer_id INT NOT NULL,
        bid_amount DECIMAL(15, 2) NOT NULL,
        deposit_amount DECIMAL(15, 2),
        monthly_mortgage DECIMAL(12, 2),
        status ENUM('pending', 'active', 'accepted', 'rejected', 'withdrawn') DEFAULT 'pending',
        bid_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        response_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id),
        INDEX idx_buyer_id (buyer_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS lease_bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        tenant_id INT NOT NULL,
        lease_type ENUM('lease', 'rent') NOT NULL,
        monthly_amount DECIMAL(12, 2) NOT NULL,
        lease_start_date DATE NOT NULL,
        lease_end_date DATE NOT NULL,
        number_of_units INT DEFAULT 1,
        status ENUM('pending', 'active', 'completed', 'terminated') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (tenant_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id),
        INDEX idx_tenant_id (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        investor_id INT NOT NULL,
        shares_owned DECIMAL(5, 2) NOT NULL,
        investment_amount DECIMAL(15, 2) NOT NULL,
        partnership_type ENUM('shares', 'joint_corporation', 'investment') DEFAULT 'shares',
        status ENUM('active', 'transferred', 'terminated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (investor_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_property_id (property_id),
        INDEX idx_investor_id (investor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS consultations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        consultation_type VARCHAR(100),
        property_id INT,
        scheduled_date DATETIME,
        preferred_date DATE,
        preferred_time TIME,
        contact_number VARCHAR(15),
        email VARCHAR(255),
        issue_description TEXT,
        notes TEXT,
        admin_notes TEXT,
        status ENUM('pending', 'scheduled', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        notification_type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        related_id INT,
        is_read BOOLEAN DEFAULT FALSE,
        email_sent BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_type (notification_type),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        property_id INT,
        bid_id INT,
        amount DECIMAL(15, 2) NOT NULL,
        payment_type ENUM('deposit', 'full_payment', 'monthly_rent', 'consultation_fee', 'installation_fee') NOT NULL,
        payment_method ENUM('mpesa', 'bank_transfer', 'credit_card', 'other') DEFAULT 'mpesa',
        mpesa_reference VARCHAR(100),
        mpesa_receipt_number VARCHAR(100),
        checkout_request_id VARCHAR(100),
        phone_number VARCHAR(15) NOT NULL,
        transaction_id VARCHAR(255),
        payment_reference VARCHAR(255),
        status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completion_date TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        FOREIGN KEY (bid_id) REFERENCES bids(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_checkout_request_id (checkout_request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_valuations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        valuation_amount DECIMAL(15, 2) NOT NULL,
        valuation_date DATE NOT NULL,
        valuation_method VARCHAR(100),
        valuator_id INT,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (valuator_id) REFERENCES users(id),
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        seller_id INT NOT NULL,
        buyer_id INT NOT NULL,
        transaction_type ENUM('sale', 'lease', 'rent') NOT NULL,
        transaction_amount DECIMAL(15, 2) NOT NULL,
        transaction_date DATE NOT NULL,
        completion_date DATE,
        legal_support_involved BOOLEAN DEFAULT true,
        status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id),
        FOREIGN KEY (buyer_id) REFERENCES users(id),
        INDEX idx_property_id (property_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS investment_guides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        category VARCHAR(100),
        author_id INT,
        is_published BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (author_id) REFERENCES users(id),
        INDEX idx_category (category)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS financing_assistance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        property_id INT,
        required_amount DECIMAL(15, 2) NOT NULL,
        down_payment DECIMAL(15, 2) NOT NULL,
        monthly_payment DECIMAL(12, 2),
        loan_term_months INT,
        status ENUM('pending', 'approved', 'rejected', 'funded') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS property_management (
        id INT AUTO_INCREMENT PRIMARY KEY,
        property_id INT NOT NULL,
        owner_id INT NOT NULL,
        manager_id INT,
        management_fee_percent DECIMAL(5, 2),
        start_date DATE NOT NULL,
        end_date DATE,
        services JSON,
        status ENUM('active', 'completed', 'terminated') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
        FOREIGN KEY (owner_id) REFERENCES users(id),
        FOREIGN KEY (manager_id) REFERENCES users(id),
        INDEX idx_property_id (property_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(100),
        entity_id INT,
        old_values JSON,
        new_values JSON,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user_id (user_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Mover System tables
    "CREATE TABLE IF NOT EXISTS mover_groups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        group_name VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mover_group_members (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mover_bookings (
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
        INDEX idx_assigned_group (assigned_group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mover_notifications (
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
        INDEX idx_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_tracking (
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
        INDEX idx_status (tracking_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_reviews (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_wallets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        balance DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_wallet_transactions (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_disputes (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_insurance_policies (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    "CREATE TABLE IF NOT EXISTS mover_activity_logs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        booking_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (booking_id) REFERENCES mover_bookings(id) ON DELETE CASCADE,
        INDEX idx_booking (booking_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS mover_pricing_rules (
        id INT PRIMARY KEY AUTO_INCREMENT,
        house_type ENUM('1_bedroom', '2_bedroom', '3_bedroom_plus') NOT NULL UNIQUE,
        within_nairobi_cost DECIMAL(12, 2) NOT NULL,
        outside_nairobi_rate_per_km DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS nairobi_areas (
        id INT PRIMARY KEY AUTO_INCREMENT,
        area_name VARCHAR(255) NOT NULL UNIQUE,
        longitude DECIMAL(9, 6),
        latitude DECIMAL(9, 6),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Interior Design tables
    "CREATE TABLE IF NOT EXISTS interior_designs (
        id INT PRIMARY KEY AUTO_INCREMENT,
        agent_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_url VARCHAR(500),
        video_file VARCHAR(255),
        property_size_sqm DECIMAL(10,2),
        bedrooms INT,
        bathrooms INT,
        renovation_cost_interior DECIMAL(15,2),
        renovation_cost_exterior DECIMAL(15,2),
        features JSON,
        project_duration VARCHAR(100),
        deposit_required DECIMAL(15,2),
        project_type ENUM('renovation', 'new_construction') DEFAULT 'renovation',
        items_included TEXT,
        items_excluded TEXT,
        project_narration TEXT,
        status ENUM('pending_review', 'approved', 'rejected', 'active', 'inactive', 'draft') DEFAULT 'pending_review',
        views_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_agent (agent_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS design_inquiries (
        id INT PRIMARY KEY AUTO_INCREMENT,
        design_id INT NOT NULL,
        client_name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        location VARCHAR(255),
        house_size_sqm DECIMAL(10,2),
        bedrooms INT,
        bathrooms INT,
        kitchens INT,
        compound_size_sqm DECIMAL(10,2),
        property_type ENUM('home', 'hotel', 'mall', 'office', 'restaurant', 'other') DEFAULT 'home',
        budget DECIMAL(15,2),
        start_date DATE,
        description TEXT,
        status ENUM('pending', 'contacted', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (design_id) REFERENCES interior_designs(id) ON DELETE CASCADE,
        INDEX idx_design (design_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS design_favorites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        design_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (design_id) REFERENCES interior_designs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, design_id),
        INDEX idx_user (user_id),
        INDEX idx_design (design_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS design_reviews (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        design_id INT NOT NULL,
        rating INT NOT NULL,
        review_text TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (design_id) REFERENCES interior_designs(id) ON DELETE CASCADE,
        UNIQUE KEY unique_review (user_id, design_id),
        INDEX idx_design (design_id),
        INDEX idx_rating (rating)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    // Cleaning Services tables
    "CREATE TABLE IF NOT EXISTS cleaning_categories (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        category_group ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
        description TEXT,
        icon VARCHAR(50),
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS service_providers (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS cleaning_requests (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS service_assignments (
        id INT PRIMARY KEY AUTO_INCREMENT,
        request_id INT NOT NULL,
        provider_id INT NOT NULL,
        assigned_by_admin_id INT,
        status ENUM('assigned', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'assigned',
        assignment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        completion_date DATETIME,
        notes TEXT,
        FOREIGN KEY (request_id) REFERENCES cleaning_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (provider_id) REFERENCES service_providers(id) ON DELETE SET NULL,
        INDEX idx_request (request_id),
        INDEX idx_provider (provider_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS admin_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT NOT NULL,
        notification_type VARCHAR(100) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        message TEXT NOT NULL,
        related_id INT DEFAULT NULL,
        action_url VARCHAR(255) DEFAULT NULL,
        is_dismissed BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(id),
        UNIQUE KEY uniq_admin_notification (admin_id, notification_type, related_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS admin_calendar_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        event_type VARCHAR(100) DEFAULT 'meeting',
        assigned_to INT DEFAULT NULL,
        event_date DATETIME NOT NULL,
        is_meeting BOOLEAN DEFAULT TRUE,
        google_meet_link VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        developer_id INT NOT NULL,
        slug VARCHAR(150) NOT NULL UNIQUE,
        project_name VARCHAR(255) NOT NULL,
        project_type ENUM('residential','commercial','mixed_use','hotel','retail','industrial','other') DEFAULT 'residential',
        location VARCHAR(255) NOT NULL,
        total_units INT NOT NULL DEFAULT 0,
        available_units INT NOT NULL DEFAULT 0,
        bedrooms_per_unit TINYINT UNSIGNED DEFAULT 0,
        bathrooms_per_unit TINYINT UNSIGNED DEFAULT 0,
        unit_size_sqm DECIMAL(10,2) DEFAULT NULL,
        price_per_unit DECIMAL(15,2) DEFAULT NULL,
        minimum_investment DECIMAL(15,2) DEFAULT NULL,
        maximum_investment DECIMAL(15,2) DEFAULT NULL,
        investment_goal DECIMAL(15,2) DEFAULT NULL,
        expected_roi VARCHAR(100) DEFAULT NULL,
        investment_open_date DATE DEFAULT NULL,
        investment_close_date DATE DEFAULT NULL,
        construction_start_date DATE DEFAULT NULL,
        construction_end_date DATE DEFAULT NULL,
        development_stage ENUM('planning','construction','ready_for_sale','completed') DEFAULT 'planning',
        verification_status ENUM('pending_review','verified','rejected','flagged') DEFAULT 'pending_review',
        project_status ENUM('draft','active','closed','archived') DEFAULT 'draft',
        project_summary TEXT,
        investment_highlights TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (developer_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_developer_id (developer_id),
        INDEX idx_verification_status (verification_status),
        INDEX idx_project_type (project_type),
        INDEX idx_location (location)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_project_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        document_type ENUM('planning_permit','building_plan','company_profile','kyc','agreement','other') DEFAULT 'other',
        file_path VARCHAR(500) NOT NULL,
        uploaded_by INT DEFAULT NULL,
        verification_status ENUM('pending','verified','rejected') DEFAULT 'pending',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_project_id (project_id),
        INDEX idx_uploaded_by (uploaded_by),
        INDEX idx_document_type (document_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_shareholders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        role VARCHAR(150),
        phone_number VARCHAR(20),
        email VARCHAR(255),
        id_document_path VARCHAR(500),
        kra_pin_path VARCHAR(500),
        passport_photo_path VARCHAR(500),
        verification_status ENUM('pending','verified','rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        INDEX idx_project_id (project_id),
        INDEX idx_verification_status (verification_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_milestones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        milestone_title VARCHAR(255) NOT NULL,
        description TEXT,
        target_date DATE DEFAULT NULL,
        status ENUM('pending','in_progress','completed','delayed','cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        INDEX idx_project_id (project_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_investments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        investor_id INT NOT NULL,
        units_committed INT NOT NULL DEFAULT 0,
        amount_committed DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        investor_note TEXT,
        mpesa_transaction_id VARCHAR(100) DEFAULT NULL,
        status ENUM('pending_payment','payment_received','confirmed','active','completed','cancelled','disputed') DEFAULT 'pending_payment',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        FOREIGN KEY (investor_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_project_id (project_id),
        INDEX idx_investor_id (investor_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_mpesa_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_code VARCHAR(100) NOT NULL UNIQUE,
        investment_id INT DEFAULT NULL,
        investor_id INT NOT NULL,
        project_id INT NOT NULL,
        phone_number VARCHAR(20),
        amount DECIMAL(15,2) NOT NULL,
        paybill_number VARCHAR(50),
        transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending','success','failed','reversed') DEFAULT 'pending',
        raw_payload TEXT,
        matched_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (investment_id) REFERENCES offplan_investments(id) ON DELETE SET NULL,
        FOREIGN KEY (investor_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        INDEX idx_investment_id (investment_id),
        INDEX idx_project_id (project_id),
        INDEX idx_investor_id (investor_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_escrow_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        total_held DECIMAL(15,2) DEFAULT 0.00,
        total_released DECIMAL(15,2) DEFAULT 0.00,
        total_commission DECIMAL(15,2) DEFAULT 0.00,
        available_balance DECIMAL(15,2) DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        INDEX idx_project_id (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_disputes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT NOT NULL,
        investment_id INT DEFAULT NULL,
        raised_by INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('open','under_review','resolved','rejected','escalated') DEFAULT 'open',
        priority ENUM('low','medium','high') DEFAULT 'medium',
        assigned_admin_id INT DEFAULT NULL,
        resolution TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES offplan_projects(id) ON DELETE CASCADE,
        FOREIGN KEY (investment_id) REFERENCES offplan_investments(id) ON DELETE SET NULL,
        FOREIGN KEY (raised_by) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (assigned_admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
        INDEX idx_project_id (project_id),
        INDEX idx_raised_by (raised_by),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS offplan_dispute_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dispute_id INT NOT NULL,
        sender_id INT NOT NULL,
        message TEXT NOT NULL,
        attachment_path VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (dispute_id) REFERENCES offplan_disputes(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_dispute_id (dispute_id),
        INDEX idx_sender_id (sender_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$success_count = 0;
$error_count = 0;

foreach ($sql_queries as $index => $query) {
    if ($conn->query($query)) {
        $success_count++;
        // Extract table name for display
        if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/i', $query, $matches)) {
            echo "✓ Table '{$matches[1]}' ready\n";
        }
    } else {
        $error_count++;
        echo "✗ Error: " . $conn->error . "\n";
        echo "Query: " . substr($query, 0, 80) . "...\n\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "✓ Successfully created/verified: $success_count tables\n";
echo "✗ Errors encountered: $error_count\n";

// List final tables
echo "\n=== FINAL TABLE LIST ===\n";
$result = $conn->query("SHOW TABLES");
$count = 0;
while ($row = $result->fetch_row()) {
    echo ($count + 1) . ". " . $row[0] . "\n";
    $count++;
}
echo "\nTotal tables: $count\n";
?>
