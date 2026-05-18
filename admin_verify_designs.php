<?php
/**
 * Admin Interior Design Verification
 * Admin page to approve/reject interior design submissions
 */

require_once 'admin_auth.php';
require_once 'helpers.php';

$user_id = $_SESSION['admin_id'];

// Handle approval/rejection actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $design_id = intval($_POST['design_id']);
    $action = sanitize($_POST['action']);
    $admin_notes = sanitize($_POST['admin_notes'] ?? '');

    if ($action === 'approve') {
        $status = 'approved';
        $message = "Design approved successfully!";
    } elseif ($action === 'reject') {
        $status = 'rejected';
        $message = "Design rejected.";
    } else {
        $_SESSION['error_message'] = "Invalid action.";
        header('Location: admin_verify_designs.php');
        exit();
    }

    // Update design status
    $stmt = $conn->prepare("UPDATE interior_designs SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $design_id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = $message;

        // Log admin action
        logUserAction($user_id, 'design_' . $action, $design_id, null, null, [
            'admin_notes' => $admin_notes,
            'status' => $status
        ]);

        // Send notification to designer
        $design_stmt = $conn->prepare("
            SELECT d.title, u.email, u.first_name, u.last_name
            FROM interior_designs d
            JOIN users u ON d.agent_id = u.id
            WHERE d.id = ?
        ");
        $design_stmt->bind_param("i", $design_id);
        $design_stmt->execute();
        $design_info = $design_stmt->get_result()->fetch_assoc();

        if ($design_info) {
            $subject = "Interior Design " . ucfirst($action) . " - Walbrand Properties Marketplace";
            $body = "Dear {$design_info['first_name']} {$design_info['last_name']},\n\n";
            $body .= "Your interior design submission '{$design_info['title']}' has been " . ($action === 'approve' ? 'approved' : 'rejected') . ".\n\n";
            if ($action === 'reject' && !empty($admin_notes)) {
                $body .= "Admin Notes: {$admin_notes}\n\n";
            }
            $body .= "Best regards,\nWalbrand Properties Marketplace & Interiors Admin Team";

            // Note: Email sending would be implemented here
        }
    } else {
        $_SESSION['error_message'] = "Failed to update design status.";
    }

    $stmt->close();
    header('Location: admin_verify_designs.php');
    exit();
}

// Get pending designs with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
$total_stmt = $conn->prepare("SELECT COUNT(*) as total FROM interior_designs WHERE status = 'pending_review'");
$total_stmt->execute();
$total_designs = $total_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_designs / $per_page);

// Get designs
$designs_stmt = $conn->prepare("
    SELECT d.*, u.first_name, u.last_name, u.email, u.phone, u.user_type
    FROM interior_designs d
    JOIN users u ON d.agent_id = u.id
    WHERE d.status = 'pending_review'
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
");
$designs_stmt->bind_param("ii", $per_page, $offset);
$designs_stmt->execute();
$designs = $designs_stmt->get_result();

// Debug: Show all designs
$all_designs_stmt = $conn->prepare("SELECT d.*, u.first_name, u.last_name FROM interior_designs d JOIN users u ON d.agent_id = u.id ORDER BY d.created_at DESC LIMIT 10");
$all_designs_stmt->execute();
$all_designs = $all_designs_stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interior Design Verification - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fb;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .design-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            background: #fafafa;
        }
        .design-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .design-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        .design-meta {
            color: #666;
            font-size: 0.9rem;
        }
        .design-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .detail-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #eee;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .detail-value {
            color: #333;
            margin-top: 5px;
        }
        .video-preview {
            margin: 15px 0;
            max-width: 300px;
        }
        .video-preview video {
            width: 100%;
            border-radius: 5px;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-reject {
            background: #dc3545;
            color: white;
        }
        .btn-view {
            background: #007bff;
            color: white;
        }
        .pagination {
            text-align: center;
            margin-top: 20px;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            text-decoration: none;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .pagination a.active {
            background: #007bff;
            color: white;
        }
        .stats {
            display: flex;
            justify-content: space-around;
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .admin-notes {
            margin-top: 15px;
        }
        .admin-notes textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            resize: vertical;
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-check-circle"></i> Interior Design Verification</h1>
            <p>Review and approve interior design submissions</p>
        </div>

        <div class="content">
            <!-- DEBUG SECTION -->
            <div style="background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px;">
                <h3>Debug Information</h3>
                <p><strong>Total designs in database:</strong> 
                <?php 
                $debug_count = $conn->query("SELECT COUNT(*) as count FROM interior_designs")->fetch_assoc()['count'];
                echo $debug_count;
                ?></p>
                <p><strong>Pending review designs:</strong> <?php echo $total_designs; ?></p>
                
                <?php if ($debug_count > 0): ?>
                <p><strong>All designs (last 5):</strong></p>
                <ul>
                <?php
                $debug_designs = $conn->query("SELECT id, title, status, created_at FROM interior_designs ORDER BY created_at DESC LIMIT 5");
                while ($d = $debug_designs->fetch_assoc()) {
                    echo "<li>ID: {$d['id']}, Title: {$d['title']}, Status: {$d['status']}, Created: {$d['created_at']}</li>";
                }
                ?>
                </ul>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <i class="fas fa-check"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_designs; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <?php
                $approved_count = $conn->query("SELECT COUNT(*) as count FROM interior_designs WHERE status = 'approved'")->fetch_assoc()['count'];
                $rejected_count = $conn->query("SELECT COUNT(*) as count FROM interior_designs WHERE status = 'rejected'")->fetch_assoc()['count'];
                ?>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $approved_count; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <?php if ($designs->num_rows > 0): ?>
                <?php while ($design = $designs->fetch_assoc()): ?>
                    <div class="design-card">
                        <div class="design-header">
                            <div>
                                <div class="design-title"><?php echo htmlspecialchars($design['title']); ?></div>
                                <div class="design-meta">
                                    By: <?php echo htmlspecialchars($design['first_name'] . ' ' . $design['last_name']); ?>
                                    (<?php echo ucfirst($design['user_type']); ?>) •
                                    Submitted: <?php echo date('M j, Y g:i A', strtotime($design['created_at'])); ?>
                                </div>
                            </div>
                            <div>
                                <a href="design_details.php?id=<?php echo $design['id']; ?>" class="btn btn-view" target="_blank">
                                    <i class="fas fa-eye"></i> Preview
                                </a>
                            </div>
                        </div>

                        <div class="design-details">
                            <div class="detail-item">
                                <div class="detail-label">Project Type</div>
                                <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $design['project_type'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Property Size</div>
                                <div class="detail-value"><?php echo number_format($design['property_size_sqm'], 1); ?> sqm</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Bedrooms</div>
                                <div class="detail-value"><?php echo $design['bedrooms']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Bathrooms</div>
                                <div class="detail-value"><?php echo $design['bathrooms']; ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Interior Cost</div>
                                <div class="detail-value">KES <?php echo number_format($design['renovation_cost_interior'], 0); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Duration</div>
                                <div class="detail-value"><?php echo htmlspecialchars($design['project_duration']); ?></div>
                            </div>
                        </div>

                        <?php if (!empty($design['video_file']) || !empty($design['video_url'])): ?>
                            <div class="video-preview">
                                <?php if (!empty($design['video_file']) && file_exists(__DIR__ . '/' . $design['video_file'])): ?>
                                    <video controls style="max-width: 100%; height: auto;">
                                        <source src="<?php echo htmlspecialchars($design['video_file']); ?>" type="video/mp4">
                                        Your browser does not support the video tag.
                                    </video>
                                <?php elseif (!empty($design['video_url'])): ?>
                                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; border: 1px solid #dee2e6;">
                                        <strong>🎥 Video URL:</strong><br>
                                        <a href="<?php echo htmlspecialchars($design['video_url']); ?>" target="_blank" style="color: #007bff; text-decoration: none;">
                                            <?php echo htmlspecialchars($design['video_url']); ?>
                                        </a>
                                    </div>
                                <?php elseif (!empty($design['video_file'])): ?>
                                    <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border: 1px solid #ffeaa7;">
                                        <strong>📁 Video File:</strong> <?php echo htmlspecialchars(basename($design['video_file'])); ?><br>
                                        <small style="color: #856404;">File path: <?php echo htmlspecialchars($design['video_file']); ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="admin-notes">
                            <button type="button" class="btn btn-approve" onclick="approveDesign(<?php echo $design['id']; ?>)">
                                <i class="fas fa-check"></i> Approve
                            </button>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="design_id" value="<?php echo $design['id']; ?>">
                                <input type="hidden" name="action" value="reject">
                                <textarea name="admin_notes" placeholder="Rejection reason (optional)" rows="2"></textarea>
                                <button type="submit" class="btn btn-reject" onclick="return confirm('Reject this design?')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 50px; color: #666;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: #28a745;"></i>
                    <h3>No designs pending review</h3>
                    <p>All submissions have been processed.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        async function approveDesign(designId) {
            const confirmed = await showConfirm('Approve this design?');
            if (confirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                const input1 = document.createElement('input');
                input1.type = 'hidden';
                input1.name = 'design_id';
                input1.value = designId;
                const input2 = document.createElement('input');
                input2.type = 'hidden';
                input2.name = 'action';
                input2.value = 'approve';
                form.appendChild(input1);
                form.appendChild(input2);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>