<?php
/**
 * Add/Manage Mover Groups and Members
 * Handles group creation, member addition/removal
 */

header('Content-Type: application/json');

require_once 'config_mover_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    $conn = getMoverDatabaseConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    if ($action === 'create') {
        // Create new group
        $groupName = trim($_POST['group_name'] ?? '');

        if (empty($groupName)) {
            throw new Exception("Group name is required");
        }

        $stmt = $conn->prepare("INSERT INTO mover_groups (group_name, created_at) VALUES (?, NOW())");
        $stmt->bind_param("s", $groupName);
        if (!$stmt->execute()) {
            throw new Exception("Group name must be unique. This name already exists.");
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Group created successfully']);

    } elseif ($action === 'add_member') {
        // Add member to group
        $groupId = intval($_POST['group_id'] ?? 0);
        $employeeName = trim($_POST['employee_name'] ?? '');
        $employeeContact = trim($_POST['employee_contact'] ?? '');

        if ($groupId <= 0) {
            throw new Exception("Invalid group ID");
        }
        if (empty($employeeName)) {
            throw new Exception("Employee name is required");
        }
        if (empty($employeeContact)) {
            throw new Exception("Employee contact is required");
        }

        // Check group exists
        $checkStmt = $conn->prepare("SELECT id FROM mover_groups WHERE id = ?");
        $checkStmt->bind_param("i", $groupId);
        $checkStmt->execute();
        if (!$checkStmt->get_result()->fetch_assoc()) {
            throw new Exception("Group not found");
        }
        $checkStmt->close();

        // Check member count
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM mover_group_members WHERE group_id = ?");
        $countStmt->bind_param("i", $groupId);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();

        if ($countResult['count'] >= MOVER_GROUP_SIZE) {
            throw new Exception("Group is full (maximum " . MOVER_GROUP_SIZE . " members)");
        }

        // Add member
        $stmt = $conn->prepare("INSERT INTO mover_group_members (group_id, employee_name, employee_contact, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iss", $groupId, $employeeName, $employeeContact);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add member: " . $stmt->error);
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Member added successfully']);

    } elseif ($action === 'remove_member') {
        // Remove member from group
        $memberId = intval($_POST['member_id'] ?? 0);

        if ($memberId <= 0) {
            throw new Exception("Invalid member ID");
        }

        $stmt = $conn->prepare("DELETE FROM mover_group_members WHERE id = ?");
        $stmt->bind_param("i", $memberId);
        if (!$stmt->execute()) {
            throw new Exception("Failed to remove member");
        }
        $stmt->close();

        echo json_encode(['success' => true, 'message' => 'Member removed successfully']);

    } else {
        throw new Exception("Invalid action");
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    error_log("Group Management Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
