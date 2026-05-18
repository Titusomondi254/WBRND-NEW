<?php
require_once 'config.php';

$count = $conn->query("SELECT COUNT(*) as cnt FROM properties WHERE main_category = 'student_housing'");
$r = $count->fetch_assoc();
echo "Student housing records: " . $r['cnt'] . "\n";

// Show one sample
$sample = $conn->query("SELECT id, location, price, electricity_bill, water_bill FROM properties WHERE main_category = 'student_housing' LIMIT 1");
if ($row = $sample->fetch_assoc()) {
    echo "Sample: " . $row['location'] . " - KES " . $row['price'] . " - Elec: " . $row['electricity_bill'] . " - Water: " . $row['water_bill'] . "\n";
}
?>
