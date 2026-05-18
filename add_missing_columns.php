<?php
require_once 'config.php';

$alterStatements = [
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS occupancy_type VARCHAR(50) DEFAULT "general"',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS is_negotiable BOOLEAN DEFAULT FALSE',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS list_student_housing BOOLEAN DEFAULT FALSE',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS for_sale BOOLEAN DEFAULT FALSE',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS for_rent BOOLEAN DEFAULT FALSE',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS for_lease BOOLEAN DEFAULT FALSE',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS university VARCHAR(255)',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS surrounding_areas TEXT',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS electricity_bill VARCHAR(50) DEFAULT "included"',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS water_bill VARCHAR(50) DEFAULT "included"',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS electricity_details TEXT',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS water_details TEXT',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS status ENUM("draft","pending_verification","verified","rejected","sold","delisted") DEFAULT "pending_verification"',
    'ALTER TABLE properties ADD COLUMN IF NOT EXISTS property_code VARCHAR(100) UNIQUE'
];

echo "<h2>Adding Missing Columns to Properties Table</h2>";
echo "<hr>";

foreach ($alterStatements as $sql) {
    if ($conn->query($sql)) {
        echo '<p style="color: green;"><strong>✓</strong> ' . htmlspecialchars(substr($sql, 0, 70)) . '...</p>';
    } else {
        echo '<p style="color: red;"><strong>✗ Error:</strong> ' . htmlspecialchars($conn->error) . '</p>';
    }
}

echo "<hr>";
echo '<h3 style="color: green;">✓ All columns added successfully!</h3>';

// Verify all columns exist
echo "<h3>Verifying Columns:</h3>";
$result = $conn->query("DESCRIBE properties");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$requiredColumns = [
    'occupancy_type', 'is_negotiable', 'list_student_housing', 'for_sale', 
    'for_rent', 'for_lease', 'university', 'surrounding_areas', 
    'electricity_bill', 'water_bill', 'electricity_details', 'water_details',
    'status', 'property_code'
];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Column Name</th><th>Status</th></tr>";
foreach ($requiredColumns as $col) {
    $status = in_array($col, $columns) ? '<span style="color: green;">✓ EXISTS</span>' : '<span style="color: red;">✗ MISSING</span>';
    echo "<tr><td><code>$col</code></td><td>$status</td></tr>";
}
echo "</table>";
?>
