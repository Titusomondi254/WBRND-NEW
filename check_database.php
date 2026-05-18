<?php
/**
 * Check Database Tables
 * See what tables exist in the database
 */

require_once 'config.php';

echo "<h1>Database Tables Check</h1>";

$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<h2>Existing Tables:</h2>";
    echo "<ul>";
    while ($row = $result->fetch_array()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Error getting tables: " . $conn->error . "</p>";
}

// Check if users table exists and its structure
$result = $conn->query("DESCRIBE users");
if ($result) {
    echo "<h2>Users Table Structure:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Users table does not exist or cannot be described: " . $conn->error . "</p>";
}

$conn->close();
?>