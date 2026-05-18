<?php
/**
 * Create Sample Mover Groups and Members
 * For testing the agent dashboard
 */

require_once 'config_mover_system.php';

echo "<h1>Create Sample Mover Groups</h1>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; }</style>";

try {
    $conn = getMoverDatabaseConnection();

    // Create sample groups
    $groups = [
        ['name' => 'Team Alpha', 'members' => [
            ['name' => 'James Wilson', 'contact' => 'james@example.com'],
            ['name' => 'Sarah Johnson', 'contact' => 'sarah@example.com'],
            ['name' => 'Mike Brown', 'contact' => '+254711111111']
        ]],
        ['name' => 'Team Beta', 'members' => [
            ['name' => 'David Lee', 'contact' => 'david@example.com'],
            ['name' => 'Anna Davis', 'contact' => 'anna@example.com'],
            ['name' => 'Tom Garcia', 'contact' => '+254722222222']
        ]]
    ];

    foreach ($groups as $groupData) {
        // Insert group
        $stmt = $conn->prepare("INSERT INTO mover_groups (group_name) VALUES (?)");
        $stmt->bind_param("s", $groupData['name']);
        if ($stmt->execute()) {
            $groupId = $conn->insert_id;
            echo "<p class='success'>✓ Created group: {$groupData['name']} (ID: $groupId)</p>";

            // Insert members
            foreach ($groupData['members'] as $member) {
                $memberStmt = $conn->prepare("INSERT INTO mover_group_members (group_id, employee_name, employee_contact) VALUES (?, ?, ?)");
                $memberStmt->bind_param("iss", $groupId, $member['name'], $member['contact']);
                if ($memberStmt->execute()) {
                    echo "<p style='margin-left: 20px; color: green;'>• Added member: {$member['name']}</p>";
                } else {
                    echo "<p style='margin-left: 20px; color: red;'>• Failed to add member: {$member['name']}</p>";
                }
                $memberStmt->close();
            }
        } else {
            echo "<p class='error'>✗ Failed to create group: {$groupData['name']}</p>";
        }
        $stmt->close();
    }

    // Assign a booking to Team Alpha for testing
    $assignStmt = $conn->prepare("UPDATE mover_bookings SET status = 'assigned', assigned_group_id = 1 WHERE status = 'pending' LIMIT 1");
    if ($assignStmt->execute() && $assignStmt->affected_rows > 0) {
        // Create notification
        $notifyStmt = $conn->prepare("INSERT INTO mover_notifications (group_id, booking_id, message) VALUES (1, (SELECT id FROM mover_bookings WHERE assigned_group_id = 1 LIMIT 1), 'New moving job assigned to your team')");
        $notifyStmt->execute();
        $notifyStmt->close();

        echo "<p class='success'>✓ Assigned a booking to Team Alpha and created notification</p>";
    }
    $assignStmt->close();

    $conn->close();

    echo "<hr>";
    echo "<h2>Testing Complete!</h2>";
    echo "<p><a href='agent_moving_jobs.php?group_id=1' target='_blank'>Test Agent Dashboard (Team Alpha)</a></p>";
    echo "<p><a href='agent_moving_jobs.php?group_id=2' target='_blank'>Test Agent Dashboard (Team Beta)</a></p>";
    echo "<p><strong>Note:</strong> For real agents, they would log in with their email matching the employee_contact field.</p>";

} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>