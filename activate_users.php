<?php
/**
 * Temporary Script to Activate All Users
 * This script activates all inactive user accounts for development/testing
 */

require_once 'config.php';

// Simple security check - delete after use
$key = $_GET['key'] ?? '';
if ($key !== 'activate_all_2026') {
    die('Invalid access key. Access denied.');
}

try {
    $update = $conn->query("UPDATE users SET is_active = 1 WHERE is_active = 0");
    
    if ($update) {
        $count = $conn->affected_rows;
        echo "<h2 style='color: green;'>✓ Success!</h2>";
        echo "<p>Activated $count user account(s).</p>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
    } else {
        echo "<h2 style='color: red;'>✗ Error</h2>";
        echo "<p>" . $conn->error . "</p>";
    }
} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Error</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>
