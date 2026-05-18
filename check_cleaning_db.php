<?php
require_once 'config.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleaning Services Database Check</title>
    <style>
        body {
            font-family: 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            background: #f5f7fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        h2 {
            color: #667eea;
            margin-top: 25px;
            margin-bottom: 15px;
            border-bottom: 2px solid#eef4fb;
            padding-bottom: 10px;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 600;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        ul li {
            padding: 8px 0;
            padding-left: 25px;
            position: relative;
        }
        ul li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: green;
            font-weight: bold;
        }
        .missing li:before {
            content: "✗";
            color: red;
        }
        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #ff7b00, #5cfaff);
            color: white;
            padding: 12px 25px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 5px 10px 0;
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #0c3f8f;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Cleaning Services Database Status</h1>

        <?php
        // Check if cleaning tables exist
        $tables = ['cleaning_requests', 'service_providers', 'cleaning_categories', 'service_assignments'];
        $existing_tables = [];

        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                $existing_tables[] = $table;
            }
        }

        $all_exist = count($existing_tables) === count($tables);
        ?>

        <h2>Database Status</h2>
        <?php if ($all_exist): ?>
            <div class="status-box status-success">
                ✅ All cleaning services tables are initialized!
            </div>

            <h3>Quick Links:</h3>
            <div>
                <a href="index.php" class="cta-button">🏠 Go to Homepage</a>
                <a href="admin_control_panel.php" class="cta-button">⚙️ Admin Panel</a>
            </div>

            <h3>What's Available:</h3>
            <div class="info-box">
                <strong>Main Website:</strong> Click "Cleaning Services" in navigation
                <br><br>
                <strong>Admin Dashboard:</strong> New cleaning service statistics and management link
                <br><br>
                <strong>Booking System:</strong> <a href="cleaning_services/pages/booking.php" style="color: inherit; font-weight: bold;">Book a Service</a>
                <br><br>
                <strong>Provider Registration:</strong> <a href="cleaning_services/pages/provider_register.php" style="color: inherit; font-weight: bold;">Register as Provider</a>
                <br><br>
                <strong>Admin Management:</strong> <a href="cleaning_services/admin/index.php" style="color: inherit; font-weight: bold;">Cleaning Services Admin</a>
            </div>

        <?php else: ?>
            <div class="status-box status-error">
                ❌ Missing database tables! Setup required.
            </div>

            <p>The following tables are missing:</p>
            <ul class="missing">
                <?php foreach (array_diff($tables, $existing_tables) as $table): ?>
                    <li><?php echo htmlspecialchars($table); ?></li>
                <?php endforeach; ?>
            </ul>

            <h3>Initialize Database Now:</h3>
            <a href="cleaning_setup.php?key=setup_cleaning_2026" class="cta-button" style="display: block; text-align: center; padding: 15px;">
                🚀 Run Setup Wizard
            </a>

            <div class="info-box">
                <strong>What the setup will do:</strong>
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Create cleaning_categories table (10 service types)</li>
                    <li>Create cleaning_requests table (client bookings)</li>
                    <li>Create service_providers table (provider profiles)</li>
                    <li>Create service_assignments table (assignments)</li>
                    <li>Create provider_reviews table (feedback)</li>
                </ul>
            </div>
        <?php endif; ?>

        <h2>Existing Tables</h2>
        <?php if (!empty($existing_tables)): ?>
            <ul>
                <?php foreach ($existing_tables as $table): ?>
                    <li><?php echo htmlspecialchars($table); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: #666;">No cleaning service tables exist yet. Run setup above to create them.</p>
        <?php endif; ?>
    </div>
</body>
</html>