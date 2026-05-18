<?php
require_once 'config.php';
$result = $conn->query('DESCRIBE admin_users');
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . '<br>';
}
?>