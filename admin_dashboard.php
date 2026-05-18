<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Get pending properties for verification
$query = "SELECT p.*, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name, u.email, u.phone,
          (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as image_count
          FROM properties p
          JOIN users u ON p.seller_id = u.id
          WHERE p.verification_status = 'pending'
          ORDER BY p.created_at DESC";

$result = $conn->query($query);
$pending_properties = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $pending_properties[] = $row;
    }
}

// Get stats
$stats_query = "SELECT 
                COUNT(*) as total_properties,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_count,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
                FROM properties";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Verification - Walbrand Properties Marketplace & Interiors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f97316;
            --success: #f97316;
            --warning: #f97316;
            --danger: #f97316;
            --text-secondary: #ea580c;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #fff7ed;
            color: #ea580c;
            margin: 0;
            padding: 0;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: #ea580c;
            color: #fff7ed;
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid rgba(249,115,22,0.3);
        }

        .sidebar-header {
            padding: 0 24px 28px;
            border-bottom: 1px solid rgba(249,115,22,0.3);
            margin-bottom: 24px;
            text-align: center;
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .sidebar-header .logo-icon {
            width: 44px;
            height: 44px;
            background: #eef4fb;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .sidebar-header h3 {
            font-size: 1.05rem;
            margin-bottom: 4px;
            color: #fff;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            color: rgba(255,247,237,0.88);
            margin: 0;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            color: rgba(255,247,237,0.95);
            text-decoration: none;
            border-radius: 16px;
            font-size: 0.95rem;
            transition: background 0.2s ease, color 0.2s ease;
        }

        .sidebar-menu a:hover {
            background: rgba(249,115,22,0.3);
            color: #fff;
        }

        .sidebar-menu a.active {
            background: rgba(249,115,22,0.4);
            color: #fff;
        }

        .sidebar-menu .menu-icon {
            width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            background: #fff7ed;
            min-height: 100vh;
            padding: 28px 30px 32px;
            box-sizing: border-box;
        }

        .welcome-section {
            background: #eef4fb;
            color: white;
            padding: 2.3rem 2rem;
            border-radius: 24px;
            box-shadow: 0 20px 45px rgba(249,115,22,0.3);
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
        }

        .welcome-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.2rem;
        }

        .welcome-text h1 {
            font-size: 2rem;
            line-height: 1.05;
            margin: 0 0 0.6rem 0;
            font-weight: 800;
        }

        .welcome-text p {
            margin: 0;
            font-size: 1rem;
            color: rgba(255,255,255,0.95);
            max-width: 720px;
            line-height: 1.7;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 16px;
            background: rgba(255,255,255,0.3);
            border: 1px solid rgba(255,255,255,0.4);
            border-radius: 18px;
            padding: 16px 18px;
            min-width: 260px;
        }

        .admin-avatar {
            width: 54px;
            height: 54px;
            background: rgba(255,255,255,0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .admin-details h4 {
            margin: 0;
            font-size: 1rem;
            color: #fff;
        }

        .admin-details p {
            margin: 0.35rem 0 0;
            font-size: 0.92rem;
            color: rgba(255,255,255,0.92);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 26px;
        }

        .stat-card {
            background: #fed7aa;
            border-radius: 22px;
            padding: 22px;
            border: 1px solid #f97316;
            box-shadow: 0 20px 45px rgba(249,115,22,0.2);
            min-height: 150px;
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .stat-icon.primary {
            background: #eef4fb;
        }

        .stat-icon.success {
            background: #eef4fb;
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f97316, #ea580c);
        }

        .stat-icon.danger {
            background: #eef4fb;
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 0.82rem;
            color: #ea580c;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #ea580c;
        }

        .property-verification-card {
            background: #fed7aa;
            border-radius: 22px;
            box-shadow: 0 20px 45px rgba(249,115,22,0.2);
            margin-bottom: 22px;
            overflow: hidden;
            border: 1px solid #f97316;
            min-height: 400px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .property-header {
            background: #fdba74;
            padding: 22px;
            border-bottom: 1px solid #f97316;
        }

        .property-title {
            font-size: 1rem;
            font-weight: 800;
            color: #ea580c;
            margin-bottom: 6px;
        }

        .property-meta {
            color: #ea580c;
            font-size: 0.94rem;
        }

        .property-body {
            padding: 22px;
        }

        .property-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 18px;
        }

        .detail-item {
            background: #fdba74;
            padding: 16px;
            border-radius: 18px;
            border: 1px solid #f97316;
        }

        .detail-item strong {
            color: #ea580c;
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .detail-item span {
            color: #ea580c;
            font-size: 0.95rem;
        }

        .property-description {
            background: #fdba74;
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #f97316;
            margin-bottom: 18px;
        }

        .property-description strong {
            color: #ea580c;
            display: block;
            margin-bottom: 8px;
        }

        .property-description p {
            color: #ea580c;
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.75;
        }

        .verification-form {
            display: flex;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 220px;
        }

        .form-group label {
            display: block;
            font-weight: 700;
            color: #ea580c;
            margin-bottom: 8px;
            font-size: 0.92rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 14px;
            border: 1px solid #f97316;
            border-radius: 16px;
            font-family: inherit;
            font-size: 0.95rem;
            resize: vertical;
            min-height: 100px;
            background: #fff7ed;
            color: #ea580c;
        }

        .btn {
            padding: 12px 18px;
            border: none;
            border-radius: 14px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-success {
            background: #eef4fb;
            color: white;
        }

        .btn-success:hover {
            background: #eef4fb;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
        }

        .btn-danger:hover {
            background: #eef4fb;
        }

        .empty-state {
            text-align: center;
            padding: 42px 24px;
            color: #ea580c;
            background: #fed7aa;
            border-radius: 24px;
            border: 1px dashed #f97316;
        }

        .empty-state h3 {
            font-size: 1.1rem;
            font-weight: 800;
            color: #ea580c;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 0.96rem;
            margin: 0;
        }

        @media (max-width: 1160px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .verification-form {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .main-content {
                margin-left: 0;
            }

            .welcome-header {
                flex-direction: column;
                gap: 18px;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">🏢</div>
                    <div>
                        <h3>Walbrand</h3>
                        <p>Admin Panel</p>
                    </div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="admin_control_panel.php">
                    <span class="menu-icon">📊</span>Dashboard</a></li>
                <li><a href="admin_dashboard.php" class="active">
                    <span class="menu-icon">✅</span>Property Verification</a></li>
                <li><a href="admin_users.php">
                    <span class="menu-icon">👥</span>Users</a></li>
                <li><a href="admin_properties.php">
                    <span class="menu-icon">🏠</span>Properties</a></li>
                <li><a href="admin_investments.php">
                    <span class="menu-icon">💰</span>Investments</a></li>
                <li><a href="property_listings.php?category=NightlyFied">
                    <span class="menu-icon">🏨</span>NightlyFied Listings</a></li>
                <li><a href="property_listings.php?category=hotel_reservation">
                    <span class="menu-icon">🏩</span>Hotel Reservations</a></li>
                <li><a href="admin_fee_management.php">
                    <span class="menu-icon">💰</span>Fee Management</a></li>
                <li><a href="admin_settings.php">
                    <span class="menu-icon">⚙️</span>Settings</a></li>
                <li><a href="admin_audit_logs.php">
                    <span class="menu-icon">📋</span>Audit Logs</a></li>
                <li><a href="index.php">
                    <span class="menu-icon">🌐</span>View Website</a></li>
                
                
                <li><a href="admin_logout.php">
                    <span class="menu-icon">🚪</span>Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Welcome Section -->
            <section class="welcome-section">
                <div class="welcome-header">
                    <div class="welcome-text">
                        <h1><i class="fas fa-check-circle"></i> Property Verification</h1>
                        <p>Review and verify property listings submitted by sellers and agents</p>
                    </div>
                    <div class="admin-info">
                        <div class="admin-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="admin-details">
                            <h4><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></h4>
                            <p>Administrator</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Total Properties</div>
                            <div class="stat-value"><?php echo $stats['total_properties'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="fas fa-home"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Pending Verification</div>
                            <div class="stat-value"><?php echo $stats['pending_count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card success">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Verified</div>
                            <div class="stat-value"><?php echo $stats['verified_count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-header">
                        <div class="stat-content">
                            <div class="stat-label">Rejected</div>
                            <div class="stat-value"><?php echo $stats['rejected_count'] ?? 0; ?></div>
                        </div>
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Properties Section -->
            <h2 style="color: var(--primary-color); margin-bottom: 2rem;"><i class="fas fa-list"></i> Properties Awaiting Verification</h2>

            <?php if (count($pending_properties) > 0): ?>
                <?php foreach ($pending_properties as $prop): ?>
                    <div class="property-verification-card">
                        <div class="property-header">
                            <div class="property-title">
                                <i class="fas fa-building"></i> <?php echo htmlspecialchars($prop['property_type']); ?> - <?php echo htmlspecialchars($prop['location']); ?>
                                <span style="color: var(--warning); font-size: 0.9rem; font-weight: normal;">(Uploaded: <?php echo date('M d, Y', strtotime($prop['created_at'])); ?>)</span>
                            </div>
                            <div class="property-meta">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($prop['seller_name']); ?> | <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($prop['email']); ?> | <i class="fas fa-phone"></i> <?php echo htmlspecialchars($prop['phone']); ?>
                            </div>
                        </div>

                        <div class="property-body">
                            <div class="property-details-grid">
                                <div class="detail-item">
                                    <strong><i class="fas fa-tag"></i> Category</strong>
                                    <span><?php echo htmlspecialchars(get_property_category($prop)); ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-dollar-sign"></i> Price (KES)</strong>
                                    <span><?php echo number_format($prop['price'], 0); ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-handshake"></i> Negotiable</strong>
                                    <span><?php echo $prop['is_negotiable'] ? '<i class="fas fa-check" style="color: var(--success);"></i> Yes' : '<i class="fas fa-times" style="color: var(--danger);"></i> No'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-bed"></i> Bedrooms</strong>
                                    <span><?php echo $prop['bedrooms'] > 0 ? $prop['bedrooms'] : 'N/A'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-bath"></i> Bathrooms</strong>
                                    <span><?php echo $prop['bathrooms'] > 0 ? $prop['bathrooms'] : 'N/A'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-ruler-combined"></i> Size (sqm)</strong>
                                    <span><?php echo $prop['size_sqm'] > 0 ? $prop['size_sqm'] : 'N/A'; ?></span>
                                </div>
                                <div class="detail-item">
                                    <strong><i class="fas fa-images"></i> Images Uploaded</strong>
                                    <span><?php echo $prop['image_count']; ?></span>
                                </div>
                            </div>

                            <div class="property-description">
                                <strong><i class="fas fa-align-left"></i> Description</strong>
                                <p><?php echo htmlspecialchars($prop['description']); ?></p>
                            </div>

                            <div class="verification-form">
                                <div class="form-group">
                                    <label><i class="fas fa-comment"></i> Verification Notes (Optional)</label>
                                    <textarea placeholder="Add notes about this verification..." id="notes-<?php echo $prop['id']; ?>"></textarea>
                                </div>
                                <button class="btn btn-success" onclick="verifyProperty(<?php echo $prop['id']; ?>, true)">
                                    <i class="fas fa-check"></i> Verify
                                </button>
                                <button class="btn btn-danger" onclick="verifyProperty(<?php echo $prop['id']; ?>, false)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;"></i>
                    <h3>All Clear!</h3>
                    <p>No properties awaiting verification at the moment.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function verifyProperty(propertyId, isVerified) {
            const notes = document.getElementById('notes-' + propertyId).value;

            const formData = new FormData();
            formData.append('action', 'verify_property');
            formData.append('property_id', propertyId);
            formData.append('is_verified', isVerified ? 1 : 0);
            formData.append('notes', notes);

            fetch('property_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(isVerified ? 'Property verified successfully!' : 'Property rejected.');
                    location.reload();
                } else {
                    showError('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred');
            });
        }

    </script>
</body>
</html>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Dashboard - Property Verification</h1>
            <div style="text-align: right;">
                <p>Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Admin') ?></p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem;">
                    <a href="admin_setup/index.php" style="color: #666; text-decoration: none;">⚙️ Admin Setup</a> |
                    <a href="admin_fee_management.php" style="color: #666; text-decoration: none;">💰 Fee Management</a>
                </p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="admin-stats">
            <div class="stat-card">
                <h3>Total Properties</h3>
                <div class="stat-number"><?= $stats['total_properties'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending Verification</h3>
                <div class="stat-number" style="color: #F59E0B;"><?= $stats['pending_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h3>Verified</h3>
                <div class="stat-number" style="color: #13c764;"><?= $stats['verified_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h3>Rejected</h3>
                <div class="stat-number" style="color: #da0808;"><?= $stats['rejected_count'] ?? 0 ?></div>
            </div>
        </div>

        <!-- Verification Section -->
        <h2 style="color: var(--primary); margin-bottom: 2rem;">Properties Awaiting Verification</h2>

        <?php if (count($pending_properties) > 0): ?>
            <?php foreach ($pending_properties as $prop): ?>
                <div class="property-verification-card">
                    <h3>
                        <?= htmlspecialchars($prop['property_type']) ?> - <?= htmlspecialchars($prop['location']) ?>
                        <span style="color: #F59E0B; font-size: 0.9rem;">(Uploaded: <?= date('M d, Y', strtotime($prop['created_at'])) ?>)</span>
                    </h3>

                    <div class="property-details-grid">
                        <div class="detail-item">
                            <strong>Seller Name</strong>
                            <?= htmlspecialchars($prop['seller_name']) ?>
                        </div>
                        <div class="detail-item">
                            <strong>Seller Email</strong>
                            <?= htmlspecialchars($prop['email']) ?>
                        </div>
                        <div class="detail-item">
                            <strong>Seller Phone</strong>
                            <?= htmlspecialchars($prop['phone']) ?>
                        </div>
                        <div class="detail-item">
                            <strong>Category</strong>
                            <?= htmlspecialchars(get_property_category($prop)) ?>
                        </div>
                        <div class="detail-item">
                            <strong>Price (KES)</strong>
                            <?= number_format($prop['price'], 0) ?>
                        </div>
                        <div class="detail-item">
                            <strong>Negotiable</strong>
                            <?= $prop['is_negotiable'] ? '✓ Yes' : '✗ No' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Bedrooms</strong>
                            <?= $prop['bedrooms'] > 0 ? $prop['bedrooms'] : 'N/A' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Bathrooms</strong>
                            <?= $prop['bathrooms'] > 0 ? $prop['bathrooms'] : 'N/A' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Size (sqm)</strong>
                            <?= $prop['size_sqm'] > 0 ? $prop['size_sqm'] : 'N/A' ?>
                        </div>
                        <div class="detail-item">
                            <strong>Images Uploaded</strong>
                            <?= $prop['image_count'] ?>
                        </div>
                    </div>

                    <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                        <strong style="color: var(--primary);">Description</strong>
                        <p style="color: #666; margin-top: 10px;"><?= htmlspecialchars($prop['description']) ?></p>
                    </div>

                    <div class="verification-form">
                        <textarea placeholder="Add verification notes (optional)" id="notes-<?= $prop['id'] ?>"></textarea>
                        <button class="verify-btn" onclick="verifyProperty(<?= $prop['id'] ?>, true)">✓ Verify</button>
                        <button class="reject-btn" onclick="verifyProperty(<?= $prop['id'] ?>, false)">✗ Reject</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-pending">
                <h2>✓ All Clear!</h2>
                <p style="color: #666; font-size: 1.1rem;">No properties awaiting verification at the moment.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function verifyProperty(propertyId, isVerified) {
            const notes = document.getElementById('notes-' + propertyId).value;
            
            const formData = new FormData();
            formData.append('action', 'verify_property');
            formData.append('property_id', propertyId);
            formData.append('is_verified', isVerified ? 1 : 0);
            formData.append('notes', notes);

            fetch('property_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess(isVerified ? 'Property verified successfully!' : 'Property rejected.');
                    location.reload();
                } else {
                    showError('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred');
            });
        }

        async function logout() {
            const confirmed = await showConfirm('Are you sure you want to logout?');
            if (confirmed) {
                window.location.href = 'admin_logout.php';
            }
        }

        function goToHome() {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>
