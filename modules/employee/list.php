<?php
/**
 * RCS HRMS Pro - Employee List Page
 * Updated for new database schema
 */

$pageTitle = 'Employees';

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'client_id' => !empty($_GET['client_id']) ? $_GET['client_id'] : null,
    'unit_id' => !empty($_GET['unit_id']) ? $_GET['unit_id'] : null,
    'worker_category' => $_GET['worker_category'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Handle export - this runs before header.php when called via index.php export handler
if (isset($isExportRequest) || isset($_GET['export'])) {
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
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" class="row g-3" id="filterForm">
                    <input type="hidden" name="page" value="employee/list">
                    
                    <div class="col-md-2">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by name, code, mobile..." 
                               value="<?php echo sanitize($filters['search'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="Active" <?php echo $filters['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Pending" <?php echo $filters['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Inactive" <?php echo $filters['status'] === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                                <th>Emp Code</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Client / Unit</th>
                                <th>Category</th>
                                <th>DOJ</th>
                                <th>PF/ESI</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                <td>
                                    <a href="index.php?page=employee/view&id=<?php echo $emp['id']; ?>">
                                        <code><?php echo sanitize($emp['employee_code']); ?></code>
                                    </a>
                                </td>
                                <td>
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
                                <td><?php echo sanitize($emp['designation'] ?? '-'); ?></td>
                                <td>
                                    <div><small class="text-muted">Client:</small> <?php echo sanitize($emp['client_name_display'] ?? $emp['client_name'] ?? '-'); ?></div>
                                    <div><small class="text-muted">Unit:</small> <?php echo sanitize($emp['unit_name_display'] ?? $emp['unit_name'] ?? '-'); ?></div>
                                </td>
                                <td><span class="badge bg-info-soft"><?php echo sanitize($emp['worker_category'] ?? '-'); ?></span></td>
                                <td><?php echo formatDate($emp['date_of_joining']); ?></td>
                                <td>
                                    <?php if (!empty($emp['pf_applicable'])): ?><span class="badge bg-primary-soft">PF</span><?php endif; ?>
                                    <?php if (!empty($emp['esi_applicable'])): ?><span class="badge bg-success-soft">ESI</span><?php endif; ?>
                                    <?php if (empty($emp['pf_applicable']) && empty($emp['esi_applicable'])): ?><span class="text-muted">-</span><?php endif; ?>
                                </td>
                                <td>
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
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="index.php?page=employee/view&id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="index.php?page=employee/edit&id=<?php echo $emp['id']; ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteEmployee('<?php echo $emp['id']; ?>')" title="Delete">
                                            <i class="bi bi-trash"></i>
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
function deleteEmployee(id) {
    if (confirm('Are you sure you want to delete this employee?')) {
        window.location.href = 'index.php?page=employee/delete&id=' + id;
    }
}

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
</script>
JS;
?>
