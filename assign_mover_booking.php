<?php
/**
 * Assign Mover Booking to Group
 * Assigns a pending booking to a mover group and creates notifications
 */

header('Content-Type: application/json');

require_once 'config.php';
require_once 'config_mover_system.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$bookingId = intval($_POST['booking_id'] ?? 0);
$groupId = intval($_POST['group_id'] ?? 0);

if ($bookingId <= 0 || $groupId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking or group ID']);
    exit;
}

try {
    $conn = getMoverDatabaseConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Start transaction
    $conn->begin_transaction();

    // Verify booking exists and is pending
    $bookingStmt = $conn->prepare("SELECT id, client_name, location_from, location_to FROM mover_bookings WHERE id = ? AND status = 'pending'");
    $bookingStmt->bind_param("i", $bookingId);
    $bookingStmt->execute();
    $bookingResult = $bookingStmt->get_result();
    $booking = $bookingResult->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        throw new Exception("Booking not found or is not in pending status");
    }

    // Verify group exists
    $groupStmt = $conn->prepare("SELECT id, group_name FROM mover_groups WHERE id = ?");
    $groupStmt->bind_param("i", $groupId);
    $groupStmt->execute();
    $groupResult = $groupStmt->get_result();
    $group = $groupResult->fetch_assoc();
    $groupStmt->close();

    if (!$group) {
        throw new Exception("Group not found");
    }

    // Update booking status and assign group
    $updateStmt = $conn->prepare("UPDATE mover_bookings SET status = 'assigned', assigned_group_id = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ii", $groupId, $bookingId);
    if (!$updateStmt->execute()) {
        throw new Exception("Failed to update booking: " . $updateStmt->error);
    }
    $updateStmt->close();

    // Create notification for the group
    $notificationMessage = "New relocation job assigned from {$booking['location_from']} to {$booking['location_to']}";
    $notifyStmt = $conn->prepare("INSERT INTO mover_notifications (group_id, booking_id, message, is_read, created_at) VALUES (?, ?, ?, FALSE, NOW())");
    $notifyStmt->bind_param("iis", $groupId, $bookingId, $notificationMessage);
    if (!$notifyStmt->execute()) {
        throw new Exception("Failed to create notification: " . $notifyStmt->error);
    }
    $notifyStmt->close();

    // Log activity
    logMoverActivity($conn, $bookingId, 'BOOKING_ASSIGNED', "Assigned to group: {$group['group_name']}");

    // Get group members for email notifications
    $membersStmt = $conn->prepare("SELECT employee_name, employee_contact FROM mover_group_members WHERE group_id = ?");
    $membersStmt->bind_param("i", $groupId);
    $membersStmt->execute();
    $membersResult = $membersStmt->get_result();
    $members = $membersResult->fetch_all(MYSQLI_ASSOC);
    $membersStmt->close();

    // Commit transaction
    $conn->commit();
    $conn->close();

    // Send notifications to group members (emails)
    foreach ($members as $member) {
        sendMemberNotificationEmail($member, $booking, $group);
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Booking assigned successfully',
        'group_name' => $group['group_name'],
        'members_notified' => count($members)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Assignment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Send notification email to group member
 */
function sendMemberNotificationEmail($member, $booking, $group) {
    $to = $member['employee_contact']; // Assuming this is email
    $subject = "New Relocation Job Assignment";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c3e50; color: white; padding: 20px; border-radius: 5px; }
            .content { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .details { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #e74c3c; }
        </style>
    </head>
    <body>
        <div class=\"container\">
            <div class=\"header\">
                <h2>🚚 New Job Assignment</h2>
            </div>
            
            <div class=\"content\">
                <p>Hello {$member['employee_name']},</p>
                <p>You have been assigned a new moving job as part of the {$group['group_name']} team.</p>
                
                <div class=\"details\">
                    <h3>Job Details:</h3>
                    <p><strong>From:</strong> {$booking['location_from']}</p>
                    <p><strong>To:</strong> {$booking['location_to']}</p>
                    <p><strong>Date:</strong> " . date('d M, Y', strtotime($booking['moving_date'])) . "</p>
                    <p><strong>Time:</strong> {$booking['moving_time']}</p>
                    <p><strong>Client:</strong> {$booking['client_name']}</p>
                    <p><strong>Client Contact:</strong> {$booking['phone']}</p>
                    <p><strong>Distance:</strong> {$booking['distance_km']} km</p>
                </div>
                
                <p>Please confirm your availability and reach out to your team lead if you have any questions.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: assignments@walbrandmovers.com" . "\r\n";

    @mail($to, $subject, $message, $headers);
}

?>
