<?php
/**
 * Admin Property Verification Handler
 * Handles property verification, classification, and notifications
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */

require_once 'config.php';
require_once 'notification_utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Verify admin is logged in
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }

    $action = $_POST['action'] ?? null;

    // ============================================================================
    // ACTION: Verify Property
    // ============================================================================
    if ($action === 'verify_property') {
        $property_id = intval($_POST['property_id'] ?? 0);
        $occupancy_status = $_POST['occupancy_status'] ?? 'available';
        $admin_notes = $_POST['admin_notes'] ?? '';

        if ($property_id <= 0) {
            throw new Exception("Invalid property ID");
        }

        // Get property details
        $stmt = $conn->prepare("
            SELECT id, listing_type, bedrooms, seller_id, property_code
            FROM properties
            WHERE id = ?
        ");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $property = $result->fetch_assoc();
        $stmt->close();

        if (!$property) {
            throw new Exception("Property not found");
        }

        // Determine category based on listing type
        $category = categorize_property($property['listing_type']);

        // Calculate viewing fee based on bedrooms
        $viewing_fee = calculate_viewing_fee($property['bedrooms'] ?? 1);

        // Update property with verification
        $stmt = $conn->prepare("
            UPDATE properties
            SET 
                category = ?,
                occupancy_status = ?,
                verification_status = 'verified',
                status = ?,
                verified_by = ?,
                verified_at = NOW(),
                verification_notes = ?
            WHERE id = ?
        ");

        // If occupied, set status to delisted, otherwise verified
        $status = ($occupancy_status === 'occupied') ? 'delisted' : 'verified';

        $stmt->bind_param("sssssi", $category, $occupancy_status, $status, $_SESSION['admin_id'], $admin_notes, $property_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update property: " . $stmt->error);
        }
        $stmt->close();

        // Add viewing fee to property meta if not already set
        $stmt = $conn->prepare("
            UPDATE viewing_requests
            SET viewing_fee = ?
            WHERE property_id = ? AND viewing_fee = 0
        ");
        $stmt->bind_param("di", $viewing_fee, $property_id);
        $stmt->execute();
        $stmt->close();

        // Create verification notification for property owner
        $stmt = $conn->prepare("SELECT location FROM properties WHERE id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $prop_data = $result->fetch_assoc();
        $stmt->close();

        notifyPropertyVerified($property['seller_id'], $property['property_code'], $prop_data['location']);

        echo json_encode([
            'success' => true,
            'message' => 'Property verified successfully',
            'category' => $category,
            'viewing_fee' => $viewing_fee,
            'status' => $status
        ]);
    }

    // ============================================================================
    // ACTION: Reject Property
    // ============================================================================
    elseif ($action === 'reject_property') {
        $property_id = intval($_POST['property_id'] ?? 0);
        $rejection_reason = $_POST['rejection_reason'] ?? '';

        if ($property_id <= 0) {
            throw new Exception("Invalid property ID");
        }

        if (empty($rejection_reason)) {
            throw new Exception("Rejection reason is required");
        }

        // Get property details
        $stmt = $conn->prepare("
            SELECT id, seller_id, property_code
            FROM properties
            WHERE id = ?
        ");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $property = $result->fetch_assoc();
        $stmt->close();

        if (!$property) {
            throw new Exception("Property not found");
        }

        // Update property with rejection
        $stmt = $conn->prepare("
            UPDATE properties
            SET 
                verification_status = 'rejected',
                status = 'rejected',
                rejected_by = ?,
                rejected_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");

        $stmt->bind_param("isi", $_SESSION['admin_id'], $rejection_reason, $property_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to reject property: " . $stmt->error);
        }
        $stmt->close();

        // Create rejection notification for property owner
        $stmt = $conn->prepare("SELECT location FROM properties WHERE id = ?");
        $stmt->bind_param("i", $property_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $prop_data = $result->fetch_assoc();
        $stmt->close();

        notifyPropertyRejected($property['seller_id'], $property['property_code'], $prop_data['location'], $rejection_reason);

        echo json_encode([
            'success' => true,
            'message' => 'Property rejected successfully'
        ]);
    }

    // ============================================================================
    // ACTION: Assign Agent
    // ============================================================================
    elseif ($action === 'assign_agent') {
        $viewing_request_id = intval($_POST['viewing_request_id'] ?? 0);
        $agent_id = intval($_POST['agent_id'] ?? 0);

        if ($viewing_request_id <= 0 || $agent_id <= 0) {
            throw new Exception("Invalid viewing request ID or agent ID");
        }

        // Get viewing request details
        $stmt = $conn->prepare("
            SELECT id, user_id, property_id
            FROM viewing_requests
            WHERE id = ?
        ");
        $stmt->bind_param("i", $viewing_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewing_request = $result->fetch_assoc();
        $stmt->close();

        if (!$viewing_request) {
            throw new Exception("Viewing request not found");
        }

        // Update viewing request with agent assignment
        $stmt = $conn->prepare("
            UPDATE viewing_requests
            SET agent_id = ?
            WHERE id = ?
        ");

        $stmt->bind_param("ii", $agent_id, $viewing_request_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to assign agent: " . $stmt->error);
        }
        $stmt->close();

        // Get agent details
        $stmt = $conn->prepare("
            SELECT first_name, last_name, email, phone
            FROM users
            WHERE id = ?
        ");
        $stmt->bind_param("i", $agent_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $agent = $result->fetch_assoc();
        $stmt->close();

        $agent_name = $agent['first_name'] . ' ' . $agent['last_name'];
        $agent_contact = $agent['phone'] . ' | ' . $agent['email'];

        // Get property details for notification
        $stmt = $conn->prepare("
            SELECT p.property_code, u.first_name, u.last_name, vr.scheduled_date
            FROM properties p
            JOIN viewing_requests vr ON p.id = vr.property_id
            JOIN users u ON vr.user_id = u.id
            WHERE vr.id = ?
        ");
        $stmt->bind_param("i", $viewing_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request_data = $result->fetch_assoc();
        $stmt->close();

        $requester_name = $request_data['first_name'] . ' ' . $request_data['last_name'];

        // Create notifications
        notifyViewingRequestConfirmed($viewing_request['user_id'], $request_data['property_code'], $request_data['scheduled_date'], $agent_name);
        notifyAgentAssigned($agent_id, $request_data['property_code'], $requester_name, $request_data['scheduled_date']);

        echo json_encode([
            'success' => true,
            'message' => 'Agent assigned successfully',
            'agent_name' => $agent_name,
            'agent_contact' => $agent_contact
        ]);
    }

    // ============================================================================
    // ACTION: Confirm Viewing Request
    // ============================================================================
    elseif ($action === 'confirm_viewing') {
        $viewing_request_id = intval($_POST['viewing_request_id'] ?? 0);
        $scheduled_date = $_POST['scheduled_date'] ?? '';
        $scheduled_time = $_POST['scheduled_time'] ?? '';

        if ($viewing_request_id <= 0) {
            throw new Exception("Invalid viewing request ID");
        }

        if (empty($scheduled_date) || empty($scheduled_time)) {
            throw new Exception("Scheduled date and time are required");
        }

        // Get viewing request details
        $stmt = $conn->prepare("
            SELECT id, user_id, property_id, agent_id
            FROM viewing_requests
            WHERE id = ?
        ");
        $stmt->bind_param("i", $viewing_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewing_request = $result->fetch_assoc();
        $stmt->close();

        if (!$viewing_request) {
            throw new Exception("Viewing request not found");
        }

        // Update viewing request
        $stmt = $conn->prepare("
            UPDATE viewing_requests
            SET 
                status = 'confirmed',
                scheduled_date = ?,
                scheduled_time = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("ssi", $scheduled_date, $scheduled_time, $viewing_request_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to confirm viewing: " . $stmt->error);
        }
        $stmt->close();

        // Get property details for notification
        $stmt = $conn->prepare("
            SELECT p.property_code, u.first_name, u.last_name
            FROM properties p
            JOIN viewing_requests vr ON p.id = vr.property_id
            JOIN users u ON vr.user_id = u.id
            WHERE vr.id = ?
        ");
        $stmt->bind_param("i", $viewing_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request_data = $result->fetch_assoc();
        $stmt->close();

        $requester_name = $request_data['first_name'] . ' ' . $request_data['last_name'];

        // Create notifications
        notifyViewingRequestConfirmed($viewing_request['user_id'], $request_data['property_code'], $scheduled_date . ' ' . $scheduled_time);

        // Create notification for agent if assigned
        if ($viewing_request['agent_id']) {
            notifyAgentAssigned($viewing_request['agent_id'], $request_data['property_code'], $requester_name, $scheduled_date . ' ' . $scheduled_time);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Viewing confirmed successfully',
            'scheduled_date' => $scheduled_date,
            'scheduled_time' => $scheduled_time
        ]);
    }

    // ============================================================================
    // ACTION: Decline Viewing Request
    // ============================================================================
    elseif ($action === 'decline_viewing') {
        $viewing_request_id = intval($_POST['viewing_request_id'] ?? 0);
        $decline_reason = $_POST['decline_reason'] ?? '';

        if ($viewing_request_id <= 0) {
            throw new Exception("Invalid viewing request ID");
        }

        // Get viewing request details
        $stmt = $conn->prepare("
            SELECT id, user_id, property_id, agent_id
            FROM viewing_requests
            WHERE id = ?
        ");
        $stmt->bind_param("i", $viewing_request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewing_request = $result->fetch_assoc();
        $stmt->close();

        if (!$viewing_request) {
            throw new Exception("Viewing request not found");
        }

        // Update viewing request
        $stmt = $conn->prepare("
            UPDATE viewing_requests
            SET 
                status = 'declined',
                admin_notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->bind_param("si", $decline_reason, $viewing_request_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to decline viewing: " . $stmt->error);
        }
        $stmt->close();

        // Get property details for notification
        $stmt = $conn->prepare("SELECT property_code FROM properties WHERE id = ?");
        $stmt->bind_param("i", $viewing_request['property_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $prop_data = $result->fetch_assoc();
        $stmt->close();

        // Create notification for client
        notifyViewingRequestDeclined($viewing_request['user_id'], $prop_data['property_code'], $decline_reason);

        echo json_encode([
            'success' => true,
            'message' => 'Viewing request declined successfully'
        ]);
    }

    // ============================================================================
    // Invalid action
    // ============================================================================
    else {
        throw new Exception("Invalid action: " . $action);
    }

    $conn->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Categorize property based on listing type
 */
function categorize_property($listing_type) {
    switch ($listing_type) {
        case 'sale':
        case 'buy':
            return 'buy';
        case 'rent':
            return 'lease';
        case 'NightlyFied':
            return 'NightlyFied';
        case 'student':
            return 'student_housing';
        default:
            return 'buy';
    }
}

/**
 * Calculate viewing fee based on number of bedrooms
 */
function calculate_viewing_fee($bedrooms) {
    $bedrooms = intval($bedrooms);
    if ($bedrooms <= 2) {
        return 1000.00;
    }
    if ($bedrooms <= 4) {
        return 1500.00;
    }
    return 2000.00;
}

/**
 * Create a notification in the database
 * DEPRECATED: Use notification_utils.php functions instead
 */
function create_notification(
    $conn,
    $user_id,
    $notification_type,
    $title,
    $message,
    $property_id = null,
    $priority = 'medium',
    $viewing_request_id = null,
    $agent_id = null
) {
    // This function is deprecated - use notification_utils.php instead
    return sendNotification($user_id, $notification_type, $title, $message, $priority, null, $property_id, $viewing_request_id);
}
?>
