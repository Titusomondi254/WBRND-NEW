<?php
require_once 'config.php';

$columns = [
    "ALTER TABLE storage_facilities ADD COLUMN IF NOT EXISTS photo_url VARCHAR(500)",
    "ALTER TABLE storage_facilities ADD COLUMN IF NOT EXISTS video_url VARCHAR(500)"
];

foreach ($columns as $sql) {
    if ($conn->query($sql)) {
        echo "✓ Added column\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}

echo "Done!\n";
?>
