<?php
require_once 'config.php';
require_once 'helpers.php';

secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: login.php');
    exit();
}

$user_stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param('i', $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc() ?: [];
$user_stmt->close();

$properties = [];
if ($conn) {
    $props_stmt = $conn->prepare("
        SELECT 
            id,
            property_type AS title,
            location,
            price,
            status,
            created_at
        FROM properties
        WHERE seller_id = ?
        ORDER BY created_at DESC
        LIMIT 100
    ");
    if ($props_stmt) {
        $props_stmt->bind_param('i', $user_id);
        $props_stmt->execute();
        $result = $props_stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $properties[] = $row;
        }
        $props_stmt->close();
    }
}

$property_status_counts = ['active' => 0, 'pending' => 0, 'sold' => 0, 'other' => 0];
foreach ($properties as $prop) {
    $status = strtolower(trim($prop['status'] ?? 'other'));
    if (!isset($property_status_counts[$status])) {
        $status = 'other';
    }
    $property_status_counts[$status]++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Properties - Agent Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #f97316; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; }

        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 32px;
            background: white;
            padding: 20px 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .header h1 { font-size: 1.8rem; color: #0f172a; }
        .header a { color: var(--primary); text-decoration: none; font-weight: 600; }

        .panel { background: white; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .panel-header { padding: 20px 24px; border-bottom: 1px solid var(--border); background: #f8fafc; }
        .panel-header h2 { font-size: 1.1rem; color: #0f172a; }
        .panel-body { padding: 24px; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid var(--border); font-size: 0.9rem; }
        td { padding: 12px; border-bottom: 1px solid var(--border); color: #1e293b; }
        tr:hover { background: #f8fafc; }

        .status-tag { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
        .status-active { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-sold { background: #fee2e2; color: #991b1b; }

        .chart-panel { display: flex; justify-content: center; align-items: center; margin-bottom: 24px; }
        .chart-panel canvas { width: 500px !important; height: 500px !important; max-width: 100%; max-height: 100%; }

        .chart-panel { display: flex; justify-content: center; align-items: center; margin-bottom: 24px; }
        .chart-panel canvas { width: 500px !important; height: 500px !important; max-width: 100%; max-height: 100%; }

        .empty { text-align: center; color: #94a3b8; padding: 40px 24px; }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🏠 My Properties</h1>
                <p style="color: #64748b; margin-top: 4px;">Manage and track your property listings</p>
            </div>
            <a href="agent_dashboard.php">← Back to Dashboard</a>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>All Properties (<?= count($properties) ?>)</h2>
            </div>
            <div class="panel-body">
                <div class="chart-panel">
                    <h3>Property Status Breakdown</h3>
                    <canvas id="propertiesChart" width="500" height="500"></canvas>
                </div>
                <?php if (!empty($properties)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Property</th>
                                <th>Location</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Listed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($properties as $prop): ?>
                                <tr>
                                    <td><?= htmlspecialchars($prop['title'] ?? 'Untitled') ?></td>
                                    <td><?= htmlspecialchars($prop['location'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($prop['price'] ? 'Ksh.' . number_format($prop['price']) : 'N/A') ?></td>
                                    <td><span class="status-tag status-<?= $prop['status'] ?? 'pending' ?>"><?= ucfirst($prop['status'] ?? 'Pending') ?></span></td>
                                    <td><?= date('M j, Y', strtotime($prop['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">
                        <p style="font-size: 1.1rem;">No properties yet</p>
                        <p>Upload your first property to get started!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const propertyStatusCounts = <?= json_encode(array_values($property_status_counts)) ?>;
        const propertyStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($property_status_counts))) ?>;
        const propertiesCtx = document.getElementById('propertiesChart');
        if (propertiesCtx) {
            new Chart(propertiesCtx, {
                type: 'bar',
                data: {
                    labels: propertyStatusLabels,
                    datasets: [{
                        label: 'Properties',
                        data: propertyStatusCounts,
                        backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, precision: 0 } }
                }
            });
        }
    </script>
</body>
</html>
