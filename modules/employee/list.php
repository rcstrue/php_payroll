<?php
/**
 * RCS HRMS Pro - Employee List Page
 * Updated for new database schema
 *
 * NOTE: Column visibility dropdown added - users can toggle columns on/off
 * Preferences are saved in localStorage for persistence
 */

$pageTitle = 'Employees';

// Define available columns for the visibility dropdown
// NOTE: Add new columns here when adding new fields to the employee table
$availableColumns = [
    'employee_code' => 'Employee Code',
    'full_name' => 'Name',
    'designation' => 'Designation',
    'client_unit' => 'Client / Unit',
    'worker_category' => 'Category',
    'date_of_joining' => 'DOJ',
    'pf_esi' => 'PF/ESI',
    'mobile_number' => 'Mobile',
    'email' => 'Email',
    'status' => 'Status',
    'actions' => 'Actions'
];

// Get filters - default to 'approved' (active) employees
// Sanitize all user inputs to prevent XSS
$filters = [
    'status' => sanitize($_GET['status'] ?? 'approved'), // Default to approved/active
    'client_id' => !empty($_GET['client_id']) ? (int)$_GET['client_id'] : null,
    'unit_id' => !empty($_GET['unit_id']) ? (int)$_GET['unit_id'] : null,
    'worker_category' => sanitize($_GET['worker_category'] ?? ''),
    'search' => sanitize($_GET['search'] ?? '')
];

// Handle export - check if we can set headers (export should be handled before header.php)
if ((isset($isExportRequest) || isset($_GET['export'])) && !headers_sent()) {
    // Get all employees for export (no pagination)
    $result = $employee->getAll($filters, 1, 10000);
    $exportData = $result['data'];
    
    // Set headers for Excel download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="employees_export_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV with BOM for Excel
    echo "\xEF\xBB\xBF";
    
    // CSV headers
    $headers = [
        'Employee Code',
        'Full Name',
        'Father Name',
        'Date of Birth',
        'Gender',
        'Mobile Number',
        'Alternate Mobile',
        'Email',
        'Aadhaar Number',
        'UAN Number',
        'ESIC Number',
        'Address',
        'Pin Code',
        'State',
        'District',
        'Bank Name',
        'Account Number',
        'IFSC Code',
        'Client Name',
        'Unit Name',
        'Designation',
        'Department',
        'Worker Category',
        'Employment Type',
        'Date of Joining',
        'Date of Leaving',
        'Status',
        'PF Applicable',
        'ESI Applicable',
        'Basic Wage',
        'Gross Salary',
        'Nominee Name',
        'Nominee Relationship',
        'Emergency Contact Name',
        'Emergency Contact Relation'
    ];
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    
    foreach ($exportData as $emp) {
        $row = [
            $emp['employee_code'] ?? '',
            $emp['full_name'] ?? '',
            $emp['father_name'] ?? '',
            $emp['date_of_birth'] ?? '',
            $emp['gender'] ?? '',
            $emp['mobile_number'] ?? '',
            $emp['alternate_mobile'] ?? '',
            $emp['email'] ?? '',
            $emp['aadhaar_number'] ?? '',
            $emp['uan_number'] ?? '',
            $emp['esic_number'] ?? '',
            $emp['address'] ?? '',
            $emp['pin_code'] ?? '',
            $emp['state'] ?? '',
            $emp['district'] ?? '',
            $emp['bank_name'] ?? '',
            $emp['account_number'] ?? '',
            $emp['ifsc_code'] ?? '',
            $emp['client_name_display'] ?? $emp['client_name'] ?? '',
            $emp['unit_name_display'] ?? $emp['unit_name'] ?? '',
            $emp['designation'] ?? '',
            $emp['department'] ?? '',
            $emp['worker_category'] ?? '',
            $emp['employment_type'] ?? '',
            $emp['date_of_joining'] ?? '',
            $emp['date_of_leaving'] ?? '',
            $emp['status'] ?? '',
            !empty($emp['pf_applicable']) ? 'Yes' : 'No',
            !empty($emp['esi_applicable']) ? 'Yes' : 'No',
            $emp['basic_wage'] ?? '',
            $emp['gross_salary'] ?? '',
            $emp['nominee_name'] ?? '',
            $emp['nominee_relationship'] ?? '',
            $emp['emergency_contact_name'] ?? '',
            $emp['emergency_contact_relation'] ?? ''
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// Get employees
$page = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$result = $employee->getAll($filters, $page, 50);
$employees = $result['data'];
$totalPages = $result['total_pages'];
$total = $result['total'];

// Get clients for filter (with error handling)
$clients = [];
try {
    $stmt = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist
}

// Get units for filter based on selected client
$units = [];
$filterClientId = $filters['client_id'];
try {
    if ($filterClientId) {
        $stmt = $db->prepare("SELECT id, name, client_id FROM units WHERE client_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$filterClientId]);
    } else {
        $stmt = $db->query("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name");
    }
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Employee List</h5>
                <div class="card-actions">
                    <button type="button" class="btn btn-success btn-sm" onclick="exportEmployees()">
                        <i class="bi bi-download me-1"></i>Export All
                    </button>
                    <a href="index.php?page=employee/add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i>Add Employee
                    </a>
                    <a href="index.php?page=employee/import" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-upload me-1"></i>Import
                    </a>
                    <!-- Column Visibility Dropdown -->
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-layout-three-columns me-1"></i>Columns
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end p-2" style="min-width: 200px;">
                            <li><small class="text-muted d-block mb-2">Toggle column visibility:</small></li>
                            <?php foreach ($availableColumns as $colKey => $colLabel): ?>
                            <li>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input column-toggle" id="col_<?php echo $colKey; ?>" 
                                           data-column="<?php echo $colKey; ?>" checked>
                                    <label class="form-check-label" for="col_<?php echo $colKey; ?>"><?php echo $colLabel; ?></label>
                                </div>
                            </li>
                            <?php endforeach; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="resetColumnVisibility()">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to Default
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" class="row g-3" id="filterForm">
                    <input type="hidden" name="page" value="employee/list">
                    
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by name, code, mobile..." 
                               value="<?php echo htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="approved" <?php echo $filters['status'] === 'approved' ? 'selected' : ''; ?>>Active</option>
                            <option value="" <?php echo $filters['status'] === '' ? 'selected' : ''; ?>>All Status</option>
                            <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="removed" <?php echo $filters['status'] === 'removed' ? 'selected' : ''; ?>>Removed</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="client_id" id="clientFilter" onchange="filterUnits()">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filters['client_id'] == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="unit_id" id="unitFilter">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filters['unit_id'] == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="worker_category">
                            <option value="">All Categories</option>
                            <option value="Skilled" <?php echo $filters['worker_category'] === 'Skilled' ? 'selected' : ''; ?>>Skilled</option>
                            <option value="Semi-Skilled" <?php echo $filters['worker_category'] === 'Semi-Skilled' ? 'selected' : ''; ?>>Semi-Skilled</option>
                            <option value="Unskilled" <?php echo $filters['worker_category'] === 'Unskilled' ? 'selected' : ''; ?>>Unskilled</option>
                            <option value="Supervisor" <?php echo $filters['worker_category'] === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            <option value="Manager" <?php echo $filters['worker_category'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                        <a href="index.php?page=employee/list" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Employee Table -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="employees-table">
                        <thead>
                            <tr>
                                <th data-column="employee_code">Emp Code</th>
                                <th data-column="full_name">Name</th>
                                <th data-column="designation">Designation</th>
                                <th data-column="client_unit">Client / Unit</th>
                                <th data-column="worker_category">Category</th>
                                <th data-column="date_of_joining">DOJ</th>
                                <th data-column="pf_esi">PF/ESI</th>
                                <th data-column="status">Status</th>
                                <th data-column="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    No employees found. <a href="index.php?page=employee/add">Add first employee</a>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td data-column="employee_code">
                                    <a href="index.php?page=employee/view&id=<?php echo $emp['id']; ?>">
                                        <code><?php echo sanitize($emp['employee_code']); ?></code>
                                    </a>
                                </td>
                                <td data-column="full_name">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-size:12px;">
                                            <?php echo substr($emp['full_name'] ?? 'U', 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo sanitize($emp['full_name'] ?? '-'); ?></div>
                                            <small class="text-muted"><?php echo sanitize($emp['mobile_number'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td data-column="designation"><?php echo sanitize($emp['designation'] ?? '-'); ?></td>
                                <td data-column="client_unit">
                                    <div><small class="text-muted">Client:</small> <?php echo sanitize($emp['client_name_display'] ?? $emp['client_name'] ?? '-'); ?></div>
                                    <div><small class="text-muted">Unit:</small> <?php echo sanitize($emp['unit_name_display'] ?? $emp['unit_name'] ?? '-'); ?></div>
                                </td>
                                <td data-column="worker_category"><span class="badge bg-info-soft"><?php echo sanitize($emp['worker_category'] ?? '-'); ?></span></td>
                                <td data-column="date_of_joining"><?php echo formatDate($emp['date_of_joining']); ?></td>
                                <td data-column="pf_esi">
                                    <?php if (!empty($emp['pf_applicable'])): ?><span class="badge bg-primary-soft">PF</span><?php endif; ?>
                                    <?php if (!empty($emp['esi_applicable'])): ?><span class="badge bg-success-soft">ESI</span><?php endif; ?>
                                    <?php if (empty($emp['pf_applicable']) && empty($emp['esi_applicable'])): ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td data-column="status">
                                    <?php 
                                    $statusClass = 'secondary';
                                    $statusText = $emp['status'] ?? 'Unknown';
                                    if ($statusText === 'approved') {
                                        $statusClass = 'success';
                                        $statusText = 'Active';
                                    } elseif (strpos($statusText, 'pending') !== false) {
                                        $statusClass = 'warning';
                                        $statusText = 'Pending';
                                    } elseif ($statusText === 'inactive' || $statusText === 'terminated') {
                                        $statusClass = 'danger';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>-soft"><?php echo sanitize($statusText); ?></span>
                                </td>
                                <td data-column="actions">
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?page=employee/view&id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index.php?page=employee/edit&id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-warning" 
                                                onclick="removeEmployee('<?php echo $emp['id']; ?>')" title="Remove">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Showing <?php echo number_format(($page - 1) * 50 + 1); ?> to 
                        <?php echo number_format(min($page * 50, $total)); ?> of 
                        <?php echo number_format($total); ?> employees
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=employee/list&pg=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=employee/list&pg=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=employee/list&pg=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Define global JS functions (will be placed outside document.ready)
$extraJS = <<<'JS'
<script>
// Global functions for employee list page
window.removeEmployee = function(id) {
    if (confirm('Are you sure you want to remove this employee?\n\nThe employee will be hidden from the active list but data will be preserved in the database.')) {
        window.location.href = 'index.php?page=employee/delete&id=' + id;
    }
};

function exportEmployees() {
    // Get current filter values
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams();
    
    params.append('page', 'employee/list');
    params.append('export', '1');
    
    // Add filter values
    const search = form.querySelector('[name="search"]').value;
    const status = form.querySelector('[name="status"]').value;
    const clientId = form.querySelector('[name="client_id"]').value;
    const unitId = form.querySelector('[name="unit_id"]').value;
    const workerCategory = form.querySelector('[name="worker_category"]').value;
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    if (clientId) params.append('client_id', clientId);
    if (unitId) params.append('unit_id', unitId);
    if (workerCategory) params.append('worker_category', workerCategory);
    
    window.location.href = 'index.php?' + params.toString();
}

function filterUnits() {
    const clientId = document.getElementById('clientFilter').value;
    const unitSelect = document.getElementById('unitFilter');
    
    // Clear current options
    unitSelect.innerHTML = '<option value="">Loading...</option>';
    
    // Fetch units based on client
    fetch('index.php?page=api/units&client_id=' + clientId)
        .then(response => response.json())
        .then(data => {
            unitSelect.innerHTML = '<option value="">All Units</option>';
            if (data.units) {
                data.units.forEach(unit => {
                    const option = document.createElement('option');
                    option.value = unit.id;
                    option.textContent = unit.name;
                    unitSelect.appendChild(option);
                });
            }
        })
        .catch(() => {
            unitSelect.innerHTML = '<option value="">All Units</option>';
        });
}

// Column visibility functions - save preferences to localStorage
function toggleColumn(columnKey, isVisible) {
    const table = document.getElementById('employees-table');
    
    // Toggle header
    const header = table.querySelector('th[data-column="' + columnKey + '"]');
    if (header) {
        header.style.display = isVisible ? '' : 'none';
    }
    
    // Toggle all body cells
    const cells = table.querySelectorAll('td[data-column="' + columnKey + '"]');
    cells.forEach(cell => {
        cell.style.display = isVisible ? '' : 'none';
    });
    
    // Save preference
    saveColumnPreferences();
}

function saveColumnPreferences() {
    const preferences = {};
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        preferences[checkbox.dataset.column] = checkbox.checked;
    });
    localStorage.setItem('employeeListColumnPrefs', JSON.stringify(preferences));
}

function loadColumnPreferences() {
    const saved = localStorage.getItem('employeeListColumnPrefs');
    if (saved) {
        try {
            const preferences = JSON.parse(saved);
            Object.keys(preferences).forEach(columnKey => {
                const checkbox = document.querySelector('.column-toggle[data-column="' + columnKey + '"]');
                if (checkbox) {
                    checkbox.checked = preferences[columnKey];
                    toggleColumn(columnKey, preferences[columnKey]);
                }
            });
        } catch (e) {
            console.error('Error loading column preferences:', e);
        }
    }
}

function resetColumnVisibility() {
    localStorage.removeItem('employeeListColumnPrefs');
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        checkbox.checked = true;
        toggleColumn(checkbox.dataset.column, true);
    });
}

// Initialize column toggle handlers on page load
document.addEventListener('DOMContentLoaded', function() {
    // Add change handlers to all column toggles
    document.querySelectorAll('.column-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleColumn(this.dataset.column, this.checked);
        });
    });
    
    // Load saved preferences
    loadColumnPreferences();
});
</script>
JS;
?>
