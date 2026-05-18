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
$avatar_url = '';
if (!empty($profile_picture) && file_exists(__DIR__ . '/' . $profile_picture)) {
    $avatar_url = $profile_picture;
}

ensure_consultations_table_exists($conn);

$total_leads = 0;
$property_views = 0;
$viewings_scheduled = 0;
$deals_in_progress = 0;
$commission_total = 0;
$commission_last_30_days = 0;
$commission_house_connection = 0;
$commission_wifi_cctv = 0;
$commission_last_30_house_connection = 0;
$commission_last_30_wifi_cctv = 0;

// New variables for detailed stats
$leads_list = [];
$properties_uploaded = 0;
$properties_successful = 0;
$properties_rejected = 0;
$clients_connected = 0;
$clients_complained = 0;
$clients_happy = 0;
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

$lead_sources = [
    ['label' => 'Property Viewing', 'value' => 0, 'color' => '#f97316'],
    ['label' => 'Valuation', 'value' => 0, 'color' => '#60a5fa'],
    ['label' => 'Financing', 'value' => 0, 'color' => '#22c55e'],
    ['label' => 'Legal', 'value' => 0, 'color' => '#a855f7'],
    ['label' => 'General', 'value' => 0, 'color' => '#facc15'],
];

$leads_performance_labels = [];
$leads_performance_data = [];

$stats = [
    ['label' => 'Total Leads', 'value' => '0', 'meta' => 'Real-time from completed client requests', 'link' => 'consultations.php'],
    ['label' => 'Property Views', 'value' => '0', 'meta' => 'Counts ongoing and completed property viewings', 'link' => 'my_properties.php'],
    ['label' => 'Viewings Scheduled', 'value' => '0', 'meta' => 'Upcoming viewing requests awaiting confirmation', 'link' => 'consultations.php?type=viewing'],
    ['label' => 'Deals in Progress', 'value' => '0', 'meta' => 'Active property transactions and service deals', 'link' => 'my_properties.php'],
    ['label' => 'Commission Earned', 'value' => 'KSH0', 'meta' => 'Only completed deals count', 'link' => 'agent_dashboard.php#wallet'],
];

$wallet = [
    'balance' => 'KSH0',
    'earnings' => 'KSH0',
    'pending' => 'KSH0',
];

// Lead and deal metrics
if ($conn) {
    $total_leads_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status IN ('pending','scheduled','completed')");
    if ($total_leads_stmt) {
        $total_leads_stmt->bind_param('i', $user_id);
        $total_leads_stmt->execute();
        $total_leads_result = $total_leads_stmt->get_result()->fetch_assoc();
        $total_leads_stmt->close();
        $total_leads = intval($total_leads_result['total'] ?? 0);
    }

    $property_views_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing'");
    if ($property_views_stmt) {
        $property_views_stmt->bind_param('i', $user_id);
        $property_views_stmt->execute();
        $property_views_result = $property_views_stmt->get_result()->fetch_assoc();
        $property_views_stmt->close();
        $property_views = intval($property_views_result['total'] ?? 0);
    }

    $viewings_scheduled_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' AND c.status IN ('pending','scheduled')");
    if ($viewings_scheduled_stmt) {
        $viewings_scheduled_stmt->bind_param('i', $user_id);
        $viewings_scheduled_stmt->execute();
        $viewings_scheduled_result = $viewings_scheduled_stmt->get_result()->fetch_assoc();
        $viewings_scheduled_stmt->close();
        $viewings_scheduled = intval($viewings_scheduled_result['total'] ?? 0);
    }

    if (tableExists($conn, 'transactions')) {
        $deals_in_progress_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM transactions WHERE seller_id = ? AND status IN ('pending','in_progress')");
        if ($deals_in_progress_stmt) {
            $deals_in_progress_stmt->bind_param('i', $user_id);
            $deals_in_progress_stmt->execute();
            $deals_in_progress_result = $deals_in_progress_stmt->get_result()->fetch_assoc();
            $deals_in_progress_stmt->close();
            $deals_in_progress = intval($deals_in_progress_result['total'] ?? 0);
        }
    } else {
        $deals_in_progress = 0;
    }

    $commission_stmt = $conn->prepare(
        "SELECT
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%house_connection%' OR LOWER(c.consultation_type) LIKE '%house connection%' THEN 1 ELSE 0 END) AS house_count,
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%wifi%' THEN 1 ELSE 0 END) AS wifi_count,
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%cctv%' THEN 1 ELSE 0 END) AS cctv_count
        FROM consultations c
        JOIN properties p ON c.property_id = p.id
        WHERE p.seller_id = ? AND c.status = 'completed'"
    );
    if ($commission_stmt) {
        $commission_stmt->bind_param('i', $user_id);
        $commission_stmt->execute();
        $commission_result = $commission_stmt->get_result()->fetch_assoc();
        $commission_stmt->close();
        $commission_house_connection = intval($commission_result['house_count'] ?? 0);
        $commission_wifi_cctv = intval($commission_result['wifi_count'] ?? 0) + intval($commission_result['cctv_count'] ?? 0);
        $commission_total = ($commission_house_connection * 500) + ($commission_wifi_cctv * 1000);
    }

    $commission_30_days_stmt = $conn->prepare(
        "SELECT
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%house_connection%' OR LOWER(c.consultation_type) LIKE '%house connection%' THEN 1 ELSE 0 END) AS house_count,
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%wifi%' THEN 1 ELSE 0 END) AS wifi_count,
            SUM(CASE WHEN LOWER(c.consultation_type) LIKE '%cctv%' THEN 1 ELSE 0 END) AS cctv_count
        FROM consultations c
        JOIN properties p ON c.property_id = p.id
        WHERE p.seller_id = ? AND c.status = 'completed' AND COALESCE(c.completed_at, c.created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    if ($commission_30_days_stmt) {
        $commission_30_days_stmt->bind_param('i', $user_id);
        $commission_30_days_stmt->execute();
        $commission_30_days_result = $commission_30_days_stmt->get_result()->fetch_assoc();
        $commission_30_days_stmt->close();
        $commission_last_30_house_connection = intval($commission_30_days_result['house_count'] ?? 0);
        $commission_last_30_wifi_cctv = intval($commission_30_days_result['wifi_count'] ?? 0) + intval($commission_30_days_result['cctv_count'] ?? 0);
        $commission_last_30_days = ($commission_last_30_house_connection * 500) + ($commission_last_30_wifi_cctv * 1000);
    }

    $lead_sources_stmt = $conn->prepare(
        "SELECT c.consultation_type, COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? GROUP BY c.consultation_type ORDER BY total DESC LIMIT 6"
    );
    if ($lead_sources_stmt) {
        $lead_sources_stmt->bind_param('i', $user_id);
        $lead_sources_stmt->execute();
        $lead_sources_result = $lead_sources_stmt->get_result();
        $lead_sources_stmt->close();
    }

    $lead_source_colors = ['#f97316', '#60a5fa', '#22c55e', '#a855f7', '#facc15', '#14b8a6'];
    $lead_sources = [];
    $color_index = 0;
    while ($row = $lead_sources_result->fetch_assoc()) {
        $label = !empty($row['consultation_type']) ? ucfirst(str_replace('_', ' ', $row['consultation_type'])) : 'Unknown';
        $lead_sources[] = [
            'label' => $label,
            'value' => intval($row['total']),
            'color' => $lead_source_colors[$color_index % count($lead_source_colors)],
        ];
        $color_index++;
    }

    if (empty($lead_sources)) {
        $lead_sources = [
            ['label' => 'Property Viewing', 'value' => 0, 'color' => '#f97316'],
            ['label' => 'Valuation', 'value' => 0, 'color' => '#60a5fa'],
            ['label' => 'Financing', 'value' => 0, 'color' => '#22c55e'],
            ['label' => 'Legal', 'value' => 0, 'color' => '#a855f7'],
            ['label' => 'General', 'value' => 0, 'color' => '#facc15'],
        ];
    }

    $lead_performance_stmt = $conn->prepare(
        "SELECT DATE(c.created_at) AS day, COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.created_at >= DATE_SUB(NOW(), INTERVAL 6 DAY) GROUP BY DATE(c.created_at) ORDER BY DATE(c.created_at)"
    );
    $lead_performance_result = null;
    if ($lead_performance_stmt) {
        $lead_performance_stmt->bind_param('i', $user_id);
        $lead_performance_stmt->execute();
        $lead_performance_result = $lead_performance_stmt->get_result();
        $lead_performance_stmt->close();
    }

    $lead_dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $lead_dates[$date] = 0;
    }
    if ($lead_performance_result) {
        while ($row = $lead_performance_result->fetch_assoc()) {
            $lead_dates[$row['day']] = intval($row['total']);
        }
    }
    foreach ($lead_dates as $day => $count) {
        $leads_performance_labels[] = date('D', strtotime($day));
        $leads_performance_data[] = $count;
    }

    // New queries for detailed stats
    // Leads list
    $leads_list_stmt = $conn->prepare("SELECT u.name, u.email, u.phone, c.consultation_type, c.status FROM consultations c JOIN properties p ON c.property_id = p.id JOIN users u ON c.user_id = u.id WHERE p.seller_id = ? AND c.status IN ('pending','scheduled','completed') ORDER BY c.created_at DESC LIMIT 10");
    if ($leads_list_stmt) {
        $leads_list_stmt->bind_param('i', $user_id);
        $leads_list_stmt->execute();
        $leads_list_result = $leads_list_stmt->get_result();
        while ($row = $leads_list_result->fetch_assoc()) {
            $leads_list[] = $row;
        }
        $leads_list_stmt->close();
    }

    // Properties stats
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

    // Clients stats
    $clients_connected_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($clients_connected_stmt) {
        $clients_connected_stmt->bind_param('i', $user_id);
        $clients_connected_stmt->execute();
        $clients_connected = intval($clients_connected_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_connected_stmt->close();
    }

    // Assuming no feedback system yet, set to 0
    $clients_happy = 0;
    $clients_complained = 0;

    $clients_failed_pick_stmt = $conn->prepare("SELECT COUNT(DISTINCT c.user_id) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'cancelled'");
    if ($clients_failed_pick_stmt) {
        $clients_failed_pick_stmt->bind_param('i', $user_id);
        $clients_failed_pick_stmt->execute();
        $clients_failed_pick = intval($clients_failed_pick_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $clients_failed_pick_stmt->close();
    }

    // Viewings stats
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

    // Delivery stats - using mover system
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

        // Groups - assuming mover_groups table exists, show all groups for now
        $groups_stmt = $mover_conn->prepare("SELECT mg.id, mg.group_name, COUNT(DISTINCT mb.id) as booking_count, AVG(mr.rating) as avg_rating FROM mover_groups mg LEFT JOIN mover_bookings mb ON mg.id = mb.assigned_group_id LEFT JOIN mover_reviews mr ON mb.id = mr.booking_id GROUP BY mg.id ORDER BY avg_rating DESC LIMIT 5");
        if ($groups_stmt) {
            $groups_stmt->execute();
            $groups_result = $groups_stmt->get_result();
            while ($row = $groups_result->fetch_assoc()) {
                $delivery_groups[] = $row;
            }
            $groups_stmt->close();
        }
    }

    // Tasks stats
    $tasks_completed_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'completed'");
    if ($tasks_completed_stmt) {
        $tasks_completed_stmt->bind_param('i', $user_id);
        $tasks_completed_stmt->execute();
        $tasks_completed = intval($tasks_completed_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $tasks_completed_stmt->close();
    }

    $tasks_incomplete_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status IN ('pending','scheduled')");
    if ($tasks_incomplete_stmt) {
        $tasks_incomplete_stmt->bind_param('i', $user_id);
        $tasks_incomplete_stmt->execute();
        $tasks_incomplete = intval($tasks_incomplete_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $tasks_incomplete_stmt->close();
    }

    $tasks_pending_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.status = 'pending'");
    if ($tasks_pending_stmt) {
        $tasks_pending_stmt->bind_param('i', $user_id);
        $tasks_pending_stmt->execute();
        $tasks_pending = intval($tasks_pending_stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $tasks_pending_stmt->close();
    }

    // Tasks by type
    $tasks_by_type_stmt = $conn->prepare("SELECT c.consultation_type, c.status, COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? GROUP BY c.consultation_type, c.status");
    if ($tasks_by_type_stmt) {
        $tasks_by_type_stmt->bind_param('i', $user_id);
        $tasks_by_type_stmt->execute();
        $tasks_by_type_result = $tasks_by_type_stmt->get_result();
        while ($row = $tasks_by_type_result->fetch_assoc()) {
            $type = $row['consultation_type'];
            $status = $row['status'];
            if (!isset($tasks_by_type[$type])) {
                $tasks_by_type[$type] = ['completed' => 0, 'incomplete' => 0, 'pending' => 0];
            }
            $tasks_by_type[$type][$status] = intval($row['total']);
        }
        $tasks_by_type_stmt->close();
    }

    // Messages from admin
    $messages_stmt = $conn->prepare("SELECT title, message, created_at FROM notifications WHERE user_id = ? AND notification_type = 'system_message' ORDER BY created_at DESC LIMIT 10");
    if ($messages_stmt) {
        $messages_stmt->bind_param('i', $user_id);
        $messages_stmt->execute();
        $messages_result = $messages_stmt->get_result();
        while ($row = $messages_result->fetch_assoc()) {
            $messages[] = $row;
        }
        $messages_stmt->close();
    }
}

$stats = [
    ['label' => 'Total Leads', 'value' => number_format($total_leads), 'meta' => 'Real-time from active consultations', 'link' => 'consultations.php'],
    ['label' => 'Property Views', 'value' => number_format($property_views), 'meta' => 'All property viewing requests', 'link' => 'my_properties.php'],
    ['label' => 'Viewings Scheduled', 'value' => number_format($viewings_scheduled), 'meta' => 'Upcoming scheduled viewings', 'link' => 'consultations.php?type=viewing'],
    ['label' => 'Deals in Progress', 'value' => number_format($deals_in_progress), 'meta' => 'Active completed deals pipeline', 'link' => 'my_properties.php'],
    ['label' => 'Commission Earned', 'value' => 'KSH' . number_format($commission_total), 'meta' => 'Only completed service deals count', 'link' => 'agent_dashboard.php#wallet'],
];

$wallet = [
    'balance' => 'KSH' . number_format($commission_total, 2),
    'earnings' => 'KSH' . number_format($commission_last_30_days, 2),
    'pending' => 'KSH0',
];

$upcoming_viewings = [
    ['time' => 'Today, May 20 • 11:00 AM', 'title' => 'Luxury 4 Bedroom Duplex', 'location' => 'Lekki Phase 1, Lagos', 'client' => 'Tunde A.'],
    ['time' => 'Today, May 20 • 2:00 PM', 'title' => '3 Bedroom Terrace', 'location' => 'Ajah, Lagos', 'client' => 'Mary U.'],
    ['time' => 'Tomorrow, May 21 • 10:00 AM', 'title' => '5 Bedroom Fully Detached', 'location' => 'Ikoyi, Lagos', 'client' => 'Chinedu O.'],
];

$tasks = [
    ['title' => 'Follow up with Tunde A. about Lekki property', 'status' => 'Overdue', 'date' => 'May 18'],
    ['title' => 'Send property brochure to Mary U.', 'status' => 'Today', 'date' => 'Today'],
    ['title' => 'Schedule viewing for Chinedu O.', 'status' => 'Today', 'date' => 'Today'],
    ['title' => 'Follow up with website lead - John D.', 'status' => 'Upcoming', 'date' => 'May 21'],
];

$recent_leads = [
    ['initials' => 'TA', 'name' => 'Tunde A.', 'location' => 'Lekki, Lagos', 'status' => 'New', 'date' => 'May 20, 2024'],
    ['initials' => 'MU', 'name' => 'Mary U.', 'location' => 'Ajah, Lagos', 'status' => 'New', 'date' => 'May 20, 2024'],
    ['initials' => 'JD', 'name' => 'John D.', 'location' => 'Victoria Island, Lagos', 'status' => 'Contacted', 'date' => 'May 19, 2024'],
    ['initials' => 'CO', 'name' => 'Chinedu O.', 'location' => 'Ikoyi, Lagos', 'status' => 'Viewing Scheduled', 'date' => 'May 19, 2024'],
];

$mover_stats = [
    'total_bookings' => 0,
    'pending_bookings' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'open_disputes' => 0,
    'average_rating' => '0.0'
];

$mover_wallet_summary = [
    'accounts' => 0,
    'balance' => 0.00
];

$mover_recent_bookings = [];
$mover_error = '';

function tableExists($conn, $tableName) {
    $tableName = $conn->real_escape_string($tableName);
    $result = $conn->query("SHOW TABLES LIKE '" . $tableName . "'");
    if (!$result) {
        return false;
    }
    $exists = $result->num_rows > 0;
    $result->free();
    return $exists;
}

function moverTableExists($conn, $tableName) {
    return tableExists($conn, $tableName);
}

$mover_conn = getMoverDatabaseConnection();
if ($mover_conn) {
    if (!moverTableExists($mover_conn, 'mover_bookings') || !moverTableExists($mover_conn, 'mover_reviews') || !moverTableExists($mover_conn, 'mover_disputes') || !moverTableExists($mover_conn, 'mover_wallets')) {
        $mover_error = 'Delivery system tables are not yet installed. Run the mover setup scripts to enable delivery metrics.';
    } else {
        $result = $mover_conn->query("SELECT COUNT(*) AS total FROM mover_bookings");
        if ($result) {
            $mover_stats['total_bookings'] = intval($result->fetch_assoc()['total'] ?? 0);
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS total FROM mover_bookings WHERE status IN ('pending','payment_pending')");
        if ($result) {
            $mover_stats['pending_bookings'] = intval($result->fetch_assoc()['total'] ?? 0);
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'in_progress'");
        if ($result) {
            $mover_stats['in_progress'] = intval($result->fetch_assoc()['total'] ?? 0);
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS total FROM mover_bookings WHERE status = 'completed'");
        if ($result) {
            $mover_stats['completed'] = intval($result->fetch_assoc()['total'] ?? 0);
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS total FROM mover_disputes WHERE status IN ('open','under_review')");
        if ($result) {
            $mover_stats['open_disputes'] = intval($result->fetch_assoc()['total'] ?? 0);
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS count, AVG(rating) AS average FROM mover_reviews");
        if ($result) {
            $row = $result->fetch_assoc();
            $mover_stats['average_rating'] = $row['average'] !== null ? number_format(floatval($row['average']), 1) : '0.0';
            $result->free();
        }

        $result = $mover_conn->query("SELECT COUNT(*) AS accounts, SUM(balance) AS balance FROM mover_wallets");
        if ($result) {
            $row = $result->fetch_assoc();
            $mover_wallet_summary['accounts'] = intval($row['accounts'] ?? 0);
            $mover_wallet_summary['balance'] = floatval($row['balance'] ?? 0.00);
            $result->free();
        }

        $recent_result = $mover_conn->query("SELECT id, client_name, status, moving_date, service_type, total_cost FROM mover_bookings ORDER BY created_at DESC LIMIT 4");
        if ($recent_result) {
            while ($row = $recent_result->fetch_assoc()) {
                $mover_recent_bookings[] = $row;
            }
            $recent_result->free();
        }
    }

    $mover_conn->close();
} else {
    $mover_error = 'Delivery system connection unavailable.';
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - Walbrand Properties Marketplace & Interiors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-yHqA0gAK7s5JtH2xKZzlZ0mPB3QUPK+HTKxVKrIY3kqgETq4ptR7zZp4euCk7SpZC0E2/TfC1p6H9T2xh9tS0aQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-soft: #f1f5f9;
            --text: #0f172a;
            --muted: #64748b;
            --accent: #f97316;
            --accent-soft: rgba(249, 115, 22, 0.12);
            --border: #e2e8f0;
            --shadow: 0 30px 60px rgba(15, 23, 42, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        a { color: inherit; text-decoration: none; }

        .layout {
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .sidebar {
            width: 280px;
            padding: 30px 24px;
            background: #08172f;
            color: #f8fafc;
            position: fixed;
            inset: 0 auto 0 0;
            overflow-y: auto;
        }

        .sidebar .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #f97316, #fb923c);
            font-weight: 800;
            color: white;
            font-size: 1.2rem;
        }

        .brand-text {
            display: grid;
            gap: 4px;
        }

        .brand-text h2 {
            margin: 0;
            font-size: 1.15rem;
            letter-spacing: -0.03em;
        }

        .brand-text span {
            font-size: 0.82rem;
            color: #cbd5e1;
        }

        .sidebar-nav {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 6px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 18px;
            color: #cbd5e1;
            font-size: 0.95rem;
            transition: background 0.25s ease, color 0.25s ease;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(249, 115, 22, 0.16);
            color: #ffffff;
        }

        .sidebar-nav .badge {
            margin-left: auto;
            background: #0f172a;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.75rem;
            color: #fbbf24;
        }

        .sidebar-footer {
            margin-top: 32px;
            padding-top: 18px;
            border-top: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8;
            font-size: 0.9rem;
        }

        .content {
            margin-left: 280px;
            width: calc(100% - 280px);
            padding: 28px 32px 32px;
        }

        .topbar {
            display: flex;
            gap: 16px;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 22px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .menu-toggle {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: white;
            box-shadow: 0 12px 25px rgba(15,23,42,0.08);
            color: var(--text);
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .topbar-search {
            position: relative;
            background: white;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            width: min(520px, 100%);
            box-shadow: 0 10px 30px rgba(15,23,42,0.05);
        }

        .topbar-search input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.94rem;
            color: #0f172a;
        }

        .topbar-search button {
            border: none;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            font-size: 1rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .topbar-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: white;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            color: var(--text);
            box-shadow: 0 10px 30px rgba(15,23,42,0.05);
            font-size: 0.95rem;
        }

        .topbar-pill .badge {
            min-width: 22px;
            height: 22px;
            border-radius: 999px;
            background: #ef4444;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            padding: 0 7px;
        }

        .topbar-pill img,
        .topbar-pill .initials {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            font-weight: 700;
        }

        .welcome-card {
            border-radius: 30px;
            padding: 30px 32px;
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 28px;
            box-shadow: 0 35px 80px rgba(249,115,22,0.16);
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            top: -60px;
            right: -80px;
            background: rgba(255,255,255,0.12);
        }

        .welcome-content {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            flex-wrap: wrap;
        }

        .welcome-text h1 {
            margin: 0 0 14px 0;
            font-size: clamp(2rem, 2.5vw, 2.8rem);
            line-height: 1.02;
        }

        .welcome-text p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.7;
            max-width: 760px;
            opacity: 0.95;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-top: 28px;
        }

        .stat-card {
            padding: 22px 24px;
            border-radius: 24px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 18px 45px rgba(15,23,42,0.06);
        }

        .stat-card h3 {
            margin: 0 0 8px;
            font-size: 1.8rem;
        }

        .stat-card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .dashboard-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: 1.75fr 1fr;
            align-items: start;
        }

        .panel {
            border-radius: 28px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 25px 60px rgba(15,23,42,0.06);
            overflow: hidden;
        }

        .card-link,
        .panel-link {
            display: block;
            color: inherit;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }

        .card-link:hover,
        .panel-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 28px 70px rgba(15,23,42,0.12);
        }

        .panel-header {
            padding: 24px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 1.05rem;
        }

        .panel-header .select {
            border-radius: 999px;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            font-size: 0.95rem;
            background: #f8fafc;
        }

        .chart-placeholder,
        .chart-canvas-wrapper {
            height: 320px;
            margin: 0 24px 24px;
            border-radius: 24px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(249,115,22,0.08), rgba(249,115,22,0.02));
            display: grid;
            place-items: center;
        }

        .chart-canvas-wrapper canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .panel-body {
            padding: 0 24px 24px;
        }

        .delivery-panel {
            border-radius: 28px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 25px 60px rgba(15,23,42,0.06);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .delivery-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            padding: 0 24px 8px;
        }

        .delivery-metric {
            border-radius: 22px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 18px 16px;
        }

        .delivery-metric h4 {
            margin: 0 0 10px;
            font-size: 1.45rem;
            color: #0f172a;
        }

        .delivery-metric p {
            margin: 0;
            color: #64748b;
            font-size: 0.92rem;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }

        .booking-table th,
        .booking-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 0.92rem;
        }

        .booking-table th {
            color: #475569;
            font-weight: 700;
            background: #f8fafc;
        }

        .status-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: capitalize;
            background: #eef2ff;
            color: #3730a3;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-in_progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        .status-payment_pending { background: #fee2e2; color: #b91c1c; }
        .status-cancelled { background: #f5f3ff; color: #5b21b6; }

        .lead-sources {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .lead-sources h3 {
            margin: 0 0 10px 0;
            font-size: 1rem;
        }

        .lead-source-list {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 12px;
        }

        .lead-source-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .lead-source-item span {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .lead-source-pill {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .upcoming {
            padding: 24px;
        }

        .upcoming h3 {
            margin: 0 0 18px 0;
            font-size: 1rem;
        }

        .upcoming-item {
            border-radius: 20px;
            padding: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            margin-bottom: 14px;
        }

        .upcoming-item:last-child {
            margin-bottom: 0;
        }

        .upcoming-item .time {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .upcoming-item h4 {
            margin: 0 0 6px 0;
            font-size: 1.02rem;
        }

        .upcoming-item p {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.6;
        }

        .bottom-grid {
            display: grid;
            gap: 24px;
            grid-template-columns: 1.6fr 0.95fr;
            margin-top: 24px;
        }

        .task-panel,
        .leads-panel,
        .wallet-panel {
            border-radius: 28px;
            background: white;
            border: 1px solid #e2e8f0;
            box-shadow: 0 25px 60px rgba(15,23,42,0.06);
        }

        .section-header {
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .section-header h3 {
            margin: 0;
            font-size: 1rem;
        }

        .section-header .section-meta {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #f97316, #fb923c);
            color: white;
            border: none;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .task-list,
        .lead-list {
            list-style: none;
            margin: 0;
            padding: 0 24px 24px;
            display: grid;
            gap: 14px;
        }

        .task-item,
        .lead-item {
            padding: 16px 18px;
            border-radius: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            display: grid;
            gap: 10px;
        }

        .task-item .task-title,
        .lead-item .lead-name {
            margin: 0;
            font-weight: 700;
            font-size: 0.98rem;
        }

        .task-item .task-meta,
        .lead-item .lead-subtext {
            margin: 0;
            color: var(--muted);
            font-size: 0.88rem;
        }

        .task-item .task-chip,
        .lead-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.78rem;
            border-radius: 999px;
            padding: 6px 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: var(--text);
            justify-content: space-between;
        }

        .lead-item {
            grid-template-columns: 48px 1fr auto;
            align-items: center;
        }

        .lead-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f97316;
            color: white;
            font-weight: 700;
            font-size: 0.95rem;
            flex-shrink: 0;
        }

        .lead-summary {
            display: grid;
            gap: 4px;
        }

        .wallet-panel {
            display: flex;
            flex-direction: column;
        }

        .wallet-body {
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .wallet-balance {
            display: grid;
            gap: 8px;
        }

        .wallet-balance h3 {
            margin: 0;
            font-size: 2.4rem;
        }

        .wallet-balance p {
            margin: 0;
            color: var(--muted);
        }

        .wallet-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .wallet-summary {
            display: grid;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 18px;
        }

        .wallet-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
        }

        .wallet-row span:first-child {
            color: var(--muted);
        }

        .wallet-row span:last-child {
            font-weight: 700;
        }

        @media (max-width: 1140px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .content { margin-left: 0; width: 100%; }
            .dashboard-grid,
            .bottom-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">W</div>
                <div class="brand-text">
                    <h2>Walbrand</h2>
                    <span>Homes & Properties</span>
                </div>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="agent_dashboard.php" class="active"><span class="menu-icon">📊</span>Dashboard</a></li>
                    <li><a href="consultations.php"><span class="menu-icon">🧭</span>Leads</a></li>
                    <li><a href="my_properties.php"><span class="menu-icon">🏘️</span>Properties</a></li>
                    <li><a href="saved_properties.php"><span class="menu-icon">👥</span>Clients</a></li>
                    <li><a href="consultations.php?type=viewing"><span class="menu-icon">📅</span>Viewings</a></li>
                    <li><a href="admin_mover_bookings.php"><span class="menu-icon">🚚</span>Deliveries</a></li>
                    <li><a href="admin_properties.php"><span class="menu-icon">🤝</span>Offers & Negotiations</a></li>
                    <li><a href="consultations.php?tab=tasks"><span class="menu-icon">✅</span>Tasks</a></li>
                    <li><a href="agent_dashboard.php#messages"><span class="menu-icon">💬</span>Messages <span class="badge">3</span></a></li>
                    <li><a href="agent_dashboard.php#wallet"><span class="menu-icon">👛</span>Wallet</a></li>
                    <li><a href="admin_control_panel.php"><span class="menu-icon">📈</span>Reports</a></li>
                    <li><a href="index.php"><span class="menu-icon">📚</span>Resources</a></li>
                    <li><a href="admin_settings.php"><span class="menu-icon">⚙️</span>Settings</a></li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                Help & Support
            </div>
        </aside>

        <main class="content">
            <div class="topbar">
                <div class="topbar-left">
                    <button class="menu-toggle" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
                    <form class="topbar-search" action="consultations.php" method="get">
                        <input type="text" name="q" placeholder="Search for anything...">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                </div>

                <div class="topbar-right">
                    <a href="agent_dashboard.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; background: white; border-radius: 18px; border: 1px solid #e2e8f0; color: #0f172a; text-decoration: none; box-shadow: 0 10px 30px rgba(15,23,42,0.05); font-size: 0.95rem; font-weight: 600; transition: all 0.3s ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='white'">
                        ← Back to Home
                    </a>
                    <a href="agent_dashboard.php#messages" class="topbar-pill notification-pill">
                        <i class="fas fa-bell"></i>
                        Notifications
                        <span class="badge">5</span>
                    </a>
                    <a href="agent_dashboard.php" class="topbar-pill profile-pill">
                        <?php if ($avatar_url): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" alt="<?= htmlspecialchars($user_name) ?>">
                        <?php else: ?>
                            <span class="initials"><?= htmlspecialchars($avatar_initials) ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($first_name) ?>
                    </a>
                </div>
            </div>

            <a href="consultations.php" class="welcome-card card-link">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h1>Welcome Back, <?= htmlspecialchars($first_name) ?>! <span>👋</span></h1>
                        <p>Here’s what’s happening with your business today.</p>
                    </div>
                    <div class="welcome-stats">
                        <p style="font-size:0.92rem; opacity:0.9;">Performance summary updated 15 minutes ago.</p>
                    </div>
                </div>
            </a>

            <div class="stat-grid">
                <?php foreach ($stats as $stat): ?>
                    <a href="<?= htmlspecialchars($stat['link']) ?>" class="stat-card card-link">
                        <h3><?= htmlspecialchars($stat['value']) ?></h3>
                        <p><?= htmlspecialchars($stat['label']) ?></p>
                        <p style="margin-top:12px; color:#475569; font-size:0.92rem;"><?= htmlspecialchars($stat['meta']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="delivery-panel">
                <div class="section-header" style="padding:24px 24px 16px; gap: 12px;">
                    <div>
                        <h3>Delivery & Logistics Summary</h3>
                        <p class="section-meta">Active tracking, booking health, reviews, disputes and wallet overview.</p>
                    </div>
                    <a href="admin_mover_bookings.php" class="btn-primary">Open Delivery Console</a>
                </div>
                <div class="panel-body">
                    <?php if (!empty($mover_error)): ?>
                        <p style="color:#dc2626; padding:12px 24px 0; margin:0;"><?= htmlspecialchars($mover_error) ?></p>
                    <?php else: ?>
                        <div class="delivery-stats-grid">
                            <div class="delivery-metric">
                                <h4 style="color:#22c55e;"><?= htmlspecialchars(number_format($delivery_completed)) ?></h4>
                                <p>Completed Deliveries</p>
                            </div>
                            <div class="delivery-metric">
                                <h4 style="color:#f59e0b;"><?= htmlspecialchars(number_format($delivery_incomplete)) ?></h4>
                                <p>Incomplete Deliveries</p>
                            </div>
                            <div class="delivery-metric">
                                <h4 style="color:#dc2626;"><?= htmlspecialchars(number_format($delivery_rescheduled)) ?></h4>
                                <p>Rescheduled Deliveries</p>
                            </div>
                            <div class="delivery-metric">
                                <h4><?= htmlspecialchars(count($delivery_groups)) ?></h4>
                                <p>Delivery Groups</p>
                            </div>
                        </div>

                        <?php if (!empty($delivery_groups)): ?>
                            <div style="padding: 0 24px 24px;">
                                <h3 style="margin:0 0 14px 0; font-size:1rem; color:#0f172a;">Group Ranking (by Client Feedback)</h3>
                                <table class="booking-table">
                                    <thead>
                                        <tr>
                                            <th>Group Name</th>
                                            <th>Bookings</th>
                                            <th>Avg Rating</th>
                                            <th>Rank</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        usort($delivery_groups, function($a, $b) {
                                            return $b['avg_rating'] <=> $a['avg_rating'];
                                        });
                                        foreach ($delivery_groups as $index => $group): 
                                        ?>
                                            <tr>
                                                <td><?= htmlspecialchars($group['group_name']) ?></td>
                                                <td><?= htmlspecialchars($group['booking_count']) ?> bookings</td>
                                                <td>
                                                    <?php if ($group['avg_rating']): ?>
                                                        <span style="color:#22c55e;">★ <?= number_format($group['avg_rating'], 1) ?></span>
                                                    <?php else: ?>
                                                        <span style="color:#64748b;">No ratings</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>#<?= $index + 1 ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="section-header" style="padding:0 24px 24px 24px; gap: 12px; border-top:1px solid #e2e8f0; margin-top:0;">
                            <div>
                                <h3>Delivery Wallet</h3>
                                <p class="section-meta">Wallet accounts available for delivery operations</p>
                            </div>
                            <div style="font-size:1.1rem; font-weight:700;">KES <?= htmlspecialchars(number_format($mover_wallet_summary['balance'], 2)) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="panel">
                    <div class="panel-header">
                        <h2>Leads</h2>
                        <span><?= count($leads_list) ?> Potential Customers</span>
                    </div>
                    <div class="panel-body">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($leads_list)): ?>
                                    <?php foreach ($leads_list as $lead): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($lead['name']) ?></td>
                                            <td>
                                                <div style="font-size:0.9rem;">
                                                    <div><?= htmlspecialchars($lead['email']) ?></div>
                                                    <div style="color:#64748b;"><?= htmlspecialchars($lead['phone']) ?></div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $lead['consultation_type']))) ?></td>
                                            <td><span class="status-tag status-<?= htmlspecialchars($lead['status']) ?>"><?= htmlspecialchars(ucfirst($lead['status'])) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align:center; color:#64748b; padding:18px 0;">No leads available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Properties</h2>
                        <span>Total Properties Managed</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= htmlspecialchars(number_format($properties_uploaded)) ?></h3>
                                <p>Properties Uploaded</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#22c55e;"><?= htmlspecialchars(number_format($properties_successful)) ?></h3>
                                <p>Successful Connections</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#dc2626;"><?= htmlspecialchars(number_format($properties_rejected)) ?></h3>
                                <p>Rejected Properties</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <div class="task-panel">
                    <div class="section-header">
                        <div>
                            <h3>Tasks</h3>
                            <p class="section-meta">Completed: <?= $tasks_completed ?> • Incomplete: <?= $tasks_incomplete ?> • Pending: <?= $tasks_pending ?></p>
                        </div>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($tasks_by_type)): ?>
                            <?php foreach ($tasks_by_type as $type => $counts): ?>
                                <div class="task-category">
                                    <h4><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $type))) ?></h4>
                                    <div class="task-stats">
                                        <span class="completed">✓ <?= $counts['completed'] ?></span>
                                        <span class="incomplete">○ <?= $counts['incomplete'] + $counts['pending'] ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align:center; color:#64748b; padding:18px 0;">No tasks available.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="display:grid; gap:24px;">
                    <a href="consultations.php" class="leads-panel card-link">
                        <div class="section-header">
                            <div>
                                <h3>Recent Leads</h3>
                                <p class="section-meta">Latest prospects and outreach</p>
                            </div>
                        </div>
                        <ul class="lead-list">
                            <?php foreach ($recent_leads as $lead): ?>
                                <li class="lead-item">
                                    <div class="lead-avatar"><?= htmlspecialchars($lead['initials']) ?></div>
                                    <div class="lead-summary">
                                        <p class="lead-name"><?= htmlspecialchars($lead['name']) ?></p>
                                        <p class="lead-subtext"><?= htmlspecialchars($lead['location']) ?></p>
                                    </div>
                                    <div class="lead-status"><?= htmlspecialchars($lead['status']) ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </a>

                    <a href="agent_dashboard.php#wallet" class="wallet-panel card-link">
                        <div class="section-header" id="wallet">
                            <div>
                                <h3>Wallet Balance</h3>
                            </div>
                        </div>
                        <div class="wallet-body">
                            <div class="wallet-balance">
                                <p>Available Balance</p>
                                <h3><?= htmlspecialchars($wallet['balance']) ?></h3>
                            </div>
                            <div class="wallet-actions">
                                <span class="btn-primary">Withdraw</span>
                                <span class="btn-primary" style="background:#1d4ed8;">Transaction History</span>
                            </div>
                            <div class="wallet-summary">
                                <div class="wallet-row"><span>Total Earnings (This Month)</span><span><?= htmlspecialchars($wallet['earnings']) ?></span></div>
                                <div class="wallet-row"><span>Pending Payout</span><span><?= htmlspecialchars($wallet['pending']) ?></span></div>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Clients</h2>
                        <span>Client Statistics</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= htmlspecialchars(number_format($clients_connected)) ?></h3>
                                <p>Clients Connected</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#dc2626;"><?= htmlspecialchars(number_format($clients_complained)) ?></h3>
                                <p>Clients Complained</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#22c55e;"><?= htmlspecialchars(number_format($clients_happy)) ?></h3>
                                <p>Clients Happy</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#f59e0b;"><?= htmlspecialchars(number_format($clients_failed_pick)) ?></h3>
                                <p>Failed to Pick House</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>Viewings</h2>
                        <span>Viewing Statistics</span>
                    </div>
                    <div class="panel-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <h3><?= htmlspecialchars(number_format($viewings_assigned)) ?></h3>
                                <p>Views Assigned by Admin</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#22c55e;"><?= htmlspecialchars(number_format($viewings_completed)) ?></h3>
                                <p>Completed Views</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#f59e0b;"><?= htmlspecialchars(number_format($viewings_pending)) ?></h3>
                                <p>Pending Views</p>
                            </div>
                            <div class="stat-item">
                                <h3 style="color:#dc2626;"><?= htmlspecialchars(number_format($viewings_expired)) ?></h3>
                                <p>Expired Views</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel" id="messages">
                    <div class="panel-header">
                        <h2>Messages</h2>
                        <span>Admin Communications</span>
                    </div>
                    <div class="panel-body">
                        <?php if (!empty($messages)): ?>
                            <ul class="message-list">
                                <?php foreach ($messages as $msg): ?>
                                    <li class="message-item">
                                        <div class="message-content">
                                            <h4><?= htmlspecialchars($msg['title']) ?></h4>
                                            <p><?= htmlspecialchars($msg['message']) ?></p>
                                            <small style="color:#64748b;"><?= htmlspecialchars(date('M j, Y H:i', strtotime($msg['created_at']))) ?></small>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="text-align:center; color:#64748b; padding:18px 0;">No messages from admin.</p>
                        <?php endif; ?>
                        <div style="padding-top:16px; border-top:1px solid #e2e8f0;">
                            <button class="btn-primary" onclick="showMessageForm()">Send Message to Admin</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.addEventListener('load', function () {
            var ctx = document.getElementById('leadsPerformanceChart');
            if (ctx) {
                var gradients = ctx.getContext('2d').createLinearGradient(0, 0, 0, 320);
                gradients.addColorStop(0, 'rgba(249, 115, 22, 0.38)');
                gradients.addColorStop(1, 'rgba(249, 115, 22, 0.05)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($leads_performance_labels) ?>,
                        datasets: [{
                            label: 'Leads',
                            data: <?= json_encode($leads_performance_data) ?>,
                            borderColor: '#f97316',
                            backgroundColor: gradients,
                            fill: true,
                            tension: 0.35,
                            pointRadius: 4,
                            pointBackgroundColor: '#f97316',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 6,
                            pointHoverBackgroundColor: '#ffedd5'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#111827',
                                titleColor: '#ffffff',
                                bodyColor: '#f8fafc',
                                borderColor: 'rgba(255,255,255,0.08)',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#475569' }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(226,232,240,0.8)' },
                                ticks: { color: '#475569' }
                            }
                        }
                    }
                });
            }

            var leadSourcesCtx = document.getElementById('leadSourcesChart');
            if (leadSourcesCtx) {
                var sourceLabels = <?= json_encode(array_column($lead_sources, 'label')) ?>;
                var sourceValues = <?= json_encode(array_column($lead_sources, 'value')) ?>;
                var sourceColors = <?= json_encode(array_column($lead_sources, 'color')) ?>;

                new Chart(leadSourcesCtx, {
                    type: 'doughnut',
                    data: {
                        labels: sourceLabels,
                        datasets: [{
                            data: sourceValues,
                            backgroundColor: sourceColors,
                            borderColor: '#ffffff',
                            borderWidth: 2,
                            hoverOffset: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: '#475569',
                                    boxWidth: 12,
                                    boxHeight: 12
                                }
                            },
                            tooltip: {
                                backgroundColor: '#111827',
                                titleColor: '#ffffff',
                                bodyColor: '#f8fafc',
                                borderColor: 'rgba(255,255,255,0.08)',
                                borderWidth: 1
                            }
                        }
                    }
                });
            }
        });

        function showMessageForm() {
            var message = prompt('Enter your message to admin:');
            if (message && message.trim()) {
                // Send message via AJAX or form submission
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'send_message_to_admin.php'; // Create this handler
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'message';
                input.value = message.trim();
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
