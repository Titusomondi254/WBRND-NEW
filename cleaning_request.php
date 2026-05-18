<?php
/**
 * Cleaning Service Request Form
 * Client interface for requesting cleaning services
 */

session_start();
require_once 'config.php';
require_once 'helpers.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $location = sanitize($_POST['location']);
    $location_area = sanitize($_POST['location_area']);
    $sqm = intval($_POST['sqm'] ?? 0);
    $bedrooms = intval($_POST['bedrooms'] ?? 0);
    $bathrooms = intval($_POST['bathrooms'] ?? 0);
    $service_types = isset($_POST['service_types']) ? $_POST['service_types'] : [];
    $preferred_date = sanitize($_POST['preferred_date']);
    $preferred_time = sanitize($_POST['preferred_time']);
    $budget = floatval($_POST['budget']);
    $notes = sanitize($_POST['notes']);

    // Validation
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($email) || !validate_email($email)) $errors[] = "Valid email is required";
    if (empty($phone)) $errors[] = "Phone number is required";
    if (empty($location)) $errors[] = "Location is required";
    if (empty($service_types)) $errors[] = "At least one service type is required";
    if ($budget < 5000) $errors[] = "Minimum budget is KES 5,000";
    if (empty($preferred_date)) $errors[] = "Preferred date is required";

    // Check if user exists or create guest user
    $user_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else {
        // Create guest user
        $guest_email = 'guest_' . time() . '@temp.com';
        $stmt = $conn->prepare("INSERT INTO users (name, email, phone, user_type) VALUES (?, ?, ?, 'buyer')");
        $stmt->bind_param("sss", $full_name, $guest_email, $phone);
        $stmt->execute();
        $user_id = $conn->insert_id;
    }

    if (empty($errors)) {
        // Insert cleaning request
        $stmt = $conn->prepare("
            INSERT INTO cleaning_requests (
                user_id, full_name, phone, email, location, location_area,
                sqm, bedrooms, bathrooms, service_types, preferred_date,
                preferred_time, budget, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $service_types_json = json_encode($service_types);

        $stmt->bind_param(
            "isssssiiisssds",
            $user_id, $full_name, $phone, $email, $location, $location_area,
            $sqm, $bedrooms, $bathrooms, $service_types_json, $preferred_date,
            $preferred_time, $budget, $notes
        );

        if ($stmt->execute()) {
            $request_id = $conn->insert_id;
            $success = true;

            // Send notification to admins about new cleaning request
            require_once 'notification_utils.php';
            notifyAdminNewCleaningRequest($request_id, $full_name, $location, $service_types, $budget);

            // Log the action
            if (isset($_SESSION['user_id'])) {
                logUserAction($_SESSION['user_id'], 'submit_cleaning_request', 'cleaning_requests', $request_id);
            }

            // Redirect to payment
            header("Location: cleaning_payment.php?request_id=" . $request_id);
            exit;
        } else {
            $errors[] = "Failed to submit request. Please try again.";
        }
    }
}

// Get service categories
$categories = $conn->query("SELECT * FROM cleaning_categories WHERE is_active = TRUE");

// Get location areas
$location_areas = $conn->query("SELECT * FROM location_areas ORDER BY category, area_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Cleaning Services - Walbrand</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .service-card { cursor: pointer; transition: all 0.3s; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .service-card.selected { border-color: #007bff; background-color: #f8f9ff; }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-home"></i> Walbrand Properties Marketplace
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="fas fa-arrow-left"></i> Back to Homepage
                </a>
                <a class="nav-link" href="cleaning_services.php">Cleaning Services</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                <a class="nav-link" href="logout.php">Logout</a>
                <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-broom"></i> Request Cleaning Services</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <form method="POST" id="cleaningForm">
                            <!-- Personal Information -->
                            <h5 class="mb-3">Personal Information</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" name="full_name" required
                                           value="<?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" name="phone" required
                                           value="<?php echo isset($_SESSION['user_phone']) ? htmlspecialchars($_SESSION['user_phone']) : ''; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Address *</label>
                                <input type="email" class="form-control" name="email" required
                                       value="<?php echo isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : ''; ?>">
                            </div>

                            <!-- Location -->
                            <h5 class="mb-3">Location</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Area *</label>
                                    <select class="form-select" name="location_area" required>
                                        <option value="">Select Area</option>
                                        <?php
                                        $current_category = '';
                                        while ($area = $location_areas->fetch_assoc()):
                                            if ($current_category !== $area['category']):
                                                if ($current_category !== '') echo '</optgroup>';
                                                echo '<optgroup label="' . ucfirst(str_replace('_', ' ', $area['category'])) . '">';
                                                $current_category = $area['category'];
                                            endif;
                                        ?>
                                        <option value="<?php echo $area['area_name']; ?>"><?php echo $area['area_name']; ?></option>
                                        <?php endwhile; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Specific Location *</label>
                                    <input type="text" class="form-control" name="location" required placeholder="e.g., Kilimani, Nairobi">
                                </div>
                            </div>

                            <!-- Property Details -->
                            <h5 class="mb-3">Property Details</h5>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Square Meters (for offices)</label>
                                    <input type="number" class="form-control" name="sqm" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bedrooms (for homes)</label>
                                    <input type="number" class="form-control" name="bedrooms" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bathrooms</label>
                                    <input type="number" class="form-control" name="bathrooms" min="0">
                                </div>
                            </div>

                            <!-- Service Types -->
                            <h5 class="mb-3">Service Types *</h5>
                            <div class="row" id="serviceTypes">
                                <?php while ($category = $categories->fetch_assoc()): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card service-card" onclick="toggleService('<?php echo $category['name']; ?>')">
                                        <div class="card-body text-center position-relative">
                                            <div style="font-size: 2em; margin-bottom: 10px;"><?php echo $category['icon']; ?></div>
                                            <h6><?php echo $category['name']; ?></h6>
                                            <p class="small text-muted"><?php echo $category['description']; ?></p>
                                            <div class="checkmark" style="position: absolute; top: 10px; right: 10px; display: none;">
                                                <i class="fas fa-check-circle text-success" style="font-size: 1.5em;"></i>
                                            </div>
                                            <input type="checkbox" name="service_types[]" value="<?php echo $category['name']; ?>" style="display: none;">
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            </div>

                            <!-- Schedule -->
                            <h5 class="mb-3">Schedule</h5>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Preferred Date *</label>
                                    <input type="date" class="form-control" name="preferred_date" required
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Preferred Time</label>
                                    <select class="form-select" name="preferred_time">
                                        <option value="">Anytime</option>
                                        <option value="morning">Morning (8AM - 12PM)</option>
                                        <option value="afternoon">Afternoon (12PM - 5PM)</option>
                                        <option value="evening">Evening (5PM - 8PM)</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Price Calculator -->
                            <h5 class="mb-3"><i class="fas fa-calculator"></i> Price Calculator</h5>
                            <div class="alert alert-info" id="pricingInfo" style="display: none;"></div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Distance from Nairobi CBD (km) - Optional</label>
                                    <input type="number" class="form-control" id="distanceKm" min="0" step="1" placeholder="Leave empty for auto-detection">
                                    <div class="form-text">For locations outside Nairobi. Leave empty to auto-calculate based on location.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estimated Price (KES)</label>
                                    <div id="estimatedPrice" class="alert alert-success mb-0" style="padding: 10px;">
                                        <h5 id="priceDisplay">KES 5,000</h5>
                                        <small id="pricingModel" class="text-muted">Based on Nairobi area category</small>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary mb-3 w-100" id="calculatePriceBtn">
                                <i class="fas fa-calculator"></i> Calculate Price
                            </button>

                            <!-- Budget -->
                            <h5 class="mb-3">Budget</h5>
                            <div class="mb-3">
                                <label class="form-label">Budget (KES) * - Minimum 5,000</label>
                                <input type="number" class="form-control" id="budgetInput" name="budget" required min="5000" step="100">
                                <div class="form-text">Payment will be held in escrow until service completion. Use calculated price above.</div>
                            </div>

                            <!-- Notes -->
                            <div class="mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions or requirements..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-paper-plane"></i> Submit Request & Proceed to Payment
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentEstimatedPrice = 5000;

        function toggleService(serviceName) {
            const cards = document.querySelectorAll('.service-card');
            cards.forEach(card => {
                if (card.querySelector('h6').textContent === serviceName) {
                    card.classList.toggle('selected');
                    const checkbox = card.querySelector('input[type="checkbox"]');
                    const checkmark = card.querySelector('.checkmark');
                    checkbox.checked = !checkbox.checked;

                    // Show/hide checkmark based on selection
                    if (checkbox.checked) {
                        checkmark.style.display = 'block';
                    } else {
                        checkmark.style.display = 'none';
                    }
                }
            });
        }

        // Auto-select service cards based on checkboxes and show checkmarks
        document.querySelectorAll('.service-card input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('.service-card').classList.toggle('selected', this.checked);
                const checkmark = this.closest('.service-card').querySelector('.checkmark');
                if (this.checked) {
                    checkmark.style.display = 'block';
                } else {
                    checkmark.style.display = 'none';
                }
            });
        });

        // Price Calculator Functions
        function calculatePrice() {
            const locationArea = document.querySelector('select[name="location_area"]').value;
            const location = document.querySelector('input[name="location"]').value;
            const distanceKm = document.getElementById('distanceKm').value;
            const sqm = document.querySelector('input[name="sqm"]').value || 0;
            const bedrooms = document.querySelector('input[name="bedrooms"]').value || 0;

            if (!locationArea || !location) {
                showPricingAlert('Please select location and area first', 'warning');
                return;
            }

            // Call the price calculation API
            fetch('api/calculate_price.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: null
            });

            const params = new URLSearchParams({
                location_area: locationArea,
                location: location,
                distance_km: distanceKm,
                sqm: sqm,
                bedrooms: bedrooms
            });

            fetch('api/calculate_price.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentEstimatedPrice = data.estimated_price;
                        updatePriceDisplay(data);
                    } else {
                        showPricingAlert('Error calculating price: ' + (data.error || 'Unknown error'), 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showPricingAlert('Failed to calculate price. Please try again.', 'danger');
                });
        }

        function updatePriceDisplay(data) {
            const priceDisplay = document.getElementById('priceDisplay');
            const pricingModel = document.getElementById('pricingModel');
            const budgetInput = document.getElementById('budgetInput');

            // Format price with commas
            const formattedPrice = data.estimated_price.toLocaleString('en-KE', { 
                style: 'currency', 
                currency: 'KES',
                minimumFractionDigits: 0 
            });

            priceDisplay.textContent = formattedPrice;
            
            // Update pricing model info
            let modelText = data.is_nairobi 
                ? `Nairobi (Category-Based) - ${data.location_area}` 
                : `Outside Nairobi (Distance-Based: ${data.distance_km} km × KSh 600/km)`;
            pricingModel.textContent = modelText;

            // Update budget input
            budgetInput.value = data.estimated_price;

            // Show success alert
            showPricingAlert(`Price calculated: ${formattedPrice} (${modelText})`, 'success');
        }

        function showPricingAlert(message, type) {
            const pricingInfo = document.getElementById('pricingInfo');
            pricingInfo.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
            pricingInfo.style.display = 'block';
            
            // Auto-hide after 5 seconds if success
            if (type === 'success') {
                setTimeout(() => {
                    pricingInfo.style.display = 'none';
                }, 5000);
            }
        }

        // Event Listeners
        document.getElementById('calculatePriceBtn').addEventListener('click', calculatePrice);

        // Auto-calculate when location changes
        document.querySelector('select[name="location_area"]').addEventListener('change', calculatePrice);
        document.querySelector('input[name="location"]').addEventListener('blur', calculatePrice);
        document.querySelector('input[name="sqm"]').addEventListener('change', calculatePrice);
        document.querySelector('input[name="bedrooms"]').addEventListener('change', calculatePrice);

        // Auto-calculate on page load
        document.addEventListener('DOMContentLoaded', () => {
            calculatePrice();
        });
    </script>
</body>
</html>