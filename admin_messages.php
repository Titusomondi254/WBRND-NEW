<?php
require_once 'config.php';
secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
$session_user_role = strtolower(trim($_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? ''));

if ($user_id <= 0) {
    header('Location: login.php');
    exit();
}

$is_admin = in_array($session_user_role, ['admin', 'super_admin'], true);
if (!$is_admin) {
    header('Location: user_dashboard.php');
    exit();
}

// Get all agents
$agents = [];
$agents_stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE user_type IN ('agent', 'seller') ORDER BY first_name");
if ($agents_stmt) {
    $agents_stmt->execute();
    $agents_result = $agents_stmt->get_result();
    while ($row = $agents_result->fetch_assoc()) {
        $agents[] = $row;
    }
    $agents_stmt->close();
}

// Handle message deletion from admin dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $message_id = intval($_POST['message_id'] ?? 0);
    $selected_agent_id = intval($_POST['agent_id'] ?? 0);

    if ($message_id > 0 && $selected_agent_id > 0) {
        $check_stmt = $conn->prepare("SELECT sender_id, receiver_id FROM agent_messages WHERE id = ?");
        if ($check_stmt) {
            $check_stmt->bind_param('i', $message_id);
            $check_stmt->execute();
            $message_row = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($message_row) {
                $sender_id = intval($message_row['sender_id']);
                $receiver_id = intval($message_row['receiver_id']);

                if ((($sender_id === $user_id && $receiver_id === $selected_agent_id) || ($sender_id === $selected_agent_id && $receiver_id === $user_id))) {
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
        $_SESSION['error_message'] = 'Invalid message selection.';
    }

    header('Location: admin_messages.php?agent_id=' . max(0, $selected_agent_id));
    exit();
}

// Get selected agent and their messages
$selected_agent_id = intval($_GET['agent_id'] ?? 0);
$agent_messages = [];
if ($selected_agent_id > 0) {
    $msg_stmt = $conn->prepare("
        SELECT 
            am.id,
            am.title,
            am.message,
            am.created_at,
            am.sender_id,
            am.receiver_id,
            am.message_type,
            am.parent_message_id,
            am.attachment_path,
            am.attachment_name,
            CONCAT(u.first_name, ' ', u.last_name) as sender_name,
            pm.message as parent_message
        FROM agent_messages am
        LEFT JOIN users u ON am.sender_id = u.id
        LEFT JOIN agent_messages pm ON am.parent_message_id = pm.id
        WHERE (am.receiver_id = ? OR am.sender_id = ?) AND (am.sender_id = ? OR am.receiver_id = ?) AND am.is_deleted = 0
        ORDER BY am.created_at DESC
        LIMIT 50
    ");
    if ($msg_stmt) {
        $msg_stmt->bind_param('iiii', $user_id, $user_id, $selected_agent_id, $selected_agent_id);
        $msg_stmt->execute();
        $msg_result = $msg_stmt->get_result();
        while ($row = $msg_result->fetch_assoc()) {
            $agent_messages[] = $row;
        }
        $msg_stmt->close();
    }
}

// Get unread message count
$unread_count = 0;
$unread_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM agent_messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted = 0");
if ($unread_stmt) {
    $unread_stmt->bind_param('i', $user_id);
    $unread_stmt->execute();
    $unread_count = intval($unread_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $unread_stmt->close();
}

// Get current admin info
$admin_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
if ($admin_stmt) {
    $admin_stmt->bind_param('i', $user_id);
    $admin_stmt->execute();
    $admin_info = $admin_stmt->get_result()->fetch_assoc();
    $admin_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Messages - Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary: #f97316;
            --secondary: #1e293b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; }

        .container { display: flex; min-height: 100vh; }
        main { flex: 1; padding: 24px; }

        .header { margin-bottom: 24px; }
        .header h1 { font-size: 1.8rem; color: #0f172a; margin-bottom: 8px; }
        .header p { color: #64748b; }

        .messages-container { display: flex; gap: 24px; height: calc(100vh - 150px); }

        .agents-list {
            flex: 0 0 300px;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow-y: auto;
        }

        .agents-list h3 { padding: 16px; border-bottom: 1px solid #e2e8f0; font-size: 1rem; color: #0f172a; }

        .agent-item {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.2s;
        }

        .agent-item:hover { background: #f8fafc; }
        .agent-item.active { background: var(--primary); color: white; }
        .agent-item.active p { color: rgba(255, 255, 255, 0.8); }

        .agent-item strong { display: block; margin-bottom: 4px; }
        .agent-item p { font-size: 0.85rem; color: #64748b; }

        .messages-area {
            flex: 1;
            background: white;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
        }

        .messages-header { padding: 16px 24px; border-bottom: 1px solid #e2e8f0; }
        .messages-header h3 { font-size: 1.1rem; color: #0f172a; }

        .messages-body {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .no-selection { display: flex; align-items: center; justify-content: center; color: #94a3b8; }

        .message {
            padding: 12px 16px;
            border-radius: 8px;
            border-left: 4px solid var(--primary);
        }

        .message.agent {
            background: #fef3c7 !important;
            border-left-color: #d97706 !important;
            margin-left: 24px;
            color: #92400e !important;
        }

        .message.admin {
            background: #eff6ff !important;
            border-left-color: #2563eb !important;
            margin-right: 24px;
            color: #1e40af !important;
        }

        .message-title { font-weight: 600; margin-bottom: 4px; font-size: 0.95rem; }
        .message-text { margin-bottom: 4px; }
        .message-time { font-size: 0.85rem; color: #94a3b8; }

        .quoted-message {
            background: #f8fafc;
            border-left: 3px solid #cbd5e1;
            padding: 8px 12px;
            margin: 8px 0;
            font-size: 0.85rem;
            color: #64748b;
        }

        .quote-line {
            width: 2px;
            height: 100%;
            background: #cbd5e1;
            position: absolute;
            left: 0;
            top: 0;
        }

        .message-attachment {
            margin: 8px 0;
        }

        .attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.85rem;
            padding: 4px 8px;
            border: 1px solid var(--primary);
            border-radius: 4px;
            transition: all 0.2s;
        }

        .attachment-link:hover {
            background: var(--primary);
            color: white;
        }

        .reply-button {
            background: transparent;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0;
            margin-left: 12px;
            text-decoration: underline;
        }

        .reply-button:hover {
            color: #1e40af;
        }

        .delete-button {
            background: transparent;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0;
            margin-left: 12px;
            text-decoration: underline;
        }

        .delete-button:hover {
            color: #b91c1c;
        }

        .compose-area { padding: 24px; border-top: 1px solid #e2e8f0; }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            color: #1e293b;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .form-group textarea { resize: vertical; min-height: 80px; }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }

        @media (max-width: 1024px) {
            .messages-container { flex-direction: column; height: auto; }
            .agents-list { flex: 0 0 auto; max-height: 300px; }
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="container">
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

            <div class="header">
                <h1>📧 Agent Messages</h1>
                <p>View and respond to agent communications</p>
                <?php if ($unread_count > 0): ?>
                    <div style="margin-top: 12px; background: #fef3c7; border: 1px solid #fcd34d; padding: 12px; border-radius: 8px; color: #92400e;">
                        📬 You have <strong><?= $unread_count ?></strong> unread message(s)
                    </div>
                <?php endif; ?>
            </div>

            <div class="messages-container">
                <div class="agents-list">
                    <h3>👥 Agents</h3>
                    <?php if (!empty($agents)): ?>
                        <?php foreach ($agents as $agent): ?>
                            <div class="agent-item <?php echo $selected_agent_id === (int)$agent['id'] ? 'active' : ''; ?>" onclick="location.href='?agent_id=<?= $agent['id'] ?>'">
                                <strong><?= htmlspecialchars($agent['name']) ?></strong>
                                <p><?= htmlspecialchars($agent['email']) ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 16px; text-align: center; color: #94a3b8;">
                            No agents found
                        </div>
                    <?php endif; ?>
                </div>

                <div class="messages-area">
                    <?php if ($selected_agent_id <= 0): ?>
                        <div class="no-selection">
                            <p>👈 Select an agent from the list to view messages</p>
                        </div>
                    <?php else: ?>
                        <div class="messages-header">
                            <h3>Messages with <?= htmlspecialchars($selected_agent_id > 0 ? ($agents[array_search($selected_agent_id, array_column($agents, 'id'))] ? $agents[array_search($selected_agent_id, array_column($agents, 'id'))]['name'] : 'Agent') : 'Agent') ?></h3>
                        </div>

                        <div class="messages-body">
                            <?php if (!empty($agent_messages)): ?>
                                <?php foreach (array_reverse($agent_messages) as $msg): ?>
                                    <div class="message <?php echo $msg['sender_id'] === $user_id ? 'admin' : 'agent'; ?>">
                                        <div class="message-title">
                                            <?= htmlspecialchars($msg['title'] ?: 'Message') ?>
                                            <?php if ($msg['message_type'] === 'reply'): ?>
                                                <span style="font-size: 0.8rem; color: #94a3b8;"> (Reply)</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($msg['message_type'] === 'reply' && !empty($msg['parent_message'])): ?>
                                            <div class="quoted-message">
                                                <div class="quote-line"></div>
                                                <div class="quote-content">
                                                    <strong>Replying to:</strong><br>
                                                    <?= htmlspecialchars(substr($msg['parent_message'], 0, 100)) ?><?php if (strlen($msg['parent_message']) > 100): ?>...<?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-text"><?= htmlspecialchars($msg['message']) ?></div>
                                        <?php if (!empty($msg['attachment_path'])): ?>
                                            <div class="message-attachment">
                                                <a href="<?= htmlspecialchars($msg['attachment_path']) ?>" target="_blank" class="attachment-link">
                                                    <i class="fas fa-paperclip"></i> <?= htmlspecialchars($msg['attachment_name'] ?: 'Attachment') ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <div class="message-time">
                                            <?= htmlspecialchars($msg['sender_name']) ?> • <?= htmlspecialchars(date('M j, Y H:i', strtotime($msg['created_at']))) ?>
                                            <button type="button" class="reply-button" onclick="replyToMessage(<?= $msg['id'] ?>, '<?= htmlspecialchars(addslashes($msg['message'])) ?>')">Reply</button>
                                            <button type="button" class="delete-button" onclick="deleteMessage(<?= $msg['id'] ?>, <?= $selected_agent_id ?>)">Delete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-selection">
                                    <p>No messages yet. Start the conversation below.</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="compose-area">
                            <form action="send_admin_message.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="agent_id" value="<?= $selected_agent_id ?>">
                                <input type="hidden" name="parent_message_id" id="parent_message_id" value="">
                                <div class="form-group">
                                    <label for="msg-title">Subject</label>
                                    <input type="text" id="msg-title" name="title" placeholder="Message subject" required>
                                </div>
                                <div class="form-group">
                                    <label for="msg-body">Message</label>
                                    <textarea id="msg-body" name="message" placeholder="Type your message..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="attachment">Attachment (optional)</label>
                                    <input type="file" id="attachment" name="attachment" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.txt">
                                    <small style="color: #666; font-size: 0.8rem;">Supported: Images, Videos, Audio, Documents (max 10MB)</small>
                                </div>
                                <div id="reply-indicator" style="display: none; background: #f0f8ff; padding: 8px; border-left: 3px solid #007bff; margin-bottom: 10px;">
                                    <strong>Replying to:</strong> <span id="reply-text"></span>
                                    <button type="button" onclick="cancelReply()" style="float: right; background: none; border: none; color: #666; cursor: pointer;">×</button>
                                </div>
                                <button type="submit" class="btn">Send Message</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        async function deleteMessage(messageId, agentId) {
            const confirmed = await showConfirm('Delete this message?');
            if (confirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_messages.php?agent_id=' + agentId;
                form.style.display = 'none';
                const input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'message_id';
                input1.value = messageId;
                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'agent_id';
                input2.value = agentId;
                const submit = document.createElement('input');
                submit.type = 'hidden';
                submit.name = 'delete_message';
                submit.value = '1';
                form.appendChild(input1);
                form.appendChild(input2);
                form.appendChild(submit);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function replyToMessage(messageId, messageText) {
            document.getElementById('parent_message_id').value = messageId;
            document.getElementById('reply-text').textContent = messageText.substring(0, 100) + (messageText.length > 100 ? '...' : '');
            document.getElementById('reply-indicator').style.display = 'block';
            document.getElementById('msg-body').focus();
            document.getElementById('msg-body').scrollIntoView({ behavior: 'smooth' });
        }

        function cancelReply() {
            document.getElementById('parent_message_id').value = '';
            document.getElementById('reply-indicator').style.display = 'none';
        }

        function showConfirm(message) {
            return new Promise((resolve) => {
                if (window.confirm(message)) {
                    resolve(true);
                } else {
                    resolve(false);
                }
            });
        }
    </script>
</body>
</html>
