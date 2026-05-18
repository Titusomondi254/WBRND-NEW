<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Redirect admin_page.php to the actual admin dashboard
header('Location: admin_control_panel.php');
exit();
?>