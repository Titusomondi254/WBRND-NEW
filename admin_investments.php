<?php
session_start();
require_once 'config.php';
require_once 'admin_auth.php';

// Handle GET requests for details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'details') {
    $project_id = intval($_GET['id'] ?? 0);
    $stmt = $conn->prepare("SELECT p.*, 
                           CONCAT_WS(' ', u.first_name, u.last_name) as developer_name,
                           u.email as developer_email,
                           u.phone as developer_phone,
                           (SELECT COUNT(*) FROM offplan_investments WHERE project_id = p.id) as total_investors,
                           (SELECT SUM(amount_committed) FROM offplan_investments WHERE project_id = p.id AND status IN ('payment_received','confirmed','active','completed')) as total_raised,
                           (SELECT GROUP_CONCAT(file_path) FROM offplan_project_documents WHERE project_id = p.id LIMIT 5) as documents
                           FROM offplan_projects p
                           JOIN users u ON p.developer_id = u.id
                           WHERE p.id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $project = $result->fetch_assoc();
        $stmt->close();
        
        if ($project):
            // Get project images
            $images_stmt = $conn->prepare("SELECT * FROM offplan_project_images WHERE project_id = ? ORDER BY display_order ASC, created_at ASC");
            $images = [];
            if ($images_stmt) {
                $images_stmt->bind_param('i', $project_id);
                $images_stmt->execute();
                $images_result = $images_stmt->get_result();
                while ($img = $images_result->fetch_assoc()) {
                    $images[] = $img;
                }
                $images_stmt->close();
            }
?>
<div style="padding: 24px;">
    <h3 style="color: #ea580c; margin-bottom: 16px;"><?php echo htmlspecialchars($project['project_name']); ?></h3>
    
    <!-- Project Images Gallery -->
    <?php if (!empty($images)): ?>
    <div style="margin-bottom: 24px;">
        <h4 style="color: #ea580c; margin-bottom: 12px;">Project Images & Videos</h4>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
            <?php foreach ($images as $image): ?>
                <?php
                    $extension = strtolower(pathinfo($image['image_path'], PATHINFO_EXTENSION));
                    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $video_extensions = ['mp4', 'avi', 'mov'];
                    $is_photo = in_array($extension, $image_extensions, true) || $image['image_type'] === 'photo';
                    $is_video = in_array($extension, $video_extensions, true) || $image['image_type'] === 'video';
                ?>
                <div style="position: relative;">
                    <?php if ($is_photo): ?>
                        <img src="<?php echo htmlspecialchars($image['image_path']); ?>" 
                             alt="Photo" 
                             style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; cursor: pointer;"
                             onclick="window.open('<?php echo htmlspecialchars($image['image_path']); ?>', '_blank')">
                    <?php elseif ($is_video): ?>
                        <div style="width: 100%; height: 150px; background: #000; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #ddd;"
                             onclick="window.open('<?php echo htmlspecialchars($image['image_path']); ?>', '_blank')">
                            <div style="text-align: center; color: white;">
                                <i class="fas fa-video" style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                                <small>Click to view video</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="width: 100%; height: 150px; background: #f3f4f6; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 1px solid #ddd;"
                             onclick="window.open('<?php echo htmlspecialchars($image['image_path']); ?>', '_blank')">
                            <div style="text-align: center; color: #333;">
                                <i class="fas fa-file" style="font-size: 2rem; margin-bottom: 8px; display: block;"></i>
                                <small>Unsupported media</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div style="position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.7); color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem;">
                        <?php echo $is_photo ? 'Photo' : ($is_video ? 'Video' : ucfirst(str_replace('_', ' ', $image['image_type']))); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div style="margin-bottom: 24px; padding: 16px; background: #f9f9f9; border-radius: 8px; text-align: center; color: #666;">
        <i class="fas fa-images" style="font-size: 2rem; margin-bottom: 8px;"></i>
        <p>No images or videos uploaded for this project</p>
    </div>
    <?php endif; ?>
    
    <!-- Supporting Documents -->
    <?php
    $docs_stmt = $conn->prepare("SELECT * FROM offplan_project_documents WHERE project_id = ? ORDER BY created_at DESC");
    $documents = [];
    if ($docs_stmt) {
        $docs_stmt->bind_param('i', $project_id);
        $docs_stmt->execute();
        $docs_result = $docs_stmt->get_result();
        while ($doc = $docs_result->fetch_assoc()) {
            $documents[] = $doc;
        }
        $docs_stmt->close();
    }
    ?>
    <?php if (!empty($documents)): ?>
    <div style="margin-bottom: 24px;">
        <h4 style="color: #ea580c; margin-bottom: 12px;">Supporting Documents</h4>
        <div style="background: #f9f9f9; border-radius: 8px; overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f0f0f0; border-bottom: 1px solid #ddd;">
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Document Type</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">File Name</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; color: #333;">Status</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; color: #333;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documents as $doc): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 12px; color: #555;">
                            <span style="display: inline-block; background: #e8f4f8; color: #0066cc; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; color: #555;">
                            <?php echo htmlspecialchars(basename($doc['file_path'])); ?>
                        </td>
                        <td style="padding: 12px; color: #555;">
                            <span style="display: inline-block; background: <?php echo $doc['verification_status'] === 'approved' ? '#dcfce7' : ($doc['verification_status'] === 'rejected' ? '#fee2e2' : '#fef3c7'); ?>; color: <?php echo $doc['verification_status'] === 'approved' ? '#166534' : ($doc['verification_status'] === 'rejected' ? '#991b1b' : '#92400e'); ?>; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                                <?php echo ucfirst(str_replace('_', ' ', $doc['verification_status'])); ?>
                            </span>
                        </td>
                        <td style="padding: 12px; text-align: center;">
                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" 
                               target="_blank" 
                               style="display: inline-block; background: #0066cc; color: white; padding: 6px 12px; border-radius: 4px; text-decoration: none; font-size: 0.85rem;"
                               title="Download document">
                                <i class="fas fa-download" style="margin-right: 4px;"></i>View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Developer</p>
            <p style="margin: 0; color: #555;"><?php echo htmlspecialchars($project['developer_name']); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Email</p>
            <p style="margin: 0; color: #555;"><?php echo htmlspecialchars($project['developer_email']); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Phone</p>
            <p style="margin: 0; color: #555;"><?php echo htmlspecialchars($project['developer_phone']); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Type</p>
            <p style="margin: 0; color: #555;"><?php echo ucfirst(str_replace('_', ' ', $project['project_type'])); ?></p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Location</p>
            <p style="margin: 0; color: #555;"><?php echo htmlspecialchars($project['location']); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Development Stage</p>
            <p style="margin: 0; color: #555;"><?php echo ucfirst(str_replace('_', ' ', $project['development_stage'])); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Price per Unit</p>
            <p style="margin: 0; color: #555;">KSH <?php echo number_format($project['price_per_unit'] ?? 0, 2); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Investment Goal</p>
            <p style="margin: 0; color: #555;">KSH <?php echo number_format($project['investment_goal'] ?? 0, 2); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Total Raised</p>
            <p style="margin: 0; color: #555;">KSH <?php echo number_format($project['total_raised'] ?? 0, 2); ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Expected ROI</p>
            <p style="margin: 0; color: #555;"><?php echo htmlspecialchars($project['expected_roi']); ?></p>
        </div>
    </div>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Total Units</p>
            <p style="margin: 0; color: #555;"><?php echo $project['total_units']; ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Available Units</p>
            <p style="margin: 0; color: #555;"><?php echo $project['available_units']; ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Total Investors</p>
            <p style="margin: 0; color: #555;"><?php echo $project['total_investors']; ?></p>
        </div>
        <div>
            <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Status</p>
            <p style="margin: 0; color: #555;">
                <span style="display: inline-block; background: <?php echo $project['verification_status'] === 'verified' ? '#dcfce7' : '#fef3c7'; ?>; color: <?php echo $project['verification_status'] === 'verified' ? '#166534' : '#92400e'; ?>; padding: 4px 8px; border-radius: 4px;">
                    <?php echo ucfirst(str_replace('_', ' ', $project['verification_status'])); ?>
                </span>
            </p>
        </div>
    </div>
    
    <?php if (!empty($project['project_summary'])): ?>
    <div style="margin-bottom: 20px;">
        <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Project Summary</p>
        <p style="margin: 0; color: #555; line-height: 1.6;"><?php echo htmlspecialchars($project['project_summary']); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($project['investment_highlights'])): ?>
    <div>
        <p style="margin: 0 0 4px 0; font-weight: 600; color: #ea580c;">Investment Highlights</p>
        <p style="margin: 0; color: #555; line-height: 1.6;"><?php echo htmlspecialchars($project['investment_highlights']); ?></p>
    </div>
    <?php endif; ?>
</div>
<?php
        endif;
    }
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $project_id = intval($_POST['project_id'] ?? 0);

    if ($action === 'verify' && $project_id > 0) {
        $stmt = $conn->prepare("UPDATE offplan_projects SET verification_status = 'verified', project_status = 'active' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $project_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Project verified successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to verify project']);
            }
            $stmt->close();
        }
        exit;
    } elseif ($action === 'suspend' && $project_id > 0) {
        $stmt = $conn->prepare("UPDATE offplan_projects SET project_status = 'closed' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $project_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Project suspended successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to suspend project']);
            }
            $stmt->close();
        }
        exit;
    } elseif ($action === 'delete' && $project_id > 0) {
        $stmt = $conn->prepare("DELETE FROM offplan_projects WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $project_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Project deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete project']);
            }
            $stmt->close();
        }
        exit;
    } elseif ($action === 'reject' && $project_id > 0) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        $stmt = $conn->prepare("UPDATE offplan_projects SET verification_status = 'rejected' WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $project_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Project rejected']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to reject project']);
            }
            $stmt->close();
        }
        exit;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_clauses[] = 'verification_status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

if ($type_filter !== 'all') {
    $where_clauses[] = 'project_type = ?';
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($search)) {
    $where_clauses[] = '(project_name LIKE ? OR location LIKE ?)';
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get projects
$query = "SELECT p.*, 
          CONCAT_WS(' ', u.first_name, u.last_name) as developer_name,
          u.email as developer_email,
          u.phone as developer_phone,
          (SELECT COUNT(*) FROM offplan_investments WHERE project_id = p.id) as total_investors,
          (SELECT SUM(amount_committed) FROM offplan_investments WHERE project_id = p.id AND status IN ('payment_received','confirmed','active','completed')) as total_raised
          FROM offplan_projects p
          JOIN users u ON p.developer_id = u.id
          $where_clause
          ORDER BY p.created_at DESC";

$stmt = $conn->prepare($query);
$projects = [];

if ($types && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    $stmt->close();
}

// Get stats
$stats_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN verification_status = 'pending_review' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN project_status = 'closed' THEN 1 ELSE 0 END) as suspended
                FROM offplan_projects";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Projects Management - Walbrand Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #f97316;
            --text-color: #ea580c;
            --light-bg: #fff7ed;
            --light-card: #fed7aa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            color: var(--text-color);
        }

        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: var(--text-color);
            color: white;
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 24px 28px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 24px;
            text-align: center;
        }

        .sidebar-header h3 {
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 16px;
        }

        .sidebar-menu li {
            margin-bottom: 10px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: background 0.2s;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 28px 30px;
            background: var(--light-bg);
        }

        .header {
            background: #eef4fb;
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 28px;
            box-shadow: 0 10px 30px rgba(249,115,22,0.2);
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.95;
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--light-card);
            border: 1px solid #f97316;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(249,115,22,0.1);
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-color);
            margin-bottom: 8px;
        }

        .stat-card .stat-label {
            font-size: 0.9rem;
            color: var(--text-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .filters {
            background: var(--light-card);
            border: 1px solid #f97316;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #f97316;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            color: var(--text-color);
            min-width: 180px;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #eef4fb;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(249,115,22,0.3);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.85rem;
        }

        .projects-table {
            background: white;
            border: 1px solid #f97316;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(249,115,22,0.1);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--light-card);
            border-bottom: 2px solid #f97316;
        }

        th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-color);
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid #f97316;
            color: var(--text-color);
            font-size: 0.95rem;
        }

        tbody tr:hover {
            background: #fff7ed;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-verified {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-active {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-closed {
            background: #f3f4f6;
            color: #374151;
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 48px 24px;
            background: white;
            border: 2px dashed #f97316;
            border-radius: 12px;
            color: var(--text-color);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h3 {
            margin-bottom: 8px;
            font-size: 1.2rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 28px;
            max-width: 600px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f97316;
        }

        .modal-header h2 {
            margin: 0;
            color: var(--text-color);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color);
        }

        .form-group input,
        .form-group textarea {
            padding: 12px;
            border: 1px solid #f97316;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            color: var(--text-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f97316;
        }

        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .main-content {
                margin-left: 0;
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group input,
            .filter-group select {
                min-width: 100%;
            }

            .table-responsive {
                overflow-x: auto;
            }

            table {
                font-size: 0.85rem;
            }

            th, td {
                padding: 12px 8px;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h3>Walbrand Admin</h3>
                <p style="font-size: 0.85rem; margin-top: 4px;">Investment Management</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin_control_panel.php">
                    <i class="fas fa-chart-line"></i> Dashboard</a></li>
                <li><a href="admin_dashboard.php">
                    <i class="fas fa-check-circle"></i> Properties</a></li>
                <li><a href="admin_investments.php" class="active">
                    <i class="fas fa-project-diagram"></i> Investments</a></li>
                <li><a href="admin_users.php">
                    <i class="fas fa-users"></i> Users</a></li>
                <li><a href="admin_fee_management.php">
                    <i class="fas fa-money-bill"></i> Fees</a></li>
                <li><a href="admin_settings.php">
                    <i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="admin_logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-project-diagram"></i> Investment Projects</h1>
                <p>Manage, verify, and monitor off-plan investment projects</p>
            </div>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
                    <div class="stat-label">Total Projects</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pending'] ?? 0; ?></div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['verified'] ?? 0; ?></div>
                    <div class="stat-label">Verified</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['rejected'] ?? 0; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label>Status</label>
                    <select onchange="filterProjects()">
                        <option value="">All Status</option>
                        <option value="pending_review" <?php echo $status_filter === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select onchange="filterProjects()">
                        <option value="">All Types</option>
                        <option value="residential" <?php echo $type_filter === 'residential' ? 'selected' : ''; ?>>Residential</option>
                        <option value="commercial" <?php echo $type_filter === 'commercial' ? 'selected' : ''; ?>>Commercial</option>
                        <option value="mixed_use" <?php echo $type_filter === 'mixed_use' ? 'selected' : ''; ?>>Mixed Use</option>
                    </select>
                </div>
                <div class="filter-group" style="flex: 1;">
                    <label>Search</label>
                    <input type="text" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" placeholder="Project name or location...">
                </div>
                <button class="btn btn-primary" onclick="filterProjects()">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>

            <!-- Projects Table -->
            <?php if (!empty($projects)): ?>
                <div class="projects-table">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Developer</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Investors</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($project['project_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($project['developer_name']); ?></td>
                                        <td><?php echo ucfirst(str_replace('_', ' ', $project['project_type'])); ?></td>
                                        <td><?php echo htmlspecialchars($project['location']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $project['verification_status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $project['verification_status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $project['total_investors'] ?? 0; ?></td>
                                        <td>
                                            <div class="actions">
                                                <button class="btn btn-primary btn-small" onclick="viewDetails(<?php echo $project['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if ($project['verification_status'] === 'pending_review'): ?>
                                                    <button class="btn btn-success btn-small" onclick="verifyProject(<?php echo $project['id']; ?>)">
                                                        <i class="fas fa-check"></i> Verify
                                                    </button>
                                                    <button class="btn btn-danger btn-small" onclick="rejectProject(<?php echo $project['id']; ?>)">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                <?php elseif ($project['project_status'] === 'active'): ?>
                                                    <button class="btn btn-warning btn-small" onclick="suspendProject(<?php echo $project['id']; ?>)">
                                                        <i class="fas fa-pause"></i> Suspend
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-danger btn-small" onclick="deleteProject(<?php echo $project['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No projects found</h3>
                    <p>No investment projects match your search criteria</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- View Details Modal -->
    <div class="modal" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Project Details</h2>
                <button class="modal-close" onclick="closeModal('detailsModal')">&times;</button>
            </div>
            <div id="detailsContent"></div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal" id="rejectModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reject Project</h2>
                <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form onsubmit="submitReject(event)">
                <input type="hidden" id="rejectProjectId" value="">
                <div class="form-group">
                    <label>Rejection Reason</label>
                    <textarea id="rejectionReason" required placeholder="Explain why this project is being rejected..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" style="background: #e5e7eb; color: #374151;" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Project</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
    <script>
        function filterProjects() {
            const status = document.querySelector('.filters select:nth-of-type(1)').value;
            const type = document.querySelector('.filters select:nth-of-type(2)').value;
            const search = document.getElementById('searchInput').value;

            let url = 'admin_investments.php?';
            if (status) url += 'status=' + encodeURIComponent(status) + '&';
            if (type) url += 'type=' + encodeURIComponent(type) + '&';
            if (search) url += 'search=' + encodeURIComponent(search);

            window.location.href = url;
        }

        function viewDetails(projectId) {
            fetch('admin_investments.php?action=details&id=' + projectId)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('detailsContent').innerHTML = html;
                    document.getElementById('detailsModal').classList.add('active');
                });
        }

        function verifyProject(projectId) {
            Swal.fire({
                title: 'Verify Project?',
                text: 'This project will be visible to all users',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Verify',
                confirmButtonColor: '#10b981'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'verify');
                    formData.append('project_id', projectId);

                    fetch('admin_investments.php', {
                        method: 'POST',
                        body: formData
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            showSuccess(data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showError(data.message);
                        }
                    });
                }
            });
        }

        function suspendProject(projectId) {
            Swal.fire({
                title: 'Suspend Project?',
                text: 'This project will be hidden from users',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Suspend',
                confirmButtonColor: '#f59e0b'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'suspend');
                    formData.append('project_id', projectId);

                    fetch('admin_investments.php', {
                        method: 'POST',
                        body: formData
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            showSuccess(data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showError(data.message);
                        }
                    });
                }
            });
        }

        function rejectProject(projectId) {
            document.getElementById('rejectProjectId').value = projectId;
            document.getElementById('rejectionReason').value = '';
            document.getElementById('rejectModal').classList.add('active');
        }

        function submitReject(event) {
            event.preventDefault();
            const projectId = document.getElementById('rejectProjectId').value;
            const reason = document.getElementById('rejectionReason').value;

            const formData = new FormData();
            formData.append('action', 'reject');
            formData.append('project_id', projectId);
            formData.append('rejection_reason', reason);

            fetch('admin_investments.php', {
                method: 'POST',
                body: formData
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    showSuccess(data.message);
                    closeModal('rejectModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showError(data.message);
                }
            });
        }

        function deleteProject(projectId) {
            Swal.fire({
                title: 'Delete Project?',
                text: 'This action cannot be undone!',
                icon: 'error',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                confirmButtonColor: '#ef4444'
            }).then(result => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete');
                    formData.append('project_id', projectId);

                    fetch('admin_investments.php', {
                        method: 'POST',
                        body: formData
                    }).then(r => r.json()).then(data => {
                        if (data.success) {
                            showSuccess(data.message);
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showError(data.message);
                        }
                    });
                }
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = event.target.closest('.modal');
            if (event.target.classList && event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
