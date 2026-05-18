<?php
require_once 'config.php';

echo "=== PROPERTIES TABLE STRUCTURE ===\n\n";

$result = $conn->query('DESCRIBE properties');
if ($result) {
    while($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' (' . $row['Type'] . ')' . (isset($row['Key']) && $row['Key'] ? ' [' . $row['Key'] . ']' : '') . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}

echo "\n=== SAMPLE PROPERTY DATA ===\n\n";

$query = "SELECT * FROM properties LIMIT 1";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "Available fields in sample record:\n";
    foreach ($row as $key => $value) {
        echo "  $key: " . (is_null($value) ? 'NULL' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value)) . "\n";
    }
} else {
    echo "No properties found in database";
}

$conn->close();
?>
