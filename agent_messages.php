<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

// Check if user is logged in and is an agent
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'agent') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user info
$user_query = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id'] ?? 0);

    if ($message_id > 0) {
        $admin_query = "SELECT id FROM users WHERE user_type = 'admin' LIMIT 1";
        $admin_result = $conn->query($admin_query);

        if ($admin_result && $admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            $admin_id = intval($admin['id']);

            $check_stmt = $conn->prepare("SELECT sender_id, receiver_id FROM agent_messages WHERE id = ?");
            if ($check_stmt) {
                $check_stmt->bind_param('i', $message_id);
                $check_stmt->execute();
                $message_row = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();

                if ($message_row) {
                    $sender_id = intval($message_row['sender_id']);
                    $receiver_id = intval($message_row['receiver_id']);
                    $other_user_id = $sender_id === $user_id ? $receiver_id : $sender_id;

                    if (($sender_id === $user_id || $receiver_id === $user_id) && $other_user_id === $admin_id) {
                        $delete_stmt = $conn->prepare("UPDATE agent_messages SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
                        if ($delete_stmt) {
                            $delete_stmt->bind_param('i', $message_id);
                            if ($delete_stmt->execute()) {
                                $_SESSION['success_message'] = 'Message deleted successfully.';
                            } else {
                                $_SESSION['error_message'] = 'Unable to delete message. Please try again.';
                            }
                            $delete_stmt->close();
                        } else {
                            $_SESSION['error_message'] = 'Unable to delete message. Please try again.';
                        }
                    } else {
                        $_SESSION['error_message'] = 'You are not authorized to delete this message.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Message not found.';
                }
            }
        } else {
            $_SESSION['error_message'] = 'Admin user not found.';
        }
    } else {
        $_SESSION['error_message'] = 'Invalid message selection.';
    }

    header('Location: agent_messages.php');
    exit();
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (!empty($message)) {
        // Find admin user (assuming there's a super_admin)
        $admin_query = "SELECT id FROM users WHERE user_type = 'admin' LIMIT 1";
        $admin_result = $conn->query($admin_query);

        if ($admin_result && $admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            $admin_id = $admin['id'];

            $insert_query = "INSERT INTO agent_messages (sender_id, receiver_id, title, message, message_type, created_at)
                            VALUES (?, ?, ?, ?, 'agent_to_admin', NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiss", $user_id, $admin_id, $title, $message);

            if ($insert_stmt->execute()) {
                $_SESSION['success_message'] = 'Message sent successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to send message. Please try again.';
            }
        } else {
            $_SESSION['error_message'] = 'Unable to find admin contact.';
        }
    } else {
        $_SESSION['error_message'] = 'Message cannot be empty.';
    }

    header("Location: agent_messages.php");
    exit();
}

// Get conversation with admin
$admin_query = "SELECT id FROM users WHERE user_type = 'admin' LIMIT 1";
$admin_result = $conn->query($admin_query);
$admin_id = null;

if ($admin_result && $admin_result->num_rows > 0) {
    $admin = $admin_result->fetch_assoc();
    $admin_id = $admin['id'];

    $messages_query = "SELECT am.*, u.first_name, u.last_name, u.profile_picture, pm.message as parent_message
                      FROM agent_messages am
                      JOIN users u ON am.sender_id = u.id
                      LEFT JOIN agent_messages pm ON am.parent_message_id = pm.id
                      WHERE ((am.sender_id = ? AND am.receiver_id = ?) OR (am.sender_id = ? AND am.receiver_id = ?)) AND am.is_deleted = 0
                      ORDER BY am.created_at ASC";
    $messages_stmt = $conn->prepare($messages_query);
    $messages_stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
    $messages_stmt->execute();
    $messages_result = $messages_stmt->get_result();

    $messages = [];
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }

    // Mark admin messages as read
    $update_read = "UPDATE agent_messages SET is_read = 1, read_at = NOW()
                   WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND is_deleted = 0";
    $read_stmt = $conn->prepare($update_read);
    $read_stmt->bind_param("ii", $admin_id, $user_id);
    $read_stmt->execute();
}

// Update unread count for navigation
$unread_query = "SELECT COUNT(*) as unread_count FROM agent_messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted = 0";
$unread_stmt = $conn->prepare($unread_query);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread_count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Agent Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --sidebar-bg: #071c2b;
            --sidebar-text: #cbd5e1;
            --sidebar-accent: #38bdf8;
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --success: #10b981;
            --success-light: #d1fae5;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: var(--gray-50);
            color: var(--gray-700);
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--sidebar-accent), #06b6d4);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            color: white;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 700;
            color: white;
        }

        .brand-subtitle {
            font-size: 12px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-links {
            display: grid;
            gap: 8px;
            padding: 0 16px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--sidebar-text);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(56, 189, 248, 0.1);
            color: var(--sidebar-accent);
        }

        .nav-link i {
            width: 18px;
            text-align: center;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px 16px;
        }

        .support-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
        }

        .support-card h4 {
            color: white;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .support-card p {
            color: var(--gray-300);
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .support-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            color: var(--gray-300);
        }

        .support-item strong {
            color: white;
        }

        .support-card a {
            display: inline-block;
            margin-top: 12px;
            color: var(--sidebar-accent);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }

        .main {
            margin-left: 280px;
            flex: 1;
            padding: 24px;
        }

        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .messages-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
        }

        .messages-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .messages-header p {
            opacity: 0.9;
        }

        .messages-content {
            display: flex;
            height: 600px;
        }

        .messages-list {
            width: 350px;
            border-right: 1px solid #e2e8f0;
            background: #f8fafc;
            overflow-y: auto;
        }

        .messages-list-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .messages-list-header h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
        }

        .conversation-item {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .conversation-item:hover {
            background: #f1f5f9;
        }

        .conversation-item.active {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
        }

        .conversation-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 12px;
            flex-shrink: 0;
        }

        .conversation-info {
            flex: 1;
            min-width: 0;
        }

        .conversation-name {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .conversation-preview {
            color: #64748b;
            font-size: 0.875rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .conversation-time {
            color: #94a3b8;
            font-size: 0.75rem;
        }

        .messages-thread {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .thread-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: white;
        }

        .thread-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
        }

        .thread-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #fafbfc;
        }

        .message-item {
            margin-bottom: 20px;
            padding: 16px;
            border-radius: 12px;
            position: relative;
            background: white;
        }

        .message-item.agent {
            margin-left: 40px;
            border-left: 4px solid #2563eb !important;
            background: #eff6ff !important;
            color: #1e40af !important;
        }

        .message-item.admin {
            margin-right: 40px;
            margin-left: auto;
            max-width: 70%;
            border-left: 4px solid #059669 !important;
            background: #ecfdf5 !important;
            color: #065f46 !important;
        }
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .message-sender {
            font-weight: 600;
            font-size: 0.875rem;
            color: inherit;
        }

        .message-time {
            font-size: 0.75rem;
            color: inherit;
            opacity: 0.8;
        }

        .message-title {
            font-weight: 600;
            margin-bottom: 8px;
            color: inherit;
        }

        .message-content {
            color: inherit;
            line-height: 1.5;
        }

        .message-delete {
            position: absolute;
            top: 16px;
            right: 16px;
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .quoted-message {
            border-left: 3px solid #e5e7eb;
            padding-left: 12px;
            margin-bottom: 12px;
            background: #f9fafb;
            padding: 8px 12px;
            border-radius: 6px;
            font-style: italic;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .message-attachment {
            margin-top: 12px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: #f9fafb;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .attachment-link:hover {
            text-decoration: underline;
        }

        .attachment-icon {
            font-size: 1.2rem;
        }

        .reply-button {
            background: transparent;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
            margin-top: 8px;
        }

        .reply-button:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .message-delete:hover {
            color: #b91c1c;
        }

        .thread-compose {
            border-top: 1px solid #e2e8f0;
            background: white;
            padding: 20px;
        }

        .compose-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .compose-input {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .compose-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .compose-button {
            align-self: flex-end;
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .compose-button:hover {
            background: #2563eb;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94a3b8;
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 768px) {
            .dashboard-layout {
                flex-direction: column;
            }

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                padding: 16px 0;
            }

            .main {
                margin-left: 0;
            }

            .messages-content {
                flex-direction: column;
                height: auto;
            }

            .messages-list {
                width: 100%;
                max-height: 300px;
            }

            .message-item.agent {
                margin-left: 0;
            }

            .message-item.admin {
                margin-right: 0;
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-icon">W</div>
                    <div>
                        <div class="brand-title">Walbrand</div>
                        <div class="brand-subtitle">Agent Hub</div>
                    </div>
                </div>

                <nav class="nav-links">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_dashboard.php' ? 'active' : '' ?>" href="agent_dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_leads.php' ? 'active' : '' ?>" href="agent_leads.php"><i class="fa-solid fa-phone"></i> Leads</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_properties.php' ? 'active' : '' ?>" href="agent_properties.php"><i class="fa-solid fa-building"></i> Properties</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_clients.php' ? 'active' : '' ?>" href="agent_clients.php"><i class="fa-solid fa-users"></i> Clients</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_viewings.php' ? 'active' : '' ?>" href="agent_viewings.php"><i class="fa-solid fa-eye"></i> Viewings</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_delivery_groups.php' ? 'active' : '' ?>" href="agent_delivery_groups.php"><i class="fa-solid fa-truck-fast"></i> Delivery Groups</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_tasks.php' ? 'active' : '' ?>" href="agent_tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'agent_messages.php' ? 'active' : '' ?>" href="agent_messages.php"><i class="fa-solid fa-message"></i> Messages<?= $unread_count ? ' <span style="background:#dc2626;color:white;border-radius:999px;padding:2px 8px;font-size:0.75rem;">'.$unread_count.'</span>' : '' ?></a>
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'earnings.php' ? 'active' : '' ?>" href="earnings.php"><i class="fa-solid fa-wallet"></i> Earnings</a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="support-card">
                    <h4>Need Admin Support?</h4>
                    <p>Send a direct message to your admin team, or use the payment details below for urgent payments.</p>
                    <div class="support-item"><span>M-Pesa Paybill</span><strong>5582122</strong></div>
                    <div class="support-item"><span>M-Pesa Name</span><strong>TITUS OMONDI</strong></div>
                    <a href="agent_messages.php">Contact Admin</a>
                </div>
            </div>
        </aside>

        <main class="main">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success_message']) ?>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error_message']) ?>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <div class="messages-container">
            <div class="messages-header">
                <h1><i class="fas fa-envelope"></i> Messages</h1>
                <p>Communicate with the admin team</p>
            </div>

            <div class="messages-content">
                <!-- Conversations List -->
                <div class="messages-list">
                    <div class="messages-list-header">
                        <h3>Admin</h3>
                    </div>
                    <div class="conversation-item active" onclick="selectConversation('admin')">
                        <div class="conversation-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="conversation-info">
                            <div class="conversation-name">Admin Team</div>
                            <div class="conversation-preview">
                                <?php
                                $last_message = end($messages ?? []);
                                if ($last_message) {
                                    echo htmlspecialchars(substr($last_message['message'], 0, 50)) . (strlen($last_message['message']) > 50 ? '...' : '');
                                } else {
                                    echo 'No messages yet';
                                }
                                ?>
                            </div>
                        </div>
                        <div class="conversation-time">
                            <?php
                            if ($last_message) {
                                echo date('M d', strtotime($last_message['created_at']));
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Message Thread -->
                <div class="messages-thread">
                    <div class="thread-header">
                        <h2>Admin Team</h2>
                    </div>

                    <div class="thread-messages" id="threadMessages">
                        <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <p>No messages yet. Start a conversation with the admin team.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $message): ?>
                                <?php
                                $is_agent = $message['sender_id'] == $user_id;
                                $message_class = $is_agent ? 'agent' : 'admin';
                                $sender_name = $is_agent ? 'You' : 'Admin';
                                ?>
                                <div class="message-item <?= $message_class ?>">
                                    <div class="message-header">
                                        <span class="message-sender"><?= $sender_name ?></span>
                                        <span class="message-time"><?= date('M d, H:i', strtotime($message['created_at'])) ?></span>
                                        <?php if ($message['message_type'] !== 'reply'): ?>
                                            <button type="button" class="reply-button" onclick="replyToMessage(<?= $message['id'] ?>, '<?= htmlspecialchars(addslashes($message['message'])) ?>')">Reply</button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($message['title'])): ?>
                                        <div class="message-title"><?= htmlspecialchars($message['title']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($message['message_type'] === 'reply' && !empty($message['parent_message'])): ?>
                                        <div class="quoted-message">
                                            <div class="quote-line"></div>
                                            <div class="quote-content">
                                                <strong>Replying to:</strong><br>
                                                <?= htmlspecialchars(substr($message['parent_message'], 0, 100)) ?><?php if (strlen($message['parent_message']) > 100): ?>...<?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <div class="message-content"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                                    <?php if (!empty($message['attachment_path'])): ?>
                                        <div class="message-attachment">
                                            <a href="<?= htmlspecialchars($message['attachment_path']) ?>" target="_blank" class="attachment-link">
                                                <i class="fas fa-paperclip"></i> <?= htmlspecialchars($message['attachment_name'] ?: 'Attachment') ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST" style="position:absolute; top:16px; right:16px;">
                                        <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                        <button type="submit" name="delete_message" class="message-delete" onclick="return confirm('Delete this message?');">Delete</button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Compose Message -->
                    <div class="thread-compose">
                        <form method="POST" class="compose-form" enctype="multipart/form-data">
                            <input type="hidden" name="parent_message_id" id="parent_message_id" value="">
                            <input type="text" name="title" class="compose-input" placeholder="Message title (optional)" maxlength="255">
                            <textarea name="message" class="compose-input compose-textarea" placeholder="Type your message..." required></textarea>
                            <input type="file" name="attachment" class="compose-input" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt" style="margin-bottom: 10px;">
                            <small style="color: #666; font-size: 0.8rem; display: block; margin-bottom: 10px;">Supported: Images, Videos, Audio, Documents (max 10MB)</small>
                            <div id="reply-indicator" style="display: none; background: #f0f8ff; padding: 8px; border-left: 3px solid #007bff; margin-bottom: 10px;">
                                <strong>Replying to:</strong> <span id="reply-text"></span>
                                <button type="button" onclick="cancelReply()" style="float: right; background: none; border: none; color: #666; cursor: pointer;">×</button>
                            </div>
                            <button type="submit" name="send_message" class="compose-button">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages
        document.addEventListener('DOMContentLoaded', function() {
            const threadMessages = document.getElementById('threadMessages');
            if (threadMessages) {
                threadMessages.scrollTop = threadMessages.scrollHeight;
            }
        });

        function selectConversation(type) {
            // For now, only admin conversation is available
            // Future enhancement: support multiple conversations
        }

        function replyToMessage(messageId, messageText) {
            document.getElementById('parent_message_id').value = messageId;
            document.getElementById('reply-text').textContent = messageText.substring(0, 100) + (messageText.length > 100 ? '...' : '');
            document.getElementById('reply-indicator').style.display = 'block';
            document.querySelector('.compose-textarea').focus();
            document.querySelector('.compose-textarea').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelReply() {
            document.getElementById('parent_message_id').value = '';
            document.getElementById('reply-indicator').style.display = 'none';
        }
    </script>
</body>
</html>