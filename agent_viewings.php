<?php require_once 'config.php'; require_once 'helpers.php'; secure_session_start(); $user_id = intval($_SESSION['user_id'] ?? 0); if ($user_id <= 0) header('Location: login.php'); $viewings = []; ensure_consultations_table_exists($conn); if ($conn) { $stmt = $conn->prepare("SELECT c.id, COALESCE(u.first_name, 'Unknown') as name, c.consultation_type, c.status, c.created_at, c.scheduled_date FROM consultations c LEFT JOIN users u ON c.user_id = u.id JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? AND c.consultation_type = 'property_viewing' ORDER BY c.created_at DESC LIMIT 100"); if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $viewings[] = $row; $stmt->close(); } }

$viewing_status_counts = ['completed' => 0, 'pending' => 0, 'scheduled' => 0, 'other' => 0];
foreach ($viewings as $viewing) {
    $status = strtolower(trim($viewing['status'] ?? 'other'));
    if (!isset($viewing_status_counts[$status])) {
        $status = 'other';
    }
    $viewing_status_counts[$status]++;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Viewings - Agent Dashboard</title><link rel="stylesheet" href="styles.css"><style>:root{--primary:#f97316;--border:#e2e8f0;}*{margin:0;padding:0;box-sizing:border-box;}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f1f5f9;}.container{max-width:1200px;margin:0 auto;padding:24px;}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;background:white;padding:20px 24px;border-radius:12px;border:1px solid var(--border);}.header h1{font-size:1.8rem;color:#0f172a;}.header a{color:var(--primary);text-decoration:none;font-weight:600;}.panel{background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden;}.panel-header{padding:20px 24px;border-bottom:1px solid var(--border);background:#f8fafc;}.panel-header h2{font-size:1.1rem;color:#0f172a;}.panel-body{padding:24px;}table{width:100%;border-collapse:collapse;}th{background:#f8fafc;padding:12px;text-align:left;font-weight:600;color:#475569;border-bottom:2px solid var(--border);font-size:0.9rem;}td{padding:12px;border-bottom:1px solid var(--border);color:#1e293b;}tr:hover{background:#f8fafc;}.status-tag{display:inline-block;padding:6px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;text-transform:capitalize;}.status-completed{background:#dcfce7;color:#166534;}.status-pending{background:#fef3c7;color:#92400e;}.status-scheduled{background:#e0e7ff;color:#3730a3;}.chart-panel{background:white;border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:24px;}.chart-panel h3{margin-bottom:14px;color:#0f172a;}.chart-panel canvas{width:500px!important;height:500px!important;max-width:100%;max-height:100%;}.empty{text-align:center;color:#94a3b8;padding:40px 24px;}
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
</head><body><div class="container"><div class="header"><div><h1>👀 Viewings</h1><p style="color:#64748b;margin-top:4px;">Your viewing assignments</p></div><a href="agent_dashboard.php">← Back to Dashboard</a></div><div class="panel"><div class="panel-header"><h2>All Viewings (<?= count($viewings) ?>)</h2></div><div class="panel-body"><div class="chart-panel"><h3>Viewing Status Summary</h3><canvas id="viewingsChart" width="500" height="500"></canvas></div><?php if (!empty($viewings)): ?><table><thead><tr><th>Client Name</th><th>Status</th><th>Scheduled Date</th><th>Booked</th></tr></thead><tbody><?php foreach ($viewings as $v): ?><tr><td><?= htmlspecialchars($v['name']) ?></td><td><span class="status-tag status-<?= $v['status'] ?>"><?= ucfirst($v['status']) ?></span></td><td><?= $v['scheduled_date'] ? date('M j, Y', strtotime($v['scheduled_date'])) : 'Not set' ?></td><td><?= date('M j, Y', strtotime($v['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="empty"><p style="font-size:1.1rem;">No viewings yet</p><p>Scheduled viewings will appear here.</p></div><?php endif; ?></div></div></div>
    <script>
        const viewingStatusCounts = <?= json_encode(array_values($viewing_status_counts)) ?>;
        const viewingStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($viewing_status_counts))) ?>;
        const viewingsCtx = document.getElementById('viewingsChart');
        if (viewingsCtx) {
            new Chart(viewingsCtx, {
                type: 'doughnut',
                data: {
                    labels: viewingStatusLabels,
                    datasets: [{
                        data: viewingStatusCounts,
                        backgroundColor: ['#22c55e', '#f59e0b', '#3b82f6', '#64748b']
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
</body></html>
