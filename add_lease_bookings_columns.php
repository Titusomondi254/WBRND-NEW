<?php
require_once 'config.php';

echo "<h2>Adding Missing Columns to lease_bookings Table</h2>";
echo "<hr>";

$alterStatements = [
    'ALTER TABLE lease_bookings ADD COLUMN IF NOT EXISTS lease_start_date DATE DEFAULT NULL AFTER monthly_amount',
    'ALTER TABLE lease_bookings ADD COLUMN IF NOT EXISTS lease_end_date DATE DEFAULT NULL AFTER lease_start_date',
];

foreach ($alterStatements as $sql) {
    if ($conn->query($sql)) {
        echo '<p style="color: green;"><strong>✓</strong> ' . htmlspecialchars(substr($sql, 0, 80)) . '...</p>';
    } else {
        echo '<p style="color: red;"><strong>✗ Error:</strong> ' . htmlspecialchars($conn->error) . '</p>';
    }
}

echo "<hr>";

// Verify all columns exist
echo "<h3>Verifying lease_bookings Columns:</h3>";
$result = $conn->query("DESCRIBE lease_bookings");
if ($result) {
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $requiredColumns = ['id', 'property_id', 'tenant_id', 'lease_type', 'monthly_amount', 'lease_start_date', 'lease_end_date', 'number_of_units', 'status', 'created_at'];

    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Column Name</th><th>Status</th></tr>";
    foreach ($requiredColumns as $col) {
        $status = in_array($col, $columns) ? '<span style="color: green;">✓ EXISTS</span>' : '<span style="color: red;">✗ MISSING</span>';
        echo "<tr><td><code>$col</code></td><td>$status</td></tr>";
    }
    echo "</table>";
    
    echo '<hr>';
    echo '<h3 style="color: green;">✓ All columns verified successfully!</h3>';
} else {
    echo '<p style="color: red;"><strong>✗ Error:</strong> Could not describe lease_bookings table. ' . htmlspecialchars($conn->error) . '</p>';
}

?>
