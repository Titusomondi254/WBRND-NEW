<?php require_once 'config.php'; require_once 'config_mover_system.php'; secure_session_start(); if (intval($_SESSION['user_id'] ?? 0) <= 0) header('Location: login.php'); $groups = []; $mover_conn = getMoverDatabaseConnection(); if ($mover_conn) { $stmt = $mover_conn->prepare("SELECT mg.id, mg.group_name, COUNT(mb.id) as total_bookings, SUM(CASE WHEN mb.status='completed' THEN 1 ELSE 0 END) as completed, AVG(mr.rating) as avg_rating, COUNT(DISTINCT mr.id) as feedback_count FROM mover_groups mg LEFT JOIN mover_bookings mb ON mg.id = mb.assigned_group_id LEFT JOIN mover_reviews mr ON mb.id = mr.booking_id GROUP BY mg.id ORDER BY avg_rating DESC LIMIT 50"); if ($stmt) { $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) $groups[] = $row; $stmt->close(); } 
        $delivery_status_counts = ['completed' => 0, 'pending' => 0, 'cancelled' => 0, 'rescheduled' => 0, 'other' => 0];
        $status_stmt = $mover_conn->prepare("SELECT status, COUNT(*) AS total FROM mover_bookings GROUP BY status");
        if ($status_stmt) {
            $status_stmt->execute();
            $status_result = $status_stmt->get_result();
            while ($row = $status_result->fetch_assoc()) {
                $status = strtolower(trim($row['status'] ?? 'other'));
                if (!isset($delivery_status_counts[$status])) {
                    $status = 'other';
                }
                $delivery_status_counts[$status] = intval($row['total']);
            }
            $status_stmt->close();
        }
    } ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Delivery Groups - Dashboard</title><link rel="stylesheet" href="styles.css"><style>:root{--primary:#f97316;--border:#e2e8f0;}*{margin:0;padding:0;box-sizing:border-box;}body{font-family:-apple-system,sans-serif;background:#f1f5f9;}.container{max-width:1200px;margin:0 auto;padding:24px;}.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;background:white;padding:20px 24px;border-radius:12px;border:1px solid var(--border);}.header h1{font-size:1.8rem;color:#0f172a;}.header a{color:var(--primary);text-decoration:none;font-weight:600;}.panel{background:white;border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:24px;}.panel-header{padding:20px 24px;border-bottom:1px solid var(--border);background:#f8fafc;}.panel-header h2{font-size:1.1rem;color:#0f172a;}.panel-body{padding:24px;}.group-card{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px;}.group-card h3{color:#0f172a;margin-bottom:8px;}.group-stat{display:inline-block;margin-right:16px;margin-bottom:8px;}.group-stat label{color:#64748b;font-size:0.85rem;}.group-stat value{display:block;font-weight:700;color:#0f172a;}.chart-panel{background:white;border:1px solid var(--border);border-radius:12px;padding:18px;margin-bottom:24px;}.chart-panel h3{margin-bottom:14px;color:#0f172a;}.chart-panel canvas{width:500px!important;height:500px!important;max-width:100%;max-height:100%;}.empty{text-align:center;color:#94a3b8;padding:40px 24px;}
        @media (max-width: 900px) {
            .container { padding: 18px 16px; }
            .header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .panel-body { overflow-x: auto; }
            .panel { min-width: 0; }
            .group-card { padding: 16px; }
            .group-stat { display: block; width: 100%; margin-right: 0; }
            .group-stat value { display: block; margin-top: 6px; }
            th, td { padding: 10px; }
        }
        @media (max-width: 640px) {
            .header h1 { font-size: 1.6rem; }
            .panel-header, .panel-body, .chart-panel { padding: 16px; }
            .chart-panel h3, .panel-header h2 { font-size: 1rem; }
        }
        </style><script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script></head><body><div class="container"><div class="header"><div><h1>🚚 Delivery Groups</h1><p style="color:#64748b;margin-top:4px;">Group performance & rankings</p></div><a href="agent_dashboard.php">← Back to Dashboard</a></div><div class="panel"><div class="panel-header"><h2>All Delivery Groups (<?= count($groups) ?>)</h2></div><div class="panel-body"><div class="chart-panel"><h3>Booking Status Overview</h3><canvas id="deliveryGroupsChart" width="500" height="500"></canvas></div><?php if (!empty($groups)): ?><?php foreach ($groups as $g): ?><div class="group-card"><h3><?= htmlspecialchars($g['group_name']) ?></h3><div class="group-stat"><label>Total Bookings</label><value><?= $g['total_bookings'] ?></value></div><div class="group-stat"><label>Completed</label><value><?= $g['completed'] ?></value></div><div class="group-stat"><label>Rating</label><value><?= $g['avg_rating'] ? round($g['avg_rating'], 1) . '/5' : 'N/A' ?></value></div><div class="group-stat"><label>Feedbacks</label><value><?= $g['feedback_count'] ?></value></div></div><?php endforeach; ?><?php else: ?><div class="empty"><p>No delivery groups yet</p></div><?php endif; ?></div></div></div><script>const deliveryStatusCounts = <?= json_encode(array_values($delivery_status_counts)) ?>;const deliveryStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($delivery_status_counts))) ?>;const deliveryGroupsCtx = document.getElementById('deliveryGroupsChart');if (deliveryGroupsCtx) {new Chart(deliveryGroupsCtx, {type: 'bar',data: {labels: deliveryStatusLabels,datasets: [{label: 'Booking Status',data: deliveryStatusCounts,backgroundColor: ['#22c55e', '#f59e0b', '#ef4444', '#3b82f6', '#64748b']} ]},options: {responsive: true,maintainAspectRatio: false,plugins: {legend: {display: false}},scales: {y: {beginAtZero: true,precision: 0}}}});} </script></body></html>
