<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Get blocked users
$query = "SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.user_type, u.status, u.created_at,
                 (SELECT created_at FROM audit_logs WHERE user_id = u.id AND action = 'account_blocked' ORDER BY created_at DESC LIMIT 1) AS blocked_at,
                 (SELECT ip_address FROM audit_logs WHERE user_id = u.id AND action = 'account_blocked' ORDER BY created_at DESC LIMIT 1) AS ip_address
          FROM users u
          WHERE u.is_active = 0 AND u.status = 'blocked'
          ORDER BY blocked_at DESC";

$result = $conn->query($query);
$blocked_users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $blocked_users[] = $row;
    }
}

// Handle blocked user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blocked_action'], $_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    $blocked_action = $_POST['blocked_action'];

    if ($blocked_action === 'reactivate_user') {
        $update_query = "UPDATE users SET is_active = 1, status = 'active' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $log_query = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                          VALUES (?, 'account_reactivated', 'users', ?, '{\"is_active\": 0, \"status\": \"blocked\"}', '{\"is_active\": 1, \"status\": \"active\"}', ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("iisss", $user_id, $user_id, $ip, $agent);
            $log_stmt->execute();

            $success = "User account has been successfully reactivated.";
        } else {
            $error = "Failed to reactivate user account.";
        }
    } elseif ($blocked_action === 'remove_agent') {
        $update_query = "UPDATE users SET user_type = 'buyer' WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("i", $user_id);

        if ($stmt->execute()) {
            $log_query = "INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent, created_at)
                          VALUES (?, 'agent_removed', 'users', ?, '{\"user_type\": \"agent\"}', '{\"user_type\": \"buyer\"}', ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_query);
            $ip = $_SERVER['REMOTE_ADDR'];
            $agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("iisss", $user_id, $user_id, $ip, $agent);
            $log_stmt->execute();

            $success = "Agent privileges removed from the blocked account.";
        } else {
            $error = "Failed to remove agent privileges.";
        }
    } elseif ($blocked_action === 'delete_user') {
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $user_id);

        if ($delete_stmt->execute()) {
            $success = "Blocked account has been deleted successfully.";
        } else {
            $error = "Failed to delete blocked user account.";
        }
    }

    // Refresh blocked users list after action
    $result = $conn->query($query);
    $blocked_users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $blocked_users[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Users Management - Admin Panel</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        .blocked-users-container {
            padding: 20px;
        }

        .header-section {
            background: linear-gradient(135deg, #ff7b00, #5cfaff);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color:#eef4fb;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
        }

        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .users-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .users-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .users-table tr:hover {
            background: #f8f9fa;
        }

        .status-blocked {
            background: #fee;
            color: #c33;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .btn-action {
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            margin: 0 0 6px 0;
            display: inline-block;
            min-width: 100px;
        }

        .btn-view {
            background: #3b82f6;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .btn-reactivate {
            background: #10b981;
        }

        .btn-reactivate:hover {
            background: #059669;
        }

        .btn-remove-agent {
            background: #f59e0b;
        }

        .btn-remove-agent:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #ef4444;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.15);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 1.4rem;
            cursor: pointer;
            color: #333;
        }

        .modal-body p {
            margin: 0.5rem 0;
            line-height: 1.6;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
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

        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Super Admin</h2>
                <p><?php echo htmlspecialchars($_SESSION['admin_name']); ?></p>
            </div>

            <nav class="sidebar-nav">
                <a href="admin_control_panel.php">Dashboard</a>
                <a href="admin_users.php">Users</a>
                <a href="admin_properties.php">Properties</a>
                <a href="admin_verify_properties.php">Verify Properties</a>
                <a href="admin_viewing_requests.php">Viewing Requests</a>
                <a href="admin_fee_management.php">Fee Management</a>
                <a href="admin_audit_logs.php">Audit Logs</a>
                <a href="admin_settings.php">Settings</a>
                <a href="admin_blocked_users.php" class="active">Blocked Users</a>
                <a href="admin_logout.php">Logout</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="blocked-users-container">
                <div class="header-section">
                    <h1>Blocked Users Management</h1>
                    <p>Manage users who have been blocked due to security violations</p>
                </div>

                <div class="security-notice">
                    <strong>Security Notice:</strong> These users attempted unauthorized access to admin areas and have been permanently blocked. Only reactivate accounts after thorough verification.
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        ✅ <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        ❌ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($blocked_users); ?></div>
                        <div class="stat-label">Blocked Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php
                            $recent_blocks = array_filter($blocked_users, function($user) {
                                return strtotime($user['blocked_at']) > strtotime('-7 days');
                            });
                            echo count($recent_blocks);
                            ?>
                        </div>
                        <div class="stat-label">Blocked This Week</div>
                    </div>
                </div>

                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Blocked Date</th>
                                <th>Reason</th>
                                <th>IP Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blocked_users)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                                        No blocked users found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blocked_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td><?php echo $user['blocked_at'] ? date('M d, Y H:i', strtotime($user['blocked_at'])) : 'Unknown'; ?></td>
                                        <td><?php echo htmlspecialchars($user['block_action'] ?? 'Security violation'); ?></td>
                                        <td><?php echo htmlspecialchars($user['ip_address'] ?? 'Unknown'); ?></td>
                                        <td>
                                            <button type="button" class="btn-action btn-view" onclick="viewDetails(<?php echo $user['id']; ?>)">View Details</button><br>
                                            <button type="button" class="btn-action btn-reactivate" onclick="submitBlockedUserAction(<?php echo $user['id']; ?>, 'reactivate_user')">Reactivate</button><br>
                                            <?php if ($user['user_type'] === 'agent'): ?>
                                                <button type="button" class="btn-action btn-remove-agent" onclick="submitBlockedUserAction(<?php echo $user['id']; ?>, 'remove_agent')">Remove as Agent</button><br>
                                            <?php endif; ?>
                                            <button type="button" class="btn-action btn-delete" onclick="submitBlockedUserAction(<?php echo $user['id']; ?>, 'delete_user')">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="userDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Blocked User Details</h2>
                <button type="button" class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <p>Loading details...</p>
            </div>
        </div>
    </div>

    <script>
        const blockedUsersData = <?php echo json_encode($blocked_users, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function getUserById(userId) {
            return blockedUsersData.find(user => Number(user.id) === Number(userId));
        }

        function viewDetails(userId) {
            const user = getUserById(userId);
            if (!user) {
                alert('Unable to load user details.');
                return;
            }

            const createdAt = user.created_at ? new Date(user.created_at).toLocaleString() : 'Unknown';
            const blockedAt = user.blocked_at ? new Date(user.blocked_at).toLocaleString() : 'Unknown';
            const userType = user.user_type ? user.user_type : 'Unknown';
            const status = user.status ? user.status : 'Unknown';

            document.getElementById('userDetailsContent').innerHTML = `
                <p><strong>User ID:</strong> ${user.id}</p>
                <p><strong>Name:</strong> ${user.first_name} ${user.last_name}</p>
                <p><strong>Email:</strong> ${user.email}</p>
                <p><strong>Phone:</strong> ${user.phone || 'Not provided'}</p>
                <p><strong>Type:</strong> ${userType}</p>
                <p><strong>Status:</strong> ${status}</p>
                <p><strong>Account Created:</strong> ${createdAt}</p>
                <p><strong>Blocked At:</strong> ${blockedAt}</p>
                <p><strong>IP Address:</strong> ${user.ip_address || 'Unknown'}</p>
                <p><strong>Reason:</strong> ${user.block_action || 'Unauthorized admin access attempt'}</p>
            `;

            document.getElementById('userDetailsModal').classList.add('active');
        }

        function closeDetailsModal() {
            document.getElementById('userDetailsModal').classList.remove('active');
        }

        async function submitBlockedUserAction(userId, action) {
            let confirmMessage = '';
            if (action === 'reactivate_user') {
                confirmMessage = 'Are you sure you want to reactivate this user account?';
            } else if (action === 'remove_agent') {
                confirmMessage = 'Remove agent privileges from this blocked account?';
            } else if (action === 'delete_user') {
                confirmMessage = 'Delete this blocked user account permanently? This action cannot be undone.';
            }

            const confirmed = confirm(confirmMessage);
            if (!confirmed) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const userIdInput = document.createElement('input');
            userIdInput.type = 'hidden';
            userIdInput.name = 'user_id';
            userIdInput.value = userId;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'blocked_action';
            actionInput.value = action;

            form.appendChild(userIdInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>