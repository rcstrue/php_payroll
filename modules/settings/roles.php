<?php
/**
 * RCS HRMS Pro - Role Management
 * Company: RCS TRUE FACILITIES PVT LTD
 */

$pageTitle = 'Manage Roles';

// Get all roles
$stmt = $db->query("SELECT * FROM roles ORDER BY level DESC");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Define available permissions
$availablePermissions = [
    'dashboard' => ['view' => 'View Dashboard'],
    'employee' => [
        'view' => 'View Employees',
        'add' => 'Add Employee',
        'edit' => 'Edit Employee',
        'delete' => 'Delete Employee',
        'import' => 'Import Employees',
        'export' => 'Export Employees'
    ],
    'attendance' => [
        'view' => 'View Attendance',
        'add' => 'Add Attendance',
        'edit' => 'Edit Attendance',
        'import' => 'Import Attendance',
        'export' => 'Export Attendance'
    ],
    'payroll' => [
        'view' => 'View Payroll',
        'process' => 'Process Payroll',
        'approve' => 'Approve Payroll',
        'export' => 'Export Payroll'
    ],
    'client' => [
        'view' => 'View Clients',
        'add' => 'Add Client',
        'edit' => 'Edit Client',
        'delete' => 'Delete Client'
    ],
    'unit' => [
        'view' => 'View Units',
        'add' => 'Add Unit',
        'edit' => 'Edit Unit',
        'delete' => 'Delete Unit'
    ],
    'contract' => [
        'view' => 'View Contracts',
        'add' => 'Add Contract',
        'edit' => 'Edit Contract',
        'delete' => 'Delete Contract'
    ],
    'compliance' => [
        'view' => 'View Compliance',
        'manage' => 'Manage Compliance',
        'file' => 'File Returns'
    ],
    'reports' => [
        'view' => 'View Reports',
        'export' => 'Export Reports'
    ],
    'settings' => [
        'view' => 'View Settings',
        'manage' => 'Manage Settings'
    ],
    'users' => [
        'view' => 'View Users',
        'add' => 'Add User',
        'edit' => 'Edit User',
        'delete' => 'Delete User'
    ],
    'roles' => [
        'view' => 'View Roles',
        'add' => 'Add Role',
        'edit' => 'Edit Role',
        'delete' => 'Delete Role'
    ]
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $roleName = sanitize($_POST['role_name']);
        $roleCode = sanitize($_POST['role_code']);
        $description = sanitize($_POST['description'] ?? '');
        $level = (int)$_POST['level'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Collect permissions
        $permissions = [];
        foreach ($availablePermissions as $module => $perms) {
            foreach ($perms as $perm => $label) {
                $key = $module . '_' . $perm;
                if (isset($_POST['permissions'][$key])) {
                    $permissions[$module][$perm] = true;
                }
            }
        }
        $permissionsJson = json_encode($permissions);
        
        // Check if role_code already exists
        $checkStmt = $db->prepare("SELECT id FROM roles WHERE role_code = ?");
        $checkStmt->execute([$roleCode]);
        
        if ($checkStmt->fetch()) {
            setFlash('error', 'Role code already exists!');
        } else {
            $stmt = $db->prepare("INSERT INTO roles (role_name, role_code, description, permissions, level, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$roleName, $roleCode, $description, $permissionsJson, $level, $isActive]);
            
            // Log activity
            logActivity('create', 'roles', $db->lastInsertId(), "Created role: $roleName");
            
            setFlash('success', 'Role created successfully!');
        }
        redirect('index.php?page=settings/roles');
    }
    
    if ($action === 'edit' && isset($_POST['role_id'])) {
        $roleId = (int)$_POST['role_id'];
        $roleName = sanitize($_POST['role_name']);
        $description = sanitize($_POST['description'] ?? '');
        $level = (int)$_POST['level'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // Collect permissions
        $permissions = [];
        foreach ($availablePermissions as $module => $perms) {
            foreach ($perms as $perm => $label) {
                $key = $module . '_' . $perm;
                if (isset($_POST['permissions'][$key])) {
                    $permissions[$module][$perm] = true;
                }
            }
        }
        $permissionsJson = json_encode($permissions);
        
        $stmt = $db->prepare("UPDATE roles SET role_name = ?, description = ?, permissions = ?, level = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$roleName, $description, $permissionsJson, $level, $isActive, $roleId]);
        
        // Log activity
        logActivity('update', 'roles', $roleId, "Updated role: $roleName");
        
        setFlash('success', 'Role updated successfully!');
        redirect('index.php?page=settings/roles');
    }
    
    if ($action === 'delete' && isset($_POST['role_id'])) {
        $roleId = (int)$_POST['role_id'];
        
        // Check if any users have this role
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
        $checkStmt->execute([$roleId]);
        $userCount = $checkStmt->fetchColumn();
        
        if ($userCount > 0) {
            setFlash('error', "Cannot delete role. $userCount user(s) assigned to this role.");
        } else {
            // Get role name before deletion
            $stmt = $db->prepare("SELECT role_name FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            $roleName = $stmt->fetchColumn();
            
            $stmt = $db->prepare("DELETE FROM roles WHERE id = ?");
            $stmt->execute([$roleId]);
            
            // Log activity
            logActivity('delete', 'roles', $roleId, "Deleted role: $roleName");
            
            setFlash('success', 'Role deleted successfully!');
        }
        redirect('index.php?page=settings/roles');
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-shield-lock me-2"></i>Manage Roles
                </h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Role
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Role Name</th>
                                <th>Role Code</th>
                                <th>Level</th>
                                <th>Users</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles as $role): 
                                // Get user count for this role
                                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = ?");
                                $stmt->execute([$role['id']]);
                                $userCount = $stmt->fetchColumn();
                                
                                // Parse permissions
                                $perms = json_decode($role['permissions'] ?? '{}', true);
                                $permCount = 0;
                                if (is_array($perms)) {
                                    foreach ($perms as $module => $actions) {
                                        $permCount += count($actions);
                                    }
                                }
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo sanitize($role['role_name']); ?></strong>
                                    <?php if ($role['description']): ?>
                                    <br><small class="text-muted"><?php echo sanitize($role['description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo sanitize($role['role_code']); ?></code>
                                    <br><small class="text-muted"><?php echo $permCount; ?> permissions</small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo $role['level']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $userCount; ?> user(s)</span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $role['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if ($role['role_code'] !== 'admin'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo sanitize($role['role_name']); ?>')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="addRoleForm">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Role Name</label>
                            <input type="text" class="form-control" name="role_name" required placeholder="e.g., HR Manager">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Role Code</label>
                            <input type="text" class="form-control" name="role_code" required placeholder="e.g., hr_manager" pattern="[a-z_]+" title="Only lowercase letters and underscores">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Level</label>
                            <input type="number" class="form-control" name="level" value="50" min="0" max="100">
                            <div class="form-text">Higher level = more access</div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllPermissions()">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllPermissions()">Deselect All</button>
                            </div>
                            <?php foreach ($availablePermissions as $module => $perms): ?>
                            <div class="mb-3">
                                <h6 class="text-primary mb-2 text-uppercase"><?php echo ucfirst($module); ?></h6>
                                <div class="row">
                                    <?php foreach ($perms as $perm => $label): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="permissions[<?php echo $module; ?>_<?php echo $perm; ?>]" id="perm_<?php echo $module; ?>_<?php echo $perm; ?>">
                                            <label class="form-check-label" for="perm_<?php echo $module; ?>_<?php echo $perm; ?>"><?php echo $label; ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="add_is_active" checked>
                        <label class="form-check-label" for="add_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editRoleForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="role_id" id="edit_role_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Role Name</label>
                            <input type="text" class="form-control" name="role_name" id="edit_role_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Role Code</label>
                            <input type="text" class="form-control" id="edit_role_code_display" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Level</label>
                            <input type="number" class="form-control" name="level" id="edit_level" min="0" max="100">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="edit_description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Permissions</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <div class="mb-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllEditPermissions()">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllEditPermissions()">Deselect All</button>
                            </div>
                            <?php foreach ($availablePermissions as $module => $perms): ?>
                            <div class="mb-3">
                                <h6 class="text-primary mb-2 text-uppercase"><?php echo ucfirst($module); ?></h6>
                                <div class="row">
                                    <?php foreach ($perms as $perm => $label): ?>
                                    <div class="col-md-4">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input edit-perm" name="permissions[<?php echo $module; ?>_<?php echo $perm; ?>]" id="edit_perm_<?php echo $module; ?>_<?php echo $perm; ?>">
                                            <label class="form-check-label" for="edit_perm_<?php echo $module; ?>_<?php echo $perm; ?>"><?php echo $label; ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="role_id" id="delete_role_id">
</form>

<script>
function editRole(role) {
    $('#edit_role_id').val(role.id);
    $('#edit_role_name').val(role.role_name);
    $('#edit_role_code_display').val(role.role_code);
    $('#edit_description').val(role.description || '');
    $('#edit_level').val(role.level);
    $('#edit_is_active').prop('checked', role.is_active == 1);
    
    // Reset all checkboxes
    $('.edit-perm').prop('checked', false);
    
    // Set permissions
    if (role.permissions) {
        var perms = JSON.parse(role.permissions);
        for (var module in perms) {
            for (var perm in perms[module]) {
                var key = module + '_' + perm;
                $('#edit_perm_' + module + '_' + perm).prop('checked', true);
            }
        }
    }
    
    new bootstrap.Modal('#editRoleModal').show();
}

function deleteRole(roleId, roleName) {
    if (confirm('Are you sure you want to delete the role "' + roleName + '"?\n\nThis action cannot be undone.')) {
        $('#delete_role_id').val(roleId);
        $('#deleteForm').submit();
    }
}

function selectAllPermissions() {
    $('#addRoleForm input[type="checkbox"][name^="permissions"]').prop('checked', true);
}

function deselectAllPermissions() {
    $('#addRoleForm input[type="checkbox"][name^="permissions"]').prop('checked', false);
}

function selectAllEditPermissions() {
    $('#editRoleForm input[type="checkbox"][name^="permissions"]').prop('checked', true);
}

function deselectAllEditPermissions() {
    $('#editRoleForm input[type="checkbox"][name^="permissions"]').prop('checked', false);
}

// Validate role code format on add form
$('#addRoleForm').on('submit', function(e) {
    var roleCode = $('input[name="role_code"]').val();
    if (!/^[a-z_]+$/.test(roleCode)) {
        e.preventDefault();
        alert('Role code must contain only lowercase letters and underscores.');
    }
});
</script>
