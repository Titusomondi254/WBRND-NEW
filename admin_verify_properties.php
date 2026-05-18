<?php
/**
 * Admin - Property Verification Interface
 * Modern interface for admins to verify, reject, and batch process property listings
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */

session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Get stats
$stats_query = "
    SELECT 
        COUNT(*) as total_properties,
        SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified_count,
        SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM properties
";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get pending properties
$query = "
    SELECT p.*, 
           CONCAT_WS(' ', u.first_name, u.last_name) AS seller_name,
           CASE WHEN u.kyc_status = 'verified' THEN TRUE ELSE FALSE END as kyc_verified,
           (SELECT COUNT(*) FROM property_images WHERE property_id = p.id) as image_count
    FROM properties p
    JOIN users u ON p.seller_id = u.id
    WHERE p.verification_status = 'pending'
    ORDER BY p.created_at ASC
    LIMIT 50
";

$result = $conn->query($query);
$pending_properties = [];
while ($row = $result->fetch_assoc()) {
    $pending_properties[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Verification - Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .verification-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .verification-header {
            background: linear-gradient(135deg, #F97316, #45f4fa);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .verification-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header-nav {
            display: flex;
            gap: 15px;
            margin-top: 1rem;
        }

        .header-nav a, .header-nav button {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border: 2px solid white;
            border-radius: 25px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
        }

        .header-nav a:hover, .header-nav button:hover {
            background: white;
            color: #F97316;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-top: 4px solid #F97316;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: bold;
            color: #F97316;
        }

        .stat-card.pending {
            border-top-color: #FFA500;
        }

        .stat-card.verified {
            border-top-color: #13c764;
        }

        .stat-card.rejected {
            border-top-color: #da0808;
        }

        .controls-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .controls-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            border-bottom: 2px solid #F97316;
            padding-bottom: 0.5rem;
        }

        .control-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #F97316, #45f4fa);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(249, 115, 22, 0.3);
        }

        .btn-success {
            background: #13c764;
            color: white;
        }

        .btn-success:hover {
            background: #0aa54a;
        }

        .btn-danger {
            background: #da0808;
            color: white;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .properties-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .properties-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
            border-bottom: 2px solid #F97316;
            padding-bottom: 0.5rem;
        }

        .property-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }

        .property-card:hover {
            border-color: #F97316;
            box-shadow: 0 5px 15px rgba(249, 115, 22, 0.1);
        }

        .property-card input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 15px;
            cursor: pointer;
        }

        .property-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .property-title {
            flex: 1;
        }

        .property-title h3 {
            color: #333;
            font-size: 1.2rem;
            margin-bottom: 0.3rem;
        }

        .property-title p {
            color: #666;
            font-size: 0.9rem;
        }

        .property-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
        }

        .status-pending {
            background: #FFF3CD;
            color: #856404;
        }

        .property-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
            padding-left: 35px;
        }

        .detail-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #F97316;
        }

        .detail-label {
            color: #666;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            color: #333;
            font-size: 1rem;
        }

        .seller-info {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            margin-left: 35px;
        }

        .seller-info p {
            margin: 0.3rem 0;
            font-size: 0.9rem;
        }

        .kyc-verified {
            display: inline-block;
            background: #13c764;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .kyc-not-verified {
            display: inline-block;
            background: #da0808;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }

        .property-actions {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
            margin-left: 35px;
            flex-wrap: wrap;
        }

        .property-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }

        .action-verify {
            background: #13c764;
            color: white;
        }

        .action-verify:hover {
            background: #0aa54a;
        }

        .action-reject {
            background: #da0808;
            color: white;
        }

        .action-reject:hover {
            background: #b91c1c;
        }

        .action-view {
            background: #2196F3;
            color: white;
        }

        .action-view:hover {
            background: #0b7dda;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #666;
        }

        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }

        .empty-state .checkmark {
            font-size: 4rem;
            color: #13c764;
            margin-bottom: 1rem;
        }

        .rejection-form {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1rem;
            margin-left: 35px;
        }

        .rejection-form h4 {
            color: #856404;
            margin-bottom: 1rem;
        }

        .rejection-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.9rem;
            min-height: 80px;
            margin-bottom: 1rem;
        }

        .rejection-form .form-buttons {
            display: flex;
            gap: 10px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            z-index: 1001;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-content h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .modal-content textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            min-height: 100px;
            margin-bottom: 1rem;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #F97316;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #F97316;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message, .error-message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            animation: slideDown 0.3s ease-out;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header style="background: linear-gradient(135deg, #F97316, #45f4fa); color: white; padding: 1rem 0; margin-bottom: 2rem;">
        <div style="max-width: 1400px; margin: 0 auto; padding: 0 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0;">Walbrand Properties Marketplace & Interiors - Admin</h2>
            </div>
            <nav style="display: flex; gap: 20px;">
                <a href="admin_dashboard.php" style="color: black; text-decoration: none;">Dashboard</a>
                <a href="admin_verify_properties.php" style="color: white; text-decoration: none; font-weight: bold; border-bottom: 3px solid white; padding-bottom: 5px;">Verify Properties</a>
                <a href="admin_logout.php" style="color: white; text-decoration: none;">Logout</a>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <div class="verification-container">
        <!-- Header Section -->
        <div class="verification-header">
            <h1>Property Verification Center</h1>
            <p>Review, verify, and manage property listings</p>
            <div class="header-nav">
                <button onclick="loadPendingProperties()" class="btn btn-primary">Refresh Properties</button>
                <button onclick="showAutoVerifyConfirm()" class="btn btn-success">⚡ Auto-Verify Eligible</button>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <h3>Pending Verification</h3>
                <div class="stat-number" id="pending-count"><?= $stats['pending_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card verified">
                <h3>Verified Properties</h3>
                <div class="stat-number" id="verified-count"><?= $stats['verified_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected Properties</h3>
                <div class="stat-number" id="rejected-count"><?= $stats['rejected_count'] ?? 0 ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Properties</h3>
                <div class="stat-number"><?= $stats['total_properties'] ?? 0 ?></div>
            </div>
        </div>

        <!-- Controls Section -->
        <div class="controls-section">
            <h2>Batch Operations</h2>
            <div class="control-buttons">
                <button onclick="batchVerifySelected()" class="btn btn-success" id="batch-verify-btn" disabled>
                    ✓ Verify Selected
                </button>
                <button onclick="showBatchRejectForm()" class="btn btn-danger" id="batch-reject-btn" disabled>
                    ✗ Reject Selected
                </button>
                <button onclick="clearSelections()" class="btn btn-secondary">
                    Clear Selections
                </button>
            </div>
            <div id="selection-counter" style="margin-top: 1rem; color: #666;"></div>
        </div>

        <!-- Message Container -->
        <div id="message-container"></div>

        <!-- Properties Section -->
        <div class="properties-section">
            <h2>Pending Properties</h2>
            <div id="properties-list" style="min-height: 200px;">
                <div class="loading">Loading properties...</div>
            </div>
        </div>
    </div>

    <!-- Modal for Rejection -->
    <div class="modal-overlay" id="rejection-modal"></div>
    <div class="modal-content" id="rejection-modal-content">
        <h3>Reject Property</h3>
        <p>Please provide a reason for rejection:</p>
        <textarea id="rejection-reason" placeholder="e.g., Incomplete information, Suspicious listing, Poor quality images..."></textarea>
        <div class="modal-buttons">
            <button onclick="closeRejectionModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="confirmBatchReject()" class="btn btn-danger">Reject</button>
        </div>
    </div>

    <!-- Modal for Auto-Verify Confirmation -->
    <div class="modal-overlay" id="auto-verify-modal"></div>
    <div class="modal-content" id="auto-verify-modal-content">
        <h3>⚡ Auto-Verify Properties</h3>
        <p>This will automatically verify all properties that meet the following criteria:</p>
        <ul style="margin: 1rem 0; color: #666; line-height: 1.8;">
            <li>✓ Seller is KYC verified</li>
            <li>✓ Has at least 1 property image</li>
            <li>✓ Has a description</li>
            <li>✓ Price is set correctly</li>
            <li>✓ Seller account is active</li>
        </ul>
        <p>Continue?</p>
        <div class="modal-buttons">
            <button onclick="closeAutoVerifyModal()" class="btn btn-secondary">Cancel</button>
            <button onclick="performAutoVerify()" class="btn btn-success">Yes, Auto-Verify</button>
        </div>
    </div>

    <script>
        let selectedProperties = [];

        // Load pending properties on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadPendingProperties();
            setInterval(loadStats, 30000); // Refresh stats every 30 seconds
        });

        function loadPendingProperties() {
            const container = document.getElementById('properties-list');
            container.innerHTML = '<div class="loading">Loading properties...</div>';

            fetch('admin_verify_property.php?action=get_pending_properties&limit=50&offset=0')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.properties.length > 0) {
                        renderProperties(data.properties);
                    } else {
                        container.innerHTML = `
                            <div class="empty-state">
                                <div class="checkmark">✓</div>
                                <p>All properties have been verified!</p>
                                <p style="color: #999; font-size: 0.9rem;">No pending properties at this time.</p>
                            </div>
                        `;
                    }
                    loadStats();
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<div class="error-message">Failed to load properties</div>';
                });
        }

        function getPropertyCategory(property) {
            return property.main_category || property.category || 'General';
        }

        function renderProperties(properties) {
            const container = document.getElementById('properties-list');
            container.innerHTML = '';

            properties.forEach(property => {
                const card = document.createElement('div');
                card.className = 'property-card';
                card.innerHTML = `
                    <div class="property-header">
                        <input type="checkbox" class="property-checkbox" value="${property.id}" onChange="updateSelections()">
                        <div class="property-title">
                            <h3>${property.property_type} - ${getPropertyCategory(property)}</h3>
                            <p>${property.location}</p>
                        </div>
                        <span class="property-status status-pending">Pending</span>
                    </div>
                    
                    <div class="seller-info">
                        <p><strong>Seller:</strong> ${property.seller_name} ${property.kyc_verified ? '<span class="kyc-verified">KYC Verified</span>' : '<span class="kyc-not-verified">Not Verified</span>'}</p>
                        <p><strong>Email:</strong> ${property.seller_email}</p>
                        <p><strong>Phone:</strong> ${property.seller_phone}</p>
                    </div>

                    <div class="property-details">
                        <div class="detail-item">
                            <div class="detail-label">Price</div>
                            <div class="detail-value">KES ${Number(property.price).toLocaleString()}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Bedrooms</div>
                            <div class="detail-value">${property.bedrooms || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Images</div>
                            <div class="detail-value">${property.image_count} uploaded</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Submitted</div>
                            <div class="detail-value">${new Date(property.created_at).toLocaleDateString()}</div>
                        </div>
                    </div>

                    <div class="property-actions">
                        <button onclick="verifyProperty(${property.id})" class="action-verify">✓ Verify</button>
                        <button onclick="showRejectForm(${property.id})" class="action-reject">✗ Reject</button>
                        <button onclick="viewProperty(${property.id})" class="action-view">👁 View</button>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function updateSelections() {
            const checkboxes = document.querySelectorAll('.property-checkbox:checked');
            selectedProperties = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            const counter = document.getElementById('selection-counter');
            counter.textContent = `${selectedProperties.length} properties selected`;
            
            document.getElementById('batch-verify-btn').disabled = selectedProperties.length === 0;
            document.getElementById('batch-reject-btn').disabled = selectedProperties.length === 0;
        }

        function clearSelections() {
            document.querySelectorAll('.property-checkbox').forEach(cb => cb.checked = false);
            updateSelections();
        }

        function verifyProperty(propertyId) {
            if (confirm('Verify this property?')) {
                const formData = new FormData();
                formData.append('action', 'verify_property');
                formData.append('property_id', propertyId);
                formData.append('is_verified', '1');

                fetch('admin_verify_property.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage('✓ Property verified successfully!', 'success');
                            loadPendingProperties();
                        } else {
                            showMessage('✗ ' + data.message, 'error');
                        }
                    });
            }
        }

        function batchVerifySelected() {
            if (selectedProperties.length === 0) {
                showMessage('No properties selected', 'error');
                return;
            }

            if (confirm(`Verify ${selectedProperties.length} selected properties?`)) {
                const formData = new FormData();
                formData.append('action', 'batch_verify');
                formData.append('property_ids', JSON.stringify(selectedProperties));
                formData.append('batch_action', 'verify');

                fetch('admin_verify_property.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(`✓ ${data.updated} properties verified!`, 'success');
                            clearSelections();
                            loadPendingProperties();
                        } else {
                            showMessage('✗ ' + data.message, 'error');
                        }
                    });
            }
        }

        function performAutoVerify() {
            closeAutoVerifyModal();
            const formData = new FormData();
            formData.append('action', 'apply_auto_verification');

            fetch('admin_verify_property.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(`⚡ ${data.verified_count} properties auto-verified!`, 'success');
                        loadPendingProperties();
                    } else {
                        showMessage('✗ ' + data.message, 'error');
                    }
                });
        }

        function showMessage(message, type) {
            const container = document.getElementById('message-container');
            const div = document.createElement('div');
            div.className = type === 'success' ? 'success-message' : 'error-message';
            div.textContent = message;
            container.innerHTML = '';
            container.appendChild(div);
            setTimeout(() => div.remove(), 5000);
        }

        function loadStats() {
            fetch('admin_verify_property.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('pending-count').textContent = data.stats.pending_count;
                        document.getElementById('verified-count').textContent = data.stats.verified_count;
                        document.getElementById('rejected-count').textContent = data.stats.rejected_count;
                    }
                });
        }

        function showAutoVerifyConfirm() {
            document.getElementById('auto-verify-modal').style.display = 'block';
            document.getElementById('auto-verify-modal-content').style.display = 'block';
        }

        function closeAutoVerifyModal() {
            document.getElementById('auto-verify-modal').style.display = 'none';
            document.getElementById('auto-verify-modal-content').style.display = 'none';
        }

        function showRejectForm(propertyId) {
            // This will be implemented in next steps
            alert('Rejection form will be implemented in next section');
        }

        function viewProperty(propertyId) {
            window.open('property_details.php?id=' + propertyId, '_blank');
        }

        // Close modals when clicking overlay
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.style.display = 'none';
                event.target.nextElementSibling.style.display = 'none';
            }
        });
    </script>
</body>
</html>
