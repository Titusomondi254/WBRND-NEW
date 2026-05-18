<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

function execute_safe_stmt($stmt, &$error = null) {
    try {
        return $stmt->execute();
    } catch (Exception $e) {
        error_log('Read-only DB write skipped: ' . $e->getMessage());
        $error = 'Database is currently read-only; no changes were saved.';
        return false;
    }
}

// Handle admin password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_admin_password'])) {
    $current_password = $_POST['current_admin_password'];
    $new_password = $_POST['new_admin_password'];
    $confirm_password = $_POST['confirm_admin_password'];
    
    $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
    
    // Get current admin password
    $admin_stmt = $conn->prepare("SELECT password FROM admin_users WHERE user_id = ?");
    $admin_stmt->bind_param("i", $admin_id);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    $admin_data = $admin_result->fetch_assoc();
    $admin_stmt->close();
    
    $errors = [];
    
    // Validate current password
    if (!$admin_data || !password_verify($current_password, $admin_data['password'])) {
        $errors[] = "Current password is incorrect.";
    }
    
    // Validate new password
    if (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match.";
    }
    
    if ($new_password === $current_password) {
        $errors[] = "New password must be different from current password.";
    }
    
    if (empty($errors)) {
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $new_password_hash, $admin_id);
        
        if ($update_stmt->execute()) {
            logAdminAction('password_change', "Admin password changed", $admin_id);
            $success = "Admin password changed successfully!";
        } else {
            $error = "Failed to change admin password. Please try again.";
        }
        $update_stmt->close();
    } else {
        $error = implode("<br>", $errors);
    }
}

// Handle actions
$action = $_GET['action'] ?? '';
$user_id = $_GET['id'] ?? 0;

// Get user details if in edit mode
$user_data = null;
if($action === 'edit' && $user_id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    switch($form_action) {
        case 'verify_kyc':
            $user_id = intval($_POST['user_id']);
            $verification_status = 'verified';
            
            $stmt = $conn->prepare("UPDATE users SET kyc_verified = TRUE, kyc_status = ? WHERE id = ?");
            $stmt->bind_param("si", $verification_status, $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('verify_kyc', "Verified KYC for user ID: $user_id", $user_id);
                $success = "KYC verification completed successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to verify KYC";
                }
            }
            $stmt->close();
            break;
            
        case 'reject_kyc':
            $user_id = intval($_POST['user_id']);
            $reason = trim($_POST['rejection_reason'] ?? '');
            
            $stmt = $conn->prepare("UPDATE users SET status = 'kyc_rejected', kyc_verified = FALSE WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('reject_kyc', "Rejected KYC - Reason: $reason", $user_id);
                $success = "KYC rejected successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to reject KYC";
                }
            }
            $stmt->close();
            break;
            
        case 'suspend_account':
            $user_id = intval($_POST['user_id']);
            $reason = trim($_POST['suspension_reason'] ?? '');
            
            $stmt = $conn->prepare("UPDATE users SET status = 'suspended' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('suspend_account', "Suspended account - Reason: $reason", $user_id);
                $success = "Account suspended successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to suspend account";
                }
            }
            $stmt->close();
            break;
            
        case 'activate_account':
            $user_id = intval($_POST['user_id']);
            
            $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('activate_account', "Activated account", $user_id);
                $success = "Account activated successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to activate account";
                }
            }
            $stmt->close();
            break;
            
        case 'assign_role':
            $user_id = intval($_POST['user_id']);
            $new_role = $_POST['new_role'] ?? '';
            
            if(!in_array($new_role, ['buyer', 'seller', 'agent'])) {
                $error = "Invalid role specified";
            } else {
                $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
                $stmt->bind_param("si", $new_role, $user_id);
                
                if (execute_safe_stmt($stmt, $error)) {
                    logAdminAction('assign_role', "Assigned role: $new_role", $user_id);
                    $success = "Role assigned successfully!";
                } else {
                    if (!$error) {
                        $error = "Failed to assign role";
                    }
                }
                $stmt->close();
            }
            break;
            
        case 'make_agent':
            $user_id = intval($_POST['user_id']);
            
            $stmt = $conn->prepare("UPDATE users SET user_type = 'agent' WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('make_agent', "Promoted user to agent", $user_id);
                $success = "User promoted to agent successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to promote user to agent";
                }
            }
            $stmt->close();
            break;
            
        case 'remove_agent':
            $user_id = intval($_POST['user_id']);
            $new_role = $_POST['new_role'] ?? 'buyer';
            
            $stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
            $stmt->bind_param("si", $new_role, $user_id);
            
            if (execute_safe_stmt($stmt, $error)) {
                logAdminAction('remove_agent', "Removed user from agent role, assigned: $new_role", $user_id);
                $success = "User removed from agent role successfully!";
            } else {
                if (!$error) {
                    $error = "Failed to remove user from agent role";
                }
            }
            $stmt->close();
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <style>
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
            background: var(--light-gray);
        }

        .admin-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h1 {
            margin: 0;
            color: var(--dark-color);
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

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: #d1f4e9;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 4px solid var(--danger);
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .user-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border-top: 4px solid var(--primary-color);
        }

        .user-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .user-avatar-img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 123, 0, 0.25);
        }

        .user-info h3 {
            margin: 0;
            color: var(--dark-color);
        }

        .user-info p {
            margin: 0.3rem 0 0;
            color: #999;
            font-size: 0.9rem;
        }

        .user-details {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
            margin-bottom: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
        }

        .detail-value {
            color: var(--dark-color);
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #d1f4e9;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-suspended {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.6rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.6rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 0.7rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .user-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
            }
        }

        .activities-section {
            margin-top: 3rem;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
        }

        .activities-section h2 {
            margin: 0 0 1.5rem 0;
            color: var(--dark-color);
        }

        .activities-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activities-table thead {
            background: var(--light-gray);
        }

        .activities-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        .activities-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: #666;
        }

        .activities-table tr:hover {
            background: var(--light-gray);
        }

        .activity-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }

        .no-activities {
            text-align: center;
            padding: 2rem;
            color: #999;
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
    <div style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        <div class="admin-header">
            <h1>👥 User Management</h1>
            <a href="admin_control_panel.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Admin Password Change Section -->
        <div class="admin-password-section" style="background: linear-gradient(135deg, #FFF3CD, #FFEAA7); border: 2px solid #F59E0B; border-radius: 10px; padding: 2rem; margin-bottom: 2rem;">
            <h2 style="color: #92400E; margin-bottom: 1rem;">🔐 Change Admin Password</h2>
            <p style="color: #92400E; margin-bottom: 1.5rem;">Keep your admin account secure by regularly updating your password.</p>
            
            <form method="post" style="background: white; padding: 1.5rem; border-radius: 8px; border: 1px solid #F59E0B;">
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label for="current_admin_password" style="display: block; font-weight: bold; margin-bottom: 0.5rem; color: #92400E;">Current Password *</label>
                        <input type="password" id="current_admin_password" name="current_admin_password" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div>
                        <label for="new_admin_password" style="display: block; font-weight: bold; margin-bottom: 0.5rem; color: #92400E;">New Password *</label>
                        <input type="password" id="new_admin_password" name="new_admin_password" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                        <small style="color: #666;">Minimum 8 characters</small>
                    </div>
                    <div>
                        <label for="confirm_admin_password" style="display: block; font-weight: bold; margin-bottom: 0.5rem; color: #92400E;">Confirm New Password *</label>
                        <input type="password" id="confirm_admin_password" name="confirm_admin_password" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </div>
                <button type="submit" name="change_admin_password" style="background: #F59E0B; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">Change Admin Password</button>
            </form>
        </div>

        <div class="filter-bar">
            <select id="filterStatus" onchange="filterUsers()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending_verification">Pending Verification</option>
                <option value="suspended">Suspended</option>
            </select>

            <select id="filterType" onchange="filterUsers()">
                <option value="">All User Types</option>
                <option value="buyer">Buyers</option>
                <option value="seller">Sellers</option>
                <option value="agent">Agents</option>
            </select>

            <select id="filterKYC" onchange="filterUsers()">
                <option value="">All KYC Status</option>
                <option value="verified">KYC Verified</option>
                <option value="pending">KYC Pending</option>
            </select>
        </div>

        <div class="user-grid" id="userGrid">
            <?php
            // Get all users
            $query = "SELECT * FROM users ORDER BY created_at DESC";
            $result = $conn->query($query);

            if($result && $result->num_rows > 0) {
                while($user = $result->fetch_assoc()):
                    $display_name = trim($user['name'] ?? ($user['first_name'] . ' ' . ($user['last_name'] ?? '')));
                    if (empty($display_name)) {
                        $display_name = 'Unknown User';
                    }
                    $initials = strtoupper(substr($display_name, 0, 1));
                    $kyc_verified = isset($user['kyc_verified']) && $user['kyc_verified'] ? 'verified' : 'pending';
                    $status = $user['status'] ?? 'active';
                    $user_type = $user['user_type'] ?? 'buyer';
                    $profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : '';
                    $id_front_path = !empty($user['id_front_path']) ? $user['id_front_path'] : '';
                    $id_back_path = !empty($user['id_back_path']) ? $user['id_back_path'] : '';
                    $email_address = htmlspecialchars($user['email'] ?? 'No email');
            ?>
            <div class="user-card" data-status="<?= htmlspecialchars($status) ?>" data-type="<?= htmlspecialchars($user_type) ?>" data-kyc="<?= $kyc_verified ?>">
                <div class="user-header">
                    <?php if ($profile_picture): ?>
                        <img src="<?= htmlspecialchars($profile_picture) ?>" alt="<?= htmlspecialchars($display_name) ?>" class="user-avatar-img">
                    <?php else: ?>
                        <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
                    <?php endif; ?>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($display_name) ?></h3>
                        <p><?= $email_address ?></p>
                    </div>
                </div>

                <div class="user-details">
                    <div class="detail-row">
                        <span class="detail-label">Type:</span>
                        <span class="detail-value"><?= ucfirst($user_type) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= htmlspecialchars($status) ?>">
                                <?= ucfirst(str_replace('_', ' ', $status)) ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">KYC:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= $kyc_verified == 'verified' ? 'active' : 'pending' ?>">
                                <?= $kyc_verified == 'verified' ? '✓ Verified' : '⏳ Pending' ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Joined:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($user['created_at'] ?? 'now')) ?></span>
                    </div>
                </div>

                <div class="user-actions">
                    <button class="btn btn-primary" onclick="openModal('user-<?= $user['id'] ?>')">View Details</button>
                    <a href="impersonate.php?user_id=<?= $user['id'] ?>" class="btn btn-info" onclick="return confirm('Are you sure you want to login as this user?')">Login As User</a>
                    <?php if($kyc_verified !== 'verified'): ?>
                        <button class="btn btn-success" onclick="openModal('verify-kyc-<?= $user['id'] ?>')">Verify KYC</button>
                    <?php endif; ?>
                    <?php if($user_type !== 'agent'): ?>
                        <button class="btn btn-success" onclick="openModal('make-agent-<?= $user['id'] ?>')">Make Agent</button>
                    <?php else: ?>
                        <button class="btn btn-warning" onclick="openModal('remove-agent-<?= $user['id'] ?>')">Remove Agent</button>
                    <?php endif; ?>
                    <?php if($status !== 'suspended'): ?>
                        <button class="btn btn-danger" onclick="openModal('suspend-<?= $user['id'] ?>')">Suspend</button>
                    <?php else: ?>
                        <button class="btn btn-success" onclick="openModal('activate-<?= $user['id'] ?>')">Activate</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- USER DETAIL MODAL -->
            <div class="modal" id="user-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>User Details</h2>
                        <button class="close-btn" onclick="closeModal('user-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" value="<?= htmlspecialchars($user['name'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" value="<?= htmlspecialchars($user['phone'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>User Type</label>
                        <input type="text" value="<?= ucfirst($user_type) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <input type="text" value="<?= ucfirst(str_replace('_', ' ', $status)) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>KYC Status</label>
                        <input type="text" value="<?= $kyc_verified == 'verified' ? 'Verified' : 'Pending' ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Type</label>
                        <input type="text" value="<?= htmlspecialchars($user['id_type'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" value="<?= htmlspecialchars($user['id_number'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Profile Photo</label>
                        <?php if ($profile_picture): ?>
                            <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile photo of <?= htmlspecialchars($display_name) ?>" style="max-width: 100%; border-radius: 12px; margin-top: 0.5rem;" />
                        <?php else: ?>
                            <input type="text" value="None uploaded" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>ID Front</label>
                        <?php if ($id_front_path): ?>
                            <img src="<?= htmlspecialchars($id_front_path) ?>" alt="ID front for <?= htmlspecialchars($display_name) ?>" style="max-width: 100%; border-radius: 12px; margin-top: 0.5rem;" />
                        <?php else: ?>
                            <input type="text" value="Not uploaded" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>ID Back</label>
                        <?php if ($id_back_path): ?>
                            <img src="<?= htmlspecialchars($id_back_path) ?>" alt="ID back for <?= htmlspecialchars($display_name) ?>" style="max-width: 100%; border-radius: 12px; margin-top: 0.5rem;" />
                        <?php else: ?>
                            <input type="text" value="Not uploaded" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= date('F d, Y g:i A', strtotime($user['created_at'] ?? 'now')) ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- VERIFY KYC MODAL -->
            <div class="modal" id="verify-kyc-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Verify KYC</h2>
                        <button class="close-btn" onclick="closeModal('verify-kyc-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Are you sure you want to verify KYC for <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>?</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="verify_kyc">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm KYC Verification</button>
                    </form>
                </div>
            </div>

            <!-- MAKE AGENT MODAL -->
            <div class="modal" id="make-agent-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Promote to Agent</h2>
                        <button class="close-btn" onclick="closeModal('make-agent-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Are you sure you want to promote <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong> to an agent?</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="make_agent">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm Promotion</button>
                    </form>
                </div>
            </div>

            <!-- REMOVE AGENT MODAL -->
            <div class="modal" id="remove-agent-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Remove Agent Role</h2>
                        <button class="close-btn" onclick="closeModal('remove-agent-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Remove <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong> from agent role and assign new role:</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="remove_agent">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <div class="form-group">
                            <label>Assign New Role</label>
                            <select name="new_role" required>
                                <option value="buyer">Buyer</option>
                                <option value="seller">Seller</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-warning" style="width: 100%; padding: 1rem;">Remove Agent Role</button>
                    </form>
                </div>
            </div>

            <!-- SUSPEND MODAL -->
            <div class="modal" id="suspend-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Suspend Account</h2>
                        <button class="close-btn" onclick="closeModal('suspend-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="form_action" value="suspend_account">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        
                        <div class="form-group">
                            <label>Reason for Suspension</label>
                            <textarea name="suspension_reason" required placeholder="Enter the reason for suspension..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 1rem;">Suspend Account</button>
                    </form>
                </div>
            </div>

            <!-- ACTIVATE MODAL -->
            <div class="modal" id="activate-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Activate Account</h2>
                        <button class="close-btn" onclick="closeModal('activate-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Are you sure you want to reactivate <strong><?= htmlspecialchars($user['name'] ?? '') ?></strong>'s account?</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="activate_account">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm Activation</button>
                    </form>
                </div>
            </div>
            <?php endwhile;
            } else { ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #999;">
                    <p>No users found in the system.</p>
                </div>
            <?php } ?>
        </div>

        <!-- User Activities Section -->
        <div class="activities-section">
            <h2>📋 User Activities</h2>
            <?php
            // Get admin activity logs
            $activity_query = "SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT 50";
            $activity_result = $conn->query($activity_query);
            
            if($activity_result && $activity_result->num_rows > 0) {
            ?>
            <table class="activities-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Target User</th>
                        <th>Details</th>
                        <th>Admin</th>
                        <th>Date/Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($activity = $activity_result->fetch_assoc()): 
                        $details = json_decode($activity['details'] ?? '{}', true);
                        $details_text = is_array($details) && isset($details['message']) ? $details['message'] : $activity['details'];
                    ?>
                    <tr>
                        <td>
                            <span class="activity-badge"><?= htmlspecialchars($activity['action'] ?? '') ?></span>
                        </td>
                        <td><?= htmlspecialchars($activity['user_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($details_text ?? '') ?></td>
                        <td><?= htmlspecialchars($activity['admin_id'] ?? 'System') ?></td>
                        <td><?= date('M d, Y H:i A', strtotime($activity['created_at'] ?? 'now')) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php } else { ?>
            <div class="no-activities">
                <p>No admin activities recorded yet. <a href="create_admin_logs_table.php">Create admin logs table</a> to enable activity tracking.</p>
            </div>
            <?php } ?>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function filterUsers() {
            const status = document.getElementById('filterStatus').value;
            const type = document.getElementById('filterType').value;
            const kyc = document.getElementById('filterKYC').value;

            const cards = document.querySelectorAll('.user-card');
            cards.forEach(card => {
                let show = true;

                if (status && card.dataset.status !== status) show = false;
                if (type && card.dataset.type !== type) show = false;
                if (kyc && card.dataset.kyc !== kyc) show = false;

                card.style.display = show ? '' : 'none';
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>
