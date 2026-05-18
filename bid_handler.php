<?php
/**
 * Bid Handler - Manages property bids and offers
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */

session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'place_bid') {

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please login first']);
            exit();
        }

        $property_id = intval($_POST['property_id']);
        $bid_amount = floatval($_POST['bid_amount']);
        $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
        $monthly_mortgage = floatval($_POST['monthly_mortgage'] ?? 0);
        $message = sanitize($_POST['message'] ?? '');

        // Validate inputs
        if ($property_id <= 0 || $bid_amount <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid property or bid amount']);
            exit();
        }

        // Check if property exists and is available
        $property_check = $conn->prepare("SELECT id, price, seller_id, status FROM properties WHERE id = ? AND verification_status = 'verified'");
        $property_check->bind_param("i", $property_id);
        $property_check->execute();
        $property_result = $property_check->get_result();

        if ($property_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Property not found or not available']);
            exit();
        }

        $property = $property_result->fetch_assoc();
        $property_check->close();

        // Don't allow bidding on own property
        if ($property['seller_id'] == $_SESSION['user_id']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You cannot bid on your own property']);
            exit();
        }

        // Check if user already has an active bid on this property
        $existing_bid_check = $conn->prepare("SELECT id FROM bids WHERE property_id = ? AND buyer_id = ? AND status = 'active'");
        $existing_bid_check->bind_param("ii", $property_id, $_SESSION['user_id']);
        $existing_bid_check->execute();
        $existing_result = $existing_bid_check->get_result();

        if ($existing_result->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'You already have an active bid on this property']);
            exit();
        }

        $existing_bid_check->close();

        // Insert bid
        $insert_bid = $conn->prepare("INSERT INTO bids (property_id, buyer_id, bid_amount, deposit_amount, monthly_mortgage, status, bid_date) VALUES (?, ?, ?, ?, ?, 'active', NOW())");
        $insert_bid->bind_param("iiddd", $property_id, $_SESSION['user_id'], $bid_amount, $deposit_amount, $monthly_mortgage);

        if ($insert_bid->execute()) {
            $bid_id = $conn->insert_id;

            // Log the bid action
            logUserAction($_SESSION['user_id'], 'place_bid', 'bids', $bid_id, null, [
                'property_id' => $property_id,
                'bid_amount' => $bid_amount,
                'deposit_amount' => $deposit_amount,
                'monthly_mortgage' => $monthly_mortgage
            ]);

            // Send notification to seller
            $seller_query = $conn->prepare("SELECT email, name FROM users WHERE id = ?");
            $seller_query->bind_param("i", $property['seller_id']);
            $seller_query->execute();
            $seller_result = $seller_query->get_result();

            if ($seller_result->num_rows > 0) {
                $seller = $seller_result->fetch_assoc();
                $seller_subject = 'New Bid Received - Walbrand Properties Marketplace & Interiors';
                $seller_message = "Dear " . htmlspecialchars($seller['name']) . ",\n\n" .
                    "You have received a new bid on your property.\n\n" .
                    "Bid Details:\n" .
                    "Property ID: " . $property_id . "\n" .
                    "Bid Amount: KES " . number_format($bid_amount, 0) . "\n" .
                    "Deposit Amount: KES " . number_format($deposit_amount, 0) . "\n" .
                    "Monthly Mortgage: KES " . number_format($monthly_mortgage, 0) . "\n" .
                    "Bid Date: " . date('Y-m-d H:i:s') . "\n\n" .
                    "Please login to your dashboard to review and respond to this bid.\n\n" .
                    "Best regards,\n" .
                    "Walbrand Properties Marketplace & Interiors Team\n" .
                    SITE_URL;

                $seller_headers = 'From: ' . ADMIN_EMAIL . "\r\n" .
                               'Reply-To: ' . ADMIN_EMAIL . "\r\n" .
                               'X-Mailer: PHP/' . phpversion();

                @mail($seller['email'], $seller_subject, $seller_message, $seller_headers);
            }

            $seller_query->close();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Bid placed successfully! The seller will be notified.',
                'bid_id' => $bid_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error placing bid']);
        }

        $insert_bid->close();
        exit();
    }

    if ($_POST['action'] === 'respond_to_bid') {

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please login first']);
            exit();
        }

        $bid_id = intval($_POST['bid_id']);
        $response = sanitize($_POST['response']); // 'accept' or 'reject'
        $notes = sanitize($_POST['notes'] ?? '');

        // Validate response
        if (!in_array($response, ['accept', 'reject'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid response']);
            exit();
        }

        // Check if bid exists and user is the seller
        $bid_check = $conn->prepare("
            SELECT b.*, p.seller_id, p.property_type, p.location, u.email as buyer_email, u.name as buyer_name
            FROM bids b
            JOIN properties p ON b.property_id = p.id
            JOIN users u ON b.buyer_id = u.id
            WHERE b.id = ? AND b.status = 'active'
        ");
        $bid_check->bind_param("i", $bid_id);
        $bid_check->execute();
        $bid_result = $bid_check->get_result();

        if ($bid_result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Bid not found']);
            exit();
        }

        $bid = $bid_result->fetch_assoc();
        $bid_check->close();

        // Check if current user is the seller
        if ($bid['seller_id'] != $_SESSION['user_id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'You are not authorized to respond to this bid']);
            exit();
        }

        $new_status = $response === 'accept' ? 'accepted' : 'rejected';

        // Update bid status
        $update_bid = $conn->prepare("UPDATE bids SET status = ?, response_date = NOW() WHERE id = ?");
        $update_bid->bind_param("si", $new_status, $bid_id);

        if ($update_bid->execute()) {
            // Log the response action
            logUserAction($_SESSION['user_id'], 'respond_to_bid', 'bids', $bid_id, null, [
                'response' => $response,
                'notes' => $notes
            ]);

            // Send notification to buyer
            $buyer_subject = 'Bid Response - Walbrand Properties Marketplace & Interiors';
            $buyer_message = "Dear " . htmlspecialchars($bid['buyer_name']) . ",\n\n" .
                "Your bid on property '" . htmlspecialchars($bid['property_type']) . " - " . htmlspecialchars($bid['location']) . "' has been " . $response . "ed.\n\n" .
                "Bid Details:\n" .
                "Bid Amount: KES " . number_format($bid['bid_amount'], 0) . "\n" .
                "Response: " . ucfirst($response) . "\n" .
                "Response Date: " . date('Y-m-d H:i:s') . "\n";

            if (!empty($notes)) {
                $buyer_message .= "Seller Notes: " . $notes . "\n";
            }

            $buyer_message .= "\nPlease login to your dashboard for more details.\n\n" .
                "Best regards,\n" .
                "Walbrand Properties Marketplace & Interiors Team\n" .
                SITE_URL;

            $buyer_headers = 'From: ' . ADMIN_EMAIL . "\r\n" .
                           'Reply-To: ' . ADMIN_EMAIL . "\r\n" .
                           'X-Mailer: PHP/' . phpversion();

            @mail($bid['buyer_email'], $buyer_subject, $buyer_message, $buyer_headers);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Bid ' . $response . 'ed successfully!'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error updating bid']);
        }

        $update_bid->close();
        exit();
    }
}

// Redirect if not POST request
header("Location: index.php");
exit();
?>