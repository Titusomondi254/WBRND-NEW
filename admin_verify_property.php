<?php
/**
 * Property Verification Handler
 * Handles admin verification, rejection, and batch operations on properties
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */

session_start();
require_once 'config.php';
require_once 'helpers.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('property_listings.php');
}

$propertyId = intval($_POST['property_id'] ?? 0);
$viewingFee = floatval($_POST['viewing_fee'] ?? 0);
$acceptTerms = isset($_POST['accept_terms']) ? 1 : 0;

if ($propertyId <= 0 || $viewingFee <= 0 || !$acceptTerms) {
    set_flash_message('error', 'Please complete the viewing request form.');
    redirect('property_listings.php');
}

$stmt = $conn->prepare("
    SELECT id, agent_id, bedrooms
    FROM properties
    WHERE id = ? AND verification_status = 'verified' AND occupancy_status = 'available'
");
$stmt->bind_param('i', $propertyId);
$stmt->execute();
$property = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$property) {
    set_flash_message('error', 'Property is not available for viewing.');
    redirect('property_listings.php');
}

$expectedFee = $property['bedrooms'] <= 2 ? 1000 : ($property['bedrooms'] <= 4 ? 1500 : 2000);
if ($expectedFee !== $viewingFee) {
    set_flash_message('error', 'Viewing fee mismatch.');
    redirect('property_listings.php');
}

$clientId = $_SESSION['user']['id'] ?? 0;
$clientPhone = $_SESSION['user']['phone'] ?? '';

$requested_date = date('Y-m-d');
$requested_time = date('H:i:s');
$viewing_fee = $viewingFee;

$requestId = create_viewing_request(
    $conn,
    $propertyId,
    $clientId,
    $viewing_fee,
    $requested_date,
    $requested_time,
    $clientPhone,
    null,
    1,
    'pending'
);

if ($requestId) {

    create_notification($clientId, 'viewing_request', "Your viewing request for property #{$propertyId} is pending.", $requestId);

    $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin'");
    $adminStmt->execute();
    $admins = $adminStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $adminStmt->close();

    foreach ($admins as $admin) {
        create_notification($admin['id'], 'viewing_request', "New viewing request for property #{$propertyId}.", $requestId);
    }

    if ($agentId) {
        create_notification($agentId, 'viewing_request', "New viewing request for property #{$propertyId}.", $requestId);
    }

    set_flash_message('success', 'Viewing request submitted successfully.');
} else {
    set_flash_message('error', 'Failed to submit viewing request.');
}

redirect('property_listings.php');
?>
