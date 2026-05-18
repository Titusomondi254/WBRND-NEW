<?php
/**
 * CONSULTATION BOOKING SYSTEM
 * Handles scheduling consultations for various services
 */

session_start();
require_once 'config.php';
require_once 'service_fee_helper.php';
require_once 'helpers.php';
require_once 'notification_utils.php';

ensure_viewing_requests_table_exists($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'schedule_consultation') {
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Please login first']);
            exit();
        }

        error_log("Viewing request from user ID: " . $_SESSION['user_id']);

        // Sanitize inputs
        $consultation_type = mysqli_real_escape_string($conn, $_POST['consultation_type']);
        $scheduled_date_input = trim($_POST['scheduled_date'] ?? $_POST['preferred_date'] ?? '');
        $scheduled_time_input = trim($_POST['scheduled_time'] ?? $_POST['preferred_time'] ?? '');

        if (strpos($scheduled_date_input, 'T') !== false) {
            list($date_part, $time_part) = explode('T', $scheduled_date_input, 2);
            $scheduled_date = mysqli_real_escape_string($conn, $date_part);
            $scheduled_time = mysqli_real_escape_string($conn, $scheduled_time_input ?: $time_part);
        } else {
            $scheduled_date = mysqli_real_escape_string($conn, $scheduled_date_input);
            $scheduled_time = mysqli_real_escape_string($conn, $scheduled_time_input);
        }

        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number'] ?? $_POST['phone'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $issue_description = mysqli_real_escape_string($conn, $_POST['issue_description'] ?? $_POST['message'] ?? '');
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name'] ?? trim((($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''))));
        $property_id = isset($_POST['property_id']) ? intval($_POST['property_id']) : NULL;
        $accept_terms = 0;
        if (isset($_POST['accept_terms'])) {
            $accept_value = $_POST['accept_terms'];
            if (in_array($accept_value, ['1', 'on', 'true'], true)) {
                $accept_terms = 1;
            } else {
                $accept_terms = intval($accept_value);
            }
        }
        $service_fee = isset($_POST['service_fee']) ? intval($_POST['service_fee']) : 0;

        // Validate inputs
        if (empty($consultation_type) || empty($scheduled_date) || empty($contact_number) || empty($email) || empty($full_name)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
            exit();
        }

        if ($consultation_type === 'property_viewing') {
            if (!$property_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Property ID is required for viewing requests']);
                exit();
            }

            $fee_stmt = $conn->prepare("SELECT location, bedrooms FROM properties WHERE id = ? AND verification_status = 'verified'");
            $fee_stmt->bind_param("i", $property_id);
            $fee_stmt->execute();
            $fee_result = $fee_stmt->get_result();
            $fee_stmt->close();

            if ($fee_result->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Property not found or not verified']);
                exit();
            }

            $property = $fee_result->fetch_assoc();
            $calculated_fee = calculate_service_fee($property['location'], $property['bedrooms']);
            if ($service_fee !== $calculated_fee) {
                $service_fee = $calculated_fee;
            }

            if (isset($_POST['accept_terms']) && !$accept_terms) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'You must accept the terms and service fee to schedule a viewing']);
                exit();
            }

            if (!$accept_terms) {
                $accept_terms = 1;
            }
        }

        // Hotel reservation type - different handling
        if ($consultation_type === 'hotel_reservation') {
            // Hotel reservations don't require property_id or service terms
            // Service fee is optional for hotel reservations
            $service_fee = isset($_POST['service_fee']) ? intval($_POST['service_fee']) : 0;
        }

        // Insert into database
        $query = "INSERT INTO consultations
                  (user_id, consultation_type, property_id, scheduled_date, contact_number, email, issue_description, accept_terms, service_fee, status)
                  VALUES
                  ({$_SESSION['user_id']}, '$consultation_type', " . ($property_id ? $property_id : "NULL") . ", '$scheduled_date $scheduled_time', '$contact_number', '$email', '$issue_description', $accept_terms, $service_fee, 'pending')";

        if ($conn->query($query)) {
            $consultation_id = $conn->insert_id;
            error_log("Viewing request inserted successfully with ID: $consultation_id");

            if ($consultation_type === 'property_viewing' && $property_id) {
                // Also create an admin-visible viewing request record
                $viewing_request_id = create_viewing_request(
                    $conn,
                    $property_id,
                    $_SESSION['user_id'],
                    $service_fee,
                    $scheduled_date,
                    $scheduled_time,
                    $contact_number,
                    $issue_description,
                    $accept_terms,
                    'pending'
                );

                if (!$viewing_request_id) {
                    error_log("Failed to create viewing_requests record for consultation property_viewing");
                }

                // Get property details for email and notifications
                $prop_stmt = $conn->prepare("SELECT location, bedrooms, property_type, property_code FROM properties WHERE id = ?");
                $prop_stmt->bind_param("i", $property_id);
                $prop_stmt->execute();
                $prop_result = $prop_stmt->get_result();
                $property = $prop_result->fetch_assoc();
                $prop_stmt->close();

                $property_details = [
                    'id' => $property_id,
                    'type' => $property['property_type'],
                    'location' => $property['location'],
                    'bedrooms' => $property['bedrooms'],
                    'tier_label' => get_service_fee_label($property['location'])
                ];

                notifyAdminNewViewingRequest(
                    $viewing_request_id,
                    $full_name,
                    $property['property_code'],
                    $property['location'],
                    $scheduled_date,
                    $scheduled_time,
                    $contact_number,
                    $issue_description
                );

                $consultation_details = [
                    'id' => $property_id,
                    'type' => $property['property_type'],
                    'location' => $property['location'],
                    'bedrooms' => $property['bedrooms'],
                    'tier_label' => get_service_fee_label($property['location'])
                ];

                $consultation_details = [
                    'date' => $scheduled_date,
                    'time' => $scheduled_time,
                    'phone' => $contact_number,
                    'service_fee' => $service_fee,
                    'message' => $issue_description
                ];

                $user_details = [
                    'name' => $full_name,
                    'email' => $email,
                    'phone' => $contact_number
                ];

                // Send confirmation email to user
                send_fee_confirmation_email($consultation_id, $email, $full_name, $property_details, $consultation_details);

                // Send notification to admin
                send_admin_fee_notification($consultation_id, $user_details, $property_details, $consultation_details);
            } elseif ($consultation_type === 'hotel_reservation') {
                // Send simple confirmation email for hotel reservation
                $user_details = [
                    'name' => $full_name,
                    'email' => $email,
                    'phone' => $contact_number
                ];

                $hotel_details = [
                    'date' => $scheduled_date,
                    'time' => $scheduled_time,
                    'service_fee' => $service_fee,
                    'message' => $issue_description
                ];

                // Send confirmation email to user
                $subject = "Hotel Reservation Request Confirmed";
                $message = "Dear $full_name,\n\n";
                $message .= "Your hotel reservation request has been submitted successfully.\n\n";
                $message .= "Request Details:\n";
                $message .= "Date: $scheduled_date\n";
                $message .= "Time: $scheduled_time\n";
                $message .= "Phone: $contact_number\n";
                if ($issue_description) {
                    $message .= "Message: $issue_description\n";
                }
                $message .= "\nOur team will contact you shortly to confirm your reservation.\n\n";
                $message .= "Thank you for choosing our service!\n\n";
                $message .= "Best regards,\n";
                $message .= "Walbrand Team";

                $headers = "From: noreply@walbrand.co.ke\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                mail($email, $subject, $message, $headers);

                // Also notify admin
                $admin_subject = "New Hotel Reservation Request";
                $admin_message = "New hotel reservation request received:\n\n";
                $admin_message .= "Client Name: $full_name\n";
                $admin_message .= "Email: $email\n";
                $admin_message .= "Phone: $contact_number\n";
                $admin_message .= "Date: $scheduled_date\n";
                $admin_message .= "Time: $scheduled_time\n";
                $admin_message .= "Message: $issue_description\n";
                $admin_message .= "\nPlease log in to the admin panel to review and approve this request.";

                $admin_headers = "From: noreply@walbrand.co.ke\r\n";
                $admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                
                // Send to all admins
                $admin_stmt = $conn->prepare("SELECT email FROM users WHERE role = 'admin'");
                $admin_stmt->execute();
                $admin_result = $admin_stmt->get_result();
                while ($admin = $admin_result->fetch_assoc()) {
                    mail($admin['email'], $admin_subject, $admin_message, $admin_headers);
                }
                $admin_stmt->close();
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Viewing request submitted successfully! You will receive a confirmation email with fee details. Admin will review and confirm your appointment.',
                'consultation_id' => $consultation_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error scheduling consultation: ' . $conn->error
            ]);
        }
        exit();
    }
    
    // Get consultation types
    if ($_POST['action'] === 'get_consultation_types') {
        $types = [
            'valuation' => 'Property Valuation',
            'financing' => 'Financing Assistance',
            'legal' => 'Legal Support',
            'management' => 'Property Management',
            'marketing' => 'Property Marketing',
            'transaction' => 'Transaction Management',
            'wifi_distribution' => 'WiFi Distribution',
            'cctv_installation' => 'CCTV Installation',
            'alexa_installation' => 'Alexa Installation',
            'general' => 'General Inquiry'
        ];
        
        echo json_encode(['success' => true, 'types' => $types]);
        exit();
    }
}

// Redirect if not POST request
header("Location: index.php");
exit();
?>
