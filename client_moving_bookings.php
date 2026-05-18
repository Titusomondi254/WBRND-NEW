<?php
/**
 * Client - Moving Service Bookings
 * Clients view and track their moving service bookings
 */

session_start();
require_once 'config.php';

// Check client authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config_mover_system.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Client';
$userEmail = $_SESSION['email'] ?? '';

$conn = getMoverDatabaseConnection();
if (!$conn) {
    die("Database connection failed");
}

// Get client's bookings
$bookingsQuery = "
    SELECT * FROM mover_bookings 
    WHERE email = ? 
    ORDER BY created_at DESC
    LIMIT 50
";

$stmt = $conn->prepare($bookingsQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$bookings = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Statistics
$totalBookings = count($bookings);
$completedBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'completed'));
$pendingBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$inProgressBookings = count(array_filter($bookings, fn($b) => $b['status'] === 'in_progress'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Moving Service Bookings - Walbrand Movers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --info-color: #3498db;
            --border-radius: 8px;
        }

        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title h1 {
            margin: 0;
            font-weight: bold;
            font-size: 2rem;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn-book-now {
            background: white;
            color: var(--accent-color);
            padding: 12px 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn-book-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            color: var(--accent-color);
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
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--accent-color);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .bookings-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0;
            overflow: hidden;
        }

        .bookings-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .booking-item {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }

        .booking-item:hover {
            background: #f8f9fa;
        }

        .booking-item:last-child {
            border-bottom: none;
        }

        .booking-header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-ref {
            font-weight: bold;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-assigned {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-in_progress {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: start;
            gap: 10px;
        }

        .detail-icon {
            width: 24px;
            height: 24px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--primary-color);
        }

        .booking-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 15px;
            font-size: 0.85rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view {
            background: var(--info-color);
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-track {
            background: var(--success-color);
            color: white;
        }

        .btn-track:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .no-bookings {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-bookings i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-bookings p {
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .sidebar-nav {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-nav-item {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 8px;
            text-decoration: none;
            color: var(--primary-color);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-nav-item:hover {
            background: #f8f9fa;
            color: var(--accent-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php" style="color: white; font-weight: bold;">
                <i class="fas fa-home"></i> Walbrand Properties            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="client_dashboard.php" style="color: white;">
                            <i class="fas fa-home"></i> My Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="client_moving_bookings.php" style="color: white;">
                            <i class="fas fa-truck"></i> My Bookings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php" style="color: white;">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-truck"></i> My Moving Service Bookings</h1>
                    <p style="margin: 10px 0 0; opacity: 0.9;">Track and manage your moving service requests</p>
                </div>
                <div class="header-actions">
                    <a href="book_moving_service.php" class="btn-book-now">
                        <i class="fas fa-plus"></i> Book New Service
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid" style="padding: 0 20px;">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalBookings; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $pendingBookings; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $inProgressBookings; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completedBookings; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Bookings List -->
        <div class="bookings-container">
            <div class="bookings-header">
                <h3 style="margin: 0;">📋 Your Bookings</h3>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <i class="fas fa-inbox"></i>
                    <p>You don't have any moving service bookings yet.</p>
                    <a href="book_moving_service.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Book Your First Service
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-item">
                        <div class="booking-header-info">
                            <div>
                                <div class="booking-ref">#<?php echo $booking['id']; ?> - <?php echo htmlspecialchars($booking['house_type']); ?> Booking</div>
                                <small style="color: #999;">Booked on <?php echo date('d M, Y H:i', strtotime($booking['created_at'])); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo str_replace('_', '', $booking['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                            </span>
                        </div>

                        <div class="booking-details">
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">From</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($booking['location_from']); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">To</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($booking['location_to']); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-calendar"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Date & Time</div>
                                    <div class="detail-value"><?php echo date('d M, Y', strtotime($booking['moving_date'])); ?> at <?php echo date('H:i', strtotime($booking['moving_time'])); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-road"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Distance</div>
                                    <div class="detail-value"><?php echo $booking['distance_km']; ?> km</div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-dollar-sign"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Cost</div>
                                    <div class="detail-value" style="color: var(--success-color); font-size: 1.1rem;">KES <?php echo number_format($booking['total_cost'], 0); ?></div>
                                </div>
                            </div>

                            <?php if ($booking['assigned_group_id']): ?>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-users"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-label">Assigned Group</div>
                                        <div class="detail-value">Group #<?php echo $booking['assigned_group_id']; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="booking-actions">
                            <button class="btn-small btn-view" onclick="viewDetails(<?php echo $booking['id']; ?>)">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <?php if ($booking['status'] === 'assigned' || $booking['status'] === 'in_progress'): ?>
                                <button class="btn-small btn-track" onclick="trackJob()">
                                    <i class="fas fa-location-dot"></i> Track Job
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin: 40px 0; color: #999;">
            <p><small>For support or inquiries, please contact us at bookings@walbrandmovers.com</small></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewDetails(bookingId) {
            alert('Booking #' + bookingId + ' details view coming soon!');
        }

        function trackJob() {
            alert('Live tracking feature coming soon! Your mover will share their location when the job starts.');
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
