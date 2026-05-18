<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

$facility_id = intval($_GET['facility_id'] ?? 0);

if ($facility_id <= 0) {
    header('Location: admin_storage_facilities.php');
    exit;
}

// Get facility details
$facility_result = $conn->query("SELECT * FROM storage_facilities WHERE id=$facility_id");
if (!($facility = $facility_result->fetch_assoc())) {
    header('Location: admin_storage_facilities.php');
    exit;
}

// Get all units for this facility
$units_result = $conn->query("SELECT * FROM storage_units WHERE facility_id=$facility_id ORDER BY unit_number");
$units = [];
while ($row = $units_result->fetch_assoc()) {
    $units[] = $row;
}

// Get booking statistics for this facility
$bookings_result = $conn->query("SELECT 
    COUNT(DISTINCT sb.id) as total_bookings,
    SUM(CASE WHEN sb.status='active' THEN 1 ELSE 0 END) as active_bookings,
    SUM(CASE WHEN sb.payment_status!='paid' THEN 1 ELSE 0 END) as pending_payments
    FROM storage_bookings sb
    WHERE sb.facility_id=$facility_id");
$booking_stats = $bookings_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Storage Units - Walbrand Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .breadcrumb {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 20px;
            margin-bottom: 30px;
            border-radius: 8px;
        }

        .admin-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .facility-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .facility-info h3 {
            color: var(--primary);
            margin-bottom: 15px;
        }

        .facility-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .content-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .content-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.3s;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-back {
            background: #e5e7eb;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: #d1d5db;
        }

        .units-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }

        .unit-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            transition: box-shadow 0.3s, border-color 0.3s;
        }

        .unit-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: var(--primary);
        }

        .unit-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .unit-number {
            font-size: 20px;
            font-weight: bold;
            color: var(--primary);
        }

        .unit-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: #d1fae5;
            color: #065f46;
        }

        .status-booked {
            background: #fecaca;
            color: #991b1b;
        }

        .status-maintenance {
            background: #fef3c7;
            color: #92400e;
        }

        .unit-details {
            margin-bottom: 15px;
            font-size: 14px;
        }

        .unit-detail-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .unit-detail-label {
            color: #666;
            font-weight: 500;
        }

        .unit-detail-value {
            color: #333;
            font-weight: 600;
        }

        .unit-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn-submit {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #333;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            display: block;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            display: block;
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="breadcrumb">
            <a href="admin_storage_facilities.php"><i class="fas fa-boxes"></i> Storage Facilities</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($facility['name']); ?></span>
        </div>

        <div class="admin-header">
            <h1>
                <i class="fas fa-warehouse"></i>
                Manage Storage Units
            </h1>
            <p><?php echo htmlspecialchars($facility['name']); ?> - <?php echo htmlspecialchars($facility['city']); ?></p>
        </div>

        <!-- Facility Info -->
        <div class="facility-info">
            <h3>Facility Information</h3>
            <div class="facility-details">
                <div class="detail-item">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?php echo htmlspecialchars($facility['address']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Contact</div>
                    <div class="detail-value"><?php echo htmlspecialchars($facility['contact_phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Price per Unit</div>
                    <div class="detail-value">KSH <?php echo number_format($facility['pricing_per_unit_monthly'] ?? 0, 2); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Climate Control</div>
                    <div class="detail-value"><?php echo $facility['climate_controlled'] ? '✓ Yes' : 'No'; ?></div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Units</div>
                <div class="stat-value"><?php echo count($units); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Available Units</div>
                <div class="stat-value"><?php echo count(array_filter($units, fn($u) => $u['availability_status'] === 'available')); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?php echo $booking_stats['total_bookings'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Bookings</div>
                <div class="stat-value"><?php echo $booking_stats['active_bookings'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Message -->
        <div class="message" id="message"></div>

        <!-- Units List -->
        <div class="content-wrapper">
            <div class="content-header">
                <h2 style="margin: 0;">Storage Units</h2>
                <div>
                    <button class="btn-primary" onclick="openUnitModal()">
                        <i class="fas fa-plus"></i> Add Unit
                    </button>
                    <button class="btn-back" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>

            <?php if (empty($units)): ?>
            <div class="empty-state">
                <i class="fas fa-box" style="font-size: 48px; opacity: 0.5; margin-bottom: 20px;"></i>
                <h3>No units yet</h3>
                <p>Add your first storage unit to get started</p>
            </div>
            <?php else: ?>
            <div class="units-grid">
                <?php foreach ($units as $unit): ?>
                <div class="unit-card">
                    <div class="unit-header">
                        <div class="unit-number">Unit <?php echo htmlspecialchars($unit['unit_number']); ?></div>
                        <span class="unit-status status-<?php echo $unit['availability_status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $unit['availability_status'])); ?>
                        </span>
                    </div>

                    <div class="unit-details">
                        <div class="unit-detail-row">
                            <span class="unit-detail-label">Category:</span>
                            <span class="unit-detail-value"><?php echo htmlspecialchars($unit['size_category']); ?></span>
                        </div>
                        <div class="unit-detail-row">
                            <span class="unit-detail-label">Size:</span>
                            <span class="unit-detail-value"><?php echo $unit['size_sqft']; ?> sq ft</span>
                        </div>
                        <div class="unit-detail-row">
                            <span class="unit-detail-label">Monthly Price:</span>
                            <span class="unit-detail-value">KSH <?php echo number_format($unit['price_monthly'], 2); ?></span>
                        </div>
                        <div class="unit-detail-row">
                            <span class="unit-detail-label">Access:</span>
                            <span class="unit-detail-value"><?php echo htmlspecialchars($unit['access_type']); ?></span>
                        </div>
                    </div>

                    <div class="unit-actions">
                        <button class="btn-secondary" onclick="editUnit(<?php echo $unit['id']; ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-danger" onclick="deleteUnit(<?php echo $unit['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Unit Modal -->
    <div class="modal" id="unitModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add New Storage Unit</span>
                <button class="modal-close" onclick="closeUnitModal()">×</button>
            </div>
            <form id="unitForm" onsubmit="saveUnit(event)">
                <input type="hidden" id="unitId" value="">

                <div class="form-group">
                    <label class="form-label">Unit Number *</label>
                    <input type="text" class="form-input" id="unitNumber" placeholder="e.g., A-101" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Size Category *</label>
                        <select class="form-select" id="unitCategory" required>
                            <option value="">Select category</option>
                            <option value="Small">Small (50-100 sq ft)</option>
                            <option value="Medium">Medium (100-200 sq ft)</option>
                            <option value="Large">Large (200-500 sq ft)</option>
                            <option value="Extra Large">Extra Large (500+ sq ft)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Size (sq ft)</label>
                        <input type="number" class="form-input" id="unitSize" min="0">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Monthly Price *</label>
                        <input type="number" class="form-input" id="unitPrice" min="0" step="100" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="unitStatus">
                            <option value="available">Available</option>
                            <option value="booked">Booked</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="unavailable">Unavailable</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Access Type</label>
                    <input type="text" class="form-input" id="unitAccess" placeholder="e.g., 24/7, 6 AM - 10 PM">
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-input" id="unitDescription" placeholder="Additional details about this unit"></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeUnitModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save Unit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const facilityId = <?php echo $facility_id; ?>;

        function openUnitModal() {
            document.getElementById('unitForm').reset();
            document.getElementById('unitId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Storage Unit';
            document.getElementById('unitModal').classList.add('active');
        }

        function closeUnitModal() {
            document.getElementById('unitModal').classList.remove('active');
        }

        function editUnit(unitId) {
            fetch(`storage_handler.php?action=get_units&facility_id=${facilityId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const unit = data.data.find(u => u.id === unitId);
                        if (unit) {
                            document.getElementById('unitId').value = unit.id;
                            document.getElementById('unitNumber').value = unit.unit_number;
                            document.getElementById('unitCategory').value = unit.size_category;
                            document.getElementById('unitSize').value = unit.size_sqft || '';
                            document.getElementById('unitPrice').value = unit.price_monthly;
                            document.getElementById('unitStatus').value = unit.availability_status;
                            document.getElementById('unitAccess').value = unit.access_type || '';
                            document.getElementById('unitDescription').value = unit.description || '';
                            document.getElementById('modalTitle').textContent = 'Edit Storage Unit';
                            document.getElementById('unitModal').classList.add('active');
                        }
                    }
                });
        }

        function saveUnit(e) {
            e.preventDefault();
            const unitId = document.getElementById('unitId').value;
            const action = unitId ? 'update_unit' : 'add_unit';

            const formData = new FormData();
            formData.append('action', action);
            formData.append('facility_id', facilityId);
            if (unitId) formData.append('unit_id', unitId);
            formData.append('unit_number', document.getElementById('unitNumber').value);
            formData.append('size_category', document.getElementById('unitCategory').value);
            formData.append('size_sqft', document.getElementById('unitSize').value);
            formData.append('price_monthly', document.getElementById('unitPrice').value);
            formData.append('availability_status', document.getElementById('unitStatus').value);
            formData.append('access_type', document.getElementById('unitAccess').value);
            formData.append('description', document.getElementById('unitDescription').value);

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage(data.message || 'Error saving unit', 'error');
                    }
                });
        }

        function deleteUnit(unitId) {
            if (confirm('Are you sure you want to delete this unit? All associated bookings will be deleted.')) {
                const formData = new FormData();
                formData.append('action', 'delete_unit');
                formData.append('unit_id', unitId);

                fetch('storage_handler.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message, 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showMessage(data.message || 'Error deleting unit', 'error');
                        }
                    });
            }
        }

        function showMessage(message, type) {
            const el = document.getElementById('message');
            el.textContent = message;
            el.className = `message ${type}`;
            setTimeout(() => el.classList.remove(type), 5000);
        }
    </script>
</body>
</html>
