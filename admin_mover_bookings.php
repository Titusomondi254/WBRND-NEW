<?php
/**
 * Admin - Mover Bookings Management
 * View, filter, and manage all moving service bookings
 */

require_once 'admin_auth.php';
require_once 'config_mover_system.php';

$conn = getMoverDatabaseConnection();
if (!$conn) {
    die("Database connection failed - please check mover system configuration");
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? '';
$serviceTypeFilter = $_GET['service_type'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$query = "SELECT * FROM mover_bookings WHERE 1=1";
$params = [];
$types = "";

if (!empty($statusFilter)) {
    $query .= " AND status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($serviceTypeFilter)) {
    $query .= " AND service_type = ?";
    $params[] = $serviceTypeFilter;
    $types .= "s";
}

if (!empty($searchQuery)) {
    $query .= " AND (client_name LIKE ? OR email LIKE ? OR phone LIKE ? OR location_from LIKE ? OR location_to LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sssss";
}

$query .= " ORDER BY created_at DESC LIMIT 100";

$bookings = [];
try {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $bookings = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} catch (Exception $e) {
    // Table might not exist yet
    error_log("Mover bookings table not available: " . $e->getMessage());
}

// Get available groups
$groups = [];
try {
    $groupsQuery = "SELECT g.id, g.group_name, COUNT(m.id) as member_count 
                    FROM mover_groups g 
                    LEFT JOIN mover_group_members m ON g.id = m.group_id 
                    GROUP BY g.id, g.group_name 
                    ORDER BY g.group_name ASC";
    $groupsResult = $conn->query($groupsQuery);
    if ($groupsResult) {
        $groups = $groupsResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    // Tables might not exist yet
    error_log("Mover groups tables not available: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - Walbrand Movers Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --border-radius: 8px;
        }

        body {
            background: linear-gradient(135deg, #FFA500, #87CEEB);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            margin: 0;
            font-weight: bold;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            gap: 10px;
        }

        .filter-group select {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
        }

        .btn-filter {
            background: var(--accent-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
        }

        .btn-filter:hover {
            background: #c0392b;
        }

        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .bookings-table {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            margin: 0;
            font-size: 0.9rem;
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
        }

        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending, .status-paymentpending {
            background: #fff3cd;
            color: #856404;
        }

        .status-assigned {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-in_progress {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-assign {
            background: var(--info-color);
            color: white;
        }

        .btn-assign:hover {
            background: #2980b9;
        }

        .btn-view {
            background: var(--primary-color);
            color: white;
        }

        .btn-view:hover {
            background: #1a252f;
        }

        .btn-cancel {
            background: var(--accent-color);
            color: white;
        }

        .btn-cancel:hover {
            background: #c0392b;
        }

        .modal-dialog {
            max-width: 500px;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .booking-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            font-size: 0.9rem;
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .detail-value {
            color: #555;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .no-results i {
            font-size: 3rem;
            margin-bottom: 20px;
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-list"></i> Mover Bookings Management</h1>
                    <p class="mb-0" style="opacity: 0.9;">View and manage all moving service bookings</p>
                </div>
                <a href="index.php" class="btn btn-light btn-lg">
                    <i class="fas fa-home"></i> Back to Home Page
                </a>
            </div>
        </div>
    </div>

    <div class="container-fluid" style="padding: 0 20px;">
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="assigned" <?php echo $statusFilter === 'assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>

                    <select name="service_type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Service Types</option>
                        <option value="within_nairobi" <?php echo $serviceTypeFilter === 'within_nairobi' ? 'selected' : ''; ?>>Within Nairobi</option>
                        <option value="outside_nairobi" <?php echo $serviceTypeFilter === 'outside_nairobi' ? 'selected' : ''; ?>>Outside Nairobi</option>
                    </select>

                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or location..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>
        </div>

        <!-- Statistics Section -->
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'pending')); ?></div>
                <div class="stat-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'assigned')); ?></div>
                <div class="stat-label">Assigned Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count(array_filter($bookings, fn($b) => $b['status'] === 'in_progress')); ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">KES <?php echo number_format(array_sum(array_column($bookings, 'total_cost')), 0); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="bookings-table">
            <?php if (empty($bookings)): ?>
                <div class="no-results">
                    <i class="fas fa-inbox"></i>
                    <p>No bookings found matching your filters</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Client</th>
                            <th>From → To</th>
                            <th>Date & Time</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Group</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><strong>#<?php echo $booking['id']; ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($booking['client_name']); ?></div>
                                    <small style="color: #666;"><?php echo htmlspecialchars($booking['email']); ?></small>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?php echo htmlspecialchars(substr($booking['location_from'], 0, 15)); ?> → 
                                        <?php echo htmlspecialchars(substr($booking['location_to'], 0, 15)); ?>
                                    </small>
                                    <br>
                                    <small style="color: #666;">Distance: <?php echo $booking['distance_km']; ?> km</small>
                                </td>
                                <td>
                                    <small>
                                        <?php echo date('d M, Y', strtotime($booking['moving_date'])); ?>
                                        <br>
                                        <?php echo date('H:i', strtotime($booking['moving_time'])); ?>
                                    </small>
                                </td>
                                <td><strong>KES <?php echo number_format($booking['total_cost'], 0); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace('_', '', $booking['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['assigned_group_id']): ?>
                                        <small style="background: #d4edda; padding: 5px 10px; border-radius: 5px; color: #155724;">
                                            Group #<?php echo $booking['assigned_group_id']; ?>
                                        </small>
                                    <?php else: ?>
                                        <small style="color: #999;">Not assigned</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-view" onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($booking['status'] === 'pending'): ?>
                                            <button class="btn-small btn-assign" onclick="assignBooking(<?php echo $booking['id']; ?>)">
                                                <i class="fas fa-user-check"></i> Assign
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

    <!-- View Booking Modal -->
    <div class="modal fade" id="viewBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Booking Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookingDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Booking Modal -->
    <div class="modal fade" id="assignBookingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Booking to Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignForm">
                        <input type="hidden" id="assignBookingId" name="booking_id">
                        
                        <div class="form-group">
                            <label for="assignGroupId">Select Group:</label>
                            <select id="assignGroupId" name="group_id" class="form-select" required>
                                <option value="">Choose a group...</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>">
                                        <?php echo htmlspecialchars($group['group_name']); ?> 
                                        (<?php echo $group['member_count']; ?> members)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Assign Booking</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const viewBookingModal = new bootstrap.Modal(document.getElementById('viewBookingModal'));
        const assignBookingModal = new bootstrap.Modal(document.getElementById('assignBookingModal'));

        function viewBooking(bookingId) {
            fetch(`get_mover_booking.php?id=${bookingId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const booking = data.booking;
                        const html = `
                            <div class="booking-details">
                                <div class="detail-row">
                                    <span class="detail-label">Booking ID:</span>
                                    <span class="detail-value">#${booking.id}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Client:</span>
                                    <span class="detail-value">${booking.client_name}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Email:</span>
                                    <span class="detail-value">${booking.email}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Phone:</span>
                                    <span class="detail-value">${booking.phone}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">From:</span>
                                    <span class="detail-value">${booking.location_from}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">To:</span>
                                    <span class="detail-value">${booking.location_to}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Distance:</span>
                                    <span class="detail-value">${booking.distance_km} km</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value">${new Date(booking.moving_date).toLocaleDateString()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Time:</span>
                                    <span class="detail-value">${booking.moving_time}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">House Type:</span>
                                    <span class="detail-value">${booking.house_type.replace(/_/g, ' ').toUpperCase()}</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Cost:</span>
                                    <span class="detail-value"><strong>KES ${parseFloat(booking.total_cost).toLocaleString()}</strong></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Status:</span>
                                    <span class="detail-value">${booking.status.toUpperCase()}</span>
                                </div>
                                ${booking.additional_notes ? `
                                <div class="detail-row">
                                    <span class="detail-label">Notes:</span>
                                    <span class="detail-value">${booking.additional_notes}</span>
                                </div>
                                ` : ''}
                            </div>
                        `;
                        document.getElementById('bookingDetails').innerHTML = html;
                        viewBookingModal.show();
                    }
                })
                .catch(e => showError('Error loading booking details'));
        }

        function assignBooking(bookingId) {
            document.getElementById('assignBookingId').value = bookingId;
            assignBookingModal.show();
        }

        document.getElementById('assignForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const bookingId = document.getElementById('assignBookingId').value;
            const groupId = document.getElementById('assignGroupId').value;

            fetch('assign_mover_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `booking_id=${bookingId}&group_id=${groupId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Booking assigned successfully!');
                    location.reload();
                } else {
                    showError('Error: ' + data.error);
                }
            })
            .catch(e => showError('Error assigning booking'));
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
