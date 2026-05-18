<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

// Check if user is logged in and is an agent
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_type = strtolower(trim($_SESSION['user_type'] ?? $_SESSION['user_role'] ?? ''));

// Allow only agents and sellers
if (!in_array($user_type, ['agent', 'seller'], true)) {
    header('Location: user_dashboard.php');
    exit;
}

track_user_activity();

// Get agent's uploaded facilities
$facilities_query = "SELECT f.*, 
    (SELECT COUNT(*) FROM storage_units WHERE facility_id = f.id) as total_units,
    (SELECT COUNT(*) FROM storage_units WHERE facility_id = f.id AND availability_status='available') as available_units
    FROM storage_facilities f
    WHERE f.owner_id = $user_id
    ORDER BY f.created_at DESC";

$facilities_result = db_query($facilities_query);
$facilities = [];
if ($facilities_result) {
    while ($row = $facilities_result->fetch_assoc()) {
        $facilities[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Storage Facility - Walbrand Properties</title>
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        :root {
            --forest-green: #2d5016;
            --light-green: #4a7c25;
            --accent-green: #6ba846;
            --pale-green: #e8f5e9;
            --primary: #f97316;
            --primary-dark: #ea580c;
        }

        .upload-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
            min-height: calc(100vh - 100px);
            background: #f5f5f5;
        }

        .page-header {
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--light-green) 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 2rem;
            margin: 0;
        }

        .page-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .btn-primary {
            background: white;
            color: var(--forest-green);
            padding: 12px 24px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .facility-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .facility-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            transform: translateY(-4px);
        }

        .facility-card-header {
            background: linear-gradient(135deg, var(--forest-green) 0%, var(--light-green) 100%);
            color: white;
            padding: 20px;
        }

        .facility-card-header h3 {
            margin: 0 0 5px 0;
            font-size: 1.3rem;
        }

        .facility-card-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .facility-card-body {
            padding: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            color: #666;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .facility-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 10px;
        }

        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .facility-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-small {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--light-green);
            background: white;
            color: var(--light-green);
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
        }

        .btn-small:hover {
            background: var(--light-green);
            color: white;
        }

        .btn-danger {
            border-color: #ef4444;
            color: #ef4444;
        }

        .btn-danger:hover {
            background: #ef4444;
            color: white;
        }

        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--light-green);
            opacity: 0.5;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--forest-green);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 25px;
        }

        .empty-state a {
            display: inline-block;
            background: var(--light-green);
            color: white;
            padding: 12px 30px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .empty-state a:hover {
            background: var(--forest-green);
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
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
            padding: 30px;
            border-radius: 12px;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--forest-green);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-note {
            margin-top: 6px;
            font-size: 0.9rem;
            color: #4b5563;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--forest-green);
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                text-align: center;
            }

            .facilities-grid {
                grid-template-columns: 1fr;
            }
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .btn-submit {
            background: var(--forest-green);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-submit:hover {
            background: var(--light-green);
        }

        .btn-cancel {
            background: #e5e7eb;
            color: #333;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .required {
            color: #ef4444;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
        }

        .success-message.show {
            display: flex;
        }

        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message.show {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <div class="logo">Walbrand Properties & Interiors</div>
            <nav>
                <button onclick="window.location.href='agent_dashboard.php'">Dashboard</button>
                <button onclick="window.location.href='index.php'">Home</button>
                <button onclick="window.location.href='agent_upload_storage.php'" style="color: var(--primary-color); font-weight: 600;">Upload Storage</button>
            </nav>
            <div class="auth-buttons">
                <a href="agent_dashboard.php" class="auth-btn login-btn">Dashboard</a>
                <a href="logout.php" class="auth-btn register-btn">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="upload-container">
        <!-- Success/Error Messages -->
        <div class="success-message" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <span id="successText"></span>
        </div>
        <div class="error-message" id="errorMessage">
            <i class="fas fa-exclamation-circle"></i>
            <span id="errorText"></span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-cube"></i> Upload Storage Facility</h1>
                <p>Add and manage your storage facilities for student bookings</p>
            </div>
            <button class="btn-primary" onclick="openUploadModal()">
                <i class="fas fa-plus"></i> Add New Facility
            </button>
        </div>

        <!-- Facilities List -->
        <?php if (empty($facilities)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Storage Facilities Yet</h3>
                <p>Upload your first storage facility to start accepting student bookings</p>
                <a href="#" onclick="openUploadModal(); return false;">Upload Facility</a>
            </div>
        <?php else: ?>
            <div class="facilities-grid">
                <?php foreach ($facilities as $facility): ?>
                    <div class="facility-card">
                        <div class="facility-card-header">
                            <h3><?php echo htmlspecialchars($facility['name']); ?></h3>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($facility['city'] . ', ' . $facility['county']); ?></p>
                        </div>
                        <div class="facility-card-body">
                            <div class="info-item">
                                <span class="info-label">Student Capacity</span>
                                <span class="info-value"><?php echo intval($facility['student_capacity'] ?? 0); ?> students</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Monthly Rate</span>
                                <span class="info-value">KSh <?php echo number_format($facility['pricing_per_unit_monthly'] ?? 0); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Deposit (Refundable)</span>
                                <span class="info-value">KSh <?php echo number_format($facility['deposit_amount'] ?? 500); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Max Months</span>
                                <span class="info-value"><?php echo intval($facility['max_months'] ?? 1); ?> months</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Pickup Penalty</span>
                                <span class="info-value">KSh <?php echo number_format($facility['pickup_overdue_penalty'] ?? 0); ?>/day</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Storage Penalty</span>
                                <span class="info-value">KSh <?php echo number_format($facility['storage_overdue_penalty'] ?? 0); ?>/day</span>
                            </div>

                            <span class="facility-status <?php echo ($facility['verification_status'] === 'verified') ? 'status-verified' : 'status-pending'; ?>">
                                <i class="fas fa-<?php echo ($facility['verification_status'] === 'verified') ? 'check-circle' : 'clock'; ?>"></i>
                                <?php echo ucfirst($facility['verification_status']); ?>
                            </span>

                            <div class="facility-actions">
                                <button class="btn-small" onclick="editFacility(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn-small" onclick="openAdminLink(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-external-link-alt"></i> Open in Admin
                                </button>
                                <button class="btn-small" onclick="copyAdminLink(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-link"></i> Copy Admin Link
                                </button>
                                <button class="btn-small btn-danger" onclick="deleteFacility(<?php echo $facility['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <span id="modalTitle">Add New Storage Facility</span>
                <button class="modal-close" onclick="closeUploadModal()">×</button>
            </div>
            <form id="facilityForm" onsubmit="saveFacility(event)">
                <input type="hidden" id="facilityId" value="">

                <div class="form-group">
                    <label class="form-label">Facility Name <span class="required">*</span></label>
                    <input type="text" class="form-input" id="facilityName" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea class="form-textarea" id="facilityDescription" placeholder="Describe your storage facility..."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Location/Address <span class="required">*</span></label>
                        <input type="text" class="form-input" id="facilityAddress" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">City <span class="required">*</span></label>
                        <input type="text" class="form-input" id="facilityCity" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">County <span class="required">*</span></label>
                        <input type="text" class="form-input" id="facilityCounty" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="tel" class="form-input" id="facilityPhone">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Contact Email</label>
                        <input type="email" class="form-input" id="facilityEmail">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Access Hours (e.g., 6 AM - 10 PM)</label>
                        <input type="text" class="form-input" id="facilityAccessHours">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Facility Photos <span class="required">*</span></label>
                        <input type="file" name="photos[]" accept="image/*" class="form-input" id="facilityPhotos" multiple>
                        <div class="form-note" id="photoFilesInfo">0/5 images selected</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Facility Videos</label>
                        <input type="file" name="videos[]" accept="video/*" class="form-input" id="facilityVideos" multiple>
                        <div class="form-note" id="videoFilesInfo">0/5 videos selected</div>
                    </div>
                </div>

                <h4 style="color: var(--forest-green); margin-top: 30px; margin-bottom: 15px;">Storage Details</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Student Capacity <span class="required">*</span></label>
                        <input type="number" class="form-input" id="studentCapacity" min="1" step="1" required placeholder="Number of students who can store">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monthly Rate per Student <span class="required">*</span></label>
                        <input type="number" class="form-input" id="facilityPrice" min="0" step="100" required placeholder="KSh amount">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Deposit Amount (Refundable) <span class="required">*</span></label>
                        <input type="number" class="form-input" id="depositAmount" min="0" step="50" value="500" required placeholder="KSh 500">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Maximum Booking Months <span class="required">*</span></label>
                        <input type="number" class="form-input" id="maxMonths" min="1" step="1" value="1" required>
                    </div>
                </div>

                <h4 style="color: var(--forest-green); margin-top: 30px; margin-bottom: 15px;">Penalty Fees</h4>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Pickup Overdue Penalty (KSh/day) <span class="required">*</span></label>
                        <input type="number" class="form-input" id="pickupPenalty" min="0" step="50" value="0" required placeholder="Daily penalty if pickup is delayed">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Storage Overdue Penalty (KSh/day) <span class="required">*</span></label>
                        <input type="number" class="form-input" id="storagePenalty" min="0" step="50" value="0" required placeholder="Daily penalty if storage extends beyond booking date">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Submit Facility</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <h3>Walbrand Properties & Interiors</h3>
            <p><strong>Location:</strong> Nairobi, Kenya | <strong>Phone:</strong> +254113906162 | <strong>Hours:</strong> 24/7</p>
            <p><strong>Message us on WhatsApp:</strong> <a href="https://wa.me/254113906162" style="color: white; text-decoration: none;">+254113906162</a></p>
        </div>
    </footer>

    <script>
        function openUploadModal() {
            document.getElementById('facilityForm').reset();
            document.getElementById('facilityId').value = '';
            document.getElementById('modalTitle').textContent = 'Add New Storage Facility';
            updateMediaInfo();
            document.getElementById('uploadModal').classList.add('active');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
        }

        const MAX_PHOTOS = 5;
        const MAX_VIDEOS = 5;

        function updateMediaInfo() {
            const photoFiles = document.getElementById('facilityPhotos').files;
            const videoFiles = document.getElementById('facilityVideos').files;
            const photoCount = photoFiles.length;
            const videoCount = videoFiles.length;
            if (photoCount > MAX_PHOTOS) {
                showError(`You can upload up to ${MAX_PHOTOS} images only.`);
            }
            if (videoCount > MAX_VIDEOS) {
                showError(`You can upload up to ${MAX_VIDEOS} videos only.`);
            }
            document.getElementById('photoFilesInfo').textContent = `${photoCount}/${MAX_PHOTOS} images selected`;
            document.getElementById('videoFilesInfo').textContent = `${videoCount}/${MAX_VIDEOS} videos selected`;
        }

        document.getElementById('facilityPhotos').addEventListener('change', updateMediaInfo);
        document.getElementById('facilityVideos').addEventListener('change', updateMediaInfo);

        function saveFacility(e) {
            e.preventDefault();
            const facilityId = document.getElementById('facilityId').value;
            const action = facilityId ? 'update_facility' : 'add_facility';

            const photoFiles = document.getElementById('facilityPhotos').files;
            const videoFiles = document.getElementById('facilityVideos').files;
            if (photoFiles.length > MAX_PHOTOS) {
                showError(`You can upload up to ${MAX_PHOTOS} images only.`);
                return;
            }
            if (videoFiles.length > MAX_VIDEOS) {
                showError(`You can upload up to ${MAX_VIDEOS} videos only.`);
                return;
            }

            const formData = new FormData();
            formData.append('action', action);
            if (facilityId) formData.append('facility_id', facilityId);
            formData.append('name', document.getElementById('facilityName').value);
            formData.append('description', document.getElementById('facilityDescription').value);
            formData.append('address', document.getElementById('facilityAddress').value);
            formData.append('city', document.getElementById('facilityCity').value);
            formData.append('county', document.getElementById('facilityCounty').value);
            formData.append('pricing_per_unit_monthly', document.getElementById('facilityPrice').value);
            formData.append('contact_phone', document.getElementById('facilityPhone').value);
            formData.append('contact_email', document.getElementById('facilityEmail').value);
            formData.append('access_hours', document.getElementById('facilityAccessHours').value);
            formData.append('student_capacity', document.getElementById('studentCapacity').value);
            formData.append('deposit_amount', document.getElementById('depositAmount').value);
            formData.append('max_months', document.getElementById('maxMonths').value);
            formData.append('pickup_overdue_penalty', document.getElementById('pickupPenalty').value);
            formData.append('storage_overdue_penalty', document.getElementById('storagePenalty').value);

            for (let i = 0; i < photoFiles.length; i++) {
                formData.append('photos[]', photoFiles[i]);
            }
            for (let i = 0; i < videoFiles.length; i++) {
                formData.append('videos[]', videoFiles[i]);
            }

            fetch('storage_agent_handler.php', { method: 'POST', body: formData })
                .then(r => r.text())
                .then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch (e) { showError('Server returned invalid JSON: ' + text); return; }
                    if (data.success) {
                        showSuccess(data.message || 'Facility saved successfully!');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(data.message || 'Error saving facility');
                    }
                })
                .catch(err => showError('Error: ' + err.message));
        }

        function editFacility(facilityId) {
            fetch(`storage_agent_handler.php?action=get_facility&facility_id=${facilityId}`)
                .then(r => r.text())
                .then(text => {
                    let data;
                    try { data = JSON.parse(text); }
                    catch (e) { showError('Server returned invalid JSON: ' + text); return; }
                    if (data.success) {
                        const f = data.data;
                        document.getElementById('facilityForm').reset();
                        document.getElementById('facilityId').value = f.id;
                        document.getElementById('facilityName').value = f.name;
                        document.getElementById('facilityDescription').value = f.description || '';
                        document.getElementById('facilityAddress').value = f.address;
                        document.getElementById('facilityCity').value = f.city;
                        document.getElementById('facilityCounty').value = f.county;
                        document.getElementById('facilityPrice').value = f.pricing_per_unit_monthly;
                        document.getElementById('facilityPhone').value = f.contact_phone || '';
                        document.getElementById('facilityEmail').value = f.contact_email || '';
                        document.getElementById('facilityAccessHours').value = f.access_hours || '';
                        document.getElementById('studentCapacity').value = f.student_capacity ?? 0;
                        document.getElementById('depositAmount').value = f.deposit_amount ?? 500;
                        document.getElementById('maxMonths').value = f.max_months ?? 1;
                        document.getElementById('pickupPenalty').value = f.pickup_overdue_penalty ?? 0;
                        document.getElementById('storagePenalty').value = f.storage_overdue_penalty ?? 0;
                        document.getElementById('facilityPhotos').value = '';
                        document.getElementById('facilityVideos').value = '';
                        updateMediaInfo();
                        document.getElementById('modalTitle').textContent = 'Edit Storage Facility';
                        document.getElementById('uploadModal').classList.add('active');
                    }
                })
                .catch(err => showError('Error loading facility: ' + err.message));
        }

        function deleteFacility(facilityId) {
            if (confirm('Are you sure you want to delete this facility? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('action', 'delete_facility');
                formData.append('facility_id', facilityId);

                fetch('storage_agent_handler.php', { method: 'POST', body: formData })
                    .then(r => r.text())
                    .then(text => {
                        let data;
                        try { data = JSON.parse(text); }
                        catch (e) { showError('Server returned invalid JSON: ' + text); return; }
                        if (data.success) {
                            showSuccess(data.message || 'Facility deleted successfully!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showError(data.message || 'Error deleting facility');
                        }
                    })
                    .catch(err => showError('Error: ' + err.message));
            }
        }

        function showSuccess(message) {
            const el = document.getElementById('successMessage');
            document.getElementById('successText').textContent = message;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 5000);
        }

        function showError(message) {
            const el = document.getElementById('errorMessage');
            document.getElementById('errorText').textContent = message;
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 5000);
        }

        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });

        function openAdminLink(facilityId) {
            // Open the admin storage facilities page with facility highlighted for verification
            window.open(`admin_storage_facilities.php?facility_id=${facilityId}`, '_blank');
        }

        function copyAdminLink(facilityId) {
            const url = `admin_storage_facilities.php?facility_id=${facilityId}`;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    showSuccess('Admin link copied to clipboard');
                }).catch(() => {
                    showError('Unable to copy link');
                });
            } else {
                // Fallback: create a temporary input
                const tmp = document.createElement('input');
                document.body.appendChild(tmp);
                tmp.value = url;
                tmp.select();
                try { document.execCommand('copy'); showSuccess('Admin link copied to clipboard'); }
                catch (e) { showError('Unable to copy link'); }
                tmp.remove();
            }
        }
    </script>
</body>
</html>
