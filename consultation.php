<?php
/**
 * CONSULTATION BOOKING PAGE
 * Allows users to schedule consultations for various services
 */

session_start();
require_once 'config.php';
require_once 'helpers.php';

// REQUIRE LOGIN TO SCHEDULE CONSULTATIONS
require_login();

// Track user activity
track_user_activity();

$prefilledPropertyId = isset($_GET['property_id']) ? intval($_GET['property_id']) : '';
$prefilledConsultationType = isset($_GET['consultation_type']) ? trim($_GET['consultation_type']) : '';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Consultation - Walbrand Properties & Interiors</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .consultation-container {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .consultation-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 3px solid#eef4fb;
            padding-bottom: 1.5rem;
        }

        .consultation-header h1 {
            color:#eef4fb;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .consultation-services {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .service-card {
            background: linear-gradient(135deg, #ff7b00 0%, #5cfaff 100%);
            padding: 1.5rem;
            border-radius: 10px;
            color: white;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(255,123,0,0.3);
        }

        .service-card h3 {
            color: white;
            margin-bottom: 0.5rem;
        }

        .service-card p {
            color: rgba(255,255,255,0.9);
            font-size: 0.9rem;
        }

        .consultation-form {
            background: #f9f9f9;
            padding: 2rem;
            border-radius: 10px;
            border: 2px solid #5cfaff;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color:#eef4fb;
            box-shadow: 0 0 10px rgba(255,123,0,0.2);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .submit-btn {
            background: linear-gradient(135deg, #ff7b00 0%, #ff9c3d 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,123,0,0.3);
        }

        .success-message {
            background: #DCFCE7;
            color: #166534;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #13c764;
        }

        .error-message {
            background: #FEE2E2;
            color: #991B1B;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 1rem;
            border-left: 4px solid #da0808;
        }

        .info-section {
            background: rgba(92,250,255,0.1);
            border-left: 4px solid #5cfaff;
            padding: 1.5rem;
            border-radius: 5px;
            margin-bottom: 2rem;
        }

        .info-section h3 {
            color:#eef4fb;
            margin-bottom: 1rem;
        }

        .info-section p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .consultation-timing {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="consultation-container" style="margin-top: 2rem;">
        <div class="consultation-header">
            <h1>📞 Schedule a Consultation</h1>
            <p>Get expert advice on your property needs</p>
        </div>

        <div class="info-section">
            <h3>We're Here to Help!</h3>
            <p><strong>Hours:</strong> 24/7 Available</p>
            <p><strong>Location:</strong> Nairobi, Kenya</p>
            <p><strong>Phone:</strong> +254113906162</p>
            <p><strong>Email:</strong> support@walbrandproperties.com</p>
        </div>

        <div id="messageContainer"></div>

        <form id="consultationForm" action="consultation_handler.php" method="POST">
            <input type="hidden" name="action" value="schedule_consultation">

            <!-- Consultation Type Selection -->
            <div class="form-group">
                <label for="consultationType">*Select Consultation Type</label>
                <select name="consultation_type" id="consultationType" required>
                    <option value="">Choose a service...</option>
                    <option value="valuation" <?= $prefilledConsultationType === 'valuation' ? 'selected' : '' ?>>💰 Property Valuation</option>
                    <option value="financing" <?= $prefilledConsultationType === 'financing' ? 'selected' : '' ?>>🏦 Financing Assistance</option>
                    <option value="legal" <?= $prefilledConsultationType === 'legal' ? 'selected' : '' ?>>⚖️ Legal Support</option>
                    <option value="management" <?= $prefilledConsultationType === 'management' ? 'selected' : '' ?>>🏢 Property Management</option>
                    <option value="marketing" <?= $prefilledConsultationType === 'marketing' ? 'selected' : '' ?>>📢 Marketing Services</option>
                    <option value="transaction" <?= $prefilledConsultationType === 'transaction' ? 'selected' : '' ?>>📋 Transaction Management</option>
                    <option value="property_viewing" <?= $prefilledConsultationType === 'property_viewing' ? 'selected' : '' ?>>👀 Property Viewing</option>
                    <option value="general" <?= $prefilledConsultationType === 'general' ? 'selected' : '' ?>>❓ General Inquiry</option>
                </select>
            </div>

            <!-- Property Selection (Optional) -->
            <div class="form-group">
                <label for="propertyId">Property (if applicable)</label>
                <input type="number" name="property_id" id="propertyId" placeholder="Enter property ID (optional)" value="<?= htmlspecialchars($prefilledPropertyId) ?>">
            </div>

            <!-- Consultation Timing -->
            <div class="consultation-timing">
                <div class="form-group">
                    <label for="consultationDate">*Preferred Date</label>
                    <input type="date" name="scheduled_date" id="consultationDate" required>
                </div>
                <div class="form-group">
                    <label for="consultationTime">*Preferred Time</label>
                    <input type="time" name="scheduled_time" id="consultationTime" required>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="form-row">
                <div class="form-group">
                    <label for="email">*Email Address</label>
                    <input type="email" name="email" id="email" required <?= isset($_SESSION['user_email']) ? 'value="' . htmlspecialchars($_SESSION['user_email']) . '"' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="phone">*Phone Number</label>
                    <input type="tel" name="contact_number" id="phone" placeholder="+254..." required <?= isset($_SESSION['user_phone']) ? 'value="' . htmlspecialchars($_SESSION['user_phone']) . '"' : '' ?>>
                </div>
            </div>

            <!-- Issue Description -->
            <div class="form-group">
                <label for="description">*Tell us more about your needs</label>
                <textarea name="issue_description" id="description" rows="6" placeholder="Please provide detailed information about what you need help with..." required></textarea>
            </div>

            <button type="submit" class="submit-btn">📅 Schedule Consultation</button>
        </form>

        <div style="margin-top: 2rem; padding: 1.5rem; background: #f0f0f0; border-radius: 10px;">
            <h3 style="color:#eef4fb; margin-bottom: 1rem;">What Happens Next?</h3>
            <ol style="color: #666;">
                <li>We receive your consultation request</li>
                <li>Our team reviews and confirms availability</li>
                <li>We contact you to finalize the consultation</li>
                <li>Expert consultation via phone, email, or in-person</li>
                <li>Follow-up support and next steps discussion</li>
            </ol>
        </div>
    </div>

    <script>
        document.getElementById('consultationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageContainer = document.getElementById('messageContainer');

            // Combine date and time
            const date = formData.get('scheduled_date');
            const time = formData.get('scheduled_time');
            const dateTime = date + 'T' + time;
            formData.set('scheduled_date', dateTime);

            fetch('consultation_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageContainer.innerHTML = `
                        <div class="success-message">
                            ✓ ${data.message}<br>
                            Consultation ID: #${data.consultation_id}
                        </div>
                    `;
                    document.getElementById('consultationForm').reset();
                } else {
                    messageContainer.innerHTML = `
                        <div class="error-message">
                            ✗ ${data.message}
                        </div>
                    `;
                }
            })
            .catch(error => {
                messageContainer.innerHTML = `
                    <div class="error-message">
                        Error: ${error.message}
                    </div>
                `;
            });
        });

        // Set minimum date to today
        document.getElementById('consultationDate').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
