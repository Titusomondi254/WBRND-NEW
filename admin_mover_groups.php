<?php
/**
 * Admin - Mover Groups Management
 * Create, view, and manage mover groups and their members
 */

require_once 'admin_auth.php';
require_once 'config_mover_system.php';

$conn = getMoverDatabaseConnection();
if (!$conn) {
    die("Database connection failed");
}

// Get all groups with member counts
$groupsQuery = "
    SELECT g.id, g.group_name, g.created_at, COUNT(m.id) as member_count, 
           COUNT(CASE WHEN m.id IS NOT NULL THEN 1 END) as current_members
    FROM mover_groups g 
    LEFT JOIN mover_group_members m ON g.id = m.group_id 
    GROUP BY g.id, g.group_name, g.created_at
    ORDER BY g.group_name ASC
";
$groupsResult = $conn->query($groupsQuery);
$groups = $groupsResult->fetch_all(MYSQLI_ASSOC);

// Handle delete group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'delete_group' && isset($_POST['group_id'])) {
        $groupId = intval($_POST['group_id']);
        $stmt = $conn->prepare("DELETE FROM mover_groups WHERE id = ?");
        $stmt->bind_param("i", $groupId);
        if ($stmt->execute()) {
            header('Location: admin_mover_groups.php?msg=deleted');
            exit;
        }
        $stmt->close();
    }
}

// Handle get group members
if (isset($_GET['get_members']) && is_numeric($_GET['get_members'])) {
    header('Content-Type: application/json');
    $groupId = intval($_GET['get_members']);
    
    $stmt = $conn->prepare("SELECT id, employee_name, employee_contact, created_at FROM mover_group_members WHERE group_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $groupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $members = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    echo json_encode(['success' => true, 'members' => $members]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Mover Groups - Walbrand Movers Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --info-color: #3498db;
            --border-radius: 8px;
        }

        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .admin-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .admin-header h1 {
            margin: 0;
            font-weight: bold;
        }

        .btn-create {
            background: var(--success-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-create:hover {
            background: #229954;
            color: white;
        }

        .group-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .group-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .group-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .group-name {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
        }

        .group-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .group-body {
            padding: 20px;
        }

        .group-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            border-left: 4px solid var(--accent-color);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            color: #666;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .members-section {
            margin-top: 20px;
        }

        .members-title {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .member-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: var(--border-radius);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-weight: 600;
            color: var(--primary-color);
        }

        .member-contact {
            font-size: 0.85rem;
            color: #666;
            margin-top: 3px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.8rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: var(--info-color);
            color: white;
        }

        .btn-view:hover {
            background: #2980b9;
        }

        .btn-edit {
            background: var(--primary-color);
            color: white;
        }

        .btn-edit:hover {
            background: #1a252f;
        }

        .btn-delete {
            background: var(--accent-color);
            color: white;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .btn-add-member {
            background: var(--success-color);
            color: white;
        }

        .btn-add-member:hover {
            background: #229954;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .form-group label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .alert-message {
            margin-bottom: 20px;
        }

        .no-groups {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: var(--border-radius);
            color: #999;
        }

        .no-groups i {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .no-members {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
    </style>
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="notifications.js"></script>
</head>
<body>
    <!-- Admin Header -->
    <div class="admin-header">
        <div class="container-fluid" style="padding: 0 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1><i class="fas fa-users"></i> Mover Groups Management</h1>
                    <p class="mb-0" style="opacity: 0.9;">Create and manage moving service groups</p>
                </div>
                <button class="btn-create" onclick="openCreateGroupModal()">
                    <i class="fas fa-plus"></i> Create New Group
                </button>
            </div>
        </div>
    </div>

    <div class="container-fluid" style="padding: 0 20px;">
        <!-- Alert Message -->
        <?php if (isset($_GET['msg'])): ?>
            <div class="alert-message">
                <?php if ($_GET['msg'] === 'deleted'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <strong>Success!</strong> Group deleted successfully
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Groups List -->
        <?php if (empty($groups)): ?>
            <div class="no-groups">
                <i class="fas fa-inbox"></i>
                <p>No groups created yet</p>
                <button class="btn-create" onclick="openCreateGroupModal()">Create First Group</button>
            </div>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <div class="group-card">
                    <div class="group-header">
                        <div>
                            <p class="group-name">
                                <i class="fas fa-users"></i> <?php echo htmlspecialchars($group['group_name']); ?>
                            </p>
                            <small style="opacity: 0.9;">Created: <?php echo date('d M, Y', strtotime($group['created_at'])); ?></small>
                        </div>
                        <div class="action-buttons">
                            <button class="btn-small btn-view" onclick="viewGroupMembers(<?php echo $group['id']; ?>)">
                                <i class="fas fa-eye"></i> View Members
                            </button>
                            <button class="btn-small btn-edit" onclick="openAddMemberModal(<?php echo $group['id']; ?>)">
                                <i class="fas fa-user-plus"></i> Add Member
                            </button>
                            <button class="btn-small btn-delete" onclick="deleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['group_name']); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>

                    <div class="group-body">
                        <div class="group-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $group['current_members']; ?></div>
                                <div class="stat-label">Members</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo MOVER_GROUP_SIZE; ?></div>
                                <div class="stat-label">Capacity</div>
                            </div>
                        </div>

                        <div class="members-section" id="members-<?php echo $group['id']; ?>">
                            <div class="members-title">Group Members</div>
                            <div class="text-muted text-center">Loading members...</div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Create Group Modal -->
    <div class="modal fade" id="createGroupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Mover Group</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createGroupForm">
                        <div class="form-group">
                            <label for="groupName">Group Name:</label>
                            <input type="text" id="groupName" name="group_name" class="form-control" placeholder="e.g., Group A, Team 1" required>
                            <small class="text-muted">Give your group a unique, memorable name</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100" style="margin-top: 20px;">Create Group</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div class="modal fade" id="addMemberModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Group Member</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addMemberForm">
                        <input type="hidden" id="addMemberGroupId" name="group_id">
                        
                        <div class="form-group">
                            <label for="employeeName">Employee Name:</label>
                            <input type="text" id="employeeName" name="employee_name" class="form-control" placeholder="Full name" required>
                        </div>

                        <div class="form-group">
                            <label for="employeeContact">Email/Phone:</label>
                            <input type="text" id="employeeContact" name="employee_contact" class="form-control" placeholder="email@example.com or +254712345678" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Add Member</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- View Members Modal -->
    <div class="modal fade" id="viewMembersModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Group Members</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="membersList">
                    <div class="text-center">Loading members...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const createGroupModal = new bootstrap.Modal(document.getElementById('createGroupModal'));
        const addMemberModal = new bootstrap.Modal(document.getElementById('addMemberModal'));
        const viewMembersModal = new bootstrap.Modal(document.getElementById('viewMembersModal'));

        function openCreateGroupModal() {
            document.getElementById('createGroupForm').reset();
            createGroupModal.show();
        }

        function openAddMemberModal(groupId) {
            document.getElementById('addMemberGroupId').value = groupId;
            document.getElementById('addMemberForm').reset();
            addMemberModal.show();
        }

        function viewGroupMembers(groupId) {
            fetch(`admin_mover_groups.php?get_members=${groupId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (data.members.length === 0) {
                            document.getElementById('membersList').innerHTML = '<div class="text-muted text-center">No members in this group yet</div>';
                        } else {
                            let html = '';
                            data.members.forEach(member => {
                                html += `
                                    <div class="member-item">
                                        <div class="member-info">
                                            <div class="member-name"><i class="fas fa-user"></i> ${member.employee_name}</div>
                                            <div class="member-contact">${member.employee_contact}</div>
                                        </div>
                                        <button class="btn-small btn-delete" onclick="removeMember(${member.id}, '${member.employee_name}')">Remove</button>
                                    </div>
                                `;
                            });
                            document.getElementById('membersList').innerHTML = html;
                        }
                        viewMembersModal.show();

                        // Load members in group cards
                        const container = document.getElementById(`members-${groupId}`);
                        if (container) {
                            if (data.members.length === 0) {
                                container.innerHTML = '<div class="no-members">No members added yet</div>';
                            } else {
                                let html = '<div class="members-title">Group Members</div>';
                                data.members.forEach(member => {
                                    html += `
                                        <div class="member-item">
                                            <div class="member-info">
                                                <div class="member-name"><i class="fas fa-user"></i> ${member.employee_name}</div>
                                                <div class="member-contact">${member.employee_contact}</div>
                                            </div>
                                        </div>
                                    `;
                                });
                                container.innerHTML = html;
                            }
                        }
                    }
                })
                .catch(e => showError('Error loading members'));
        }

        document.getElementById('createGroupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const groupName = document.getElementById('groupName').value;

            fetch('add_mover_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=create&group_name=${encodeURIComponent(groupName)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Group created successfully!');
                    location.reload();
                } else {
                    showError('Error: ' + data.error);
                }
            })
            .catch(e => showError('Error creating group'));
        });

        document.getElementById('addMemberForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const groupId = document.getElementById('addMemberGroupId').value;
            const employeeName = document.getElementById('employeeName').value;
            const employeeContact = document.getElementById('employeeContact').value;

            fetch('add_mover_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_member&group_id=${groupId}&employee_name=${encodeURIComponent(employeeName)}&employee_contact=${encodeURIComponent(employeeContact)}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showSuccess('Member added successfully!');
                    addMemberModal.hide();
                    location.reload();
                } else {
                    showError('Error: ' + data.error);
                }
            })
            .catch(e => showError('Error adding member'));
        });

        function deleteGroup(groupId, groupName) {
            showConfirm(`Are you sure you want to delete "${groupName}"? This will also delete all group members and any associated assignments.`)
                .then(confirmed => {
                    if (confirmed) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.innerHTML = `<input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="${groupId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
        }

        function removeMember(memberId, memberName) {
            showConfirm(`Remove ${memberName} from this group?`)
                .then(confirmed => {
                    if (confirmed) {
                        fetch('add_mover_group.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=remove_member&member_id=${memberId}`
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                showSuccess('Member removed successfully!');
                                location.reload();
                            } else {
                                showError('Error: ' + data.error);
                            }
                        })
                        .catch(e => showError('Error removing member'));
                    }
                });
        }

        // Load members for all groups on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($groups as $group): ?>
            viewGroupMembers(<?php echo $group['id']; ?>);
            <?php endforeach; ?>
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
