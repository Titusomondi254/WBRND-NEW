<?php
session_start();
require_once 'config.php';

if(isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // Log the logout action
    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, created_at) VALUES (?, 'admin_logout', 'admin_users', ?, ?, ?, NOW())");
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $log_stmt->bind_param("iiss", $admin_id, $admin_id, $ip, $agent);
    $log_stmt->execute();
}

// Destroy session
session_destroy();

// Redirect to login
header("Location: admin_login.php?logout=1");
exit();
?>
