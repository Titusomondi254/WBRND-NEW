<?php
/**
 * Admin Property Verification Interface
 * Displays properties pending verification with options to verify/reject
 * Walbrand Properties Marketplace & Interiors - Kenya Real Estate Marketplace
 */

require_once 'admin_auth.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get filter parameters
    $status_filter = $_GET['status'] ?? 'pending_verification';
    $sort_by = $_GET['sort'] ?? 'created_at';
    $sort_order = $_GET['order'] ?? 'DESC';

    // Validate sort parameters
    $allowed_sort = ['created_at', 'location', 'price', 'bedrooms'];
    $allowed_order = ['ASC', 'DESC'];

    if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';
    if (!in_array($sort_order, $allowed_order)) $sort_order = 'DESC';

    // Get properties pending verification
    $query = "
        SELECT 
            p.id, p.property_code, p.location, p.price, p.bedrooms, p.bathrooms,
            p.size_sqm, p.property_type, p.listing_type, p.status, p.verification_status,
            p.created_at, p.seller_id, u.first_name, u.last_name, u.email, u.phone,
            p.image_count, p.description, p.features
        FROM properties p
        LEFT JOIN users u ON p.seller_id = u.id
        WHERE p.verification_status = ?
        ORDER BY p.$sort_by $sort_order
        LIMIT 50
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status_filter);
    $stmt->execute();
    $result = $stmt->get_result();
    $properties = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Get count of pending properties
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM properties WHERE verification_status = ?");
    $stmt->bind_param("s", $status_filter);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $pending_count = $count_row['total'];
    $stmt->close();

    // Get list of agents for assignment
    $agents_query = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone
        FROM users u
        WHERE u.user_type = 'agent' AND u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ";
    $agents_result = $conn->query($agents_query);
    $agents = $agents_result->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Verification - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e40af;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #ea580c;
            --info-color: #0891b2;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .verification-container {
            padding: 2rem 0;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color), #1e3a8a);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .header-section h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .header-section .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.15);
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid rgba(255, 255, 255, 0.5);
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-card .label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .property-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 5px solid var(--warning-color);
        }

        .property-card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .property-card-header {
            background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }

        .property-code {
            font-size: 0.85rem;
            color: var(--primary-color);
            font-weight: 600;
            background: #eff6ff;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .property-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0.5rem 0;
        }

        .property-location {
            color: #6b7280;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .property-card-body {
            padding: 1.5rem;
        }

        .property-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .spec-item {
            text-align: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .spec-label {
            font-size: 0.85rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .spec-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .seller-info {
            background: #f0f9ff;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid var(--info-color);
            margin-bottom: 1.5rem;
        }

        .seller-info-label {
            font-size: 0.85rem;
            color: #0891b2;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .seller-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .seller-contact {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .seller-contact a {
            color: var(--info-color);
            text-decoration: none;
        }

        .seller-contact a:hover {
            text-decoration: underline;
        }

        .property-description {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }

        .btn-verify, .btn-reject, .btn-review {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-verify {
            background: var(--success-color);
            color: white;
        }

        .btn-verify:hover {
            background: #15803d;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .btn-reject {
            background: var(--danger-color);
            color: white;
        }

        .btn-reject:hover {
            background: #b91c1c;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-review {
            background: var(--info-color);
            color: white;
        }

        .btn-review:hover {
            background: #0570a9;
            box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #1e3a8a);
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
        }

        .modal-header .btn-close {
            filter: invert(1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.1);
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .badge-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <div class="container-fluid verification-container">
        <!-- Header -->
        <div class="header-section">
            <h1><i class="fas fa-check-square me-3"></i>Property Verification Center</h1>
            <div class="stats">
                <div class="stat-card">
                    <div class="number"><?php echo $pending_count; ?></div>
                    <div class="label">Pending Verification</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo count($properties); ?></div>
                    <div class="label">Displayed on this page</div>
                </div>
                <div class="stat-card">
                    <div class="number"><?php echo count($agents); ?></div>
                    <div class="label">Active Agents</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sort By</label>
                    <select class="form-select" onchange="updateSort(this.value)">
                        <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="created_at_asc" <?php echo $sort_by === 'created_at' && $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="location" <?php echo $sort_by === 'location' ? 'selected' : ''; ?>>Location</option>
                        <option value="price" <?php echo $sort_by === 'price' ? 'selected' : ''; ?>>Price</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">&nbsp;</label>
                    <a href="admin_properties.php" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Back to Admin Panel
                    </a>
                </div>
            </div>
        </div>

        <!-- Properties List -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if (count($properties) > 0): ?>
            <?php foreach ($properties as $property): ?>
                <div class="property-card">
                    <div class="property-card-header">
                        <div>
                            <div class="property-code"><?php echo htmlspecialchars($property['property_code']); ?></div>
                            <h3 class="property-title"><?php echo htmlspecialchars($property['property_type'] ?? 'Property'); ?></h3>
                            <div class="property-location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($property['location']); ?>
                            </div>
                        </div>
                        <span class="badge badge-pending">
                            <i class="fas fa-clock me-1"></i>Pending
                        </span>
                    </div>

                    <div class="property-card-body">
                        <!-- Specs Grid -->
                        <div class="property-specs">
                            <div class="spec-item">
                                <div class="spec-label">Price</div>
                                <div class="spec-value">KES <?php echo number_format($property['price']); ?></div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-label">Bedrooms</div>
                                <div class="spec-value"><?php echo $property['bedrooms'] ?? 'N/A'; ?></div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-label">Bathrooms</div>
                                <div class="spec-value"><?php echo $property['bathrooms'] ?? 'N/A'; ?></div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-label">Size (sqm)</div>
                                <div class="spec-value"><?php echo number_format($property['size_sqm'] ?? 0, 1); ?></div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-label">Images</div>
                                <div class="spec-value"><?php echo $property['image_count']; ?></div>
                            </div>
                        </div>

                        <!-- Seller Info -->
                        <div class="seller-info">
                            <div class="seller-info-label">
                                <i class="fas fa-user-circle me-1"></i>Property Owner
                            </div>
                            <div class="seller-name"><?php echo htmlspecialchars($property['first_name'] . ' ' . $property['last_name']); ?></div>
                            <div class="seller-contact">
                                <a href="tel:<?php echo htmlspecialchars($property['phone']); ?>">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($property['phone']); ?>
                                </a>
                                <a href="mailto:<?php echo htmlspecialchars($property['email']); ?>">
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($property['email']); ?>
                                </a>
                            </div>
                        </div>

                        <!-- Description -->
                        <?php if (!empty($property['description'])): ?>
                            <div class="property-description">
                                <strong>Description:</strong><br>
                                <?php echo htmlspecialchars(substr($property['description'], 0, 200) . (strlen($property['description']) > 200 ? '...' : '')); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button class="btn-verify" onclick="openVerifyModal(<?php echo $property['id']; ?>)">
                                <i class="fas fa-check"></i>Verify
                            </button>
                            <button class="btn-reject" onclick="openRejectModal(<?php echo $property['id']; ?>)">
                                <i class="fas fa-times"></i>Reject
                            </button>
                            <button class="btn-review" onclick="viewPropertyDetails(<?php echo $property['id']; ?>)">
                                <i class="fas fa-eye"></i>View Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="property-card">
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h4>No properties to verify</h4>
                    <p>All pending properties have been processed.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Verify Modal -->
    <div class="modal fade" id="verifyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Verify Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="verifyForm">
                        <input type="hidden" id="verify_property_id" name="property_id">
                        <input type="hidden" name="action" value="verify_property">

                        <div class="form-group">
                            <label class="form-label">Occupancy Status</label>
                            <select class="form-select" name="occupancy_status" required>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="partially_occupied">Partially Occupied</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Admin Notes</label>
                            <textarea class="form-control" name="admin_notes" rows="4" placeholder="Add verification notes..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            The property will be automatically categorized and viewing fee will be calculated based on bedroom count.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitVerification()">
                        <i class="fas fa-check me-2"></i>Verify Property
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i>Reject Property</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="rejectForm">
                        <input type="hidden" id="reject_property_id" name="property_id">
                        <input type="hidden" name="action" value="reject_property">

                        <div class="form-group">
                            <label class="form-label">Rejection Reason *</label>
                            <textarea class="form-control" name="rejection_reason" rows="4" required placeholder="Explain why this property is being rejected..."></textarea>
                            <small class="form-text text-muted">The property owner will receive this message.</small>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action will notify the property owner. They can appeal or resubmit the property.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitRejection()">
                        <i class="fas fa-ban me-2"></i>Reject Property
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let verifyModal, rejectModal;

        document.addEventListener('DOMContentLoaded', function() {
            verifyModal = new bootstrap.Modal(document.getElementById('verifyModal'));
            rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
        });

        function openVerifyModal(propertyId) {
            document.getElementById('verify_property_id').value = propertyId;
            verifyModal.show();
        }

        function openRejectModal(propertyId) {
            document.getElementById('reject_property_id').value = propertyId;
            rejectModal.show();
        }

        function submitVerification() {
            const formData = new FormData(document.getElementById('verifyForm'));
            
            fetch('admin_verify_property_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Property verified successfully! Category: ' + data.category + ', Viewing Fee: KES ' + data.viewing_fee);
                    verifyModal.hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function submitRejection() {
            const formData = new FormData(document.getElementById('rejectForm'));
            
            fetch('admin_verify_property_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Property rejected successfully. The owner has been notified.');
                    rejectModal.hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }

        function updateSort(sortValue) {
            const parts = sortValue.split('_');
            const sort = parts[0];
            const order = parts.length > 1 ? 'ASC' : 'DESC';
            window.location.href = '?sort=' + sort + '&order=' + order;
        }

        function viewPropertyDetails(propertyId) {
            window.open('property_details.php?id=' + propertyId, '_blank');
        }
    </script>
</body>
</html>
