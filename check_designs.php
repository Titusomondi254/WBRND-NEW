<?php
require_once "config.php";

$result = $conn->query("SELECT COUNT(*) as count FROM interior_designs");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "Total designs: $count\n";

    if ($count > 0) {
        $designs = $conn->query("SELECT id, title, status, video_file, video_url, created_at FROM interior_designs ORDER BY created_at DESC LIMIT 5");
        while ($row = $designs->fetch_assoc()) {
            $has_video = (!empty($row['video_file']) || !empty($row['video_url'])) ? 'YES' : 'NO';
            echo "ID: {$row['id']}, Title: {$row['title']}, Status: {$row['status']}, Video: $has_video, Created: {$row['created_at']}\n";
        }
    }
} else {
    echo "Query failed\n";
}
?>