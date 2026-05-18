<?php
/**
 * Add Missing Property Card Fields
 * Adds units_available and deposit_required columns if they don't exist
 */
require_once 'config.php';

$columns_to_add = [
    'units_available INT DEFAULT 1',
    'deposit_required DECIMAL(15, 2)',
    'target_audience VARCHAR(100) DEFAULT "general"',
    'category VARCHAR(50)' // Simplified category for filtering
];

echo "<h2>Adding Missing Property Card Columns</h2><hr>";

foreach ($columns_to_add as $column_def) {
    $column_name = explode(' ', $column_def)[0];
    
    // Check if column exists
    $check = $conn->query("SHOW COLUMNS FROM properties LIKE '$column_name'");
    
    if ($check->num_rows == 0) {
        $sql = "ALTER TABLE properties ADD COLUMN $column_def";
        if ($conn->query($sql)) {
            echo "<p style='color: green;'><strong>✓</strong> Added column: <code>$column_name</code></p>";
        } else {
            echo "<p style='color: red;'><strong>✗</strong> Error adding <code>$column_name</code>: " . $conn->error . "</p>";
        }
    } else {
        echo "<p style='color: blue;'><strong>ℹ</strong> Column already exists: <code>$column_name</code></p>";
    }
}

echo "<hr><h3>Updated Schema</h3>";
$result = $conn->query("DESCRIBE properties");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}
echo "<pre>";
print_r($columns);
echo "</pre>";

$conn->close();
?>
