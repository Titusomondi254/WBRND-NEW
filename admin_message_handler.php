<?php
session_start();
require_once 'config.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_messages':
            $agent_id = intval($_GET['agent_id'] ?? 0);

            if (!$agent_id) {
                echo json_encode(['success' => false, 'message' => 'Agent ID required']);
                exit();
            }

            // Get conversation between admin and agent
            $query = "SELECT am.*, u.first_name, u.last_name
                     FROM agent_messages am
                     JOIN users u ON am.sender_id = u.id
                     WHERE ((am.sender_id = ? AND am.receiver_id = ?) OR (am.sender_id = ? AND am.receiver_id = ?)) AND am.is_deleted = 0
                     ORDER BY am.created_at ASC";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiii", $admin_id, $agent_id, $agent_id, $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $messages = [];
            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }

            // Mark messages as read (messages from agent to admin)
            $update_read = "UPDATE agent_messages SET is_read = 1, read_at = NOW()
                           WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND is_deleted = 0";
            $read_stmt = $conn->prepare($update_read);
            $read_stmt->bind_param("ii", $agent_id, $admin_id);
            $read_stmt->execute();

            echo json_encode(['success' => true, 'messages' => $messages]);
            break;

        case 'send_message':
            $receiver_id = intval($_POST['receiver_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if (!$receiver_id || empty($message)) {
                echo json_encode(['success' => false, 'message' => 'Receiver ID and message are required']);
                exit();
            }

            // Verify receiver is an active agent
            $check_agent = "SELECT id FROM users WHERE id = ? AND user_type = 'agent' AND is_active = 1";
            $check_stmt = $conn->prepare($check_agent);
            $check_stmt->bind_param("i", $receiver_id);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
                exit();
            }

            // Insert message
            $insert_query = "INSERT INTO agent_messages (sender_id, receiver_id, title, message, message_type, created_at)
                            VALUES (?, ?, ?, ?, 'admin_to_agent', NOW())";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("iiss", $admin_id, $receiver_id, $title, $message);

            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message_id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send message']);
            }
            break;

        case 'delete_message':
            $message_id = intval($_POST['message_id'] ?? 0);
            $agent_id = intval($_POST['agent_id'] ?? 0);

            if (!$message_id || !$agent_id) {
                echo json_encode(['success' => false, 'message' => 'Message ID and agent ID are required']);
                exit();
            }

            // Confirm this message belongs to this admin-agent conversation
            $check_query = "SELECT sender_id, receiver_id FROM agent_messages WHERE id = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('i', $message_id);
            $check_stmt->execute();
            $message_row = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if (!$message_row) {
                echo json_encode(['success' => false, 'message' => 'Message not found']);
                exit();
            }

            $sender_id = intval($message_row['sender_id']);
            $receiver_id = intval($message_row['receiver_id']);
            $is_valid = (($sender_id === $admin_id && $receiver_id === $agent_id) || ($sender_id === $agent_id && $receiver_id === $admin_id));

            if (!$is_valid) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized message deletion']);
                exit();
            }

            $delete_stmt = $conn->prepare("UPDATE agent_messages SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
            $delete_stmt->bind_param('i', $message_id);
            if ($delete_stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete message']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Admin message handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>