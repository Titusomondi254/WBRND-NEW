<?php
require_once 'config.php';

echo "Users table columns:\n";
$result = $conn->query("DESCRIBE users");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . "\n";
    }
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>