<?php
/**
 * CLEANING SERVICES - QUICK SETUP
 * Simple page to initialize the cleaning services database
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// Security check
$setup_key = $_GET['key'] ?? '';
$setup_valid = ($setup_key === 'setup_cleaning_2026');

$setup_complete = false;
$setup_errors = [];
$setup_messages = [];

// Handle setup
if ($_POST['action'] === 'setup' && $setup_valid) {
    // Create tables inline
    $tables_sql = [
        // Cleaning Categories
        "CREATE TABLE IF NOT EXISTS cleaning_categories (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            category_group ENUM('residential', 'commercial', 'specialized') DEFAULT 'residential',
            description TEXT,
            icon VARCHAR(50),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Cleaning Requests
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

        // Service Providers
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

        // Service Assignments
        "CREATE TABLE IF NOT EXISTS service_assignments (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Provider Reviews
        "CREATE TABLE IF NOT EXISTS provider_reviews (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    // Execute table creation
    foreach ($tables_sql as $sql) {
        if ($conn->query($sql)) {
            $setup_messages[] = '✅ Table created successfully';
        } else {
            $setup_errors[] = 'Error: ' . $conn->error;
        }
    }

    // Insert categories
    $categories = [
        ['Live-in House Help', 'residential', 'Full-time house help services'],
        ['Live-out House Help', 'residential', 'Part-time house help services'],
        ['Deep Cleaning Specialist', 'residential', 'Professional deep cleaning service'],
        ['Move-in / Move-out Cleaning', 'residential', 'Comprehensive cleaning for moving'],
        ['Upholstery Cleaning', 'residential', 'Sofas, carpets, curtains, mattresses cleaning'],
        ['Office Cleaning', 'commercial', 'Cleaning for offices and workspaces'],
        ['Post-Construction Cleaning', 'commercial', 'Specialized post-construction cleanup'],
        ['Public Area Cleaning', 'commercial', 'Cleaning for apartments, hotels, lobbies'],
        ['Trained House Help', 'specialized', 'Trained and certified house help'],
        ['Caregiver Services', 'specialized', 'Elderly care and child care services']
    ];

    foreach ($categories as $cat) {
        $stmt = $conn->prepare("INSERT IGNORE INTO cleaning_categories (name, category_group, description, icon) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $cat[0], $cat[1], $cat[2], $cat[3]);
        if ($stmt->execute()) {
            $setup_messages[] = "✅ Added category: {$cat[0]}";
        } else {
            $setup_errors[] = "Error adding category: " . $conn->error;
        }
        $stmt->close();
    }

    $setup_complete = empty($setup_errors);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleaning Services Setup - Walbrand Properties Marketplace & Interiors</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .setup-button {
            background: linear-gradient(135deg, #ff7b00, #5cfaff);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            margin: 20px 0;
        }

        .setup-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .error-box {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .message-list {
            list-style: none;
        }

        .message-list li {
            padding: 8px 0;
            display: flex;
            align-items: center;
        }

        .security-warning {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .next-steps {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .next-steps h3 {
            margin-bottom: 10px;
        }

        .next-steps ol {
            margin-left: 20px;
        }

        .next-steps li {
            margin: 8px 0;
        }

        .next-steps a {
            color: #0c5460;
            font-weight: 600;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <h1>Cleaning Services Setup</h1>
        <p class="subtitle">Walbrand Properties Marketplace & Interiors Database Initialization</p>

        <?php if (!$setup_valid): ?>
            <div class="security-warning">
                <strong>⚠️ Security Key Required</strong>
                <p>To run setup, add ?key=setup_cleaning_2026 to this URL</p>
            </div>
        <?php else: ?>

            <?php if ($setup_complete): ?>
                <div class="success-box">
                    <strong>✅ Setup Completed Successfully!</strong>
                    <ul class="message-list">
                        <?php foreach ($setup_messages as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="next-steps">
                    <h3>Next Steps</h3>
                    <ol>
                        <li><a href="index.php">Go to Homepage</a> - Click "Cleaning Services"</li>
                        <li><a href="admin_control_panel.php">Admin Panel</a> - See cleaning stats</li>
                        <li><a href="cleaning_services/pages/booking.php">Book a Service</a> - Test booking</li>
                        <li><a href="cleaning_services/pages/provider_register.php">Register Provider</a> - Add providers</li>
                        <li><a href="cleaning_services/admin/index.php">Cleaning Admin</a> - Manage everything</li>
                    </ol>
                </div>

            <?php elseif (!empty($setup_messages) || !empty($setup_errors)): ?>
                <div class="success-box hidden">
                    <ul class="message-list">
                        <?php foreach ($setup_messages as $msg): ?>
                            <li><?= htmlspecialchars($msg) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="error-box">
                    <strong>⚠️ Setup Encountered Errors</strong>
                    <ul class="message-list">
                        <?php foreach ($setup_errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="POST" action="?key=setup_cleaning_2026">
                    <input type="hidden" name="action" value="setup">
                    <button type="submit" class="setup-button">🔄 Retry Setup</button>
                </form>

            <?php else: ?>
                <p style="color: #666; margin: 20px 0;">Click below to create all necessary database tables for the cleaning services marketplace.</p>

                <form method="POST" action="?key=setup_cleaning_2026">
                    <input type="hidden" name="action" value="setup">
                    <button type="submit" class="setup-button">🚀 Initialize Database</button>
                </form>

                <div class="security-warning">
                    <strong>📋 What will be created:</strong>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>cleaning_categories (10 service categories)</li>
                        <li>cleaning_requests (client bookings)</li>
                        <li>service_providers (provider profiles)</li>
                        <li>service_assignments (request assignments)</li>
                        <li>provider_reviews (customer feedback)</li>
                    </ul>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>