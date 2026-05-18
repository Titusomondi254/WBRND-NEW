<?php
require_once 'config.php';

echo "Checking table structures...\n\n";

// Check properties table structure
echo "PROPERTIES TABLE:\n";
$result = $conn->query("DESCRIBE properties");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ") " . ($row['Key'] === 'PRI' ? 'PRIMARY KEY' : ($row['Key'] === 'MUL' ? 'INDEX' : '')) . "\n";
}

echo "\nUSERS TABLE:\n";
$result = $conn->query("DESCRIBE users");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " (" . $row['Type'] . ") " . ($row['Key'] === 'PRI' ? 'PRIMARY KEY' : ($row['Key'] === 'MUL' ? 'INDEX' : '')) . "\n";
}
?>
