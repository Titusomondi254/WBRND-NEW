<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

header('Location: user_dashboard.php?open_property_modal=1');
exit();
