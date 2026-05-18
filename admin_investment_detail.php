<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

$investment_id = intval($_GET['id'] ?? 0);
if ($investment_id <= 0) {
    echo '<div style="padding: 24px; color: #b91c1c; background: #fee2e2; border-radius: 12px; margin: 24px;">Invalid investment ID.</div>';
    exit();
}

$stmt = $conn->prepare(
    "SELECT i.*, p.project_name, p.location, p.price_per_unit, p.minimum_investment, p.available_units,
            CONCAT(u.first_name, ' ', u.last_name) AS investor_name, u.email AS investor_email, u.phone AS investor_phone
     FROM offplan_investments i
     LEFT JOIN offplan_projects p ON i.project_id = p.id
     LEFT JOIN users u ON i.investor_id = u.id
     WHERE i.id = ? LIMIT 1"
);

if (!$stmt) {
    echo '<div style="padding: 24px; color: #b91c1c; background: #fee2e2; border-radius: 12px; margin: 24px;">Unable to load investment details.</div>';
    exit();
}

$stmt->bind_param('i', $investment_id);
$stmt->execute();
$result = $stmt->get_result();
$investment = $result->fetch_assoc();
$stmt->close();

if (!$investment) {
    echo '<div style="padding: 24px; color: #b91c1c; background: #fee2e2; border-radius: 12px; margin: 24px;">Investment record not found.</div>';
    exit();
}

function format_currency_value($value) {
    return 'KES ' . number_format((float) $value, 2);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment #<?= intval($investment['id']) ?> — Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        .container { max-width: 920px; margin: 2rem auto; background: white; border-radius: 16px; box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08); overflow: hidden; }
        .header { background: #111827; color: white; padding: 1.5rem 2rem; }
        .header h1 { margin: 0; font-size: 1.5rem; }
        .content { padding: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1rem; }
        .card strong { display: block; margin-bottom: 0.5rem; color: #111827; }
        .card span { color: #475569; }
        .section { margin-bottom: 1.5rem; }
        .section h2 { margin: 0 0 0.75rem; color: #111827; font-size: 1.15rem; }
        .button-row { display: flex; flex-wrap: wrap; gap: 0.75rem; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem 1.2rem; border-radius: 10px; border: none; cursor: pointer; font-weight: 700; text-decoration: none; }
        .btn-secondary { background: #eef2ff; color: #1e3a8a; }
        .btn-primary { background: #111827; color: white; }
        .detail-block { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 1.2rem; }
        .detail-block p { margin: 0.7rem 0; color: #374151; line-height: 1.6; }
        .label { color: #0f172a; font-weight: 700; }
        .value { color: #334155; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Investment #<?= intval($investment['id']) ?> Details</h1>
        </div>
        <div class="content">
            <div class="section">
                <h2>Investment Summary</h2>
                <div class="detail-block">
                    <p><span class="label">Project:</span> <span class="value"><?= htmlspecialchars($investment['project_name'] ?? 'Unknown Project') ?></span></p>
                    <p><span class="label">Investor:</span> <span class="value"><?= htmlspecialchars($investment['investor_name'] ?? 'Unknown Investor') ?></span></p>
                    <p><span class="label">Status:</span> <span class="value"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $investment['status']))) ?></span></p>
                    <p><span class="label">Units Committed:</span> <span class="value"><?= intval($investment['units_committed']) ?></span></p>
                    <p><span class="label">Amount Committed:</span> <span class="value"><?= format_currency_value($investment['amount_committed']) ?></span></p>
                    <p><span class="label">Investor Email:</span> <span class="value"><?= htmlspecialchars($investment['investor_email'] ?? '-') ?></span></p>
                    <p><span class="label">Investor Phone:</span> <span class="value"><?= htmlspecialchars($investment['investor_phone'] ?? '-') ?></span></p>
                    <p><span class="label">Submitted:</span> <span class="value"><?= date('F j, Y H:i', strtotime($investment['created_at'])) ?></span></p>
                </div>
            </div>

            <div class="grid">
                <div class="card">
                    <strong>Project Location</strong>
                    <span><?= htmlspecialchars($investment['location'] ?? 'N/A') ?></span>
                </div>
                <div class="card">
                    <strong>Price Per Unit</strong>
                    <span><?= format_currency_value($investment['price_per_unit'] ?? 0) ?></span>
                </div>
                <div class="card">
                    <strong>Available Units</strong>
                    <span><?= intval($investment['available_units']) ?></span>
                </div>
            </div>

            <div class="section">
                <h2>Investment Note</h2>
                <div class="detail-block">
                    <p><?= nl2br(htmlspecialchars($investment['investor_note'] ?? 'No note provided.')) ?></p>
                </div>
            </div>

            <div class="button-row">
                <a href="admin_investments.php" class="btn btn-secondary">Back to Investments</a>
                <a href="admin_control_panel.php?view=notifications" class="btn btn-primary">Back to Notifications</a>
            </div>
        </div>
    </div>
</body>
</html>
