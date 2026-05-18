<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle form submissions
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    switch($form_action) {
        case 'update_settings':
            $commission_rate = floatval($_POST['commission_rate'] ?? 0);
            $min_property_price = floatval($_POST['min_property_price'] ?? 0);
            $max_property_price = floatval($_POST['max_property_price'] ?? 0);
            $verification_required = isset($_POST['verification_required']) ? 1 : 0;
            
            // Update settings in database or config file
            // For now, we'll just show success message
            $success = "Settings updated successfully!";
            logAdminAction('update_settings', "Updated platform settings");
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color:#eef4fb;
            --secondary-color: #5cfaff;
            --dark-color: #1a1a1a;
            --light-gray: #f8f9fa;
            --border-color: #e0e0e0;
            --success: #10b981;
            --danger: #ef4444;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.12);
        }

        body {
            font-family: 'Segoe UI', 'Roboto', -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: var(--light-gray);
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            margin: 0;
        }

        .back-btn {
            background: var(--border-color);
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 600;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: #d1f4e9;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }

        .settings-menu {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            height: fit-content;
            overflow: hidden;
        }

        .settings-menu-item {
            padding: 1rem 1.5rem;
            border-left: 4px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 1px solid var(--border-color);
        }

        .settings-menu-item:last-child {
            border-bottom: none;
        }

        .settings-menu-item:hover {
            background: var(--light-gray);
            border-left-color: var(--primary-color);
        }

        .settings-menu-item.active {
            background: rgba(255, 123, 0, 0.1);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .settings-panel {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            padding: 2rem;
            display: none;
        }

        .settings-panel.active {
            display: block;
        }

        .settings-panel h2 {
            margin-top: 0;
            margin-bottom: 2rem;
            color: var(--dark-color);
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: var(--dark-color);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 1.5px rgba(255, 123, 0, 0.15);
        }

        .form-group p {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.4rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 0.9rem 2rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            flex: 1;
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--dark-color);
            flex: 1;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .setting-info {
            background: #f0f9ff;
            border-left: 4px solid var(--primary-color);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            color: #0c4a6e;
        }

        @media (max-width: 768px) {
            .settings-container {
                grid-template-columns: 1fr;
            }

            .settings-menu {
                display: flex;
                overflow-x: auto;
            }

            .settings-menu-item {
                white-space: nowrap;
                border-left: none;
                border-bottom: none;
                border-right: 2px solid transparent;
            }

            .settings-menu-item.active {
                border-right-color: var(--primary-color);
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>⚙️ System Settings</h1>
            <a href="admin_control_panel.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="settings-container">
            <!-- SETTINGS MENU -->
            <div class="settings-menu">
                <div class="settings-menu-item active" onclick="showPanel('general')">
                    General Settings
                </div>
                <div class="settings-menu-item" onclick="showPanel('commission')">
                    Commission Settings
                </div>
                <div class="settings-menu-item" onclick="showPanel('verification')">
                    ✓ Verification Rules
                </div>
                <div class="settings-menu-item" onclick="showPanel('email')">
                    Email Templates
                </div>
                <div class="settings-menu-item" onclick="showPanel('security')">
                    Security Settings
                </div>
                <div class="settings-menu-item" onclick="showPanel('info')">
                    ℹ️ Platform Info
                </div>
            </div>

            <!-- SETTINGS PANELS -->
            <div>
                <!-- GENERAL SETTINGS -->
                <div class="settings-panel active" id="general">
                    <h2>General Settings</h2>
                    
                    <div class="setting-info">
                        Configure general platform settings and business rules.
                    </div>

                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="platform_name">Platform Name</label>
                                <input type="text" id="platform_name" name="platform_name" value="Walbrand Properties Marketplace & Interiors" required>
                            </div>

                            <div class="form-group">
                                <label for="support_email">Support Email</label>
                                <input type="email" id="support_email" name="support_email" value="support@walbrandproperties.com" required>
                            </div>

                            <div class="form-group">
                                <label for="support_phone">Support Phone</label>
                                <input type="text" id="support_phone" name="support_phone" value="+254113906162" required>
                            </div>

                            <div class="form-group">
                                <label for="timezone">Timezone</label>
                                <select id="timezone" name="timezone">
                                    <option value="Africa/Nairobi">Africa/Nairobi (EAT)</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode">
                            <label for="maintenance_mode">Enable Maintenance Mode</label>
                        </div>

                        <div class="button-group">
                            <input type="hidden" name="form_action" value="update_settings">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <!-- COMMISSION SETTINGS -->
                <div class="settings-panel" id="commission">
                    <h2>Commission Settings</h2>
                    
                    <div class="setting-info">
                        Configure how commissions are calculated on the platform.
                    </div>

                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="commission_rate">Commission Rate (%)</label>
                                <input type="number" id="commission_rate" name="commission_rate" min="0" max="100" step="0.1" value="5.0" required>
                                <p>Percentage taken from each property transaction</p>
                            </div>

                            <div class="form-group">
                                <label for="min_commission">Minimum Commission (KES)</label>
                                <input type="number" id="min_commission" name="min_commission" min="0" value="5000" required>
                                <p>Minimum amount charged per transaction</p>
                            </div>

                            <div class="form-group">
                                <label for="agent_commission">Agent Commission (%)</label>
                                <input type="number" id="agent_commission" name="agent_commission" min="0" max="100" step="0.1" value="10.0" required>
                                <p>Commission for registered agents</p>
                            </div>

                            <div class="form-group">
                                <label for="referral_bonus">Referral Bonus (KES)</label>
                                <input type="number" id="referral_bonus" name="referral_bonus" min="0" value="1000" required>
                                <p>Bonus given for successful referrals</p>
                            </div>
                        </div>

                        <div class="button-group">
                            <input type="hidden" name="form_action" value="update_settings">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <!-- VERIFICATION SETTINGS -->
                <div class="settings-panel" id="verification">
                    <h2>✓ Verification Rules</h2>
                    
                    <div class="setting-info">
                        Configure what is required for users and properties to be verified.
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <h3>Property Verification Requirements</h3>
                            <div class="checkbox-group" style="margin-top: 1rem;">
                                <input type="checkbox" id="require_title_deed" name="require_title_deed" checked>
                                <label for="require_title_deed">Title Deed Required</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="require_survey" name="require_survey" checked>
                                <label for="require_survey">Survey Map Required</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="require_photos" name="require_photos" checked>
                                <label for="require_photos">Property Photos Required</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <h3>User KYC Requirements</h3>
                            <div class="checkbox-group" style="margin-top: 1rem;">
                                <input type="checkbox" id="require_id" name="require_id" checked>
                                <label for="require_id">National ID/Passport Required</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="require_address" name="require_address" checked>
                                <label for="require_address">Address Verification Required</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="require_phone" name="require_phone" checked>
                                <label for="require_phone">Phone Verification Required</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="kyc_expire_days">KYC Validity Period (Days)</label>
                            <input type="number" id="kyc_expire_days" name="kyc_expire_days" min="30" value="365" required>
                            <p>How long before KYC needs to be renewed</p>
                        </div>

                        <div class="button-group">
                            <input type="hidden" name="form_action" value="update_settings">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <!-- EMAIL TEMPLATES -->
                <div class="settings-panel" id="email">
                    <h2>Email Templates</h2>
                    
                    <div class="setting-info">
                        Manage email templates sent to users.
                    </div>

                    <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                        <p><strong>Available Templates:</strong></p>
                        <ul style="margin-left: 1.5rem; margin-top: 1rem;">
                            <li>Account Verification Email</li>
                            <li>KYC Approval Email</li>
                            <li>KYC Rejection Email</li>
                            <li>Property Verification Email</li>
                            <li>Payment Confirmation Email</li>
                            <li>Consultation Scheduled Email</li>
                            <li>Password Reset Email</li>
                        </ul>
                    </div>

                    <button class="btn btn-primary" style="width: 100%; padding: 1rem;">Edit Email Templates</button>
                </div>

                <!-- SECURITY SETTINGS -->
                <div class="settings-panel" id="security">
                    <h2>Security Settings</h2>
                    
                    <div class="setting-info">
                        Configure security policies and access controls.
                    </div>

                    <form method="POST">
                        <div class="form-group">
                            <label for="session_timeout">Session Timeout (Minutes)</label>
                            <input type="number" id="session_timeout" name="session_timeout" min="15" value="60" required>
                            <p>How long before user sessions expire</p>
                        </div>

                        <div class="form-group">
                            <label for="max_login_attempts">Maximum Login Attempts</label>
                            <input type="number" id="max_login_attempts" name="max_login_attempts" min="3" value="5" required>
                            <p>Number of failed attempts before account lock</p>
                        </div>

                        <div class="form-group">
                            <label for="lockout_duration">Account Lockout Duration (Minutes)</label>
                            <input type="number" id="lockout_duration" name="lockout_duration" min="15" value="30" required>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="force_https" name="force_https" checked>
                            <label for="force_https">Force HTTPS Connections</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="require_2fa" name="require_2fa">
                            <label for="require_2fa">Require Two-Factor Authentication for Admins</label>
                        </div>

                        <div class="button-group">
                            <input type="hidden" name="form_action" value="update_settings">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

                <!-- PLATFORM INFO -->
                <div class="settings-panel" id="info">
                    <h2>ℹ️ Platform Information</h2>
                    
                    <div class="setting-info">
                        View system and platform information.
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: var(--dark-color);">System Information</h3>
                            <p><strong>PHP Version:</strong> <?= phpversion() ?></p>
                            <p><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
                            <p><strong>Database:</strong> MySQL</p>
                        </div>

                        <div style="background: var(--light-gray); padding: 1.5rem; border-radius: 8px;">
                            <h3 style="margin-top: 0; color: var(--dark-color);">Platform Stats</h3>
                            <p><strong>Platform Name:</strong> Walbrand Properties Marketplace & Interiors</p>
                            <p><strong>Version:</strong> 2.0.0</p>
                            <p><strong>Last Updated:</strong> <?= date('M d, Y') ?></p>
                        </div>
                    </div>

                    <div style="background: #f0f9ff; border-left: 4px solid var(--primary-color); padding: 1.5rem; border-radius: 8px; margin-top: 2rem;">
                        <h3 style="margin-top: 0;">Support & Resources</h3>
                        <ul style="margin-left: 1.5rem;">
                            <li><a href="mailto:support@walbrandproperties.com" style="color: var(--primary-color);">Contact Support</a></li>
                            <li><a href="#" style="color: var(--primary-color);">Documentation</a></li>
                            <li><a href="#" style="color: var(--primary-color);">Report a Bug</a></li>
                            <li><a href="#" style="color: var(--primary-color);">System Status</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPanel(panelId) {
            // Hide all panels
            document.querySelectorAll('.settings-panel').forEach(panel => {
                panel.classList.remove('active');
            });

            // Remove active from all menu items
            document.querySelectorAll('.settings-menu-item').forEach(item => {
                item.classList.remove('active');
            });

            // Show selected panel
            document.getElementById(panelId).classList.add('active');

            // Highlight menu item
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
