<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$facility_filter = intval($_GET['facility'] ?? 0);
$payment_status_filter = $_GET['payment_status'] ?? '';

// Build query
$query = "SELECT sb.*, f.name as facility_name, u.size_category, u.unit_number, 
          CONCAT_WS(' ', us.first_name, us.last_name) as student_name, us.email, us.phone
          FROM storage_bookings sb
          JOIN storage_facilities f ON sb.facility_id = f.id
          JOIN storage_units u ON sb.unit_id = u.id
          JOIN users us ON sb.student_id = us.id
          WHERE 1=1";

if (!empty($status_filter)) {
    $status_filter = $conn->real_escape_string($status_filter);
    $query .= " AND sb.status='$status_filter'";
}

if ($facility_filter > 0) {
    $query .= " AND sb.facility_id=$facility_filter";
}

if (!empty($payment_status_filter)) {
    $payment_status_filter = $conn->real_escape_string($payment_status_filter);
    $query .= " AND sb.payment_status='$payment_status_filter'";
}

$query .= " ORDER BY sb.created_at DESC";

$result = $conn->query($query);
$bookings = [];
while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

// Get statistics
$stats_result = $conn->query("SELECT 
    COUNT(*) as total_bookings,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_bookings,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending_bookings,
    SUM(CASE WHEN payment_status='paid' THEN total_cost ELSE 0 END) as total_revenue
    FROM storage_bookings");
$stats = $stats_result->fetch_assoc();

// Get facilities for filter
$facilities_result = $conn->query("SELECT id, name FROM storage_facilities ORDER BY name");
$facilities = [];
while ($row = $facilities_result->fetch_assoc()) {
    $facilities[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Bookings Management - Walbrand Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --primary: #f97316;
            --primary-dark: #ea580c;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
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

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table thead {
            background: #f8f9fa;
            border-bottom: 2px solid #e5e7eb;
        }

        .bookings-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 13px;
            text-transform: uppercase;
        }

        .bookings-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: background 0.2s;
        }

        .bookings-table tbody tr:hover {
            background: #f9fafb;
        }

        .bookings-table td {
            padding: 15px;
            color: #333;
        }

        .booking-ref {
            font-weight: 600;
            color: var(--primary);
        }

        .student-info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-paid {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-unpaid {
            background: #fecaca;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }

        .btn-secondary:hover {
            background: #4b5563;
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

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .bookings-table {
                font-size: 12px;
            }

            .bookings-table td {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php require_once 'header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>
                <i class="fas fa-list"></i>
                Storage Bookings Management
            </h1>
            <p>Manage all student storage bookings and monitor payment status</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Bookings</div>
                <div class="stat-value"><?php echo $stats['total_bookings'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Active Bookings</div>
                <div class="stat-value"><?php echo $stats['active_bookings'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Pending Bookings</div>
                <div class="stat-value"><?php echo $stats['pending_bookings'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">KSH <?php echo number_format($stats['total_revenue'] ?? 0, 0); ?></div>
            </div>
        </div>

        <!-- Bookings List -->
        <div class="content-wrapper">
            <div class="content-header">
                <h2 style="margin: 0;">All Bookings</h2>
                <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                    <button type="button" class="btn-back" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                    <a href="admin_storage_facilities.php" class="btn-secondary"><i class="fas fa-warehouse"></i> Manage Facilities</a>
                    <div class="filters">
                    <select class="filter-input" id="statusFilter" onchange="applyFilters()">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    <select class="filter-input" id="paymentFilter" onchange="applyFilters()">
                        <option value="">All Payment Status</option>
                        <option value="unpaid" <?php echo $payment_status_filter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="partial" <?php echo $payment_status_filter === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="paid" <?php echo $payment_status_filter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                    </select>
                    <select class="filter-input" id="facilityFilter" onchange="applyFilters()">
                        <option value="">All Facilities</option>
                        <?php foreach ($facilities as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo $facility_filter === $f['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No bookings found</h3>
                <p>Adjust your filters or check back later</p>
            </div>
            <?php else: ?>
            <table class="bookings-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Student</th>
                        <th>Facility & Unit</th>
                        <th>Duration</th>
                        <th>Dates</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><span class="booking-ref"><?php echo htmlspecialchars($booking['booking_reference']); ?></span></td>
                        <td>
                            <div class="student-info">
                                <strong><?php echo htmlspecialchars($booking['student_name']); ?></strong>
                                <small><?php echo htmlspecialchars($booking['email']); ?></small>
                                <small><?php echo htmlspecialchars($booking['phone']); ?></small>
                            </div>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($booking['facility_name']); ?></strong><br>
                            <small><?php echo htmlspecialchars($booking['size_category'] . ' - Unit ' . $booking['unit_number']); ?></small>
                        </td>
                        <td><?php echo $booking['duration_months']; ?> months</td>
                        <td>
                            <small><?php echo date('d M', strtotime($booking['start_date'])); ?> - <?php echo date('d M Y', strtotime($booking['end_date'])); ?></small>
                        </td>
                        <td>KSH <?php echo number_format($booking['total_cost'], 0); ?></td>
                        <td><span class="badge badge-<?php echo strtolower($booking['status']); ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                        <td><span class="badge badge-<?php echo strtolower($booking['payment_status']); ?>"><?php echo ucfirst($booking['payment_status']); ?></span></td>
                        <td>
                            <div class="action-buttons">
                                    <button class="btn-secondary" onclick="openBookingModal(<?php echo $booking['id']; ?>)">View</button>
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'active')">Approve</button>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'cancelled')">Cancel</button>
                                <?php elseif ($booking['status'] === 'active'): ?>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'completed')">Complete</button>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'cancelled')">Cancel</button>
                                <?php elseif ($booking['status'] === 'completed'): ?>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'pending')">Reopen</button>
                                <?php else: ?>
                                    <button class="btn-secondary" onclick="updateBookingStatus(<?php echo $booking['id']; ?>, 'pending')">Reopen</button>
                                <?php endif; ?>
                                <?php if ($booking['payment_status'] !== 'paid'): ?>
                                    <button class="btn-secondary" onclick="updatePaymentStatus(<?php echo $booking['id']; ?>, 'paid')">Mark Paid</button>
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

    <script>
        function applyFilters() {
            const status = document.getElementById('statusFilter').value;
            const payment = document.getElementById('paymentFilter').value;
            const facility = document.getElementById('facilityFilter').value;
            
            let url = '?';
            if (status) url += 'status=' + status + '&';
            if (payment) url += 'payment_status=' + payment + '&';
            if (facility) url += 'facility=' + facility;
            
            window.location.href = url;
        }

        function updateBookingStatus(bookingId, status) {
            if (!confirm('Update booking status to ' + status + '?')) return;
            // Ask admin for an optional reason to send to the student
            let adminReason = '';
            try {
                adminReason = prompt('Enter a short reason or note for the student (optional):', '');
                if (adminReason === null) return; // cancelled
            } catch (e) {
                adminReason = '';
            }

            const formData = new FormData();
            formData.append('action', 'set_booking_status');
            formData.append('booking_id', bookingId);
            formData.append('status', status);
            if (adminReason && adminReason.trim() !== '') formData.append('admin_reason', adminReason.trim());

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Booking status updated');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error updating booking');
                    }
                });
        }

        function updatePaymentStatus(bookingId, paymentStatus) {
            if (!confirm('Set payment status to ' + paymentStatus + '?')) return;
            const formData = new FormData();
            formData.append('action', 'set_booking_status');
            formData.append('booking_id', bookingId);
            formData.append('payment_status', paymentStatus);

            fetch('storage_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Payment status updated');
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error updating payment status');
                    }
                });
        }

        // Admin booking modal
        function openBookingModal(bookingId) {
            const fd = new FormData();
            fd.append('action', 'get_booking');
            fd.append('booking_id', bookingId);

            fetch('storage_handler.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) { alert(res.message || 'Failed to load booking'); return; }
                    const b = res.data.booking;
                    document.getElementById('modalBookingRef').textContent = b.booking_reference || ('#' + b.id);
                    document.getElementById('modalStudentName').textContent = b.student_name || '';
                    document.getElementById('modalStudentContact').textContent = (b.email || '') + ' / ' + (b.phone || '');
                    document.getElementById('modalFacility').textContent = (b.facility_name || '') + ' - Unit ' + (b.unit_number || '');
                    document.getElementById('modalDates').textContent = b.start_date + ' → ' + b.end_date;
                    document.getElementById('modalDuration').textContent = (b.duration_months || '') + ' months';
                    document.getElementById('modalAmount').textContent = 'KSH ' + (b.total_cost || 0);
                    document.getElementById('modalMpesaName').textContent = b.mpesa_name || '';
                    document.getElementById('modalMpesaContact').textContent = b.mpesa_contact || '';
                    document.getElementById('modalMpesaTx').textContent = b.mpesa_transaction_id || '';
                    document.getElementById('modalNotes').textContent = b.notes || '';
                    // screenshot
                    const imgWrap = document.getElementById('modalMpesaScreenshot');
                    imgWrap.innerHTML = '';
                    if (b.mpesa_screenshot_path) {
                        const a = document.createElement('a');
                        a.href = b.mpesa_screenshot_path;
                        a.target = '_blank';
                        a.textContent = 'View Screenshot';
                        imgWrap.appendChild(a);
                    }

                    // notifications
                    const notList = document.getElementById('modalNotifications');
                    notList.innerHTML = '';
                    (res.data.notifications || []).forEach(n => {
                        const li = document.createElement('div');
                        li.style.borderBottom = '1px solid #eee';
                        li.style.padding = '6px 0';
                        li.innerHTML = '<strong>' + new Date(n.created_at).toLocaleString() + '</strong><div>' + n.message + '</div>';
                        notList.appendChild(li);
                    });

                    // show modal
                    document.getElementById('bookingModal').style.display = 'block';
                    document.getElementById('bookingModal').dataset.bookingId = bookingId;
                })
                .catch(err => { console.error(err); alert('Error loading booking'); });
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        function modalAction(actionType) {
            const bookingId = document.getElementById('bookingModal').dataset.bookingId;
            if (!bookingId) return;
            if (actionType === 'markPaid') {
                updatePaymentStatus(bookingId, 'paid');
                closeBookingModal();
                return;
            }
            // For status changes, reuse updateBookingStatus (which prompts for reason)
            updateBookingStatus(parseInt(bookingId,10), actionType);
            closeBookingModal();
        }
    </script>

    <!-- Booking Modal -->
    <div id="bookingModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:10000;">
        <div style="background:white; width:900px; max-width:95%; margin:auto; border-radius:8px; padding:20px; position:relative;">
            <button onclick="closeBookingModal()" style="position:absolute; right:12px; top:12px;">Close</button>
            <h3 id="modalBookingRef">Booking</h3>
            <div style="display:flex; gap:20px;">
                <div style="flex:1;">
                    <p><strong>Student:</strong> <span id="modalStudentName"></span></p>
                    <p><strong>Contact:</strong> <span id="modalStudentContact"></span></p>
                    <p><strong>Facility/Unit:</strong> <span id="modalFacility"></span></p>
                    <p><strong>Dates:</strong> <span id="modalDates"></span></p>
                    <p><strong>Duration:</strong> <span id="modalDuration"></span></p>
                    <p><strong>Amount:</strong> <span id="modalAmount"></span></p>
                    <p><strong>Notes:</strong> <div id="modalNotes"></div></p>
                </div>
                <div style="width:320px;">
                    <p><strong>MPesa Name:</strong> <span id="modalMpesaName"></span></p>
                    <p><strong>MPesa Contact:</strong> <span id="modalMpesaContact"></span></p>
                    <p><strong>MPesa Tx:</strong> <span id="modalMpesaTx"></span></p>
                    <p id="modalMpesaScreenshot"></p>
                </div>
            </div>
            <hr>
            <div>
                <h4>Notifications</h4>
                <div id="modalNotifications" style="max-height:180px; overflow:auto;"></div>
            </div>
            <div style="display:flex; gap:10px; margin-top:12px;">
                <button class="btn-secondary" onclick="modalAction('active')">Approve</button>
                <button class="btn-secondary" onclick="modalAction('cancelled')">Cancel</button>
                <button class="btn-secondary" onclick="modalAction('completed')">Complete</button>
                <button class="btn-secondary" onclick="modalAction('markPaid')">Mark Paid</button>
            </div>
        </div>
    </div>
</body>
</html>
