<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Get all facilities
$facilities_result = $conn->query("SELECT f.*,
    COALESCE(unit_counts.total_units, 0) as total_units,
    COALESCE(unit_counts.available_units, 0) as available_units,
    COALESCE(booking_count.active_bookings, 0) as active_bookings
    FROM storage_facilities f
    LEFT JOIN (
        SELECT facility_id,
            COUNT(*) as total_units,
            SUM(CASE WHEN availability_status='available' THEN 1 ELSE 0 END) as available_units
        FROM storage_units
        GROUP BY facility_id
    ) unit_counts ON f.id = unit_counts.facility_id
    LEFT JOIN (
        SELECT facility_id, COUNT(*) as active_bookings FROM storage_bookings WHERE status IN ('active', 'pending', 'paid', 'confirmed') GROUP BY facility_id
    ) booking_count ON f.id = booking_count.facility_id
    ORDER BY f.created_at DESC");
$facilities = [];
while ($row = $facilities_result->fetch_assoc()) {
    $facilities[] = $row;
}

// Get statistics
$stats_result = $conn->query("SELECT 
    COUNT(DISTINCT f.id) as total_facilities,
    COUNT(DISTINCT u.id) as total_units,
    SUM(u.size_sqft) as total_sqft,
    SUM(CASE WHEN u.availability_status='available' THEN 1 ELSE 0 END) as available_units,
    COUNT(DISTINCT sb.id) as total_bookings,
    SUM(CASE WHEN sb.status='active' THEN 1 ELSE 0 END) as active_bookings
    FROM storage_facilities f
    LEFT JOIN storage_units u ON f.id = u.facility_id
    LEFT JOIN storage_bookings sb ON u.id = sb.unit_id");
$stats = $stats_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Facilities Management - Walbrand Admin</title>
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

        .admin-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 20px;
            margin-bottom: 30px;
            border-radius: 8px 8px 0 0;
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary);
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .btn-back {
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }

        .btn-back:hover {
            background: #4b5563;
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

        .facilities-table {
            width: 100%;
            border-collapse: collapse;
        }

        .facilities-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e5e7eb;
        }

        .facilities-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .facilities-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }

        .facilities-table tbody tr:hover {
            background: #f9fafb;
        }

        .facilities-table td {
            padding: 15px;
            color: #333;
        }

        .facility-name {
            font-weight: 600;
            color: var(--primary);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
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
            max-width: 600px;
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

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
        }

        .checkbox-group input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
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
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: var(--primary-dark);
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #333;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .success-message.show {
            display: flex;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>
                <i class="fas fa-cube"></i>
                Storage Facilities Management
            </h1>
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <button type="button" class="btn-back" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <p style="margin: 0;">Manage student housing storage facilities and units</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Facilities</div>
                <div class="stat-value"><?php echo $stats['total_facilities'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Units</div>
                <div class="stat-value"><?php echo $stats['total_units'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Available Units</div>
                <div class="stat-value"><?php echo $stats['available_units'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Bookings</div>
                <div class="stat-value"><?php echo $stats['active_bookings'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <div class="success-message" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <span id="successText"></span>
        </div>
        <div class="error-message" id="errorMessage">
            <i class="fas fa-exclamation-circle"></i>
            <span id="errorText"></span>
        </div>

        <!-- Facilities List -->
        <div class="content-wrapper">
            <div class="content-header">
                <h2 style="margin: 0;">Storage Facilities</h2>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <a href="admin_storage_bookings.php" class="btn-secondary" style="background:#059669;color:white;"> <i class="fas fa-receipt"></i> Manage Storage Requests</a>
                    <button class="btn-primary" onclick="openFacilityModal()">
                        <i class="fas fa-plus"></i> Add New Facility
                    </button>
                </div>
            </div>

            <?php if (empty($facilities)): ?>
            <div class="empty-state">
                <i class="fas fa-cube"></i>
                <h3>No facilities yet</h3>
                <p>Create your first storage facility to get started</p>
            </div>
            <?php else: ?>
            <table class="facilities-table">
                <thead>
                    <tr>
                        <th>Facility Name</th>
                        <th>Location</th>
                        <th>Capacity</th>
                        <th>Total Units</th>
                        <th>Available</th>
                        <th>Monthly Rate</th>
                        <th>Deposit</th>
                        <th>Max Months</th>
                        <th>Status</th>
                        <th>Availability</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($facilities as $facility): ?>
                    <tr>
                        <td><span class="facility-name"><?php echo htmlspecialchars($facility['name']); ?></span></td>
                        <td><?php echo htmlspecialchars($facility['city'] . ', ' . $facility['county']); ?></td>
                        <td><?php echo intval($facility['student_capacity'] ?? 0); ?></td>
                        <td><?php echo $facility['total_units']; ?></td>
                        <td>
                            <span class="badge badge-success"><?php echo $facility['available_units']; ?> available</span>
                        </td>
                        <td>KSH <?php echo number_format($facility['pricing_per_unit_monthly'] ?? 0, 2); ?></td>
                        <td>KSH <?php echo number_format($facility['deposit_amount'] ?? 0, 2); ?></td>
                        <td><?php echo intval($facility['max_months'] ?? 0); ?></td>
                        <td>
                            <span class="badge badge-info"><?php echo ucfirst($facility['verification_status']); ?></span>
                        </td>
                        <td>
                            <?php
                                $isFacilityFull = intval($facility['active_bookings'] ?? 0) >= intval($facility['student_capacity'] ?? 0) && intval($facility['student_capacity'] ?? 0) > 0;
                                if ($facility['verification_status'] !== 'verified') {
                                    $availabilityLabel = 'Pending';
                                    $availabilityClass = 'badge badge-warning';
                                } elseif ($isFacilityFull) {
                                    $availabilityLabel = 'Full';
                                    $availabilityClass = 'badge badge-danger';
                                } else {
                                    $availabilityLabel = 'Available';
                                    $availabilityClass = 'badge badge-success';
                                }
                            ?>
                            <span class="<?php echo $availabilityClass; ?>"><?php echo $availabilityLabel; ?></span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-secondary" onclick="editFacility(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-secondary" onclick="manageFacilityUnits(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-boxes"></i> Units
                                </button>
                                <button class="btn-danger" onclick="deleteFacility(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php if ($facility['verification_status'] === 'pending'): ?>
                                    <button class="btn-primary" style="background:#059669;" onclick="verifyFacility(<?php echo $facility['id']; ?>)">
                                        <i class="fas fa-check"></i> Verify
                                    </button>
                                    <button class="btn-secondary" style="background:#f97316;color:white;" onclick="rejectFacility(<?php echo $facility['id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <button class="btn-secondary" onclick="setFacilityStatus(<?php echo $facility['id']; ?>,'pending')">
                                        <i class="fas fa-clock"></i> Mark Pending
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add/Edit Facility Modal -->
    <div class="modal" id="facilityModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add New Storage Facility</span>
                <button class="modal-close" onclick="closeFacilityModal()">×</button>
            </div>
            <form id="facilityForm" onsubmit="saveFacility(event)">
                <input type="hidden" id="facilityId" value="">

                <div class="form-group">
                    <label class="form-label">Facility Name *</label>
                    <input type="text" class="form-input" id="facilityName" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" id="facilityDescription"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Address *</label>
                        <input type="text" class="form-input" id="facilityAddress" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City *</label>
                        <input type="text" class="form-input" id="facilityCity" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">County *</label>
                        <input type="text" class="form-input" id="facilityCounty" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Price per Unit *</label>
                        <input type="number" class="form-input" id="facilityPrice" min="0" step="100" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Student Capacity *</label>
                        <input type="number" class="form-input" id="studentCapacity" min="1" step="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deposit Amount *</label>
                        <input type="number" class="form-input" id="depositAmount" min="0" step="50" value="500" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Max Booking Months *</label>
                        <input type="number" class="form-input" id="maxMonths" min="1" step="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Pickup Overdue Penalty *</label>
                        <input type="number" class="form-input" id="pickupPenalty" min="0" step="50" value="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Storage Overdue Penalty *</label>
                        <input type="number" class="form-input" id="storagePenalty" min="0" step="50" value="0" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" class="form-input" id="facilityPhone">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-input" id="facilityEmail">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Access Hours</label>
                    <input type="text" class="form-input" id="facilityAccessHours" placeholder="e.g., 24/7 or 6 AM - 10 PM">
                </div>

                <div class="form-group">
                    <label class="form-label">Features</label>
                    <label class="checkbox-group">
                        <input type="checkbox" id="climateControlled">
                        Climate Controlled
                    </label>
                    <label class="checkbox-group">
                        <input type="checkbox" id="surveillance" checked>
                        24/7 Surveillance
                    </label>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeFacilityModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Facility</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openFacilityModal() {
            document.getElementById('facilityForm').reset();
            document.getElementById('facilityId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Storage Facility';
            document.getElementById('facilityModal').classList.add('active');
        }

        function closeFacilityModal() {
            document.getElementById('facilityModal').classList.remove('active');
        }

        function editFacility(facilityId) {
            fetch(`storage_handler.php?action=get_facility&facility_id=${facilityId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const f = data.data;
                        document.getElementById('facilityId').value = f.id;
                        document.getElementById('facilityName').value = f.name;
                        document.getElementById('facilityDescription').value = f.description || '';
                        document.getElementById('facilityAddress').value = f.address;
                        document.getElementById('facilityCity').value = f.city;
                        document.getElementById('facilityCounty').value = f.county;
                        document.getElementById('facilityPrice').value = f.pricing_per_unit_monthly;
                        document.getElementById('facilityPhone').value = f.contact_phone || '';
                        document.getElementById('facilityEmail').value = f.contact_email || '';
                        document.getElementById('facilityAccessHours').value = f.access_hours || '';
                        document.getElementById('studentCapacity').value = f.student_capacity ?? 0;
                        document.getElementById('depositAmount').value = f.deposit_amount ?? 500;
                        document.getElementById('maxMonths').value = f.max_months ?? 1;
                        document.getElementById('pickupPenalty').value = f.pickup_overdue_penalty ?? 0;
                        document.getElementById('storagePenalty').value = f.storage_overdue_penalty ?? 0;
                        document.getElementById('climateControlled').checked = f.climate_controlled == 1;
                        document.getElementById('surveillance').checked = f.surveillance == 1;
                        document.getElementById('modalTitle').textContent = 'Edit Storage Facility';
                        document.getElementById('facilityModal').classList.add('active');
                    }
                })
                .catch(err => showError('Error loading facility: ' + err.message));
        }

        function saveFacility(e) {
            e.preventDefault();
            const facilityId = document.getElementById('facilityId').value;
            const action = facilityId ? 'update_facility' : 'add_facility';

            const formData = new FormData();
            formData.append('action', action);
            if (facilityId) formData.append('facility_id', facilityId);
            formData.append('name', document.getElementById('facilityName').value);
            formData.append('description', document.getElementById('facilityDescription').value);
            formData.append('address', document.getElementById('facilityAddress').value);
            formData.append('city', document.getElementById('facilityCity').value);
            formData.append('county', document.getElementById('facilityCounty').value);
            formData.append('pricing_per_unit_monthly', document.getElementById('facilityPrice').value);
            formData.append('contact_phone', document.getElementById('facilityPhone').value);
            formData.append('contact_email', document.getElementById('facilityEmail').value);
            formData.append('access_hours', document.getElementById('facilityAccessHours').value);
            formData.append('student_capacity', document.getElementById('studentCapacity').value);
            formData.append('deposit_amount', document.getElementById('depositAmount').value);
            formData.append('max_months', document.getElementById('maxMonths').value);
            formData.append('pickup_overdue_penalty', document.getElementById('pickupPenalty').value);
            formData.append('storage_overdue_penalty', document.getElementById('storagePenalty').value);
            if (document.getElementById('climateControlled').checked) formData.append('climate_controlled', '1');
            if (document.getElementById('surveillance').checked) formData.append('surveillance', '1');

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message || 'Error saving facility');
                    }
                })
                .catch(err => showError('Error: ' + err.message));
        }

        function deleteFacility(facilityId) {
            if (confirm('Are you sure you want to delete this facility? All associated units and bookings will be deleted.')) {
                const formData = new FormData();
                formData.append('action', 'delete_facility');
                formData.append('facility_id', facilityId);

                fetch('storage_handler.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showSuccess(data.message);
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showError(data.message || 'Error deleting facility');
                        }
                    })
                    .catch(err => showError('Error: ' + err.message));
            }
        }

        function manageFacilityUnits(facilityId) {
            window.location.href = `admin_storage_units.php?facility_id=${facilityId}`;
        }

        function showSuccess(message) {
            const el = document.getElementById('successMessage');
            document.getElementById('successText').textContent = message;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 5000);
        }

        function showError(message) {
            const el = document.getElementById('errorMessage');
            document.getElementById('errorText').textContent = message;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 5000);
        }

        function verifyFacility(facilityId) {
            if (!confirm('Verify this facility and make it visible to users?')) return;
            const formData = new FormData();
            formData.append('action', 'set_verification');
            formData.append('facility_id', facilityId);
            formData.append('status', 'verified');

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message || 'Facility verified');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message || 'Error verifying facility');
                    }
                })
                .catch(err => showError('Error: ' + err.message));
        }

        function rejectFacility(facilityId) {
            if (!confirm('Reject this facility submission?')) return;
            const formData = new FormData();
            formData.append('action', 'set_verification');
            formData.append('facility_id', facilityId);
            formData.append('status', 'rejected');

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message || 'Facility rejected');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message || 'Error rejecting facility');
                    }
                })
                .catch(err => showError('Error: ' + err.message));
        }

        function setFacilityStatus(facilityId, status) {
            const formData = new FormData();
            formData.append('action', 'set_verification');
            formData.append('facility_id', facilityId);
            formData.append('status', status);
            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showSuccess(data.message || 'Status updated');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(data.message || 'Error updating status');
                    }
                })
                .catch(err => showError('Error: ' + err.message));
        }

        // If a facility_id is present in the URL, open it for verification on load
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const params = new URLSearchParams(window.location.search);
                const fid = params.get('facility_id');
                if (fid) {
                    // attempt to open the facility modal for the admin to verify
                    editFacility(fid);
                }
            } catch (e) {
                // ignore malformed URLs
            }
        });
    </script>
</body>
</html>
