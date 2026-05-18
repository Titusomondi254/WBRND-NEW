<?php
require_once 'config.php';

echo "<h2>🔍 Cleaning Services Tables Check</h2>";

// Check if cleaning tables exist
$tables = ['cleaning_categories', 'cleaning_requests', 'service_providers', 'provider_assignments', 'service_reviews'];
$existing_tables = [];

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        $existing_tables[] = $table;
    }
}

if (empty($existing_tables)) {
    echo "<p style='color: red;'>❌ No cleaning services tables found. Need to run setup.</p>";
    echo "<p><a href='cleaning_services/setup.php?key=setup_cleaning_2026'>Run Setup Now</a></p>";
} else {
    echo "<p style='color: green;'>✅ Found " . count($existing_tables) . " cleaning tables:</p>";
    echo "<ul>";
    foreach ($existing_tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Check if data exists
    $result = $conn->query("SELECT COUNT(*) as count FROM cleaning_categories");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>Categories: $count</p>";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM cleaning_requests");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "<p>Requests: $count</p>";
    }
}
?>