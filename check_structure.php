<?php
require_once 'config.php';
$result = $conn->query('DESCRIBE offplan_project_documents');
echo "offplan_project_documents table structure:\n";
while($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
}
?>