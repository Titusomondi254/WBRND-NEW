<?php
/**
 * Agent Consultations Page
 * Lists property consultations for sellers/agents.
 */

require_once 'config.php';
require_once 'helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
if (!in_array($user_type, ['seller', 'agent'], true)) {
    header('Location: user_dashboard.php');
    exit();
}

$stats_stmt = $conn->prepare("SELECT
    COUNT(*) as total_consultations,
    SUM(CASE WHEN c.status = 'pending' THEN 1 ELSE 0 END) as pending_consultations,
    SUM(CASE WHEN c.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled_consultations,
    SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as completed_consultations
FROM consultations c
JOIN properties p ON c.property_id = p.id
WHERE p.seller_id = ?");
$stats_stmt->bind_param('i', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$consultations_stmt = $conn->prepare("SELECT
    c.id,
    c.consultation_type,
    c.status,
    c.scheduled_date,
    c.email,
    c.phone,
    c.created_at,
    c.issue_description,
    p.title as property_title,
    p.location,
    COALESCE(p.property_type, '') as property_type,
    COALESCE(u.first_name, '') as client_first_name,
    COALESCE(u.last_name, '') as client_last_name
FROM consultations c
JOIN properties p ON c.property_id = p.id
LEFT JOIN users u ON c.user_id = u.id
WHERE p.seller_id = ?
ORDER BY c.created_at DESC
LIMIT 100");
$consultations_stmt->bind_param('i', $user_id);
$consultations_stmt->execute();
$consultations_result = $consultations_stmt->get_result();
$consultations = [];
while ($row = $consultations_result->fetch_assoc()) {
    $consultations[] = $row;
}
$consultations_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultations - Walbrand Properties & Interiors</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            color: #1f2937;
            margin: 0;
            padding: 0;
        }
        .page-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .page-header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 4px solid#eef4fb;
        }
        .page-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        .page-header p {
            margin: 0.5rem 0 0;
            color: #6b7280;
        }
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background:#eef4fb;
            color: white;
            padding: 0.85rem 1.3rem;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 0.75rem;
            color: #6b7280;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: #111827;
        }
        .consultation-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
            border: 1px solid #e5e7eb;
        }
        .consultation-table th,
        .consultation-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }
        .consultation-table th {
            background: #f8fafc;
            color: #4b5563;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .consultation-table tbody tr:last-child td {
            border-bottom: none;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: capitalize;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-scheduled { background: #dbeafe; color: #1d4ed8; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .no-data {
            background: white;
            padding: 2rem;
            border-radius: 18px;
            text-align: center;
            color: #6b7280;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.05);
        }
        .small-text {
            display: block;
            color: #6b7280;
            margin-top: 0.35rem;
            font-size: 0.95rem;
        }
        @media (max-width: 900px) {
            .consultation-table th,
            .consultation-table td {
                padding: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-comments"></i> Consultations</h1>
                <p>Review scheduled and completed property consultations from your active listings.</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn-secondary"><i class="fas fa-home"></i> Back to Home</a>
                <a href="agent_dashboard.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Consultations</h3>
                <div class="stat-value"><?= number_format(intval($stats['total_consultations'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="stat-value"><?= number_format(intval($stats['pending_consultations'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <h3>Scheduled</h3>
                <div class="stat-value"><?= number_format(intval($stats['scheduled_consultations'] ?? 0)) ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed</h3>
                <div class="stat-value"><?= number_format(intval($stats['completed_consultations'] ?? 0)) ?></div>
            </div>
        </div>

        <?php if (count($consultations) === 0): ?>
            <div class="no-data">
                <h2>No consultations found</h2>
                <p>There are no consultation requests for your properties yet. Check back once clients book viewings or consultations.</p>
            </div>
        <?php else: ?>
            <table class="consultation-table">
                <thead>
                    <tr>
                        <th>Consultation</th>
                        <th>Property</th>
                        <th>Client</th>
                        <th>Schedule</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($consultations as $consultation): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $consultation['consultation_type']))) ?></strong>
                                <span class="small-text"><?= htmlspecialchars($consultation['issue_description'] ?: 'No description provided') ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($consultation['property_title'] ?: 'Untitled property') ?></strong>
                                <span class="small-text"><?= htmlspecialchars(trim($consultation['property_type'] . ' in ' . $consultation['location'])) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars(trim($consultation['client_first_name'] . ' ' . $consultation['client_last_name'])) ?></strong>
                                <span class="small-text"><?= htmlspecialchars($consultation['email'] ?: 'No email') ?> | <?= htmlspecialchars($consultation['phone'] ?: 'No phone') ?></span>
                            </td>
                            <td>
                                <strong><?= $consultation['scheduled_date'] ? date('M d, Y H:i', strtotime($consultation['scheduled_date'])) : 'TBD' ?></strong>
                                <span class="small-text">Requested: <?= date('M d, Y', strtotime($consultation['created_at'])) ?></span>
                            </td>
                            <td>
                                <span class="status-pill status-<?= htmlspecialchars($consultation['status'] ?: 'pending') ?>">
                                    <?= htmlspecialchars(ucfirst($consultation['status'] ?: 'pending')) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
