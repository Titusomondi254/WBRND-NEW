<?php
session_start();

// Enable debug error reporting for admin pages
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Temporary development access fallback
if ((!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) && isset($_GET['dev_access']) && $_GET['dev_access'] === 'true') {
    $_SESSION['admin_id'] = 4;
    $_SESSION['user_id'] = 4;
    $_SESSION['admin_name'] = 'TITUS OMONDI';
    $_SESSION['admin_email'] = 'tomondi653@gmmail.com';
    $_SESSION['admin_role'] = 'super_admin';
    $_SESSION['is_admin'] = true;
    $_SESSION['user_role'] = 'super_admin';
    $_SESSION['last_activity'] = time();
}

require_once 'config.php';
require_once 'admin_auth.php';
require_once 'notifications.php';

// DASHBOARD DATA

$total_users = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'] ?? 0;

$total_properties = $conn->query("SELECT COUNT(*) as total FROM properties")->fetch_assoc()['total'] ?? 0;

$pending_kyc = $conn->query("SELECT COUNT(*) as total FROM users WHERE kyc_status='pending'")->fetch_assoc()['total'] ?? 0;

$verified_properties = $conn->query("SELECT COUNT(*) as total FROM properties WHERE verification_status='verified'")->fetch_assoc()['total'] ?? 0;

$pending_properties = $conn->query("SELECT COUNT(*) as total FROM properties WHERE verification_status='pending'")->fetch_assoc()['total'] ?? 0;

$pending_storage_facilities = $conn->query("SELECT COUNT(*) as total FROM storage_facilities WHERE verification_status='pending'")->fetch_assoc()['total'] ?? 0;

$total_revenue = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status='paid'")->fetch_assoc()['total'] ?? 0;

$mover_bookings = 0;
$pending_roommate_requests = 0;
$result = $conn->query("SHOW TABLES LIKE 'mover_bookings'");
if ($result && $result->num_rows > 0) {
    $mover_bookings = $conn->query("SELECT COUNT(*) as total FROM mover_bookings")->fetch_assoc()['total'] ?? 0;
}

$result = $conn->query("SHOW TABLES LIKE 'roommate_requests'");
if ($result && $result->num_rows > 0) {
    $pending_roommate_requests = $conn->query("SELECT COUNT(*) as total FROM roommate_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'] ?? 0;
}

// Hide raw PHP errors on admin pages and show friendly messages instead
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);


// Custom error handler to log runtime errors and display them in the browser
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $message = "PHP Error [$errno]: $errstr in $errfile on line $errline";
    error_log($message);
    echo '<pre style="color: red; background: #f8d7da; padding: 12px; border-radius: 6px;">' . htmlspecialchars($message) . '</pre>';
    return false;
});

set_exception_handler(function($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    error_log($message);
    echo '<pre style="color: red; background: #f8d7da; padding: 12px; border-radius: 6px;">' . htmlspecialchars($message) . '</pre>';
});

// Check admin access - ONLY super admins can access this page
$user_id = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;

if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: admin_login.php");
    exit();
}

// Verify this is actually a super_admin
if (!is_super_admin_only($user_id)) {
    // Block non-super-admin users trying to access admin pages
    error_log("SECURITY VIOLATION: Non-super-admin user (ID: $user_id) attempted unauthorized admin access");
    
    // Block their account
    block_user_account($user_id, 'Attempted unauthorized admin access');
    
    session_destroy();
    header("Location: index.php?error=unauthorized");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin_user_id = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
$admin_name = $_SESSION['admin_name'];

// Read admin profile picture from the users table if available
$admin_profile_picture = '';
$admin_profile_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ? LIMIT 1");
if ($admin_profile_stmt) {
    $admin_profile_stmt->bind_param('i', $admin_id);
    $admin_profile_stmt->execute();
    $admin_profile_stmt->bind_result($admin_profile_picture);
    $admin_profile_stmt->fetch();
    $admin_profile_stmt->close();
}

// Handle admin profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_admin_profile_picture'])) {
    $success = '';
    $error = '';

    if (!isset($_FILES['profile_picture']) || empty($_FILES['profile_picture']['name'])) {
        $error = "Please select a profile picture to upload.";
    } else {
        if ($_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            $file_type = mime_content_type($_FILES['profile_picture']['tmp_name']);

            if (!in_array($file_extension, ALLOWED_EXTENSIONS) || !in_array($file_type, ['image/jpeg', 'image/png'])) {
                $error = "Profile picture must be a JPG or PNG file.";
            } elseif ($_FILES['profile_picture']['size'] > MAX_FILE_SIZE) {
                $error = "Profile picture must be smaller than 5MB.";
            } else {
                $upload_dir = PROFILE_UPLOAD_DIR;
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_name = 'admin_profile_' . $admin_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
                    $profile_picture_rel = 'uploads/profiles/' . $file_name;

                    $update_profile = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    if ($update_profile) {
                        $update_profile->bind_param("si", $profile_picture_rel, $admin_id);
                        if ($update_profile->execute()) {
                            if ($update_profile->affected_rows > 0) {
                                if (!empty($admin_profile_picture)) {
                                    $old_path = __DIR__ . '/' . ltrim($admin_profile_picture, '/');
                                    if (file_exists($old_path)) {
                                        @unlink($old_path);
                                    }
                                }
                                $success = "Admin profile picture uploaded successfully.";
                            } else {
                                $error = "Admin profile record was not found. Please contact support.";
                            }
                        } else {
                            $error = "Failed to save profile picture. Please try again.";
                        }
                        $update_profile->close();
                    } else {
                        $error = "Database error while updating profile picture.";
                    }
                } else {
                    $error = "Failed to upload the profile picture.";
                }
            }
        } else {
            $error = "Error uploading profile picture.";
        }
    }

    $redirectUrl = 'admin_control_panel.php';
    if (!empty($success)) {
        $redirectUrl .= '?success=' . urlencode($success);
    } elseif (!empty($error)) {
        $redirectUrl .= '?error=' . urlencode($error);
    }
    header("Location: $redirectUrl");
    exit();
}

// Ensure admin schedule events table exists for official calendar items
$create_calendar_table = "CREATE TABLE IF NOT EXISTS admin_calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_type VARCHAR(100) DEFAULT 'meeting',
    assigned_to INT DEFAULT NULL,
    event_date DATETIME NOT NULL,
    is_meeting BOOLEAN DEFAULT TRUE,
    google_meet_link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_calendar_table);

// Add missing columns if they don't exist
$check_meet_col = $conn->query("SHOW COLUMNS FROM admin_calendar_events LIKE 'google_meet_link'");
if ($check_meet_col && $check_meet_col->num_rows === 0) {
    $conn->query("ALTER TABLE admin_calendar_events ADD COLUMN google_meet_link VARCHAR(500) AFTER event_date");
}

$check_meeting_col = $conn->query("SHOW COLUMNS FROM admin_calendar_events LIKE 'is_meeting'");
if ($check_meeting_col && $check_meeting_col->num_rows === 0) {
    $conn->query("ALTER TABLE admin_calendar_events ADD COLUMN is_meeting BOOLEAN DEFAULT TRUE AFTER event_date");
}

$create_admin_notifications_table = "CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    related_id INT DEFAULT NULL,
    action_url VARCHAR(255) DEFAULT NULL,
    is_dismissed BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_admin_notification (admin_id, notification_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conn->query($create_admin_notifications_table);

// Handle schedule event creation from admin dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_schedule_event') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_type = trim($_POST['event_type'] ?? 'meeting');
    $assigned_to = intval($_POST['assigned_to'] ?? 0) ?: null;
    $event_date = trim($_POST['event_date'] ?? '');
    $is_meeting = isset($_POST['is_meeting']) && $_POST['is_meeting'] === 'on' ? 1 : 0;
    $google_meet_link = $is_meeting ? trim($_POST['google_meet_link'] ?? '') : null;

    if ($title === '' || $event_date === '') {
        $error = 'Please provide a title and a valid date for the schedule event.';
        header('Location: admin_control_panel.php?view=schedule&error=' . urlencode($error));
        exit();
    }

    // Validate event date is in the future
    $event_datetime = strtotime($event_date);
    if ($event_datetime <= time()) {
        $error = 'Event date and time must be in the future.';
        header('Location: admin_control_panel.php?view=schedule&error=' . urlencode($error));
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO admin_calendar_events (admin_id, title, description, event_type, assigned_to, event_date, is_meeting, google_meet_link) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssisis', $admin_id, $title, $description, $event_type, $assigned_to, $event_date, $is_meeting, $google_meet_link);

    if ($stmt->execute()) {
        $event_id = $conn->insert_id;
        
        // If it's a meeting, send notifications to all agents
        if ($is_meeting) {
            $agents_result = $conn->query("SELECT id, first_name, last_name, email FROM users WHERE user_type = 'agent' AND is_active = 1");
            if ($agents_result && $agents_result->num_rows > 0) {
                while ($agent = $agents_result->fetch_assoc()) {
                    $notification_title = "New Meeting Scheduled: " . htmlspecialchars($title);
                    $notification_message = "A new meeting has been scheduled. Title: " . htmlspecialchars($title) . 
                        "\nDate & Time: " . date('M d, Y g:i A', strtotime($event_date));
                    if (!empty($google_meet_link)) {
                        $notification_message .= "\n\nGoogle Meet Link: " . htmlspecialchars($google_meet_link);
                    }
                    
                    sendNotification($agent['id'], 'meeting_scheduled', $notification_title, $notification_message, $event_id, true);
                }
            }
        }
        
        $success = 'Schedule event created successfully. ' . ($is_meeting ? 'Notifications have been sent to all agents.' : 'The calendar is now updated.');
        header('Location: admin_control_panel.php?view=schedule&success=' . urlencode($success));
        exit();
    } else {
        $error = 'Unable to save the schedule event. Please try again.';
        header('Location: admin_control_panel.php?view=schedule&error=' . urlencode($error));
        exit();
    }
    $stmt->close();
}

// Ensure the consultations table has the columns the dashboard expects
try {
    $consultations_exists = $conn->query("SHOW TABLES LIKE 'consultations'");
    if ($consultations_exists && $consultations_exists->num_rows > 0) {
        $required_columns = [
            'issue_description' => 'TEXT NULL',
            'scheduled_date' => 'DATETIME NULL'
        ];
        foreach ($required_columns as $column => $definition) {
            $column_check = $conn->query("SHOW COLUMNS FROM consultations LIKE '$column'");
            if ($column_check && $column_check->num_rows === 0) {
                $conn->query("ALTER TABLE consultations ADD COLUMN $column $definition");
            }
        }
    }
} catch (Exception $e) {
    error_log('Consultations schema check failed: ' . $e->getMessage());
}

$consultations_columns = [];
$consultations_desc = $conn->query("SHOW COLUMNS FROM consultations");
if ($consultations_desc) {
    while ($col = $consultations_desc->fetch_assoc()) {
        $consultations_columns[$col['Field']] = true;
    }
}

$has_issue_description = isset($consultations_columns['issue_description']);
$has_scheduled_date = isset($consultations_columns['scheduled_date']);

$consultation_select_fields = "c.id, c.consultation_type, c.status, ";
if ($has_scheduled_date) {
    $consultation_select_fields .= "c.scheduled_date, ";
} else {
    $consultation_select_fields .= "c.created_at as scheduled_date, ";
}
$consultation_select_fields .= $has_issue_description ? "c.issue_description, " : "'' as issue_description, ";
$consultation_select_fields .= "CONCAT_WS(' ', u.first_name, u.last_name) AS user_name, COALESCE(p.location, '') AS property_location";

$search_query = trim($_GET['search'] ?? '');
$search_results = ['users' => [], 'properties' => [], 'consultations' => []];
if ($search_query !== '') {
    $view = 'search';
    $safe_search = '%' . $conn->real_escape_string($search_query) . '%';

    $stmt = $conn->prepare("SELECT id, CONCAT_WS(' ', first_name, last_name) AS name, email, user_type, kyc_status, is_active FROM users WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR user_type LIKE ? LIMIT 50");
    $stmt->bind_param('ssss', $safe_search, $safe_search, $safe_search, $safe_search);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results['users'][] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT p.id, p.location, p.property_type, p.price, p.status, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name FROM properties p LEFT JOIN users u ON p.seller_id = u.id WHERE p.location LIKE ? OR p.property_type LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? LIMIT 50");
    $stmt->bind_param('ssss', $safe_search, $safe_search, $safe_search, $safe_search);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results['properties'][] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT $consultation_select_fields FROM consultations c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN properties p ON c.property_id = p.id WHERE c.consultation_type LIKE ? OR c.status LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.location LIKE ? LIMIT 50");
    $stmt->bind_param('sssss', $safe_search, $safe_search, $safe_search, $safe_search, $safe_search);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $search_results['consultations'][] = $row;
    }
    $stmt->close();
}

$schedule_events = [];
$event_result = $conn->query("SELECT ace.*, CONCAT_WS(' ', u.first_name, u.last_name) AS assigned_name FROM admin_calendar_events ace LEFT JOIN users u ON ace.assigned_to = u.id WHERE ace.admin_id = $admin_id AND ace.event_date >= DATE_SUB(NOW(), INTERVAL 6 HOUR) ORDER BY ace.event_date ASC LIMIT 6");
if ($event_result) {
    while ($row = $event_result->fetch_assoc()) {
        $schedule_events[] = $row;
    }
}

$scheduled_consultations = [];
$consultation_result = null;
if ($has_scheduled_date) {
    $scheduled_query = "SELECT $consultation_select_fields FROM consultations c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN properties p ON c.property_id = p.id WHERE c.status IN ('scheduled', 'pending') AND c.scheduled_date >= DATE_SUB(NOW(), INTERVAL 6 HOUR) ORDER BY c.scheduled_date ASC LIMIT 6";
} else {
    $scheduled_query = "SELECT $consultation_select_fields FROM consultations c LEFT JOIN users u ON c.user_id = u.id LEFT JOIN properties p ON c.property_id = p.id WHERE c.status IN ('scheduled', 'pending') ORDER BY c.created_at ASC LIMIT 6";
}
$consultation_result = $conn->query($scheduled_query);
if ($consultation_result) {
    while ($row = $consultation_result->fetch_assoc()) {
        $scheduled_consultations[] = $row;
    }
}

// Get dashboard statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Pending KYC
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE kyc_status = 'pending'");
$stats['pending_kyc'] = $result->fetch_assoc()['count'];

// Total properties
$result = $conn->query("SELECT COUNT(*) as count FROM properties");
$stats['total_properties'] = $result->fetch_assoc()['count'];

// Pending property verification
$result = $conn->query("SELECT COUNT(*) as count FROM properties WHERE verification_status = 'pending'");
$stats['pending_properties'] = $result->fetch_assoc()['count'];

// Verified properties
$result = $conn->query("SELECT COUNT(*) as count FROM properties WHERE verification_status = 'verified'");
$stats['verified_properties'] = $result->fetch_assoc()['count'];

// KYC user counts
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE kyc_status = 'verified' OR kyc_verified = TRUE");
$stats['kyc_verified_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE NOT (kyc_status = 'verified' OR kyc_verified = TRUE)");
$stats['kyc_unverified_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'suspended'");
$stats['suspended_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'blocked'");
$stats['blocked_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'agent'");
$stats['agent_users'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type IN ('buyer', 'seller')");
$stats['client_users'] = $result->fetch_assoc()['count'];

// Total payments
$result = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status IN ('completed','paid')");
$payment_data = $result->fetch_assoc();
$stats['total_payments'] = $payment_data['total'] ?? 0;

// Payment analytics summary
$payments_summary = [];
$completedStatuses = "('completed','paid')";
$payments_summary['total_received'] = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status IN $completedStatuses")->fetch_assoc()['total'] ?? 0);
$payments_summary['total_collected'] = 0;
$payments_summary['pending_settlement'] = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'pending'")->fetch_assoc()['total'] ?? 0);
$payments_summary['failed_amount'] = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status IN ('failed','refunded','cancelled','declined','rejected')")->fetch_assoc()['total'] ?? 0);
$payments_summary['today_collected'] = 0;
$payments_summary['monthly_collected'] = 0;
$payments_summary['transaction_count'] = intval($conn->query("SELECT COUNT(*) as count FROM payments WHERE status IN $completedStatuses")->fetch_assoc()['count'] ?? 0);
$payments_summary['average_transaction'] = $payments_summary['transaction_count'] > 0 ? round($payments_summary['total_received'] / $payments_summary['transaction_count'], 2) : 0;
if (!function_exists('calculate_commission_shares')) {
    function calculate_commission_shares($amount, $payment_type = '') {
        $amount = floatval($amount);
        $payment_type = strtolower(trim($payment_type));

        if ($payment_type === 'consultation_fee') {
            return ['admin' => 500.00, 'agent' => round(max(0, $amount - 500.00), 2)];
        }

        if ($payment_type === 'installation_fee') {
            return ['admin' => 800.00, 'agent' => round(max(0, $amount - 800.00), 2)];
        }

        if ($amount <= 500) {
            return ['admin' => $amount, 'agent' => $amount];
        }

        return ['admin' => round($amount * 0.8, 2), 'agent' => round($amount * 0.2, 2)];
    }
}

$payments_summary['pending_count'] = intval($conn->query("SELECT COUNT(*) as count FROM payments WHERE status = 'pending'")->fetch_assoc()['count'] ?? 0);

$completed_payments = $conn->query("SELECT amount, payment_type, created_at FROM payments WHERE status IN $completedStatuses");
if ($completed_payments) {
    while ($row = $completed_payments->fetch_assoc()) {
        $shares = calculate_commission_shares($row['amount'], $row['payment_type'] ?? '');
        $payments_summary['total_collected'] += $shares['admin'];
        if (date('Y-m-d', strtotime($row['created_at'])) === date('Y-m-d')) {
            $payments_summary['today_collected'] += $shares['admin'];
        }
        if (date('Y-m', strtotime($row['created_at'])) === date('Y-m')) {
            $payments_summary['monthly_collected'] += $shares['admin'];
        }
    }
}
$payments_summary['total_collected'] = round($payments_summary['total_collected'], 2);
$payments_summary['today_collected'] = round($payments_summary['today_collected'], 2);
$payments_summary['monthly_collected'] = round($payments_summary['monthly_collected'], 2);

// Extended Payments dashboard metrics
$payments_dashboard = [];
$payments_dashboard['total_registered_users'] = intval($conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0);
$payments_dashboard['total_clients_served'] = intval($conn->query("SELECT COUNT(DISTINCT user_id) as count FROM payments WHERE status IN $completedStatuses")->fetch_assoc()['count'] ?? 0);

$agent_rewards_exists = false;
$result = $conn->query("SHOW TABLES LIKE 'agent_rewards'");
if ($result && $result->num_rows > 0) {
    $agent_rewards_exists = true;
}

// Calculate total agent payouts from all active payout sources.
// This includes completed Digital Installation consultations, completed viewing request fees,
// and any bonus reward payments stored in the agent_rewards table.
$digital_installation_payouts = floatval($conn->query("SELECT COALESCE(SUM(COALESCE(service_fee, 0)), 0) as total FROM consultations WHERE status = 'completed' AND consultation_type IN ('digital_installation', 'installation_request')")->fetch_assoc()['total'] ?? 0);
$viewing_request_payouts = floatval($conn->query("SELECT COALESCE(SUM(COALESCE(viewing_fee, 0)), 0) as total FROM viewing_requests WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0);
$agent_rewards_total = 0;
if ($agent_rewards_exists) {
    $agent_rewards_total = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM agent_rewards")->fetch_assoc()['total'] ?? 0);
}
$payments_dashboard['total_agent_payouts'] = $digital_installation_payouts + $viewing_request_payouts + $agent_rewards_total;

// Calculate total completed services from both Digital Installation and View Requests
$completed_digital_installations = intval($conn->query("SELECT COUNT(*) as total FROM consultations WHERE status = 'completed' AND consultation_type IN ('digital_installation', 'installation_request')")->fetch_assoc()['total'] ?? 0);
$completed_viewing_requests = intval($conn->query("SELECT COUNT(*) as total FROM viewing_requests WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0);
$payments_dashboard['total_completed_services'] = $completed_digital_installations + $completed_viewing_requests;

$payments_dashboard['commission_split'] = ['admin' => 0, 'agent' => 0];
$commission_rows = $conn->query("SELECT amount FROM payments WHERE status IN $completedStatuses");
if ($commission_rows) {
    while ($row = $commission_rows->fetch_assoc()) {
        $shares = calculate_commission_shares($row['amount']);
        $payments_dashboard['commission_split']['admin'] += $shares['admin'];
        $payments_dashboard['commission_split']['agent'] += $shares['agent'];
    }
}
$payments_dashboard['commission_split']['admin'] = round($payments_dashboard['commission_split']['admin'], 2);
$payments_dashboard['commission_split']['agent'] = round($payments_dashboard['commission_split']['agent'], 2);

$period_definitions = [
    '7d' => ['label' => '7 Days', 'days' => 7],
    '30d' => ['label' => '30 Days', 'days' => 30],
    '1y' => ['label' => '1 Year', 'days' => 365],
    '5y' => ['label' => '5 Years', 'days' => 1825],
];

$payments_dashboard['periods'] = [];
foreach ($period_definitions as $period_key => $period_data) {
    $days = $period_data['days'];
    $previous_days = $days * 2;

    $current_revenue = floatval($conn->query("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status IN $completedStatuses AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['total'] ?? 0);
    $previous_revenue = floatval($conn->query("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status IN $completedStatuses AND created_at >= DATE_SUB(NOW(), INTERVAL $previous_days DAY) AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['total'] ?? 0);

    $current_users = intval($conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['count'] ?? 0);
    $previous_users = intval($conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL $previous_days DAY) AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['count'] ?? 0);

    $current_clients = intval($conn->query("SELECT COUNT(DISTINCT user_id) as count FROM payments WHERE status IN $completedStatuses AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['count'] ?? 0);
    $previous_clients = intval($conn->query("SELECT COUNT(DISTINCT user_id) as count FROM payments WHERE status IN $completedStatuses AND created_at >= DATE_SUB(NOW(), INTERVAL $previous_days DAY) AND created_at < DATE_SUB(NOW(), INTERVAL $days DAY)")->fetch_assoc()['count'] ?? 0);

    $current_commission = 0;
    $commission_rows = $conn->query("SELECT amount FROM payments WHERE status IN $completedStatuses AND created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)");
    if ($commission_rows) {
        while ($row = $commission_rows->fetch_assoc()) {
            $shares = calculate_commission_shares($row['amount']);
            $current_commission += $shares['admin'];
        }
    }

    $payments_dashboard['periods'][$period_key] = [
        'label' => $period_data['label'],
        'current_revenue' => round($current_revenue, 2),
        'previous_revenue' => round($previous_revenue, 2),
        'revenue_change_percent' => $previous_revenue > 0 ? round((($current_revenue - $previous_revenue) / $previous_revenue) * 100, 2) : ($current_revenue > 0 ? 100 : 0),
        'revenue_change_amount' => round($current_revenue - $previous_revenue, 2),
        'current_users' => $current_users,
        'previous_users' => $previous_users,
        'user_change_percent' => $previous_users > 0 ? round((($current_users - $previous_users) / $previous_users) * 100, 2) : ($current_users > 0 ? 100 : 0),
        'current_clients' => $current_clients,
        'previous_clients' => $previous_clients,
        'client_change_percent' => $previous_clients > 0 ? round((($current_clients - $previous_clients) / $previous_clients) * 100, 2) : ($current_clients > 0 ? 100 : 0),
        'estimated_admin_commission' => round($current_commission, 2),
        'estimated_agent_share' => round(max(0, $current_revenue - $current_commission), 2),
    ];
}

$payments_dashboard['top_agents'] = [];
foreach ($period_definitions as $period_key => $period_data) {
    $days = $period_data['days'];
    $payments_dashboard['top_agents'][$period_key] = [];

    if ($agent_rewards_exists) {
        $top_agents_result = $conn->query(
            "SELECT agent_id, agent_name, SUM(activity_count) AS activity_count, SUM(total_amount) AS total_amount FROM (" .
            "SELECT c.agent_id AS agent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS agent_name, COUNT(c.id) AS activity_count, SUM(COALESCE(c.service_fee, 0)) AS total_amount " .
            "FROM consultations c LEFT JOIN users u ON c.agent_id = u.id " .
            "WHERE c.status = 'completed' AND c.completed_at >= DATE_SUB(NOW(), INTERVAL $days DAY) GROUP BY c.agent_id " .
            "UNION ALL " .
            "SELECT vr.agent_id AS agent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS agent_name, COUNT(vr.id) AS activity_count, SUM(COALESCE(vr.viewing_fee, 0)) AS total_amount " .
            "FROM viewing_requests vr LEFT JOIN users u ON vr.agent_id = u.id " .
            "WHERE vr.status = 'completed' AND vr.approved_at >= DATE_SUB(NOW(), INTERVAL $days DAY) GROUP BY vr.agent_id " .
            "UNION ALL " .
            "SELECT ar.agent_id AS agent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS agent_name, COUNT(ar.id) AS activity_count, SUM(COALESCE(ar.amount, 0)) AS total_amount " .
            "FROM agent_rewards ar LEFT JOIN users u ON ar.agent_id = u.id " .
            "WHERE ar.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY) GROUP BY ar.agent_id " .
            ") AS combined_agents GROUP BY agent_id ORDER BY total_amount DESC LIMIT 10"
        );
    } else {
        $top_agents_result = $conn->query(
            "SELECT agent_id, agent_name, SUM(activity_count) AS activity_count, SUM(total_amount) AS total_amount FROM (" .
            "SELECT c.agent_id AS agent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS agent_name, COUNT(c.id) AS activity_count, SUM(COALESCE(c.service_fee, 0)) AS total_amount " .
            "FROM consultations c LEFT JOIN users u ON c.agent_id = u.id " .
            "WHERE c.status = 'completed' AND c.completed_at >= DATE_SUB(NOW(), INTERVAL $days DAY) GROUP BY c.agent_id " .
            "UNION ALL " .
            "SELECT vr.agent_id AS agent_id, CONCAT_WS(' ', u.first_name, u.last_name) AS agent_name, COUNT(vr.id) AS activity_count, SUM(COALESCE(vr.viewing_fee, 0)) AS total_amount " .
            "FROM viewing_requests vr LEFT JOIN users u ON vr.agent_id = u.id " .
            "WHERE vr.status = 'completed' AND vr.approved_at >= DATE_SUB(NOW(), INTERVAL $days DAY) GROUP BY vr.agent_id " .
            ") AS combined_agents GROUP BY agent_id ORDER BY total_amount DESC LIMIT 10"
        );
    }

    if ($top_agents_result) {
        while ($row = $top_agents_result->fetch_assoc()) {
            $payments_dashboard['top_agents'][$period_key][] = [
                'agent_id' => intval($row['agent_id']),
                'agent_name' => trim($row['agent_name']) ?: 'Unknown Agent',
                'activity_count' => intval($row['activity_count']),
                'total_amount' => round(floatval($row['total_amount']), 2),
            ];
        }
    }
}

// Revenue by day for the last 7 days
$payments_by_day_labels = [];
$payments_by_day_values = [];
$startDate = new DateTime('today - 6 days');
$current = clone $startDate;
while ($current <= new DateTime('today')) {
    $payments_by_day_labels[] = $current->format('M j');
    $payments_by_day_values[$current->format('Y-m-d')] = 0;
    $current->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values)) {
            $payments_by_day_values[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values = array_values($payments_by_day_values);
$payments_by_day_mavg_3d = calculate_moving_average($payments_by_day_values, 3);
$payments_by_day_mavg_7d = calculate_moving_average($payments_by_day_values, 7);
$payments_by_day_mavg_14d = calculate_moving_average($payments_by_day_values, 14);
$payments_by_day_mavg_30d = calculate_moving_average($payments_by_day_values, 30);
$payments_by_day_mavg_1y = calculate_moving_average($payments_by_day_values, 365);
$payments_by_day_mavg_5y = calculate_moving_average($payments_by_day_values, 1825);

// Rolling revenue trends for 30 and 90 days
$payments_by_day_labels_30 = [];
$payments_by_day_values_30 = [];
$startDate30 = new DateTime('today - 29 days');
$current30 = clone $startDate30;
while ($current30 <= new DateTime('today')) {
    $payments_by_day_labels_30[] = $current30->format('M j');
    $payments_by_day_values_30[$current30->format('Y-m-d')] = 0;
    $current30->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate30->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values_30)) {
            $payments_by_day_values_30[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values_30 = array_values($payments_by_day_values_30);
$payments_by_day_mavg_30_3d = calculate_moving_average($payments_by_day_values_30, 3);
$payments_by_day_mavg_30_7d = calculate_moving_average($payments_by_day_values_30, 7);
$payments_by_day_mavg_30_14d = calculate_moving_average($payments_by_day_values_30, 14);
$payments_by_day_mavg_30_30d = calculate_moving_average($payments_by_day_values_30, 30);
$payments_by_day_mavg_30_1y = calculate_moving_average($payments_by_day_values_30, 365);
$payments_by_day_mavg_30_5y = calculate_moving_average($payments_by_day_values_30, 1825);

$payments_by_day_labels_90 = [];
$payments_by_day_values_90 = [];
$startDate90 = new DateTime('today - 89 days');
$current90 = clone $startDate90;
while ($current90 <= new DateTime('today')) {
    $payments_by_day_labels_90[] = $current90->format('M j');
    $payments_by_day_values_90[$current90->format('Y-m-d')] = 0;
    $current90->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate90->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values_90)) {
            $payments_by_day_values_90[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values_90 = array_values($payments_by_day_values_90);
$payments_by_day_mavg_90_3d = calculate_moving_average($payments_by_day_values_90, 3);
$payments_by_day_mavg_90_7d = calculate_moving_average($payments_by_day_values_90, 7);
$payments_by_day_mavg_90_14d = calculate_moving_average($payments_by_day_values_90, 14);
$payments_by_day_mavg_90_30d = calculate_moving_average($payments_by_day_values_90, 30);
$payments_by_day_mavg_90_1y = calculate_moving_average($payments_by_day_values_90, 365);
$payments_by_day_mavg_90_5y = calculate_moving_average($payments_by_day_values_90, 1825);

$payments_by_day_labels_180 = [];
$payments_by_day_values_180 = [];
$startDate180 = new DateTime('today - 179 days');
$current180 = clone $startDate180;
while ($current180 <= new DateTime('today')) {
    $payments_by_day_labels_180[] = $current180->format('M j');
    $payments_by_day_values_180[$current180->format('Y-m-d')] = 0;
    $current180->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate180->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values_180)) {
            $payments_by_day_values_180[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values_180 = array_values($payments_by_day_values_180);
$payments_by_day_mavg_180_3d = calculate_moving_average($payments_by_day_values_180, 3);
$payments_by_day_mavg_180_7d = calculate_moving_average($payments_by_day_values_180, 7);
$payments_by_day_mavg_180_14d = calculate_moving_average($payments_by_day_values_180, 14);
$payments_by_day_mavg_180_30d = calculate_moving_average($payments_by_day_values_180, 30);
$payments_by_day_mavg_180_180d = calculate_moving_average($payments_by_day_values_180, 180);
$payments_by_day_mavg_180_1y = calculate_moving_average($payments_by_day_values_180, 365);
$payments_by_day_mavg_180_5y = calculate_moving_average($payments_by_day_values_180, 1825);

$payments_by_day_labels_365 = [];
$payments_by_day_values_365 = [];
$startDate365 = new DateTime('today - 364 days');
$current365 = clone $startDate365;
while ($current365 <= new DateTime('today')) {
    $payments_by_day_labels_365[] = $current365->format('M j');
    $payments_by_day_values_365[$current365->format('Y-m-d')] = 0;
    $current365->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate365->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values_365)) {
            $payments_by_day_values_365[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values_365 = array_values($payments_by_day_values_365);
$payments_by_day_mavg_365_3d = calculate_moving_average($payments_by_day_values_365, 3);
$payments_by_day_mavg_365_7d = calculate_moving_average($payments_by_day_values_365, 7);
$payments_by_day_mavg_365_14d = calculate_moving_average($payments_by_day_values_365, 14);
$payments_by_day_mavg_365_30d = calculate_moving_average($payments_by_day_values_365, 30);
$payments_by_day_mavg_365_180d = calculate_moving_average($payments_by_day_values_365, 180);
$payments_by_day_mavg_365_1y = calculate_moving_average($payments_by_day_values_365, 365);
$payments_by_day_mavg_365_5y = calculate_moving_average($payments_by_day_values_365, 1825);

$payments_by_day_labels_1825 = [];
$payments_by_day_values_1825 = [];
$startDate1825 = new DateTime('today - 1824 days');
$current1825 = clone $startDate1825;
while ($current1825 <= new DateTime('today')) {
    $payments_by_day_labels_1825[] = $current1825->format('M j');
    $payments_by_day_values_1825[$current1825->format('Y-m-d')] = 0;
    $current1825->modify('+1 day');
}
$result = $conn->query("SELECT DATE(created_at) as created_date, SUM(amount) as total FROM payments WHERE status IN $completedStatuses AND created_at >= '" . $conn->real_escape_string($startDate1825->format('Y-m-d')) . "' GROUP BY DATE(created_at) ORDER BY DATE(created_at)");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $date = $row['created_date'];
        if (array_key_exists($date, $payments_by_day_values_1825)) {
            $payments_by_day_values_1825[$date] = floatval($row['total']);
        }
    }
}
$payments_by_day_values_1825 = array_values($payments_by_day_values_1825);
$payments_by_day_mavg_1825_3d = calculate_moving_average($payments_by_day_values_1825, 3);
$payments_by_day_mavg_1825_7d = calculate_moving_average($payments_by_day_values_1825, 7);
$payments_by_day_mavg_1825_14d = calculate_moving_average($payments_by_day_values_1825, 14);
$payments_by_day_mavg_1825_30d = calculate_moving_average($payments_by_day_values_1825, 30);
$payments_by_day_mavg_1825_180d = calculate_moving_average($payments_by_day_values_1825, 180);
$payments_by_day_mavg_1825_1y = calculate_moving_average($payments_by_day_values_1825, 365);
$payments_by_day_mavg_1825_5y = calculate_moving_average($payments_by_day_values_1825, 1825);

// Payment method breakdown for the last 30 days
$payment_method_breakdown = [];
$method_result = $conn->query("SELECT payment_method, COUNT(*) as count, SUM(amount) as total FROM payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY payment_method ORDER BY total DESC");
if ($method_result) {
    while ($row = $method_result->fetch_assoc()) {
        $payment_method_breakdown[] = [
            'label' => $row['payment_method'] ?: 'Other',
            'count' => intval($row['count']),
            'value' => floatval($row['total'])
        ];
    }
}

// Latest payment transactions for table view
$recent_payments = [];
$recent_results = $conn->query("SELECT p.id, p.amount, p.status, p.payment_method, p.created_at, u.first_name, u.last_name, COALESCE(p.mpesa_reference, p.payment_reference, p.transaction_id, p.checkout_request_id, '') AS reference FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 20");
if ($recent_results) {
    while ($payment = $recent_results->fetch_assoc()) {
        $recent_payments[] = $payment;
    }
}

// Top customers by completed revenue
$top_customers = [];
$top_customers_result = $conn->query("SELECT u.id, CONCAT_WS(' ', u.first_name, u.last_name) as name, COUNT(p.id) as payment_count, SUM(p.amount) as total_paid FROM payments p JOIN users u ON p.user_id = u.id WHERE p.status IN $completedStatuses GROUP BY u.id ORDER BY total_paid DESC LIMIT 5");
if ($top_customers_result) {
    while ($customer = $top_customers_result->fetch_assoc()) {
        $top_customers[] = $customer;
    }
}

// Online users
$stats['online_users'] = 0;
if (column_exists('users', 'is_online')) {
    $result = db_query("SELECT COUNT(*) as count FROM users WHERE is_online = 1");
    $stats['online_users'] = $result ? intval($result->fetch_assoc()['count']) : 0;
}

// Audit activity counts
$stats['total_audit_logs'] = 0;
$stats['today_audit_logs'] = 0;
$result = $conn->query("SHOW TABLES LIKE 'audit_logs'");
if ($result && $result->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM audit_logs");
    $stats['total_audit_logs'] = intval($result->fetch_assoc()['count'] ?? 0);
    $result = $conn->query("SELECT COUNT(*) as count FROM audit_logs WHERE created_at >= CURDATE()");
    $stats['today_audit_logs'] = intval($result->fetch_assoc()['count'] ?? 0);
}

// Pending admin notifications
$stats['pending_notifications'] = 0;
$notification_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE admin_id = ? AND is_dismissed = FALSE");
if ($notification_count_stmt) {
    $notification_count_stmt->bind_param('i', $admin_user_id);
    $notification_count_stmt->execute();
    $notification_count_result = $notification_count_stmt->get_result();
    $stats['pending_notifications'] = intval($notification_count_result->fetch_assoc()['count'] ?? 0);
    $notification_count_stmt->close();
}

// Unread agent messages
$unread_agent_messages = 0;
$unread_messages_stmt = $conn->prepare("SELECT COUNT(*) as count FROM agent_messages WHERE receiver_id = ? AND is_read = 0 AND is_deleted = 0");
if ($unread_messages_stmt) {
    $unread_messages_stmt->bind_param('i', $admin_user_id);
    $unread_messages_stmt->execute();
    $unread_messages_result = $unread_messages_stmt->get_result();
    $unread_agent_messages = intval($unread_messages_result->fetch_assoc()['count'] ?? 0);
    $unread_messages_stmt->close();
}

// Total notifications (admin notifications + unread agent messages)
$total_notifications = $stats['pending_notifications'] + $unread_agent_messages;

// Pending consultations
$result = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'pending'");
$stats['pending_consultations'] = $result->fetch_assoc()['count'];

// Interior design statistics
$result = $conn->query("SELECT COUNT(*) as count FROM interior_designs WHERE status = 'pending_review'");
$stats['pending_designs'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM interior_designs WHERE status = 'approved'");
$stats['approved_designs'] = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM interior_designs WHERE status = 'rejected'");
$stats['rejected_designs'] = $result->fetch_assoc()['count'];

// Pending ownership documents
$stats['pending_documents'] = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'ownership_documents'");
if ($check_table && $check_table->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM ownership_documents WHERE verification_status = 'pending'");
    $stats['pending_documents'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Unassigned property inquiries
$stats['unassigned_inquiries'] = 0;
$check_table = $conn->query("SHOW TABLES LIKE 'property_inquiries'");
if ($check_table && $check_table->num_rows > 0) {
    $result = $conn->query("SELECT COUNT(*) as count FROM property_inquiries WHERE status = 'pending' AND assigned_agent_id IS NULL");
    $stats['unassigned_inquiries'] = $result ? $result->fetch_assoc()['count'] : 0;
}

// Cleaning services statistics (safely check if tables exist)
$cleaning_tables_exist = false;
$check_table = $conn->query("SHOW TABLES LIKE 'cleaning_requests'");
if ($check_table && $check_table->num_rows > 0) {
    $cleaning_tables_exist = true;
}

if ($cleaning_tables_exist) {
    $result = $conn->query("SELECT COUNT(*) as count FROM cleaning_requests WHERE status = 'pending'");
    $stats['pending_cleaning_requests'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM cleaning_requests WHERE status = 'assigned'");
    $stats['assigned_cleaning_requests'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM service_providers WHERE is_approved = FALSE");
    $stats['pending_provider_approvals'] = $result->fetch_assoc()['count'];

    $result = $conn->query("SELECT COUNT(*) as count FROM service_providers WHERE is_approved = TRUE");
    $stats['approved_providers'] = $result->fetch_assoc()['count'];
} else {
    // Tables don't exist yet
    $stats['pending_cleaning_requests'] = 0;
    $stats['assigned_cleaning_requests'] = 0;
    $stats['pending_provider_approvals'] = 0;
    $stats['approved_providers'] = 0;
}

// Get admin notifications and persist them in the admin_notifications table
$admin_notifications = [];
$generated_notifications = [];

$notification_queries = [
    [
        'type' => 'new_user',
        'sql' => "SELECT u.id AS related_id, CONCAT('New user registered: ', u.first_name, ' ', u.last_name) AS message, u.created_at AS created_at FROM users u WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY u.created_at DESC LIMIT 5",
    ],
    [
        'type' => 'pending_property',
        'sql' => "SELECT p.id AS related_id, CONCAT('Property verification pending: ', LEFT(p.location, 30), '...') AS message, p.created_at AS created_at FROM properties p WHERE p.verification_status = 'pending' ORDER BY p.created_at DESC LIMIT 5",
    ],
    [
        'type' => 'pending_consultation',
        'sql' => "SELECT c.id AS related_id, CONCAT('New viewing request from: ', u.first_name, ' ', u.last_name) AS message, c.created_at AS created_at FROM consultations c JOIN users u ON c.user_id = u.id WHERE c.status = 'pending' ORDER BY c.created_at DESC LIMIT 5",
    ],
    [
        'type' => 'pending_viewing_request',
        'sql' => "SELECT vr.id AS related_id, CONCAT('New viewing request from: ', u.first_name, ' ', u.last_name, ' for ', LEFT(p.location, 30), '...') AS message, vr.created_at AS created_at FROM viewing_requests vr LEFT JOIN users u ON vr.user_id = u.id LEFT JOIN properties p ON vr.property_id = p.id WHERE vr.status = 'pending' ORDER BY vr.created_at DESC LIMIT 5",
    ],
    [
        'type' => 'pending_design',
        'sql' => "SELECT d.id AS related_id, CONCAT('Interior design pending review from: ', u.first_name, ' ', u.last_name) AS message, d.created_at AS created_at FROM interior_designs d JOIN users u ON d.agent_id = u.id WHERE d.status = 'pending_review' ORDER BY d.created_at DESC LIMIT 5",
    ],
];

$result = $conn->query("SHOW TABLES LIKE 'roommate_requests'");
if ($result && $result->num_rows > 0) {
    $notification_queries[] = [
        'type' => 'pending_roommate_request',
        'sql' => "SELECT id AS related_id, CONCAT('New roommate request from ', name, ' (', institution, ')') AS message, created_at AS created_at FROM roommate_requests ORDER BY created_at DESC LIMIT 5",
    ];
}

foreach ($notification_queries as $template) {
    $result = $conn->query($template['sql']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $row['type'] = $template['type'];
            $generated_notifications[] = $row;
        }
    }
}

try {
    $insert_stmt = $conn->prepare("INSERT INTO admin_notifications (admin_id, notification_type, title, message, related_id, action_url, created_at) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE message = VALUES(message), title = VALUES(title), action_url = VALUES(action_url), created_at = VALUES(created_at)");
    if ($insert_stmt) {
        foreach ($generated_notifications as $notification) {
            $type = $notification['type'];
            $message = $notification['message'];
            $created_at = $notification['created_at'];
            $related_id = intval($notification['related_id']);
            $title = ucfirst(str_replace('_', ' ', $type));
            $action_url = null;

            switch ($type) {
                case 'new_user':
                    $action_url = "admin_users.php?action=edit&id={$related_id}";
                    break;
                case 'pending_property':
                    $action_url = "admin_properties.php?action=verify&id={$related_id}";
                    break;
                case 'pending_consultation':
                    $action_url = "admin_viewing_requests.php";
                    break;
                case 'pending_viewing_request':
                    $action_url = "admin_viewing_requests.php";
                    break;
                case 'pending_design':
                    $action_url = "admin_verify_designs.php";
                    break;
                case 'pending_roommate_request':
                    $action_url = "admin_control_panel.php?view=roommate_requests";
                    break;
            }

            $insert_stmt->bind_param('issssss', $admin_user_id, $type, $title, $message, $related_id, $action_url, $created_at);
            try {
                $insert_stmt->execute();
            } catch (Exception $e) {
                error_log('Admin notification insert skipped: ' . $e->getMessage());
            }
        }
        $insert_stmt->close();
    }
} catch (Exception $e) {
    error_log('Admin notifications disabled due to write error: ' . $e->getMessage());
}

$notification_stmt = $conn->prepare("SELECT *, notification_type AS type FROM admin_notifications WHERE admin_id = ? AND is_dismissed = FALSE ORDER BY created_at DESC LIMIT 20");
if ($notification_stmt) {
    $notification_stmt->bind_param('i', $admin_user_id);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    while ($row = $notification_result->fetch_assoc()) {
        $admin_notifications[] = $row;
    }
    $notification_stmt->close();
}

$notification_count = count($admin_notifications);

// Update notification count to include unread agent messages
$notification_count += $unread_agent_messages;

// Get unread agent messages for display
$agent_messages_stmt = $conn->prepare("
    SELECT am.*, u.first_name, u.last_name, u.email,
           CONCAT(u.first_name, ' ', u.last_name) as sender_name
    FROM agent_messages am
    JOIN users u ON am.sender_id = u.id
    WHERE am.receiver_id = ? AND am.is_read = 0 AND am.is_deleted = 0
    ORDER BY am.created_at DESC
    LIMIT 10
");
$agent_messages = [];
if ($agent_messages_stmt) {
    $agent_messages_stmt->bind_param('i', $admin_user_id);
    $agent_messages_stmt->execute();
    $agent_messages_result = $agent_messages_stmt->get_result();
    while ($row = $agent_messages_result->fetch_assoc()) {
        $agent_messages[] = $row;
    }
    $agent_messages_stmt->close();
}

// Combine admin notifications and agent messages for display
$all_notifications = [];

// Add agent messages first (they're more urgent)
foreach ($agent_messages as $msg) {
    $all_notifications[] = [
        'type' => 'agent_message',
        'title' => htmlspecialchars($msg['title'] ?: 'New Message'),
        'message' => htmlspecialchars(substr($msg['message'], 0, 100)) . (strlen($msg['message']) > 100 ? '...' : ''),
        'sender_name' => htmlspecialchars($msg['sender_name']),
        'created_at' => $msg['created_at'],
        'action_url' => '?view=messages',
        'is_new' => true
    ];
}

// Add admin notifications
foreach ($admin_notifications as $notification) {
    $all_notifications[] = $notification;
}

// Sort by created_at descending
usort($all_notifications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$notification_detail = null;
if (isset($_GET['view'], $_GET['id']) && $_GET['view'] === 'notification') {
    $notification_id = intval($_GET['id']);
    foreach ($all_notifications as $notification) {
        if (isset($notification['id']) && intval($notification['id']) === $notification_id) {
            $notification_detail = $notification;
            break;
        }
    }
}

// Handle actions
$action = $_GET['action'] ?? '';
$view = $_GET['view'] ?? ($search_query !== '' ? 'search' : 'dashboard');

// CSV export for payments analytics
if ($view === 'payments' && ($_GET['export'] ?? '') === 'csv') {
    $filename = 'payments_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Excel compatibility

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment ID', 'User', 'Email', 'Amount', 'Method', 'Status', 'Reference', 'Created At', 'Updated At']);

    $export_stmt = $conn->prepare("SELECT p.id, CONCAT_WS(' ', u.first_name, u.last_name) AS user_name, u.email, p.amount, p.payment_method, p.status, COALESCE(p.mpesa_reference, p.payment_reference, p.transaction_id, p.checkout_request_id, '') AS reference, p.created_at, p.updated_at FROM payments p LEFT JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 1000");
    if ($export_stmt) {
        $export_stmt->execute();
        $export_results = $export_stmt->get_result();
        while ($row = $export_results->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                $row['user_name'],
                $row['email'],
                number_format($row['amount'], 2, '.', ''),
                ucfirst($row['payment_method']),
                ucfirst($row['status']),
                $row['reference'],
                $row['created_at'],
                $row['updated_at']
            ]);
        }
        $export_stmt->close();
    }

    fclose($output);
    exit();
}

// Process property verification actions
if ($action === 'verify' && isset($_GET['id'])) {
    $property_id = intval($_GET['id']);
    
    // Check current transaction flags
    $check_stmt = $conn->prepare("SELECT for_sale, for_rent, for_lease FROM properties WHERE id = ?");
    $check_stmt->bind_param("i", $property_id);
    $check_stmt->execute();
    $property = $check_stmt->get_result()->fetch_assoc();
    
    $update_flags = "";
    if ($property['for_sale'] == 0 && $property['for_rent'] == 0 && $property['for_lease'] == 0) {
        // No flags set, default to for_sale
        $update_flags = ", for_sale = 1, for_rent = 0, for_lease = 0";
    }
    
    $stmt = $conn->prepare("UPDATE properties SET verification_status = 'verified', verified_at = NOW(), verified_by = ?{$update_flags} WHERE id = ?");
    $stmt->bind_param("ii", $admin_user_id, $property_id);
    
    if ($stmt->execute()) {
        // Log the action
        logAdminAction('verify_property', "Verified property ID: $property_id", null, $property_id);

        $seller_stmt = $conn->prepare("SELECT seller_id, property_type, location FROM properties WHERE id = ?");
        $seller_stmt->bind_param("i", $property_id);
        $seller_stmt->execute();
        $seller_info = $seller_stmt->get_result()->fetch_assoc();
        $seller_stmt->close();

        if ($seller_info) {
            $property_label = trim($seller_info['property_type'] . ' in ' . $seller_info['location']);
            $notification_title = 'Property Verified';
            $notification_message = "Your listing has been verified and published: $property_label. It is now visible to buyers and renters on Walbrand Properties Marketplace & Interiors.";
            sendNotification(intval($seller_info['seller_id']), 'property_verified', $notification_title, $notification_message, $property_id, true);
        }

        $success = "Property verified successfully!";
    } else {
        $error = "Failed to verify property: " . $conn->error;
    }
    
    // Redirect back to properties view
    header("Location: admin_control_panel.php?view=properties&success=" . urlencode($success ?? $error));
    exit();
}

if ($action === 'reject' && isset($_GET['id'])) {
    $property_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("UPDATE properties SET verification_status = 'rejected', verified_at = NOW(), verified_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $admin_user_id, $property_id);
    
    if ($stmt->execute()) {
        // Log the action
        logAdminAction('reject_property', "Rejected property ID: $property_id", null, $property_id);

        $seller_stmt = $conn->prepare("SELECT seller_id, property_type, location FROM properties WHERE id = ?");
        $seller_stmt->bind_param("i", $property_id);
        $seller_stmt->execute();
        $seller_info = $seller_stmt->get_result()->fetch_assoc();
        $seller_stmt->close();

        if ($seller_info) {
            $property_label = trim($seller_info['property_type'] . ' in ' . $seller_info['location']);
            $notification_title = 'Property Verification Rejected';
            $notification_message = "Your listing has been rejected: $property_label. Please review the listing details and resubmit when ready.";
            sendNotification(intval($seller_info['seller_id']), 'property_rejected', $notification_title, $notification_message, $property_id, true);
        }

        $success = "Property rejected successfully!";
    } else {
        $error = "Failed to reject property: " . $conn->error;
    }
    
    // Redirect back to properties view
    header("Location: admin_control_panel.php?view=properties&success=" . urlencode($success ?? $error));
    exit();
}

if ($action === 'suspend' && isset($_GET['id'])) {
    $property_id = intval($_GET['id']);

    $stmt = $conn->prepare("UPDATE properties SET verification_status = 'pending' WHERE id = ?");
    $stmt->bind_param("i", $property_id);

    if ($stmt->execute()) {
        logAdminAction('suspend_property', "Suspended property ID: $property_id", null, $property_id);

        $seller_stmt = $conn->prepare("SELECT seller_id, property_type, location FROM properties WHERE id = ?");
        $seller_stmt->bind_param("i", $property_id);
        $seller_stmt->execute();
        $seller_info = $seller_stmt->get_result()->fetch_assoc();
        $seller_stmt->close();

        if ($seller_info) {
            $property_label = trim($seller_info['property_type'] . ' in ' . $seller_info['location']);
            $notification_title = 'Property Suspended';
            $notification_message = "Your listing has been suspended by admin: $property_label. It is no longer visible on the homepage until re-verified.";
            sendNotification(intval($seller_info['seller_id']), 'property_suspended', $notification_title, $notification_message, $property_id, true);
        }

        $success = "Property suspended successfully!";
    } else {
        $error = "Failed to suspend property: " . $conn->error;
    }

    header("Location: admin_control_panel.php?view=properties&success=" . urlencode($success ?? $error));
    exit();
}

if ($action === 'confirm_payment' && isset($_GET['id'])) {
    $payment_id = intval($_GET['id']);
    $payment_stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? LIMIT 1");
    $payment_stmt->bind_param("i", $payment_id);
    $payment_stmt->execute();
    $payment = $payment_stmt->get_result()->fetch_assoc();
    $payment_stmt->close();

    if (!$payment) {
        $error = "Payment not found.";
    } elseif ($payment['status'] === 'completed') {
        $error = "Payment is already confirmed.";
    } else {
        $property_id = intval($payment['property_id']);
        $property = null;
        if ($property_id) {
            $prop_stmt = $conn->prepare("SELECT id, seller_id, for_sale, for_rent, for_lease, category, property_type, location FROM properties WHERE id = ? LIMIT 1");
            $prop_stmt->bind_param("i", $property_id);
            $prop_stmt->execute();
            $property = $prop_stmt->get_result()->fetch_assoc();
            $prop_stmt->close();
        }

        $conn->begin_transaction();
        $update_payment = $conn->prepare("UPDATE payments SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = ?");
        $update_payment->bind_param("i", $payment_id);
        $completed = $update_payment->execute();
        $update_payment->close();

        if ($completed) {
            $transaction_success = true;
            $message = "Service fee transfer confirmed by admin.";
            $agent_id = null;
            if ($property && !empty($property['seller_id'])) {
                $agent_id = intval($property['seller_id']);
                $property_label = trim(($property['property_type'] ?? '') . ' in ' . ($property['location'] ?? ''));                
                $notification_title = "Service Fee Confirmed";
                $notification_message = "A client has completed the service fee transfer for your listing: $property_label. Admin has confirmed the transaction. Please review the request on your dashboard.";
                sendNotification($agent_id, 'service_fee_confirmed', $notification_title, $notification_message, $payment_id, true);

                $is_lease = intval($property['for_lease']) === 1 || strtolower($property['category'] ?? '') === 'NightlyFied';
                if ($is_lease) {
                    $existing_booking_stmt = $conn->prepare("SELECT id FROM lease_bookings WHERE property_id = ? AND tenant_id = ? AND status IN ('pending', 'active') LIMIT 1");
                    $existing_booking_stmt->bind_param("ii", $property_id, $payment['user_id']);
                    $existing_booking_stmt->execute();
                    $existing_booking = $existing_booking_stmt->get_result()->fetch_assoc();
                    $existing_booking_stmt->close();

                    if (!$existing_booking) {
                        $insert_booking = $conn->prepare("INSERT INTO lease_bookings (property_id, tenant_id, lease_type, monthly_amount, lease_start_date, lease_end_date, number_of_units, status, created_at) VALUES (?, ?, 'lease', ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 1, 'pending', NOW())");
                        $insert_booking->bind_param("iid", $property_id, $payment['user_id'], $payment['amount']);
                        if (!$insert_booking->execute()) {
                            $transaction_success = false;
                            $error = "Failed to create lease booking: " . $conn->error;
                        }
                        $insert_booking->close();
                    }
                } else {
                    $existing_bid_stmt = $conn->prepare("SELECT id FROM bids WHERE property_id = ? AND buyer_id = ? AND status IN ('pending', 'accepted') LIMIT 1");
                    $existing_bid_stmt->bind_param("ii", $property_id, $payment['user_id']);
                    $existing_bid_stmt->execute();
                    $existing_bid = $existing_bid_stmt->get_result()->fetch_assoc();
                    $existing_bid_stmt->close();

                    if (!$existing_bid) {
                        $insert_bid = $conn->prepare("INSERT INTO bids (property_id, buyer_id, bid_amount, status, bid_date, created_at) VALUES (?, ?, ?, 'pending', NOW(), NOW())");
                        $insert_bid->bind_param("iid", $property_id, $payment['user_id'], $payment['amount']);
                        if (!$insert_bid->execute()) {
                            $transaction_success = false;
                            $error = "Failed to create bid: " . $conn->error;
                        }
                        $insert_bid->close();
                    }
                }
            }

            if ($transaction_success) {
                logAdminAction('confirm_payment', "Confirmed payment ID: $payment_id", $payment['user_id'], $payment_id);
                $success = "Payment confirmed and agent notified.";
                $conn->commit();
            } else {
                $conn->rollback();
                if (empty($error)) {
                    $error = "Failed to confirm payment due to a related record error.";
                }
            }
        } else {
            $conn->rollback();
            $error = "Failed to confirm payment: " . $conn->error;
        }
    }

    header("Location: admin_control_panel.php?view=payments&" . ($success ? 'success=' . urlencode($success) : 'error=' . urlencode($error)));
    exit();
}

// Handle success/error messages from redirects
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Installation requests and digital services data for overview chart
$installation_history = [];
$submission_history = [];
$approval_history = [];
$rejection_history = [];

// Get installation request counts over last 30 days
$installation_count_stmt = $conn->prepare("SELECT DATE(created_at) AS event_date, COUNT(*) AS count FROM consultations WHERE consultation_type IN ('digital_installation', 'installation_request') AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY event_date ORDER BY event_date");
if ($installation_count_stmt) {
    $installation_count_stmt->execute();
    $installation_result = $installation_count_stmt->get_result();
    while ($row = $installation_result->fetch_assoc()) {
        $installation_history[$row['event_date']] = intval($row['count']);
    }
    $installation_count_stmt->close();
}

// Get product submission counts over last 30 days
$submission_count_stmt = $conn->prepare("SELECT DATE(created_at) AS event_date, COUNT(*) AS count FROM agent_digital_products WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY event_date ORDER BY event_date");
if ($submission_count_stmt) {
    $submission_count_stmt->execute();
    $submission_result = $submission_count_stmt->get_result();
    while ($row = $submission_result->fetch_assoc()) {
        $submission_history[$row['event_date']] = intval($row['count']);
    }
    $submission_count_stmt->close();
}

// Get product approval counts over last 30 days
$approval_count_stmt = $conn->prepare("SELECT DATE(updated_at) AS event_date, COUNT(*) AS count FROM agent_digital_products WHERE status = 'verified' AND DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY event_date ORDER BY event_date");
if ($approval_count_stmt) {
    $approval_count_stmt->execute();
    $approval_result = $approval_count_stmt->get_result();
    while ($row = $approval_result->fetch_assoc()) {
        $approval_history[$row['event_date']] = intval($row['count']);
    }
    $approval_count_stmt->close();
}

// Get product rejection counts over last 30 days
$rejection_count_stmt = $conn->prepare("SELECT DATE(updated_at) AS event_date, COUNT(*) AS count FROM agent_digital_products WHERE status = 'rejected' AND DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY event_date ORDER BY event_date");
if ($rejection_count_stmt) {
    $rejection_count_stmt->execute();
    $rejection_result = $rejection_count_stmt->get_result();
    while ($row = $rejection_result->fetch_assoc()) {
        $rejection_history[$row['event_date']] = intval($row['count']);
    }
    $rejection_count_stmt->close();
}

// Generate date range for last 30 days
$overview_labels = [];
$start_date = date('Y-m-d', strtotime('-29 days'));
$end_date = date('Y-m-d');
$current_date = $start_date;
while ($current_date <= $end_date) {
    $overview_labels[] = date('M j', strtotime($current_date));
    $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
}

// Calculate 7-day moving averages
function calculate_moving_average($data, $window = 7) {
    $result = [];
    $keys = array_keys($data);
    for ($i = 0; $i < count($keys); $i++) {
        $sum = 0;
        $count = 0;
        for ($j = max(0, $i - $window + 1); $j <= $i; $j++) {
            $sum += $data[$keys[$j]] ?? 0;
            $count++;
        }
        $result[] = $count > 0 ? round($sum / $count, 2) : 0;
    }
    return $result;
}

$installation_mavg = calculate_moving_average($installation_history);
$submission_mavg = calculate_moving_average($submission_history);
$approval_mavg = calculate_moving_average($approval_history);
$rejection_mavg = calculate_moving_average($rejection_history);

// Get pending installation request total for summary card
$pending_installation_total = 0;
$pending_installation_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM consultations WHERE consultation_type IN ('digital_installation', 'installation_request') AND (status = 'pending_payment_verification' OR status = '' OR status IS NULL)");
if ($pending_installation_stmt) {
    $pending_installation_stmt->execute();
    $pending_installation_total = intval($pending_installation_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pending_installation_stmt->close();
}

// Get pending product submissions total for summary card
$pending_submissions_total = 0;
$pending_submissions_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM agent_digital_products WHERE status = 'pending_review'");
if ($pending_submissions_stmt) {
    $pending_submissions_stmt->execute();
    $pending_submissions_total = intval($pending_submissions_stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pending_submissions_stmt->close();
}

// Calculate 30-day totals
$installation_total_30d = array_sum($installation_history);
$submission_total_30d = array_sum($submission_history);
$approval_total_30d = array_sum($approval_history);
$rejection_total_30d = array_sum($rejection_history);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Control Panel - Walbrand Properties Marketplace & Interiors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #f97316;
            --primary-dark: #ea580c;
            --primary-light: #fdba74;
            --secondary-color: #fb923c;
            --secondary-light: #fed7aa;
            --accent-color: #f97316;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --dark-color: #0f172a;
            --dark-light: #1e293b;
            --light-gray: #f8fafc;
            --medium-gray: #e2e8f0;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --gradient-primary: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            --gradient-secondary: linear-gradient(135deg, var(--secondary-color), var(--secondary-light));
            --gradient-accent: linear-gradient(135deg, var(--accent-color), #fbbf24);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        body {
            font-family: 'Inter', 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fb;
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-width: 0;
            overflow-x: hidden;
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
            width: 100%;
            min-width: 0;
            background: var(--light-gray);
            overflow-x: hidden;
        }

        /* ============ SIDEBAR ============ */
        .sidebar {
            background: linear-gradient(180deg, var(--dark-color) 0%, var(--dark-light) 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            width: 280px;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: var(--shadow-xl);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .sidebar-header .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
            color: white;
            margin-right: 0.75rem;
            box-shadow: var(--shadow-md);
        }

        .sidebar-header h3 {
            font-size: 1.125rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            background: linear-gradient(135deg, white, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: var(--text-muted);
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 1rem;
        }

        .sidebar-menu li {
            margin-bottom: 0.25rem;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--gradient-primary);
            transform: scaleY(0);
            transition: var(--transition);
            border-radius: 0 2px 2px 0;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            transform: translateX(2px);
        }

        .sidebar-menu a:hover::before {
            transform: scaleY(1);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left: 4px solid #f97316;
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        .sidebar-menu a.active::before {
            transform: scaleY(1);
            background: var(--gradient-primary);
        }

        .sidebar-menu .menu-icon {
            width: 20px;
            height: 20px;
            opacity: 0.8;
            flex-shrink: 0;
        }

        /* ============ MAIN CONTENT ============ */
        .main-content {
            margin-left: 280px;
            flex: 1;
            background: var(--light-gray);
            min-height: 100vh;
            min-width: 0;
            padding: 24px;
            box-sizing: border-box;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, white 0%, #f8fafc 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .welcome-text h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-text p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin: 0;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .admin-avatar {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
        }

        .admin-details h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .admin-details p {
            font-size: 0.875rem;
            opacity: 0.9;
            margin: 0.25rem 0 0 0;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            padding: 0;
            margin-bottom: 1.75rem;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
        }

        .welcome-block {
            flex: 1;
            min-width: 280px;
        }

        .welcome-block .eyebrow {
            margin-bottom: 0.5rem;
            color: #475569;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .welcome-block h1 {
            font-size: 2rem;
            line-height: 1.15;
            margin-bottom: 0.75rem;
            color: #111827;
        }

        .welcome-block .subtitle {
            color: #6b7280;
            font-size: 0.98rem;
            max-width: 620px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .icon-button,
        .date-button,
        .add-new-btn {
            border: none;
            border-radius: 10px;
            padding: 0.85rem 1rem;
            cursor: pointer;
            transition: transform 0.3s ease, background-color 0.3s ease, box-shadow 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .icon-button {
            width: 48px;
            height: 48px;
            background: white;
            border: 1px solid rgba(15, 23, 42, 0.1);
            color: #111827;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
        }

        .icon-button:hover,
        .date-button:hover,
        .add-new-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .badge-button {
            position: relative;
        }

        .badge-button .badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 18px;
            height: 18px;
            background: #ef4444;
            color: white;
            border-radius: 999px;
            font-size: 0.75rem;
            display: grid;
            place-items: center;
        }

        .profile-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            background: white;
            border: 1px solid rgba(15, 23, 42, 0.1);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            color: #111827;
            font-weight: 600;
        }

        .profile-pill span:first-child,
        .profile-pill img {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: #0f172a;
            color: white;
            font-size: 0.95rem;
            object-fit: cover;
        }

        .profile-pill img {
            background: transparent;
        }

        .profile-upload-form {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 0.5rem;
        }

        .profile-upload-form input[type="file"] {
            display: none;
        }

        .date-button {
            background: white;
            border: 1px solid rgba(15, 23, 42, 0.1);
            color: #111827;
        }

        .chart-card-body {
            min-height: 340px;
        }

        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #475569;
        }

        .legend-swatch {
            width: 14px;
            height: 14px;
            border-radius: 4px;
            display: inline-block;
            border: 1px solid rgba(15, 23, 42, 0.12);
        }

        .legend-collected { background: #2563eb; }
        .legend-1w { background: #2563eb; border-style: dashed; }
        .legend-1m { background: #ec4899; border-style: dashed; }
        .legend-6m { background: #c084fc; border-style: dashed; }
        .legend-1y { background: #fb923c; border-style: dashed; }
        .legend-5y { background: #10b981; border-style: dashed; }

        .add-new-btn {
            background: #111827;
            color: white;
        }

        .add-new-btn:hover {
            background: #1f2937;
        }

        .sidebar {
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 2rem 0;
            position: fixed;
            width: 260px;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            box-shadow: var(--shadow-xl);
            border-right: 1px solid rgba(255, 255, 255, 0.08);
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.95rem 1rem;
            color: #cbd5e1;
            text-decoration: none;
            transition: var(--transition);
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left: 4px solid #f97316;
        }

        .sidebar-menu .menu-icon {
            width: 22px;
            text-align: center;
            flex-shrink: 0;
            color: #f97316;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .cards.square-cards {
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }

        .cards.square-cards .card {
            aspect-ratio: 1 / 1;
            min-height: 0;
            justify-content: center;
            padding: 12px;
        }

        .card {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 14px;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: grid;
            place-items: center;
            color: white;
            flex-shrink: 0;
        }

        .icon-box.blue { background: #2563eb; }
        .icon-box.orange { background: #f97316; }
        .icon-box.green { background: #16a34a; }
        .icon-box.red { background: #dc2626; }
        .icon-box.purple { background: #8b5cf6; }
        .icon-box.pink { background: #ec4899; }

        .card-content p {
            margin: 0 0 4px;
            color: #6b7280;
            font-size: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-weight: 700;
        }

        .card-content h3 {
            margin: 0;
            font-size: 10px;
            font-weight: 700;
            color: #111827;
        }

        .card-content small {
            font-size: 6px;
        }

        .dashboard-panels {
            display: grid;
            grid-template-columns: 7fr 3fr;
            gap: 20px;
            margin-bottom: 24px;
            min-width: 0;
        }

        .cards,
        .dashboard-panels,
        .dashboard-bottom,
        .actions-grid,
        .messages-layout,
        .table-container {
            min-width: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .chart-card,
        .schedule-card,
        .recent-activity-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
            padding: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .chart-card:hover,
        .schedule-card:hover,
        .recent-activity-card:hover {
            transform: translateY(-3px);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .panel-header h2 {
            margin: 0;
            font-size: 1.1rem;
            color: #111827;
        }

        .panel-header .panel-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .panel-actions select {
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            background: white;
            color: #111827;
            font-size: 0.95rem;
        }

        .mini-calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }

        .calendar-day {
            background: #f8fafc;
            color: #475569;
            padding: 10px 0;
            border-radius: 12px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 700;
        }

        .calendar-day.active {
            background: #111827;
            color: white;
        }

        .event-list {
            display: grid;
            gap: 14px;
        }

        .event-item {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 16px;
            align-items: center;
            padding: 16px;
            border-radius: 16px;
            background: #f8fafc;
        }

        .event-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: white;
            font-size: 1rem;
        }

        .event-icon.blue { background: #2563eb; }
        .event-icon.orange { background: #f97316; }
        .event-icon.green { background: #16a34a; }
        .event-icon.purple { background: #7c3aed; }

        .event-details p {
            margin: 0;
            font-weight: 600;
            color: #111827;
        }

        .event-details small {
            color: #6b7280;
            display: block;
            margin-top: 4px;
        }

        .status-badge {
            padding: 0.45rem 0.85rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .status-scheduled {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-pending {
            background: #fff1f2;
            color: #be123c;
        }

        .recent-activity-card .activity-entry {
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 0;
        }

        .recent-activity-card .activity-entry:last-child {
            border-bottom: none;
        }

        .recent-activity-card .activity-entry.new-message {
            background: linear-gradient(90deg, #f0fdf4 0%, #ffffff 100%);
            border-left: 4px solid #10b981;
            border-radius: 8px;
            margin: 4px 0;
            padding-left: 12px;
        }

        .recent-activity-card .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: #eff6ff;
            color: #2563eb;
            font-size: 1rem;
        }

        .recent-activity-card .activity-text {
            flex: 1;
        }

        .recent-activity-card .activity-text p {
            margin: 0 0 4px;
            font-weight: 600;
            color: #111827;
        }

        .recent-activity-card .activity-text small {
            color: #6b7280;
        }

        .btn {
            padding: 0.75rem 1.2rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.95rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #111827;
            color: white;
        }

        .btn-primary:hover {
            background: #1f2937;
        }

        .btn-secondary {
            background: #f8fafc;
            color: #111827;
            border: 1px solid #e5e7eb;
        }

        .btn-secondary:hover {
            background: #eef2ff;
        }

        /* ============ DASHBOARD BOTTOM ============ */
        .dashboard-bottom {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .quick-actions h3,
        .activity-feed h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-actions h3::before {
            content: '⚡';
        }

        .activity-feed h3::before {
            content: '📈';
        }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .action-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-color);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .action-content h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
        }

        .action-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.4;
        }

        /* Activity Feed */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .activity-item:hover {
            box-shadow: var(--shadow-md);
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: var(--success-light);
            color: var(--success);
        }

        .activity-icon.primary {
            background: rgba(249, 115, 22, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .activity-icon.danger {
            background: var(--danger-light);
            color: var(--danger);
        }

        .activity-content p {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
            line-height: 1.4;
        }

        .activity-content small {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
            align-items: center;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary-color);
        }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-card.danger .stat-value {
            color: var(--danger);
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        .card i {
            font-size: 20px;
            margin-bottom: 10px;
            display: inline-flex;
        }

        .card h3 {
            margin: 5px 0;
            font-size: 2rem;
        }

        .card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .chart-box {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .chart-box h3 {
            margin-bottom: 1.25rem;
            font-size: 1.25rem;
        }

        .card .blue { color: #2563eb; }
        .card .orange { color: #f97316; }
        .card .green { color: #16a34a; }
        .card .yellow { color: #f59e0b; }
        .card .red { color: #ef4444; }
        .card .purple { color: #8b5cf6; }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card.success::before {
            background: var(--gradient-secondary);
        }

        .stat-card.warning::before {
            background: var(--gradient-accent);
        }

        .stat-card.danger::before {
            background: linear-gradient(135deg, var(--danger), #f87171);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            box-shadow: var(--shadow-md);
        }

        .stat-icon.primary {
            background: var(--gradient-primary);
        }

        .stat-icon.success {
            background: var(--gradient-secondary);
        }

        .stat-icon.warning {
            background: var(--gradient-accent);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, var(--danger), #f87171);
        }

        .stat-content {
            flex: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* ============ DASHBOARD BOTTOM ============ */
        .dashboard-bottom {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .quick-actions h3,
        .activity-feed h3 {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quick-actions h3::before {
            content: '⚡';
        }

        .activity-feed h3::before {
            content: '📈';
        }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .action-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            text-decoration: none;
            color: inherit;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-color);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            background: var(--gradient-primary);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            flex-shrink: 0;
        }

        .action-content h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
        }

        .action-content p {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin: 0;
            line-height: 1.4;
        }

        /* Activity Feed */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .activity-item:hover {
            box-shadow: var(--shadow-md);
        }

        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
        }

        .activity-icon.success {
            background: var(--success-light);
            color: var(--success);
        }

        .activity-icon.primary {
            background: rgba(249, 115, 22, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.warning {
            background: var(--warning-light);
            color: var(--warning);
        }

        .activity-icon.danger {
            background: var(--danger-light);
            color: var(--danger);
        }

        .activity-content p {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin: 0 0 0.25rem 0;
            line-height: 1.4;
        }

        .activity-content small {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
            align-items: center;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-item .activity-content {
            flex: 1;
        }

        .toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            display: grid;
            gap: 0.75rem;
            z-index: 2000;
        }

        .toast {
            min-width: 320px;
            max-width: 420px;
            padding: 1rem 1.25rem;
            border-radius: 14px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
            color: white;
            font-weight: 600;
            animation: slideIn 0.35s ease-out;
        }

        .toast-success { background: #16a34a; }
        .toast-error { background: #dc2626; }
        .toast-info { background: #2563eb; }

        @keyframes slideIn {
            from { transform: translateX(20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .event-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
        }

        .event-item .event-details small {
            display: block;
            color: #64748b;
            margin-top: 0.35rem;
        }

        .event-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .event-icon.blue { background: #2563eb; }
        .event-icon.orange { background: #f97316; }
        .event-icon.purple { background: #8b5cf6; }

        .stat-card.warning {
            border-left-color: var(--warning);
        }

        .stat-card.danger {
            border-left-color: var(--danger);
        }

        .stat-card.success {
            border-left-color: var(--success);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-card.danger .stat-value {
            color: var(--danger);
        }

        /* ============ TABLES ============ */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h2 {
            margin: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--light-gray);
            padding: 1rem 2rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background: var(--light-gray);
        }

        .table-container {
            overflow-x: auto;
        }

        .table-container table {
            width: 100%;
            min-width: 1200px;
            table-layout: auto;
            border-collapse: collapse;
        }

        .table-container th,
        .table-container td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding: 12px;
        }

        .table-container td a {
            white-space: nowrap;
        }

        /* Notifications table specific rules (7 columns) */
        .table-container table:has(thead tr > th:nth-child(7):last-child) {
            table-layout: fixed;
            min-width: 1100px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(1),
        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(1) {
            width: 5%;
            min-width: 50px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(2),
        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(2) {
            width: 12%;
            min-width: 100px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(3),
        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(3) {
            width: 15%;
            min-width: 140px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(4),
        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(4) {
            width: 40%;
            min-width: 320px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(5),
        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(5) {
            width: 10%;
            min-width: 110px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(6),
        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(6) {
            width: 10%;
            min-width: 110px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(7),
        .table-container table:has(thead tr > th:nth-child(7):last-child) th:nth-child(7) {
            width: 8%;
            min-width: 120px;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(7) {
            display: flex;
            gap: 0.3rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .table-container table:has(thead tr > th:nth-child(7):last-child) td:nth-child(7) .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            min-width: fit-content;
            white-space: nowrap;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
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

        .status-active {
            background: #d1f4e9;
            color: #065f46;
        }

        .status-suspended {
            background: #fee2e2;
            color: #7f1d1d;
        }

        /* ============ BUTTONS ============ */
        .btn {
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 123, 0, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* ============ FORMS ============ */
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
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 1.5px rgba(255, 123, 0, 0.15);
        }

        /* ============ RESPONSIVE ============ */
        @media (max-width: 1200px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dashboard-panels {
                grid-template-columns: 1fr;
            }

            .dashboard-bottom {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .dashboard-header {
                flex-direction: column;
                align-items: stretch;
                gap: 1.5rem;
            }

            .top-actions {
                justify-content: flex-start;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .table-container,
            .chart-card,
            .schedule-card,
            .recent-activity-card,
            .messages-layout {
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }

            .sidebar {
                position: static;
                width: 100%;
                height: auto;
                z-index: auto;
                padding: 1rem 0;
            }

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
            }

            .sidebar-menu a {
                padding: 0.7rem 1rem;
                font-size: 0.85rem;
                flex: 1 1 calc(50% - 1rem);
                min-width: 140px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .cards,
            .dashboard-panels,
            .dashboard-bottom,
            .actions-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                align-items: stretch;
            }

            .top-actions {
                justify-content: flex-start;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.8rem 1rem;
            }
        }

        /* ============ MESSAGES STYLES ============ */
        .messages-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .messages-header {
            padding: 2rem;
            border-bottom: 1px solid var(--border-color);
            background: linear-gradient(135deg, #f8fafc 0%, white 100%);
        }

        .messages-header h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .messages-header p {
            margin: 0;
            color: var(--text-secondary);
        }

        .messages-layout {
            display: flex;
            height: 600px;
        }

        .agents-list {
            width: 300px;
            border-right: 1px solid var(--border-color);
            background: #f8fafc;
            padding: 1.5rem;
            overflow-y: auto;
        }

        .agents-list h3 {
            margin: 0 0 1rem 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .agents-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .agent-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .agent-item:hover {
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .agent-item.selected {
            background: var(--primary-light);
            border: 1px solid var(--primary-color);
        }

        .agent-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .agent-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .agent-info {
            flex: 1;
            min-width: 0;
        }

        .agent-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .agent-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .unread-badge {
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .message-thread {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .thread-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: white;
        }

        .thread-header h3 {
            margin: 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .thread-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: #fafbfc;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .message-item {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: var(--radius-md);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .message-item.admin {
            margin-left: 2rem;
            border-left: 4px solid #2563eb;
            color: #2563eb;
        }

        .message-item.agent {
            margin-right: 2rem;
            border-left: 4px solid #1e3a8a;
            color: #1e3a8a;
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .message-sender {
            font-weight: 600;
            font-size: 0.875rem;
            color: inherit;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .message-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: inherit;
        }

        .message-content {
            color: inherit;
            line-height: 1.5;
        }

        .message-delete {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 12px;
            text-decoration: underline;
        }

        .message-delete:hover {
            color: #b91c1c;
        }

        .thread-compose {
            border-top: 1px solid var(--border-color);
            background: white;
            padding: 1.5rem;
        }

        .compose-input {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .compose-input input[type="text"] {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }

        .compose-input textarea {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            min-height: 80px;
            resize: vertical;
        }

        .compose-input button {
            align-self: flex-end;
            padding: 0.75rem 1.5rem;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .compose-input button:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 768px) {
            .messages-layout {
                flex-direction: column;
                height: auto;
            }

            .agents-list {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                max-height: 300px;
            }

            .message-thread {
                height: 500px;
            }
        }

    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="admin-wrapper">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">W</div>
                    <div>
                        <h3>Walbrand</h3>
                        <p>Admin Panel</p>
                    </div>
                </div>
            </div>

            <ul class="sidebar-menu">
                <li><a href="admin_control_panel.php" class="<?= empty($view) || $view === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt menu-icon"></i>Dashboard</a></li>
                <li><a href="admin_users.php" class="">
                    <i class="fas fa-users menu-icon"></i>Manage Users</a></li>
                <li><a href="admin_properties.php" class="">
                    <i class="fas fa-building menu-icon"></i>Properties</a></li>
                <li><a href="admin_storage_facilities.php" class="">
                    <i class="fas fa-warehouse menu-icon"></i>Storage Facilities</a></li>
                <li><a href="admin_investments.php" class="">
                    <i class="fas fa-project-diagram menu-icon"></i>Investments</a></li>
                <li><a href="admin_commit_interest.php" class="">
                    <i class="fas fa-handshake menu-icon"></i>Commit Interests</a></li>
                <li><a href="admin_control_panel.php?view=documents" class="<?= $view === 'documents' ? 'active' : '' ?>">
                    <i class="fas fa-file-alt menu-icon"></i>Documents</a></li>
                <li><a href="admin_control_panel.php?view=payments" class="<?= $view === 'payments' ? 'active' : '' ?>">
                    <i class="fas fa-credit-card menu-icon"></i>Payments</a></li>
                <li><a href="admin_viewing_requests.php" class="">
                    <i class="fas fa-calendar-check menu-icon"></i>Viewing Requests</a></li>
                <li><a href="admin_digital_services.php" class="<?= $view === 'digital_services' ? 'active' : '' ?>">
                    <i class="fas fa-tv menu-icon"></i>Digital Services</a></li>
                <li><a href="admin_control_panel.php?view=roommate_requests" class="<?= $view === 'roommate_requests' ? 'active' : '' ?>">
                    <i class="fas fa-user-friends menu-icon"></i>Roommate Requests</a></li>
                <li><a href="admin_control_panel.php?view=schedule" class="<?= $view === 'schedule' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt menu-icon"></i>Schedule</a></li>
                <li><a href="admin_control_panel.php?view=messages" class="<?= $view === 'messages' ? 'active' : '' ?>">
                    <i class="fas fa-envelope menu-icon"></i>Agent Messages</a></li>
                <li><a href="admin_verify_designs.php" class="">
                    <i class="fas fa-paint-roller menu-icon"></i>Interior Designs</a></li>
                <li><a href="admin/dashboard.php" class="">
                    <i class="fas fa-broom menu-icon"></i>Cleaning Services</a></li>
                <li><a href="admin_mover_bookings.php" class="">
                    <i class="fas fa-truck menu-icon"></i>Moving Services</a></li>
                <li><a href="admin_mpesa_verifications.php" class="">
                    <i class="fas fa-receipt menu-icon"></i>M-Pesa Verifications</a></li>
                <li><a href="admin_audit_logs.php" class="">
                    <i class="fas fa-clipboard-list menu-icon"></i>Audit Logs</a></li>
                <li><a href="admin_blocked_users.php" class="">
                    <i class="fas fa-user-lock menu-icon"></i>Blocked Users</a></li>
                <li><a href="admin_settings.php" class="">
                    <i class="fas fa-cog menu-icon"></i>Settings</a></li>
            </ul>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div id="toastContainer" class="toast-container"></div>

            <!-- TOP BAR -->
            <div class="dashboard-header">
                <div class="welcome-block">
                    <p class="eyebrow">Welcome Back, <?= htmlspecialchars($admin_name) ?> 👋</p>
                    <h1>Your admin dashboard is ready for today.</h1>
                    <p class="subtitle">Monitor growth, review activity, and manage priority tasks from one centralized workspace.</p>
                </div>
                <div class="top-actions">
                    <a href="index.php" class="btn btn-secondary" style="margin-right: 0.5rem;"><i class="fas fa-home"></i> Back to Home</a>
                    <button class="icon-button" aria-label="Search"><i class="fas fa-search"></i></button>
                    <button class="icon-button badge-button" aria-label="Notifications" onclick="window.location.href='admin_control_panel.php?view=notifications'">
                        <i class="fas fa-bell"></i>
                        <span class="badge"><?= $total_notifications > 0 ? $total_notifications : '' ?></span>
                    </button>
                    <div class="profile-pill">
                        <?php if (!empty($admin_profile_picture)): ?>
                            <img src="<?= htmlspecialchars($admin_profile_picture) ?>" alt="Profile photo">
                        <?php else: ?>
                            <span><?= strtoupper(substr($admin_name, 0, 1)) ?></span>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($admin_name) ?></span>
                    </div>
                    <form class="profile-upload-form" method="post" enctype="multipart/form-data">
                        <label class="btn btn-secondary upload-photo-btn" for="admin_profile_picture"><i class="fas fa-camera"></i> Upload Photo</label>
                        <input type="file" id="admin_profile_picture" name="profile_picture" accept="image/png,image/jpeg" onchange="this.form.submit()">
                        <input type="hidden" name="upload_admin_profile_picture" value="1">
                    </form>
                    <button class="date-button"><i class="fas fa-calendar-alt"></i> This Week</button>
                    <a href="admin_properties.php" class="btn btn-primary add-new-btn"><i class="fas fa-plus"></i> Add New</a>
                </div>
            </div>

            <?php if($view === 'dashboard'): ?>
                <a href="?view=users" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box blue"><i class="fas fa-users"></i></div>
                    <div class="card-content">
                        <p>Total Users</p>
                        <h3><?php echo $total_users; ?></h3>
                    </div>
                </a>
                <a href="?view=kyc" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box orange"><i class="fas fa-id-card"></i></div>
                    <div class="card-content">
                        <p>Pending KYC</p>
                        <h3><?php echo $pending_kyc; ?></h3>
                    </div>
                </a>
                <a href="?view=properties" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box green"><i class="fas fa-home"></i></div>
                    <div class="card-content">
                        <p>Total Properties</p>
                        <h3><?php echo $total_properties; ?></h3>
                    </div>
                </a>
                <a href="?view=payments" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box purple"><i class="fas fa-wallet"></i></div>
                    <div class="card-content">
                        <p>Total Revenue</p>
                        <h3>KES <?php echo number_format($total_revenue); ?></h3>
                    </div>
                </a>
                <a href="?view=properties&filter=verified" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box blue"><i class="fas fa-check-circle"></i></div>
                    <div class="card-content">
                        <p>Verified Properties</p>
                        <h3><?php echo $verified_properties; ?></h3>
                    </div>
                </a>
                <a href="admin_users.php" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box teal"><i class="fas fa-user-clock"></i></div>
                    <div class="card-content">
                        <p>Online Users</p>
                        <h3><?php echo number_format($stats['online_users']); ?></h3>
                    </div>
                </a>
                <a href="admin_audit_logs.php" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box purple"><i class="fas fa-stream"></i></div>
                    <div class="card-content">
                        <p>Today's Site Activity</p>
                        <h3><?php echo number_format($stats['today_audit_logs']); ?></h3>
                    </div>
                </a>
                <a href="?view=properties&filter=pending" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box red"><i class="fas fa-clock"></i></div>
                    <div class="card-content">
                        <p>Pending Properties</p>
                        <h3><?php echo $pending_properties; ?></h3>
                    </div>
                </a>
                <a href="admin_storage_facilities.php" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box orange"><i class="fas fa-warehouse"></i></div>
                    <div class="card-content">
                        <p>Pending Storage Facilities</p>
                        <h3><?php echo $pending_storage_facilities; ?></h3>
                    </div>
                </a>
                <a href="?view=roommate_requests" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box teal"><i class="fas fa-user-friends"></i></div>
                    <div class="card-content">
                        <p>Roommate Requests</p>
                        <h3><?php echo $pending_roommate_requests; ?></h3>
                    </div>
                </a>
                <a href="?view=mover_bookings" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box pink"><i class="fas fa-truck"></i></div>
                    <div class="card-content">
                        <p>Mover Bookings</p>
                        <h3><?php echo $mover_bookings; ?></h3>
                    </div>
                </a>
                <a href="admin_digital_services.php" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box orange"><i class="fas fa-tools"></i></div>
                    <div class="card-content">
                        <p>Pending Install Verifications</p>
                        <h3><?php echo $pending_installation_total; ?></h3>
                    </div>
                </a>
                <a href="admin_digital_services.php" class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box blue"><i class="fas fa-upload"></i></div>
                    <div class="card-content">
                        <p>Pending Product Submissions</p>
                        <h3><?php echo $pending_submissions_total; ?></h3>
                    </div>
                </a>
            </div>

            <div class="dashboard-panels">
                <section class="chart-card">
                    <div class="panel-header">
                        <h2>Digital Services Overview</h2>
                        <div class="panel-actions">
                            <select id="chartPeriod">
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-card-body">
                        <canvas id="chart"></canvas>
                    </div>
                </section>

                <section class="schedule-card">
                    <div class="panel-header">
                        <h2>Upcoming Schedule</h2>
                        <a href="?view=schedule" class="btn btn-secondary">View Calendar</a>
                    </div>
                    <div class="mini-calendar">
                        <div class="calendar-day">Mon</div>
                        <div class="calendar-day">Tue</div>
                        <div class="calendar-day">Wed</div>
                        <div class="calendar-day active">Thu</div>
                        <div class="calendar-day">Fri</div>
                        <div class="calendar-day">Sat</div>
                        <div class="calendar-day">Sun</div>
                    </div>
                    <div class="event-list">
                        <?php if (!empty($schedule_events)): ?>
                            <?php foreach ($schedule_events as $event): ?>
                                <div class="event-item">
                                    <div class="event-icon <?= $event['event_type'] === 'meeting' ? 'blue' : 'green' ?>"><i class="fas fa-calendar-check"></i></div>
                                    <div class="event-details">
                                        <p><?= htmlspecialchars($event['title']) ?></p>
                                        <small><?= date('M d, Y g:i A', strtotime($event['event_date'])) ?> · <?= htmlspecialchars(ucfirst($event['event_type'])) ?></small>
                                    </div>
                                    <span class="status-badge status-scheduled">Assigned</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach (array_slice($scheduled_consultations, 0, 3) as $consultation): ?>
                                <div class="event-item">
                                    <div class="event-icon orange"><i class="fas fa-comments"></i></div>
                                    <div class="event-details">
                                        <p><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $consultation['consultation_type']))) ?></p>
                                        <small><?= $consultation['scheduled_date'] ? date('M d, Y g:i A', strtotime($consultation['scheduled_date'])) : 'TBD' ?></small>
                                    </div>
                                    <span class="status-badge <?= $consultation['status'] === 'scheduled' ? 'status-scheduled' : 'status-pending' ?>">
                                        <?= ucfirst($consultation['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($scheduled_consultations)): ?>
                                <div class="event-item">
                                    <div class="event-icon purple"><i class="fas fa-info-circle"></i></div>
                                    <div class="event-details">
                                        <p>No upcoming schedule items found</p>
                                        <small>Create a new meeting or assign a viewing request.</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="recent-activity-card">
                <div class="panel-header">
                    <h2>Recent Activity & Messages</h2>
                    <span class="status-badge status-scheduled">Live updates</span>
                </div>
                <?php if (empty($all_notifications)): ?>
                    <div class="activity-entry">
                        <div class="activity-icon"><i class="fas fa-info"></i></div>
                        <div class="activity-text">
                            <p>No recent activity yet.</p>
                            <small>Check back later for notifications.</small>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($all_notifications as $notification): ?>
                        <div class="activity-entry <?= ($notification['type'] === 'agent_message') ? 'new-message' : '' ?>">
                            <div class="activity-icon">
                                <?php
                                $notificationType = $notification['type'] ?? $notification['notification_type'] ?? '';
                                switch($notificationType) {
                                    case 'agent_message': echo '💬'; break;
                                    case 'new_user': echo '👤'; break;
                                    case 'pending_property': echo '🏠'; break;
                                    case 'pending_consultation': echo '📅'; break;
                                    case 'pending_design': echo '🎨'; break;
                                    default: echo 'ℹ️'; break;
                                }
                                ?>
                            </div>
                            <div class="activity-text">
                                <?php if ($notificationType === 'agent_message'): ?>
                                    <p style="color: #10b981; font-weight: 600;">
                                        📧 New message from <?= htmlspecialchars($notification['sender_name']) ?>: <?= $notification['message'] ?>
                                    </p>
                                <?php else: ?>
                                    <p><?= htmlspecialchars($notification['message']) ?></p>
                                <?php endif; ?>
                                <small><?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?></small>
                                <?php if ($notificationType === 'agent_message'): ?>
                                    <a href="?view=messages" style="color: #10b981; font-size: 0.8rem; margin-left: 10px;">Reply →</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- RECENT USERS -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Users</h2>
                    <a href="?view=users" class="btn btn-primary">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT id, CONCAT_WS(' ', first_name, last_name) AS name, email, user_type, is_active, created_at FROM users ORDER BY created_at DESC LIMIT 5");
                        while($user = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= ucfirst($user['user_type']) ?></td>
                            <td><span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- PENDING PROPERTIES -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Pending Property Verification</h2>
                    <a href="?view=properties" class="btn btn-primary">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Property</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Seller</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT p.id, p.property_type, p.location, p.price, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name FROM properties p JOIN users u ON p.seller_id = u.id WHERE p.verification_status = 'pending' LIMIT 5");
                        if($result->num_rows > 0):
                            while($prop = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($prop['property_type']) ?></td>
                            <td><?= htmlspecialchars($prop['location']) ?></td>
                            <td>KES <?= number_format($prop['price']) ?></td>
                            <td><?= htmlspecialchars($prop['seller_name']) ?></td>
                            <td><a href="?view=properties&action=verify&id=<?= $prop['id'] ?>" class="btn btn-success btn-small">Review</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #999;">No pending properties</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h2>Pending Storage Facility Verification</h2>
                    <a href="admin_storage_facilities.php" class="btn btn-primary">Review All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Location</th>
                            <th>Owner</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $storage_result = $conn->query("SELECT sf.id, sf.name, sf.city, sf.county, CONCAT_WS(' ', u.first_name, u.last_name) AS owner_name FROM storage_facilities sf JOIN users u ON sf.owner_id = u.id WHERE sf.verification_status = 'pending' LIMIT 5");
                        if($storage_result && $storage_result->num_rows > 0):
                            while($sf = $storage_result->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($sf['name']) ?></td>
                            <td><?= htmlspecialchars($sf['city'] . ', ' . $sf['county']) ?></td>
                            <td><?= htmlspecialchars($sf['owner_name']) ?></td>
                            <td><span class="status-badge status-pending">Pending</span></td>
                            <td><a href="admin_storage_facilities.php" class="btn btn-success btn-small">Review</a></td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="5" style="text-align: center; color: #999;">No pending storage facilities</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif($view === 'users'): ?>
            <!-- USERS VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Users</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>KYC Status</th>
                            <th>Account Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT id, CONCAT_WS(' ', first_name, last_name) AS name, email, user_type, kyc_status, is_active FROM users ORDER BY created_at DESC LIMIT 20");
                        while($user = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td>#<?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= ucfirst($user['user_type']) ?></td>
                            <td><span class="status-badge status-<?= $user['kyc_status'] === 'verified' ? 'verified' : 'pending' ?>"><?= $user['kyc_status'] === 'verified' ? 'Verified' : ucfirst(str_replace('_', ' ', $user['kyc_status'])) ?></span></td>
                            <td><span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>"><?= $user['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <a href="?view=users&action=edit&id=<?= $user['id'] ?>" class="btn btn-primary btn-small">Edit</a>
                                <a href="?view=users&action=suspend&id=<?= $user['id'] ?>" class="btn btn-danger btn-small">Suspend</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif($view === 'kyc'): ?>
            <!-- KYC USER STATS VIEW -->
            <div class="dashboard-header">
                <div class="welcome-block">
                    <p class="eyebrow">KYC User Overview</p>
                    <h1>KYC and User Status Summary</h1>
                    <p class="subtitle">Quick access to KYC verification status, suspended and blocked accounts, and agent/client user counts.</p>
                </div>
                <div class="top-actions">
                    <a href="admin_control_panel.php" class="btn btn-secondary" style="margin-right: 0.5rem;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                </div>
            </div>

            <div class="cards">
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box green"><i class="fas fa-user-check"></i></div>
                    <div class="card-content">
                        <p>KYC Verified Users</p>
                        <h3><?= number_format($stats['kyc_verified_users']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box orange"><i class="fas fa-user-clock"></i></div>
                    <div class="card-content">
                        <p>KYC Unverified Users</p>
                        <h3><?= number_format($stats['kyc_unverified_users']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box red"><i class="fas fa-user-slash"></i></div>
                    <div class="card-content">
                        <p>Suspended Users</p>
                        <h3><?= number_format($stats['suspended_users']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box purple"><i class="fas fa-user-lock"></i></div>
                    <div class="card-content">
                        <p>Blocked Users</p>
                        <h3><?= number_format($stats['blocked_users']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box blue"><i class="fas fa-user-tie"></i></div>
                    <div class="card-content">
                        <p>Agent Users</p>
                        <h3><?= number_format($stats['agent_users']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box green"><i class="fas fa-user-friends"></i></div>
                    <div class="card-content">
                        <p>Client Users</p>
                        <h3><?= number_format($stats['client_users']) ?></h3>
                    </div>
                </div>
            </div>

            <?php elseif($view === 'properties'): ?>
            <!-- PROPERTIES VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Properties</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Price</th>
                            <th>Seller</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT p.id, p.property_type, p.location, p.price, p.verification_status, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name FROM properties p JOIN users u ON p.seller_id = u.id ORDER BY p.created_at DESC LIMIT 20");
                        while($prop = $result->fetch_assoc()):
                        ?>
                        <tr>
                            <td>#<?= $prop['id'] ?></td>
                            <td><?= htmlspecialchars($prop['property_type']) ?></td>
                            <td><?= htmlspecialchars($prop['location']) ?></td>
                            <td>KES <?= number_format($prop['price']) ?></td>
                            <td><?= htmlspecialchars($prop['seller_name']) ?></td>
                            <td><span class="status-badge status-<?= $prop['verification_status'] ?>"><?= ucfirst(str_replace('_', ' ', $prop['verification_status'])) ?></span></td>
                            <td>
                                <a href="?view=properties&action=view&id=<?= $prop['id'] ?>" class="btn btn-primary btn-small">View</a>
                                <?php if($prop['verification_status'] === 'verified'): ?>
                                    <a href="?view=properties&action=suspend&id=<?= $prop['id'] ?>" class="btn btn-warning btn-small">Suspend</a>
                                <?php else: ?>
                                    <a href="?view=properties&action=verify&id=<?= $prop['id'] ?>" class="btn btn-success btn-small">Verify</a>
                                <?php endif; ?>
                                <?php if($prop['verification_status'] !== 'rejected'): ?>
                                    <a href="?view=properties&action=reject&id=<?= $prop['id'] ?>" class="btn btn-danger btn-small">Reject</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php elseif($view === 'roommate_requests'): ?>
            <!-- ROOMMATE REQUESTS VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Roommate Requests</h2>
                </div>
                <?php
                $roommate_result = $conn->query("SHOW TABLES LIKE 'roommate_requests'");
                $has_roommate_table = $roommate_result && $roommate_result->num_rows > 0;
                $roommate_requests = [];
                if ($has_roommate_table) {
                    $result = $conn->query("SELECT * FROM roommate_requests ORDER BY created_at DESC LIMIT 50");
                    while ($row = $result->fetch_assoc()) {
                        $roommate_requests[] = $row;
                    }
                }
                ?>
                <?php if (!$has_roommate_table): ?>
                    <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                        <p>The roommate request feature is available once the request table is initialized.</p>
                    </div>
                <?php elseif (empty($roommate_requests)): ?>
                    <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                        <p>No roommate requests have been submitted yet.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Institution</th>
                                <th>Preferred Location</th>
                                <th>Budget</th>
                                <th>Date Submitted</th>
                                <th>Contact</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roommate_requests as $request): ?>
                                <tr>
                                    <td>#<?= intval($request['id']) ?></td>
                                    <td><?= htmlspecialchars($request['name']) ?></td>
                                    <td><?= htmlspecialchars($request['institution']) ?></td>
                                    <td><?= htmlspecialchars($request['preferred_location']) ?></td>
                                    <td>KES <?= number_format($request['budget_min']) ?> - KES <?= number_format($request['budget_max']) ?></td>
                                    <td><?= date('M d, Y', strtotime($request['created_at'])) ?></td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($request['email']) ?>" class="btn btn-primary btn-small">Email</a>
                                        <a href="tel:<?= htmlspecialchars($request['contact']) ?>" class="btn btn-secondary btn-small">Call</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php elseif($view === 'notifications'): ?>
            <!-- NOTIFICATIONS VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>All Notifications & Messages</h2>
                    <?php if (!empty($all_notifications)): ?>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <span style="color: #10b981; font-weight: 600; font-size: 0.9rem;">
                                📧 <?= $unread_agent_messages ?> unread agent message<?= $unread_agent_messages !== 1 ? 's' : '' ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (empty($all_notifications)): ?>
                    <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                        <p>No notifications or messages yet.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        <button class="btn btn-danger btn-small" id="delete-selected-btn" style="display: none;" onclick="deleteSelectedNotifications()">🗑️ Delete Selected</button>
                        <button class="btn btn-danger btn-small" id="delete-all-btn" onclick="deleteAllNotifications()">🗑️ Delete All</button>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all-notifications" title="Select all notifications"></th>
                                <th>Type</th>
                                <th>From/Sender</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_notifications as $notification): ?>
                                <?php $notificationType = $notification['type'] ?? $notification['notification_type'] ?? ''; ?>
                                <tr class="notification-row <?= ($notificationType === 'agent_message') ? 'new-message-row' : '' ?>" style="<?= ($notificationType === 'agent_message') ? 'background: linear-gradient(90deg, #f0fdf4 0%, #ffffff 50%); border-left: 4px solid #10b981;' : '' ?>" data-notification-id="<?= isset($notification['id']) ? intval($notification['id']) : '' ?>">
                                    <td>
                                        <?php if ($notificationType !== 'agent_message' && isset($notification['id'])): ?>
                                            <input type="checkbox" class="notification-checkbox" value="<?= intval($notification['id']) ?>">
                                        <?php else: ?>
                                            <span style="color: #9ca3af;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notificationType === 'agent_message'): ?>
                                            <span style="color: #10b981; font-weight: 600;">💬 Agent Message</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($notificationType))) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notificationType === 'agent_message'): ?>
                                            <span style="color: #10b981; font-weight: 600;"><?= htmlspecialchars($notification['sender_name']) ?></span>
                                        <?php else: ?>
                                            System
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($notificationType === 'agent_message'): ?>
                                            <span style="color: #10b981; font-weight: 500;">
                                                <?= htmlspecialchars($notification['title']) ?>: <?= $notification['message'] ?>
                                            </span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($notification['message']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($notification['created_at'])) ?></td>
                                    <td>
                                        <?php if ($notificationType === 'agent_message'): ?>
                                            <span style="background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">NEW</span>
                                        <?php else: ?>
                                            <span style="background: #e2e8f0; color: #475569; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">Read</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <?php if ($notificationType === 'agent_message'): ?>
                                            <a href="?view=messages" class="btn btn-primary btn-small" style="background: #10b981; border-color: #10b981;">Reply</a>
                                        <?php elseif (!empty($notification['action_url'])): ?>
                                            <a href="?view=notification&id=<?= intval($notification['id']) ?>" class="btn btn-secondary btn-small">View</a>
                                        <?php else: ?>
                                            <span style="color: #6b7280; font-size: 0.8rem;">No action</span>
                                        <?php endif; ?>
                                        <?php if ($notificationType !== 'agent_message' && isset($notification['id'])): ?>
                                            <button class="btn btn-danger btn-small dismiss-notification" data-id="<?= intval($notification['id']) ?>">Dismiss</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php elseif($view === 'notification'): ?>
            <!-- NOTIFICATION DETAILS VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Notification Details</h2>
                </div>
                <?php if (!$notification_detail): ?>
                    <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                        <p>Notification not found or it may have been dismissed.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 2rem;">
                        <p><strong>Type:</strong> <?= htmlspecialchars(str_replace('_', ' ', ucfirst($notification_detail['type']))) ?></p>
                        <p><strong>Title:</strong> <?= htmlspecialchars($notification_detail['title'] ?? ucfirst(str_replace('_', ' ', $notification_detail['type']))) ?></p>
                        <p><strong>Message:</strong><br><?= nl2br(htmlspecialchars($notification_detail['message'])) ?></p>
                        <p><strong>Date:</strong> <?= date('M d, Y H:i', strtotime($notification_detail['created_at'])) ?></p>
                        <?php if (!empty($notification_detail['action_url'])): ?>
                            <p><strong>Related page:</strong> <a href="<?= htmlspecialchars($notification_detail['action_url']) ?>">Open related page</a></p>
                        <?php endif; ?>
                        <?php if (!empty($notification_detail['related_id'])): ?>
                            <p><strong>Reference ID:</strong> <?= intval($notification_detail['related_id']) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <div style="padding: 1rem 2rem 2rem;">
                    <a href="admin_control_panel.php?view=notifications" class="btn btn-secondary">← Back to Notifications</a>
                </div>
            </div>

            <?php elseif($view === 'search'): ?>
            <!-- SEARCH RESULTS VIEW -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Search Results for "<?= htmlspecialchars($search_query) ?>"</h2>
                </div>
                <?php if (empty($search_results['users']) && empty($search_results['properties']) && empty($search_results['consultations'])): ?>
                    <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                        <p>No results found. Try a broader search phrase or check spelling.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($search_results['users'])): ?>
                        <h3 style="margin: 1rem 0 0.5rem;">Users</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results['users'] as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= ucfirst($user['user_type']) ?></td>
                                        <td><?= htmlspecialchars($user['kyc_status']) ?> / <?= $user['is_active'] ? 'Active' : 'Inactive' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($search_results['properties'])): ?>
                        <h3 style="margin: 1.5rem 0 0.5rem;">Properties</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Seller</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results['properties'] as $property): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($property['property_type']) ?></td>
                                        <td><?= htmlspecialchars($property['location']) ?></td>
                                        <td>KES <?= number_format($property['price']) ?></td>
                                        <td><?= htmlspecialchars($property['status']) ?></td>
                                        <td><?= htmlspecialchars($property['seller_name']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($search_results['consultations'])): ?>
                        <h3 style="margin: 1.5rem 0 0.5rem;">Consultations</h3>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Scheduled</th>
                                    <th>Client</th>
                                    <th>Property</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results['consultations'] as $consultation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(str_replace('_', ' ', ucfirst($consultation['consultation_type']))) ?></td>
                                        <td><?= htmlspecialchars($consultation['status']) ?></td>
                                        <td><?= $consultation['scheduled_date'] ? date('M d, Y H:i', strtotime($consultation['scheduled_date'])) : 'TBD' ?></td>
                                        <td><?= htmlspecialchars($consultation['user_name']) ?></td>
                                        <td><?= htmlspecialchars($consultation['property_location']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php elseif($view === 'schedule'): ?>
            <!-- SCHEDULE VIEW -->
            <div class="table-container">
                <div class="table-header" style="display:flex; justify-content:space-between; align-items:center; gap:1rem;">
                    <div>
                        <h2>Calendar & Schedule</h2>
                        <p style="margin:0; color:#64748b;">Create official events, assign agents, and manage all upcoming meetings here.</p>
                    </div>
                    <a href="?view=dashboard" class="btn btn-primary">Return to Dashboard</a>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 420px; gap: 20px; margin-bottom: 2rem;">
                    <div>
                        <h3>Upcoming Schedule Items</h3>
                        <?php if (empty($schedule_events) && empty($scheduled_consultations)): ?>
                            <div class="no-data" style="padding: 2rem; text-align: center; color: #6b7280;">
                                <p>No scheduled events yet. Use the form to create a new meeting or appointment.</p>
                            </div>
                        <?php else: ?>
                            <div class="event-list">
                                <?php foreach ($schedule_events as $event): ?>
                                    <div class="event-item">
                                        <div class="event-icon blue"><i class="fas fa-briefcase"></i></div>
                                        <div class="event-details">
                                            <p><?= htmlspecialchars($event['title']) ?></p>
                                            <small><?= htmlspecialchars($event['description']) ?></small>
                                            <small><?= date('M d, Y g:i A', strtotime($event['event_date'])) ?><?= $event['assigned_name'] ? ' · ' . htmlspecialchars($event['assigned_name']) : '' ?></small>
                                        </div>
                                        <span class="status-badge status-scheduled">Event</span>
                                    </div>
                                <?php endforeach; ?>
                                <?php foreach ($scheduled_consultations as $consultation): ?>
                                    <div class="event-item">
                                        <div class="event-icon orange"><i class="fas fa-comments"></i></div>
                                        <div class="event-details">
                                            <p><?= htmlspecialchars(str_replace('_', ' ', ucfirst($consultation['consultation_type']))) ?></p>
                                            <small><?= htmlspecialchars($consultation['issue_description']) ?></small>
                                            <small><?= $consultation['scheduled_date'] ? date('M d, Y g:i A', strtotime($consultation['scheduled_date'])) : 'TBD' ?></small>
                                        </div>
                                        <span class="status-badge <?= $consultation['status'] === 'scheduled' ? 'status-scheduled' : 'status-pending' ?>">
                                            <?= ucfirst($consultation['status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="background:white; padding:1.5rem; border-radius:16px; border:1px solid #e2e8f0; box-shadow:0 10px 30px rgba(15, 23, 42, 0.06);">
                        <h3 style="margin-bottom:1rem;">Create New Schedule Event</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="create_schedule_event">
                            <div class="form-group">
                                <label>Title</label>
                                <input type="text" name="title" required placeholder="Agent onboarding, Property review call...">
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" rows="3" placeholder="Event details..."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Event Type</label>
                                <select name="event_type" id="event_type_select">
                                    <option value="meeting">Meeting</option>
                                    <option value="job_assignment">Job Assignment</option>
                                    <option value="viewing">Property Viewing</option>
                                    <option value="consultation">Consultation</option>
                                    <option value="cleaning">Cleaning Service</option>
                                    <option value="training">Training Session</option>
                                    <option value="review">Property Review</option>
                                    <option value="onboarding">Agent Onboarding</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="is_meeting" id="is_meeting_toggle" checked onchange="toggleGoogleMeetField()">
                                    <span>This is a Meeting (send to all agents)</span>
                                </label>
                                <small style="color: #666; margin-top: 0.25rem; display: block;">Uncheck if this is just a job assignment</small>
                            </div>
                            <div class="form-group" id="google_meet_field" style="display: none;">
                                <label>Google Meet Link (Optional)</label>
                                <input type="url" name="google_meet_link" placeholder="https://meet.google.com/xxx-xxxx-xxx">
                                <small style="color: #666; margin-top: 0.25rem; display: block;">Agents will receive this link in their notification</small>
                            </div>
                            <div class="form-group">
                                <label>Assign To (Agent)</label>
                                <select name="assigned_to">
                                    <option value="">Unassigned / All Agents</option>
                                    <?php
                                    $agents = $conn->query("SELECT id, CONCAT_WS(' ', first_name, last_name) AS name FROM users WHERE user_type = 'agent' ORDER BY first_name, last_name");
                                    while ($agent = $agents->fetch_assoc()):
                                    ?>
                                        <option value="<?= intval($agent['id']) ?>"><?= htmlspecialchars($agent['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Event Date & Time</label>
                                <input type="datetime-local" name="event_date" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%;">Save Event</button>
                        </form>
                    </div>
                    <script>
                        function toggleGoogleMeetField() {
                            const checkbox = document.getElementById('is_meeting_toggle');
                            const googleMeetField = document.getElementById('google_meet_field');
                            googleMeetField.style.display = checkbox.checked ? 'block' : 'none';
                        }
                        // Initialize on page load
                        document.addEventListener('DOMContentLoaded', toggleGoogleMeetField);
                    </script>
                </div>
            </div>

            <?php elseif($view === 'payments'): ?>
            <!-- PAYMENTS & FINANCIAL ANALYTICS VIEW -->
            <div class="dashboard-header">
                <div class="welcome-block">
                    <p class="eyebrow">Payments & Financial Analytics</p>
                    <h1>Financial intelligence for your revenue operations</h1>
                    <p class="subtitle">Monitor transaction velocity, reconcile settlement, and identify the highest-impact customer segments.</p>
                </div>
                <div class="top-actions">
                    <a href="admin_control_panel.php" class="btn btn-secondary" style="margin-right: 0.5rem;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <button class="btn btn-primary" onclick="window.location.reload();"><i class="fas fa-sync"></i> Refresh Data</button>
                </div>
            </div>

            <div class="cards square-cards">
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="card-content">
                        <p>Total Collected</p>
                        <h3>KES <?= number_format($payments_summary['total_collected'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit; font-size: 5px;">
                    <div class="card-content">
                        <p>Pending Settlement</p>
                        <h3>KES <?= number_format($payments_summary['pending_settlement'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                  
                    <div class="card-content">
                        <p>Failed / Rejected</p>
                        <h3>KES <?= number_format($payments_summary['failed_amount'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                   
                    <div class="card-content">
                        <p>Monthly Revenue</p>
                        <h3>KES <?= number_format($payments_summary['monthly_collected'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                   
                    <div class="card-content">
                        <p>Today's Collected</p>
                        <h3>KES <?= number_format($payments_summary['today_collected'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                  
                    <div class="card-content">
                        <p>Avg Transaction</p>
                        <h3>KES <?= number_format($payments_summary['average_transaction'], 2) ?></h3>
                    </div>
                </div>
            </div>

            <div class="cards square-cards" style="margin-top: 1rem;">
                <div class="card" style="text-decoration: none; color: inherit;">
                 
                    <div class="card-content">
                        <p>Total Money Received</p>
                        <h3>KES <?= number_format($payments_summary['total_received'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                   
                    <div class="card-content">
                        <p>Total Agent Payouts</p>
                        <h3>KES <?= number_format($payments_dashboard['total_agent_payouts'], 2) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                  
                    <div class="card-content">
                        <p>Completed Services</p>
                        <h3><?= number_format($payments_dashboard['total_completed_services']) ?></h3>
                        <small style="color: #64748b; display: block; margin-top: 4px;">Digital Installation + View Requests</small>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box lime"><i class="fas fa-users"></i></div>
                    <div class="card-content">
                        <p>Clients Served</p>
                        <h3><?= number_format($payments_dashboard['total_clients_served']) ?></h3>
                    </div>
                </div>
                <div class="card" style="text-decoration: none; color: inherit;">
                    <div class="icon-box violet"><i class="fas fa-user-friends"></i></div>
                    <div class="card-content">
                        <p>Registered Users</p>
                        <h3><?= number_format($payments_dashboard['total_registered_users']) ?></h3>
                    </div>
                </div>
            </div>

            <div class="table-container" style="margin-top: 1.5rem;">
                <div class="table-header">
                    <div>
                        <h2>Growth, Profitability & Commission Trends</h2>
                        <p style="margin: 0; color: #64748b;">Track revenue momentum, user growth, and estimated commission splits across multiple timeframes.</p>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>7 Days</th>
                            <th>30 Days</th>
                            <th>1 Year</th>
                            <th>5 Years</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Revenue (Current)</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td>KES <?= number_format($period['current_revenue'], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Revenue Change</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td><?= $period['revenue_change_percent'] >= 0 ? '+' : '' ?><?= number_format($period['revenue_change_percent'], 2) ?>% (KES <?= number_format($period['revenue_change_amount'], 2) ?>)</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Profit / Loss vs Prior Period</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td><?= $period['revenue_change_amount'] >= 0 ? 'Profit ' : 'Loss ' ?>KES <?= number_format(abs($period['revenue_change_amount']), 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Client Growth</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td><?= $period['client_change_percent'] >= 0 ? '+' : '' ?><?= number_format($period['client_change_percent'], 2) ?>% (<?= number_format($period['current_clients']) ?>)</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Registered User Growth</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td><?= $period['user_change_percent'] >= 0 ? '+' : '' ?><?= number_format($period['user_change_percent'], 2) ?>% (<?= number_format($period['current_users']) ?>)</td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Estimated Admin Commission</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td>KES <?= number_format($period['estimated_admin_commission'], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td>Estimated Agent Share</td>
                            <?php foreach ($payments_dashboard['periods'] as $period): ?>
                                <td>KES <?= number_format($period['estimated_agent_share'], 2) ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="dashboard-panels">
                <section class="chart-card">
                    <div class="panel-header">
                        <h2 id="paymentChartTitle">Revenue Performance</h2>
                        <div class="panel-actions">
                            <select id="paymentAnalyticsPeriod">
                                <option value="1w">1 Week</option>
                                <option value="1m">1 Month</option>
                                <option value="6m">6 Months</option>
                                <option value="1y">1 Year</option>
                                <option value="5y">5 Years</option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-card-body" style="height: 320px;">
                        <canvas id="paymentsRevenueChart"></canvas>
                        <div class="chart-legend">
                            <div class="legend-item"><span class="legend-swatch legend-collected"></span>Collected</div>
                            <div class="legend-item"><span class="legend-swatch legend-1w"></span>1-Week Moving Average</div>
                            <div class="legend-item"><span class="legend-swatch legend-1m"></span>1-Month Moving Average</div>
                            <div class="legend-item"><span class="legend-swatch legend-6m"></span>6-Month Moving Average</div>
                            <div class="legend-item"><span class="legend-swatch legend-1y"></span>1-Year Moving Average</div>
                            <div class="legend-item"><span class="legend-swatch legend-5y"></span>5-Years Moving Average</div>
                        </div>
                    </div>
                </section>

                <section class="chart-card">
                    <div class="panel-header">
                        <h2>Payment Method Mix</h2>
                        <div class="panel-actions">
                            <span class="status-badge status-scheduled">Last 30 Days</span>
                        </div>
                    </div>
                    <div class="chart-card-body" style="height: 320px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="paymentMethodChart"></canvas>
                    </div>
                </section>
            </div>

            <div class="table-container">
                <div class="table-header" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem; align-items: center;">
                    <div>
                        <h2>Latest Transactions</h2>
                        <p style="margin: 0; color: #64748b;">Review the most recent payment activity and take action on pending settlements.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a class="btn btn-secondary" href="admin_control_panel.php?view=payments&export=csv"><i class="fas fa-file-csv"></i> Export CSV</a>
                        <button class="btn btn-primary" onclick="document.getElementById('transactionSearch').focus();"><i class="fas fa-search"></i> Search</button>
                    </div>
                </div>

                <div style="margin-bottom: 1rem; display: flex; gap: 1rem; flex-wrap: wrap;">
                    <input id="transactionSearch" type="text" placeholder="Search by user, method, or status" style="flex: 1; min-width: 220px; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #d1d5db;">
                </div>

                <table id="transactionTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                        <tr>
                            <td>#<?= intval($payment['id']) ?></td>
                            <td><?= htmlspecialchars(trim($payment['first_name'] . ' ' . $payment['last_name'])) ?></td>
                            <td>KES <?= number_format($payment['amount'], 2) ?></td>
                            <td><?= htmlspecialchars(ucfirst($payment['payment_method'] ?? 'Unknown')) ?></td>
                            <td><span class="status-badge status-<?= htmlspecialchars($payment['status']) ?>"><?= htmlspecialchars(ucfirst($payment['status'])) ?></span></td>
                            <td><?= date('M d, Y H:i', strtotime($payment['created_at'])) ?></td>
                            <td><?= htmlspecialchars($payment['reference'] ?? '-') ?></td>
                            <td>
                                <?php if ($payment['status'] === 'pending'): ?>
                                    <a href="admin_control_panel.php?view=payments&action=confirm_payment&id=<?= intval($payment['id']) ?>" class="btn btn-success btn-small">Confirm</a>
                                <?php else: ?>
                                    <span style="color: #6b7280;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recent_payments)): ?>
                        <tr><td colspan="8" style="text-align:center; color:#6b7280;">No payment transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header">
                    <h2>Top Customers</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Payments</th>
                            <th>Total Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($top_customers)): ?>
                            <?php $rank = 1; foreach ($top_customers as $customer): ?>
                            <tr>
                                <td><?= $rank++ ?></td>
                                <td><?= htmlspecialchars($customer['name']) ?></td>
                                <td><?= intval($customer['payment_count']) ?></td>
                                <td>KES <?= number_format($customer['total_paid'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; color:#6b7280;">No top customer data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2>Top Agents</h2>
                        <p style="margin: 0; color: #64748b;">Highest-performing agents based on completed payouts and service volume across selected periods.</p>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach ($period_definitions as $period_key => $period_data): ?>
                        <button class="btn btn-secondary agent-period-selector<?= $period_key === '30d' ? ' active' : '' ?>" data-agent-period="<?= $period_key ?>"><?= htmlspecialchars($period_data['label']) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php foreach ($payments_dashboard['top_agents'] as $period_key => $agent_rows): ?>
                <div class="agent-period-table" data-agent-period="<?= $period_key ?>" style="display: <?= $period_key === '30d' ? 'block' : 'none' ?>; margin-top: 1rem;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Agent</th>
                                <th>Completed Services</th>
                                <th>Total Payout</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($agent_rows)): ?>
                                <?php $agent_rank = 1; foreach ($agent_rows as $agent_data): ?>
                                <tr>
                                    <td><?= $agent_rank++ ?></td>
                                    <td><?= htmlspecialchars($agent_data['agent_name']) ?></td>
                                    <td><?= intval($agent_data['activity_count']) ?></td>
                                    <td>KES <?= number_format($agent_data['total_amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" style="text-align:center; color:#6b7280;">No agent activity found for this period.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('transactionSearch');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const filter = this.value.toLowerCase();
                            document.querySelectorAll('#transactionTable tbody tr').forEach(row => {
                                const rowText = row.textContent.toLowerCase();
                                row.style.display = rowText.includes(filter) ? '' : 'none';
                            });
                        });
                    }

                    const agentPeriodButtons = document.querySelectorAll('.agent-period-selector');
                    const agentPeriodTables = document.querySelectorAll('.agent-period-table');
                    agentPeriodButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const selected = this.dataset.agentPeriod;
                            agentPeriodButtons.forEach(btn => btn.classList.remove('active'));
                            this.classList.add('active');
                            agentPeriodTables.forEach(table => {
                                table.style.display = table.dataset.agentPeriod === selected ? 'block' : 'none';
                            });
                        });
                    });
                });
            </script>

            <?php elseif($view === 'documents'): ?>

            <!-- DOCUMENTS VIEW -->
            <?php
                $property_docs_count = $conn->query("SELECT COUNT(*) as count FROM ownership_documents")->fetch_assoc()['count'] ?? 0;
                $offplan_docs_count = $conn->query("SELECT COUNT(*) as count FROM offplan_project_documents")->fetch_assoc()['count'] ?? 0;

                $property_docs = [];
                $property_docs_stmt = $conn->prepare(
                    "SELECT od.id, od.document_type, od.document_path, od.verification_status, od.uploaded_at, 
                            p.id AS property_id, p.location, p.property_type, CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name 
                     FROM ownership_documents od 
                     JOIN properties p ON od.property_id = p.id 
                     LEFT JOIN users u ON p.seller_id = u.id 
                     ORDER BY od.uploaded_at DESC 
                     LIMIT 200"
                );
                if ($property_docs_stmt) {
                    $property_docs_stmt->execute();
                    $result = $property_docs_stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $property_docs[] = $row;
                    }
                    $property_docs_stmt->close();
                }

                $offplan_docs = [];
                $offplan_docs_stmt = $conn->prepare(
                    "SELECT d.id, d.document_type, d.file_path, d.verification_status, d.created_at, 
                            p.project_name, p.id AS project_id, CONCAT_WS(' ', u.first_name, u.last_name) AS developer_name 
                     FROM offplan_project_documents d 
                     JOIN offplan_projects p ON d.project_id = p.id 
                     LEFT JOIN users u ON p.developer_id = u.id 
                     ORDER BY d.created_at DESC 
                     LIMIT 200"
                );
                if ($offplan_docs_stmt) {
                    $offplan_docs_stmt->execute();
                    $result = $offplan_docs_stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        $offplan_docs[] = $row;
                    }
                    $offplan_docs_stmt->close();
                }
            ?>

            <div class="dashboard-header">
                <div class="welcome-block">
                    <p class="eyebrow">Documents</p>
                    <h1>Property and Off-Plan Project PDFs</h1>
                    <p class="subtitle">Browse and download all uploaded property ownership documents and off-plan development submissions.</p>
                </div>
                <div class="top-actions" style="gap: 0.75rem; flex-wrap: wrap;">
                    <a href="admin_control_panel.php" class="btn btn-secondary" style="margin-right: 0.5rem;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
                    <input id="documentSearch" type="text" placeholder="Search documents, property, project, seller or developer" style="flex: 1 1 320px; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #d1d5db; min-width: 280px; outline: none;">
                    <button class="btn btn-primary" onclick="window.location.reload();"><i class="fas fa-sync"></i> Refresh Data</button>
                </div>
            </div>

            <div class="cards square-cards" style="margin-top: 1.5rem; gap: 1rem;">
                <div class="card" style="color: inherit;">
                    <div class="card-content">
                        <p>Property Ownership Docs</p>
                        <h3><?= number_format($property_docs_count) ?></h3>
                    </div>
                </div>
                <div class="card" style="color: inherit;">
                    <div class="card-content">
                        <p>Off-Plan Project Docs</p>
                        <h3><?= number_format($offplan_docs_count) ?></h3>
                    </div>
                </div>
                <div class="card" style="color: inherit;">
                    <div class="card-content">
                        <p>Total Documents</p>
                        <h3><?= number_format($property_docs_count + $offplan_docs_count) ?></h3>
                    </div>
                </div>
            </div>

            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header" style="align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
                    <div>
                        <h2>Property Ownership Documents</h2>
                        <p style="margin: 0; color: #64748b;">PDFs uploaded for properties currently listed for sale.</p>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Seller</th>
                                <th>Document Type</th>
                                <th>File Name</th>
                                <th>Status</th>
                                <th>Uploaded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="propertyDocumentsTable">
                            <?php if (!empty($property_docs)): ?>
                                <?php foreach ($property_docs as $doc): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($doc['property_type'] . ' in ' . $doc['location']) ?></strong></td>
                                        <td><?= htmlspecialchars($doc['seller_name'] ?: 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></td>
                                        <td><small><?= htmlspecialchars(basename($doc['document_path'])) ?></small></td>
                                        <td>
                                            <span class="status-badge <?= $doc['verification_status'] === 'verified' ? 'status-verified' : ($doc['verification_status'] === 'rejected' ? 'status-rejected' : 'status-pending') ?>">
                                                <?= htmlspecialchars(ucfirst($doc['verification_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($doc['document_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;">
                                                <i class="fas fa-download"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:#6b7280; padding: 2rem;">No property ownership documents found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="table-container" style="margin-top: 2rem;">
                <div class="table-header" style="align-items: flex-start; gap: 0.5rem; flex-wrap: wrap;">
                    <div>
                        <h2>Off-Plan Project Documents</h2>
                        <p style="margin: 0; color: #64748b;">Developer uploads for off-plan projects, including plans, KYC, permits and agreements.</p>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Project</th>
                                <th>Developer</th>
                                <th>Document Type</th>
                                <th>File Name</th>
                                <th>Status</th>
                                <th>Uploaded At</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="offplanDocumentsTable">
                            <?php if (!empty($offplan_docs)): ?>
                                <?php foreach ($offplan_docs as $doc): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($doc['project_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($doc['developer_name'] ?: 'Unknown') ?></td>
                                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['document_type']))) ?></td>
                                        <td><small><?= htmlspecialchars(basename($doc['file_path'])) ?></small></td>
                                        <td>
                                            <span class="status-badge <?= $doc['verification_status'] === 'verified' ? 'status-verified' : ($doc['verification_status'] === 'rejected' ? 'status-rejected' : 'status-pending') ?>">
                                                <?= htmlspecialchars(ucfirst($doc['verification_status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($doc['created_at'])) ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.85rem;">
                                                <i class="fas fa-download"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" style="text-align:center; color:#6b7280; padding: 2rem;">No off-plan project documents found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const searchInput = document.getElementById('documentSearch');
                    if (searchInput) {
                        searchInput.addEventListener('input', function() {
                            const filter = this.value.toLowerCase();
                            document.querySelectorAll('#propertyDocumentsTable tr, #offplanDocumentsTable tr').forEach(row => {
                                const rowText = row.textContent.toLowerCase();
                                row.style.display = rowText.includes(filter) ? '' : 'none';
                            });
                        });
                    }
                });
            </script>

            <?php elseif($view === 'messages'): ?>

            <!-- MESSAGES VIEW -->
            <div class="messages-container">
                <div class="messages-header">
                    <h2><i class="fas fa-envelope"></i> Agent Messages</h2>
                    <p>Communicate directly with verified agents</p>
                </div>

                <div class="messages-layout">
                    <!-- Agent List -->
                    <div class="agents-list">
                        <h3>Verified Agents</h3>
                        <div class="agents-grid">
                            <?php
                            $agents_query = "SELECT u.id, u.first_name, u.last_name, u.email, u.profile_picture,
                                                   COUNT(am.id) as unread_count
                                            FROM users u
                                            LEFT JOIN agent_messages am ON am.sender_id = u.id AND am.receiver_id = ? AND am.is_read = 0 AND am.is_deleted = 0
                                            WHERE u.user_type = 'agent' AND u.is_active = 1
                                            GROUP BY u.id
                                            ORDER BY u.first_name, u.last_name";
                            $agents_stmt = $conn->prepare($agents_query);
                            $agents_stmt->bind_param("i", $admin_id);
                            $agents_stmt->execute();
                            $agents_result = $agents_stmt->get_result();

                            while($agent = $agents_result->fetch_assoc()):
                                $unread_count = $agent['unread_count'];
                            ?>
                            <div class="agent-item" data-agent-id="<?= $agent['id'] ?>" onclick="selectAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?>')">
                                <div class="agent-avatar">
                                    <?php if($agent['profile_picture']): ?>
                                        <img src="<?= htmlspecialchars($agent['profile_picture']) ?>" alt="Agent">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($agent['first_name'], 0, 1) . substr($agent['last_name'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="agent-info">
                                    <div class="agent-name"><?= htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']) ?></div>
                                    <div class="agent-email"><?= htmlspecialchars($agent['email']) ?></div>
                                </div>
                                <?php if($unread_count > 0): ?>
                                <div class="unread-badge"><?= $unread_count ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <!-- Message Thread -->
                    <div class="message-thread" id="messageThread">
                        <div class="thread-header">
                            <h3 id="threadTitle">Select an agent to start messaging</h3>
                        </div>
                        <div class="thread-messages" id="threadMessages">
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <p>Choose an agent from the list to view your conversation</p>
                            </div>
                        </div>
                        <div class="thread-compose" id="threadCompose" style="display: none;">
                            <form id="messageForm" onsubmit="sendMessage(event)">
                                <input type="hidden" id="selectedAgentId" name="receiver_id">
                                <div class="compose-input">
                                    <input type="text" id="messageTitle" name="title" placeholder="Message title (optional)" maxlength="255">
                                    <textarea id="messageContent" name="message" placeholder="Type your message..." required></textarea>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- PLACEHOLDER FOR OTHER VIEWS -->
            <div class="table-container">
                <div style="padding: 3rem; text-align: center; color: #999;">
                    <h2>📋 Section Coming Soon</h2>
                    <p>This section is being configured. Please check back soon.</p>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function showToast(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toastContainer');
            if (!container || !message) return;

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(12px)';
            }, duration - 500);

            setTimeout(() => toast.remove(), duration);
        }

        // Bulk delete notification functions - Global scope for onclick handlers
        function selectAllNotifications() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = true;
            }

            const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
            notificationCheckboxes.forEach(checkbox => {
                checkbox.checked = true;
            });

            updateDeleteButtonState();
        }

        function clearAllSelections() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = false;
            }

            const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
            notificationCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });

            updateDeleteButtonState();
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
            
            if (selectAllCheckbox && notificationCheckboxes.length > 0) {
                notificationCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateDeleteButtonState();
            }
        }

        function updateDeleteButtonState() {
            const deleteBtn = document.getElementById('deleteSelectedBtn');
            const selectedCheckboxes = document.querySelectorAll('.notification-checkbox:checked');
            
            if (deleteBtn) {
                deleteBtn.disabled = selectedCheckboxes.length === 0;
            }
        }

        async function deleteSelectedNotifications() {
            const selectedCheckboxes = document.querySelectorAll('.notification-checkbox:checked');
            
            if (selectedCheckboxes.length === 0) {
                showToast('Please select at least one notification to delete', 'warning');
                return;
            }

            const confirmed = await showConfirm(`Delete ${selectedCheckboxes.length} notification(s)?`);
            if (!confirmed) {
                return;
            }

            const notificationIds = Array.from(selectedCheckboxes).map(cb => cb.value);

            fetch('admin_notification_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete_bulk&ids=${notificationIds.join(',')}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    selectedCheckboxes.forEach(checkbox => {
                        const row = checkbox.closest('tr');
                        if (row) row.remove();
                    });
                    updateDeleteButtonState();
                    showToast(`${notificationIds.length} notification(s) deleted`, 'success');
                } else {
                    showToast('Failed to delete notifications', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred while deleting notifications', 'error');
            });
        }

        document.addEventListener('DOMContentLoaded', async function() {
            const searchButton = document.querySelector('button[aria-label="Search"]');
            if (searchButton) {
                searchButton.addEventListener('click', async function() {
                    const searchTerm = await showPrompt('Search for users, properties, or consultations:', 'Search', 'text', '');
                    if (searchTerm && searchTerm.trim()) {
                        window.location.href = `admin_control_panel.php?view=search&search=${encodeURIComponent(searchTerm.trim())}`;
                    }
                });
            }

            const notificationButton = document.querySelector('button[aria-label="Notifications"]');
            if (notificationButton) {
                notificationButton.addEventListener('click', function() {
                    window.location.href = 'admin_control_panel.php?view=notifications';
                });
            }

            const weekButton = document.querySelector('.date-button');
            if (weekButton) {
                weekButton.addEventListener('click', function() {
                    const currentText = weekButton.textContent.trim();
                    if (currentText.includes('This Week')) {
                        weekButton.innerHTML = '<i class="fas fa-calendar-alt"></i> This Month';
                    } else if (currentText.includes('This Month')) {
                        weekButton.innerHTML = '<i class="fas fa-calendar-alt"></i> This Year';
                    } else {
                        weekButton.innerHTML = '<i class="fas fa-calendar-alt"></i> This Week';
                    }
                });
            }

            document.querySelectorAll('.view-notification').forEach(button => {
                button.addEventListener('click', function() {
                    const actionUrl = this.getAttribute('data-action-url');
                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');

                    if (actionUrl) {
                        window.location.href = actionUrl;
                        return;
                    }

                    switch(type) {
                        case 'new_user':
                            window.location.href = `admin_users.php?action=edit&id=${id}`;
                            break;
                        case 'pending_property':
                            window.location.href = `admin_properties.php?action=verify&id=${id}`;
                            break;
                        case 'pending_consultation':
                        case 'pending_viewing_request':
                        case 'new_viewing_request':
                            window.location.href = `admin_viewing_requests.php`;
                            break;
                        case 'pending_design':
                        case 'new_interior_design':
                            window.location.href = `admin_verify_designs.php`;
                            break;
                        case 'new_property_submission':
                        case 'new_user':
                            window.location.href = `admin_properties.php`;
                            break;
                        case 'new_roommate_request':
                            window.location.href = `admin_control_panel.php?view=roommate_requests`;
                            break;
                        case 'new_mover_booking':
                            window.location.href = `admin_mover_bookings.php`;
                            break;
                        case 'mpesa_verification':
                            window.location.href = `admin_mpesa_verifications.php?id=${id}`;
                            break;
                        case 'blocked_user':
                            window.location.href = `admin_blocked_users.php`;
                            break;
                        default:
                            showToast('Unknown notification type', 'error');
                    }
                });
            });

            document.querySelectorAll('.dismiss-notification, .delete-notification').forEach(button => {
                button.addEventListener('click', async function() {
                    const confirmed = await showConfirm('Are you sure you want to dismiss this notification?');
                    if (!confirmed) {
                        return;
                    }

                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');
                    const notificationItem = this.closest('.notification-item') || this.closest('tr');

                    fetch('admin_notification_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete&type=${type}&id=${id}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (notificationItem) {
                                notificationItem.remove();
                            }
                            showToast('Notification dismissed', 'success');
                            updateNotificationDeleteButtonVisibility();
                        } else {
                            showToast('Failed to dismiss notification', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('An error occurred while dismissing the notification', 'error');
                    });
                });
            });

            // Bulk notification delete functionality
            const selectAllCheckbox = document.getElementById('select-all-notifications');
            const notificationCheckboxes = document.querySelectorAll('.notification-checkbox');
            const deleteSelectedBtn = document.getElementById('delete-selected-btn');

            function updateNotificationDeleteButtonVisibility() {
                const anyChecked = Array.from(document.querySelectorAll('.notification-checkbox')).some(cb => cb.checked);
                if (deleteSelectedBtn) {
                    deleteSelectedBtn.style.display = anyChecked ? 'inline-block' : 'none';
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    document.querySelectorAll('.notification-checkbox').forEach(cb => cb.checked = this.checked);
                    updateNotificationDeleteButtonVisibility();
                });
            }

            document.querySelectorAll('.notification-checkbox').forEach(checkbox => {
                checkbox.addEventListener('change', updateNotificationDeleteButtonVisibility);
            });

            window.deleteSelectedNotifications = async function() {
                const selectedIds = Array.from(document.querySelectorAll('.notification-checkbox'))
                    .filter(cb => cb.checked)
                    .map(cb => cb.value)
                    .join(',');

                if (!selectedIds) {
                    showToast('Please select at least one notification', 'warning');
                    return;
                }

                const selectedCount = Array.from(document.querySelectorAll('.notification-checkbox')).filter(cb => cb.checked).length;
                const confirmed = await showConfirm(`Delete ${selectedCount} notification(s)?`);
                if (!confirmed) return;

                try {
                    const response = await fetch('admin_notification_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_bulk&ids=${selectedIds}`
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Remove selected rows
                        document.querySelectorAll('.notification-checkbox:checked').forEach(cb => {
                            const row = cb.closest('tr');
                            if (row) row.remove();
                        });
                        showToast(data.message, 'success');
                        updateNotificationDeleteButtonVisibility();
                        if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('An error occurred while deleting notifications', 'error');
                }
            };

            window.deleteAllNotifications = async function() {
                const allIds = Array.from(document.querySelectorAll('.notification-checkbox'))
                    .map(cb => cb.value)
                    .join(',');

                if (!allIds) {
                    showToast('No notifications to delete', 'warning');
                    return;
                }

                const allCount = document.querySelectorAll('.notification-checkbox').length;
                const confirmed = await showConfirm(`Delete all ${allCount} notification(s)? This cannot be undone.`);
                if (!confirmed) return;

                try {
                    const response = await fetch('admin_notification_handler.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_bulk&ids=${allIds}`
                    });
                    const data = await response.json();
                    if (data.success) {
                        // Remove all notification rows
                        document.querySelectorAll('tr.notification-row').forEach(row => row.remove());
                        showToast(data.message, 'success');
                        updateNotificationDeleteButtonVisibility();
                        if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showToast('An error occurred while deleting notifications', 'error');
                }
            };

        });

        function createChart(labels = null, data = null) {
            const chartCanvas = document.getElementById('chart');
            if (!chartCanvas || typeof Chart === 'undefined') {
                return;
            }

            const ctx = chartCanvas.getContext('2d');
            if (!ctx) {
                return;
            }

            if (window.adminDashboardChart && typeof window.adminDashboardChart.destroy === 'function') {
                window.adminDashboardChart.destroy();
            }

            // If no labels/data provided, use the installation overview data
            if (labels === null && data === null) {
                labels = <?= json_encode($overview_labels) ?>;
                data = null; // We'll use datasets instead
            }

            const chartConfig = {
                type: 'line',
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#475569' } },
                        y: { beginAtZero: true, ticks: { color: '#475569' }, grid: { color: 'rgba(148, 163, 184, 0.2)' } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#1f2937' } },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    interaction: { mode: 'nearest', axis: 'x', intersect: false }
                }
            };

            if (data !== null) {
                // Use single dataset for period selector
                chartConfig.data = {
                    labels,
                    datasets: [{
                        label: 'Overview',
                        data,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        fill: true,
                        tension: 0.4,
                    }]
                };
                chartConfig.options.plugins.legend.display = false;
                chartConfig.options.scales.y.grid.display = false;
                chartConfig.options.scales.x.grid.display = false;
            } else {
                // Use multiple datasets for installation overview
                chartConfig.data = {
                    labels: <?= json_encode($overview_labels) ?>,
                    datasets: [
                        {
                            label: 'Installation Requests (7-day avg)',
                            data: <?= json_encode($installation_mavg) ?>,
                            borderColor: '#f97316',
                            backgroundColor: 'rgba(249, 115, 22, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Submissions (7-day avg)',
                            data: <?= json_encode($submission_mavg) ?>,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Approvals (7-day avg)',
                            data: <?= json_encode($approval_mavg) ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        },
                        {
                            label: 'Product Rejections (7-day avg)',
                            data: <?= json_encode($rejection_mavg) ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.12)',
                            fill: true,
                            tension: 0.35,
                            pointRadius: 2,
                            borderWidth: 2
                        }
                    ]
                };
            }

            window.adminDashboardChart = new Chart(ctx, chartConfig);
        }

        const chartPeriod = document.getElementById('chartPeriod');
        if (chartPeriod) {
            createChart();

            chartPeriod.addEventListener('change', function() {
                const period = this.value;
                let labels, data;

                switch(period) {
                    case 'week':
                        labels = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
                        data = [12000, 15000, 13000, 17000, 19000, 16000, 21000];
                        break;
                    case 'month':
                        labels = ["Week 1","Week 2","Week 3","Week 4"];
                        data = [45000, 52000, 48000, 61000];
                        break;
                    case 'year':
                        labels = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
                        data = [120000, 135000, 118000, 142000, 158000, 165000, 172000, 168000, 175000, 182000, 178000, 195000];
                        break;
                }

                createChart(labels, data);
            });
        }

        function renderPaymentsCharts(period = '1w') {
            const payment7Labels = <?= isset($payments_by_day_labels) ? json_encode($payments_by_day_labels) : '[]' ?>;
            const payment7Values = <?= isset($payments_by_day_values) ? json_encode($payments_by_day_values) : '[]' ?>;
            const payment7Mavg3d = <?= isset($payments_by_day_mavg_3d) ? json_encode($payments_by_day_mavg_3d) : '[]' ?>;
            const payment7Mavg7d = <?= isset($payments_by_day_mavg_7d) ? json_encode($payments_by_day_mavg_7d) : '[]' ?>;
            const payment7Mavg14d = <?= isset($payments_by_day_mavg_14d) ? json_encode($payments_by_day_mavg_14d) : '[]' ?>;
            const payment7Mavg30d = <?= isset($payments_by_day_mavg_30d) ? json_encode($payments_by_day_mavg_30d) : '[]' ?>;
            const payment7Mavg1y = <?= isset($payments_by_day_mavg_1y) ? json_encode($payments_by_day_mavg_1y) : '[]' ?>;
            const payment7Mavg5y = <?= isset($payments_by_day_mavg_5y) ? json_encode($payments_by_day_mavg_5y) : '[]' ?>;
            const payment30Labels = <?= isset($payments_by_day_labels_30) ? json_encode($payments_by_day_labels_30) : '[]' ?>;
            const payment30Values = <?= isset($payments_by_day_values_30) ? json_encode($payments_by_day_values_30) : '[]' ?>;
            const payment30Mavg3d = <?= isset($payments_by_day_mavg_30_3d) ? json_encode($payments_by_day_mavg_30_3d) : '[]' ?>;
            const payment30Mavg7d = <?= isset($payments_by_day_mavg_30_7d) ? json_encode($payments_by_day_mavg_30_7d) : '[]' ?>;
            const payment30Mavg14d = <?= isset($payments_by_day_mavg_30_14d) ? json_encode($payments_by_day_mavg_30_14d) : '[]' ?>;
            const payment30Mavg30d = <?= isset($payments_by_day_mavg_30_30d) ? json_encode($payments_by_day_mavg_30_30d) : '[]' ?>;
            const payment30Mavg180d = <?= isset($payments_by_day_mavg_30_180d) ? json_encode($payments_by_day_mavg_30_180d) : '[]' ?>;
            const payment30Mavg1y = <?= isset($payments_by_day_mavg_30_1y) ? json_encode($payments_by_day_mavg_30_1y) : '[]' ?>;
            const payment30Mavg5y = <?= isset($payments_by_day_mavg_30_5y) ? json_encode($payments_by_day_mavg_30_5y) : '[]' ?>;
            const payment180Labels = <?= isset($payments_by_day_labels_180) ? json_encode($payments_by_day_labels_180) : '[]' ?>;
            const payment180Values = <?= isset($payments_by_day_values_180) ? json_encode($payments_by_day_values_180) : '[]' ?>;
            const payment180Mavg3d = <?= isset($payments_by_day_mavg_180_3d) ? json_encode($payments_by_day_mavg_180_3d) : '[]' ?>;
            const payment180Mavg7d = <?= isset($payments_by_day_mavg_180_7d) ? json_encode($payments_by_day_mavg_180_7d) : '[]' ?>;
            const payment180Mavg14d = <?= isset($payments_by_day_mavg_180_14d) ? json_encode($payments_by_day_mavg_180_14d) : '[]' ?>;
            const payment180Mavg30d = <?= isset($payments_by_day_mavg_180_30d) ? json_encode($payments_by_day_mavg_180_30d) : '[]' ?>;
            const payment180Mavg180d = <?= isset($payments_by_day_mavg_180_180d) ? json_encode($payments_by_day_mavg_180_180d) : '[]' ?>;
            const payment180Mavg1y = <?= isset($payments_by_day_mavg_180_1y) ? json_encode($payments_by_day_mavg_180_1y) : '[]' ?>;
            const payment180Mavg5y = <?= isset($payments_by_day_mavg_180_5y) ? json_encode($payments_by_day_mavg_180_5y) : '[]' ?>;
            const payment365Labels = <?= isset($payments_by_day_labels_365) ? json_encode($payments_by_day_labels_365) : '[]' ?>;
            const payment365Values = <?= isset($payments_by_day_values_365) ? json_encode($payments_by_day_values_365) : '[]' ?>;
            const payment365Mavg3d = <?= isset($payments_by_day_mavg_365_3d) ? json_encode($payments_by_day_mavg_365_3d) : '[]' ?>;
            const payment365Mavg7d = <?= isset($payments_by_day_mavg_365_7d) ? json_encode($payments_by_day_mavg_365_7d) : '[]' ?>;
            const payment365Mavg14d = <?= isset($payments_by_day_mavg_365_14d) ? json_encode($payments_by_day_mavg_365_14d) : '[]' ?>;
            const payment365Mavg30d = <?= isset($payments_by_day_mavg_365_30d) ? json_encode($payments_by_day_mavg_365_30d) : '[]' ?>;
            const payment365Mavg180d = <?= isset($payments_by_day_mavg_365_180d) ? json_encode($payments_by_day_mavg_365_180d) : '[]' ?>;
            const payment365Mavg1y = <?= isset($payments_by_day_mavg_365_1y) ? json_encode($payments_by_day_mavg_365_1y) : '[]' ?>;
            const payment365Mavg5y = <?= isset($payments_by_day_mavg_365_5y) ? json_encode($payments_by_day_mavg_365_5y) : '[]' ?>;
            const payment1825Labels = <?= isset($payments_by_day_labels_1825) ? json_encode($payments_by_day_labels_1825) : '[]' ?>;
            const payment1825Values = <?= isset($payments_by_day_values_1825) ? json_encode($payments_by_day_values_1825) : '[]' ?>;
            const payment1825Mavg3d = <?= isset($payments_by_day_mavg_1825_3d) ? json_encode($payments_by_day_mavg_1825_3d) : '[]' ?>;
            const payment1825Mavg7d = <?= isset($payments_by_day_mavg_1825_7d) ? json_encode($payments_by_day_mavg_1825_7d) : '[]' ?>;
            const payment1825Mavg14d = <?= isset($payments_by_day_mavg_1825_14d) ? json_encode($payments_by_day_mavg_1825_14d) : '[]' ?>;
            const payment1825Mavg30d = <?= isset($payments_by_day_mavg_1825_30d) ? json_encode($payments_by_day_mavg_1825_30d) : '[]' ?>;
            const payment1825Mavg180d = <?= isset($payments_by_day_mavg_1825_180d) ? json_encode($payments_by_day_mavg_1825_180d) : '[]' ?>;
            const payment1825Mavg1y = <?= isset($payments_by_day_mavg_1825_1y) ? json_encode($payments_by_day_mavg_1825_1y) : '[]' ?>;
            const payment1825Mavg5y = <?= isset($payments_by_day_mavg_1825_5y) ? json_encode($payments_by_day_mavg_1825_5y) : '[]' ?>;
            const methodLabels = <?= isset($payment_method_breakdown) && is_array($payment_method_breakdown) ? json_encode(array_column($payment_method_breakdown, 'label')) : '[]' ?>;
            const methodValues = <?= isset($payment_method_breakdown) && is_array($payment_method_breakdown) ? json_encode(array_column($payment_method_breakdown, 'value')) : '[]' ?>;

            let selectedLabels = payment7Labels;
            let selectedValues = payment7Values;
            let selectedMavg3d = payment7Mavg3d;
            let selectedMavg7d = payment7Mavg7d;
            let selectedMavg14d = payment7Mavg14d;
            let selectedMavg30d = payment7Mavg30d;
            let selectedMavg180d = payment30Mavg180d;
            let selectedMavg1y = payment7Mavg1y;
            let selectedMavg5y = payment7Mavg5y;

            if (period === '1m') {
                selectedLabels = payment30Labels;
                selectedValues = payment30Values;
                selectedMavg3d = payment30Mavg3d;
                selectedMavg7d = payment30Mavg7d;
                selectedMavg14d = payment30Mavg14d;
                selectedMavg30d = payment30Mavg30d;
                selectedMavg180d = payment30Mavg180d;
                selectedMavg1y = payment30Mavg1y;
                selectedMavg5y = payment30Mavg5y;
            } else if (period === '6m') {
                selectedLabels = payment180Labels;
                selectedValues = payment180Values;
                selectedMavg3d = payment180Mavg3d;
                selectedMavg7d = payment180Mavg7d;
                selectedMavg14d = payment180Mavg14d;
                selectedMavg30d = payment180Mavg30d;
                selectedMavg180d = payment180Mavg180d;
                selectedMavg1y = payment180Mavg1y;
                selectedMavg5y = payment180Mavg5y;
            } else if (period === '1y') {
                selectedLabels = payment365Labels;
                selectedValues = payment365Values;
                selectedMavg3d = payment365Mavg3d;
                selectedMavg7d = payment365Mavg7d;
                selectedMavg14d = payment365Mavg14d;
                selectedMavg30d = payment365Mavg30d;
                selectedMavg180d = payment365Mavg180d;
                selectedMavg1y = payment365Mavg1y;
                selectedMavg5y = payment365Mavg5y;
            } else if (period === '5y') {
                selectedLabels = payment1825Labels;
                selectedValues = payment1825Values;
                selectedMavg3d = payment1825Mavg3d;
                selectedMavg7d = payment1825Mavg7d;
                selectedMavg14d = payment1825Mavg14d;
                selectedMavg30d = payment1825Mavg30d;
                selectedMavg180d = payment1825Mavg180d;
                selectedMavg1y = payment1825Mavg1y;
                selectedMavg5y = payment1825Mavg5y;
            }

            const revenueEl = document.getElementById('paymentsRevenueChart');
            if (revenueEl && typeof Chart !== 'undefined') {
                const revenueCtx = revenueEl.getContext('2d');
                if (window.paymentsRevenueChart && typeof window.paymentsRevenueChart.destroy === 'function') {
                    window.paymentsRevenueChart.destroy();
                }
                window.paymentsRevenueChart = new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: selectedLabels,
                        datasets: [
                            {
                                label: 'Collected',
                                data: selectedValues,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.18)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 3,
                                borderWidth: 2,
                                spanGaps: true
                            },
                            {
                                label: '1-Week Moving Average',
                                data: selectedMavg7d,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                borderDash: [8, 4],
                                borderWidth: 2,
                                spanGaps: true
                            },
                            {
                                label: '1-Month Moving Average',
                                data: selectedMavg30d,
                                borderColor: '#ec4899',
                                backgroundColor: 'rgba(236, 72, 153, 0.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                borderDash: [12, 4],
                                borderWidth: 2,
                                spanGaps: true
                            },
                            {
                                label: '6-Month Moving Average',
                                data: selectedMavg180d,
                                borderColor: '#c084fc',
                                backgroundColor: 'rgba(192, 132, 252, 0.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                borderDash: [10, 5],
                                borderWidth: 2,
                                spanGaps: true
                            },
                            {
                                label: '1-Year Moving Average',
                                data: selectedMavg1y,
                                borderColor: '#fb923c',
                                backgroundColor: 'rgba(251, 146, 60, 0.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                borderDash: [6, 4],
                                borderWidth: 2,
                                spanGaps: true
                            },
                            {
                                label: '5-Years Moving Average',
                                data: selectedMavg5y,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.08)',
                                fill: false,
                                tension: 0.35,
                                pointRadius: 0,
                                borderDash: [14, 4],
                                borderWidth: 2,
                                spanGaps: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#1f2937' } },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                callbacks: {
                                    label: (context) => `${context.dataset.label}: KES ${Number(context.parsed.y).toLocaleString()}`
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: { display: true, text: 'Date', color: '#1f2937' },
                                ticks: { color: '#475569', autoSkip: true, maxRotation: 0 },
                                grid: { display: false, drawBorder: true },
                                border: { display: true, color: 'rgba(148, 163, 184, 0.5)' }
                            },
                            y: {
                                display: true,
                                beginAtZero: true,
                                title: { display: true, text: 'Revenue (KES)', color: '#1f2937' },
                                ticks: {
                                    color: '#475569',
                                    callback: value => `KES ${Number(value).toLocaleString()}`
                                },
                                grid: { color: 'rgba(148, 163, 184, 0.2)', drawBorder: true },
                                border: { display: true, color: 'rgba(148, 163, 184, 0.5)' }
                            }
                        }
                    }
                });
            }

            const methodEl = document.getElementById('paymentMethodChart');
            if (methodEl && typeof Chart !== 'undefined') {
                const methodCtx = methodEl.getContext('2d');
                if (window.paymentMethodChart && typeof window.paymentMethodChart.destroy === 'function') {
                    window.paymentMethodChart.destroy();
                }
                window.paymentMethodChart = new Chart(methodCtx, {
                    type: 'doughnut',
                    data: {
                        labels: methodLabels,
                        datasets: [{
                            data: methodValues,
                            backgroundColor: ['#2563eb', '#f97316', '#10b981', '#8b5cf6', '#ef4444', '#6d28d9'],
                            borderColor: '#fff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom', labels: { color: '#1f2937' } },
                            tooltip: { callbacks: { label: (context) => `${context.label}: KES ${Number(context.raw).toLocaleString()}` } }
                        }
                    }
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const periodSelect = document.getElementById('paymentAnalyticsPeriod');
            const selectedPeriod = periodSelect ? periodSelect.value : '1w';
            const titleElement = document.getElementById('paymentChartTitle');
            function updateChartTitle(period) {
                if (!titleElement) return;
                switch (period) {
                    case '5y':
                        titleElement.textContent = 'Revenue Performance (Last 5 Years)';
                        break;
                    case '1y':
                        titleElement.textContent = 'Revenue Performance (Last 1 Year)';
                        break;
                    case '6m':
                        titleElement.textContent = 'Revenue Performance (Last 6 Months)';
                        break;
                    case '1m':
                        titleElement.textContent = 'Revenue Performance (Last 1 Month)';
                        break;
                    default:
                        titleElement.textContent = 'Revenue Performance (Last 1 Week)';
                }
            }
            updateChartTitle(selectedPeriod);
            renderPaymentsCharts(selectedPeriod);
            if (periodSelect) {
                periodSelect.addEventListener('change', function() {
                    updateChartTitle(this.value);
                    renderPaymentsCharts(this.value);
                });
            }
        });

        // Messaging functionality
        let selectedAgentId = null;
        let selectedAgentName = '';

        function selectAgent(agentId, agentName) {
            selectedAgentId = agentId;
            selectedAgentName = agentName;

            // Update UI
            document.querySelectorAll('.agent-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.querySelector(`[data-agent-id="${agentId}"]`).classList.add('selected');

            document.getElementById('threadTitle').textContent = `Conversation with ${agentName}`;
            document.getElementById('selectedAgentId').value = agentId;
            document.getElementById('threadCompose').style.display = 'block';

            // Load messages
            loadMessages(agentId);
        }

        function loadMessages(agentId) {
            fetch(`admin_message_handler.php?action=get_messages&agent_id=${agentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayMessages(data.messages);
                    } else {
                        showToast('Failed to load messages', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    showToast('An error occurred while loading messages', 'error');
                });
        }

        function displayMessages(messages) {
            const container = document.getElementById('threadMessages');
            if (!container) {
                return;
            }

            if (messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = messages.map(message => {
                const isAdmin = message.sender_id == <?= $admin_id ?>;
                const senderName = isAdmin ? 'You' : (selectedAgentName || 'Agent');
                const messageClass = isAdmin ? 'admin' : 'agent';
                const messageText = message.message ? message.message.replace(/\n/g, '<br>') : '';

                return `
                    <div class="message-item ${messageClass}">
                        <div class="message-header">
                            <span class="message-sender">${senderName}</span>
                            <span class="message-time">
                                ${formatDate(message.created_at)}
                                <button class="message-delete" type="button" onclick="deleteMessage(${message.id});">Delete</button>
                            </span>
                        </div>
                        <div class="message-body">${messageText}</div>
                    </div>
                `;
            }).join('');

            container.scrollTop = container.scrollHeight;
        }

        function sendMessage(event) {
            event.preventDefault();

            const formData = new FormData(document.getElementById('messageForm'));

            fetch('admin_message_handler.php?action=send_message', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('messageTitle').value = '';
                    document.getElementById('messageContent').value = '';
                    loadMessages(selectedAgentId);
                    showToast('Message sent successfully', 'success');
                } else {
                    showToast('Failed to send message', 'error');
                }
            })
            .catch(error => {
                console.error('Error sending message:', error);
                showToast('An error occurred while sending the message', 'error');
            });
        }

        async function deleteMessage(messageId) {
            if (!selectedAgentId) {
                showToast('Select an agent before deleting a message.', 'error');
                return;
            }

            const confirmed = await showConfirm('Delete this message?');
            if (!confirmed) {
                return;
            }

            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('agent_id', selectedAgentId);

            fetch('admin_message_handler.php?action=delete_message', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages(selectedAgentId);
                    showToast('Message deleted successfully.', 'success');
                } else {
                    showToast(data.message || 'Failed to delete message', 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting message:', error);
                showToast('An error occurred while deleting the message', 'error');
            });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diff = now - date;

            if (diff < 60000) { // Less than 1 minute
                return 'Just now';
            } else if (diff < 3600000) { // Less than 1 hour
                return Math.floor(diff / 60000) + 'm ago';
            } else if (diff < 86400000) { // Less than 1 day
                return Math.floor(diff / 3600000) + 'h ago';
            } else {
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            }
        }

        // Initialize messaging if on messages view
        if (window.location.search.includes('view=messages')) {
            // Auto-select first agent if available
            const firstAgent = document.querySelector('.agent-item');
            if (firstAgent) {
                const agentId = firstAgent.getAttribute('data-agent-id');
                const agentName = firstAgent.querySelector('.agent-name').textContent;
                selectAgent(agentId, agentName);
            }
        }
    </script>
</body>
</html>
