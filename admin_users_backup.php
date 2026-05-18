<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle actions
$action = $GET['action'] ?? '';
$user_id = $GET['id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Walbrand Properties Marketplace & Interiors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
