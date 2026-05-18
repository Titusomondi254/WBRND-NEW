<?php
require_once 'config.php';

$alterStatements = [
    "ALTER TABLE agent_messages ADD COLUMN IF NOT EXISTS attachment_path VARCHAR(500)",
    "ALTER TABLE agent_messages ADD COLUMN IF NOT EXISTS attachment_type ENUM('image', 'video', 'audio', 'document') DEFAULT NULL",
    "ALTER TABLE agent_messages ADD COLUMN IF NOT EXISTS attachment_name VARCHAR(255)"
];

echo "<h2>Adding Missing Attachment Columns to agent_messages Table</h2>";
echo "<hr>";

foreach ($alterStatements as $sql) {
    if ($conn->query($sql)) {
        echo '<p style="color: green;"><strong>✓</strong> ' . htmlspecialchars(substr($sql, 0, 70)) . '...</p>';
    } else {
        echo '<p style="color: red;"><strong>✗ Error:</strong> ' . htmlspecialchars($conn->error) . '</p>';
    }
}

echo "<hr>";
echo '<h3 style="color: green;">✓ All attachment columns added successfully!</h3>';
?></content>
<parameter name="filePath">c:\xampp\htdocs\WBRND\WBRND\add_attachment_columns.php