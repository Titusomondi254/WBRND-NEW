<?php
require_once 'config.php';
$result = $conn->query('SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE "service_fee_%"');
if ($result) {
    echo "Current fee settings in database:" . PHP_EOL;
    echo str_repeat('=', 40) . PHP_EOL;
    while ($row = $result->fetch_assoc()) {
        echo $row['setting_key'] . ': ' . $row['setting_value'] . PHP_EOL;
    }
} else {
    echo 'No settings found';
}
?>