<?php
require_once 'config.php';

$result = $conn->query('DESCRIBE consultations');
if ($result) {
    echo "<h2>Consultations Table Structure:</h2>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . '<br>';
    }
} else {
    echo 'Table does not exist or error: ' . $conn->error;
}
?>