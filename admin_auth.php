<?php
/**
 * Admin Authentication Middleware
 * Include this file at the top of any admin page to ensure user is authenticated
 * Provides comprehensive admin access control and security features
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'helpers.php';

secure_session_start();

// Function to check if user is the designated super admin
function is_super_admin_only($user_id) {
    global $conn;

    $query = "SELECT role FROM admin_users WHERE user_id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        return $admin['role'] === 'super_admin';
    }

    return false;
}

// Function to check whether a user exists
if (!function_exists('user_exists')) {
    function user_exists($user_id) {
        global $conn;

        if (empty($user_id)) {
            return false;
        }

        $query = "SELECT id FROM users WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result && $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}

// Function to get super admin contact info for notifications
function get_super_admin_contact() {
    global $conn;

    $query = "SELECT u.email, CONCAT_WS(' ', u.first_name, u.last_name) AS name
              FROM admin_users a
              JOIN users u ON a.user_id = u.id
              WHERE a.role = 'super_admin' AND a.is_active = 1
              LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }

    return null;
}


// Function to send security alert to super admin
function send_security_alert($attacker_user_id, $attacker_info) {
    global $conn;

    $super_admin = get_super_admin_contact();
    if (!$super_admin) {
        error_log("SECURITY ALERT: No super admin found to notify about unauthorized access attempt by user ID: $attacker_user_id");
        return false;
    }

    $subject = "🚨 SECURITY ALERT: Unauthorized Admin Access Attempt";
    $message = "
    <h2>Security Alert: Unauthorized Admin Access Attempt</h2>
    <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
    <p><strong>Attacker User ID:</strong> $attacker_user_id</p>
    <p><strong>Attacker Info:</strong> {$attacker_info['name']} ({$attacker_info['email']})</p>
    <p><strong>IP Address:</strong> {$attacker_info['ip']}</p>
    <p><strong>User Agent:</strong> {$attacker_info['user_agent']}</p>
    <p><strong>Requested Page:</strong> {$attacker_info['requested_page']}</p>
    <p><strong>Action Taken:</strong> Account has been permanently blocked</p>
    <p>Please review this incident immediately.</p>
    ";

    // For now, log the alert. In production, you'd send an email
    error_log("SECURITY ALERT: $message");

    // You could integrate with email system here
    // send_email($super_admin['email'], $subject, $message);

    return true;
}

// Check if this is a security violation confirmation
if (isset($_POST['confirm_security_violation']) && $_POST['confirm_security_violation'] === 'yes') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $user_info = [
        'name' => $_SESSION['user_name'] ?? 'Unknown',
        'email' => $_SESSION['user_email'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'requested_page' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
    ];

    // Block the account
    block_user_account($user_id, 'Confirmed unauthorized admin access attempt');

    // Send security alert
    send_security_alert($user_id, $user_info);

    // Destroy session and redirect
    session_destroy();

    // Show blocked message
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Account Blocked</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
            .message { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
            .warning { color: #d32f2f; font-size: 48px; margin-bottom: 20px; }
            h1 { color: #333; }
            p { color: #666; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class='message'>
            <div class='warning'>🚫</div>
            <h1>Account Permanently Blocked</h1>
            <p>Your account has been permanently blocked due to unauthorized access attempt to admin areas.</p>
            <p>Contact the administrator to request account reactivation.</p>
            <p><a href='index.php'>Return to Home</a></p>
        </div>
    </body>
    </html>";
    exit();
}

// Enhanced admin authentication check - ONLY super admins allowed
$is_admin_session = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

if (!$is_admin_session) {
    // TEMPORARY BYPASS FOR DEVELOPMENT - REMOVE IN PRODUCTION
    if (isset($_GET['dev_access']) && $_GET['dev_access'] === 'true') {
        // Set up development admin session
        $_SESSION['admin_id'] = 4;
        $_SESSION['user_id'] = 4;
        $_SESSION['admin_name'] = 'TITUS OMONDI';
        $_SESSION['admin_email'] = 'tomondi653@gmmail.com';
        $_SESSION['admin_role'] = 'super_admin';
        $_SESSION['is_admin'] = true;
        $_SESSION['user_role'] = 'super_admin';
        $_SESSION['last_activity'] = time();

        // Redirect to remove dev_access from URL
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // Log unauthorized access attempt
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $requested_page = $_SERVER['REQUEST_URI'] ?? 'Unknown';

    error_log("Unauthorized admin access attempt from IP: $ip, Page: $requested_page, User-Agent: $user_agent");

    // Redirect to login without building recursive redirect URLs
    header("Location: admin_login.php");
    exit();
}

// CRITICAL: Verify user is actually a super_admin
$user_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
if (!is_super_admin_only($user_id)) {
    // Non-super-admin trying to access admin page - block immediately
    $user_info = [
        'name' => $_SESSION['admin_name'] ?? $_SESSION['user_name'] ?? 'Unknown',
        'email' => $_SESSION['admin_email'] ?? $_SESSION['user_email'] ?? 'Unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'requested_page' => $_SERVER['REQUEST_URI'] ?? 'Unknown'
    ];

    error_log("SECURITY VIOLATION: Non-super-admin user {$user_info['name']} (ID: $user_id) attempted admin access: {$user_info['requested_page']}");
    
    // Block the account
    block_user_account($user_id, 'Unauthorized admin access attempt');
    send_security_alert($user_id, $user_info);
    
    session_destroy();
    header("Location: index.php?error=blocked");
    exit();
}

// All checks passed - user is authenticated as super admin

// Check if admin session has expired (optional: set to 1 hour)
$session_timeout = 3600; // 1 hour
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $session_timeout) {
    // Log session timeout
    $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 'Unknown';
    error_log("Admin session expired for user ID: $admin_id");

    session_destroy();
    header("Location: admin_login.php?expired=1");
    exit();
}

// Update last activity timestamp
$_SESSION['last_activity'] = time();

// Function to check admin role
function hasAdminRole($required_role = null) {
    if ($required_role === null) {
        return true; // Any admin can access
    }
    
    $admin_role = $_SESSION['admin_role'] ?? '';
    return $admin_role === $required_role || $admin_role === 'super_admin';
}

// Function to require specific role
function requireAdminRole($required_role) {
    if (!hasAdminRole($required_role)) {
        http_response_code(403);
        die('Access Denied: You do not have permission to access this resource.');
    }
}

// Function to log admin actions
function logAdminAction($action, $details = '', $user_id = null, $resource_id = null) {
    global $conn;
    
    if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
        return false;
    }
    
    // Check if admin_logs table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_logs'");
    if ($table_check->num_rows == 0) {
        // Create the table if it doesn't exist
        $create_table = "
        CREATE TABLE admin_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            admin_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            details JSON,
            user_id INT,
            resource_id INT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_action (action),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if (!$conn->query($create_table)) {
            return false; // Could not create table
        }
    }
    
    $admin_id = $_SESSION['admin_user_id'] ?? null;

    // Validate cached admin user ID if present
    if (!empty($admin_id)) {
        $validate_stmt = $conn->prepare("SELECT id FROM admin_users WHERE id = ? AND is_active = 1 LIMIT 1");
        if ($validate_stmt) {
            $validate_stmt->bind_param("i", $admin_id);
            $validate_stmt->execute();
            $validate_result = $validate_stmt->get_result();
            if (!($validate_result && $validate_result->num_rows > 0)) {
                $admin_id = null;
                unset($_SESSION['admin_user_id']);
            }
            $validate_stmt->close();
        }
    }

    // Try lookup by the logged-in user's user_id if we still don't have the admin row ID
    if (empty($admin_id)) {
        $session_user_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
        if (!empty($session_user_id)) {
            $lookup_stmt = $conn->prepare("SELECT id FROM admin_users WHERE user_id = ? AND is_active = 1 LIMIT 1");
            if ($lookup_stmt) {
                $lookup_stmt->bind_param("i", $session_user_id);
                $lookup_stmt->execute();
                $lookup_result = $lookup_stmt->get_result();
                if ($lookup_result && $lookup_result->num_rows > 0) {
                    $lookup_row = $lookup_result->fetch_assoc();
                    $admin_id = $lookup_row['id'];
                    $_SESSION['admin_user_id'] = $admin_id;
                }
                $lookup_stmt->close();
            }
        }
    }

    if (empty($admin_id)) {
        return false;
    }

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Normalize details to valid JSON for the admin_logs JSON CHECK constraint
    if (is_array($details) || is_object($details)) {
        $details = json_encode($details, JSON_UNESCAPED_UNICODE);
    } else {
        $details = json_encode(['message' => (string) $details], JSON_UNESCAPED_UNICODE);
    }
    
    $stmt = $conn->prepare(
        "INSERT INTO admin_logs (admin_id, action, details, user_id, resource_id, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param(
        "isssiss",
        $admin_id,
        $action,
        $details,
        $user_id,
        $resource_id,
        $ip_address,
        $user_agent
    );
    
    return $stmt->execute();
}

// Function to get admin info
function getAdminInfo() {
    return [
        'id' => $_SESSION['admin_id'] ?? null,
        'name' => $_SESSION['admin_name'] ?? null,
        'email' => $_SESSION['admin_email'] ?? null,
        'role' => $_SESSION['admin_role'] ?? null,
    ];
}
?>
