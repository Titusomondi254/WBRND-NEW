<?php
/**
 * Browse Interior Designs
 * Client-facing page to browse and filter interior designs
 */

session_start();
require_once 'config.php';
require_once 'helpers.php';

// Track user activity
track_user_activity();

// Get filter parameters
$budget_min = floatval($_GET['budget_min'] ?? 0);
$budget_max = floatval($_GET['budget_max'] ?? 999999999);
$bedrooms = intval($_GET['bedrooms'] ?? 0);
$property_type = sanitize($_GET['property_type'] ?? '');
$sort_by = sanitize($_GET['sort'] ?? 'created_at');
$sort_order = sanitize($_GET['order'] ?? 'DESC');

// Build query
$where_conditions = ["d.status = 'approved'"];
$params = [];
$param_types = '';

if ($budget_min > 0) {
    $where_conditions[] = "(d.renovation_cost_interior + d.renovation_cost_exterior) >= ?";
    $params[] = $budget_min;
    $param_types .= 'd';
}

if ($budget_max < 999999999) {
    $where_conditions[] = "(d.renovation_cost_interior + d.renovation_cost_exterior) <= ?";
    $params[] = $budget_max;
    $param_types .= 'd';
}

if ($bedrooms > 0) {
    $where_conditions[] = "d.bedrooms >= ?";
    $params[] = $bedrooms;
    $param_types .= 'i';
}

if (!empty($property_type)) {
    $where_conditions[] = "d.project_type = ?";
    $params[] = $property_type;
    $param_types .= 's';
}

// Validate sort parameters
$valid_sort_fields = ['created_at', 'renovation_cost_interior', 'bedrooms', 'views_count'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_fields)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

// Build final query
$query = "
    SELECT d.*,
           (SELECT di.image_path FROM design_images di WHERE di.design_id = d.id ORDER BY di.sort_order ASC, di.id ASC LIMIT 1) AS image_path,
           (SELECT COUNT(*) FROM design_images di WHERE di.design_id = d.id) AS image_count,
           u.name as agent_name,
           u.email as agent_email,
           u.phone as agent_phone,
           COALESCE(AVG(dr.rating), 0) as avg_rating,
           COUNT(dr.id) as review_count
    FROM interior_designs d
    JOIN users u ON d.agent_id = u.id
    LEFT JOIN design_reviews dr ON d.id = dr.design_id
    WHERE " . implode(' AND ', $where_conditions) . "
    GROUP BY d.id
    ORDER BY d.{$sort_by} {$sort_order}
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$designs = [];
while ($row = $result->fetch_assoc()) {
    $designs[] = $row;
}
$stmt->close();

// Get filter stats for UI
$stats_query = "
    SELECT
        COUNT(*) as total_designs,
        MIN(renovation_cost_interior + renovation_cost_exterior) as min_budget,
        MAX(renovation_cost_interior + renovation_cost_exterior) as max_budget,
        MIN(bedrooms) as min_bedrooms,
        MAX(bedrooms) as max_bedrooms
    FROM interior_designs
    WHERE status = 'approved'
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Log search activity
if (is_logged_in()) {
    logUserAction($_SESSION['user_id'], 'browse_designs', null, null, null, [
        'budget_min' => $budget_min,
        'budget_max' => $budget_max,
        'bedrooms' => $bedrooms,
        'property_type' => $property_type,
        'results_count' => count($designs)
    ]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Interior Designs - Walbrand Properties Marketplace & Interiors</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f7f8fb;
            color: #1f2937;
            min-height: 100vh;
        }

        .designs-container {
            max-width: 1100px;
            margin: 1.5rem auto;
            padding: 0 1rem 2rem;
        }

        .filters-section {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .btn-filter {
            background: linear-gradient(135deg, #ff7b00 0%, #ff9c3d 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,123,0,0.3);
        }

        .btn-home {
            background: #ffffff;
            color: #333;
            padding: 0.75rem 1.2rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-home:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        .btn-clear {
            background: #6c757d;
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: #5a6268;
        }

        .designs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .design-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            aspect-ratio: 1;
            min-height: 200px;
        }

        .design-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .card-media {
            width: 150px;
            height: 150px;
            border-radius: 12px;
            overflow: hidden;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .card-media img,
        .card-media iframe,
        .card-media video {
            width: 150px;
            height: 150px;
            object-fit: cover;
            display: block;
        }

        .media-placeholder {
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #ff7b00 0%, #ff9c3d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .media-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: rgba(0, 0, 0, 0.55);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }

        .design-info {
            padding: 0;
            background: transparent;
            position: static;
            width: calc(100% - 50px);
        }

        .design-title {
            font-size: 1rem;
            font-weight: bold;
            color: #111827;
            margin-bottom: 0.25rem;
            display: block;
            text-decoration: none;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .design-cost {
            font-size: 0.9rem;
            font-weight: 700;
            color:#eef4fb;
            margin: 0;
        }

        /* Modal Styles */
        .design-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .design-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            max-width: 800px;
            max-height: 90vh;
            width: 90%;
            overflow-y: auto;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #111827;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #6b7280;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-media {
            margin-bottom: 1.5rem;
        }

        .modal-media img,
        .modal-media video,
        .modal-media iframe {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 10px;
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: #f3f4f6;
            color: #374151;
        }

        .badge-pill.verified {
            background: #ecfdf5;
            color: #047857;
        }

        .badge-pill.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-pill.available {
            background: #e0f2fe;
            color: #0369a1;
        }
        .btn-share {
            min-width: 110px;
        }

        .btn-share {
            background: #4338ca;
            color: white;
        }

        .btn-share:hover {
            background: #312e81;
        }

        .btn-action {
            background: #e5e7eb;
            color: #374151;
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            min-width: 80px;
            justify-content: center;
        }

        .btn-action:hover {
            background: #d1d5db;
            transform: translateY(-1px);
        }

        .btn-action.liked {
            background: #fee2e2;
            color: #dc2626;
        }

        .btn-action.disliked {
            background: #dbeafe;
            color: #2563eb;
        }

        .btn-action.saved {
            background: #fef3c7;
            color: #d97706;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .sort-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .sort-controls label {
            font-weight: bold;
        }

        .sort-controls select {
            padding: 0.5rem;
            border: 2px solid #ddd;
            border-radius: 5px;
        }

        .btn-back-home {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.25rem;
            border-radius: 5px;
            border: 2px solid#eef4fb;
            background: #ffffff;
            color:#eef4fb;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-back-home:hover {
            background:#eef4fb;
            color: #ffffff;
            border-color:#eef4fb;
        }

        .results-count {
            font-weight: bold;
            color: #666;
        }

        @media (max-width: 768px) {
            .designs-container {
                padding: 0 0.75rem 1.5rem;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-filter,
            .btn-clear {
                width: 100%;
            }

            .designs-grid {
                grid-template-columns: 1fr;
            }

            .design-info {
                padding: 1rem;
            }

            .design-title {
                font-size: 1.1rem;
            }

            .design-actions {
                flex-direction: column;
            }
        }
    </style>
    <!-- Mobile Responsive CSS -->
    <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="designs-container">

        <!-- Sort and Results Section -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <a href="index.php" class="btn-home">← Back to Home</a>
                <div class="results-count" style="font-weight: bold; color: #666;">
                    Found <?= count($designs) ?> design<?= count($designs) !== 1 ? 's' : '' ?>
                </div>
            </div>
            <div class="sort-controls" style="display: flex; gap: 1rem; align-items: center;">
                <label for="sort" style="font-weight: bold;">Sort by:</label>
                <select id="sort" onchange="changeSort(this.value)" style="padding: 0.5rem; border: 2px solid #ddd; border-radius: 5px;">
                    <option value="created_at_DESC" <?= $sort_by == 'created_at' && $sort_order == 'DESC' ? 'selected' : '' ?>>Newest First</option>
                    <option value="created_at_ASC" <?= $sort_by == 'created_at' && $sort_order == 'ASC' ? 'selected' : '' ?>>Oldest First</option>
                    <option value="renovation_cost_interior_ASC" <?= $sort_by == 'renovation_cost_interior' && $sort_order == 'ASC' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="renovation_cost_interior_DESC" <?= $sort_by == 'renovation_cost_interior' && $sort_order == 'DESC' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="bedrooms_DESC" <?= $sort_by == 'bedrooms' && $sort_order == 'DESC' ? 'selected' : '' ?>>Most Bedrooms</option>
                    <option value="bedrooms_ASC" <?= $sort_by == 'bedrooms' && $sort_order == 'ASC' ? 'selected' : '' ?>>Least Bedrooms</option>
                </select>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="browse_designs.php">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="budget_min">Min Budget (KES)</label>
                        <input type="number" name="budget_min" id="budget_min"
                               value="<?= $budget_min > 0 ? $budget_min : '' ?>"
                               placeholder="0">
                    </div>

                    <div class="filter-group">
                        <label for="budget_max">Max Budget (KES)</label>
                        <input type="number" name="budget_max" id="budget_max"
                               value="<?= $budget_max < 999999999 ? $budget_max : '' ?>"
                               placeholder="Any">
                    </div>

                    <div class="filter-group">
                        <label for="bedrooms">Min Bedrooms</label>
                        <select name="bedrooms" id="bedrooms">
                            <option value="">Any</option>
                            <option value="1" <?= $bedrooms == 1 ? 'selected' : '' ?>>1+</option>
                            <option value="2" <?= $bedrooms == 2 ? 'selected' : '' ?>>2+</option>
                            <option value="3" <?= $bedrooms == 3 ? 'selected' : '' ?>>3+</option>
                            <option value="4" <?= $bedrooms == 4 ? 'selected' : '' ?>>4+</option>
                            <option value="5" <?= $bedrooms == 5 ? 'selected' : '' ?>>5+</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="property_type">Project Type</label>
                        <select name="property_type" id="property_type">
                            <option value="">All Types</option>
                            <option value="renovation" <?= $property_type == 'renovation' ? 'selected' : '' ?>>Renovation</option>
                            <option value="new_construction" <?= $property_type == 'new_construction' ? 'selected' : '' ?>>New Construction</option>
                        </select>
                    </div>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-filter">🔍 Apply Filters</button>
                    <a href="browse_designs.php" class="btn-clear">🗑️ Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Designs Grid -->
        <?php if (count($designs) > 0): ?>
            <div class="designs-grid">
                <?php foreach ($designs as $design): ?>
                    <div class="design-card" onclick="openDesignModal(<?= $design['id'] ?>)">
                        <div class="card-media">
                            <?php if (!empty($design['video_url'])): ?>
                                <iframe class="design-video"
                                        src="<?= htmlspecialchars($design['video_url']) ?>"
                                        frameborder="0" allowfullscreen>
                                </iframe>
                            <?php elseif (!empty($design['video_file'])): ?>
                                <video class="design-video" controls>
                                    <source src="<?= htmlspecialchars($design['video_file']) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php elseif (!empty($design['image_path'])): ?>
                                <img class="design-video" src="<?= htmlspecialchars($design['image_path']) ?>" alt="<?= htmlspecialchars($design['title']) ?>">
                            <?php else: ?>
                                <div class="media-placeholder">
                                    🎥
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($design['image_count']) || !empty($design['video_url']) || !empty($design['video_file'])): ?>
                                <div class="media-badge">
                                    <?= intval($design['image_count'] + (!empty($design['video_url']) || !empty($design['video_file']) ? 1 : 0)) ?> media
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="design-info">
                            <div class="design-title">
                                <?= htmlspecialchars($design['title']) ?>
                            </div>
                            <div class="design-cost">
                                KES <?= number_format($design['renovation_cost_interior'] + $design['renovation_cost_exterior']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <h3>No designs found</h3>
                <p>Try adjusting your filters or check back later for new designs.</p>
                <a href="browse_designs.php" class="btn-filter" style="margin-top: 1rem; display: inline-block;">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Design Modal -->
    <div id="designModal" class="design-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"></h2>
                <button class="modal-close" onclick="closeDesignModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Modal content will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Store designs data for modal
        const designsData = <?php echo json_encode($designs); ?>;

        function changeSort(value) {
            const [sortBy, sortOrder] = value.split('_');
            const url = new URL(window.location);
            url.searchParams.set('sort', sortBy);
            url.searchParams.set('order', sortOrder);
            window.location.href = url.toString();
        }

        // Auto-submit filters on change (optional enhancement)
        document.querySelectorAll('.filters-grid select').forEach(select => {
            select.addEventListener('change', () => {
                // Uncomment to auto-submit on filter change
                // select.closest('form').submit();
            });
        });

        // Open Design Modal
        function openDesignModal(designId) {
            const design = designsData.find(d => d.id == designId);
            if (!design) return;

            // Populate modal
            document.getElementById('modalTitle').textContent = design.title;

            const modalBody = document.getElementById('modalBody');
            modalBody.innerHTML = generateModalContent(design);

            // Show modal
            document.getElementById('designModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Close Design Modal
        function closeDesignModal() {
            document.getElementById('designModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Generate modal content
        function generateModalContent(design) {
            const designFeatures = JSON.parse(design.features || '[]');
            let targetGroup = 'General occupants';
            designFeatures.forEach(feature => {
                const lower = feature.toLowerCase();
                if (lower.includes('student')) targetGroup = 'Students';
                if (lower.includes('family')) targetGroup = 'Family';
            });

            const listingType = design.project_type === 'new_construction' ? 'New Construction' : 'Renovation';
            const verificationStatus = design.status === 'approved' ? 'Verified' : design.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            const occupancyStatus = ['approved', 'active'].includes(design.status) ? 'Available' : 'Pending';

            let mediaHtml = '';
            if (design.video_url) {
                mediaHtml = `<iframe src="${design.video_url}" frameborder="0" allowfullscreen style="width: 100%; height: 300px; border-radius: 10px;"></iframe>`;
            } else if (design.video_file) {
                mediaHtml = `<video controls style="width: 100%; height: 300px; border-radius: 10px;"><source src="${design.video_file}" type="video/mp4">Your browser does not support the video tag.</video>`;
            } else if (design.image_path) {
                mediaHtml = `<img src="${design.image_path}" alt="${design.title}" style="width: 100%; height: 300px; object-fit: cover; border-radius: 10px;">`;
            } else {
                mediaHtml = `<div style="width: 100%; height: 300px; background: linear-gradient(135deg, #ff7b00 0%, #ff9c3d 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">🎥</div>`;
            }

            let ratingHtml = '';
            if (design.avg_rating > 0) {
                const rating = Math.round(design.avg_rating);
                const stars = '★'.repeat(rating) + '☆'.repeat(5 - rating);
                ratingHtml = `
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                        <div style="color: #ffd700; font-size: 1.2rem;">${stars}</div>
                        <span style="font-size: 0.9rem; color: #666;">(${design.review_count} reviews)</span>
                    </div>
                `;
            }

            const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;

            return `
                <div class="modal-media">${mediaHtml}</div>

                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem;">
                    <span class="badge-pill">${listingType}</span>
                    <span class="badge-pill ${verificationStatus.toLowerCase() === 'verified' ? 'verified' : 'pending'}">${verificationStatus}</span>
                    <span class="badge-pill available">${occupancyStatus}</span>
                </div>

                <div style="margin-bottom: 1rem;">
                    <span>Designer: ${design.agent_name}</span><br>
                    <span>Units: 1 unit</span><br>
                    <span>Electricity: Included</span><br>
                    <span>Water: Included</span>
                </div>

                <div style="font-size: 1.5rem; font-weight: 700; color:#eef4fb; margin-bottom: 1rem;">
                    KES ${Number(design.renovation_cost_interior + design.renovation_cost_exterior).toLocaleString()}
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin: 1rem 0;">
                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.35rem;">Deposit</div>
                        <div style="font-size: 1rem; font-weight: 700; color: #111827;">KES ${design.deposit_required > 0 ? Number(design.deposit_required).toLocaleString() : 'N/A'}</div>
                    </div>
                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.35rem;">Size</div>
                        <div style="font-size: 1rem; font-weight: 700; color: #111827;">${Number(design.property_size_sqm || 0).toLocaleString()} sqm</div>
                    </div>
                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.35rem;">Bedrooms</div>
                        <div style="font-size: 1rem; font-weight: 700; color: #111827;">${design.bedrooms}</div>
                    </div>
                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.35rem;">Bathrooms</div>
                        <div style="font-size: 1rem; font-weight: 700; color: #111827;">${design.bathrooms}</div>
                    </div>
                    <div style="background: #f8fafc; padding: 0.85rem 1rem; border-radius: 12px; border: 1px solid #e5e7eb;">
                        <div style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.35rem;">Target Group</div>
                        <div style="font-size: 1rem; font-weight: 700; color: #111827;">${targetGroup}</div>
                    </div>
                </div>

                <div style="color: #4b5563; font-size: 0.95rem; line-height: 1.6; margin-bottom: 1rem;">
                    ${design.description}
                </div>

                ${ratingHtml}

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="design_details.php?id=${design.id}" class="btn-view" style="flex: 1;">View Details</a>
                    <a href="design_details.php?id=${design.id}#inquiry" class="btn-action">📅 Request Viewing</a>
                    <button class="btn-share" onclick="shareDesign(${design.id}, '${design.title.replace(/'/g, "\\'")}')">🔗 Share</button>
                </div>

                ${isLoggedIn ? `
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.75rem;">
                    <button class="btn-action" onclick="toggleLike(${design.id}, event)" id="like-btn-${design.id}">👍 Like</button>
                    <button class="btn-action" onclick="toggleDislike(${design.id}, event)" id="dislike-btn-${design.id}">👎 Dislike</button>
                    <button class="btn-action" onclick="toggleSaveDesign(${design.id}, event)" id="save-btn-${design.id}">Save</button>
                </div>
                ` : ''}
            `;
        }

        // Close modal when clicking outside
        document.getElementById('designModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDesignModal();
            }
        });

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('designModal').classList.contains('show')) {
                closeDesignModal();
            }
        });

        // Toggle Like
        function toggleLike(designId, event) {
            event.preventDefault();
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'design_id=' + designId
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    const btn = document.getElementById('like-btn-' + designId);
                    const dislikeBtn = document.getElementById('dislike-btn-' + designId);
                    btn.classList.toggle('liked', data.liked);
                    if (data.liked && dislikeBtn) dislikeBtn.classList.remove('disliked');
                    btn.textContent = data.liked ? '👍 Liked' : '👍 Like';
                }
            });
        }

        // Toggle Dislike
        function toggleDislike(designId, event) {
            event.preventDefault();
            fetch('toggle_design_dislike.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'design_id=' + designId
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    const btn = document.getElementById('dislike-btn-' + designId);
                    const likeBtn = document.getElementById('like-btn-' + designId);
                    btn.classList.toggle('disliked', data.disliked);
                    if (data.disliked && likeBtn) likeBtn.classList.remove('liked');
                    btn.textContent = data.disliked ? '👎 Disliked' : '👎 Dislike';
                }
            });
        }

        // Toggle Save Design
        function toggleSaveDesign(designId, event) {
            event.preventDefault();
            fetch('toggle_design_save.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'design_id=' + designId + '&action=toggle'
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    const btn = document.getElementById('save-btn-' + designId);
                    btn.classList.toggle('saved', data.saved);
                    btn.textContent = data.saved ? 'Saved' : 'Save';
                    alert(data.message);
                }
            });
        }

        // Open Comment Modal
        function openCommentModal(designId) {
            window.location.href = 'design_details.php?id=' + designId + '#reviews';
        }

        // Open Request Modal
        function openRequestModal(designId) {
            window.location.href = 'design_details.php?id=' + designId + '#inquiry';
        }

        function shareDesign(designId, title) {
            const url = window.location.origin + '/design_details.php?id=' + designId;
            if (navigator.share) {
                navigator.share({
                    title,
                    text: 'Check out this design on Walbrand Properties Marketplace & Interiors',
                    url
                }).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copied to clipboard');
                }, () => {
                    window.prompt('Copy this link to share:', url);
                });
            } else {
                window.prompt('Copy this link to share:', url);
            }
        }
    </script>
</body>
</html>