<?php
require_once 'config.php';

$result = $conn->query("DESCRIBE consultations");
if ($result) {
    echo "<pre>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "</pre>";
} else {
    echo "Error: " . $conn->error;
}
?>
