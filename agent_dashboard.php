<?php
require_once 'config.php';
require_once 'helpers.php';
require_once 'config_mover_system.php';

secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
$session_user_role = strtolower(trim($_SESSION['user_role'] ?? $_SESSION['admin_role'] ?? ''));

if ($user_id <= 0) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'agent_dashboard.php';
    header('Location: login.php');
    exit();
}

$user_stmt = $conn->prepare("SELECT user_type, first_name, last_name, email, phone, profile_picture FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc() ?: [];
$user_stmt->close();

$allowed_admin = in_array($session_user_role, ['admin', 'super_admin'], true);
$allowed_agent = in_array($user['user_type'] ?? '', ['agent', 'seller'], true);

if (!$allowed_admin && !$allowed_agent) {
    header('Location: user_dashboard.php');
    exit();
}

$user_name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
if (empty($user_name)) {
    $user_name = 'Agent';
}

$first_name = explode(' ', $user_name)[0];
$profile_picture = trim($user['profile_picture'] ?? '');
$avatar_initials = strtoupper(substr($user['first_name'] ?? 'A', 0, 1) . substr($user['last_name'] ?? 'G', 0, 1));
$currentPage = basename($_SERVER['PHP_SELF']);

$leads_list = [];
$properties_uploaded = 0;
$properties_successful = 0;
$properties_rejected = 0;
$clients_connected = 0;
$clients_happy = 0;
$clients_complained = 0;
$clients_failed_pick = 0;
$viewings_assigned = 0;
$viewings_completed = 0;
$viewings_pending = 0;
$viewings_expired = 0;
$delivery_completed = 0;
$delivery_incomplete = 0;
$delivery_rescheduled = 0;
$delivery_groups = [];
$tasks_completed = 0;
$tasks_incomplete = 0;
$tasks_pending = 0;
$tasks_by_type = [];
$messages = [];
$unread_message_count = 0;

ensure_consultations_table_exists($conn);

if ($conn) {
    $leads_list_stmt = $conn->prepare(
        "SELECT COALESCE(u.first_name, '') as name, u.email, u.phone, c.consultation_type, c.status, c.created_at FROM consultations c LEFT JOIN users u ON c.user_id = u.id JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? ORDER BY c.created_at DESC LIMIT 50"
    );
    if ($leads_list_stmt) {
        $leads_list_stmt->bind_param('i', $user_id);
        $leads_list_stmt->execute();
        $leads_result = $leads_list_stmt->get_result();
        while ($row = $leads_result->fetch_assoc()) {
            $leads_list[] = $row;
        }
        $leads_list_stmt->close();
    }

    $properties_uploaded_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM properties WHERE seller_id = ?");
    if ($properties_uploaded_stmt) {
        $properties_uploaded_stmt->bind_param('i', $user_id);
        $properties_uploaded_stmt->execute();
        $properties_uploaded = intval($properties_uploaded_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_uploaded_stmt->close();
    }

    $properties_successful_stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) AS total FROM properties p JOIN consultations c ON p.id = c.property_id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($properties_successful_stmt) {
        $properties_successful_stmt->bind_param('i', $user_id);
        $properties_successful_stmt->execute();
        $properties_successful = intval($properties_successful_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_successful_stmt->close();
    }

    $properties_rejected_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM properties WHERE seller_id = ? AND status = 'rejected'");
    if ($properties_rejected_stmt) {
        $properties_rejected_stmt->bind_param('i', $user_id);
        $properties_rejected_stmt->execute();
        $properties_rejected = intval($properties_rejected_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $properties_rejected_stmt->close();
    }

    $clients_connected_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($clients_connected_stmt) {
        $clients_connected_stmt->bind_param('i', $user_id);
        $clients_connected_stmt->execute();
        $clients_connected = intval($clients_connected_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_connected_stmt->close();
    }

    $clients_happy_stmt = $conn->prepare("SELECT COUNT(DISTINCT cf.client_id) AS total FROM client_feedback cf WHERE cf.agent_id = ? AND cf.feedback_type = 'positive'");
    if ($clients_happy_stmt) {
        $clients_happy_stmt->bind_param('i', $user_id);
        $clients_happy_stmt->execute();
        $clients_happy = intval($clients_happy_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_happy_stmt->close();
    }

    $clients_complained_stmt = $conn->prepare("SELECT COUNT(DISTINCT cf.client_id) AS total FROM client_feedback cf WHERE cf.agent_id = ? AND cf.feedback_type = 'negative'");
    if ($clients_complained_stmt) {
        $clients_complained_stmt->bind_param('i', $user_id);
        $clients_complained_stmt->execute();
        $clients_complained = intval($clients_complained_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_complained_stmt->close();
    }

    $clients_failed_pick_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'cancelled'");
    if ($clients_failed_pick_stmt) {
        $clients_failed_pick_stmt->bind_param('i', $user_id);
        $clients_failed_pick_stmt->execute();
        $clients_failed_pick = intval($clients_failed_pick_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_failed_pick_stmt->close();
    }

    $viewings_assigned_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing'");
    if ($viewings_assigned_stmt) {
        $viewings_assigned_stmt->bind_param('i', $user_id);
        $viewings_assigned_stmt->execute();
        $viewings_assigned = intval($viewings_assigned_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_assigned_stmt->close();
    }

    $viewings_completed_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status = 'completed'");
    if ($viewings_completed_stmt) {
        $viewings_completed_stmt->bind_param('i', $user_id);
        $viewings_completed_stmt->execute();
        $viewings_completed = intval($viewings_completed_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_completed_stmt->close();
    }

    $viewings_pending_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status IN ('pending','scheduled')");
    if ($viewings_pending_stmt) {
        $viewings_pending_stmt->bind_param('i', $user_id);
        $viewings_pending_stmt->execute();
        $viewings_pending = intval($viewings_pending_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_pending_stmt->close();
    }

    $viewings_expired_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status != 'completed' AND c.scheduled_date < CURDATE()");
    if ($viewings_expired_stmt) {
        $viewings_expired_stmt->bind_param('i', $user_id);
        $viewings_expired_stmt->execute();
        $viewings_expired = intval($viewings_expired_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewings_expired_stmt->close();
    }

    $digital_service_requests = 0;
    $digital_service_completed = 0;
    $digital_service_pending = 0;
    $digital_service_incomplete = 0;

    $digital_service_stmt = $conn->prepare(
        "SELECT c.status, COUNT(*) AS total FROM consultations c WHERE c.agent_id = ? AND c.consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation') GROUP BY c.status"
    );
    if ($digital_service_stmt) {
        $digital_service_stmt->bind_param('i', $user_id);
        $digital_service_stmt->execute();
        $digital_service_result = $digital_service_stmt->get_result();
        while ($row = $digital_service_result->fetch_assoc()) {
            if ($row['status'] === 'completed') {
                $digital_service_completed = intval($row['total']);
            } elseif (in_array($row['status'], ['pending', 'approved', 'scheduled'], true)) {
                $digital_service_pending += intval($row['total']);
            } else {
                $digital_service_incomplete += intval($row['total']);
            }
            $digital_service_requests += intval($row['total']);
        }
        $digital_service_stmt->close();
    }

    // Calculate total completed services from both Digital Installations and Viewing Requests
    $total_completed_services = $digital_service_completed + $viewings_completed;

    // Calculate agent payouts from both Digital Installations and Viewing Requests
    $digital_installation_agent_payouts = 0;
    $viewing_request_agent_payouts = 0;
    $total_agent_payouts = 0;

    $digital_payout_stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(service_fee, 0)), 0) as total FROM consultations WHERE agent_id = ? AND status = 'completed' AND consultation_type IN ('wifi_distribution', 'cctv_installation', 'alexa_installation')");
    if ($digital_payout_stmt) {
        $digital_payout_stmt->bind_param('i', $user_id);
        $digital_payout_stmt->execute();
        $digital_installation_agent_payouts = floatval($digital_payout_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $digital_payout_stmt->close();
    }

    $viewing_payout_stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(viewing_fee, 0)), 0) as total FROM viewing_requests WHERE user_id = ? AND status = 'completed'");
    if ($viewing_payout_stmt) {
        $viewing_payout_stmt->bind_param('i', $user_id);
        $viewing_payout_stmt->execute();
        $viewing_request_agent_payouts = floatval($viewing_payout_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $viewing_payout_stmt->close();
    }

    $total_agent_payouts = $digital_installation_agent_payouts + $viewing_request_agent_payouts;

    $mover_conn = getMoverDatabaseConnection();
    if ($mover_conn) {
        $delivery_completed_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'completed'");
        if ($delivery_completed_stmt) {
            $delivery_completed_stmt->execute();
            $delivery_completed = intval($delivery_completed_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_completed_stmt->close();
        }

        $delivery_incomplete_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status IN ('pending','in_progress')");
        if ($delivery_incomplete_stmt) {
            $delivery_incomplete_stmt->execute();
            $delivery_incomplete = intval($delivery_incomplete_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_incomplete_stmt->close();
        }

        $delivery_rescheduled_stmt = $mover_conn->prepare("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'rescheduled'");
        if ($delivery_rescheduled_stmt) {
            $delivery_rescheduled_stmt->execute();
            $delivery_rescheduled = intval($delivery_rescheduled_stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $delivery_rescheduled_stmt->close();
        }

        $groups_stmt = $mover_conn->prepare(
            "SELECT mg.id, mg.group_name, COUNT(DISTINCT mb.id) as total_bookings, SUM(CASE WHEN mb.status = 'completed' THEN 1 ELSE 0 END) as completed_bookings, SUM(CASE WHEN mb.status IN ('pending','in_progress') THEN 1 ELSE 0 END) as pending_bookings, SUM(CASE WHEN mb.status = 'rescheduled' THEN 1 ELSE 0 END) as rescheduled_bookings, AVG(mr.rating) as avg_rating, COUNT(DISTINCT mr.id) as feedback_count FROM mover_groups mg LEFT JOIN mover_bookings mb ON mg.id = mb.assigned_group_id LEFT JOIN mover_reviews mr ON mb.id = mr.booking_id GROUP BY mg.id ORDER BY avg_rating DESC LIMIT 10"
        );
        if ($groups_stmt) {
            $groups_stmt->execute();
            $groups_result = $groups_stmt->get_result();
            while ($row = $groups_result->fetch_assoc()) {
                $delivery_groups[] = $row;
            }
            $groups_stmt->close();
        }
    }

    $task_types = ['NightlyFied', 'Hotel Reservation', 'Student Housing', 'Sold Properties', 'Delivery', 'House Swap', 'Cleaning Services', 'WIFI Distribution', 'CCTV Installation', 'Alexa Installation', 'Interior Designs'];
    $tasks_by_type_stmt = $conn->prepare(
        "SELECT c.consultation_type, c.status, COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? GROUP BY c.consultation_type, c.status"
    );
    if ($tasks_by_type_stmt) {
        $tasks_by_type_stmt->bind_param('i', $user_id);
        $tasks_by_type_stmt->execute();
        $tasks_by_type_result = $tasks_by_type_stmt->get_result();
        while ($row = $tasks_by_type_result->fetch_assoc()) {
            $type = $row['consultation_type'] ?? 'Other';
            $status = $row['status'] ?? 'pending';
            if (!isset($tasks_by_type[$type])) {
                $tasks_by_type[$type] = ['completed' => 0, 'incomplete' => 0, 'pending' => 0];
            }
            if ($status === 'completed') {
                $tasks_by_type[$type]['completed'] = intval($row['total']);
            } elseif ($status === 'pending') {
                $tasks_by_type[$type]['pending'] = intval($row['total']);
            } else {
                $tasks_by_type[$type]['incomplete'] = intval($row['total']);
            }
            $tasks_completed += ($status === 'completed' ? intval($row['total']) : 0);
            $tasks_pending += ($status === 'pending' ? intval($row['total']) : 0);
            $tasks_incomplete += ($status !== 'completed' && $status !== 'pending' ? intval($row['total']) : 0);
        }
        $tasks_by_type_stmt->close();
    }

    $messages_stmt = $conn->prepare(
        "SELECT id, title, message, created_at, sender_id, receiver_id, is_read, message_type FROM agent_messages WHERE (receiver_id = ? OR sender_id = ?) AND is_deleted = 0 ORDER BY created_at DESC LIMIT 20"
    );
    if ($messages_stmt) {
        $messages_stmt->bind_param('ii', $user_id, $user_id);
        $messages_stmt->execute();
        $messages_result = $messages_stmt->get_result();
        while ($row = $messages_result->fetch_assoc()) {
            $messages[] = $row;
            if (!$row['is_read'] && $row['receiver_id'] == $user_id) {
                $unread_message_count++;
            }
        }
        $messages_stmt->close();
    }
}

$total_leads = count($leads_list);
$total_tasks = $tasks_completed + $tasks_pending + $tasks_incomplete;
$happy_rate = $clients_connected ? round(($clients_happy / $clients_connected) * 100) : 0;
$success_rate = $properties_uploaded ? round(($properties_successful / $properties_uploaded) * 100) : 0;
$activity_counts = [
    'Leads' => $total_leads,
    'Properties' => $properties_uploaded,
    'Clients' => $clients_connected,
    'Viewings' => $viewings_assigned,
    'Digital Services' => $digital_service_requests,
    'Deliveries' => ($delivery_completed + $delivery_incomplete + $delivery_rescheduled),
    'Tasks' => $total_tasks,
    'Messages' => count($messages),
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Walbrand Properties Marketplace</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        :root {
            --sidebar-bg: #071c2b;
            --sidebar-text: #cbd5e1;
            --sidebar-accent: #38bdf8;
            --primary: #f97316;
            --surface: #ffffff;
            --border: #e2e8f0;
            --text-strong: #0f172a;
            --text-muted: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #eff3f6; color: var(--text-strong); }
        a { color: inherit; text-decoration: none; }

        .dashboard-layout { display: flex; min-height: 100vh; }

        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 28px 20px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            background: rgba(56, 189, 248, 0.15);
            border-radius: 14px;
            font-size: 1.15rem;
            color: #38bdf8;
        }

        .brand-title {
            font-size: 1.1rem;
            letter-spacing: 0.02em;
            font-weight: 700;
            line-height: 1.15;
        }

        .brand-subtitle { color: #94a3b8; font-size: 0.85rem; margin-top: 4px; }

        .nav-links { display: grid; gap: 8px; }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 16px;
            border-radius: 14px;
            color: var(--sidebar-text);
            transition: background 0.2s ease, color 0.2s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: white;
        }

        .nav-link i { width: 18px; text-align: center; }

        .sidebar-footer {
            padding: 20px 0 10px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }

        .support-card {
            background: #0f172a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 18px;
            padding: 18px;
            margin-top: 24px;
        }

        .support-card h4 { color: white; margin-bottom: 10px; font-size: 1rem; }
        .support-card p { color: #cbd5e1; font-size: 0.9rem; line-height: 1.5; }
        .support-card .support-item { margin-top: 10px; display: flex; justify-content: space-between; gap: 12px; }
        .support-card .support-item span:first-child { color: #94a3b8; }
        .support-card .support-item span:last-child { color: white; font-weight: 700; }
        .support-card a { display: inline-block; margin-top: 14px; padding: 10px 16px; background: var(--primary); border-radius: 12px; color: white; text-align: center; width: 100%; }

        .main { flex: 1; padding: 28px 32px; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 28px;
        }

        .topbar-left h1 { font-size: 2rem; color: var(--text-strong); }
        .topbar-left p { color: var(--text-muted); margin-top: 8px; }

        .topbar-right {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .topbar-button,
        .notification-pill,
        .profile-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: white;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            color: var(--text-strong);
            font-weight: 700;
            text-decoration: none;
        }

        .topbar-button i,
        .notification-pill i,
        .profile-card i {
            font-size: 0.95rem;
        }

        .notification-pill {
            background: rgba(249, 115, 22, 0.08);
            border-color: rgba(249, 115, 22, 0.2);
            color: #b45309;
        }

        .notification-pill .notification-count {
            min-width: 22px;
            min-height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #dc2626;
            color: white;
            font-size: 0.8rem;
            padding: 0 7px;
        }

        .profile-card {
            gap: 12px;
            padding: 12px 16px;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .profile-name {
            text-align: left;
            line-height: 1.1;
        }

        .profile-name small {
            display: block;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 400;
        }

        .topbar-home {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            border-radius: 16px;
            background: white;
            border: 1px solid var(--border);
            color: var(--text-strong);
            text-decoration: none;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            font-weight: 700;
        }

        .topbar-home i {
            color: var(--primary);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .topbar-button,
        .notification-pill,
        .profile-card {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: white;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
            color: var(--text-strong);
            font-weight: 700;
            text-decoration: none;
        }

        .topbar-button i,
        .notification-pill i,
        .profile-card i {
            font-size: 0.95rem;
        }

        .notification-pill {
            background: rgba(249, 115, 22, 0.08);
            border-color: rgba(249, 115, 22, 0.2);
            color: #b45309;
        }

        .notification-pill .notification-count {
            min-width: 22px;
            min-height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: #dc2626;
            color: white;
            font-size: 0.8rem;
            padding: 0 7px;
        }

        .profile-card {
            gap: 12px;
            padding: 12px 16px;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .profile-name {
            text-align: left;
            line-height: 1.1;
        }

        .profile-name small {
            display: block;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 400;
        }

        .metric-pill {
            background: white;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 14px 18px;
            min-width: 160px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }
            border-radius: 18px;
            padding: 14px 18px;
            min-width: 160px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.05);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .quick-action-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 22px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }

        .quick-action-card span {
            font-weight: 700;
            color: var(--text-strong);
        }

        .quick-action-card small {
            display: block;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .quick-action-card i {
            font-size: 1.45rem;
            color: var(--primary);
            min-width: 36px;
            min-height: 36px;
            display: grid;
            place-items: center;
            background: rgba(249, 115, 22, 0.12);
            border-radius: 12px;
        }

        .metric-pill strong { display: block; font-size: 1.05rem; margin-bottom: 6px; }
        .metric-pill small { color: var(--text-muted); }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 26px;
        }

        .overview-card {
            background: white;
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.05);
            border: 1px solid var(--border);
        }

        .overview-card h3 { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 16px; }
        .overview-card .value { font-size: 2.25rem; font-weight: 700; margin-bottom: 10px; }
        .overview-card .small { color: var(--text-muted); font-size: 0.9rem; }

        .panel-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }

        .panel {
            background: white;
            border-radius: 24px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.05);
        }

        .panel-header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .panel-header h2 { font-size: 1.05rem; color: var(--text-strong); }
        .panel-header span { color: var(--text-muted); font-size: 0.9rem; }

        .panel-body { padding: 24px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .stat-item {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px 20px;
        }

        .stat-item h3 { font-size: 1.8rem; margin-bottom: 8px; }
        .stat-item p { color: var(--text-muted); font-size: 0.9rem; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        .data-table th,
        .data-table td { padding: 14px 12px; text-align: left; }
        .data-table th { background: #f8fafc; color: #475569; font-size: 0.85rem; letter-spacing: 0.01em; }
        .data-table td { border-bottom: 1px solid var(--border); color: #1e293b; font-size: 0.95rem; }
        .data-table tr:hover { background: #f8fafc; }

        .status-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: capitalize;
        }

        .status-completed { background: rgba(34, 197, 94, 0.15); color: #166534; }
        .status-pending { background: rgba(245, 158, 11, 0.15); color: #92400e; }
        .status-cancelled { background: rgba(220, 38, 38, 0.12); color: #991b1b; }
        .status-scheduled { background: rgba(59, 130, 246, 0.15); color: #1d4ed8; }
        .status-new { background: rgba(56, 189, 248, 0.12); color: #0ea5e9; }

        .message-list { list-style: none; padding: 0; margin: 0; }
        .message-item {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 14px;
            background: #f8fafc;
        }
        .message-item.agent {
            background: #eff6ff !important;
            border-color: #2563eb !important;
        }
        .message-item.admin {
            background: #ecfdf5 !important;
            border-color: #059669 !important;
        }
        .message-item.unread { background: #fef3c7; border-color: #fde68a; }
        .message-item h4 { margin-bottom: 8px; font-size: 1rem; }
        .message-item p { color: #475569; margin-bottom: 10px; line-height: 1.6; }
        .message-item small { color: var(--text-muted); }
        .card-actions { margin-top: 18px; display: flex; flex-wrap: wrap; gap: 12px; }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            border-radius: 14px;
            padding: 12px 18px;
            background: #eef4fb;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 16px 30px rgba(249, 115, 22, 0.18); }
        .btn-secondary {
            background: #f8fafc;
            color: var(--text-strong);
            border: 1px solid var(--border);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            inset: 0;
            background: rgba(15, 23, 42, 0.5);
            padding: 36px 24px;
            overflow-y: auto;
        }
        .modal-content {
            max-width: 620px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.18);
            position: relative;
        }
        .close {
            position: absolute;
            top: 18px;
            right: 18px;
            font-size: 24px;
            color: var(--text-muted);
            cursor: pointer;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--text-strong); }
        .form-group input,
        .form-group textarea { width: 100%; border-radius: 14px; border: 1px solid var(--border); padding: 14px 16px; font-size: 0.95rem; }
        .form-group textarea { min-height: 160px; resize: vertical; }

        .stats-chips { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 12px; }
        .stats-chip { background: #f8fafc; border: 1px solid var(--border); border-radius: 16px; padding: 12px 16px; min-width: 124px; }
        .stats-chip strong { display: block; font-size: 1.1rem; margin-bottom: 4px; }

        @media (max-width: 1140px) {
            .dashboard-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .main { padding: 24px; }
            .panel-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .topbar { flex-direction: column; }
            .topbar-right { width: 100%; }
            .overview-grid { grid-template-columns: 1fr; }
            .stat-item { padding: 16px; }
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
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
                    <a class="nav-link <?= $currentPage === 'agent_dashboard.php' ? 'active' : '' ?>" href="agent_dashboard.php"><i class="fa-solid fa-gauge-high"></i> Dashboard</a>
                    <a class="nav-link <?= $currentPage === 'agent_leads.php' ? 'active' : '' ?>" href="agent_leads.php"><i class="fa-solid fa-phone"></i> Leads</a>
                    <a class="nav-link <?= $currentPage === 'agent_properties.php' ? 'active' : '' ?>" href="agent_properties.php"><i class="fa-solid fa-building"></i> Properties</a>
                    <a class="nav-link <?= $currentPage === 'agent_clients.php' ? 'active' : '' ?>" href="agent_clients.php"><i class="fa-solid fa-users"></i> Clients</a>
                    <a class="nav-link <?= $currentPage === 'agent_viewings.php' ? 'active' : '' ?>" href="agent_viewings.php"><i class="fa-solid fa-eye"></i> Viewings</a>
                    <a class="nav-link <?= $currentPage === 'agent_digital_services.php' ? 'active' : '' ?>" href="agent_digital_services.php"><i class="fa-solid fa-wifi"></i> Digital Services</a>
                    <a class="nav-link <?= $currentPage === 'agent_delivery_groups.php' ? 'active' : '' ?>" href="agent_delivery_groups.php"><i class="fa-solid fa-truck-fast"></i> Delivery Groups</a>
                    <a class="nav-link <?= $currentPage === 'agent_tasks.php' ? 'active' : '' ?>" href="agent_tasks.php"><i class="fa-solid fa-list-check"></i> Tasks</a>
                    <a class="nav-link <?= $currentPage === 'agent_messages.php' ? 'active' : '' ?>" href="agent_messages.php"><i class="fa-solid fa-message"></i> Messages<?= $unread_message_count ? ' <span style="background:#dc2626;color:white;border-radius:999px;padding:2px 8px;font-size:0.75rem;">'.$unread_message_count.'</span>' : '' ?></a>
                    <a class="nav-link <?= $currentPage === 'earnings.php' ? 'active' : '' ?>" href="earnings.php"><i class="fa-solid fa-wallet"></i> Earnings</a>
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
            <?php if (!empty($_SESSION['success_message'])): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; border-left: 4px solid #22c55e; color: #166534; padding: 16px; border-radius: 14px; margin-bottom: 18px;">
                    ✓ <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error_message'])): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; border-left: 4px solid #dc2626; color: #991b1b; padding: 16px; border-radius: 14px; margin-bottom: 18px;">
                    ✗ <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <section class="topbar">
                <div class="topbar-left">
                    <h1>Good morning, <?= htmlspecialchars($first_name) ?> 👋</h1>
                    <p>Here's what's happening with your business today.</p>
                </div>

                <div class="topbar-right">
                    <a href="index.php" class="topbar-home"><i class="fa-solid fa-house"></i> Back to Home</a>
                    <div class="topbar-actions">
                        <a href="user_dashboard.php" class="topbar-button"><i class="fa-solid fa-arrow-left"></i> Back to User Dashboard</a>
                        <a href="agent_messages.php" class="notification-pill"><i class="fa-solid fa-bell"></i><span class="notification-count"><?= $unread_message_count ?></span> Notifications</a>
                        <a href="agent_dashboard.php" class="profile-card">
                            <?php if (!empty($profile_picture)): ?>
                                <img src="<?= htmlspecialchars($profile_picture) ?>" alt="Profile" class="profile-avatar" style="object-fit: cover;" />
                            <?php else: ?>
                                <div class="profile-avatar"><?= htmlspecialchars($avatar_initials) ?></div>
                            <?php endif; ?>
                            <span class="profile-name">
                                <?= htmlspecialchars($user_name) ?><br>
                                <small>Agent Profile</small>
                            </span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="quick-actions">
                <a href="agent_leads.php" class="quick-action-card">
                    <div>
                        <span>Leads</span>
                        <small>Open your latest leads</small>
                    </div>
                    <i class="fa-solid fa-phone"></i>
                </a>
                <a href="agent_properties.php" class="quick-action-card">
                    <div>
                        <span>Properties</span>
                        <small>Manage listings and progress</small>
                    </div>
                    <i class="fa-solid fa-building"></i>
                </a>
                <a href="agent_clients.php" class="quick-action-card">
                    <div>
                        <span>Clients</span>
                        <small>Review client connections</small>
                    </div>
                    <i class="fa-solid fa-users"></i>
                </a>
                <a href="agent_viewings.php" class="quick-action-card">
                    <div>
                        <span>Viewings</span>
                        <small>Track upcoming viewings</small>
                    </div>
                    <i class="fa-solid fa-eye"></i>
                </a>
                <a href="agent_delivery_groups.php" class="quick-action-card">
                    <div>
                        <span>Delivery Groups</span>
                        <small>Open mover group details</small>
                    </div>
                    <i class="fa-solid fa-truck-fast"></i>
                </a>
                <a href="agent_tasks.php" class="quick-action-card">
                    <div>
                        <span>Tasks</span>
                        <small>Open task categories</small>
                    </div>
                    <i class="fa-solid fa-list-check"></i>
                </a>
                <a href="agent_digital_services.php" class="quick-action-card">
                    <div>
                        <span>Digital Services</span>
                        <small>Manage WiFi, CCTV, and Alexa services</small>
                    </div>
                    <i class="fa-solid fa-wifi"></i>
                </a>
            </section>

            <section class="overview-grid">
                <div class="overview-card">
                    <h3>Leads</h3>
                    <div class="value"><?= $total_leads ?></div>
                    <div class="small">All potential customers in the last 7 days.</div>
                </div>
                <div class="overview-card">
                    <h3>Properties</h3>
                    <div class="value"><?= $properties_uploaded ?></div>
                    <div class="small"><?= $success_rate ?> success rate from your active listings.</div>
                </div>
                <div class="overview-card">
                    <h3>Clients</h3>
                    <div class="value"><?= $clients_connected ?></div>
                    <div class="small">Happy rate: <?= $happy_rate ?>%</div>
                </div>
                <div class="overview-card">
                    <h3>Tasks</h3>
                    <div class="value"><?= $total_tasks ?></div>
                    <div class="small">Total tasks across all categories.</div>
                </div>
                <div class="overview-card">
                    <h3>Messages</h3>
                    <div class="value"><?= count($messages) ?></div>
                    <div class="small"><?= $unread_message_count ?> unread messages</div>
                </div>
                <div class="overview-card">
                    <h3>Deliveries</h3>
                    <div class="value"><?= $delivery_completed + $delivery_incomplete + $delivery_rescheduled ?></div>
                    <div class="small">Delivery booking totals from the mover system.</div>
                </div>
                <div class="overview-card">
                    <h3>Digital Services</h3>
                    <div class="value"><?= $digital_service_requests ?></div>
                    <div class="small">WiFi, CCTV, and Alexa installation requests.</div>
                </div>
            </section>

            <section class="panel" style="margin-bottom: 24px;">
                <div class="panel-header">
                    <h2>Activity Summary</h2>
                    <span>All agent page activity this session</span>
                </div>
                <div class="panel-body">
                    <div class="chart-container" style="height: 360px;">
                        <canvas id="activityChart"></canvas>
                    </div>
                </div>
            </section>

            <section class="panel-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>1. Leads</h2>
                        <span>Recent enquiries</span>
                    </div>
                    <div class="panel-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Consultation</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leads_list)): ?>
                                    <?php foreach (array_slice($leads_list, 0, 6) as $lead): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lead['name'] ?: 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(($lead['email'] ?: 'N/A') . ' / ' . ($lead['phone'] ?: 'N/A')) ?></td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lead['consultation_type'] ?? 'general'))) ?></td>
                                        <td><span class="status-tag status-<?= htmlspecialchars($lead['status'] ?: 'pending') ?>"><?= htmlspecialchars(ucfirst($lead['status'] ?: 'Pending')) ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; color: var(--text-muted); padding: 20px 0;">No recent leads available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="card-actions">
                            <a href="agent_leads.php" class="btn-primary">View All Leads</a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>2. Properties</h2>
                        <span>Your property performance</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= $properties_uploaded ?></h3>
                                <p>Uploaded Properties</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--success);"><?= $properties_successful ?></h3>
                                <p>Successful Connections</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--danger);"><?= $properties_rejected ?></h3>
                                <p>Rejected Properties</p>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="agent_properties.php" class="btn-primary">Manage Properties</a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>3. Clients</h2>
                        <span>Client satisfaction overview</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= $clients_connected ?></h3>
                                <p>Happy Clients</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--danger);"><?= $clients_complained ?></h3>
                                <p>Complaints</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--warning);"><?= $clients_failed_pick ?></h3>
                                <p>Failed to Pick</p>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="agent_clients.php" class="btn-primary">View All Clients</a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>4. Viewings</h2>
                        <span>Assigned by admin</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= $viewings_assigned ?></h3>
                                <p>Assigned</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--success);"><?= $viewings_completed ?></h3>
                                <p>Completed</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--warning);"><?= $viewings_pending ?></h3>
                                <p>Pending</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--danger);"><?= $viewings_expired ?></h3>
                                <p>Expired</p>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="agent_viewings.php" class="btn-primary">View All Viewings</a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>5. Digital Services</h2>
                        <span>WiFi, CCTV, and Alexa installations</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= $digital_service_requests ?></h3>
                                <p>Total Requests</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--success);"><?= $digital_service_completed ?></h3>
                                <p>Completed</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--warning);"><?= $digital_service_pending ?></h3>
                                <p>Pending</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color: var(--danger);"><?= $digital_service_incomplete ?></h3>
                                <p>Incomplete</p>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="agent_digital_services.php" class="btn-primary">View Digital Services</a>
                        </div>
                    </div>
                </div>

                <div class="panel" style="background: #eef4fb; color: white;">
                    <div class="panel-header" style="border-bottom: 1px solid rgba(255,255,255,0.2);">
                        <h2 style="color: white;">Total Completed Services</h2>
                        <span style="color: rgba(255,255,255,0.8);">Digital Installation + Viewing Requests</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                            <div class="stat-item">
                                <h3 style="font-size: 2.5rem; color: #4ade80;"><?= $total_completed_services ?></h3>
                                <p style="color: rgba(255,255,255,0.9);">Total Completed</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="font-size: 1.5rem; color: #fbbf24;"><?= $digital_service_completed ?></h3>
                                <p style="color: rgba(255,255,255,0.8);">Digital Services</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="font-size: 1.5rem; color: #60a5fa;"><?= $viewings_completed ?></h3>
                                <p style="color: rgba(255,255,255,0.8);">Viewing Requests</p>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="agent_viewings.php" class="btn-secondary" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white; margin-right: 10px;">View Viewings</a>
                            <a href="agent_digital_services.php" class="btn-secondary" style="background: rgba(255,255,255,0.2); color: white; border: 1px solid white;">View Digital Services</a>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>Total Agent Payouts</h2>
                    <span>Earnings from Digital Services & Viewing Requests</span>
                </div>
                <div class="panel-body">
                    <div class="stats-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                        <div class="stat-item" style="background: #eef4fb; color: white; padding: 20px; border-radius: 8px;">
                            <h3 style="font-size: 2rem; color: white; margin: 0 0 10px 0;">KES <?= number_format($total_agent_payouts, 0) ?></h3>
                            <p style="color: rgba(255,255,255,0.9); margin: 0;">Total Earnings</p>
                        </div>
                        <div class="stat-item" style="background: #eef4fb; color: white; padding: 20px; border-radius: 8px;">
                            <h3 style="font-size: 1.5rem; color: white; margin: 0 0 10px 0;">KES <?= number_format($digital_installation_agent_payouts, 0) ?></h3>
                            <p style="color: rgba(255,255,255,0.9); margin: 0;">Digital Services</p>
                        </div>
                        <div class="stat-item" style="background: #eef4fb; color: white; padding: 20px; border-radius: 8px;">
                            <h3 style="font-size: 1.5rem; color: white; margin: 0 0 10px 0;">KES <?= number_format($viewing_request_agent_payouts, 0) ?></h3>
                            <p style="color: rgba(255,255,255,0.9); margin: 0;">Viewing Requests</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <h2>6. Delivery Groups</h2>
                    <span>Group performance summary</span>
                </div>
                <div class="panel-body">
                    <div class="stats-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                        <div class="stat-item">
                            <h3 style="color: var(--success);"><?= $delivery_completed ?></h3>
                            <p>Completed</p>
                        </div>
                        <div class="stat-item">
                            <h3 style="color: var(--warning);"><?= $delivery_incomplete ?></h3>
                            <p>Incomplete</p>
                        </div>
                        <div class="stat-item">
                            <h3 style="color: #3b82f6;"><?= $delivery_rescheduled ?></h3>
                            <p>Rescheduled</p>
                        </div>
                    </div>
                    <div style="margin-top: 22px;">
                        <div class="stats-chips">
                            <?php if (!empty($delivery_groups)): ?>
                                <?php foreach (array_slice($delivery_groups, 0, 4) as $group): ?>
                                    <div class="stats-chip">
                                        <strong><?= htmlspecialchars($group['group_name']) ?></strong>
                                        <span><?= $group['completed_bookings'] ?> completed</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="color: var(--text-muted);">No delivery group data available yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-actions" style="margin-top: 22px;">
                        <a href="agent_delivery_groups.php" class="btn-primary">View All Delivery Groups</a>
                    </div>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <div class="panel-header">
                    <h2>7. Tasks</h2>
                    <span>Completion by category</span>
                </div>
                <div class="panel-body">
                    <div class="stats-grid" style="grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 24px;">
                        <div class="stat-item"><h3><?= $tasks_completed ?></h3><p>Completed</p></div>
                        <div class="stat-item"><h3><?= $tasks_incomplete ?></h3><p>Incomplete</p></div>
                        <div class="stat-item"><h3><?= $tasks_pending ?></h3><p>Pending</p></div>
                        <div class="stat-item"><h3><?= $total_tasks ?></h3><p>Total Tasks</p></div>
                    </div>
                    <div class="chart-container" style="height: 320px;">
                        <canvas id="tasksChart"></canvas>
                    </div>
                    <div class="card-actions" style="margin-top: 22px;">
                        <a href="agent_tasks.php" class="btn-primary">View All Tasks</a>
                    </div>
                </div>
            </section>

            <section class="panel" style="margin-top: 24px;">
                <div class="panel-header">
                    <h2>8. Messages</h2>
                    <span>Admin support and unread alerts</span>
                </div>
                <div class="panel-body">
                    <div class="stats-grid" style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom: 24px;">
                        <div class="stat-item"><h3><?= count($messages) ?></h3><p>Total Messages</p></div>
                        <div class="stat-item"><h3><?= $unread_message_count ?></h3><p>Unread</p></div>
                        <div class="stat-item"><h3>Admin</h3><p>Direct support channel</p></div>
                    </div>
                    <?php if (!empty($messages)): ?>
                        <ul class="message-list">
                            <?php foreach (array_slice($messages, 0, 5) as $message): ?>
                                <?php $is_agent = $message['sender_id'] == $user_id; ?>
                                <li class="message-item <?= $is_agent ? 'agent' : 'admin' ?> <?= $message['is_read'] ? '' : 'unread' ?>">
                                    <h4><?= htmlspecialchars($message['title'] ?: 'New Message') ?></h4>
                                    <p><?= htmlspecialchars(substr($message['message'], 0, 120)) ?><?= strlen($message['message']) > 120 ? '...' : '' ?></p>
                                    <small><?= htmlspecialchars(date('M j, Y H:i', strtotime($message['created_at']))) ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: var(--text-muted);">No messages yet. Send a request to admin using the button below.</p>
                    <?php endif; ?>
                    <div class="card-actions" style="margin-top: 18px;">
                        <button class="btn-primary" onclick="showMessageModal()"><i class="fa-solid fa-envelope"></i> Send Message</button>
                        <a href="agent_messages.php" class="btn-secondary">Open Messages Page</a>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <div id="messageModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeMessageModal()">&times;</span>
            <h2 style="margin-bottom: 18px;">Send Message to Admin</h2>
            <form action="send_message_to_admin.php" method="POST">
                <div class="form-group">
                    <label for="message-title">Subject</label>
                    <input id="message-title" type="text" name="title" placeholder="Message subject" required>
                </div>
                <div class="form-group">
                    <label for="message-body">Message</label>
                    <textarea id="message-body" name="message" placeholder="Write your message here..." required></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 14px; flex-wrap: wrap;">
                    <button type="button" class="btn-secondary" onclick="closeMessageModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const tasksByType = <?= json_encode($tasks_by_type) ?>;
        const activityCounts = <?= json_encode($activity_counts) ?>;
        window.addEventListener('load', function() {
            const activityLabels = Object.keys(activityCounts);
            const activityValues = activityLabels.map(key => activityCounts[key]);

            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                new Chart(activityCtx, {
                    type: 'bar',
                    data: {
                        labels: activityLabels,
                        datasets: [{
                            label: 'Activity by Agent Page',
                            data: activityValues,
                            backgroundColor: ['#3b82f6', '#f97316', '#10b981', '#8b5cf6', '#f59e0b', '#ec4899', '#0ea5e9']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        },
                        plugins: {
                            legend: { position: 'top' },
                            tooltip: { mode: 'index', intersect: false }
                        }
                    }
                });
            }

            const labels = Object.keys(tasksByType);
            const completedData = labels.map(key => tasksByType[key].completed);
            const pendingData = labels.map(key => tasksByType[key].pending + tasksByType[key].incomplete);

            const ctx = document.getElementById('tasksChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Completed', data: completedData, backgroundColor: '#22c55e' },
                            { label: 'Pending / Incomplete', data: pendingData, backgroundColor: '#f59e0b' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { position: 'top' } }
                    }
                });
            }
        });

        function showMessageModal() {
            document.getElementById('messageModal').style.display = 'block';
        }
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeMessageModal();
            }
        };
    </script>
</body>
</html>
