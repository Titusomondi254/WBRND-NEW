<?php
require_once 'config.php';

$query = "SELECT COUNT(*) as count FROM consultations WHERE consultation_type = 'property_viewing'";
$result = $conn->query($query);
$count = $result->fetch_assoc()['count'];

echo "Total viewing requests: $count<br>";

$query = "SELECT c.*, u.email FROM consultations c LEFT JOIN users u ON c.user_id = u.id WHERE c.consultation_type = 'property_viewing' ORDER BY c.created_at DESC LIMIT 3";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<h3>Recent viewing requests:</h3>";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, User: {$row['email']}, Status: {$row['status']}, Created: {$row['created_at']}<br>";
    }
} else {
    echo "No viewing requests found.";
}
?>