<?php
require_once 'config.php';
require_once 'helpers.php';
require_once 'config_mover_system.php';

secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
$session_user_role = strtolower(trim($_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? ''));

if ($user_id <= 0) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'agent_dashboard.php';
    header('Location: login.php');
    exit();
}

$user_stmt = $conn->prepare("SELECT user_type, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc() ?: [];
$user_stmt->close();

$allowed_admin = in_array($session_user_role, ['admin', 'super_admin'], true);
$allowed_agent = in_array($user['user_type'] ?? '', ['agent', 'seller'], true);

if (!$allowed_admin && !$allowed_agent) {
    header('Location: user_dashboard.php');
    exit();
}

$user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($user_name)) {
    $user_name = 'Agent';
}

$first_name = explode(' ', $user_name)[0];
$profile_picture = trim($user['profile_picture'] ?? '');
$avatar_initials = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? 'G', 0, 1));

// Initialize all variables
$leads_list = [];
$properties_uploaded = 0;
$properties_successful = 0;
$properties_rejected = 0;
$clients_connected = 0;
$clients_complained = 0;
$clients_happy = 0;
$clients_failed_pick = 0;
$viewings_assigned = 0;
$viewings_completed = 0;
$viewings_pending = 0;
$viewings_expired = 0;
$delivery_completed = 0;
$delivery_incomplete = 0;
$delivery_rescheduled = 0;
$delivery_groups = [];
$tasks_completed = 0;
$tasks_incomplete = 0;
$tasks_pending = 0;
$tasks_by_type = [];
$messages = [];
$unread_message_count = 0;

ensure_consultations_table_exists($conn);

if ($conn) {
    // ===== LEADS =====
    $leads_list_stmt = $conn->prepare("
        SELECT 
            COALESCE(u.first_name, '') as name,
            u.email,
            u.phone,
            c.consultation_type,
            c.status,
            c.created_at
        FROM consultations c
        LEFT JOIN users u ON c.user_id = u.id
        JOIN properties p ON c.property_id = p.id
        WHERE p.seller_id = ?
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    if ($leads_list_stmt) {
        $leads_list_stmt->bind_param('i', $user_id);
        $leads_list_stmt->execute();
        $leads_result = $leads_list_stmt->get_result();
        while ($row = $leads_result->fetch_assoc()) {
            $leads_list[] = $row;
        }
        $leads_list_stmt->close();
    }

    // ===== PROPERTIES =====
    $properties_uploaded_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM properties WHERE seller_id = ?");
    if ($properties_uploaded_stmt) {
        $properties_uploaded_stmt->bind_param('i', $user_id);
        $properties_uploaded_stmt->execute();
        $properties_uploaded = intval($properties_uploaded_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_uploaded_stmt->close();
    }

    $properties_successful_stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) AS total FROM properties p JOIN consultations c ON p.id = c.property_id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($properties_successful_stmt) {
        $properties_successful_stmt->bind_param('i', $user_id);
        $properties_successful_stmt->execute();
        $properties_successful = intval($properties_successful_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_successful_stmt->close();
    }

    $properties_rejected_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM properties WHERE seller_id = ? AND status = 'rejected'");
    if ($properties_rejected_stmt) {
        $properties_rejected_stmt->bind_param('i', $user_id);
        $properties_rejected_stmt->execute();
        $properties_rejected = intval($properties_rejected_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_rejected_stmt->close();
    }

    // ===== CLIENTS =====
    $clients_connected_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($clients_connected_stmt) {
        $clients_connected_stmt->bind_param('i', $user_id);
        $clients_connected_stmt->execute();
        $clients_connected = intval($clients_connected_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_connected_stmt->close();
    }

    $clients_happy_stmt = $conn->prepare("SELECT COUNT(DISTINCT cf.client_id) AS total FROM client_feedback cf WHERE cf.agent_id = ? AND cf.feedback_type = 'positive'");
    if ($clients_happy_stmt) {
        $clients_happy_stmt->bind_param('i', $user_id);
        $clients_happy_stmt->execute();
        $clients_happy = intval($clients_happy_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_happy_stmt->close();
    }

    $clients_complained_stmt = $conn->prepare("SELECT COUNT(DISTINCT cf.client_id) AS total FROM client_feedback cf WHERE cf.agent_id = ? AND cf.feedback_type = 'negative'");
    if ($clients_complained_stmt) {
        $clients_complained_stmt->bind_param('i', $user_id);
        $clients_complained_stmt->execute();
        $clients_complained = intval($clients_complained_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_complained_stmt->close();
    }

    $clients_failed_pick_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'cancelled'");
    if ($clients_failed_pick_stmt) {
        $clients_failed_pick_stmt->bind_param('i', $user_id);
        $clients_failed_pick_stmt->execute();
        $clients_failed_pick = intval($clients_failed_pick_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_failed_pick_stmt->close();
    }

    // ===== VIEWINGS =====
    $viewings_assigned_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing'");
    if ($viewings_assigned_stmt) {
        $viewings_assigned_stmt->bind_param('i', $user_id);
        $viewings_assigned_stmt->execute();
        $viewings_assigned = intval($viewings_assigned_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_assigned_stmt->close();
    }

    $viewings_completed_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status = 'completed'");
    if ($viewings_completed_stmt) {
        $viewings_completed_stmt->bind_param('i', $user_id);
        $viewings_completed_stmt->execute();
        $viewings_completed = intval($viewings_completed_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_completed_stmt->close();
    }

    $viewings_pending_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status IN ('pending','scheduled')");
    if ($viewings_pending_stmt) {
        $viewings_pending_stmt->bind_param('i', $user_id);
        $viewings_pending_stmt->execute();
        $viewings_pending = intval($viewings_pending_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_pending_stmt->close();
    }

    $viewings_expired_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status != 'completed' AND c.scheduled_date < CURDATE()");
    if ($viewings_expired_stmt) {
        $viewings_expired_stmt->bind_param('i', $user_id);
        $viewings_expired_stmt->execute();
        $viewings_expired = intval($viewings_expired_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_expired_stmt->close();
    }

    // ===== DELIVERY GROUPS =====
    $mover_conn = getMoverDatabaseConnection();
    if ($mover_conn) {
        $delivery_completed_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'completed'");
        if ($delivery_completed_stmt) {
            $delivery_completed_stmt->execute();
            $delivery_completed = intval($delivery_completed_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_completed_stmt->close();
        }

        $delivery_incomplete_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status IN ('pending','in_progress')");
        if ($delivery_incomplete_stmt) {
            $delivery_incomplete_stmt->execute();
            $delivery_incomplete = intval($delivery_incomplete_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_incomplete_stmt->close();
        }

        $delivery_rescheduled_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'rescheduled'");
        if ($delivery_rescheduled_stmt) {
            $delivery_rescheduled_stmt->execute();
            $delivery_rescheduled = intval($delivery_rescheduled_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_rescheduled_stmt->close();
        }

        // Get delivery groups with performance metrics
        $groups_stmt = $mover_conn->prepare("
            SELECT 
                mg.id, 
                mg.group_name, 
                COUNT(DISTINCT mb.id) as total_bookings,
                SUM(CASE WHEN mb.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN mb.status IN ('pending','in_progress') THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN mb.status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_bookings,
                AVG(mr.rating) as avg_rating,
                COUNT(DISTINCT mr.id) as feedback_count
            FROM mover_groups mg 
            LEFT JOIN mover_bookings mb ON mg.id = mb.assigned_group_id 
            LEFT JOIN mover_reviews mr ON mb.id = mr.booking_id 
            GROUP BY mg.id 
            ORDER BY avg_rating DESC
            LIMIT 10
        ");
        if ($groups_stmt) {
            $groups_stmt->execute();
            $groups_result = $groups_stmt->get_result();
            while ($row = $groups_result->fetch_assoc()) {
                $delivery_groups[] = $row;
            }
            $groups_stmt->close();
        }
    }

    // ===== TASKS BY TYPE =====
    $task_types = ['NightlyFied', 'Hotel Reservation', 'Student Housing', 'Sold Properties', 'Delivery', 'House Swap', 'Cleaning Services', 'WIFI Distribution', 'CCTV Installation', 'Alexa Installation', 'Interior Designs'];
    
    $tasks_by_type_stmt = $conn->prepare("
        SELECT 
            c.consultation_type, 
            c.status, 
            COUNT(*) AS total 
        FROM consultations c 
        JOIN properties p ON c.property_id = p.id 
        WHERE p.seller_id = ? 
        GROUP BY c.consultation_type, c.status
    ");
    if ($tasks_by_type_stmt) {
        $tasks_by_type_stmt->bind_param('i', $user_id);
        $tasks_by_type_stmt->execute();
        $tasks_by_type_result = $tasks_by_type_stmt->get_result();
        while ($row = $tasks_by_type_result->fetch_assoc()) {
            $type = $row['consultation_type'] ?? 'Other';
            $status = $row['status'] ?? 'pending';
            if (!isset($tasks_by_type[$type])) {
                $tasks_by_type[$type] = ['completed' => 0, 'incomplete' => 0, 'pending' => 0];
            }
            if ($status === 'completed') {
                $tasks_by_type[$type]['completed'] = intval($row['total']);
            } elseif ($status === 'pending') {
                $tasks_by_type[$type]['pending'] = intval($row['total']);
            } else {
                $tasks_by_type[$type]['incomplete'] = intval($row['total']);
            }
            $tasks_completed += ($status === 'completed' ? intval($row['total']) : 0);
            $tasks_pending += ($status === 'pending' ? intval($row['total']) : 0);
            $tasks_incomplete += ($status !== 'completed' && $status !== 'pending' ? intval($row['total']) : 0);
        }
        $tasks_by_type_stmt->close();
    }

    // ===== MESSAGES =====
    $messages_stmt = $conn->prepare("
        SELECT 
            id,
            title, 
            message, 
            created_at,
            sender_id,
            is_read,
            message_type
        FROM agent_messages 
        WHERE receiver_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    if ($messages_stmt) {
        $messages_stmt->bind_param('i', $user_id);
        $messages_stmt->execute();
        $messages_result = $messages_stmt->get_result();
        while ($row = $messages_result->fetch_assoc()) {
            $messages[] = $row;
            if (!$row['is_read']) {
                $unread_message_count++;
            }
        }
        $messages_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Walbrand Properties Marketplace</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --primary: #f97316;
            --secondary: #1e293b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; }

        .dashboard-container { display: flex; min-height: 100vh; }

        main { flex: 1; padding: 24px; max-width: 1400px; }

        .dashboard-header { margin-bottom: 32px; }
        .dashboard-header h1 { font-size: 2rem; color: #0f172a; margin-bottom: 8px; }
        .dashboard-header p { color: #64748b; font-size: 1rem; }

        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 24px; margin-bottom: 24px; }

        .panel {
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h2 { font-size: 1.25rem; color: #0f172a; }
        .panel-header span { color: #64748b; font-size: 0.9rem; }

        .panel-body { padding: 24px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
        }

        .stat-item {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .stat-item h3 { font-size: 2rem; color: var(--primary); margin-bottom: 8px; }
        .stat-item p { color: #64748b; font-size: 0.9rem; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
        }

        .data-table tr:hover { background: #f8fafc; }

        .status-tag {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-scheduled { background: #e0e7ff; color: #3730a3; }

        .task-category {
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .task-category h4 { font-size: 0.95rem; color: #0f172a; }

        .task-stats {
            display: flex;
            gap: 12px;
            font-size: 0.9rem;
        }

        .task-stats .completed { color: var(--success); }
        .task-stats .incomplete { color: var(--warning); }

        .chart-container { 
            position: relative; 
            height: 300px;
            margin: 20px 0;
        }

        .message-list { list-style: none; }

        .message-item {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 12px;
            background: #f8fafc;
        }

        .message-item.unread {
            background: #fef3c7;
            border-color: #fcd34d;
        }

        .message-item h4 { color: #0f172a; margin-bottom: 4px; font-size: 0.95rem; }
        .message-item p { color: #475569; font-size: 0.85rem; margin-bottom: 4px; }
        .message-item small { color: #94a3b8; }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #fb923c);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3); }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 24px;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .close {
            color: #64748b;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover { color: #0f172a; }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .delivery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .group-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
        }

        .group-card h3 { color: #0f172a; margin-bottom: 12px; }

        .group-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .group-stat {
            background: white;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.85rem;
        }

        .group-stat-label { color: #64748b; }
        .group-stat-value { font-weight: 700; color: #0f172a; font-size: 1.1rem; }

        .rating { color: #fbbf24; font-size: 1.2rem; }

        @media (max-width: 1024px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <main>
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; border-left: 4px solid #22c55e; color: #166534; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    ✓ <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; border-left: 4px solid #dc2626; color: #991b1b; padding: 16px; border-radius: 8px; margin-bottom: 16px;">
                    ✗ <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="dashboard-header">
                <h1>👋 Welcome, <?= htmlspecialchars($first_name) ?>!</h1>
                <p>Your comprehensive agent dashboard - Track leads, properties, clients, viewings, deliveries, and tasks all in one place.</p>
            </div>

            <!-- LEADS SECTION -->
            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>📞 Leads</h2>
                        <span><?= count($leads_list) ?> Potential Customers</span>
                    </div>
                    <div class="panel-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leads_list)): ?>
                                    <?php foreach (array_slice($leads_list, 0, 10) as $lead): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($lead['name'] ?: 'Unknown') ?></td>
                                            <td>
                                                <div style="font-size:0.9rem;">
                                                    <div><?= htmlspecialchars($lead['email'] ?: 'N/A') ?></div>
                                                    <div style="color:#64748b;"><?= htmlspecialchars($lead['phone'] ?: 'N/A') ?></div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lead['consultation_type'] ?? 'General'))) ?></td>
                                            <td><span class="status-tag status-<?= htmlspecialchars($lead['status']) ?>"><?= htmlspecialchars(ucfirst($lead['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; color:#64748b; padding:18px 0;">No leads available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div style="margin-top: 16px; text-align: center;">
                            <a href="consultations.php" class="btn-primary">View All Leads</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PROPERTIES SECTION -->
            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>🏠 Properties</h2>
                        <span>Total Properties Managed</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= number_format($properties_uploaded) ?></h3>
                                <p>Uploaded</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--success);"><?= number_format($properties_successful) ?></h3>
                                <p>Successful Connections</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--danger);"><?= number_format($properties_rejected) ?></h3>
                                <p>Rejected</p>
                            </div>
                        </div>
                        <div style="margin-top: 16px; text-align: center;">
                            <a href="my_properties.php" class="btn-primary">Manage Properties</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CLIENTS SECTION -->
            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>👥 Clients</h2>
                        <span>Client Statistics & Feedback</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= number_format($clients_connected) ?></h3>
                                <p>Connected</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--success);"><?= number_format($clients_happy) ?></h3>
                                <p>Happy 😊</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--danger);"><?= number_format($clients_complained) ?></h3>
                                <p>Complained 😞</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--warning);"><?= number_format($clients_failed_pick) ?></h3>
                                <p>Failed to Pick</p>
                            </div>
                        </div>
                        <div style="margin-top: 16px; text-align: center;">
                            <a href="consultations.php" class="btn-primary">View Clients</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEWINGS SECTION -->
            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>👀 Viewings</h2>
                        <span>Viewing Statistics</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= number_format($viewings_assigned) ?></h3>
                                <p>Assigned by Admin</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--success);"><?= number_format($viewings_completed) ?></h3>
                                <p>Completed</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--warning);"><?= number_format($viewings_pending) ?></h3>
                                <p>Pending</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:var(--danger);"><?= number_format($viewings_expired) ?></h3>
                                <p>Expired</p>
                            </div>
                        </div>
                        <div style="margin-top: 16px; text-align: center;">
                            <a href="consultations.php?type=viewing" class="btn-primary">Manage Viewings</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DELIVERY GROUPS SECTION -->
            <div style="margin-bottom: 24px;">
                <h2 style="margin-bottom: 16px; color: #0f172a;">🚚 Delivery Groups Performance</h2>
                <div class="dashboard-grid">
                    <div class="panel" style="grid-column: 1 / -1;">
                        <div class="panel-header">
                            <h2>Group Statistics</h2>
                            <span>Completed: <?= $delivery_completed ?> • Incomplete: <?= $delivery_incomplete ?> • Rescheduled: <?= $delivery_rescheduled ?></span>
                        </div>
                        <div class="panel-body">
                            <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 24px;">
                                <div class="stat-item">
                                    <h3 style="color:var(--success);"><?= $delivery_completed ?></h3>
                                    <p>Completed Deliveries</p>
                                </div>
                                <div class="stat-item">
                                    <h3 style="color:var(--warning);"><?= $delivery_incomplete ?></h3>
                                    <p>Incomplete Deliveries</p>
                                </div>
                                <div class="stat-item">
                                    <h3 style="color:#3b82f6;"><?= $delivery_rescheduled ?></h3>
                                    <p>Rescheduled Deliveries</p>
                                </div>
                            </div>

                            <h3 style="margin-bottom: 16px; color: #0f172a; font-size: 1.1rem;">Group Rankings (by Client Feedback)</h3>
                            <div class="delivery-grid">
                                <?php if (!empty($delivery_groups)): ?>
                                    <?php foreach ($delivery_groups as $group): ?>
                                        <div class="group-card">
                                            <h3>#<?= htmlspecialchars($group['group_name']) ?></h3>
                                            <div class="group-stats">
                                                <div class="group-stat">
                                                    <div class="group-stat-label">Total Bookings</div>
                                                    <div class="group-stat-value"><?= $group['total_bookings'] ?></div>
                                                </div>
                                                <div class="group-stat">
                                                    <div class="group-stat-label">Completed</div>
                                                    <div class="group-stat-value" style="color:var(--success);"><?= $group['completed_bookings'] ?></div>
                                                </div>
                                                <div class="group-stat">
                                                    <div class="group-stat-label">Pending</div>
                                                    <div class="group-stat-value" style="color:var(--warning);"><?= $group['pending_bookings'] ?></div>
                                                </div>
                                                <div class="group-stat">
                                                    <div class="group-stat-label">Rescheduled</div>
                                                    <div class="group-stat-value"><?= $group['rescheduled_bookings'] ?></div>
                                                </div>
                                            </div>
                                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
                                                <div class="group-stat-label">Average Rating</div>
                                                <div class="rating" style="margin-top: 4px;">
                                                    <?php
                                                    $rating = $group['avg_rating'] ? round($group['avg_rating'], 1) : 0;
                                                    echo str_repeat('★', (int)$rating) . str_repeat('☆', 5 - (int)$rating);
                                                    echo " (" . $rating . "/5)";
                                                    ?>
                                                </div>
                                                <small style="color: #94a3b8;">Based on <?= $group['feedback_count'] ?> feedbacks</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div style="grid-column: 1 / -1; text-align: center; color: #94a3b8; padding: 24px;">
                                        No delivery groups assigned yet.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TASKS SECTION -->
            <div style="margin-bottom: 24px;">
                <h2 style="margin-bottom: 16px; color: #0f172a;">✅ Tasks by Category</h2>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Task Statistics</h2>
                        <span>Completed: <?= $tasks_completed ?> • Incomplete: <?= $tasks_incomplete ?> • Pending: <?= $tasks_pending ?></span>
                    </div>
                    <div class="panel-body">
                        <div class="chart-container">
                            <canvas id="tasksChart"></canvas>
                        </div>
                        <div style="margin-top: 24px;">
                            <h3 style="margin-bottom: 16px; color: #0f172a; font-size: 1.1rem;">Task Breakdown</h3>
                            <?php if (!empty($tasks_by_type)): ?>
                                <?php foreach ($tasks_by_type as $type => $counts): ?>
                                    <div class="task-category">
                                        <h4><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?></h4>
                                        <div class="task-stats">
                                            <span class="completed">✓ <?= $counts['completed'] ?> Completed</span>
                                            <span class="incomplete">○ <?= $counts['incomplete'] + $counts['pending'] ?> Pending</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="text-align:center; color:#64748b; padding:18px 0;">No tasks available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MESSAGES SECTION -->
            <div style="margin-bottom: 24px;">
                <h2 style="margin-bottom: 16px; color: #0f172a;">💬 Messages <?php if ($unread_message_count > 0) echo "<span style='background: var(--danger); color: white; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; margin-left: 8px;'>$unread_message_count</span>"; ?></h2>
                <div class="panel">
                    <div class="panel-header">
                        <h2>Admin Communications</h2>
                        <button class="btn-primary" onclick="showMessageModal()">📧 Send Message</button>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($messages)): ?>
                            <ul class="message-list">
                                <?php foreach ($messages as $msg): ?>
                                    <li class="message-item <?php echo $msg['is_read'] ? '' : 'unread'; ?>">
                                        <strong><?= htmlspecialchars($msg['title'] ?: 'Message') ?></strong>
                                        <p><?= htmlspecialchars(substr($msg['message'], 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?></p>
                                        <small><?= htmlspecialchars(date('M j, Y H:i', strtotime($msg['created_at']))) ?></small>
                                        <button class="btn-primary" style="margin-top: 8px; padding: 6px 12px; font-size: 0.8rem;" onclick="showReplyModal(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['title'])) ?>')">Reply</button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="text-align:center; color:#64748b; padding:32px 0;">No messages from admin yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MESSAGE MODAL -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h2 style="margin-bottom: 20px; color: #0f172a;">Send Message to Admin</h2>
            <form action="send_message_to_admin.php" method="POST">
                <div class="form-group">
                    <label for="msg-title">Subject</label>
                    <input type="text" id="msg-title" name="title" placeholder="Message subject" required>
                </div>
                <div class="form-group">
                    <label for="msg-body">Message</label>
                    <textarea id="msg-body" name="message" placeholder="Write your message here..." required></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn-primary" style="background: #94a3b8;" onclick="closeMessageModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <!-- REPLY MODAL -->
    <div id="replyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeReplyModal()">&times;</span>
            <h2 style="margin-bottom: 20px; color: #0f172a;">Reply to Message</h2>
            <form action="send_message_to_admin.php" method="POST">
                <input type="hidden" id="parent-msg-id" name="parent_message_id" value="">
                <div class="form-group">
                    <label>Replying to:</label>
                    <p id="reply-to-subject" style="color: #64748b; margin-top: 4px;"></p>
                </div>
                <div class="form-group">
                    <label for="reply-body">Your Reply</label>
                    <textarea id="reply-body" name="message" placeholder="Type your reply..." required></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" class="btn-primary" style="background: #94a3b8;" onclick="closeReplyModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Send Reply</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Chart.js - Tasks by Type
        window.addEventListener('load', function() {
            const tasksByType = <?= json_encode($tasks_by_type) ?>;
            
            if (tasksByType && Object.keys(tasksByType).length > 0) {
                const labels = Object.keys(tasksByType);
                const completedData = Object.values(tasksByType).map(t => t.completed);
                const incompleteData = Object.values(tasksByType).map(t => t.incomplete + t.pending);

                const ctx = document.getElementById('tasksChart');
                if (ctx) {
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Completed',
                                    data: completedData,
                                    backgroundColor: '#22c55e'
                                },
                                {
                                    label: 'Pending/Incomplete',
                                    data: incompleteData,
                                    backgroundColor: '#f59e0b'
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { beginAtZero: true }
                            },
                            plugins: {
                                legend: { position: 'top' }
                            }
                        }
                    });
                }
            }
        });

        function showMessageModal() {
            document.getElementById('messageModal').style.display = 'block';
        }

        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        function showReplyModal(msgId, subject) {
            document.getElementById('parent-msg-id').value = msgId;
            document.getElementById('reply-to-subject').textContent = subject;
            document.getElementById('replyModal').style.display = 'block';
        }

        function closeReplyModal() {
            document.getElementById('replyModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('messageModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
            modal = document.getElementById('replyModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
