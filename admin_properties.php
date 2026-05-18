<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';
require_once 'notifications.php';

// Handle actions
$action = $_GET['action'] ?? '';
$property_id = intval($_GET['id'] ?? 0);

// Get property details if editing
$property_data = null;
if(($action === 'verify' || $action === 'view') && $property_id) {
    $stmt = $conn->prepare("
        SELECT p.*, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name, u.email as seller_email, u.phone as seller_phone
        FROM properties p
        JOIN users u ON p.seller_id = u.id
        WHERE p.id = ?
    ");
    $stmt->bind_param("i", $property_id);
    $stmt->execute();
    $property_data = $stmt->get_result()->fetch_assoc();
}

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    switch($form_action) {
        case 'verify_property':
            $property_id = intval($_POST['property_id']);

            // Ensure verified properties have a transaction flag so they appear in Buy/Rent listings
            $check_stmt = $conn->prepare("SELECT for_sale, for_rent, for_lease FROM properties WHERE id = ?");
            $check_stmt->bind_param("i", $property_id);
            $check_stmt->execute();
            $flags = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            $update_flags = "";
            if ($flags['for_sale'] == 0 && $flags['for_rent'] == 0 && $flags['for_lease'] == 0) {
                $update_flags = ", for_sale = 1";
            }

            $stmt = $conn->prepare("UPDATE properties SET verification_status = 'verified', verified_by = ?, verified_at = NOW(){$update_flags} WHERE id = ?");
            $admin_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
            $stmt->bind_param("ii", $admin_user_id, $property_id);

            if($stmt->execute()) {
                logAdminAction('verify_property', "Verified property ID: $property_id", null, $property_id);

                $property_stmt = $conn->prepare("SELECT seller_id, property_type, location FROM properties WHERE id = ?");
                $property_stmt->bind_param("i", $property_id);
                $property_stmt->execute();
                $property_info = $property_stmt->get_result()->fetch_assoc();
                $property_stmt->close();

                if ($property_info) {
                    $seller_id = intval($property_info['seller_id']);
                    $property_label = trim($property_info['property_type'] . ' in ' . $property_info['location']);
                    $notification_title = "Property Verified";
                    $notification_message = "Your listing has been verified and published: $property_label. It is now visible to buyers and renters on the Walbrand marketplace.";
                    sendNotification($seller_id, 'property_verified', $notification_title, $notification_message, $property_id, true);
                }

                $success = "Property verified successfully!";

                // Redirect to prevent resubmission
                header("Location: admin_properties.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Failed to verify property";
            }
            break;
            
        case 'update_occupancy_status':
            $property_id = intval($_POST['property_id']);
            $new_status = trim($_POST['occupancy_status'] ?? 'available');
            $allowed_statuses = ['available', 'reserved', 'occupied', 'maintenance'];

            if (!in_array($new_status, $allowed_statuses, true)) {
                $error = "Invalid occupancy status selected.";
                break;
            }

            $stmt = $conn->prepare("UPDATE properties SET occupancy_status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $property_id);
            
            if ($stmt->execute()) {
                logAdminAction('update_occupancy_status', "Updated occupancy status to $new_status", null, $property_id);
                $success = "Property occupancy status updated successfully!";
                header("Location: admin_properties.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Failed to update occupancy status";
            }
            break;
        case 'reject_property':
            $property_id = intval($_POST['property_id']);
            $reason = trim($_POST['rejection_reason'] ?? '');
            
            $stmt = $conn->prepare("UPDATE properties SET verification_status = 'rejected', rejection_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
            $admin_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
            $stmt->bind_param("sii", $reason, $admin_user_id, $property_id);
            
            if($stmt->execute()) {
                logAdminAction('reject_property', "Rejected property - Reason: $reason", null, $property_id);

                $property_stmt = $conn->prepare("SELECT seller_id, property_type, location FROM properties WHERE id = ?");
                $property_stmt->bind_param("i", $property_id);
                $property_stmt->execute();
                $property_info = $property_stmt->get_result()->fetch_assoc();
                $property_stmt->close();

                if ($property_info) {
                    $seller_id = intval($property_info['seller_id']);
                    $property_label = trim($property_info['property_type'] . ' in ' . $property_info['location']);
                    $notification_title = "Property Verification Rejected";
                    $notification_message = "Your listing has been rejected: $property_label. Reason: " . ($reason !== '' ? $reason : 'No feedback was provided.') . " Please review the details and resubmit your property with the required corrections.";
                    sendNotification($seller_id, 'property_rejected', $notification_title, $notification_message, $property_id, true);
                }

                $success = "Property rejected successfully!";
                
                header("Location: admin_properties.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Failed to reject property";
            }
            break;
            
        case 'change_property_status':
            $property_id = intval($_POST['property_id']);
            $new_status = trim($_POST['new_status']);
            $allowed_statuses = ['pending', 'verified', 'rejected'];

            if (!in_array($new_status, $allowed_statuses, true)) {
                $error = "Invalid status selected.";
                break;
            }

            // Get current status for logging
            $current_stmt = $conn->prepare("SELECT verification_status, property_type, location FROM properties WHERE id = ?");
            $current_stmt->bind_param("i", $property_id);
            $current_stmt->execute();
            $current_property = $current_stmt->get_result()->fetch_assoc();
            $current_stmt->close();

            if (!$current_property) {
                $error = "Property not found.";
                break;
            }

            $update_data = [];
            $update_flags = "";

            if ($new_status === 'verified' && $current_property['verification_status'] !== 'verified') {
                // Ensure verified properties have a transaction flag so they appear in Buy/Rent listings
                $check_stmt = $conn->prepare("SELECT for_sale, for_rent, for_lease FROM properties WHERE id = ?");
                $check_stmt->bind_param("i", $property_id);
                $check_stmt->execute();
                $flags = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();

                if ($flags['for_sale'] == 0 && $flags['for_rent'] == 0 && $flags['for_lease'] == 0) {
                    $update_flags = ", for_sale = 1";
                }
                $update_data = ", verified_by = ?, verified_at = NOW()";
            } elseif ($new_status === 'rejected' && $current_property['verification_status'] !== 'rejected') {
                $update_data = ", rejected_by = ?, rejected_at = NOW()";
            }

            $stmt = $conn->prepare("UPDATE properties SET verification_status = ?{$update_data}{$update_flags} WHERE id = ?");
            
            if ($new_status === 'verified' || $new_status === 'rejected') {
                $admin_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
                $stmt->bind_param("sii", $new_status, $admin_user_id, $property_id);
            } else {
                $stmt->bind_param("si", $new_status, $property_id);
            }

            if ($stmt->execute()) {
                logAdminAction('change_property_status', "Changed status from {$current_property['verification_status']} to {$new_status}", null, $property_id);

                // Send notifications for status changes
                if ($new_status === 'verified') {
                    $property_stmt = $conn->prepare("SELECT seller_id FROM properties WHERE id = ?");
                    $property_stmt->bind_param("i", $property_id);
                    $property_stmt->execute();
                    $property_info = $property_stmt->get_result()->fetch_assoc();
                    $property_stmt->close();

                    if ($property_info) {
                        $seller_id = intval($property_info['seller_id']);
                        $property_label = trim($current_property['property_type'] . ' in ' . $current_property['location']);
                        $notification_title = "Property Re-verified";
                        $notification_message = "Your listing has been re-verified and is now visible to buyers and renters on the Walbrand marketplace: $property_label.";
                        sendNotification($seller_id, 'property_verified', $notification_title, $notification_message, $property_id, true);
                    }
                } elseif ($new_status === 'rejected') {
                    $property_stmt = $conn->prepare("SELECT seller_id FROM properties WHERE id = ?");
                    $property_stmt->bind_param("i", $property_id);
                    $property_stmt->execute();
                    $property_info = $property_stmt->get_result()->fetch_assoc();
                    $property_stmt->close();

                    if ($property_info) {
                        $seller_id = intval($property_info['seller_id']);
                        $property_label = trim($current_property['property_type'] . ' in ' . $current_property['location']);
                        $notification_title = "Property Verification Changed";
                        $notification_message = "Your listing status has been changed to rejected: $property_label. Please review the details and contact support if you have questions.";
                        sendNotification($seller_id, 'property_rejected', $notification_title, $notification_message, $property_id, true);
                    }
                }

                $success = "Property status changed to " . ucfirst($new_status) . " successfully!";
                echo "success"; // For AJAX response
                exit();
            } else {
                $error = "Failed to change property status";
            }
            break;
            
        case 'delete_property':
            $property_id = intval($_POST['property_id']);
            
            // Get property details for logging
            $property_stmt = $conn->prepare("SELECT p.property_type, p.location, u.email FROM properties p LEFT JOIN users u ON p.seller_id = u.id WHERE p.id = ?");
            $property_stmt->bind_param("i", $property_id);
            $property_stmt->execute();
            $property_info = $property_stmt->get_result()->fetch_assoc();
            $property_stmt->close();
            
            // Delete property images first
            $conn->query("DELETE FROM property_images WHERE property_id = $property_id");
            
            // Delete the property
            $stmt = $conn->prepare("DELETE FROM properties WHERE id = ?");
            $stmt->bind_param("i", $property_id);
            
            if($stmt->execute()) {
                logAdminAction('delete_property', "Deleted property: {$property_info['property_type']} in {$property_info['location']} (Seller: {$property_info['email']})", null, $property_id);
                $success = "Property deleted successfully!";
                
                header("Location: admin_properties.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Failed to delete property";
            }
            break;

        case 'assign_inquiry':
            $inquiry_id = intval($_POST['inquiry_id']);
            $agent_id = intval($_POST['agent_id']);
            $assignment_notes = trim($_POST['assignment_notes'] ?? '');
            $admin_id = $_SESSION['admin_id'];

            if ($agent_id <= 0) {
                $error = 'Please select a valid agent to assign this request.';
                break;
            }

            $inquiry_stmt = $conn->prepare("SELECT * FROM property_inquiries WHERE id = ?");
            $inquiry_stmt->bind_param("i", $inquiry_id);
            $inquiry_stmt->execute();
            $inquiry_result = $inquiry_stmt->get_result();
            $inquiry = $inquiry_result->fetch_assoc();
            $inquiry_stmt->close();

            if (!$inquiry) {
                $error = 'Property inquiry not found.';
                break;
            }

            $agent_check = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ? AND user_type = 'agent' AND is_active = 1");
            $agent_check->bind_param("i", $agent_id);
            $agent_check->execute();
            $agent_result = $agent_check->get_result();
            $agent = $agent_result->fetch_assoc();
            $agent_check->close();

            if (!$agent) {
                $error = 'Selected agent is not available. Please choose another agent.';
                break;
            }

            $update_stmt = $conn->prepare("UPDATE property_inquiries SET assigned_agent_id = ?, assigned_by_admin_id = ?, assignment_notes = ?, status = 'processing', assigned_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("iisi", $agent_id, $admin_id, $assignment_notes, $inquiry_id);

            if ($update_stmt->execute()) {
                require_once 'notifications.php';

                $agent_name = trim($agent['first_name'] . ' ' . $agent['last_name']);
                $admin_message = "A new property inquiry has been assigned to you.\n\n";
                $admin_message .= "Inquiry ID: #$inquiry_id\n";
                $admin_message .= "Client: {$inquiry['full_name']}\n";
                $admin_message .= "Location: {$inquiry['location']}\n";
                $admin_message .= "Property Type: {$inquiry['property_type']}\n";
                $admin_message .= "Transaction: {$inquiry['transaction_type']}\n";
                $admin_message .= "Urgency: {$inquiry['urgency_value']} {$inquiry['urgency_unit']}\n";
                if (!empty($inquiry['additional_requirements'])) {
                    $admin_message .= "Notes: {$inquiry['additional_requirements']}\n";
                }
                if (!empty($assignment_notes)) {
                    $admin_message .= "\nAssignment Notes: {$assignment_notes}\n";
                }
                sendNotification($agent_id, 'property_assignment', 'New Property Inquiry Assigned', $admin_message, $inquiry_id);

                $client_message = "Hello {$inquiry['full_name']},\n\nYour property inquiry (ID: #$inquiry_id) has been assigned to our agent $agent_name. They will review your requirements and be in touch shortly.\n\n";
                $client_message .= "Inquiry details:\n";
                $client_message .= "Location: {$inquiry['location']}\n";
                $client_message .= "Property Type: {$inquiry['property_type']}\n";
                $client_message .= "Transaction: {$inquiry['transaction_type']}\n";
                $client_message .= "Urgency: {$inquiry['urgency_value']} {$inquiry['urgency_unit']}\n";
                if (!empty($assignment_notes)) {
                    $client_message .= "Admin Notes: {$assignment_notes}\n";
                }
                $client_message .= "\nIf you have questions, contact us at +254113906162.";
                sendEmailNotification($inquiry['email'], $inquiry['full_name'], 'Your Property Request Has Been Assigned', $client_message, 'property_inquiry_assigned');

                logAdminAction('assign_property_inquiry', "Assigned inquiry #$inquiry_id to agent ID $agent_id", null, $inquiry_id);
                $success = "Property inquiry assigned to $agent_name successfully.";
                header("Location: admin_properties.php?success=" . urlencode($success) . "#property-inquiries");
                exit();
            } else {
                $error = 'Failed to assign the property inquiry. Please try again.';
            }
            break;
            
        case 'approve_viewing':
            $consultation_id = intval($_POST['consultation_id']);
            $notes = trim($_POST['admin_notes'] ?? '');
            
            $stmt = $conn->prepare("UPDATE consultations SET status = 'scheduled', admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $notes, $consultation_id);
            
            if ($stmt->execute()) {
                // Send notification to user
                require_once 'notifications.php';
                notifyViewingRequestApproved($consultation_id);
                
                logAdminAction('approve_viewing', "Approved viewing request ID: $consultation_id", null, $consultation_id);
                $success = "Viewing request approved successfully! User has been notified.";
                header("Location: admin_properties.php?success=" . urlencode($success) . "#viewing-requests");
                exit();
            } else {
                $error = "Failed to approve viewing request";
            }
            break;
            
        case 'reschedule_viewing':
            $consultation_id = intval($_POST['consultation_id']);
            $new_date = trim($_POST['new_date']);
            $new_time = trim($_POST['new_time']);
            $notes = trim($_POST['admin_notes'] ?? '');
            
            if (empty($new_date) || empty($new_time)) {
                $error = "New date and time are required for rescheduling";
                break;
            }
            
            $new_scheduled_date = $new_date . ' ' . $new_time;
            $stmt = $conn->prepare("UPDATE consultations SET scheduled_date = ?, admin_notes = ?, status = 'scheduled' WHERE id = ?");
            $stmt->bind_param("ssi", $new_scheduled_date, $notes, $consultation_id);
            
            if ($stmt->execute()) {
                // Send notification to user
                require_once 'notifications.php';
                notifyViewingRequestRescheduled($consultation_id, $new_scheduled_date, $notes);
                
                logAdminAction('reschedule_viewing', "Rescheduled viewing request ID: $consultation_id to $new_date $new_time", null, $consultation_id);
                $success = "Viewing request rescheduled successfully! User has been notified.";
                header("Location: admin_properties.php?success=" . urlencode($success) . "#viewing-requests");
                exit();
            } else {
                $error = "Failed to reschedule viewing request";
            }
            break;
            
        case 'reject_viewing':
            $consultation_id = intval($_POST['consultation_id']);
            $notes = trim($_POST['admin_notes'] ?? '');
            
            $stmt = $conn->prepare("UPDATE consultations SET status = 'cancelled', admin_notes = ? WHERE id = ?");
            $stmt->bind_param("si", $notes, $consultation_id);
            
            if ($stmt->execute()) {
                // Send notification to user
                require_once 'notifications.php';
                notifyViewingRequestRejected($consultation_id, $notes);
                
                logAdminAction('reject_viewing', "Rejected viewing request ID: $consultation_id", null, $consultation_id);
                $success = "Viewing request rejected successfully! User has been notified.";
                header("Location: admin_properties.php?success=" . urlencode($success) . "#viewing-requests");
                exit();
            } else {
                $error = "Failed to reject viewing request";
            }
            break;
    }
}

// Get viewing requests
$viewing_requests = [];
$stmt = $conn->prepare("
    SELECT c.*, 
           CONCAT_WS(' ', u.first_name, u.last_name) AS client_name,
           p.property_type, p.location, p.price
    FROM consultations c
    LEFT JOIN users u ON c.user_id = u.id
    LEFT JOIN properties p ON c.property_id = p.id
    WHERE c.consultation_type = 'property_viewing' AND c.status IN ('pending', 'scheduled')
    ORDER BY c.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $viewing_requests[] = $row;
}
$stmt->close();

// Get active agents for assignment
$agents = [];
$agent_stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'agent' AND is_active = 1 ORDER BY first_name, last_name");
$agent_stmt->execute();
$agent_result = $agent_stmt->get_result();
while ($row = $agent_result->fetch_assoc()) {
    $agents[] = $row;
}
$agent_stmt->close();

// Get property inquiries pending assignment or processing
$property_inquiries = [];
try {
    $inquiry_stmt = $conn->prepare("SELECT pi.*, CONCAT_WS(' ', a.first_name, a.last_name) AS agent_name, CONCAT_WS(' ', ad.first_name, ad.last_name) AS assigned_by_name
        FROM property_inquiries pi
        LEFT JOIN users a ON pi.assigned_agent_id = a.id
        LEFT JOIN users ad ON pi.assigned_by_admin_id = ad.id
        WHERE pi.status IN ('pending', 'processing')
        ORDER BY pi.created_at DESC");
    if ($inquiry_stmt) {
        $inquiry_stmt->execute();
        $inquiry_result = $inquiry_stmt->get_result();
        while ($row = $inquiry_result->fetch_assoc()) {
            $property_inquiries[] = $row;
        }
        $inquiry_stmt->close();
    }
} catch (Exception $e) {
    // Table might not exist yet - silently continue
    error_log("Property inquiries table not available: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Management - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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
            font-family: 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: var(--light-gray);
            padding: 2rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--primary-color);
        }

        .page-header h1 {
            margin: 0;
            color: var(--dark-color);
            font-size: 2rem;
        }

        .back-btn {
            background: var(--light-gray);
            padding: 0.8rem 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: var(--border-color);
            transform: translateX(-2px);
        }

        .alert {
            padding: 1.2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 5px solid;
        }

        .alert-success {
            background: #d1f4e9;
            color: #065f46;
            border-left-color: var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border-left-color: var(--danger);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.2rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(255, 123, 0, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .properties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .property-card {
            width: 100%;
            max-width: 500px;
            min-height: 500px;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all 0.3s;
            border: 1px solid var(--border-color);
        }

        .property-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }

        .property-image {
            width: 100%;
            height: 250px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .property-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .property-content {
            padding: 0.75rem;
            min-height: 360px;
            height: auto;
            overflow: visible;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .property-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.6rem;
        }

        .property-header h3 {
            margin: 0;
            color: var(--dark-color);
            flex: 1;
            font-size: 1.2rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
            margin-left: 0.5rem;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-verified {
            background: #d1f4e9;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .property-details {
            border-top: 1px solid var(--border-color);
            padding-top: 0.8rem;
            margin-bottom: 0.8rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 600;
            min-width: 140px;
        }

        .detail-value {
            color: var(--dark-color);
            font-weight: 500;
            flex: 1;
            text-align: right;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 1rem;
        }

        .modal.show {
            display: flex;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 100%;
            margin: auto;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--dark-color);
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.8rem;
            cursor: pointer;
            color: #999;
            transition: all 0.3s;
        }

        .close-btn:hover {
            color: var(--dark-color);
            transform: rotate(90deg);
        }

        .price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin: 0.5rem 0 1rem 0;
        }

        .seller-info {
            background: var(--light-gray);
            padding: 0.8rem;
            border-radius: 8px;
            margin: 1rem 0;
        }

        .seller-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.4rem;
        }

        .seller-name {
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.3rem;
        }

        .seller-contact {
            font-size: 0.9rem;
            color: #666;
        }

        .property-actions {
            display: flex;
            gap: 0.8rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.7rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            flex: 1;
            min-width: 110px;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-color);
            border: 1px solid var(--border-color);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-delete {
            background: #6c757d;
            color: white;
        }

        .btn-delete:hover {
            background: #5a6268;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            color: #999;
        }

        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 1.3rem;
        }

        .empty-state p {
            margin: 0;

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
            overflow-y: auto;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 600px;
            width: 100%;
            margin: 2rem auto;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 1rem;
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

        .property-details-modal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            background: var(--light-gray);
            padding: 1rem;
            border-radius: 8px;
        }

        .detail-item label {
            display: block;
            font-size: 0.8rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .detail-item span {
            display: block;
            font-weight: 700;
            color: var(--dark-color);
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .properties-grid {
                grid-template-columns: 1fr;
            }

            .property-details-modal {
                grid-template-columns: 1fr;
            }
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            background: white;
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .nav-tab {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .nav-tab:hover {
            background: var(--light-gray);
            color: var(--dark-color);
            transform: translateY(-2px);
        }

        .nav-tab.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 12px rgba(255, 123, 0, 0.3);
            transform: translateY(-2px);
        }

        /* Section Management */
        .section {
            display: none;
            opacity: 0;
            animation: fadeIn 0.4s ease-in forwards;
        }

        .section.active {
            display: block;
            opacity: 1;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid var(--primary-color);
        }

        .section-header h2 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.8rem;
        }

        .section-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .stat-badge {
            background: linear-gradient(135deg, #ff7b00, #5cfaff);
            color: white;
            padding: 0.6rem 1.2rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.85rem;
            white-space: nowrap;
            box-shadow: 0 2px 8px rgba(255, 123, 0, 0.2);
        }

        /* Viewing Requests Styles */
        .viewing-requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
        }

        .viewing-request-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .viewing-request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .request-header h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.1rem;
        }

        .request-status {
            display: inline-block;
            padding: 0.3rem 0.7rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #d1f4e9;
            color: #065f46;
        }

        .status-rejected {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .status-rescheduled {
            background: #dbeafe;
            color: #1e40af;
        }

        .request-details {
            margin-bottom: 1rem;
        }

        .request-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .request-label {
            color: #666;
            font-weight: 600;
        }

        .request-value {
            color: var(--dark-color);
            font-weight: 500;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .request-actions .btn {
            flex: 1;
            min-width: 100px;
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>🏠 Property Management</h1>
            <div style="display: flex; gap: 1rem;">
                <a href="index.php" class="back-btn" style="background: #5cfaff; color: #333;">Back to Website</a>
                <a href="admin_control_panel.php" class="back-btn">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Navigation Tabs -->
       

        <?php if(!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- PROPERTIES SECTION -->
        <div class="section active" id="properties">
            <?php
            // Get all properties
            $query = "
                SELECT p.*, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name, u.email as seller_email, u.phone as seller_phone, u.kyc_status as seller_kyc_status, CASE WHEN u.kyc_status = 'verified' THEN TRUE ELSE FALSE END as kyc_verified, u.id_front_path, u.id_back_path,
                (SELECT image_path FROM property_images WHERE property_id = p.id LIMIT 1) AS primary_image,
                (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as image_count
                FROM properties p
                JOIN users u ON p.seller_id = u.id
                ORDER BY p.created_at DESC
            ";
            $result = $conn->query($query);

            if($result->num_rows === 0) {
                echo '<p style="grid-column: 1/-1; text-align: center; color: #999; padding: 2rem;">No properties found</p>';
            }

            while($property = $result->fetch_assoc()):
            ?>
            <div class="property-card" data-status="<?= $property['verification_status'] ?>">
                <div class="property-image">
                    <?php if(!empty($property['primary_image'])): ?>
                        <img src="<?= htmlspecialchars($property['primary_image']) ?>" alt="<?= htmlspecialchars($property['property_type']) ?>">
                    <?php else: ?>
                        🏠
                    <?php endif; ?>
                </div>

                <div class="property-content">
                    <div class="property-header">
                        <h3><?= htmlspecialchars($property['property_type']) ?></h3>
                        <span class="status-badge status-<?= $property['verification_status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $property['verification_status'])) ?>
                        </span>
                    </div>

                    <div class="price">KES <?= number_format($property['price']) ?></div>

                    <div class="property-details">
                        <div class="detail-row">
                            <span class="detail-label">Location:</span>
                            <span class="detail-value"><?= htmlspecialchars($property['location']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Size:</span>
                            <span class="detail-value"><?= htmlspecialchars($property['size_sqm'] ?? 'N/A') ?> m²</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Bedrooms:</span>
                            <span class="detail-value"><?= intval($property['bedrooms']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Bathrooms:</span>
                            <span class="detail-value"><?= intval($property['bathrooms']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Images:</span>
                            <span class="detail-value"><?= intval($property['image_count']) ?> uploaded</span>
                        </div>
                    </div>

                    <div class="seller-info">
                        <div class="seller-label">Seller</div>
                        <div class="seller-name"><?= htmlspecialchars($property['seller_name']) ?></div>
                        <div class="seller-contact">
                            📧 <?= htmlspecialchars($property['seller_email']) ?>
                        </div>
                    </div>

                    <div class="property-actions">
                        <button class="btn btn-primary" onclick="openModal('property-detail-<?= $property['id'] ?>')">View Full Details</button>
                        <button class="btn btn-success" onclick="openModal('verify-<?= $property['id'] ?>')">✓ Verify</button>
                        <?php if($property['verification_status'] === 'verified'): ?>
                            <button class="btn btn-warning" onclick="changePropertyStatus(<?= $property['id'] ?>, 'pending')">⏸️ Suspend</button>
                        <?php endif; ?>
                        <button class="btn btn-danger" onclick="openModal('reject-<?= $property['id'] ?>')">✗ Reject</button>
                        <button class="btn btn-delete" onclick="openModal('delete-property-<?= $property['id'] ?>')">🗑️ Delete</button>
                    </div>
                </div>
            </div>

            <!-- PROPERTY DETAIL MODAL -->
            <div class="modal" id="property-detail-<?= $property['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2><?= htmlspecialchars($property['property_type']) ?> - <?= htmlspecialchars($property['location']) ?></h2>
                        <button class="close-btn" onclick="closeModal('property-detail-<?= $property['id'] ?>')">&times;</button>
                    </div>

                    <div class="property-details-modal">
                        <div class="detail-item">
                            <label>Property Type</label>
                            <span><?= htmlspecialchars($property['property_type']) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Offer Type</label>
                            <span><?= ucfirst(htmlspecialchars($property['transaction_type'] ?? 'sell')) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Occupancy</label>
                            <span><?= ucfirst(htmlspecialchars($property['occupancy_type'] ?? 'general')) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Price</label>
                            <span>KES <?= number_format($property['price']) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Location</label>
                            <span><?= htmlspecialchars($property['location']) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Size</label>
                            <span><?= htmlspecialchars($property['size_sqm'] ?? 'N/A') ?> m² (<?= round(($property['size_sqm'] ?? 0) * 10.7639) ?> sqft)</span>
                        </div>
                        <div class="detail-item">
                            <label>Bedrooms</label>
                            <span><?= intval($property['bedrooms']) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Bathrooms</label>
                            <span><?= intval($property['bathrooms']) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Verification Status</label>
                            <span><?= ucfirst(str_replace('_', ' ', $property['verification_status'])) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Property Status</label>
                            <span><?= ucfirst(htmlspecialchars($property['status'] ?? 'available')) ?></span>
                        </div>
                        <div class="detail-item">
                            <label>Listed Date</label>
                            <span><?= date('M d, Y', strtotime($property['created_at'])) ?></span>
                        </div>
                    </div>

                    <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-top: 0;">Adjust Occupancy Status</h3>
                        <form method="post" style="display: grid; gap: 10px;">
                            <input type="hidden" name="form_action" value="update_occupancy_status">
                            <input type="hidden" name="property_id" value="<?= intval($property['id']) ?>">
                            <label for="occupancy_status">Set Occupancy</label>
                            <select name="occupancy_status" id="occupancy_status" style="padding: 10px; border: 1px solid #ccc; border-radius: 6px;">
                                <?php $current_status = $property['status'] ?? 'available'; ?>
                                <option value="available" <?= $current_status === 'available' ? 'selected' : '' ?>>Available</option>
                                <option value="reserved" <?= $current_status === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                <option value="occupied" <?= $current_status === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                                <option value="maintenance" <?= $current_status === 'maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Update Occupancy</button>
                        </form>
                    </div>

                    <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-top: 0;">Seller Information</h3>
                        <p><strong>Name:</strong> <?= htmlspecialchars($property['seller_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($property['seller_email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($property['seller_phone']) ?></p>
                        <p><strong>KYC Status:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $property['seller_kyc_status'] ?? 'pending'))) ?>
                        <?php if (!empty($property['kyc_verified'])): ?>
                            <span style="color: #065F46; font-weight: bold;">(Verified)</span>
                        <?php endif; ?></p>
                        <?php if (!empty($property['id_front_path']) || !empty($property['id_back_path'])): ?>
                            <p><strong>KYC Files:</strong></p>
                            <?php if (!empty($property['id_front_path'])): ?>
                                <p><a href="<?= htmlspecialchars($property['id_front_path']) ?>" target="_blank">View ID Front</a></p>
                            <?php endif; ?>
                            <?php if (!empty($property['id_back_path'])): ?>
                                <p><a href="<?= htmlspecialchars($property['id_back_path']) ?>" target="_blank">View ID Back</a></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <p><strong>Description:</strong></p>
                        <p><?= nl2br(htmlspecialchars($property['description'])) ?></p>
                    </div>

                    <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h3 style="margin-top: 0;">Property Images</h3>
                        <div id="property-images-<?= $property['id'] ?>" class="property-images-gallery" style="display: flex; flex-wrap: wrap; gap: 1rem;"></div>
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            fetch('get_property_images.php?property_id=<?= $property['id'] ?>')
                                .then(res => res.json())
                                .then(files => {
                                    const gallery = document.getElementById('property-images-<?= $property['id'] ?>');
                                    if (files.length === 0) {
                                        gallery.innerHTML = '<span style="color: #888">No images or video files uploaded.</span>';
                                        return;
                                    }

                                    files.forEach(function(file) {
                                        const extension = file.split('.').pop().toLowerCase();
                                        if (['mp4','mov','avi','wmv','mkv'].includes(extension)) {
                                            const video = document.createElement('video');
                                            video.src = file;
                                            video.controls = true;
                                            video.style.maxWidth = '220px';
                                            video.style.maxHeight = '180px';
                                            video.style.borderRadius = '8px';
                                            video.style.border = '1px solid #eee';
                                            gallery.appendChild(video);
                                        } else {
                                            const img = document.createElement('img');
                                            img.src = file;
                                            img.alt = 'Property Image';
                                            img.style.maxWidth = '120px';
                                            img.style.maxHeight = '120px';
                                            img.style.borderRadius = '8px';
                                            img.style.border = '1px solid #eee';
                                            gallery.appendChild(img);
                                        }
                                    });
                                });
                        });
                        </script>
                    </div>

                    <button class="close-btn" style="width: 100%; padding: 1rem;" onclick="closeModal('property-detail-<?= $property['id'] ?>')">Close</button>
                </div>
            </div>

            <!-- VERIFY MODAL -->
            <div class="modal" id="verify-<?= $property['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Verify Property</h2>
                        <button class="close-btn" onclick="closeModal('verify-<?= $property['id'] ?>')">&times;</button>
                    </div>

                    <p>Are you sure you want to verify this property?</p>
                    <p><strong><?= htmlspecialchars($property['property_type']) ?> in <?= htmlspecialchars($property['location']) ?></strong></p>

                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="verify_property">
                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm Verification</button>
                    </form>
                </div>
            </div>

            <!-- REJECT MODAL -->
            <div class="modal" id="reject-<?= $property['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Reject Property</h2>
                        <button class="close-btn" onclick="closeModal('reject-<?= $property['id'] ?>')">&times;</button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="form_action" value="reject_property">
                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">

                        <div class="form-group">
                            <label for="rejection_reason_<?= $property['id'] ?>">Reason for Rejection</label>
                            <textarea id="rejection_reason_<?= $property['id'] ?>" name="rejection_reason" required placeholder="Explain why this property is being rejected..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 1rem;">Reject Property</button>
                    </form>
                </div>
            </div>
            
            <!-- DELETE PROPERTY MODAL -->
            <div class="modal" id="delete-property-<?= $property['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>🗑️ Delete Property</h2>
                        <button class="close-btn" onclick="closeModal('delete-property-<?= $property['id'] ?>')">&times;</button>
                    </div>

                    <div style="text-align: center; padding: 20px;">
                        <p style="color: #666; margin-bottom: 20px;">Are you sure you want to permanently delete this property? This action cannot be undone.</p>
                        <p style="font-weight: bold; color: #333; margin-bottom: 20px;">
                            Property: <?= htmlspecialchars($property['property_type']) ?> in <?= htmlspecialchars($property['location']) ?><br>
                            Seller: <?= htmlspecialchars($property['seller_name']) ?>
                        </p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="form_action" value="delete_property">
                        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">

                        <div style="display: flex; gap: 10px;">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('delete-property-<?= $property['id'] ?>')" style="flex: 1;">Cancel</button>
                            <button type="submit" class="btn btn-danger" style="flex: 1;">🗑️ Delete Permanently</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- PROPERTY INQUIRIES SECTION -->
    <div class="section" id="property-inquiries">
        <div class="section-header">
            <h2>Property Inquiries</h2>
            <div class="section-stats">
                <span class="stat-badge"><?= count($property_inquiries) ?> Pending / In Progress</span>
            </div>
        </div>

        <?php if (count($property_inquiries) > 0): ?>
            <div class="properties-grid">
                <?php foreach ($property_inquiries as $inquiry): ?>
                    <div class="property-card">
                        <div class="property-header">
                            <h3>Inquiry #<?= $inquiry['id'] ?></h3>
                            <span class="status-badge status-<?= htmlspecialchars($inquiry['status']) ?>">
                                <?= ucfirst(htmlspecialchars($inquiry['status'])) ?>
                            </span>
                        </div>

                        <div class="property-details">
                            <div class="detail-row">
                                <span class="detail-label">Client:</span>
                                <span class="detail-value"><?= htmlspecialchars($inquiry['full_name']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?= htmlspecialchars($inquiry['email']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?= htmlspecialchars($inquiry['contact']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?= htmlspecialchars($inquiry['location']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Category:</span>
                                <span class="detail-value"><?= ucfirst(htmlspecialchars(str_replace('_', ' ', $inquiry['category']))) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Type:</span>
                                <span class="detail-value"><?= ucfirst(htmlspecialchars($inquiry['transaction_type'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Property Type:</span>
                                <span class="detail-value"><?= htmlspecialchars($inquiry['property_type']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Urgency:</span>
                                <span class="detail-value"><?= intval($inquiry['urgency_value']) ?> <?= htmlspecialchars($inquiry['urgency_unit']) ?></span>
                            </div>
                            <?php if (!empty($inquiry['additional_requirements'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Requirements:</span>
                                    <span class="detail-value"><?= nl2br(htmlspecialchars($inquiry['additional_requirements'])) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($inquiry['agent_name'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Assigned Agent:</span>
                                    <span class="detail-value"><?= htmlspecialchars($inquiry['agent_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($inquiry['assigned_by_name'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Assigned By:</span>
                                    <span class="detail-value"><?= htmlspecialchars($inquiry['assigned_by_name']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($inquiry['assigned_at'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Assigned At:</span>
                                    <span class="detail-value"><?= date('M j, Y g:i A', strtotime($inquiry['assigned_at'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="property-actions">
                            <button class="btn btn-primary" onclick="openModal('assign-inquiry-<?= $inquiry['id'] ?>')">Assign Agent</button>
                        </div>
                    </div>

                    <div class="modal" id="assign-inquiry-<?= $inquiry['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Assign Inquiry #<?= $inquiry['id'] ?></h2>
                                <button class="close-btn" onclick="closeModal('assign-inquiry-<?= $inquiry['id'] ?>')">&times;</button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="form_action" value="assign_inquiry">
                                <input type="hidden" name="inquiry_id" value="<?= $inquiry['id'] ?>">

                                <div class="form-group">
                                    <label>Select Agent</label>
                                    <select name="agent_id" required>
                                        <option value="">Choose an agent</option>
                                        <?php foreach ($agents as $agentOption): ?>
                                            <option value="<?= $agentOption['id'] ?>" <?= $inquiry['assigned_agent_id'] == $agentOption['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($agentOption['first_name'] . ' ' . $agentOption['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Assignment Notes</label>
                                    <textarea name="assignment_notes" placeholder="Optional notes for the agent or client..."><?= htmlspecialchars($inquiry['assignment_notes']) ?></textarea>
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Assign Inquiry</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No pending or processing inquiries</h3>
                <p>Property inquiry requests are assigned to agents from here once reviewed.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- VIEWING REQUESTS SECTION -->
    <div class="section" id="viewing-requests">
        <div class="section-header">
            <h2>Viewing Requests</h2>
            <div class="section-stats">
                <span class="stat-badge"><?= count($viewing_requests) ?> Pending</span>
            </div>
        </div>

        <?php if (count($viewing_requests) > 0): ?>
            <div class="properties-grid">
                <?php foreach ($viewing_requests as $request): ?>
                    <div class="property-card">
                        <div class="property-header">
                            <h3>Viewing Request #<?= $request['id'] ?></h3>
                            <span class="status-badge status-<?= $request['status'] ?>">
                                <?= ucfirst($request['status']) ?>
                            </span>
                        </div>

                        <div class="property-details">
                            <div class="detail-row">
                                <span class="detail-label">Client:</span>
                                <span class="detail-value"><?= htmlspecialchars($request['client_name'] ?: 'Guest') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?= htmlspecialchars($request['email']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?= htmlspecialchars($request['contact_number']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Requested Date/Time:</span>
                                <span class="detail-value">
                                    <?= date('M j, Y', strtotime($request['scheduled_date'])) ?> at 
                                    <?= date('g:i A', strtotime($request['scheduled_date'])) ?>
                                </span>
                            </div>
                            <?php if ($request['property_type']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Property:</span>
                                    <span class="detail-value">
                                        <?= htmlspecialchars($request['property_type']) ?> in <?= htmlspecialchars($request['location']) ?> 
                                        (KES <?= number_format($request['price']) ?>)
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($request['issue_description']): ?>
                                <div class="detail-row">
                                    <span class="detail-label">Message:</span>
                                    <span class="detail-value"><?= htmlspecialchars($request['issue_description']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <span class="detail-label">Submitted:</span>
                                <span class="detail-value"><?= date('M j, Y g:i A', strtotime($request['created_at'])) ?></span>
                            </div>
                        </div>

                        <div class="property-actions">
                            <!-- APPROVE MODAL -->
                            <button class="btn btn-success" onclick="openModal('approve-viewing-<?= $request['id'] ?>')">✓ Approve</button>
                            
                            <!-- RESCHEDULE MODAL -->
                            <button class="btn btn-warning" onclick="openModal('reschedule-viewing-<?= $request['id'] ?>')">Reschedule</button>
                            
                            <!-- REJECT MODAL -->
                            <button class="btn btn-danger" onclick="openModal('reject-viewing-<?= $request['id'] ?>')">✗ Reject</button>
                        </div>
                    </div>

                    <!-- APPROVE VIEWING MODAL -->
                    <div class="modal" id="approve-viewing-<?= $request['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Approve Viewing Request</h2>
                                <button class="close-btn" onclick="closeModal('approve-viewing-<?= $request['id'] ?>')">&times;</button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="form_action" value="approve_viewing">
                                <input type="hidden" name="consultation_id" value="<?= $request['id'] ?>">

                                <div class="form-group">
                                    <label>Admin Notes (Optional)</label>
                                    <textarea name="admin_notes" placeholder="Add any notes for the client..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Approve Viewing</button>
                            </form>
                        </div>
                    </div>

                    <!-- RESCHEDULE VIEWING MODAL -->
                    <div class="modal" id="reschedule-viewing-<?= $request['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Reschedule Viewing Request</h2>
                                <button class="close-btn" onclick="closeModal('reschedule-viewing-<?= $request['id'] ?>')">&times;</button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="form_action" value="reschedule_viewing">
                                <input type="hidden" name="consultation_id" value="<?= $request['id'] ?>">

                                <div class="form-group">
                                    <label for="new_date_<?= $request['id'] ?>">New Date *</label>
                                    <input type="date" id="new_date_<?= $request['id'] ?>" name="new_date" required 
                                           min="<?= date('Y-m-d') ?>">
                                </div>

                                <div class="form-group">
                                    <label for="new_time_<?= $request['id'] ?>">New Time *</label>
                                    <input type="time" id="new_time_<?= $request['id'] ?>" name="new_time" required>
                                </div>

                                <div class="form-group">
                                    <label>Admin Notes</label>
                                    <textarea name="admin_notes" placeholder="Explain the rescheduling reason..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-warning" style="width: 100%; padding: 1rem;">Reschedule Viewing</button>
                            </form>
                        </div>
                    </div>

                    <!-- REJECT VIEWING MODAL -->
                    <div class="modal" id="reject-viewing-<?= $request['id'] ?>">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Reject Viewing Request</h2>
                                <button class="close-btn" onclick="closeModal('reject-viewing-<?= $request['id'] ?>')">&times;</button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="form_action" value="reject_viewing">
                                <input type="hidden" name="consultation_id" value="<?= $request['id'] ?>">

                                <div class="form-group">
                                    <label>Reason for Rejection</label>
                                    <textarea name="admin_notes" required placeholder="Explain why this viewing request is being rejected..."></textarea>
                                </div>

                                <button type="submit" class="btn btn-danger" style="width: 100%; padding: 1rem;">Reject Viewing Request</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No Pending Viewing Requests</h3>
                <p>All viewing requests have been processed.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });

        // Section switching functionality
        function showSection(sectionId, tabButton) {
            // Hide all sections
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => {
                section.classList.remove('active');
            });

            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.nav-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected section
            document.getElementById(sectionId).classList.add('active');

            // Add active class to clicked tab
            if (tabButton) {
                tabButton.classList.add('active');
            }
        }

        // Change property verification status
        function changePropertyStatus(propertyId, newStatus) {
            if (!confirm(`Are you sure you want to change this property's status to "${newStatus}"?`)) {
                return;
            }

            // Create form data
            const formData = new FormData();
            formData.append('form_action', 'change_property_status');
            formData.append('property_id', propertyId);
            formData.append('new_status', newStatus);

            // Send request
            fetch('admin_properties.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert('Property status updated successfully!');
                    location.reload();
                } else {
                    alert('Failed to update property status. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>
