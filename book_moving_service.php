<?php
/**
 * Book Moving Service Form
 * Public-facing booking form for moving service requests
 */

require_once 'config_mover_system.php';

// Get Google Maps API key
$googleMapsKey = GOOGLE_MAPS_API_KEY;

$serviceMode = $_GET['mode'] ?? 'moving';
if (!in_array($serviceMode, ['moving', 'book_delivery', 'house_swap'], true)) {
    $serviceMode = 'moving';
}

$serviceTitles = [
    'moving' => 'Book Moving Service',
    'book_delivery' => 'Book Delivery Service',
    'house_swap' => 'Request House Swap Service',
];

$serviceSubtitles = [
    'moving' => 'Fast, reliable, and affordable moving solutions in Kenya.',
    'book_delivery' => 'Book fast delivery services for trusted items and packages.',
    'house_swap' => 'Arrange a house swap for temporary relocations with confidence.',
];

$serviceTitle = $serviceTitles[$serviceMode];
$serviceSubtitle = $serviceSubtitles[$serviceMode];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($serviceTitle); ?> - Walbrand Movers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/moving_service.css">
    <script src="js/moving_service.js"></script>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($googleMapsKey); ?>&libraries=places,geometry&callback=initGoogleMaps" async defer onerror="handleMapsScriptError()"></script>
    <style>
        :root {
            --primary-color: #586a7c;
            --accent-color: #f76a0c;
            --success-color: #27ae60;
            --info-color: #3498db;
            --border-radius: 8px;
        }

        * {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #FFA500, #87CEEB);
            min-height: 100vh;
            padding: 40px 0;
        }

        .booking-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .booking-header {
            background: #FFA500;
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .booking-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: bold;
        }

        .booking-header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .booking-form {
            padding: 40px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group.required label::after {
            content: '*';
            color: var(--accent-color);
            margin-left: 4px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .pricing-section {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .pricing-grid {
                grid-template-columns: 1fr;
            }
        }

        .pricing-item {
            background: white;
            padding: 15px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--accent-color);
        }

        .pricing-item-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .pricing-item-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--success-color);
        }

        .mode-toggle {
            display: inline-block;
            background: white;
            padding: 10px 15px;
            border: 2px solid var(--accent-color);
            border-radius: var(--border-radius);
            color: var(--accent-color);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-size: 0.95rem;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .mode-toggle:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }

        .mode-toggle.manual-mode {
            background: var(--accent-color);
            color: white;
        }

        .mode-toggle.manual-mode:hover {
            background: darkorange;
        }

        .distance-info {
            background: #e8f4f8;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-top: 10px;
            border-left: 4px solid var(--info-color);
            font-size: 0.9rem;
            color: var(--primary-color);
        }

        .distance-info i {
            color: var(--info-color);
            margin-right: 5px;
        }

        .btn-submit {
            background:orange;
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-submit:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgb(245, 102, 6);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #f86008;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #ee7a0e;
        }

        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        .autocomplete-container {
            position: relative;
        }

        .commute-estimator-container {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border: 1px solid #e9ecef;
        }

        .commute-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--accent-color);
        }

        .commute-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .commute-widget-wrapper {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .commute-widget-wrapper iframe {
            width: 100%;
            border: none;
            display: block;
        }

        @media (max-width: 768px) {
            .commute-widget-wrapper iframe {
                height: 400px !important;
            }
        }

        .feature-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
        }

        .feature-list h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: bold;
        }

        .feature-list ul {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 8px 0;
            color: #555;
        }

        .feature-list li::before {
            content: '✓ ';
            color: var(--success-color);
            font-weight: bold;
            margin-right: 10px;
        }

        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
        }

        .loading-spinner.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <!-- Header -->
        <div class="booking-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div></div>
                <a href="index.php" class="btn btn-light btn-lg">
                    <i class="fas fa-home"></i> Back to Home Page
                </a>
            </div>
            <h1><?php echo htmlspecialchars($serviceTitle); ?></h1>
            <p><?php echo htmlspecialchars($serviceSubtitle); ?></p>
        </div>

        <!-- Form -->
        <div class="booking-form">
            <!-- Features -->
            <div class="feature-list">
                <h5>Why Choose Walbrand Movers?</h5>
                <ul>
                    <li>Real-time pricing based on distance</li>
                    <li>Professional and experienced movers</li>
                    <li>Insured and secure transportation</li>
                    <li>Transparent, no hidden charges</li>
                    <li>Fast assignment and tracking</li>
                </ul>
            </div>

            <!-- Alerts -->
            <div id="alerts-container"></div>

            <!-- Booking Form -->
            <form id="mover_booking_form" method="POST" data-google-maps-key="<?php echo htmlspecialchars($googleMapsKey); ?>">
                
                <!-- Client Information Section -->
                <div class="form-section">
                    <h3 class="form-section-title">Your Information</h3>

                    <div class="form-row">
                        <div class="form-group required">
                            <label for="client_name">Full Name</label>
                            <input type="text" id="client_name" name="client_name" placeholder="Enter your full name" required>
                            <div class="help-text">Your complete name as it appears on ID</div>
                        </div>

                        <div class="form-group required">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" placeholder="your@email.com" required>
                            <div class="help-text">We'll send confirmation to this address</div>
                        </div>
                    </div>

                    <div class="form-group required">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="+254712345678 or 0712345678" required>
                        <div class="help-text">Kenya phone number format (starts with +254 or 0)</div>
                    </div>

                    <div class="form-group">
                        <label for="additional_notes">Additional Notes (Optional)</label>
                        <textarea id="additional_notes" name="additional_notes" rows="3" placeholder="Any special instructions or requirements?"></textarea>
                    </div>
                </div>

                <!-- Location & Distance Section -->
                <div class="form-section">
                    <h3 class="form-section-title">Moving Locations</h3>

                    <div class="form-group required">
                        <label for="location_from">Location From</label>
                        <input type="text" id="location_from" name="location_from" placeholder="e.g., Westlands, Nairobi" required>
                        <div class="help-text">Type to search for locations (Google Maps powered)</div>
                        <div id="map" style="width:100%; height:400px; margin-top:20px; border-radius:10px;"></div>
                    </div>

                    <div class="form-group required">
                        <label for="location_to">Location To</label>
                        <input type="text" id="location_to" name="location_to" placeholder="e.g., Kilimani, Nairobi" required>
                        <div class="help-text">Your destination address</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="pickup_county">Pickup County</label>
                            <input type="text" id="pickup_county" name="pickup_county" placeholder="e.g., Nairobi County">
                        </div>
                        <div class="form-group">
                            <label for="destination_county">Destination County</label>
                            <input type="text" id="destination_county" name="destination_county" placeholder="e.g., Mombasa County">
                        </div>
                    </div>

                    <!-- Distance Section -->
                    <div class="form-group required">
                        <label for="distance_km">Distance (km)</label>
                        <div style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                            <input type="number" id="distance_km" name="distance_km" step="0.1" min="0" placeholder="Auto-calculated" required readonly style="flex: 1; min-width: 200px;">
                            <button type="button" class="mode-toggle" id="distance_mode_toggle" onclick="toggleDistanceMode()" style="margin-top: 0; white-space: nowrap;">
                                ✏️ Switch to Manual
                            </button>
                        </div>
                        <div class="distance-info">
                            ℹ Distance is auto-calculated using Google Maps. You can switch to manual input if needed.
                        </div>
                    </div>

                    <!-- Commute Time Estimator -->

                </div>

                <!-- House & Service Type Section -->
                <div class="form-section">
                    <h3 class="form-section-title">Property & Service Details</h3>

                    <div class="form-row">
                        <div class="form-group required">
<label for="house_type">House Size Category</label>
                        <select id="house_type" name="house_type" required>
                            <option value="">Select house size...</option>
                            <option value="1_bedroom">1 Bedroom or Below</option>
                            <option value="2_3_bedroom">2 to 3 Bedroom</option>
                            <option value="4_bedroom_plus">4 Bedroom and Above</option>
                        </select>
                        <div class="help-text">Choose the category that matches the property size</div>
                        </div>

                        <div class="form-group required">
                            <label for="service_type">Service Type</label>
                            <select id="service_type" name="service_type" required>
                                <option value="">Auto-detected...</option>
                                <option value="within_nairobi">Within Nairobi</option>
                                <option value="outside_nairobi">Outside Nairobi</option>
                            </select>
                            <div class="help-text">Auto-detected based on locations</div>
                        </div>
                    </div>
                </div>

                <!-- Recipient Section -->
                <div class="form-section">
                    <h3 class="form-section-title">Recipient Information</h3>

                    <div class="form-row">
                        <div class="form-group required">
                            <label for="recipient_name">Recipient Name</label>
                            <input type="text" id="recipient_name" name="recipient_name" placeholder="Full name of recipient" required>
                            <div class="help-text">This helps the delivery team confirm identity on arrival.</div>
                        </div>

                        <div class="form-group required">
                            <label for="recipient_phone">Recipient Phone</label>
                            <input type="tel" id="recipient_phone" name="recipient_phone" placeholder="+254712345678 or 0712345678" required>
                            <div class="help-text">Recipient contact for day-of delivery coordination.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group required">
                            <label for="recipient_email">Recipient Email</label>
                            <input type="email" id="recipient_email" name="recipient_email" placeholder="recipient@email.com" required>
                            <div class="help-text">Optional email for delivery notification.</div>
                        </div>

                        <div class="form-group required">
                            <label for="recipient_gender">Recipient Gender</label>
                            <select id="recipient_gender" name="recipient_gender" required>
                                <option value="">Select gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="recipient_photo_url">Recipient Photo URL</label>
                        <input type="url" id="recipient_photo_url" name="recipient_photo_url" placeholder="https://example.com/recipient-photo.jpg">
                        <div class="help-text">Use a URL for recipient identification if available.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="budget_min">Budget Minimum</label>
                            <input type="number" id="budget_min" name="budget_min" min="0" step="100" placeholder="0">
                        </div>
                        <div class="form-group">
                            <label for="budget_max">Budget Maximum</label>
                            <input type="number" id="budget_max" name="budget_max" min="0" step="100" placeholder="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="items_description">Items Description</label>
                        <textarea id="items_description" name="items_description" rows="4" placeholder="Describe the items to be transported"></textarea>
                        <div class="help-text">Tell us what will be moved so we can prepare the right team.</div>
                    </div>
                </div>

                <!-- Schedule Section -->
                <div class="form-section">
                    <h3 class="form-section-title">Delivery Schedule</h3>

                    <div class="form-row">
                        <div class="form-group required">
                            <label for="moving_date">Delivery Date</label>
                            <input type="date" id="moving_date" name="moving_date" required>
                            <div class="help-text">When should the delivery take place?</div>
                        </div>

                        <div class="form-group required">
                            <label for="moving_time">Delivery Time</label>
                            <input type="time" id="moving_time" name="moving_time" required>
                            <div class="help-text">Preferred start time for the team.</div>
                        </div>
                    </div>
                </div>

                <!-- Pricing Section -->
                <div class="pricing-section">
                    <h4 style="color: var(--primary-color); margin-bottom: 20px;">Estimated Cost</h4>
                    
                    <div class="pricing-grid">
                        <div class="pricing-item">
                            <div class="pricing-item-label">Estimated Total Cost</div>
                            <div class="pricing-item-value" id="estimated_cost">0 KES</div>
                        </div>
                        <div class="pricing-item">
                            <div class="pricing-item-label">Distance</div>
                            <div class="pricing-item-value"><span id="distance_display">0</span> km</div>
                        </div>
                    </div>

                    <div style="background: white; padding: 15px; border-radius: var(--border-radius); font-size: 0.9rem; color: #666;">
                        <strong>Pricing Details:</strong>
                        <ul style="margin: 10px 0 0; padding-left: 20px;">
                            <li><strong>Within Nairobi:</strong> 1BR (13,000 KES) | 2BR (23,000 KES) | 3BR+ (35,000 KES)</li>
                            <li><strong>Outside Nairobi:</strong> Distance × 600 KES/km (minimum = within Nairobi rate)</li>
                        </ul>
                    </div>
                </div>

                <!-- Hidden Fields -->
                <input type="hidden" id="total_cost" name="total_cost" value="0">

                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                        <input type="checkbox" id="terms_checkbox" name="terms_accepted" value="1" style="width: auto;" required>
                        I accept the terms and conditions for Walbrand Delivery Services.
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit">Complete Booking</button>

                <p style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9rem;">
                    By submitting, you agree to our terms and conditions.<br>
                    A confirmation will be sent to your email.
                </p>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update distance display
        document.getElementById('distance_km').addEventListener('input', function() {
            document.getElementById('distance_display').textContent = this.value || '0';
        });
    </script>
</body>
</html>
