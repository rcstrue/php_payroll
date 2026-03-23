<?php
/**
 * RCS HRMS Pro - Menu Permissions Management (Enhanced)
 * Allows admin to control:
 * - Which menus each role can see
 * - Which submenus each role can see  
 * - What actions (view, add, edit, delete, export, import, print) each role can perform
 */

$pageTitle = 'Menu Permissions';

// Only admin can access this page
if (!in_array($_SESSION['role_code'], ['admin'])) {
    setFlash('error', 'Access denied. Admin only.');
    redirect('index.php?page=dashboard');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken()) {
        setFlash('error', 'Invalid request. Please try again.');
    } else {
        $roleId = isset($_POST['role_id']) ? (int)$_POST['role_id'] : 0;
        $action = $_POST['form_action'] ?? 'save_permissions';
        
        if ($roleId > 0) {
            if ($action === 'save_permissions') {
                // Save all permissions at once
                $menus = $auth->getAllMenus();
                $actionTypes = $auth->getActionTypes();
                
                foreach ($menus as $menuKey => $menuInfo) {
                    // Save main menu visibility
                    $isVisible = isset($_POST['menus'][$menuKey]) ? 1 : 0;
                    $menuActions = [];
                    foreach ($actionTypes as $actionKey => $actionInfo) {
                        $menuActions[$actionKey] = isset($_POST['actions'][$menuKey][$actionKey]) ? 1 : 0;
                    }
                    $menuActions['is_visible'] = $isVisible;
                    $auth->saveRoleMenuPermissions($roleId, $menuActions, $menuKey, null);
                    
                    // Save submenu permissions
                    if (!empty($menuInfo['submenus'])) {
                        foreach ($menuInfo['submenus'] as $submenuKey => $submenuInfo) {
                            $submenuVisible = isset($_POST['submenus'][$submenuKey]) ? 1 : 0;
                            $submenuActions = [];
                            foreach ($actionTypes as $actionKey => $actionInfo) {
                                $submenuActions[$actionKey] = isset($_POST['actions'][$submenuKey][$actionKey]) ? 1 : 0;
                            }
                            $submenuActions['is_visible'] = $submenuVisible;
                            $auth->saveDetailedPermissions($roleId, $menuKey, $submenuKey, $submenuActions);
                        }
                    }
                }
                setFlash('success', 'Permissions saved successfully!');
            }
        } else {
            setFlash('error', 'Invalid role selected.');
        }
        
        redirect('index.php?page=settings/menu-permissions');
    }
}

// Get all roles and their permissions
$allPermissions = $auth->getAllRoleMenuPermissions();
$menus = $auth->getAllMenus();
$actionTypes = $auth->getActionTypes();
$flash = getFlash();
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0"><i class="bi bi-list-check me-2"></i>Menu Permissions</h4>
            <span class="text-muted">Configure menus, submenus and action permissions for each role</span>
        </div>
        
        <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
            <?php echo sanitize($flash['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Info Card -->
        <div class="card mb-4 border-0 bg-light">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <i class="bi bi-info-circle-fill text-primary fs-4"></i>
                    </div>
                    <div class="col">
                        <strong>How Permission System Works:</strong>
                        <ul class="mb-0 mt-2 small">
                            <li><strong>Menu Visibility:</strong> Check to show the main menu to this role</li>
                            <li><strong>Submenu Visibility:</strong> Check to show individual submenu items</li>
                            <li><strong>Action Permissions:</strong> Control what actions users can perform (View, Add, Edit, Delete, Export, Import, Print)</li>
                            <li><strong>Admin Role:</strong> Always has full access (cannot be restricted)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Role Tabs -->
        <div class="card">
            <div class="card-header bg-white">
                <ul class="nav nav-tabs card-header-tabs" id="roleTabs" role="tablist">
                    <?php 
                    $firstRole = true;
                    foreach ($allPermissions as $roleCode => $roleData): 
                        if ($roleCode === 'admin') continue; // Skip admin tab
                    ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $firstRole ? 'active' : ''; ?>" 
                                id="tab-<?php echo $roleCode; ?>" 
                                data-bs-toggle="tab" 
                                data-bs-target="#panel-<?php echo $roleCode; ?>" 
                                type="button" 
                                role="tab">
                            <?php echo sanitize($roleData['role_name']); ?>
                        </button>
                    </li>
                    <?php 
                        $firstRole = false;
                    endforeach; 
                    ?>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="roleTabContent">
                    <?php 
                    $firstRole = true;
                    foreach ($allPermissions as $roleCode => $roleData): 
                        if ($roleCode === 'admin') continue; // Skip admin
                    ?>
                    <div class="tab-pane fade <?php echo $firstRole ? 'show active' : ''; ?>" 
                         id="panel-<?php echo $roleCode; ?>" 
                         role="tabpanel">
                        
                        <form method="POST" class="permission-form">
                            <?php echo getCSRFTokenField(); ?>
                            <input type="hidden" name="role_id" value="<?php echo $roleData['role_id']; ?>">
                            <input type="hidden" name="form_action" value="save_permissions">
                            
                            <!-- Quick Actions -->
                            <div class="mb-4 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="selectAllInForm(this)">
                                    <i class="bi bi-check-all me-1"></i>Select All
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deselectAllInForm(this)">
                                    <i class="bi bi-x-lg me-1"></i>Deselect All
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm ms-auto">
                                    <i class="bi bi-save me-1"></i>Save Permissions
                                </button>
                            </div>
                            
                            <!-- Permission Matrix -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="min-width: 250px;">Menu / Submenu</th>
                                            <th class="text-center" style="width: 60px;" title="Visibility">
                                                <i class="bi bi-eye"></i><br><small>Visible</small>
                                            </th>
                                            <?php foreach ($actionTypes as $actionKey => $actionInfo): ?>
                                            <th class="text-center" style="width: 60px;" title="<?php echo $actionInfo['description']; ?>">
                                                <i class="<?php echo $actionInfo['icon']; ?>"></i><br><small><?php echo $actionInfo['label']; ?></small>
                                            </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($menus as $menuKey => $menuInfo): ?>
                                        <!-- Main Menu Row -->
                                        <tr class="table-primary">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <i class="bi <?php echo $menuInfo['icon']; ?> me-2 fs-5"></i>
                                                    <strong><?php echo sanitize($menuInfo['label']); ?></strong>
                                                    <?php if (!empty($menuInfo['submenus'])): ?>
                                                    <button type="button" class="btn btn-sm btn-link ms-auto" onclick="toggleSubmenus(this)">
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input menu-visible-check"
                                                           name="menus[<?php echo $menuKey; ?>]"
                                                           id="menu-<?php echo $roleCode; ?>-<?php echo $menuKey; ?>"
                                                           value="1"
                                                           data-menu="<?php echo $menuKey; ?>"
                                                           <?php echo !empty($roleData['menus'][$menuKey]) ? 'checked' : ''; ?>
                                                           onchange="updateMenuVisibility(this)">
                                                </div>
                                            </td>
                                            <?php foreach ($actionTypes as $actionKey => $actionInfo): ?>
                                            <td class="text-center">
                                                <div class="form-check d-flex justify-content-center">
                                                    <input type="checkbox" 
                                                           class="form-check-input action-check"
                                                           name="actions[<?php echo $menuKey; ?>][<?php echo $actionKey; ?>]"
                                                           id="action-<?php echo $roleCode; ?>-<?php echo $menuKey; ?>-<?php echo $actionKey; ?>"
                                                           value="1"
                                                           <?php echo !empty($roleData['actions'][$menuKey][$actionKey]) ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <?php endforeach; ?>
                                        </tr>
                                        
                                        <!-- Submenu Rows -->
                                        <?php if (!empty($menuInfo['submenus'])): ?>
                                            <?php foreach ($menuInfo['submenus'] as $submenuKey => $submenuInfo): ?>
                                            <tr class="submenu-row" data-parent="<?php echo $menuKey; ?>">
                                                <td style="padding-left: 3rem;">
                                                    <i class="bi bi-arrow-return-right me-2 text-muted"></i>
                                                    <?php echo sanitize($submenuInfo['label']); ?>
                                                    <small class="text-muted ms-2"><?php echo $submenuInfo['url']; ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="checkbox" 
                                                               class="form-check-input submenu-visible-check"
                                                               name="submenus[<?php echo $submenuKey; ?>]"
                                                               id="submenu-<?php echo $roleCode; ?>-<?php echo $submenuKey; ?>"
                                                               value="1"
                                                               data-parent-menu="<?php echo $menuKey; ?>"
                                                               <?php echo !empty($roleData['submenus'][$submenuKey]['is_visible']) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <?php foreach ($actionTypes as $actionKey => $actionInfo): ?>
                                                <td class="text-center">
                                                    <div class="form-check d-flex justify-content-center">
                                                        <input type="checkbox" 
                                                               class="form-check-input submenu-action-check"
                                                               name="actions[<?php echo $submenuKey; ?>][<?php echo $actionKey; ?>]"
                                                               id="action-<?php echo $roleCode; ?>-<?php echo $submenuKey; ?>-<?php echo $actionKey; ?>"
                                                               value="1"
                                                               <?php echo !empty($roleData['submenus'][$submenuKey][$actionKey]) ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <?php endforeach; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                    <?php 
                        $firstRole = false;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Legend -->
        <div class="card mt-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-legend me-2"></i>Action Legend</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($actionTypes as $actionKey => $actionInfo): ?>
                    <div class="col-md-3 col-sm-6 mb-2">
                        <div class="d-flex align-items-center">
                            <i class="bi <?php echo $actionInfo['icon']; ?> me-2 fs-5 text-primary"></i>
                            <div>
                                <strong><?php echo $actionInfo['label']; ?></strong><br>
                                <small class="text-muted"><?php echo $actionInfo['description']; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle submenu visibility
function toggleSubmenus(btn) {
    const row = btn.closest('tr');
    const menuKey = row.querySelector('.menu-visible-check').dataset.menu;
    const submenuRows = document.querySelectorAll(`tr.submenu-row[data-parent="${menuKey}"]`);
    
    submenuRows.forEach(subRow => {
        subRow.style.display = subRow.style.display === 'none' ? '' : 'none';
    });
    
    // Toggle icon
    const icon = btn.querySelector('i');
    icon.classList.toggle('bi-chevron-down');
    icon.classList.toggle('bi-chevron-right');
}

// When main menu visibility is unchecked, disable submenu checkboxes
function updateMenuVisibility(checkbox) {
    const menuKey = checkbox.dataset.menu;
    const form = checkbox.closest('form');
    
    if (!checkbox.checked) {
        // Uncheck all submenus under this menu
        form.querySelectorAll(`.submenu-visible-check[data-parent-menu="${menuKey}"]`).forEach(cb => {
            cb.checked = false;
        });
    }
}

// Select all checkboxes in the form
function selectAllInForm(btn) {
    const form = btn.closest('form');
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
}

// Deselect all checkboxes in the form
function deselectAllInForm(btn) {
    const form = btn.closest('form');
    form.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
}
</script>

<style>
.submenu-row {
    background-color: #f8f9fa;
}
.submenu-row td {
    border-top: 1px dashed #dee2e6;
}
.table-primary td {
    border-top: 2px solid #0d6efd;
}
.form-check-input {
    cursor: pointer;
}
.nav-tabs .nav-link {
    color: #495057;
}
.nav-tabs .nav-link.active {
    font-weight: bold;
}
</style>
