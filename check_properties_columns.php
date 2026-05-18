<?php
require_once 'config.php';

echo "<h2>Properties Table Columns</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";

$result = $conn->query("DESCRIBE properties");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "</tr>";
    }
} else {
    echo "Error: " . $conn->error;
}
echo "</table>";

// Check if occupancy_type column exists
echo "<h3>Checking for 'occupancy_type' column...</h3>";
$check = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'properties' AND COLUMN_NAME = 'occupancy_type'");
if ($check && $check->num_rows > 0) {
    echo "<p style='color: green;'>✓ Column EXISTS</p>";
} else {
    echo "<p style='color: red;'>✗ Column DOES NOT EXIST</p>";
}
?>
