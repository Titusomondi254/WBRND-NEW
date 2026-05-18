<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle AJAX GET request for details - MUST be before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_details') {
    $investment_id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare(
        "SELECT i.*, p.project_name, p.location, p.price_per_unit,
                CONCAT(u.first_name, ' ', u.last_name) AS investor_name, 
                u.email AS investor_email, u.phone AS investor_phone
         FROM offplan_investments i
         LEFT JOIN offplan_projects p ON i.project_id = p.id
         LEFT JOIN users u ON i.investor_id = u.id
         WHERE i.id = ? LIMIT 1"
    );
    
    if ($stmt) {
        $stmt->bind_param('i', $investment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $investment = $result->fetch_assoc();
        $stmt->close();
        
        if ($investment) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'investment' => $investment]);
            exit;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Investment not found']);
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $investment_id = intval($_POST['investment_id'] ?? 0);

    if ($action === 'update_status' && $investment_id > 0) {
        $new_status = $_POST['status'] ?? '';
        $valid_statuses = ['pending_payment', 'payment_received', 'confirmed', 'active', 'completed', 'cancelled', 'disputed'];
        
        if (in_array($new_status, $valid_statuses)) {
            $stmt = $conn->prepare("UPDATE offplan_investments SET status = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('si', $new_status, $investment_id);
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
                }
                $stmt->close();
            }
        }
        exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = 'i.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR p.project_name LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get investments (commit interest requests)
$query = "SELECT i.id, i.project_id, i.investor_id, i.units_committed, i.amount_committed, 
                 i.status, i.created_at, i.updated_at,
                 CONCAT_WS(' ', u.first_name, u.last_name) as investor_name,
                 u.email as investor_email, u.phone as investor_phone,
                 p.project_name, p.location, p.price_per_unit
          FROM offplan_investments i
          JOIN users u ON i.investor_id = u.id
          JOIN offplan_projects p ON i.project_id = p.id
          $where_clause
          ORDER BY i.created_at DESC";

$stmt = $conn->prepare($query);
$investments = [];

if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $investments[] = $row;
    }
    $stmt->close();
}

// Get stats
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending_payment' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'payment_received' THEN 1 ELSE 0 END) as payment_received,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(amount_committed) as total_amount
                FROM offplan_investments";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

function format_currency($value) {
    return 'KES ' . number_format((float) $value, 2);
}

function get_status_badge($status) {
    $badges = [
        'pending_payment' => ['color' => '#fbbf24', 'bg' => '#fef3c7', 'text' => 'Pending Payment'],
        'payment_received' => ['color' => '#3b82f6', 'bg' => '#dbeafe', 'text' => 'Payment Received'],
        'confirmed' => ['color' => '#8b5cf6', 'bg' => '#ede9fe', 'text' => 'Confirmed'],
        'active' => ['color' => '#10b981', 'bg' => '#d1fae5', 'text' => 'Active'],
        'completed' => ['color' => '#059669', 'bg' => '#ecfdf5', 'text' => 'Completed'],
        'cancelled' => ['color' => '#ef4444', 'bg' => '#fee2e2', 'text' => 'Cancelled'],
        'disputed' => ['color' => '#f97316', 'bg' => '#ffedd5', 'text' => 'Disputed'],
    ];
    
    if (isset($badges[$status])) {
        return $badges[$status];
    }
    return ['color' => '#666', 'bg' => '#f3f4f6', 'text' => ucfirst(str_replace('_', ' ', $status))];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commit Interest Requests - Walbrand Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f97316;
            --text-color: #ea580c;
            --light-bg: #fff7ed;
            --light-card: #fed7aa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--text-color);
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--text-color);
            color: white;
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid rgba(249,115,22,0.3);
        }

        .sidebar-header {
            padding: 0 24px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
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
            color: var(--text-color);
        }

        .sidebar-header h3 {
            font-size: 1.05rem;
            margin-bottom: 4px;
            color: #fff;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            color: rgba(255,255,255,0.88);
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
            color: rgba(255,255,255,0.95);
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
            font-weight: 600;
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
            padding: 28px 30px;
            background: var(--light-bg);
        }

        .header {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 28px;
            box-shadow: 0 10px 30px rgba(249,115,22,0.2);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--light-card);
            border: 1px solid #f97316;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(249,115,22,0.1);
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-color);
        }

        .stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--text-color);
            margin-top: 4px;
            opacity: 0.8;
        }

        .stat-card .stat-icon {
            font-size: 1.5rem;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .controls {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 40px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--text-color);
        }

        .search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .filter-select {
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            color: var(--text-color);
            cursor: pointer;
            transition: border-color 0.2s;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--text-color);
        }

        .btn-primary {
            background: var(--text-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            background: #d64a08;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f3f4f6;
            border-bottom: 2px solid #e5e7eb;
        }

        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.95rem;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #e5e7eb;
            color: #555;
        }

        tbody tr:hover {
            background: #f9fafb;
        }

        .investor-info {
            font-weight: 600;
            color: var(--text-color);
        }

        .project-name {
            color: var(--text-color);
            font-weight: 500;
        }

        .amount {
            font-weight: 600;
            color: #059669;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-view {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .btn-view:hover {
            background: #bfdbfe;
        }

        .btn-edit {
            background: #ddd6fe;
            color: #6d28d9;
        }

        .btn-edit:hover {
            background: #c4b5fd;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .detail-group {
            margin-bottom: 16px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: #555;
            font-size: 1rem;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #374151;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .btn-save {
            background: var(--text-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-save:hover {
            background: #d64a08;
        }

        .toast-message {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #059669;
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            animation: slideUp 0.3s ease;
            z-index: 2000;
        }

        @keyframes slideUp {
            from { transform: translateY(100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: black;
        }

        select.filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }

            .main-content {
                margin-left: 200px;
                padding: 16px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .controls {
                flex-direction: column;
            }

            .search-box {
                min-width: 100%;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 12px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">
                        <i class="fas fa-cubes"></i>
                    </div>
                    <div>
                        <h3>Walbrand</h3>
                        <p>Admin Panel</p>
                    </div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="admin_control_panel.php">
                    <i class="fas fa-tachometer-alt menu-icon"></i>Dashboard</a></li>
                <li><a href="admin_users.php">
                    <i class="fas fa-users menu-icon"></i>Manage Users</a></li>
                <li><a href="admin_properties.php">
                    <i class="fas fa-building menu-icon"></i>Properties</a></li>
                <li><a href="admin_investments.php">
                    <i class="fas fa-project-diagram menu-icon"></i>Projects</a></li>
                <li><a href="admin_commit_interest.php" class="active">
                    <i class="fas fa-handshake menu-icon"></i>Commit Interests</a></li>
                <li><a href="admin_control_panel.php?view=payments">
                    <i class="fas fa-credit-card menu-icon"></i>Payments</a></li>
                <li><a href="admin_viewing_requests.php">
                    <i class="fas fa-calendar-check menu-icon"></i>Viewing Requests</a></li>
                <li><a href="admin_mpesa_verifications.php">
                    <i class="fas fa-receipt menu-icon"></i>M-Pesa Verifications</a></li>
                <li><a href="admin_audit_logs.php">
                    <i class="fas fa-clipboard-list menu-icon"></i>Audit Logs</a></li>
                <li><a href="admin_settings.php">
                    <i class="fas fa-cog menu-icon"></i>Settings</a></li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- HEADER -->
            <div class="header">
                <h1>
                    <i class="fas fa-handshake"></i>
                    Commit Interest Requests
                </h1>
                <p>Manage and track all investment commitment requests from clients</p>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list"></i></div>
                    <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                    <div class="stat-label">Pending Payment</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value"><?= $stats['confirmed'] ?? 0 ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value"><?= format_currency($stats['total_amount'] ?? 0) ?></div>
                    <div class="stat-label">Total Committed</div>
                </div>
            </div>

            <!-- CONTROLS -->
            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by investor name, email, or project..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending_payment" <?= $status_filter === 'pending_payment' ? 'selected' : '' ?>>Pending Payment</option>
                    <option value="payment_received" <?= $status_filter === 'payment_received' ? 'selected' : '' ?>>Payment Received</option>
                    <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="disputed" <?= $status_filter === 'disputed' ? 'selected' : '' ?>>Disputed</option>
                </select>
                <button class="btn-primary" onclick="location.href='admin_commit_interest.php'">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>

            <!-- TABLE -->
            <div class="table-container">
                <?php if (empty($investments)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No commit interest requests found</h3>
                        <p>There are currently no investment commitment requests to display.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Investor</th>
                                <th>Project</th>
                                <th>Location</th>
                                <th>Units</th>
                                <th>Amount Committed</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($investments as $investment): ?>
                                <?php $status_info = get_status_badge($investment['status']); ?>
                                <tr>
                                    <td>
                                        <div class="investor-info"><?= htmlspecialchars($investment['investor_name']) ?></div>
                                        <small style="color: #999;"><?= htmlspecialchars($investment['investor_email']) ?></small>
                                    </td>
                                    <td>
                                        <div class="project-name"><?= htmlspecialchars($investment['project_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($investment['location']) ?></td>
                                    <td><?= $investment['units_committed'] ?></td>
                                    <td class="amount"><?= format_currency($investment['amount_committed']) ?></td>
                                    <td>
                                        <span class="status-badge" style="background-color: <?= $status_info['bg'] ?>; color: <?= $status_info['color'] ?>;">
                                            <?= $status_info['text'] ?>
                                        </span>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($investment['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-sm btn-view" onclick="viewDetails(<?= $investment['id'] ?>)">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                            <button class="btn-sm btn-edit" onclick="editStatus(<?= $investment['id'] ?>, '<?= $investment['status'] ?>')">
                                                <i class="fas fa-edit"></i> Status
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- VIEW DETAILS MODAL -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('detailsModal')">&times;</span>
            <div class="modal-header">
                <i class="fas fa-info-circle"></i>
                Commitment Details
            </div>
            <div class="modal-body" id="detailsContent">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('detailsModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- EDIT STATUS MODAL -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('statusModal')">&times;</span>
            <div class="modal-header">
                <i class="fas fa-edit"></i>
                Update Commitment Status
            </div>
            <div class="modal-body">
                <div class="detail-group">
                    <label class="detail-label">New Status:</label>
                    <select id="newStatus" class="filter-select" style="width: 100%;">
                        <option value="pending_payment">Pending Payment</option>
                        <option value="payment_received">Payment Received</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="disputed">Disputed</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal('statusModal')">Cancel</button>
                <button class="btn-save" onclick="saveStatus()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        let currentInvestmentId = null;

        function viewDetails(investmentId) {
            currentInvestmentId = investmentId;
            fetch('admin_commit_interest.php?action=get_details&id=' + investmentId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const inv = data.investment;
                        const statusInfo = getStatusColor(inv.status);
                        const html = `
                            <div class="detail-group">
                                <div class="detail-label">Investor Name:</div>
                                <div class="detail-value">${escapeHtml(inv.investor_name)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Email:</div>
                                <div class="detail-value">${escapeHtml(inv.investor_email)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Phone:</div>
                                <div class="detail-value">${escapeHtml(inv.investor_phone)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Project:</div>
                                <div class="detail-value">${escapeHtml(inv.project_name)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Location:</div>
                                <div class="detail-value">${escapeHtml(inv.location)}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Units Committed:</div>
                                <div class="detail-value">${inv.units_committed}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Amount Committed:</div>
                                <div class="detail-value">KES ${parseFloat(inv.amount_committed).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Price per Unit:</div>
                                <div class="detail-value">KES ${parseFloat(inv.price_per_unit).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Status:</div>
                                <div class="detail-value"><span class="status-badge" style="background-color: ${statusInfo.bg}; color: ${statusInfo.color};">${statusInfo.text}</span></div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Submitted:</div>
                                <div class="detail-value">${new Date(inv.created_at).toLocaleDateString('en-KE', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</div>
                            </div>
                            <div class="detail-group">
                                <div class="detail-label">Last Updated:</div>
                                <div class="detail-value">${new Date(inv.updated_at).toLocaleDateString('en-KE', {year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</div>
                            </div>
                        `;
                        document.getElementById('detailsContent').innerHTML = html;
                        document.getElementById('detailsModal').style.display = 'block';
                    }
                })
                .catch(error => {
                    alert('Error loading details: ' + error);
                });
        }

        function editStatus(investmentId, currentStatus) {
            currentInvestmentId = investmentId;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function saveStatus() {
            const newStatus = document.getElementById('newStatus').value;
            
            fetch('admin_commit_interest.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=update_status&investment_id=' + currentInvestmentId + '&status=' + encodeURIComponent(newStatus)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Status updated successfully');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error updating status: ' + error);
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'toast-message';
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getStatusColor(status) {
            const colors = {
                'pending_payment': {bg: '#fef3c7', color: '#fbbf24', text: 'Pending Payment'},
                'payment_received': {bg: '#dbeafe', color: '#3b82f6', text: 'Payment Received'},
                'confirmed': {bg: '#ede9fe', color: '#8b5cf6', text: 'Confirmed'},
                'active': {bg: '#d1fae5', color: '#10b981', text: 'Active'},
                'completed': {bg: '#ecfdf5', color: '#059669', text: 'Completed'},
                'cancelled': {bg: '#fee2e2', color: '#ef4444', text: 'Cancelled'},
                'disputed': {bg: '#ffedd5', color: '#f97316', text: 'Disputed'},
            };
            return colors[status] || {bg: '#f3f4f6', color: '#666', text: status};
        }

        // Search and filter functionality
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });

        document.getElementById('statusFilter').addEventListener('change', function() {
            applyFilters();
        });

        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            let url = 'admin_commit_interest.php';
            const params = [];
            if (search) params.push('search=' + encodeURIComponent(search));
            if (status) params.push('status=' + encodeURIComponent(status));
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            location.href = url;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const detailsModal = document.getElementById('detailsModal');
            const statusModal = document.getElementById('statusModal');
            if (event.target == detailsModal) {
                detailsModal.style.display = 'none';
            }
            if (event.target == statusModal) {
                statusModal.style.display = 'none';
            }
        }

        // Handle GET requests for details
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'get_details') {
            // This is handled by JavaScript fetch above
        }
    </script>
</body>
</html>
