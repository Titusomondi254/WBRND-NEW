<?php
require_once 'config.php';
require_once 'helpers.php';
secure_session_start();

$user_id = intval($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    header('Location: login.php');
    exit();
}

$clients = [];
ensure_consultations_table_exists($conn);
if ($conn) {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            u.id, u.first_name, u.last_name, u.email, u.phone,
            COUNT(c.id) as total_consultations,
            SUM(CASE WHEN c.status = 'completed' THEN 1 ELSE 0 END) as successful
        FROM consultations c
        JOIN users u ON c.user_id = u.id
        JOIN properties p ON c.property_id = p.id
        WHERE p.seller_id = ?
        GROUP BY u.id
        ORDER BY total_consultations DESC
        LIMIT 100
    ");
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        $stmt->close();
    }
}

$consultation_status_counts = ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'other' => 0];
if ($conn) {
    $status_stmt = $conn->prepare("SELECT c.status, COUNT(*) AS total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? GROUP BY c.status");
    if ($status_stmt) {
        $status_stmt->bind_param('i', $user_id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        while ($row = $status_result->fetch_assoc()) {
            $status = strtolower(trim($row['status'] ?? 'other'));
            if (!isset($consultation_status_counts[$status])) {
                $status = 'other';
            }
            $consultation_status_counts[$status] = intval($row['total']);
        }
        $status_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clients - Agent Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #f97316; --border: #e2e8f0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f1f5f9; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; background: white; padding: 20px 24px; border-radius: 12px; border: 1px solid var(--border); }
        .header h1 { font-size: 1.8rem; color: #0f172a; }
        .header a { color: var(--primary); text-decoration: none; font-weight: 600; }
        .panel { background: white; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
        .panel-header { padding: 20px 24px; border-bottom: 1px solid var(--border); background: #f8fafc; }
        .panel-header h2 { font-size: 1.1rem; color: #0f172a; }
        .panel-body { padding: 24px; }
        .chart-panel { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; margin-bottom: 24px; }
        .chart-panel h3 { margin-bottom: 14px; color: #0f172a; }
        .chart-panel canvas { width: 500px !important; height: 500px !important; max-width: 100%; max-height: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid var(--border); font-size: 0.9rem; }
        td { padding: 12px; border-bottom: 1px solid var(--border); color: #1e293b; }
        tr:hover { background: #f8fafc; }
        .empty { text-align: center; color: #94a3b8; padding: 40px 24px; }
        @media (max-width: 900px) {
            .container { padding: 18px 16px; }
            .header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .panel-body { overflow-x: auto; }
            .panel { min-width: 0; }
            table { min-width: 640px; }
            .chart-panel { padding: 16px; }
            th, td { padding: 10px; }
        }
        @media (max-width: 640px) {
            .header h1 { font-size: 1.6rem; }
            .panel-header, .panel-body, .chart-panel { padding: 16px; }
            .chart-panel h3, .panel-header h2 { font-size: 1rem; }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>👥 Clients</h1>
                <p style="color: #64748b; margin-top: 4px;">Client satisfaction overview</p>
            </div>
            <a href="agent_dashboard.php">← Back to Dashboard</a>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>All Clients (<?= count($clients) ?>)</h2>
            </div>
            <div class="panel-body">
                <div class="chart-panel">
                    <h3>Consultation Status Distribution</h3>
                    <canvas id="clientsChart" width="500" height="500"></canvas>
                </div>
                <?php if (!empty($clients)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Total Consultations</th>
                                <th>Successful</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?= htmlspecialchars($client['first_name'] . ' ' . $client['last_name']) ?></td>
                                    <td><?= htmlspecialchars($client['email'] ?: 'N/A') ?></td>
                                    <td><?= htmlspecialchars($client['phone'] ?: 'N/A') ?></td>
                                    <td><?= $client['total_consultations'] ?></td>
                                    <td><?= $client['successful'] ?> (<?= $client['total_consultations'] > 0 ? round(($client['successful'] / $client['total_consultations']) * 100) : 0 ?>%)</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty">
                        <p style="font-size: 1.1rem;">No clients yet</p>
                        <p>Successful consultations will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        const clientStatusCounts = <?= json_encode(array_values($consultation_status_counts)) ?>;
        const clientStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($consultation_status_counts))) ?>;
        const clientsCtx = document.getElementById('clientsChart');
        if (clientsCtx) {
            new Chart(clientsCtx, {
                type: 'pie',
                data: {
                    labels: clientStatusLabels,
                    datasets: [{
                        data: clientStatusCounts,
                        backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b']
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
</body>
</html>
