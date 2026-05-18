<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle actions
$action = $GET['action'] ?? '';
$user_id = $GET['id'] ?? 0;
?>
