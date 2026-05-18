<?php
/**
 * Agent - Moving Service Jobs Dashboard
 * Agents/Team members view their assigned moving jobs
 */

session_start();
require_once 'config.php';

// Check agent/employee authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config_mover_system.php';

$userId = $_SESSION['user_id'];
$userName = $_SESSION['name'] ?? 'Agent';
$userEmail = $_SESSION['email'] ?? '';

$conn = getMoverDatabaseConnection();
if (!$conn) {
    die("Database connection failed");
}

// Find which group this agent belongs to
$groupQuery = "SELECT mgm.id, mgm.group_id, mg.group_name FROM mover_group_members mgm
               JOIN mover_groups mg ON mgm.group_id = mg.id
               WHERE mgm.employee_contact = ? LIMIT 1";
$stmt = $conn->prepare($groupQuery);
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();
$groupMember = $result->fetch_assoc();
$stmt->close();

$groupId = $groupMember['group_id'] ?? 0;
$groupName = $groupMember['group_name'] ?? 'Not Assigned';

if ($groupId === 0) {
    // Find by group_id if stored in session
    $groupId = $_GET['group_id'] ?? 0;
}

// Get group info
$groupInfo = null;
if ($groupId > 0) {
    $gQuery = "SELECT id, group_name FROM mover_groups WHERE id = ?";
    $gStmt = $conn->prepare($gQuery);
    $gStmt->bind_param("i", $groupId);
    $gStmt->execute();
    $groupInfo = $gStmt->get_result()->fetch_assoc();
    $gStmt->close();
}

// Get jobs assigned to this group
$jobsQuery = "
    SELECT 
        mn.id as notification_id,
        mn.booking_id,
        mn.message,
        mn.is_read,
        mn.created_at as assigned_at,
        mb.*
    FROM mover_notifications mn
    JOIN mover_bookings mb ON mn.booking_id = mb.id
    WHERE mn.group_id = ?
    ORDER BY mn.is_read ASC, mn.created_at DESC
    LIMIT 100
";

$jobs = [];
if ($groupId > 0) {
    $stmt = $conn->prepare($jobsQuery);
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $jobs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get team members
$membersQuery = "SELECT id, employee_name, employee_contact FROM mover_group_members WHERE group_id = ? ORDER BY employee_name ASC";
$members = [];
if ($groupId > 0) {
    $stmt = $conn->prepare($membersQuery);
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $members = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Statistics
$totalJobs = count($jobs);
$pendingJobs = count(array_filter($jobs, fn($j) => $j['status'] === 'pending'));
$assignedJobs = count(array_filter($jobs, fn($j) => $j['status'] === 'assigned'));
$inProgressJobs = count(array_filter($jobs, fn($j) => $j['status'] === 'in_progress'));
$completedJobs = count(array_filter($jobs, fn($j) => $j['status'] === 'completed'));
$unreadNotifications = count(array_filter($jobs, fn($j) => !$j['is_read']));

$job_status_counts = [
    'pending' => $pendingJobs,
    'assigned' => $assignedJobs,
    'in_progress' => $inProgressJobs,
    'completed' => $completedJobs,
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walbrand Movers - Agent Jobs Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
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

        .header-badge {
            background: var(--success-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-left: 15px;
            display: inline-block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .jobs-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0;
            overflow: hidden;
        }

        .jobs-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .job-card {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            transition: all 0.3s ease;
            border-left: 4px solid #ddd;
        }

        .job-card.unread {
            background: #f0f8ff;
            border-left: 4px solid var(--accent-color);
        }

        .job-card:hover {
            background: #f8f9fa;
        }

        .job-card:last-child {
            border-bottom: none;
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .job-ref {
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

        .job-details {
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
            width: 28px;
            height: 28px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 3px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--primary-color);
        }

        .client-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }

        .client-header {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .client-detail {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 0.9rem;
        }

        .job-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-action {
            padding: 10px 15px;
            font-size: 0.85rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-start {
            background: var(--warning-color);
            color: white;
        }

        .btn-start:hover {
            background: #e0880e;
            transform: translateY(-2px);
        }

        .btn-complete {
            background: var(--success-color);
            color: white;
        }

        .btn-complete:hover {
            background: #229954;
            transform: translateY(-2px);
        }

        .btn-contact {
            background: var(--info-color);
            color: white;
        }

        .btn-contact:hover {
            background: #2980b9;
        }

        .team-section {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .team-member {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .team-member:last-child {
            border-bottom: none;
        }

        .member-avatar {
            width: 40px;
            height: 40px;
            background: var(--accent-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--primary-color);
        }

        .member-contact {
            font-size: 0.85rem;
            color: #666;
        }

        .no-jobs {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-jobs i {
            font-size: 3rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .chart-card h4 {
            margin-bottom: 18px;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php" style="color: white; font-weight: bold; font-size: 1.5rem;">
                <i class="fas fa-truck" style="margin-right: 10px;"></i>
                <span style="color: #ffd700;">Walbrand</span> Movers
                <div style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px;">Professional Moving Services</div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#" style="color: white;">
                            <i class="fas fa-briefcase"></i> My Jobs
                            <?php if ($unreadNotifications > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $unreadNotifications; ?></span>
                            <?php endif; ?>
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
            <div class="brand-section" style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: white; font-size: 2.5rem; margin: 0; font-weight: bold;">
                    <i class="fas fa-truck" style="margin-right: 15px;"></i>
                    <span style="color: #ffd700;">Walbrand</span> Movers
                </h2>
                <p style="color: #e0e0e0; font-size: 1.1rem; margin: 10px 0 0; opacity: 0.9;">
                    Kenya's Premier Moving & Relocation Services
                </p>
            </div>
            <h1>
                <i class="fas fa-tasks"></i> My Moving Jobs
                <?php if ($groupInfo): ?>
                    <span class="header-badge"><?php echo htmlspecialchars($groupInfo['group_name']); ?></span>
                <?php endif; ?>
            </h1>
            <p style="margin: 10px 0 0; opacity: 0.9;">Manage your assigned moving service jobs</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid" style="padding: 0 20px; margin-bottom: 40px;">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalJobs; ?></div>
                <div class="stat-label">Total Jobs</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $assignedJobs; ?></div>
                <div class="stat-label">Assigned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $inProgressJobs; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $completedJobs; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <div class="chart-card">
            <h4><i class="fas fa-chart-pie"></i> Job Status Breakdown</h4>
            <canvas id="jobsStatusChart" height="180"></canvas>
        </div>

        <!-- Jobs List -->
        <div class="jobs-container">
            <div class="jobs-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0;"><i class="fas fa-list"></i> Assigned Jobs</h3>
                    <div style="text-align: right;">
                        <div style="font-size: 0.9rem; color: #666; margin-bottom: 2px;">Powered by</div>
                        <div style="font-weight: bold; color: var(--accent-color); font-size: 1.1rem;">
                            <i class="fas fa-truck" style="margin-right: 5px;"></i>Walbrand Movers
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($jobs)): ?>
                <div class="no-jobs">
                    <i class="fas fa-inbox"></i>
                    <p>No jobs assigned yet. Check back soon!</p>
                </div>
            <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                    <div class="job-card <?php echo !$job['is_read'] ? 'unread' : ''; ?>">
                        <div class="job-header">
                            <div>
                                <div class="job-ref">Job #<?php echo $job['booking_id']; ?></div>
                                <small style="color: #999;">Assigned <?php echo date('d M, Y H:i', strtotime($job['assigned_at'])); ?></small>
                            </div>
                            <span class="status-badge status-<?php echo str_replace('_', '', $job['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $job['status'])); ?>
                            </span>
                        </div>

                        <!-- Client Info -->
                        <div class="client-info">
                            <div class="client-header">👤 Client Information</div>
                            <div class="client-detail">
                                <span><?php echo htmlspecialchars($job['client_name']); ?></span>
                            </div>
                            <div class="client-detail">
                                <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($job['phone']); ?></span>
                            </div>
                            <div class="client-detail">
                                <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($job['email']); ?></span>
                            </div>
                        </div>

                        <!-- Job Details -->
                        <div class="job-details">
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-location-arrow"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">From</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($job['location_from']); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-location-arrow"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">To</div>
                                    <div class="detail-value"><?php echo htmlspecialchars($job['location_to']); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-calendar"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Date & Time</div>
                                    <div class="detail-value"><?php echo date('d M, Y', strtotime($job['moving_date'])); ?> at <?php echo date('H:i', strtotime($job['moving_time'])); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-road"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Distance</div>
                                    <div class="detail-value"><?php echo $job['distance_km']; ?> km</div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-home"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">House Type</div>
                                    <div class="detail-value"><?php echo str_replace('_', ' ', strtoupper($job['house_type'])); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-dollar-sign"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Cost</div>
                                    <div class="detail-value" style="color: var(--success-color); font-size: 1.1rem;">KES <?php echo number_format($job['total_cost'], 0); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="job-actions">
                            <?php if ($job['status'] === 'assigned'): ?>
                                <button class="btn-action btn-start" onclick="updateStatus(<?php echo $job['booking_id']; ?>, 'in_progress')">
                                    <i class="fas fa-play"></i> Start Job
                                </button>
                            <?php elseif ($job['status'] === 'in_progress'): ?>
                                <button class="btn-action btn-complete" onclick="updateStatus(<?php echo $job['booking_id']; ?>, 'completed')">
                                    <i class="fas fa-check"></i> Mark Complete
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn-action btn-contact" onclick="contactClient('<?php echo htmlspecialchars($job['phone']); ?>')">
                                <i class="fas fa-phone"></i> Call Client
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Team Section -->
        <?php if (!empty($members)): ?>
            <div class="team-section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h4 style="margin: 0;"><i class="fas fa-users"></i> Team Members (<?php echo count($members); ?>/<?php echo MOVER_GROUP_SIZE; ?>)</h4>
                    <div style="text-align: right;">
                        <div style="font-size: 0.8rem; color: #666;">Walbrand Movers</div>
                        <div style="font-size: 0.7rem; color: #999;">Professional Team</div>
                    </div>
                </div>
                <?php foreach ($members as $member): ?>
                    <div class="team-member">
                        <div class="member-avatar"><?php echo strtoupper(substr($member['employee_name'], 0, 1)); ?></div>
                        <div class="member-info">
                            <div class="member-name"><?php echo htmlspecialchars($member['employee_name']); ?></div>
                            <div class="member-contact"><?php echo htmlspecialchars($member['employee_contact']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <script>
        const jobStatusCounts = <?= json_encode(array_values($job_status_counts)) ?>;
        const jobStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($job_status_counts))) ?>;
        const jobsStatusCtx = document.getElementById('jobsStatusChart');
        if (jobsStatusCtx) {
            new Chart(jobsStatusCtx, {
                type: 'doughnut',
                data: {
                    labels: jobStatusLabels,
                    datasets: [{
                        data: jobStatusCounts,
                        backgroundColor: ['#f59e0b', '#3b82f6', '#0ea5e9', '#22c55e']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    </script>

    <!-- Footer -->
    <footer style="background: #2c3e50; color: white; padding: 30px 0; margin-top: 50px;">
        <div class="container-fluid" style="text-align: center;">
            <div style="margin-bottom: 20px;">
                <h3 style="color: #ffd700; margin: 0; font-size: 1.8rem;">
                    <i class="fas fa-truck" style="margin-right: 10px;"></i>
                    Walbrand Movers
                </h3>
                <p style="margin: 5px 0; opacity: 0.8;">Professional Moving & Relocation Services</p>
            </div>
            <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap; margin-bottom: 20px;">
                <div>
                    <i class="fas fa-phone" style="margin-right: 8px;"></i>
                    <strong>Call Us:</strong> +254 113906162
                </div>
                <div>
                    <i class="fas fa-envelope" style="margin-right: 8px;"></i>
                    <strong>Email:</strong> info@walbrandmovers.com
                </div>
                <div>
                    <i class="fas fa-map-marker-alt" style="margin-right: 8px;"></i>
                    <strong>Serving:</strong> Nairobi & Beyond
                </div>
            </div>
            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px; opacity: 0.7;">
                <p style="margin: 0; font-size: 0.9rem;">
                    © 2024 Walbrand Movers. All rights reserved. | Trusted Moving Services Across Kenya
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus(bookingId, status) {
            if (confirm('Are you sure you want to update this job status?')) {
                // In production, this would call an API endpoint
                alert('Job status updated to: ' + status);
                location.reload();
            }
        }

        function contactClient(phone) {
            alert('Calling ' + phone + '\n\nIn production, this would integrate with your communication system.');
        }
    </script>
</body>
</html>

<?php $conn->close(); ?>
