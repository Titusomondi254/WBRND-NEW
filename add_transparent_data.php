<?php
require 'config.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) die('Connection failed: ' . $conn->connect_error);

// Add transparent data columns to properties table
$conn->query('ALTER TABLE properties ADD COLUMN deposit_amount DECIMAL(10,2) DEFAULT NULL');
$conn->query('ALTER TABLE properties ADD COLUMN deposit_type VARCHAR(50) DEFAULT NULL');
$conn->query('ALTER TABLE properties ADD COLUMN advance_payment DECIMAL(10,2) DEFAULT NULL');
$conn->query('ALTER TABLE properties ADD COLUMN additional_fees TEXT DEFAULT NULL');
$conn->query('ALTER TABLE properties ADD COLUMN payment_terms TEXT DEFAULT NULL');

echo 'Transparent data columns added successfully';
$conn->close();
?>