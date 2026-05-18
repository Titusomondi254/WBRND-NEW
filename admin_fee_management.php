<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';

    switch($form_action) {
        case 'update_fees':
            $updated_count = 0;
            $admin_id = $_SESSION['admin_id'];

            // Update all fee settings
            $fee_keys = [
                'service_fee_high_end_1_bed',
                'service_fee_high_end_2_bed',
                'service_fee_high_end_3_bed',
                'service_fee_mid_tier_1_bed',
                'service_fee_mid_tier_2_bed',
                'service_fee_mid_tier_3_bed',
                'service_fee_affordable_1_bed',
                'service_fee_affordable_2_bed',
                'service_fee_affordable_3_bed'
            ];

            foreach($fee_keys as $key) {
                if(isset($_POST[$key])) {
                    $value = intval($_POST[$key]);
                    $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
                    $stmt->bind_param("sis", $value, $admin_id, $key);
                    if($stmt->execute()) {
                        $updated_count++;
                    }
                    $stmt->close();
                }
            }

            if($updated_count > 0) {
                $success = "Successfully updated $updated_count fee settings!";
                logAdminAction('update_fee_settings', "Updated $updated_count service fee settings");
            } else {
                $error = "No fee settings were updated.";
            }
            break;
    }
}

// Get current fee settings
$fee_settings = [];
$query = "SELECT setting_key, setting_value, description FROM system_settings WHERE setting_key LIKE 'service_fee_%' ORDER BY setting_key";
$result = $conn->query($query);
if($result) {
    while($row = $result->fetch_assoc()) {
        $fee_settings[$row['setting_key']] = $row;
    }
}

// Get some sample properties for testing
$sample_properties_query = "SELECT id, CONCAT(property_type, ' in ', location) AS title, location, bedrooms FROM properties WHERE verification_status = 'verified' LIMIT 10";
$sample_result = $conn->query($sample_properties_query);
$sample_properties = [];
if($sample_result) {
    while($row = $sample_result->fetch_assoc()) {
        $sample_properties[] = $row;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Fee Management - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-nav {
            background: var(--dark-color);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }

        .admin-nav .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-nav .nav-links {
            display: flex;
            gap: 2rem;
        }

        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .admin-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .admin-nav .logout-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
        }

        .admin-nav .logout-btn:hover {
            background: #e66a00;
        }
        .fee-management-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .fee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .fee-tier-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .fee-tier-card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .fee-input-group {
            margin-bottom: 1rem;
        }

        .fee-input-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .fee-input-group input {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .fee-input-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 123, 0, 0.1);
        }

        .sample-properties {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .sample-properties h3 {
            color: var(--dark-color);
            margin-bottom: 1rem;
        }

        .property-sample {
            background: white;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 4px;
            border-left: 4px solid var(--primary-color);
        }

        .property-sample .fee-display {
            font-weight: bold;
            color: var(--primary-color);
        }

        .btn-update {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 1rem;
        }

        .btn-update:hover {
            background: #e66a00;
        }

        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <nav class="admin-nav">
        <div class="nav-container">
            <div class="logo">Walbrand Properties Marketplace - Admin</div>
            <div class="nav-links">
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="admin_properties.php">Properties</a>
                <a href="admin_users.php">Users</a>
                <a href="admin_fee_management.php" class="active">Fee Management</a>
                <a href="admin_settings.php">Settings</a>
                <button class="logout-btn" onclick="window.location.href='admin_logout.php'">Logout</button>
            </div>
        </div>
    </nav>

    <div class="fee-management-container">
        <h1>Service Fee Management</h1>
        <p>Configure service fees for property viewing requests based on location tiers and bedroom count.</p>

        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="form_action" value="update_fees">

            <div class="fee-grid">
                <!-- High-End Tier -->
                <div class="fee-tier-card">
                    <h3>High-End Areas</h3>
                    <p>Karen, Runda, Muthaiga, Gigiri, Kitisuru, etc.</p>

                    <div class="fee-input-group">
                        <label for="service_fee_high_end_1_bed">1 Bedroom (KES)</label>
                        <input type="number" id="service_fee_high_end_1_bed" name="service_fee_high_end_1_bed"
                               value="<?php echo $fee_settings['service_fee_high_end_1_bed']['setting_value'] ?? 2000; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_high_end_2_bed">2 Bedrooms (KES)</label>
                        <input type="number" id="service_fee_high_end_2_bed" name="service_fee_high_end_2_bed"
                               value="<?php echo $fee_settings['service_fee_high_end_2_bed']['setting_value'] ?? 2500; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_high_end_3_bed">3+ Bedrooms (KES)</label>
                        <input type="number" id="service_fee_high_end_3_bed" name="service_fee_high_end_3_bed"
                               value="<?php echo $fee_settings['service_fee_high_end_3_bed']['setting_value'] ?? 3500; ?>" min="0" required>
                    </div>
                </div>

                <!-- Mid-Tier -->
                <div class="fee-tier-card">
                    <h3>Mid-Tier Areas</h3>
                    <p>Kilimani, Lavington, Westlands, Nyayo Estate, etc.</p>

                    <div class="fee-input-group">
                        <label for="service_fee_mid_tier_1_bed">1 Bedroom (KES)</label>
                        <input type="number" id="service_fee_mid_tier_1_bed" name="service_fee_mid_tier_1_bed"
                               value="<?php echo $fee_settings['service_fee_mid_tier_1_bed']['setting_value'] ?? 1500; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_mid_tier_2_bed">2 Bedrooms (KES)</label>
                        <input type="number" id="service_fee_mid_tier_2_bed" name="service_fee_mid_tier_2_bed"
                               value="<?php echo $fee_settings['service_fee_mid_tier_2_bed']['setting_value'] ?? 2000; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_mid_tier_3_bed">3+ Bedrooms (KES)</label>
                        <input type="number" id="service_fee_mid_tier_3_bed" name="service_fee_mid_tier_3_bed"
                               value="<?php echo $fee_settings['service_fee_mid_tier_3_bed']['setting_value'] ?? 2500; ?>" min="0" required>
                    </div>
                </div>

                <!-- Affordable -->
                <div class="fee-tier-card">
                    <h3>Affordable Areas</h3>
                    <p>Kasaranai, Umoja, Pipeline, Nairobi CBD, etc.</p>

                    <div class="fee-input-group">
                        <label for="service_fee_affordable_1_bed">1 Bedroom (KES)</label>
                        <input type="number" id="service_fee_affordable_1_bed" name="service_fee_affordable_1_bed"
                               value="<?php echo $fee_settings['service_fee_affordable_1_bed']['setting_value'] ?? 1000; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_affordable_2_bed">2 Bedrooms (KES)</label>
                        <input type="number" id="service_fee_affordable_2_bed" name="service_fee_affordable_2_bed"
                               value="<?php echo $fee_settings['service_fee_affordable_2_bed']['setting_value'] ?? 1500; ?>" min="0" required>
                    </div>

                    <div class="fee-input-group">
                        <label for="service_fee_affordable_3_bed">3+ Bedrooms (KES)</label>
                        <input type="number" id="service_fee_affordable_3_bed" name="service_fee_affordable_3_bed"
                               value="<?php echo $fee_settings['service_fee_affordable_3_bed']['setting_value'] ?? 2000; ?>" min="0" required>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-update">Update Fee Settings</button>
        </form>

        <!-- Sample Properties Testing -->
        <div class="sample-properties">
            <h3>Sample Properties & Calculated Fees</h3>
            <p>Preview how the current fee settings affect sample properties:</p>

            <?php foreach($sample_properties as $property): ?>
                <?php
                require_once 'service_fee_helper.php';
                $fee = calculate_service_fee($property['location'], $property['bedrooms']);
                $tier = get_service_fee_tier($property['location']);
                ?>
                <div class="property-sample">
                    <strong><?php echo htmlspecialchars($property['title']); ?></strong><br>
                    Location: <?php echo htmlspecialchars($property['location']); ?> |
                    Bedrooms: <?php echo $property['bedrooms']; ?> |
                    Tier: <?php echo ucfirst($tier); ?><br>
                    <span class="fee-display">Service Fee: KSH <?php echo number_format($fee); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>