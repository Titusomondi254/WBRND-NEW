<?php
/**
 * Cleaning Service Payment Page
 * M-Pesa payment integration
 */

session_start();
require_once 'config.php';

if (!isset($_GET['request_id'])) {
    header('Location: cleaning_request.php');
    exit;
}

$request_id = intval($_GET['request_id']);

// Get request details
$stmt = $conn->prepare("
    SELECT cr.*, u.first_name, u.last_name
    FROM cleaning_requests cr
    LEFT JOIN users u ON cr.user_id = u.id
    WHERE cr.id = ?
");
$stmt->bind_param("i", $request_id);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();

if (!$request) {
    header('Location: cleaning_request.php');
    exit;
}

$service_types = json_decode($request['service_types'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Walbrand Cleaning Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home"></i> Walbrand Properties Marketplace
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-money-bill-wave"></i> Complete Payment</h4>
                    </div>
                    <div class="card-body">
                        <!-- Request Summary -->
                        <div class="alert alert-info">
                            <h6>Service Request Summary</h6>
                            <p><strong>Client:</strong> <?php echo htmlspecialchars($request['full_name']); ?></p>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($request['location']); ?></p>
                            <p><strong>Services:</strong> <?php echo htmlspecialchars(implode(', ', $service_types)); ?></p>
                            <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($request['preferred_date'])); ?></p>
                        </div>

                        <!-- Payment Amount -->
                        <div class="text-center mb-4">
                            <h3 class="text-primary">KES <?php echo number_format($request['budget'], 2); ?></h3>
                            <p class="text-muted">Amount to pay</p>
                        </div>

                        <!-- M-Pesa Payment Form -->
                        <form id="paymentForm">
                            <input type="hidden" id="requestId" value="<?php echo $request_id; ?>">
                            <input type="hidden" id="amount" value="<?php echo $request['budget']; ?>">

                            <div class="mb-3">
                                <label class="form-label">M-Pesa Phone Number *</label>
                                <input type="tel" class="form-control" id="phone" required
                                       placeholder="254XXXXXXXXX" pattern="254[0-9]{9}"
                                       value="<?php echo htmlspecialchars($request['phone']); ?>">
                                <div class="form-text">Enter your M-Pesa registered phone number</div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg w-100" id="payBtn">
                                <i class="fas fa-mobile-alt"></i> Pay with M-Pesa
                            </button>
                        </form>

                        <!-- Payment Status -->
                        <div id="paymentStatus" class="mt-3" style="display: none;">
                            <div class="alert alert-warning">
                                <i class="fas fa-spinner fa-spin"></i> Processing payment...
                                <br><small>Check your phone for the M-Pesa prompt</small>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <div id="paymentSuccess" class="mt-3" style="display: none;">
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> Payment successful!
                                <br><small>Your request has been submitted and will be assigned to a cleaner soon.</small>
                            </div>
                            <a href="index.php" class="btn btn-primary">Return to Home</a>
                        </div>

                        <!-- Error Message -->
                        <div id="paymentError" class="mt-3" style="display: none;">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> Payment failed. Please try again.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security Notice -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6><i class="fas fa-shield-alt"></i> Secure Payment</h6>
                        <ul class="small text-muted mb-0">
                            <li>Payment is held in escrow until service completion</li>
                            <li>Money is only released to the cleaner after you confirm satisfaction</li>
                            <li>100% secure M-Pesa integration</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const phone = document.getElementById('phone').value;
            const amount = document.getElementById('amount').value;
            const requestId = document.getElementById('requestId').value;

            // Show processing status
            document.getElementById('paymentStatus').style.display = 'block';
            document.getElementById('payBtn').disabled = true;

            // Initiate M-Pesa STK Push
            fetch('payments/mpesa_stk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    phone: phone,
                    amount: amount,
                    request_id: requestId
                })
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('paymentStatus').style.display = 'none';

                if (data.success) {
                    document.getElementById('paymentSuccess').style.display = 'block';
                    // Poll for payment confirmation
                    pollPaymentStatus(data.checkout_request_id);
                } else {
                    document.getElementById('paymentError').style.display = 'block';
                    document.getElementById('paymentError').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> ${data.message}
                        </div>
                    `;
                    document.getElementById('payBtn').disabled = false;
                }
            })
            .catch(error => {
                document.getElementById('paymentStatus').style.display = 'none';
                document.getElementById('paymentError').style.display = 'block';
                document.getElementById('payBtn').disabled = false;
                console.error('Payment error:', error);
            });
        });

        function pollPaymentStatus(checkoutRequestId) {
            const pollInterval = setInterval(() => {
                fetch(`api/payment_status.php?checkout_id=${checkoutRequestId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'paid') {
                            clearInterval(pollInterval);
                            document.getElementById('paymentSuccess').style.display = 'block';
                            document.getElementById('paymentStatus').style.display = 'none';
                        } else if (data.status === 'failed') {
                            clearInterval(pollInterval);
                            document.getElementById('paymentError').style.display = 'block';
                            document.getElementById('paymentStatus').style.display = 'none';
                            document.getElementById('payBtn').disabled = false;
                        }
                    });
            }, 5000); // Poll every 5 seconds

            // Stop polling after 5 minutes
            setTimeout(() => {
                clearInterval(pollInterval);
                document.getElementById('paymentStatus').innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-clock"></i> Payment is taking longer than expected.
                        <br><small>Please check your M-Pesa messages and refresh the page.</small>
                    </div>
                `;
                document.getElementById('payBtn').disabled = false;
            }, 300000);
        }
    </script>
</body>
</html>