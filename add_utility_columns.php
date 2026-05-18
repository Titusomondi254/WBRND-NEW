<?php
require_once 'config.php';

$columns = array('electricity_bill', 'water_bill');
foreach ($columns as $col) {
    $check = $conn->query("SHOW COLUMNS FROM properties LIKE '$col'");
    if ($check->num_rows == 0) {
        $conn->query("ALTER TABLE properties ADD COLUMN $col VARCHAR(20) DEFAULT 'included'");
        echo "✓ Added $col\n";
    } else {
        echo "✓ $col already exists\n";
    }
}
echo "\nDatabase schema updated successfully!";
?>
