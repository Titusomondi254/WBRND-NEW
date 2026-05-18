<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Create a test viewing request as TITUS OMONDI (user 9)
$user_id = 9;
$property_id = 14; // First property from check_properties.php

// Insert viewing request
$stmt = $conn->prepare("
    INSERT INTO viewing_requests (
        property_id, user_id, viewing_fee, requested_date, requested_time,
        contact_number, additional_notes, terms_accepted, terms_accepted_at, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
");

$viewing_fee = 1000.00;
$requested_date = date('Y-m-d', strtotime('+1 day'));
$requested_time = '10:00';
$contact_number = '0712345678';
$additional_notes = 'Test viewing request from admin user';
$terms_accepted = 1;

$stmt->bind_param("iiddsssi", $property_id, $user_id, $viewing_fee, $requested_date, $requested_time, $contact_number, $additional_notes, $terms_accepted);

if ($stmt->execute()) {
    $viewing_request_id = $stmt->insert_id;
    echo "Test viewing request created with ID: $viewing_request_id" . PHP_EOL;
} else {
    echo "Failed to create viewing request: " . $stmt->error . PHP_EOL;
}

$stmt->close();
$conn->close();
?>