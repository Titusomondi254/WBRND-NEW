<?php require_once 'admin_auth.php'; ?>

            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.6rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-bar select {
            padding: 0.7rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            font-family: inherit;
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .user-grid {
                grid-template-columns: 1fr;
            }

            .filter-bar {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
        <div class="admin-header">
            <h1>👥 User Management</h1>
            <a href="admin_control_panel.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <?php if(isset($success)): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if(isset($error)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="filter-bar">
            <select id="filterStatus" onchange="filterUsers()">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="pending_verification">Pending Verification</option>
                <option value="suspended">Suspended</option>
            </select>

            <select id="filterType" onchange="filterUsers()">
                <option value="">All User Types</option>
                <option value="buyer">Buyers</option>
                <option value="seller">Sellers</option>
                <option value="agent">Agents</option>
            </select>

            <select id="filterKYC" onchange="filterUsers()">
                <option value="">All KYC Status</option>
                <option value="verified">KYC Verified</option>
                <option value="pending">KYC Pending</option>
            </select>
        </div>

        <div class="user-grid" id="userGrid">
            <?php
            // Get all users
            $query = "SELECT * FROM users ORDER BY created_at DESC";
            $result = $conn->query($query);

            while($user = $result->fetch_assoc()):
                $initials = strtoupper(substr($user['name'], 0, 1));
            ?>
            <div class="user-card" data-status="<?= $user['status'] ?>" data-type="<?= $user['user_type'] ?>" data-kyc="<?= $user['kyc_verified'] ? 'verified' : 'pending' ?>">
                <div class="user-header">
                    <div class="user-avatar"><?= $initials ?></div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($user['name']) ?></h3>
                        <p><?= htmlspecialchars($user['email']) ?></p>
                    </div>
                </div>

                <div class="user-details">
                    <div class="detail-row">
                        <span class="detail-label">Type:</span>
                        <span class="detail-value"><?= ucfirst($user['user_type']) ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= $user['status'] ?>">
                                <?= ucfirst(str_replace('_', ' ', $user['status'])) ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">KYC:</span>
                        <span class="detail-value">
                            <span class="status-badge status-<?= $user['kyc_verified'] ? 'active' : 'pending' ?>">
                                <?= $user['kyc_verified'] ? '✓ Verified' : '⏳ Pending' ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Joined:</span>
                        <span class="detail-value"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                    </div>
                </div>

                <div class="user-actions">
                    <button class="btn btn-primary" onclick="openModal('user-<?= $user['id'] ?>')">View Details</button>
                    <?php if(!$user['kyc_verified']): ?>
                        <button class="btn btn-success" onclick="openModal('verify-kyc-<?= $user['id'] ?>')">Verify KYC</button>
                    <?php endif; ?>
                    <?php if($user['status'] !== 'suspended'): ?>
                        <button class="btn btn-danger" onclick="openModal('suspend-<?= $user['id'] ?>')">Suspend</button>
                    <?php else: ?>
                        <button class="btn btn-success" onclick="openModal('activate-<?= $user['id'] ?>')">Activate</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- USER DETAIL MODAL -->
            <div class="modal" id="user-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>User Details</h2>
                        <button class="close-btn" onclick="closeModal('user-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" value="<?= htmlspecialchars($user['name']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" value="<?= htmlspecialchars($user['phone'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Type</label>
                        <input type="text" value="<?= htmlspecialchars($user['id_type'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Number</label>
                        <input type="text" value="<?= htmlspecialchars($user['id_number'] ?? 'N/A') ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>ID Front Image</label>
                        <?php if (!empty($user['id_front_path'])): ?>
                            <img src="<?= htmlspecialchars($user['id_front_path']) ?>" alt="ID Front" style="max-width:180px;max-height:120px;border-radius:8px;border:1px solid #eee;">
                        <?php else: ?>
                            <span style="color:#888">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>ID Back Image</label>
                        <?php if (!empty($user['id_back_path'])): ?>
                            <img src="<?= htmlspecialchars($user['id_back_path']) ?>" alt="ID Back" style="max-width:180px;max-height:120px;border-radius:8px;border:1px solid #eee;">
                        <?php else: ?>
                            <span style="color:#888">Not uploaded</span>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label>User Type</label>
                        <input type="text" value="<?= ucfirst($user['user_type']) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <input type="text" value="<?= ucfirst(str_replace('_', ' ', $user['status'])) ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>KYC Status</label>
                        <input type="text" value="<?= $user['kyc_verified'] ? 'Verified' : 'Pending' ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?= date('F d, Y g:i A', strtotime($user['created_at'])) ?>" disabled>
                    </div>
                </div>
            </div>

            <!-- VERIFY KYC MODAL -->
            <div class="modal" id="verify-kyc-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Verify KYC</h2>
                        <button class="close-btn" onclick="closeModal('verify-kyc-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Are you sure you want to verify KYC for <strong><?= htmlspecialchars($user['name']) ?></strong>?</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="verify_kyc">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm Verification</button>
                    </form>
                </div>
            </div>

            <!-- SUSPEND MODAL -->
            <div class="modal" id="suspend-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Suspend Account</h2>
                        <button class="close-btn" onclick="closeModal('suspend-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="form_action" value="suspend_account">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        
                        <div class="form-group">
                            <label>Reason for Suspension</label>
                            <textarea name="suspension_reason" required placeholder="Enter the reason for suspension..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 1rem;">Suspend Account</button>
                    </form>
                </div>
            </div>

            <!-- ACTIVATE MODAL -->
            <div class="modal" id="activate-<?= $user['id'] ?>">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Activate Account</h2>
                        <button class="close-btn" onclick="closeModal('activate-<?= $user['id'] ?>')">&times;</button>
                    </div>
                    <p>Are you sure you want to reactivate <strong><?= htmlspecialchars($user['name']) ?></strong>'s account?</p>
                    <form method="POST" style="margin-top: 2rem;">
                        <input type="hidden" name="form_action" value="activate_account">
                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="btn btn-success" style="width: 100%; padding: 1rem;">Confirm Activation</button>
                    </form>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function filterUsers() {
            const status = document.getElementById('filterStatus').value;
            const type = document.getElementById('filterType').value;
            const kyc = document.getElementById('filterKYC').value;

            const cards = document.querySelectorAll('.user-card');
            cards.forEach(card => {
                let show = true;

                if (status && card.dataset.status !== status) show = false;
                if (type && card.dataset.type !== type) show = false;
                if (kyc && card.dataset.kyc !== kyc) show = false;

                card.style.display = show ? '' : 'none';
            });
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        });
    </script>
</body>
</html>
