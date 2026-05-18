<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);

session_start();
ob_start();

function send_json_response(array $data, int $status = 200): void {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit();
}

// Set a flag to track where we fail
define('DEBUG_MODE', true);

try {
    require_once 'config.php';
} catch (Exception $e) {
    send_json_response(['success' => false, 'message' => 'Config load error: ' . $e->getMessage()], 500);
}

try {
    require_once 'helpers.php';
} catch (Exception $e) {
    send_json_response(['success' => false, 'message' => 'Helpers load error: ' . $e->getMessage()], 500);
}

try {
    require_once 'notification_utils.php';
} catch (Exception $e) {
    send_json_response(['success' => false, 'message' => 'Notification utils load error: ' . $e->getMessage()], 500);
}

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Check database connection
if (!$conn) {
    send_json_response(['success' => false, 'message' => 'Database connection failed. Please contact support.'], 500);
}

// Ensure installation_payments table exists
if (function_exists('ensure_installation_payments_table_exists')) {
    ensure_installation_payments_table_exists($conn);
}

// Check if user is logged in
if (!is_logged_in()) {
    send_json_response(['success' => false, 'message' => 'Please log in to request installation.'], 401);
}

$user_id = intval($_SESSION['user_id']);

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(['success' => false, 'message' => 'Invalid request method.'], 405);
}

try {
    // Get and validate form data
    $product_id = intval($_POST['product_id'] ?? 0);
    $client_name = trim($_POST['client_name'] ?? '');
    $client_contact = trim($_POST['client_contact'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_location = trim($_POST['client_location'] ?? '');
    $installation_date = trim($_POST['installation_date'] ?? '');
    $installation_time = trim($_POST['installation_time'] ?? '');
    $product_name = trim($_POST['product_name'] ?? '');
    $product_price = floatval($_POST['product_price'] ?? 0);
    $additional_notes = trim($_POST['additional_notes'] ?? '');
    
    // M-Pesa payment details
    $mpesa_name = trim($_POST['mpesa_name'] ?? '');
    $mpesa_code = trim($_POST['mpesa_code'] ?? '');
    $mpesa_contact = trim($_POST['mpesa_contact'] ?? '');
    $mpesa_time = trim($_POST['mpesa_time'] ?? '');
    $terms_accepted = isset($_POST['terms_accepted']) ? 1 : 0;

    // Validate all required fields
    $errors = [];
    if (empty($client_name)) $errors[] = 'Client name is required';
    if (empty($client_contact)) $errors[] = 'Phone number is required';
    if (empty($client_email)) $errors[] = 'Email is required';
    if (empty($client_location)) $errors[] = 'Installation location is required';
    if (empty($installation_date)) $errors[] = 'Installation date is required';
    if (empty($installation_time)) $errors[] = 'Installation time is required';
    if (empty($mpesa_name)) $errors[] = 'M-Pesa account name is required';
    if (empty($mpesa_code)) $errors[] = 'Transaction code is required';
    if (empty($mpesa_contact)) $errors[] = 'M-Pesa contact is required';
    if (empty($mpesa_time)) $errors[] = 'Payment time is required';
    if (!$terms_accepted) $errors[] = 'You must accept the terms and conditions';
    if ($product_id <= 0) $errors[] = 'Invalid product';
    if ($product_price <= 0) $errors[] = 'Invalid product price';

    if (!empty($errors)) {
        send_json_response(['success' => false, 'message' => implode(', ', $errors)], 400);
    }

    // Validate file upload
    if (!isset($_FILES['mpesa_screenshot']) || $_FILES['mpesa_screenshot']['error'] !== UPLOAD_ERR_OK) {
        send_json_response(['success' => false, 'message' => 'Please upload a valid M-Pesa screenshot.'], 400);
    }

    $file = $_FILES['mpesa_screenshot'];
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    $max_size = 5 * 1024 * 1024; // 5MB

    // Validate file type
    if (!in_array($file['type'], $allowed_types)) {
        send_json_response(['success' => false, 'message' => 'Invalid file type. Please upload a JPG, JPEG, or PNG image.'], 400);
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        send_json_response(['success' => false, 'message' => 'File size too large. Maximum size is 5MB.'], 400);
    }

    // Create upload directory
    $upload_dir = 'uploads/installation_payments/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $unique_filename = 'mpesa_' . $product_id . '_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $unique_filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        send_json_response(['success' => false, 'message' => 'Failed to save uploaded file. Please try again.'], 500);
    }

    // Verify product exists
    $product_stmt = $conn->prepare("
        SELECT dp.*, u.first_name, u.last_name, u.email as agent_email, u.id as agent_id
        FROM agent_digital_products dp
        LEFT JOIN users u ON dp.agent_id = u.id
        WHERE dp.id = ? AND dp.status = 'active'
    ");
    $product_stmt->bind_param('i', $product_id);
    $product_stmt->execute();
    $product = $product_stmt->get_result()->fetch_assoc();
    $product_stmt->close();

    if (!$product) {
        unlink($upload_path); // Clean up uploaded file
        send_json_response(['success' => false, 'message' => 'Product not found or not available.'], 400);
    }

    // Calculate payment amounts
    $total_price = $product_price;
    $payment_70_percent = round($total_price * 0.70);
    $payment_30_percent = $total_price - $payment_70_percent;

    // Create installation request in consultations table
    $description = "Installation request for: " . $product_name . "\n\n" .
                   "Product: " . $product['product_name'] . "\n" .
                   "Price: KES " . number_format($total_price, 2) . "\n" .
                   "Payment Status: 70% Pending Verification\n" .
                   "Client Location: " . $client_location . "\n" .
                   "Installation Date: " . $installation_date . " at " . $installation_time . "\n" .
                   "Additional Notes: " . ($additional_notes ?: "None");

    $insert_stmt = $conn->prepare("
        INSERT INTO consultations (
            user_id,
            consultation_type,
            scheduled_date,
            contact_number,
            email,
            issue_description,
            product_title,
            product_category,
            status,
            agent_id,
            created_at
        ) VALUES (?, 'digital_installation', ?, ?, ?, ?, ?, ?, 'pending_payment_verification', ?, NOW())
    ");

    $product_category = $product['product_category'] ?? 'digital_service';
    $insert_stmt->bind_param(
        'issssssi',
        $user_id,
        $installation_date,
        $client_contact,
        $client_email,
        $description,
        $product_name,
        $product_category,
        $product['agent_id']
    );

    if (!$insert_stmt->execute()) {
        unlink($upload_path);
        send_json_response(['success' => false, 'message' => 'Failed to create installation request.'], 500);
    }

    $request_id = $conn->insert_id;
    $insert_stmt->close();

    // Create payment verification record
    $payment_stmt = $conn->prepare("
        INSERT INTO installation_payments (
            consultation_id,
            user_id,
            mpesa_name,
            mpesa_code,
            mpesa_contact,
            mpesa_time,
            screenshot_path,
            payment_amount,
            payment_percentage,
            payment_status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification', NOW())
    ");

    $payment_percentage = 70;
    $payment_stmt->bind_param(
        'iisssssdi',
        $request_id,
        $user_id,
        $mpesa_name,
        $mpesa_code,
        $mpesa_contact,
        $mpesa_time,
        $upload_path,
        $payment_70_percent,
        $payment_percentage
    );

    if (!$payment_stmt->execute()) {
        unlink($upload_path);
        send_json_response(['success' => false, 'message' => 'Failed to record payment information.'], 500);
    }

    $payment_stmt->close();

    // Log the action
    if (function_exists('logUserAction')) {
        logUserAction($user_id, 'request_digital_installation_with_payment', $request_id, null, null, [
            'product_id' => $product_id,
            'product_name' => $product_name,
            'payment_amount' => $payment_70_percent
        ]);
    }

    // Send notifications (if functions exist)
    if (function_exists('notifyAdminNewInstallationRequest')) {
        notifyAdminNewInstallationRequest(
            $request_id,
            $client_name,
            $product_name,
            $client_location,
            $installation_date,
            $installation_time,
            $client_contact,
            $mpesa_code,
            $payment_70_percent
        );
    }

    send_json_response([
        'success' => true,
        'message' => 'Installation request submitted successfully! Admin will verify your payment within 24 hours.',
        'request_id' => $request_id,
        'payment_70' => $payment_70_percent,
        'payment_30' => $payment_30_percent
    ], 200);

} catch (Exception $e) {
    send_json_response([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}
?>