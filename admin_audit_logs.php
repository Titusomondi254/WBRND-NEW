<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    $log_id = intval($_POST['id'] ?? 0);
    if ($log_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid log ID']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM admin_logs WHERE id = ?");
    $stmt->bind_param("i", $log_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Log entry deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete log entry']);
    }
    exit;
}

// Get filter parameters
$filter_date = $_GET['date'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_admin = $_GET['admin'] ?? '';
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$where = "WHERE 1=1";
$params = [];
$types = "";

if(!empty($filter_date)) {
    $where .= " AND DATE(created_at) = ?";
    $params[] = $filter_date;
    $types .= "s";
}

if(!empty($filter_action)) {
    $where .= " AND action = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if(!empty($filter_admin)) {
    $where .= " AND admin_id = ?";
    $params[] = intval($filter_admin);
    $types .= "i";
}

// Get total count
$count_query = $conn->query("SELECT COUNT(*) as total FROM admin_logs $where");
$total = $count_query->fetch_assoc()['total'];
$total_pages = ceil($total / $per_page);

// Get logs
$query = "
    SELECT al.*, CONCAT_WS(' ', a.first_name, a.last_name) as admin_name, a.email as admin_email
    FROM admin_logs al
    LEFT JOIN users a ON al.admin_id = a.id
    $where
    ORDER BY al.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result();

// Get unique actions for filter
$actions_query = $conn->query("SELECT DISTINCT action FROM admin_logs ORDER BY action");

// Get unique admins for filter
$admins_query = $conn->query("SELECT DISTINCT a.id, CONCAT_WS(' ', a.first_name, a.last_name) as name FROM admin_logs al LEFT JOIN users a ON al.admin_id = a.id WHERE a.id IS NOT NULL");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color:#eef4fb;
            --secondary-color: #5cfaff;
            --dark-color: #1a1a1a;
            --light-gray: #f8f9fa;
            --border-color: #e0e0e0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: var(--light-gray);
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            margin: 0;
        }

        .back-btn {
            background: var(--border-color);
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--dark-color);
        }

        .filter-group input,
        .filter-group select {
            padding: 0.7rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            min-width: 200px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: flex-end;
        }

        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-color);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .export-btn {
            padding: 0.7rem 1.5rem;
            background: var(--success);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem;
            background: var(--light-gray);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--light-gray);
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background: var(--light-gray);
        }

        .action-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .action-verify {
            background: #d1f4e9;
            color: #065f46;
        }

        .action-reject {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .action-suspend {
            background: #fef3c7;
            color: #92400e;
        }

        .action-update {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .action-login {
            background: #d1f4e9;
            color: #065f46;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .admin-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.75rem;
        }

        .admin-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .admin-email {
            font-size: 0.85rem;
            color: #666;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.6rem 0.9rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
        }

        .pagination a:hover {
            background: var(--light-gray);
        }

        .pagination span.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination span.disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .timestamp {
            white-space: nowrap;
            font-size: 0.9rem;
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            color: #666;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .filter-bar {
                flex-direction: column;
            }

            .filter-group input,
            .filter-group select {
                min-width: 100%;
            }

            .table-container {
                overflow-x: auto;
            }

            table {
                min-width: 800px;
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
    <div class="container">
        <div class="page-header">
            <h1>Audit Logs</h1>
            <a href="admin_control_panel.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; width: 100%;">
                <div class="filter-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
                </div>

                <div class="filter-group">
                    <label for="action">Action</label>
                    <select id="action" name="action">
                        <option value="">All Actions</option>
                        <?php while($action = $actions_query->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($action['action']) ?>" <?= $filter_action === $action['action'] ? 'selected' : '' ?>>
                                <?= ucfirst(str_replace('_', ' ', $action['action'])) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="admin">Admin</label>
                    <select id="admin" name="admin">
                        <option value="">All Admins</option>
                        <?php while($admin = $admins_query->fetch_assoc()): ?>
                            <option value="<?= $admin['id'] ?>" <?= $filter_admin == $admin['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($admin['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    <a href="admin_audit_logs.php" class="btn btn-secondary">↺ Reset</a>
                </div>
            </form>
        </div>

        <!-- LOGS TABLE -->
        <div class="table-container">
            <div class="table-header">
                <h2>System Activity Logs</h2>
                <div style="font-size: 0.9rem; color: #666;">
                    Showing <?= min($offset + 1, $total) ?> - <?= min($offset + $per_page, $total) ?> of <?= $total ?> records
                </div>
            </div>

            <?php if($logs->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Admin</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>IP Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td class="timestamp"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td>
                            <div class="admin-info">
                                <div class="admin-avatar">
                                    <?= strtoupper(substr($log['admin_name'] ?? 'System', 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="admin-name"><?= htmlspecialchars($log['admin_name'] ?? 'System') ?></div>
                                    <div class="admin-email"><?= htmlspecialchars($log['admin_email'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="action-badge action-<?= strtolower(str_replace(' ', '_', $log['action'])) ?>">
                                <?= ucfirst(str_replace('_', ' ', $log['action'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars(substr($log['details'], 0, 50)) ?><?= strlen($log['details']) > 50 ? '...' : '' ?></td>
                        <td class="ip-address"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td>
                            <button class="btn-small btn-delete" onclick="deleteLog(<?= $log['id'] ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>No logs found matching your criteria</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if($total_pages > 1): ?>
        <div class="pagination">
            <?php if($page > 1): ?>
                <a href="?page=1<?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?><?= !empty($filter_action) ? '&action=' . urlencode($filter_action) : '' ?><?= !empty($filter_admin) ? '&admin=' . urlencode($filter_admin) : '' ?>">« First</a>
                <a href="?page=<?= $page - 1 ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?><?= !empty($filter_action) ? '&action=' . urlencode($filter_action) : '' ?><?= !empty($filter_admin) ? '&admin=' . urlencode($filter_admin) : '' ?>">‹ Previous</a>
            <?php else: ?>
                <span class="disabled">« First</span>
                <span class="disabled">‹ Previous</span>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            for($i = $start; $i <= $end; $i++):
            ?>
                <?php if($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?><?= !empty($filter_action) ? '&action=' . urlencode($filter_action) : '' ?><?= !empty($filter_admin) ? '&admin=' . urlencode($filter_admin) : '' ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?><?= !empty($filter_action) ? '&action=' . urlencode($filter_action) : '' ?><?= !empty($filter_admin) ? '&admin=' . urlencode($filter_admin) : '' ?>">Next ›</a>
                <a href="?page=<?= $total_pages ?><?= !empty($filter_date) ? '&date=' . urlencode($filter_date) : '' ?><?= !empty($filter_action) ? '&action=' . urlencode($filter_action) : '' ?><?= !empty($filter_admin) ? '&admin=' . urlencode($filter_admin) : '' ?>">Last »</a>
            <?php else: ?>
                <span class="disabled">Next ›</span>
                <span class="disabled">Last »</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        async function deleteLog(logId) {
            const confirmed = await showConfirm('Are you sure you want to delete this log entry? This action cannot be undone.');
            if (!confirmed) {
                return;
            }

            fetch('admin_audit_logs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&id=${logId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Log entry deleted successfully');
                    location.reload();
                } else {
                    showError('Failed to delete log entry: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showError('An error occurred while deleting the log entry');
            });
        }
    </script>
</body>
</html>
