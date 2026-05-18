<?php
/**
 * Admin Viewing Requests Management
 * Manage viewing requests, assign agents, and track payments
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */


// Initialize counts to avoid undefined variable warnings
$pending_count = 0;
$scheduled_count = 0;
$completed_count = 0;
$cancelled_count = 0;

require_once 'admin_auth.php';
require_once __DIR__ . '/notification_utils.php';

// Admin auth guard is handled by admin_auth.php

// Ensure variables are always arrays to avoid undefined errors
$status_counts = [];
$viewing_requests = [];
$message = null;
$error = null;
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    if (function_exists('ensure_viewing_requests_table_exists')) {
        ensure_viewing_requests_table_exists($conn);
    }

    // Get filter parameters
    $tab = $_GET['tab'] ?? 'pending';
    $allowed_tabs = ['pending', 'approved', 'rejected', 'completed'];
    if (!in_array($tab, $allowed_tabs)) {
        $tab = 'pending';
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tab']) && in_array($_POST['tab'], $allowed_tabs)) {
        $tab = $_POST['tab'];
    }

    $status_filter = $tab;

    $sort_by = $_GET['sort'] ?? 'created_at';
    $sort_order = $_GET['order'] ?? 'DESC';
    $search_query = trim($_GET['search'] ?? '');

    // Validate sort parameters
    $allowed_sort = ['created_at', 'requested_date', 'status'];
    $allowed_order = ['ASC', 'DESC'];

    if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';
    if (!in_array($sort_order, $allowed_order)) $sort_order = 'DESC';

    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20; // Show 20 requests per page
    $offset = ($page - 1) * $per_page;

    // Handle success messages from redirects
    if (isset($_GET['success'])) {
        $message = $_GET['success'];
    }

    // Build optional search filter
    $search_condition = '';
    $search_types = '';
    $search_params = [];
    if ($search_query !== '') {
        $search_condition = " AND (
            p.property_code LIKE ? OR
            p.location LIKE ? OR
            COALESCE(vr.full_name, CONCAT(u.first_name, ' ', u.last_name)) LIKE ? OR
            COALESCE(vr.email, u.email) LIKE ? OR
            COALESCE(vr.contact_number, u.phone) LIKE ?
        )";
        $search_types = 'sssss';
        $search_term = "%{$search_query}%";
        $search_params = [$search_term, $search_term, $search_term, $search_term, $search_term];
    }

    function bind_stmt_params($stmt, $types, array &$values) {
        $bind = [];
        $bind[] = $types;
        foreach ($values as $key => $value) {
            $bind[] = &$values[$key];
        }
        return call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    // Get total count for pagination
    $count_sql = "
        SELECT COUNT(*) as total
        FROM viewing_requests vr
        LEFT JOIN properties p ON vr.property_id = p.id
        LEFT JOIN users u ON vr.user_id = u.id
        WHERE vr.status = ? {$search_condition}
    ";
    $count_stmt = $conn->prepare($count_sql);
    if (!empty($search_params)) {
        $count_binding_values = array_merge([$status_filter], $search_params);
        bind_stmt_params($count_stmt, "s{$search_types}", $count_binding_values);
    } else {
        $count_stmt->bind_param("s", $status_filter);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_count = $count_result->fetch_assoc()['total'];
    $total_pages = max(1, ceil($total_count / $per_page));
    $count_stmt->close();

    // Get viewing requests with property and user info
    $query = "
        SELECT
            vr.id,
            vr.property_id,
            vr.user_id,
            vr.requested_date,
            vr.requested_time,
            vr.contact_number,
            vr.additional_notes,
            vr.payment_reference,
            vr.payment_screenshot_path,
            vr.fee_paid,
            vr.viewing_fee,
            vr.terms_accepted,
            vr.status,
            vr.approved_by,
            vr.approved_at,
            vr.created_at,
            COALESCE(p.property_code, p.property_type, p.location) AS title,
            p.property_code,
            p.location,
            p.bedrooms,
            p.price,
            p.seller_id,
            COALESCE(vr.full_name, CONCAT(u.first_name, ' ', u.last_name)) AS user_full_name,
            COALESCE(vr.email, u.email) AS user_email,
            COALESCE(vr.contact_number, u.phone) AS user_phone,
            a.first_name as approved_first_name,
            a.last_name as approved_last_name
        FROM viewing_requests vr
        LEFT JOIN properties p ON vr.property_id = p.id
        LEFT JOIN users u ON vr.user_id = u.id
        LEFT JOIN users a ON vr.approved_by = a.id
        WHERE vr.status = ? {$search_condition}
        ORDER BY vr.{$sort_by} {$sort_order}
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($query);
    if (!empty($search_params)) {
        $query_binding_values = array_merge([$status_filter], $search_params, [$per_page, $offset]);
        bind_stmt_params($stmt, "s{$search_types}ii", $query_binding_values);
    } else {
        $stmt->bind_param("sii", $status_filter, $per_page, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $viewing_requests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $baseQueryParams = [
        'tab' => $tab,
        'search' => $search_query,
        'sort' => $sort_by,
        'order' => $sort_order,
    ];

    // Get count by status for tabs
    $status_counts = [];
    $statuses = ['pending', 'approved', 'rejected', 'completed'];
    foreach ($statuses as $status) {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM viewing_requests WHERE status = ?");
        $count_stmt->bind_param("s", $status);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $status_counts[$status] = $count_row['total'];
        $count_stmt->close();
    }

    $latest_pending_request = null;
    if (!empty($status_counts['pending'])) {
        $latest_stmt = $conn->prepare(
            "SELECT vr.id, vr.requested_date, vr.requested_time, vr.contact_number, vr.additional_notes, CONCAT(u.first_name, ' ', u.last_name) AS user_full_name, u.email AS user_email, p.property_code, p.location
             FROM viewing_requests vr
             LEFT JOIN users u ON vr.user_id = u.id
             LEFT JOIN properties p ON vr.property_id = p.id
             WHERE vr.status = 'pending'
             ORDER BY vr.created_at DESC
             LIMIT 1"
        );
        $latest_stmt->execute();
        $latest_result = $latest_stmt->get_result();
        $latest_pending_request = $latest_result->fetch_assoc();
        $latest_stmt->close();
    }

    // Get list of agents for assignment
    $agents_query = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        WHERE u.user_type = 'agent' AND u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ";
    $agents_result = $conn->query($agents_query);
    $agents = $agents_result->fetch_all(MYSQLI_ASSOC);

    // Handle admin actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $consultation_id = intval($_POST['consultation_id'] ?? 0);

        if ($consultation_id <= 0) {
            $error = "Invalid viewing request ID";
        } else {
            try {
                // Get viewing request details
                $stmt = $conn->prepare("
                    SELECT vr.*, p.property_code, p.location, p.seller_id, p.category,
                           CONCAT(u.first_name, ' ', u.last_name) AS client_name, u.email AS client_email
                    FROM viewing_requests vr
                    LEFT JOIN properties p ON vr.property_id = p.id
                    LEFT JOIN users u ON vr.user_id = u.id
                    WHERE vr.id = ?
                ");
                $stmt->bind_param("i", $consultation_id);
                $stmt->execute();
                $request = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if (!$request) {
                    throw new Exception("Viewing request not found");
                }

                $admin_id = $_SESSION['admin_id'];
                $admin_name = $_SESSION['admin_name'] ?? 'Admin';

                switch ($action) {
                    case 'approve_viewing':
                        $admin_notes = $_POST['admin_notes'] ?? '';

                        if (!$request['fee_paid']) {
                            throw new Exception("Payment must be verified before approving this viewing request.");
                        }

                        $stmt = $conn->prepare(" 
                            UPDATE viewing_requests
                            SET status = 'approved', approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ii", $admin_id, $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Notify client
                        notifyViewingRequestApproved($consultation_id, $request['user_id'], $request['property_code'], $request['location'], $admin_notes);

                        // Notify agent/seller
                        if ($request['seller_id']) {
                            notifyAgentViewingRequestApproved($consultation_id, $request['seller_id'], $request['client_name'], $request['property_code'], $request['location']);
                        }

                        $success = "Viewing request approved successfully";
                        break;

                    case 'reject_viewing':
                        $rejection_reason = $_POST['rejection_reason'] ?? '';

                        $stmt = $conn->prepare("
                            UPDATE viewing_requests
                            SET status = 'rejected', approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ii", $admin_id, $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Notify client
                        notifyViewingRequestRejected($consultation_id, $request['user_id'], $request['property_code'], $request['location'], $rejection_reason);

                        // Notify agent/seller
                        if ($request['seller_id']) {
                            notifyAgentViewingRequestRejected($consultation_id, $request['seller_id'], $request['client_name'], $request['property_code'], $request['location']);
                        }

                        $success = "Viewing request rejected successfully";
                        break;

                    case 'reschedule_viewing':
                        $new_date = $_POST['new_date'] ?? '';
                        $new_time = $_POST['new_time'] ?? '';
                        $admin_notes = $_POST['admin_notes'] ?? '';

                        if (empty($new_date) || empty($new_time)) {
                            throw new Exception("New date and time are required");
                        }

                        $stmt = $conn->prepare("
                            UPDATE viewing_requests
                            SET requested_date = ?, requested_time = ?, approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ssii", $new_date, $new_time, $admin_id, $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Notify client
                        notifyViewingRequestRescheduled($consultation_id, $request['user_id'], $request['property_code'], $request['location'], $new_date, $new_time, $admin_notes);

                        // Notify agent/seller
                        if ($request['seller_id']) {
                            notifyAgentViewingRequestRescheduled($consultation_id, $request['seller_id'], $request['client_name'], $request['property_code'], $request['location'], $new_date, $new_time);
                        }

                        $success = "Viewing request rescheduled successfully";
                        break;

                    case 'complete_viewing':
                        // Update viewing request status
                        $stmt = $conn->prepare("
                            UPDATE viewing_requests
                            SET status = 'completed', approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ii", $admin_id, $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Only mark residential properties as sold, not NightlyFied properties
                        // NightlyFied properties can have multiple bookings
                        $property_category = strtolower($request['category'] ?? '');
                        if ($property_category !== 'NightlyFied') {
                            $stmt = $conn->prepare("
                                UPDATE properties
                                SET verification_status = 'sold'
                                WHERE id = ?
                            ");
                            $stmt->bind_param("i", $request['property_id']);
                            $stmt->execute();
                            $stmt->close();

                            if (!empty($request['seller_id'])) {
                                if (function_exists('ensure_agent_rewards_table_exists')) {
                                    ensure_agent_rewards_table_exists($conn);
                                }

                                $reward_amount = 500.00;
                                $reward_description = "Sold property reward for viewing request #{$consultation_id} on property {$request['property_code']} in {$request['location']}";
                                $reward_stmt = $conn->prepare("INSERT INTO agent_rewards (agent_id, property_id, viewing_request_id, amount, reward_type, description, created_at, updated_at)
                                    VALUES (?, ?, ?, ?, 'sold_property', ?, NOW(), NOW())
                                    ON DUPLICATE KEY UPDATE amount = VALUES(amount), description = VALUES(description), updated_at = NOW()");
                                $reward_stmt->bind_param("iiids", $request['seller_id'], $request['property_id'], $consultation_id, $reward_amount, $reward_description);
                                $reward_stmt->execute();
                                $reward_stmt->close();
                            }
                        }
                        $stmt = $conn->prepare(" 
                            INSERT INTO consultations (
                                property_id, user_id, consultation_type, scheduled_date,
                                description, status, created_at, completed_at
                            ) VALUES (?, ?, 'property_viewing', ?, 'Property viewing completed', 'completed', NOW(), NOW())
                            ON DUPLICATE KEY UPDATE status = 'completed', completed_at = NOW()
                        ");
                        $stmt->bind_param("iis", $request['property_id'], $request['user_id'], $scheduledDateTime);
                        $stmt->execute();
                        $stmt->close();

                        // Notify client
                        notifyViewingRequestCompleted($consultation_id, $request['user_id'], $request['property_code'], $request['location']);

                        // Notify agent/seller
                        if ($request['seller_id']) {
                            notifyAgentViewingRequestCompleted($consultation_id, $request['seller_id'], $request['client_name'], $request['property_code'], $request['location']);
                        }

                        // Record admin payment for the completed viewing request
                        $payment_reference = 'viewing_request_' . $consultation_id;
                        $check_payment_stmt = $conn->prepare("SELECT id FROM payments WHERE payment_reference = ? LIMIT 1");
                        if ($check_payment_stmt) {
                            $check_payment_stmt->bind_param('s', $payment_reference);
                            $check_payment_stmt->execute();
                            $check_payment_stmt->store_result();
                            $payment_exists = $check_payment_stmt->num_rows > 0;
                            $check_payment_stmt->close();
                        } else {
                            $payment_exists = false;
                        }

                        if (!$payment_exists) {
                            $payment_stmt = $conn->prepare(
                                "INSERT INTO payments (user_id, property_id, amount, payment_type, payment_method, status, payment_reference, created_at, completed_at) VALUES (?, ?, ?, 'consultation_fee', 'other', 'completed', ?, NOW(), NOW())"
                            );
                            if ($payment_stmt) {
                                $property_id = intval($request['property_id'] ?? 0);
                                $amount = 1000.00;
                                $payment_stmt->bind_param('iids', $request['user_id'], $property_id, $amount, $payment_reference);
                                $payment_stmt->execute();
                                $payment_stmt->close();
                            }
                        }

                        $success = "Viewing request marked as completed. Property has been delisted from active listings.";
                        break;

                    case 'delete_viewing':
                        // Soft delete by setting status to cancelled, or hard delete if needed
                        $stmt = $conn->prepare("DELETE FROM viewing_requests WHERE id = ?");
                        $stmt->bind_param("i", $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        $success = "Viewing request deleted successfully";
                        break;

                    case 'verify_payment':
                        $payment_notes = $_POST['payment_notes'] ?? '';

                        $stmt = $conn->prepare("
                            UPDATE viewing_requests
                            SET fee_paid = 1, payment_date = NOW(), approved_by = ?, approved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->bind_param("ii", $admin_id, $consultation_id);
                        $stmt->execute();
                        $stmt->close();

                        // Add admin notes if provided
                        if (!empty($payment_notes)) {
                            $stmt = $conn->prepare("
                                UPDATE viewing_requests
                                SET admin_notes = CONCAT(COALESCE(admin_notes, ''), '\n\nPayment verified: ', ?)
                                WHERE id = ?
                            ");
                            $stmt->bind_param("si", $payment_notes, $consultation_id);
                            $stmt->execute();
                            $stmt->close();
                        }

                        // Notify client that payment is verified
                        notifyPaymentVerified($consultation_id, $request['user_id'], $request['property_code'], $request['location']);

                        $success = "Payment verified successfully. The viewing request can now be approved.";
                        break;

                    default:
                        throw new Exception("Invalid action");
                }

                // Redirect to refresh the page
                header("Location: admin_viewing_requests.php?tab=$tab&success=" . urlencode($success));
                exit;

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viewing Requests Management - Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color:#eef4fb;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 2rem;
            border-bottom: 2px solid #eee;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color:#eef4fb;
            border-bottom-color:#eef4fb;
        }
        
        .tab-btn:hover {
            color:#eef4fb;
        }

        .page-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }

        .filter-bar {
            display: grid;
            grid-template-columns: repeat(4, minmax(180px, 1fr));
            gap: 1rem;
            width: 100%;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            background: #f9fafb;
        }

        .filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .table-responsive {
            overflow-x: auto;
            background: white;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .request-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .request-table th,
        .request-table td {
            padding: 1rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
            text-align: left;
        }

        .request-table thead th {
            background: #f8fafc;
            color: #111827;
            font-weight: 700;
        }

        .request-table tbody tr:hover {
            background: #f9fafb;
        }

        .request-meta {
            display: grid;
            gap: 0.35rem;
        }

        .meta-label {
            display: inline-block;
            color: #6b7280;
            font-size: 0.85rem;
        }

        .note-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            margin-top: 0.75rem;
            color: #334155;
        }

        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-completed {
            background: #dbf4ff;
            color: #0c4a6e;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .request-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            border-left: 4px solid#eef4fb;
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .request-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-scheduled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-item {
            font-size: 0.9rem;
        }
        
        .detail-label {
            color: #666;
            font-weight: bold;
        }
        
        .detail-value {
            color: #333;
            margin-top: 3px;
        }
        
        .request-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-approve {
            background: #28a745;
            color: white;
        }
        
        .btn-approve:hover {
            background: #218838;
        }
        
        .btn-reschedule {
            background: #007bff;
            color: white;
        }
        
        .btn-reschedule:hover {
            background: #0056b3;
        }
        
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        
        .btn-reject:hover {
            background: #c82333;
        }
        
        .btn-complete {
            background: #28a745;
            color: white;
        }
        
        .btn-complete:hover {
            background: #218838;
        }
        
        .btn-verify {
            background: #28a745;
            color: white;
        }
        
        .btn-verify:hover {
            background: #218838;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.65);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: block !important;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        .modal-header {
            font-size: 1.3rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .close-modal {
            background: #6c757d;
            color: white;
        }
        
        .payment-info {
            font-size: 0.9rem;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .payment-badge.paid {
            background: #d4edda;
            color: #155724;
        }
        
        .payment-badge.pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .payment-badge.unpaid {
            background: #f8d7da;
            color: #721c24;
        }
        
        .screenshot-link {
            color: #007bff;
            text-decoration: none;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .screenshot-link:hover {
            text-decoration: underline;
        }
        
        .close-modal:hover {
            background: #5a6268;
        }
        
        .pagination {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        
        .pagination a:hover {
            background:#eef4fb;
            color: white;
        }
        
        .pagination .active {
            background:#eef4fb;
            color: white;
            border-color:#eef4fb;
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .admin-header h1 {
            margin: 0;
            color: #333;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Property Viewing Requests Management</h1>
            <a href="admin_control_panel.php" class="back-btn">← Back to Admin Dashboard</a>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($latest_pending_request)): ?>
            <div class="alert alert-success">
                <strong>New viewing request:</strong>
                Request #<?= intval($latest_pending_request['id']) ?> from <?= htmlspecialchars($latest_pending_request['user_full_name'] ?? 'Unknown') ?>
                for property <?= htmlspecialchars($latest_pending_request['property_code'] ?? 'N/A') ?> in <?= htmlspecialchars($latest_pending_request['location'] ?? 'N/A') ?>
                on <?= htmlspecialchars(date('M d, Y', strtotime($latest_pending_request['requested_date']))) ?> at <?= htmlspecialchars($latest_pending_request['requested_time'] ?? 'N/A') ?>.
                Contact: <?= htmlspecialchars($latest_pending_request['contact_number'] ?? $latest_pending_request['user_email'] ?? 'N/A') ?>.
                <?php if (!empty($latest_pending_request['additional_notes'])): ?>
                    <div class="note-box" style="margin-top: 10px;"><strong>Client notes:</strong> <?= htmlspecialchars($latest_pending_request['additional_notes']) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Pending Requests</div>
                <div class="stat-value"><?= $status_counts['pending'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Approved Requests</div>
                <div class="stat-value"><?= $status_counts['approved'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Completed</div>
                <div class="stat-value" style="color: #10b981;"><?= $status_counts['completed'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Rejected Requests</div>
                <div class="stat-value" style="color: #ef4444;"><?= $status_counts['rejected'] ?? 0 ?></div>
            </div>
        </div>
        
        <div class="page-toolbar">
            <div class="tabs">
                <button class="tab-btn <?= $tab === 'pending' ? 'active' : '' ?>" onclick="location.href='admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => 'pending', 'page' => 1]))) ?>'">
                    📝 Pending (<?= $status_counts['pending'] ?? 0 ?>)
                </button>
                <button class="tab-btn <?= $tab === 'approved' ? 'active' : '' ?>" onclick="location.href='admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => 'approved', 'page' => 1]))) ?>'">
                    ✅ Approved (<?= $status_counts['approved'] ?? 0 ?>)
                </button>
                <button class="tab-btn <?= $tab === 'completed' ? 'active' : '' ?>" onclick="location.href='admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => 'completed', 'page' => 1]))) ?>'">
                    🏁 Completed (<?= $status_counts['completed'] ?? 0 ?>)
                </button>
                <button class="tab-btn <?= $tab === 'rejected' ? 'active' : '' ?>" onclick="location.href='admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => 'rejected', 'page' => 1]))) ?>'">
                    ❌ Rejected (<?= $status_counts['rejected'] ?? 0 ?>)
                </button>
            </div>

            <form class="filter-bar" method="GET">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                <input type="hidden" name="page" value="1">
                <div class="filter-group">
                    <label for="search">Search requests</label>
                    <input id="search" type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Search by property, client, email or location">
                </div>
                <div class="filter-group">
                    <label for="sort">Sort by</label>
                    <select id="sort" name="sort">
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Newest requests</option>
                        <option value="requested_date" <?= $sort_by === 'requested_date' ? 'selected' : '' ?>>Requested date</option>
                        <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="order">Order</label>
                    <select id="order" name="order">
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply filters</button>
                    <a href="admin_viewing_requests.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>

        <?php if (!empty($viewing_requests)): ?>
            <div class="table-responsive">
                <table class="request-table">
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Client</th>
                            <th>Property</th>
                            <th>Schedule</th>
                            <th>Contact</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewing_requests as $request): ?>
                            <tr>
                                <td>
                                    <div class="request-meta">
                                        <span class="meta-label">#<?= intval($request['id']) ?></span>
                                        <strong><?= htmlspecialchars($request['title'] ?? 'Property #' . $request['property_id']) ?></strong>
                                        <span class="meta-label">Code: <?= htmlspecialchars($request['property_code'] ?? '-') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-meta">
                                        <strong><?= htmlspecialchars($request['user_full_name'] ?? 'N/A') ?></strong>
                                        <span class="meta-label"><?= htmlspecialchars($request['user_email'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-meta">
                                        <span><?= htmlspecialchars($request['location'] ?? 'Unknown location') ?></span>
                                        <span class="meta-label"><?= intval($request['bedrooms']) ?> Beds • KES <?= number_format($request['price'] ?? 0) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-meta">
                                        <span><?= date('M d, Y', strtotime($request['requested_date'])) ?></span>
                                        <span class="meta-label"><?= htmlspecialchars($request['requested_time'] ?? 'N/A') ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="request-meta">
                                        <span><?= htmlspecialchars($request['user_phone'] ?? 'N/A') ?></span>
                                        <?php if (!empty($request['contact_number']) && $request['contact_number'] !== $request['user_phone']): ?>
                                            <span class="meta-label">Alt: <?= htmlspecialchars($request['contact_number']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="payment-info">
                                        <?php if (!empty($request['payment_screenshot_path'])): ?>
                                            <div class="payment-status">
                                                <span class="payment-badge <?= $request['fee_paid'] ? 'paid' : 'pending' ?>">
                                                    <?= $request['fee_paid'] ? '✓ Paid' : '⏳ Pending' ?>
                                                </span>
                                                <a href="<?= htmlspecialchars($request['payment_screenshot_path']) ?>" target="_blank" class="screenshot-link">View Screenshot</a>
                                            </div>
                                            <?php if (!empty($request['payment_reference'])): ?>
                                                <div class="meta-label">Ref: <?= htmlspecialchars($request['payment_reference']) ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="payment-badge unpaid">No Payment</span>
                                        <?php endif; ?>
                                        <div class="meta-label">Fee: KES <?= number_format($request['viewing_fee'] ?? 1000) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($request['status']) ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <?php if (!empty($request['payment_screenshot_path']) && !$request['fee_paid']): ?>
                                                <button type="button" class="action-btn btn-verify" onclick="openVerifyPaymentModal(<?= $request['id'] ?>)">Verify Payment</button>
                                            <?php endif; ?>
                                            <button type="button" class="action-btn btn-approve" onclick="openApproveModal(<?= $request['id'] ?>)">Approve</button>
                                            <button type="button" class="action-btn btn-reschedule" onclick="openRescheduleModal(<?= $request['id'] ?>)">Reschedule</button>
                                            <button type="button" class="action-btn btn-reject" onclick="openRejectModal(<?= $request['id'] ?>)">Reject</button>
                                            <button type="button" class="action-btn btn-delete" onclick="openDeleteModal(<?= $request['id'] ?>)">Delete</button>
                                        <?php elseif ($request['status'] === 'approved' || $request['status'] === 'scheduled'): ?>
                                            <button type="button" class="action-btn btn-complete" onclick="openCompleteModal(<?= $request['id'] ?>)">Complete</button>
                                            <button type="button" class="action-btn btn-reschedule" onclick="openRescheduleModal(<?= $request['id'] ?>)">Reschedule</button>
                                            <button type="button" class="action-btn btn-reject" onclick="openRejectModal(<?= $request['id'] ?>)">Cancel</button>
                                            <button type="button" class="action-btn btn-delete" onclick="openDeleteModal(<?= $request['id'] ?>)">Delete</button>
                                        <?php else: ?>
                                            <button type="button" class="action-btn btn-delete" onclick="openDeleteModal(<?= $request['id'] ?>)">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php if (!empty($request['additional_notes'])): ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="note-box">
                                            <strong>Client notes:</strong> <?= htmlspecialchars($request['additional_notes']) ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => $tab, 'page' => $page - 1]))) ?>">← Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?= $i ?></span>
                        <?php else: ?>
                            <a href="admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => $tab, 'page' => $i]))) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="admin_viewing_requests.php?<?= htmlspecialchars(http_build_query(array_merge($baseQueryParams, ['tab' => $tab, 'page' => $page + 1]))) ?>">Next →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="note-box" style="text-align: center;">
                No viewing requests found in this category.
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✅ Approve Viewing Request</div>
            <form method="POST">
                <input type="hidden" name="action" value="approve_viewing">
                <input type="hidden" name="consultation_id" id="approveConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="form-group">
                    <label>Admin Notes (Optional)</label>
                    <textarea name="admin_notes" placeholder="e.g., Please arrive 5 minutes early..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" style="background: #28a745; color: white;">Approve Request</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">📅 Suggest New Date/Time</div>
            <form method="POST">
                <input type="hidden" name="action" value="reschedule_viewing">
                <input type="hidden" name="consultation_id" id="rescheduleConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="form-group">
                    <label>New Date</label>
                    <input type="date" name="new_date" required>
                </div>
                
                <div class="form-group">
                    <label>New Time</label>
                    <input type="time" name="new_time" required>
                </div>
                
                <div class="form-group">
                    <label>Admin Notes</label>
                    <textarea name="admin_notes" placeholder="Reason for rescheduling..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('rescheduleModal')">Cancel</button>
                    <button type="submit" style="background: #007bff; color: white;">Update Schedule</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">❌ Reject/Cancel Viewing</div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_viewing">
                <input type="hidden" name="consultation_id" id="rejectConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="form-group">
                    <label>Rejection Reason (Required)</label>
                    <textarea name="rejection_reason" placeholder="Why are you rejecting this request?" required></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" style="background: #dc3545; color: white;">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Complete Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">✅ Mark Viewing as Completed</div>
            <p style="color: #666; margin-bottom: 20px;">Mark this viewing request as completed? The client will be notified and asked to provide feedback on the agent's service.</p>
            <form method="POST">
                <input type="hidden" name="action" value="complete_viewing">
                <input type="hidden" name="consultation_id" id="completeConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('completeModal')">Cancel</button>
                    <button type="submit" style="background: #28a745; color: white;">✅ Mark as Completed</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">🗑️ Delete Viewing Request</div>
            <p style="color: #666; margin-bottom: 20px;">Are you sure you want to permanently delete this viewing request? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="action" value="delete_viewing">
                <input type="hidden" name="consultation_id" id="deleteConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" style="background: #dc3545; color: white;">🗑️ Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Verify Payment Modal -->
    <div id="verifyPaymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">💰 Verify Payment</div>
            <p style="color: #666; margin-bottom: 20px;">Please verify the M-Pesa payment screenshot and mark the payment as confirmed.</p>
            <form method="POST">
                <input type="hidden" name="action" value="verify_payment">
                <input type="hidden" name="consultation_id" id="verifyPaymentConsultationId">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                
                <div class="form-group">
                    <label>Payment Notes (Optional)</label>
                    <textarea name="payment_notes" placeholder="e.g., Payment verified, transaction code matches..."></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="close-modal" onclick="closeModal('verifyPaymentModal')">Cancel</button>
                    <button type="submit" style="background: #28a745; color: white;">✅ Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openApproveModal(consultationId) {
            document.getElementById('approveConsultationId').value = consultationId;
            document.getElementById('approveModal').classList.add('active');
        }
        
        function openRescheduleModal(consultationId) {
            document.getElementById('rescheduleConsultationId').value = consultationId;
            document.getElementById('rescheduleModal').classList.add('active');
        }
        
        function openRejectModal(consultationId) {
            document.getElementById('rejectConsultationId').value = consultationId;
            document.getElementById('rejectModal').classList.add('active');
        }
        
        function openDeleteModal(consultationId) {
            const modal = document.getElementById('deleteModal');
            if (modal) {
                document.getElementById('deleteConsultationId').value = consultationId;
                modal.classList.add('active');
            }
        }

        function openCompleteModal(consultationId) {
            document.getElementById('completeConsultationId').value = consultationId;
            document.getElementById('completeModal').classList.add('active');
        }

        function openVerifyPaymentModal(consultationId) {
            document.getElementById('verifyPaymentConsultationId').value = consultationId;
            document.getElementById('verifyPaymentModal').classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Close modal when clicking outside or pressing Escape
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.active').forEach(modal => modal.classList.remove('active'));
            }
        });
    </script>
</body>
</html>