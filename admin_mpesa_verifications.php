<?php
/**
 * Admin M-Pesa Verification Review Panel
 */

require_once 'config.php';
require_once 'helpers.php';

secure_session_start();

// Check if admin
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

global $conn;
$admin_id = intval($_SESSION['admin_id']);
$admin_user_id = intval($_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);

// Validate admin exists using admin_users.id
$admin_check = "SELECT id FROM admin_users WHERE id = ? AND is_active = 1";
$admin_stmt = $conn->prepare($admin_check);
$admin_stmt->bind_param('i', $admin_user_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();
if ($admin_result->num_rows === 0) {
    header('Location: admin_login.php');
    exit;
}
$admin_stmt->close();

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $verification_id = intval($_POST['verification_id'] ?? 0);
    $admin_message = trim($_POST['admin_message'] ?? '');

    if ($verification_id <= 0) {
        die('Invalid verification ID');
    }

    // Get verification record
    $get_query = "SELECT * FROM mpesa_verifications WHERE id = ?";
    $stmt = $conn->prepare($get_query);
    $stmt->bind_param('i', $verification_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $verification = $result->fetch_assoc();
    $stmt->close();

    if (!$verification) {
        die('Verification not found');
    }

    $user_id = intval($verification['user_id']);

    if ($action === 'confirm') {
        // Confirm the transaction
        $status = 'confirmed';
        $message = !empty($admin_message) ? $admin_message : 'Your M-Pesa transaction has been verified and confirmed. Thank you!';
        
        try {
            $update_query = "UPDATE mpesa_verifications 
                            SET status = ?, admin_id = ?, confirmation_message = ?, updated_at = NOW() 
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('sisi', $status, $admin_user_id, $message, $verification_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            die("Error confirming verification: " . $e->getMessage());
        }

        // Update related viewing request if exists
        if (!empty($verification['related_type']) && $verification['related_type'] === 'viewing_request' && !empty($verification['related_id'])) {
            $update_viewing = "UPDATE viewing_requests SET fee_paid = 1, payment_date = NOW() WHERE id = ?";
            $viewing_stmt = $conn->prepare($update_viewing);
            $viewing_stmt->bind_param('i', $verification['related_id']);
            $viewing_stmt->execute();
            $viewing_stmt->close();
        }

        // Send notification to client
        $notify_query = "INSERT INTO notifications 
                        (user_id, notification_type, title, message, related_id, is_read) 
                        VALUES (?, 'mpesa_confirmed', 'M-Pesa Verification Confirmed', ?, ?, 0)";
        $stmt = $conn->prepare($notify_query);
        $stmt->bind_param('isi', $user_id, $message, $verification_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_msg'] = 'Transaction confirmed and client notified.';

    } elseif ($action === 'suspend') {
        // Suspend/reject the transaction
        $status = 'suspended';
        $message = !empty($admin_message) ? $admin_message : 'Your M-Pesa transaction verification was not confirmed. Please review the details and resubmit.';
        
        try {
            $update_query = "UPDATE mpesa_verifications 
                            SET status = ?, admin_id = ?, confirmation_message = ?, updated_at = NOW() 
                            WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param('sisi', $status, $admin_user_id, $message, $verification_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            die("Error suspending verification: " . $e->getMessage());
        }

        // Send notification to client
        $notify_query = "INSERT INTO notifications 
                        (user_id, notification_type, title, message, related_id, is_read) 
                        VALUES (?, 'mpesa_suspended', 'M-Pesa Verification Not Confirmed', ?, ?, 0)";
        $stmt = $conn->prepare($notify_query);
        $stmt->bind_param('isi', $user_id, $message, $verification_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success_msg'] = 'Transaction suspended and client notified.';

    } elseif ($action === 'delete') {
        // Delete the verification
        $delete_query = "DELETE FROM mpesa_verifications WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param('i', $verification_id);
        $stmt->execute();
        $stmt->close();

        // Delete the screenshot file
        if (file_exists(__DIR__ . '/' . $verification['screenshot_path'])) {
            unlink(__DIR__ . '/' . $verification['screenshot_path']);
        }

        $_SESSION['success_msg'] = 'Verification record deleted.';
    }

    header('Location: admin_mpesa_verifications.php');
    exit;
}

// Get filter
$filter = isset($_GET['filter']) ? trim($_GET['filter']) : 'pending';
$allowed_filters = ['pending', 'confirmed', 'suspended', 'all'];
if (!in_array($filter, $allowed_filters)) {
    $filter = 'pending';
}

// Fetch verifications
$where_clause = $filter === 'all' ? '' : "WHERE m.status = '$filter'";
$query = "SELECT m.*, u.name AS username, u.email 
          FROM mpesa_verifications m 
          JOIN users u ON m.user_id = u.id 
          $where_clause 
          ORDER BY m.created_at DESC";

$result = db_query($query);
$verifications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $verifications[] = $row;
    }
}

// Count by status
$counts = [];
$status_types = ['pending', 'confirmed', 'suspended'];
foreach ($status_types as $status) {
    $count_query = "SELECT COUNT(*) as count FROM mpesa_verifications WHERE status = '$status'";
    $count_result = db_query($count_query);
    if ($count_result) {
        $count_data = $count_result->fetch_assoc();
        $counts[$status] = $count_data['count'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>M-Pesa Verification Review - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .verification-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1.5rem;
        }

        .verification-header {
            margin-bottom: 2rem;
        }

        .verification-header h1 {
            color: #1f2937;
            margin-bottom: 1rem;
        }

        .status-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .status-tab {
            padding: 0.75rem 1.5rem;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            color: #1f2937;
            font-weight: 500;
        }

        .status-tab:hover {
            border-color:#eef4fb;
            color:#eef4fb;
        }

        .status-tab.active {
            background:#eef4fb;
            color: white;
            border-color:#eef4fb;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.suspended {
            background: #fee2e2;
            color: #991b1b;
        }

        .verification-grid {
            display: grid;
            gap: 1.5rem;
        }

        .verification-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .verification-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .verification-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .verification-card-title {
            display: flex;
            flex-direction: column;
        }

        .verification-card-title h3 {
            margin: 0 0 0.5rem 0;
            color: #1f2937;
        }

        .verification-card-title p {
            margin: 0;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .verification-details {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #374151;
            min-width: 150px;
        }

        .detail-value {
            color: #6b7280;
            word-break: break-word;
        }

        .screenshot-preview {
            margin: 1rem 0;
            border-radius: 8px;
            overflow: hidden;
            max-width: 300px;
        }

        .screenshot-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        .verification-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 0.65rem 1.2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-confirm {
            background: #10b981;
            color: white;
        }

        .btn-confirm:hover {
            background: #059669;
        }

        .btn-suspend {
            background: #f59e0b;
            color: white;
        }

        .btn-suspend:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-view {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }

        .btn-view:hover {
            background: #2563eb;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 25px rgba(0, 0, 0, 0.15);
        }

        .modal-content h2 {
            margin-top: 0;
            color: #1f2937;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .btn-close-modal {
            background: #e5e7eb;
            color: #374151;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            flex: 1;
        }

        .btn-submit-modal {
            background:#eef4fb;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            flex: 1;
            font-weight: 600;
        }

        .btn-submit-modal:hover {
            background: #e67e00;
        }

        .success-message {
            background: #d1fae5;
            border: 1px solid #6ee7b7;
            color: #065f46;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        @media (max-width: 768px) {
            .verification-card-header {
                flex-direction: column;
            }

            .status-badge {
                margin-top: 0.5rem;
            }

            .detail-row {
                flex-direction: column;
                gap: 0.25rem;
            }

            .verification-actions {
                flex-direction: column;
            }

            .btn-action {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="verification-container">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($_SESSION['success_msg']); 
                unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="verification-header">
            <h1><i class="fa-solid fa-receipt"></i> M-Pesa Verification Review</h1>
            <a href="admin_control_panel.php" class="btn btn-secondary" style="margin-top: 1rem; display: inline-flex; align-items: center; gap: 0.5rem;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
        </div>

        <div class="status-tabs">
            <button class="status-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>" onclick="filterBy('pending')">
                Pending <span style="background: rgba(255,255,255,0.3); padding: 0.25rem 0.75rem; border-radius: 4px; margin-left: 0.5rem;"><?php echo $counts['pending'] ?? 0; ?></span>
            </button>
            <button class="status-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>" onclick="filterBy('confirmed')">
                Confirmed <span style="background: rgba(255,255,255,0.3); padding: 0.25rem 0.75rem; border-radius: 4px; margin-left: 0.5rem;"><?php echo $counts['confirmed'] ?? 0; ?></span>
            </button>
            <button class="status-tab <?php echo $filter === 'suspended' ? 'active' : ''; ?>" onclick="filterBy('suspended')">
                Suspended <span style="background: rgba(255,255,255,0.3); padding: 0.25rem 0.75rem; border-radius: 4px; margin-left: 0.5rem;"><?php echo $counts['suspended'] ?? 0; ?></span>
            </button>
            <button class="status-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="filterBy('all')">
                All
            </button>
        </div>

        <?php if (empty($verifications)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                <p>No M-Pesa verifications to review</p>
            </div>
        <?php else: ?>
            <div class="verification-grid">
                <?php foreach ($verifications as $v): ?>
                    <div class="verification-card">
                        <div class="verification-card-header">
                            <div class="verification-card-title">
                                <h3><?php echo htmlspecialchars($v['username']); ?></h3>
                                <p><?php echo htmlspecialchars($v['email']); ?></p>
                            </div>
                            <span class="status-badge <?php echo $v['status']; ?>">
                                <?php echo ucfirst($v['status']); ?>
                            </span>
                        </div>

                        <div class="verification-details">
                            <div class="detail-row">
                                <span class="detail-label">M-Pesa Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($v['mpesa_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Transaction Code:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($v['transaction_code']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Contact:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($v['transaction_contact']); ?></span>
                            </div>
                            <?php if (!empty($v['related_type'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Related To:</span>
                                    <span class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $v['related_type'])); ?> #<?php echo $v['related_id']; ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Submitted:</span>
                                <span class="detail-value"><?php echo date('j M Y, g:i A', strtotime($v['created_at'])); ?></span>
                            </div>
                            <?php if (!empty($v['confirmation_message'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Admin Message:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($v['confirmation_message']); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button class="btn-view" onclick="openScreenshot('<?php echo htmlspecialchars($v['screenshot_path']); ?>')">
                            <i class="fa-solid fa-image"></i> View Screenshot
                        </button>

                        <?php if ($v['status'] === 'pending'): ?>
                            <div class="verification-actions">
                                <button class="btn-action btn-confirm" onclick="openModal(<?php echo $v['id']; ?>, 'confirm')">
                                    <i class="fa-solid fa-check"></i> Confirm
                                </button>
                                <button class="btn-action btn-suspend" onclick="openModal(<?php echo $v['id']; ?>, 'suspend')">
                                    <i class="fa-solid fa-ban"></i> Suspend
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteVerification(<?php echo $v['id']; ?>)">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Screenshot Modal -->
    <div class="modal-overlay" id="screenshotModal">
        <div class="modal-content">
            <h2>Screenshot Preview</h2>
            <img id="screenshotImage" src="" alt="Transaction Screenshot" style="width: 100%; border-radius: 8px;">
            <div class="modal-actions">
                <button class="btn-close-modal" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Action Modal (Confirm/Suspend) -->
    <div class="modal-overlay" id="actionModal">
        <div class="modal-content">
            <h2 id="actionTitle">Confirm Transaction</h2>
            <form method="POST">
                <input type="hidden" name="action" id="actionType" value="confirm">
                <input type="hidden" name="verification_id" id="verificationId" value="">
                
                <div class="form-group">
                    <label for="adminMessage">Message to Client (Optional)</label>
                    <textarea name="admin_message" id="adminMessage" placeholder="Enter a custom message for the client..."></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-close-modal" onclick="closeActionModal()">Cancel</button>
                    <button type="submit" class="btn-submit-modal">Send</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function filterBy(status) {
            window.location.href = 'admin_mpesa_verifications.php?filter=' + status;
        }

        function openScreenshot(path) {
            const modal = document.getElementById('screenshotModal');
            const img = document.getElementById('screenshotImage');
            img.src = path;
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('screenshotModal').classList.remove('active');
        }

        function openModal(verificationId, action) {
            const modal = document.getElementById('actionModal');
            const titleEl = document.getElementById('actionTitle');
            const actionInput = document.getElementById('actionType');
            const idInput = document.getElementById('verificationId');

            if (action === 'confirm') {
                titleEl.textContent = 'Confirm Transaction';
                actionInput.value = 'confirm';
            } else if (action === 'suspend') {
                titleEl.textContent = 'Suspend Transaction';
                actionInput.value = 'suspend';
            }

            idInput.value = verificationId;
            modal.classList.add('active');
        }

        function closeActionModal() {
            document.getElementById('actionModal').classList.remove('active');
            document.getElementById('adminMessage').value = '';
        }

        function deleteVerification(verificationId) {
            showConfirm('Are you sure you want to delete this verification? This action cannot be undone.')
                .then(confirmed => {
                    if (confirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="verification_id" value="${verificationId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
        }

        // Close modal when clicking outside
        document.getElementById('screenshotModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        document.getElementById('actionModal').addEventListener('click', function(e) {
            if (e.target === this) closeActionModal();
        });
    </script>
</body>
</html>
?>
