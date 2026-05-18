<?php
require_once 'config.php';

echo "Unique locations in verified properties:\n";
$result = $conn->query("SELECT DISTINCT location FROM properties WHERE verification_status = 'verified' ORDER BY location");

while($row = $result->fetch_assoc()) {
    echo "- " . $row['location'] . "\n";
}
?>