<?php
require_once 'config.php';

try {
    $result = $conn->query("SELECT kyc_verified FROM users LIMIT 1");
    if ($result) {
        echo "kyc_verified column exists\n";
    } else {
        echo "kyc_verified column does not exist\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

$conn->close();
?>