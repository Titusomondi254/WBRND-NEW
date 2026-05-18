<?php require_once 'config.php'; require_once 'helpers.php'; secure_session_start(); $user_id = intval($_SESSION['user_id'] ?? 0); if ($user_id <= 0) header('Location: login.php'); $tasks = []; ensure_consultations_table_exists($conn); if ($conn) { $stmt = $conn->prepare("SELECT c.id, c.consultation_type, c.status, COUNT(*) as total FROM consultations c JOIN properties p ON c.property_id = p.id WHERE p.seller_id = ? GROUP BY c.consultation_type, c.status ORDER BY consultation_type"); if ($stmt) { $stmt->bind_param('i', $user_id); $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $tasks[] = $row; $stmt->close(); } }

$task_status_counts = ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'other' => 0];
foreach ($tasks as $task_item) {
    $status = strtolower(trim($task_item['status'] ?? 'other'));
    if (!isset($task_status_counts[$status])) {
        $status = 'other';
    }
    $task_status_counts[$status] += intval($task_item['total']);
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Tasks - Agent Dashboard</title><link rel="stylesheet" href="styles.css"><style>:root{--primary:#f97316;--border:#e2e8f0;}*{margin:0;padding:0;box-sizing:border-box;}body{font-family:-apple-system,sans-serif;background:#f1f5f9;}.container{max-width:1200px;margin:0 auto;padding:24px;}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;background:white;padding:20px 24px;border-radius:12px;border:1px solid var(--border);}.header h1{font-size:1.8rem;color:#0f172a;}.header a{color:var(--primary);text-decoration:none;font-weight:600;}.panel{background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden;}.panel-header{padding:20px 24px;border-bottom:1px solid var(--border);background:#f8fafc;}.panel-header h2{font-size:1.1rem;color:#0f172a;}.panel-body{padding:24px;}table{width:100%;border-collapse:collapse;}th{background:#f8fafc;padding:12px;text-align:left;font-weight:600;color:#475569;border-bottom:2px solid var(--border);font-size:0.9rem;}td{padding:12px;border-bottom:1px solid var(--border);color:#1e293b;}tr:hover{background:#f8fafc;}.status-tag{display:inline-block;padding:6px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;text-transform:capitalize;}.status-completed{background:#dcfce7;color:#166534;}.status-pending{background:#fef3c7;color:#92400e;}.chart-panel{background:white;border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:24px;}.chart-panel h3{margin-bottom:14px;color:#0f172a;}.chart-panel canvas{width:500px!important;height:500px!important;max-width:100%;max-height:100%;}.empty{text-align:center;color:#94a3b8;padding:40px 24px;}
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
        </style><script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script></head><body><div class="container"><div class="header"><div><h1>✅ Tasks</h1><p style="color:#64748b;margin-top:4px;">Task completion by category</p></div><a href="agent_dashboard.php">← Back to Dashboard</a></div><div class="panel"><div class="panel-header"><h2>All Tasks</h2></div><div class="panel-body"><div class="chart-panel"><h3>Task Status Overview</h3><canvas id="tasksChart" width="500" height="500"></canvas></div><?php if (!empty($tasks)): ?><table><thead><tr><th>Category</th><th>Status</th><th>Count</th></tr></thead><tbody><?php foreach ($tasks as $t): ?><tr><td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $t['consultation_type']))) ?></td><td><span class="status-tag status-<?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td><td><?= $t['total'] ?></td></tr><?php endforeach; ?></tbody></table><?php else: ?><div class="empty"><p>No tasks yet</p></div><?php endif; ?></div></div></div><script>const taskStatusCounts = <?= json_encode(array_values($task_status_counts)) ?>;const taskStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($task_status_counts))) ?>;const tasksCtx = document.getElementById('tasksChart');if (tasksCtx) {new Chart(tasksCtx, {type: 'bar',data: {labels: taskStatusLabels,datasets: [{label: 'Tasks',data: taskStatusCounts,backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#64748b']} ]},options: {responsive: true,maintainAspectRatio: false,plugins: {legend: {display: false}},scales: {y: {beginAtZero: true,precision: 0}}}});} </script></body></html>
