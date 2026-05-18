<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Check if admin is logged in
$admin_user_id = intval($_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? 0);
if ($admin_user_id <= 0) {
    http_response_header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$action = $_POST['action'] ?? '';
$type = $_POST['type'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Missing required action']);
    exit;
}

try {
    if ($action === 'delete') {
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Missing notification ID']);
            exit;
        }

        // Delete from admin_notifications table
        $delete_stmt = $conn->prepare("UPDATE admin_notifications SET is_dismissed = TRUE WHERE id = ? AND admin_id = ?");
        $delete_stmt->bind_param("ii", $id, $admin_user_id);
        $delete_stmt->execute();
        $affected_rows = $delete_stmt->affected_rows;
        $delete_stmt->close();

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Notification dismissed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found or already dismissed']);
        }
    } elseif ($action === 'delete_bulk') {
        // Bulk delete notifications
        $ids_str = $_POST['ids'] ?? '';
        $ids_array = array_filter(array_map('intval', explode(',', $ids_str)));
        
        if (empty($ids_array)) {
            echo json_encode(['success' => false, 'message' => 'No notifications selected']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
        $bulk_delete_query = "UPDATE admin_notifications SET is_dismissed = TRUE WHERE id IN ($placeholders) AND admin_id = ?";
        $bulk_stmt = $conn->prepare($bulk_delete_query);
        
        if (!$bulk_stmt) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        // Bind parameters
        $types = str_repeat('i', count($ids_array)) . 'i';
        $bind_params = array_merge($ids_array, [$admin_user_id]);
        $bulk_stmt->bind_param($types, ...$bind_params);
        $bulk_stmt->execute();
        $affected_rows = $bulk_stmt->affected_rows;
        $bulk_stmt->close();

        if ($affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => "Dismissed $affected_rows notification(s)"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No notifications were deleted']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>