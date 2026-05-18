<?php
require_once 'config.php';
require_once 'helpers.php';

secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
$session_user_role = strtolower(trim($_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? ''));

if ($user_id <= 0) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'agent_digital_services.php';
    header('Location: login.php');
    exit();
}

$user_stmt = $conn->prepare("SELECT user_type, first_name, last_name FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc() ?: [];
$user_stmt->close();

$allowed_agent = in_array($user['user_type'] ?? '', ['agent', 'seller'], true);

if (!$allowed_agent) {
    header('Location: user_dashboard.php');
    exit();
}

// Ensure the digital products table exists
ensure_agent_digital_products_table_exists($conn);

// Handle form submissions
$message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product']) || (isset($_POST['action']) && $_POST['action'] === 'add_product')) {
        // Add new digital product
        $product_category = trim($_POST['product_category'] ?? '');
        $service_provider = trim($_POST['service_provider'] ?? '');
        $product_name = trim($_POST['product_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $pricing_type = trim($_POST['pricing_type'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $installation_fee = floatval($_POST['installation_fee'] ?? 0);
        $maintenance_fee = floatval($_POST['maintenance_fee'] ?? 0);
        $contract_terms = trim($_POST['contract_terms'] ?? '');
        $warranty_period = intval($_POST['warranty_period'] ?? 12);
        $availability_status = trim($_POST['availability_status'] ?? 'available');

        // Category-specific fields
        $wifi_speed = isset($_POST['wifi_speed_mbps']) ? floatval($_POST['wifi_speed_mbps']) : null;
        $wifi_reliability = isset($_POST['wifi_reliability_percent']) ? floatval($_POST['wifi_reliability_percent']) : null;
        $wifi_latency = isset($_POST['wifi_latency_ms']) ? intval($_POST['wifi_latency_ms']) : null;
        $wifi_coverage = isset($_POST['wifi_coverage_range_meters']) ? intval($_POST['wifi_coverage_range_meters']) : null;

        $cctv_resolution = trim($_POST['cctv_resolution_standard'] ?? '');
        $cctv_low_light = isset($_POST['cctv_low_light_iso']) ? intval($_POST['cctv_low_light_iso']) : null;
        $cctv_dynamic_range = isset($_POST['cctv_dynamic_range_stops']) ? floatval($_POST['cctv_dynamic_range_stops']) : null;
        $cctv_ir_distance = isset($_POST['cctv_ir_distance_meters']) ? intval($_POST['cctv_ir_distance_meters']) : null;
        $cctv_geometric_distortion = isset($_POST['cctv_geometric_distortion_percent']) ? floatval($_POST['cctv_geometric_distortion_percent']) : null;
        $cctv_veiling_flare = trim($_POST['cctv_veiling_flare_resistance'] ?? '');
        $cctv_frame_rate = isset($_POST['cctv_frame_rate_fps']) ? intval($_POST['cctv_frame_rate_fps']) : null;
        $cctv_color_reproduction = trim($_POST['cctv_color_reproduction_accuracy'] ?? '');

        $alexa_energy = isset($_POST['alexa_energy_consumption_watts']) ? floatval($_POST['alexa_energy_consumption_watts']) : null;
        $alexa_annual_energy = isset($_POST['alexa_annual_energy_kwh']) ? floatval($_POST['alexa_annual_energy_kwh']) : null;
        $alexa_latency = isset($_POST['alexa_system_latency_ms']) ? intval($_POST['alexa_system_latency_ms']) : null;
        $alexa_uptime = isset($_POST['alexa_uptime_percent']) ? floatval($_POST['alexa_uptime_percent']) : null;
        $alexa_responsiveness = isset($_POST['alexa_responsiveness_rating']) ? floatval($_POST['alexa_responsiveness_rating']) : null;

        // Validation
        $errors = [];
        if (empty($product_category) || !in_array($product_category, ['wifi', 'cctv', 'alexa'])) {
            $errors[] = "Please select a valid product category.";
        }
        if (empty($service_provider)) {
            $errors[] = "Service provider/brand is required.";
        }
        if (empty($product_name)) {
            $errors[] = "Product name is required.";
        }
        if (empty($pricing_type) || !in_array($pricing_type, ['one_time', 'recurring_monthly'])) {
            $errors[] = "Please select a valid pricing type.";
        }
        if ($price <= 0) {
            $errors[] = "Price must be greater than 0.";
        }

        // Category-specific validation
        if ($product_category === 'wifi') {
            if ($wifi_speed === null || $wifi_speed <= 0) $errors[] = "WiFi speed is required.";
            if ($wifi_reliability === null || $wifi_reliability < 0 || $wifi_reliability > 100) $errors[] = "WiFi reliability must be between 0-100%.";
            if ($wifi_latency === null || $wifi_latency < 0) $errors[] = "WiFi latency must be a positive number.";
            if ($wifi_coverage === null || $wifi_coverage <= 0) $errors[] = "WiFi coverage range is required.";
        } elseif ($product_category === 'cctv') {
            if (empty($cctv_resolution)) $errors[] = "CCTV resolution standard is required.";
            if ($cctv_low_light === null || $cctv_low_light <= 0) $errors[] = "CCTV low-light performance (ISO) is required.";
            if ($cctv_dynamic_range === null || $cctv_dynamic_range <= 0) $errors[] = "CCTV dynamic range is required.";
            if ($cctv_ir_distance === null || $cctv_ir_distance <= 0) $errors[] = "CCTV IR illumination distance is required.";
            if ($cctv_geometric_distortion === null || $cctv_geometric_distortion < 0) $errors[] = "CCTV geometric distortion must be a positive number.";
            if (empty($cctv_veiling_flare)) $errors[] = "CCTV veiling flare resistance is required.";
            if ($cctv_frame_rate === null || $cctv_frame_rate <= 0) $errors[] = "CCTV frame rate is required.";
            if (empty($cctv_color_reproduction)) $errors[] = "CCTV color reproduction accuracy is required.";
        } elseif ($product_category === 'alexa') {
            if ($alexa_energy === null || $alexa_energy <= 0) $errors[] = "Alexa energy consumption is required.";
            if ($alexa_annual_energy === null || $alexa_annual_energy <= 0) $errors[] = "Alexa annual energy usage is required.";
            if ($alexa_latency === null || $alexa_latency < 0) $errors[] = "Alexa system latency must be a positive number.";
            if ($alexa_uptime === null || $alexa_uptime < 0 || $alexa_uptime > 100) $errors[] = "Alexa uptime must be between 0-100%.";
            if ($alexa_responsiveness === null || $alexa_responsiveness < 1 || $alexa_responsiveness > 5) $errors[] = "Alexa responsiveness rating must be between 1-5.";
        }

        if (empty($errors)) {
            try {
                // Handle file uploads
                $uploaded_media = handle_product_media_uploads($user_id, $product_name);
                $product_images_json = !empty($uploaded_media['images']) ? json_encode($uploaded_media['images']) : null;
                $product_videos_json = !empty($uploaded_media['videos']) ? json_encode($uploaded_media['videos']) : null;

                $stmt = $conn->prepare("
                    INSERT INTO agent_digital_products (
                        agent_id, product_category, service_provider, product_name, description,
                        pricing_type, price, installation_fee, maintenance_fee, contract_terms,
                        warranty_period_months, availability_status,
                        wifi_speed_mbps, wifi_reliability_percent, wifi_latency_ms, wifi_coverage_range_meters,
                        cctv_resolution_standard, cctv_low_light_iso, cctv_dynamic_range_stops, cctv_ir_distance_meters,
                        cctv_geometric_distortion_percent, cctv_veiling_flare_resistance, cctv_frame_rate_fps, cctv_color_reproduction_accuracy,
                        alexa_energy_consumption_watts, alexa_annual_energy_kwh, alexa_system_latency_ms, alexa_uptime_percent, alexa_responsiveness_rating,
                        product_images, product_videos
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if (!$stmt) {
                    throw new Exception('Database prepare failed: ' . $conn->error);
                }

                $stmt->bind_param(
                    'isssssdddsisddiisididsisddiddss',
                    $user_id, $product_category, $service_provider, $product_name, $description,
                    $pricing_type, $price, $installation_fee, $maintenance_fee, $contract_terms,
                    $warranty_period, $availability_status,
                    $wifi_speed, $wifi_reliability, $wifi_latency, $wifi_coverage,
                    $cctv_resolution, $cctv_low_light, $cctv_dynamic_range, $cctv_ir_distance,
                    $cctv_geometric_distortion, $cctv_veiling_flare, $cctv_frame_rate, $cctv_color_reproduction,
                    $alexa_energy, $alexa_annual_energy, $alexa_latency, $alexa_uptime, $alexa_responsiveness,
                    $product_images_json, $product_videos_json
                );

                if ($stmt->execute()) {
                    $product_id = $conn->insert_id;

                    // Send notification to admins about new digital product submission
                    require_once 'notification_utils.php';
                    $agent_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
                    notifyAdminNewDigitalProductSubmission($product_id, $agent_name, $product_name, $product_category);

                    $message = "Digital product added successfully! It will be reviewed by an administrator before becoming visible to clients.";
                } else {
                    $error_message = "Failed to add product: " . $stmt->error;
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Failed to add product: " . $e->getMessage();
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    } elseif (isset($_POST['update_product'])) {
        // Update existing product
        $product_id = intval($_POST['product_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        if ($product_id > 0 && in_array($status, ['active', 'inactive'])) {
            $stmt = $conn->prepare("UPDATE agent_digital_products SET status = ?, updated_at = NOW() WHERE id = ? AND agent_id = ?");
            $stmt->bind_param('sii', $status, $product_id, $user_id);

            if ($stmt->execute()) {
                $message = "Product status updated successfully!";
            } else {
                $error_message = "Failed to update product status.";
            }
            $stmt->close();
        }
    } elseif (isset($_POST['delete_product'])) {
        // Delete product
        $product_id = intval($_POST['product_id'] ?? 0);

        if ($product_id > 0) {
            $stmt = $conn->prepare("DELETE FROM agent_digital_products WHERE id = ? AND agent_id = ? AND status != 'active'");
            $stmt->bind_param('ii', $product_id, $user_id);

            if ($stmt->execute()) {
                $message = "Product deleted successfully!";
            } else {
                $error_message = "Failed to delete product or product is currently active.";
            }
            $stmt->close();
        }
    }
}

// Fetch agent's digital products
$products = [];
$product_counts = ['total' => 0, 'active' => 0, 'pending_review' => 0, 'inactive' => 0, 'suspended' => 0];

if ($conn) {
    // Get product counts
    $count_stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM agent_digital_products WHERE agent_id = ? GROUP BY status");
    if ($count_stmt) {
        $count_stmt->bind_param('i', $user_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        while ($row = $count_result->fetch_assoc()) {
            $product_counts[$row['status']] = intval($row['total']);
            $product_counts['total'] += intval($row['total']);
        }
        $count_stmt->close();
    }

    // Get all products
    $products_stmt = $conn->prepare("
        SELECT * FROM agent_digital_products
        WHERE agent_id = ?
        ORDER BY created_at DESC
    ");
    if ($products_stmt) {
        $products_stmt->bind_param('i', $user_id);
        $products_stmt->execute();
        $products_result = $products_stmt->get_result();
        while ($row = $products_result->fetch_assoc()) {
            $products[] = $row;
        }
        $products_stmt->close();
    }
}

$service_types = [
    'wifi' => 'WiFi Network Services',
    'cctv' => 'CCTV Security Systems',
    'alexa' => 'Smart Home & Alexa Services'
];

$pricing_types = [
    'one_time' => 'One-time Payment',
    'recurring_monthly' => 'Monthly Subscription'
];

$availability_statuses = [
    'available' => 'Available',
    'limited' => 'Limited Availability',
    'unavailable' => 'Currently Unavailable'
];

$product_statuses = [
    'pending_review' => 'Pending Review',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'suspended' => 'Suspended'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Product Management - Agent Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #f97316;
            --secondary: #06b6d4;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border: #e2e8f0;
            --bg-light: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 2rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header .subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-top: 4px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.9rem;
        }

        .panel {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .panel-header {
            padding: 24px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h2 {
            font-size: 1.25rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-body {
            padding: 24px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #ea580c;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: #0891b2;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: #475569;
        }

        .btn-outline:hover {
            background: var(--bg-light);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            border-radius: 12px;
            width: 95%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 24px 24px 0 24px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .modal-body {
            padding: 0 24px;
        }

        .modal-footer {
            padding: 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .category-specific {
            display: none;
            padding: 20px;
            background: var(--bg-light);
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid var(--border);
        }

        .category-specific.active {
            display: block;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .metric-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .metric-item label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 5px;
            display: block;
            font-size: 0.85rem;
        }

        .metric-item input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.85rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background: var(--bg-light);
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid var(--border);
            font-size: 0.85rem;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid var(--border);
            color: #1e293b;
        }

        tr:hover {
            background: var(--bg-light);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-active { background: #dcfce7; color: #166534; }
        .status-pending_review { background: #fef3c7; color: #92400e; }
        .status-inactive { background: #f3f4f6; color: #374151; }
        .status-suspended { background: #fecaca; color: #dc2626; }

        .category-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .category-wifi { background: #dbeafe; color: #1e40af; }
        .category-cctv { background: #dcfce7; color: #166534; }
        .category-alexa { background: #fef3c7; color: #92400e; }

        .actions-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fecaca;
            border: 1px solid #fca5a5;
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }

            .header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
                padding: 20px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }

            .stat-card {
                padding: 20px;
            }

            .stat-card h3 {
                font-size: 1.5rem;
            }

            .panel-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                width: 95%;
                margin: 5% auto;
            }

            .modal-footer {
                flex-direction: column;
            }

            .modal-footer .btn {
                width: 100%;
            }

            .actions-cell {
                flex-direction: column;
            }

            .actions-cell .btn {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 0.8rem;
            }

            th, td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fa-solid fa-cogs"></i> Digital Product Management</h1>
                <p class="subtitle">Manage your WiFi, CCTV, and Smart Home service offerings</p>
            </div>
            <div class="header-actions">
                <a href="agent_dashboard.php" class="btn btn-outline">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="btn btn-primary" onclick="openAddProductModal()">
                    <i class="fa-solid fa-plus"></i> Add New Product
                </button>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-exclamation-circle"></i>
                <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= $product_counts['total'] ?></h3>
                <p>Total Products</p>
            </div>
            <div class="stat-card">
                <h3><?= $product_counts['active'] ?></h3>
                <p>Active Products</p>
            </div>
            <div class="stat-card">
                <h3><?= $product_counts['pending_review'] ?></h3>
                <p>Pending Review</p>
            </div>
            <div class="stat-card">
                <h3><?= $product_counts['inactive'] ?></h3>
                <p>Inactive Products</p>
            </div>
        </div>

        <!-- Products Table -->
        <div class="panel">
            <div class="panel-header">
                <h2><i class="fa-solid fa-list"></i> Your Digital Products</h2>
            </div>
            <div class="panel-body">
                <?php if (!empty($products)): ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Provider</th>
                                    <th>Pricing</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                            <?php if ($product['featured']): ?>
                                                <span style="background:#fef3c7;color:#92400e;padding:2px 6px;border-radius:10px;font-size:0.7rem;margin-left:8px;">Featured</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="category-badge category-<?= $product['product_category'] ?>">
                                                <?= ucfirst($product['product_category']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($product['service_provider']) ?></td>
                                        <td>
                                            <div style="font-weight:600;">
                                                <?= $product['pricing_type'] === 'recurring_monthly' ? 'KES ' . number_format($product['price']) . '/month' : 'KES ' . number_format($product['price']) ?>
                                            </div>
                                            <?php if ($product['installation_fee'] > 0): ?>
                                                <small style="color:#64748b;">+ KES <?= number_format($product['installation_fee']) ?> install</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $product['status'] ?>">
                                                <?= htmlspecialchars($product_statuses[$product['status']] ?? ucfirst(str_replace('_', ' ', $product['status']))) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($product['created_at'])) ?></td>
                                        <td class="actions-cell">
                                            <button class="btn btn-secondary btn-sm" onclick="viewProductDetails(<?= $product['id'] ?>)">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>
                                            <?php if ($product['status'] !== 'active'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="status" value="active">
                                                    <button type="submit" name="update_product" class="btn btn-success btn-sm">
                                                        <i class="fa-solid fa-play"></i> Activate
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <input type="hidden" name="status" value="inactive">
                                                    <button type="submit" name="update_product" class="btn btn-outline btn-sm">
                                                        <i class="fa-solid fa-pause"></i> Deactivate
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($product['status'] !== 'active'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-danger btn-sm">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-cogs"></i>
                        <h3>No Digital Products Yet</h3>
                        <p>Start by adding your first digital service offering to attract clients.</p>
                        <button class="btn btn-primary" onclick="openAddProductModal()">
                            <i class="fa-solid fa-plus"></i> Add Your First Product
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus"></i> Add New Digital Product</h3>
                <p>Fill in the details below to add a new service offering</p>
            </div>
            <form method="POST" id="addProductForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="product_category">Product Category *</label>
                            <select name="product_category" id="product_category" required onchange="toggleCategoryFields()">
                                <option value="">Select Category</option>
                                <option value="wifi">WiFi Network Services</option>
                                <option value="cctv">CCTV Security Systems</option>
                                <option value="alexa">Smart Home & Alexa Services</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="service_provider">Service Provider/Brand *</label>
                            <input type="text" name="service_provider" id="service_provider" required placeholder="e.g., Starlink, Wavelink Networks, Amazon">
                        </div>

                        <div class="form-group">
                            <label for="product_name">Product Name *</label>
                            <input type="text" name="product_name" id="product_name" required placeholder="e.g., Starlink Residential Internet">
                        </div>

                        <div class="form-group">
                            <label for="pricing_type">Pricing Type *</label>
                            <select name="pricing_type" id="pricing_type" required>
                                <option value="">Select Pricing Type</option>
                                <option value="one_time">One-time Payment</option>
                                <option value="recurring_monthly">Monthly Subscription</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="price">Price (KES) *</label>
                            <input type="number" name="price" id="price" required min="0" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="installation_fee">Installation Fee (KES)</label>
                            <input type="number" name="installation_fee" id="installation_fee" min="0" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="maintenance_fee">Monthly Maintenance Fee (KES)</label>
                            <input type="number" name="maintenance_fee" id="maintenance_fee" min="0" step="0.01" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="warranty_period">Warranty Period (Months)</label>
                            <input type="number" name="warranty_period" id="warranty_period" min="0" value="12">
                        </div>

                        <div class="form-group">
                            <label for="availability_status">Availability Status</label>
                            <select name="availability_status" id="availability_status">
                                <option value="available">Available</option>
                                <option value="limited">Limited Availability</option>
                                <option value="unavailable">Currently Unavailable</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Product Description</label>
                        <textarea name="description" id="description" placeholder="Describe the product features, benefits, and what clients can expect..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="contract_terms">Contract Terms & Conditions</label>
                        <textarea name="contract_terms" id="contract_terms" placeholder="Specify any contract terms, cancellation policies, or special conditions..."></textarea>
                    </div>

                    <!-- Media Upload Section -->
                    <div style="border: 2px solid var(--primary); border-radius: 8px; padding: 16px; margin-bottom: 20px; background: rgba(249, 115, 22, 0.05);">
                        <h4 style="margin-bottom: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-images"></i> Product Media (Images & Videos)
                        </h4>
                        
                        <!-- Product Images -->
                        <div class="form-group" style="margin-bottom: 16px;">
                            <label for="product_images">
                                <i class="fa-solid fa-image"></i> Product Images (Max 5 files, 5MB each)
                            </label>
                            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">Supported: JPG, PNG, GIF, WebP</p>
                            <input type="file" name="product_images[]" id="product_images" multiple accept="image/jpeg,image/png,image/gif,image/webp" style="display: block; width: 100%; padding: 12px; border: 2px dashed var(--border); border-radius: 6px; cursor: pointer;">
                            <div id="images-preview" style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px;"></div>
                        </div>

                        <!-- Product Videos -->
                        <div class="form-group">
                            <label for="product_videos">
                                <i class="fa-solid fa-video"></i> Product Videos (Max 5 files, 50MB each)
                            </label>
                            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 8px;">Supported: MP4, MOV, AVI, WebM</p>
                            <input type="file" name="product_videos[]" id="product_videos" multiple accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" style="display: block; width: 100%; padding: 12px; border: 2px dashed var(--border); border-radius: 6px; cursor: pointer;">
                            <div id="videos-preview" style="display: flex; flex-wrap: wrap; gap: 12px; margin-top: 12px;"></div>
                        </div>
                    </div>

                    <!-- WiFi Specific Fields -->
                    <div id="wifi-fields" class="category-specific">
                        <h4 style="margin-bottom:15px;color:#0f172a;"><i class="fa-solid fa-wifi"></i> WiFi Performance Metrics</h4>
                        <div class="metric-grid">
                            <div class="metric-item">
                                <label for="wifi_speed_mbps">Internet Speed (Mbps) *</label>
                                <input type="number" name="wifi_speed_mbps" id="wifi_speed_mbps" min="0" step="0.1" placeholder="100.0">
                            </div>
                            <div class="metric-item">
                                <label for="wifi_reliability_percent">Network Reliability (%) *</label>
                                <input type="number" name="wifi_reliability_percent" id="wifi_reliability_percent" min="0" max="100" step="0.1" placeholder="99.9">
                            </div>
                            <div class="metric-item">
                                <label for="wifi_latency_ms">Latency (ms) *</label>
                                <input type="number" name="wifi_latency_ms" id="wifi_latency_ms" min="0" placeholder="20">
                            </div>
                            <div class="metric-item">
                                <label for="wifi_coverage_range_meters">Coverage Range (meters) *</label>
                                <input type="number" name="wifi_coverage_range_meters" id="wifi_coverage_range_meters" min="0" placeholder="50">
                            </div>
                        </div>
                    </div>

                    <!-- CCTV Specific Fields -->
                    <div id="cctv-fields" class="category-specific">
                        <h4 style="margin-bottom:15px;color:#0f172a;"><i class="fa-solid fa-video"></i> CCTV Technical Specifications</h4>
                        <div class="metric-grid">
                            <div class="metric-item">
                                <label for="cctv_resolution_standard">Resolution Standard *</label>
                                <input type="text" name="cctv_resolution_standard" id="cctv_resolution_standard" placeholder="e.g., 4K UHD, 1080p">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_low_light_iso">Low-Light Performance (ISO) *</label>
                                <input type="number" name="cctv_low_light_iso" id="cctv_low_light_iso" min="0" placeholder="1600">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_dynamic_range_stops">Dynamic Range (stops) *</label>
                                <input type="number" name="cctv_dynamic_range_stops" id="cctv_dynamic_range_stops" min="0" step="0.1" placeholder="12.5">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_ir_distance_meters">IR Illumination Distance (m) *</label>
                                <input type="number" name="cctv_ir_distance_meters" id="cctv_ir_distance_meters" min="0" placeholder="30">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_geometric_distortion_percent">Geometric Distortion (%) *</label>
                                <input type="number" name="cctv_geometric_distortion_percent" id="cctv_geometric_distortion_percent" min="0" step="0.01" placeholder="0.5">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_veiling_flare_resistance">Veiling Flare Resistance *</label>
                                <input type="text" name="cctv_veiling_flare_resistance" id="cctv_veiling_flare_resistance" placeholder="Excellent">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_frame_rate_fps">Frame Rate (fps) *</label>
                                <input type="number" name="cctv_frame_rate_fps" id="cctv_frame_rate_fps" min="0" placeholder="30">
                            </div>
                            <div class="metric-item">
                                <label for="cctv_color_reproduction_accuracy">Color Reproduction Accuracy *</label>
                                <input type="text" name="cctv_color_reproduction_accuracy" id="cctv_color_reproduction_accuracy" placeholder="Delta E < 3">
                            </div>
                        </div>
                    </div>

                    <!-- Alexa Specific Fields -->
                    <div id="alexa-fields" class="category-specific">
                        <h4 style="margin-bottom:15px;color:#0f172a;"><i class="fa-solid fa-microphone"></i> Smart Home Performance Metrics</h4>
                        <div class="metric-grid">
                            <div class="metric-item">
                                <label for="alexa_energy_consumption_watts">Energy Consumption (Watts) *</label>
                                <input type="number" name="alexa_energy_consumption_watts" id="alexa_energy_consumption_watts" min="0" step="0.1" placeholder="15.5">
                            </div>
                            <div class="metric-item">
                                <label for="alexa_annual_energy_kwh">Annual Energy Usage (kWh) *</label>
                                <input type="number" name="alexa_annual_energy_kwh" id="alexa_annual_energy_kwh" min="0" step="0.1" placeholder="136.0">
                            </div>
                            <div class="metric-item">
                                <label for="alexa_system_latency_ms">System Latency (ms) *</label>
                                <input type="number" name="alexa_system_latency_ms" id="alexa_system_latency_ms" min="0" placeholder="150">
                            </div>
                            <div class="metric-item">
                                <label for="alexa_uptime_percent">Uptime Percentage (%) *</label>
                                <input type="number" name="alexa_uptime_percent" id="alexa_uptime_percent" min="0" max="100" step="0.01" placeholder="99.95">
                            </div>
                            <div class="metric-item">
                                <label for="alexa_responsiveness_rating">Responsiveness Rating (1-5) *</label>
                                <input type="number" name="alexa_responsiveness_rating" id="alexa_responsiveness_rating" min="1" max="5" step="0.1" placeholder="4.5">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeAddProductModal()">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">
                        <span class="loading" id="submit-loading" style="display:none;"></span>
                        Add Product
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div id="productDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fa-solid fa-eye"></i> Product Details</h3>
            </div>
            <div class="modal-body" id="productDetailsContent">
                <!-- Content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeProductDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openAddProductModal() {
            document.getElementById('addProductModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeAddProductModal() {
            document.getElementById('addProductModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            document.getElementById('addProductForm').reset();
            document.querySelectorAll('.category-specific').forEach(el => el.classList.remove('active'));
        }

        function openProductDetailsModal() {
            document.getElementById('productDetailsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeProductDetailsModal() {
            document.getElementById('productDetailsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function toggleCategoryFields() {
            const category = document.getElementById('product_category').value;
            document.querySelectorAll('.category-specific').forEach(el => el.classList.remove('active'));
            if (category) {
                document.getElementById(category + '-fields').classList.add('active');
            }
        }

        function viewProductDetails(productId) {
            // This would typically fetch product details via AJAX
            // For now, we'll show a placeholder
            const content = document.getElementById('productDetailsContent');
            content.innerHTML = '<p>Loading product details...</p>';
            openProductDetailsModal();

            // Simulate loading
            setTimeout(() => {
                content.innerHTML = '<p>Product details would be displayed here with all specifications and metrics.</p>';
            }, 500);
        }

        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const loading = document.getElementById('submit-loading');

            submitBtn.disabled = true;
            loading.style.display = 'inline-block';
            submitBtn.innerHTML = '<span class="loading"></span> Adding Product...';
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        };

        // Initialize any existing state
        document.addEventListener('DOMContentLoaded', function() {
            // Any initialization code here
            
            // Handle image file selection and preview
            const imagesInput = document.getElementById('product_images');
            if (imagesInput) {
                imagesInput.addEventListener('change', function(e) {
                    handleMediaPreview(this, 'images-preview', 5, 'image');
                });
            }
            
            // Handle video file selection and preview
            const videosInput = document.getElementById('product_videos');
            if (videosInput) {
                videosInput.addEventListener('change', function(e) {
                    handleMediaPreview(this, 'videos-preview', 5, 'video');
                });
            }
        });

        function handleMediaPreview(inputElement, previewId, maxFiles, mediaType) {
            const previewContainer = document.getElementById(previewId);
            const files = inputElement.files;
            
            if (files.length > maxFiles) {
                alert(`Maximum ${maxFiles} ${mediaType}s allowed. ${files.length} selected.`);
                inputElement.value = '';
                previewContainer.innerHTML = '';
                return;
            }
            
            previewContainer.innerHTML = '';
            let validFiles = 0;
            const maxFileSize = mediaType === 'image' ? 5242880 : 52428800; // 5MB for images, 50MB for videos
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                // Validate file size
                if (file.size > maxFileSize) {
                    const maxMB = maxFileSize / 1048576;
                    alert(`File "${file.name}" exceeds ${maxMB}MB limit`);
                    continue;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const thumbnail = document.createElement('div');
                    thumbnail.style.cssText = 'position: relative; width: 100px; height: 100px; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;';
                    
                    if (mediaType === 'image') {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                        thumbnail.appendChild(img);
                    } else {
                        const video = document.createElement('video');
                        video.src = e.target.result;
                        video.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                        const playIcon = document.createElement('div');
                        playIcon.innerHTML = '<i class="fa-solid fa-play" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 24px; text-shadow: 0 0 4px rgba(0,0,0,0.7);"></i>';
                        playIcon.style.cssText = 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.3);';
                        thumbnail.appendChild(video);
                        thumbnail.appendChild(playIcon);
                    }
                    
                    const fileNameLabel = document.createElement('div');
                    fileNameLabel.textContent = file.name.substring(0, 12) + '...';
                    fileNameLabel.style.cssText = 'font-size: 0.75rem; color: #64748b; margin-top: 4px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;';
                    
                    const container = document.createElement('div');
                    container.style.cssText = 'display: flex; flex-direction: column; align-items: center;';
                    container.appendChild(thumbnail);
                    container.appendChild(fileNameLabel);
                    
                    previewContainer.appendChild(container);
                };
                
                reader.readAsDataURL(file);
                validFiles++;
            }
            
            if (validFiles === 0) {
                inputElement.value = '';
            }
        }

    </script>
</body>
</html>
