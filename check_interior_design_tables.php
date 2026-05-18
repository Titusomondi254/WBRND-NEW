<?php
/**
 * Check Interior Design Tables
 * Verify that the interior design tables were created successfully
 */

require_once 'config.php';

echo "<h1>Interior Design Database Tables Check</h1>";

$tables_to_check = [
    'interior_designs',
    'design_inquiries',
    'design_favorites',
    'design_reviews'
];

$all_exist = true;

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Table '$table' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        $all_exist = false;
    }
}

echo "<hr>";

if ($all_exist) {
    echo "<p style='color: green; font-weight: bold;'>All interior design tables are ready! You can now:</p>";
    echo "<ul>";
    echo "<li><a href='upload_design.php'>Upload new designs</a> (Agents)</li>";
    echo "<li><a href='browse_designs.php'>Browse designs</a> (Clients)</li>";
    echo "<li><a href='my_designs.php'>Manage your designs</a> (Agents)</li>";
    echo "<li><a href='design_inquiries.php'>View design inquiries</a> (Agents)</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red; font-weight: bold;'>Some tables are missing. Please run the setup script again.</p>";
    echo "<p><a href='setup_interior_design.php'>Run Setup Again</a></p>";
}

$conn->close();
?>