<?php
/**
 * Mover System Configuration
 * Handles all configuration for the moving service booking system
 */

// Database Configuration (should match main config.php)
define('MOVER_DB_HOST', 'localhost');
define('MOVER_DB_USER', 'root');
define('MOVER_DB_PASSWORD', '');
define('MOVER_DB_NAME', 'walbrand_movers');

// Google Maps API Key
// Get this from: https://console.cloud.google.com/
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBi_-tc7txucoA3YVh__5RUt0UVAlFBW-8');

// Pricing Constants
const MOVER_PRICING = [
    '1_bedroom' => [
        'label' => '1 Bedroom or Below',
        'within_nairobi' => 13000,
        'outside_rate_per_km' => 600
    ],
    '2_3_bedroom' => [
        'label' => '2 to 3 Bedroom',
        'within_nairobi' => 25000,
        'outside_rate_per_km' => 600
    ],
    '4_bedroom_plus' => [
        'label' => '4 Bedroom and Above',
        'within_nairobi' => 35000,
        'outside_rate_per_km' => 600
    ]
];

// Service Types
const SERVICE_TYPES = [
    'within_nairobi' => 'Within Nairobi',
    'outside_nairobi' => 'Outside Nairobi'
];

// Booking Statuses
const BOOKING_STATUSES = [
    'pending' => 'Pending Assignment',
    'payment_pending' => 'Awaiting Payment',
    'assigned' => 'Assigned to Group',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
];

// Payment Statuses
const PAYMENT_STATUSES = [
    'pending' => 'Pending',
    'completed' => 'Completed',
    'failed' => 'Failed'
];

// Tracking Statuses
const DELIVERY_TRACKING_STATUSES = [
    'awaiting_pickup' => 'Awaiting Pickup',
    'en_route' => 'En Route',
    'paused' => 'Paused',
    'delivered' => 'Delivered',
    'offline' => 'Offline'
];

// Route Optimization Settings
const ROUTE_OPTIMIZATION_MODES = [
    'fastest' => 'Fastest Route',
    'most_efficient' => 'Most Efficient',
    'balanced' => 'Balanced'
];

// Wallet transaction types
const WALLET_TRANSACTION_TYPES = [
    'top_up' => 'Top-up',
    'payment' => 'Payment',
    'refund' => 'Refund',
    'commission' => 'Commission'
];

// Dispute statuses
const DISPUTE_STATUSES = [
    'open' => 'Open',
    'under_review' => 'Under Review',
    'resolved' => 'Resolved',
    'rejected' => 'Rejected'
];

// Insurance statuses
const INSURANCE_STATUSES = [
    'active' => 'Active',
    'expired' => 'Expired',
    'claimed' => 'Claimed',
    'cancelled' => 'Cancelled'
];

// Nairobi Geolocation Boundaries (approximate)
// Used to determine if a location is within Nairobi
const NAIROBI_BOUNDS = [
    'north' => -1.0,
    'south' => -1.5,
    'east' => 37.2,
    'west' => 36.5
];

// Members per group
define('MOVER_GROUP_SIZE', 5);

// SMS/Notification Settings
const NOTIFICATION_SETTINGS = [
    'enable_sms' => false,  // Set to true when SMS integration is ready
    'enable_email' => true,
    'enable_whatsapp' => false  // Set to true when WhatsApp integration is ready
];

// Establish Database Connection
function getMoverDatabaseConnection() {
    try {
        $conn = new mysqli(
            MOVER_DB_HOST,
            MOVER_DB_USER,
            MOVER_DB_PASSWORD,
            MOVER_DB_NAME
        );

        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }

        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return false;
    }
}

// Sanitize Location Input
function sanitizeLocation($location) {
    return htmlspecialchars(trim($location), ENT_QUOTES, 'UTF-8');
}

// Validate Email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate Phone (Kenya format)
function validatePhone($phone) {
    // Remove non-numeric characters
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Check if valid Kenya phone format
    return preg_match('/^(\+254|0)[1-9]\d{8}$/', $phone);
}

// Format Currency (KES)
function formatCurrency($amount) {
    return number_format($amount, 2) . ' KES';
}

// Calculate Booking Cost
function calculateBookingCost($houseType, $distance, $isWithinNairobi) {
    if (!isset(MOVER_PRICING[$houseType])) {
        return false;
    }

    $pricing = MOVER_PRICING[$houseType];

    if ($isWithinNairobi) {
        return $pricing['within_nairobi'];
    } else {
        return max($pricing['within_nairobi'], $distance * $pricing['outside_rate_per_km']);
    }
}

// Check if location is within Nairobi (basic coordinate check)
function isWithinNairobi($latitude, $longitude) {
    return ($latitude >= NAIROBI_BOUNDS['south'] && 
            $latitude <= NAIROBI_BOUNDS['north'] &&
            $longitude >= NAIROBI_BOUNDS['west'] && 
            $longitude <= NAIROBI_BOUNDS['east']);
}

// Get all Nairobi areas from database
function getNairobiAreas($conn) {
    $stmt = $conn->prepare("SELECT id, area_name, latitude, longitude FROM nairobi_areas ORDER BY area_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    $areas = [];

    while ($row = $result->fetch_assoc()) {
        $areas[] = $row;
    }

    $stmt->close();
    return $areas;
}

// Log Activity
function logMoverActivity($conn, $bookingId, $action, $details = '') {
    $stmt = $conn->prepare("
        INSERT INTO mover_activity_logs (booking_id, action, details, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("iss", $bookingId, $action, $details);
    return $stmt->execute();
}

// Send a team notification and optionally persist it for the assigned group
function sendMoverNotification($conn, $groupId, $bookingId, $message) {
    if (!$conn) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO mover_notifications (group_id, booking_id, message, is_read, created_at) VALUES (?, ?, ?, FALSE, NOW())");
    $stmt->bind_param("iis", $groupId, $bookingId, $message);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function sendDeliveryNotification($email, $phone, $subject, $message) {
    $sent = false;

    if (NOTIFICATION_SETTINGS['enable_email'] && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $headers = "From: no-reply@walbrand.co.ke\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = @mail($email, $subject, $message, $headers) || $sent;
    }

    if (NOTIFICATION_SETTINGS['enable_sms'] && !empty($phone)) {
        // Placeholder for SMS integration; enable when SMS provider is configured
        $sent = true;
    }

    return $sent;
}

function recordGpsTracking($conn, $bookingId, $groupId, $latitude, $longitude, $status = 'en_route', $deviceId = null, $locationLabel = null, $accuracy = null) {
    $stmt = $conn->prepare("INSERT INTO mover_tracking (booking_id, group_id, device_id, latitude, longitude, accuracy, tracking_status, location_label, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissddis", $bookingId, $groupId, $deviceId, $latitude, $longitude, $accuracy, $status, $locationLabel);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function createDeliveryReview($conn, $bookingId, $groupId, $customerName, $rating, $punctuality, $professionalism, $handling, $reviewText) {
    if ($rating < 1 || $rating > 5) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO mover_reviews (booking_id, group_id, customer_name, rating, punctuality_rating, professionalism_rating, handling_rating, review_text, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisiiiis", $bookingId, $groupId, $customerName, $rating, $punctuality, $professionalism, $handling, $reviewText);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function getOrCreateMoverWallet($conn, $userId) {
    $stmt = $conn->prepare("SELECT id, balance FROM mover_wallets WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wallet = $result->fetch_assoc();
    $stmt->close();

    if ($wallet) {
        return $wallet;
    }

    $stmt = $conn->prepare("INSERT INTO mover_wallets (user_id, balance, created_at, updated_at) VALUES (?, 0.00, NOW(), NOW())");
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $walletId = $conn->insert_id;
    $stmt->close();
    return ['id' => $walletId, 'balance' => 0.00];
}

function addWalletTransaction($conn, $walletId, $bookingId, $amount, $type, $reference = null, $details = null) {
    if (!in_array($type, array_keys(WALLET_TRANSACTION_TYPES), true)) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO mover_wallet_transactions (wallet_id, booking_id, amount, type, status, reference, details, created_at) VALUES (?, ?, ?, ?, 'completed', ?, ?, NOW())");
    $stmt->bind_param("iidsss", $walletId, $bookingId, $amount, $type, $reference, $details);
    $success = $stmt->execute();
    $stmt->close();

    if ($success) {
        $stmt = $conn->prepare("UPDATE mover_wallets SET balance = balance + ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("di", $amount, $walletId);
        $stmt->execute();
        $stmt->close();
    }

    return $success;
}

function openDeliveryDispute($conn, $bookingId, $userId, $issueType, $description, $requestedRefund = 0) {
    if (!in_array($issueType, ['damaged', 'delayed', 'lost', 'payment', 'other'], true)) {
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO mover_disputes (booking_id, user_id, issue_type, description, status, requested_refund, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', ?, NOW(), NOW())");
    $stmt->bind_param("iissd", $bookingId, $userId, $issueType, $description, $requestedRefund);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

function optimizeDeliveryRoute($origin, $destination, $mode = 'balanced') {
    // Placeholder: integrate with a route optimization or traffic API
    return [
        'mode' => $mode,
        'route' => [
            'origin' => $origin,
            'destination' => $destination,
            'expected_duration_minutes' => null,
            'total_distance_km' => null,
            'advice' => 'Route optimization will be provided by the connected map provider.'
        ]
    ];
}

?>
