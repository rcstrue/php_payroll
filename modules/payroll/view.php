<?php
/**
 * RCS HRMS Pro - View Payroll Records
 */

$pageTitle = 'View Payroll';

// Get filter parameters
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selectedClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$selectedUnit = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;
$selectedStatus = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Get clients
$clients = [];
try {
    $stmt = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get units based on selected client
$units = [];
if ($selectedClient) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM units WHERE client_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$selectedClient]);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Build query
$where = "pp.month = ? AND pp.year = ?";
$params = [$selectedMonth, $selectedYear];

if ($selectedUnit) {
    $where .= " AND p.unit_id = ?";
    $params[] = $selectedUnit;
} elseif ($selectedClient) {
    $unitIds = array_column($units, 'id');
    if (!empty($unitIds)) {
        $where .= " AND p.unit_id IN (" . implode(',', $unitIds) . ")";
    }
}

// Get payroll records
$payrollData = [];
$totals = ['employees' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0, 'ctc' => 0];

try {
    $stmt = $db->prepare("
        SELECT p.*, e.employee_code, e.full_name, e.worker_category, 
               u.name as unit_name, c.name as client_name
        FROM payroll p
        JOIN payroll_periods pp ON p.payroll_period_id = pp.id
        LEFT JOIN employees e ON e.employee_code = p.employee_id
        LEFT JOIN units u ON u.id = p.unit_id
        LEFT JOIN clients c ON c.id = u.client_id
        WHERE {$where}
        ORDER BY e.employee_code
    ");
    $stmt->execute($params);
    $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    foreach ($payrollData as $row) {
        $totals['employees']++;
        $totals['gross'] += floatval($row['gross_earnings']);
        $totals['deductions'] += floatval($row['total_deductions']);
        $totals['net'] += floatval($row['net_pay']);
        $totals['ctc'] += floatval($row['ctc'] ?? 0);
    }
} catch (Exception $e) {
    error_log("Error fetching payroll: " . $e->getMessage());
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>View Payroll Records</h5>
            </div>
            <div class="card-body">
                <!-- Filters -->
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="payroll/view">
                    
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth == $m ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Client</label>
                        <select class="form-select" name="client_id" id="clientSelect">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClient == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <select class="form-select" name="unit_id" id="unitSelect">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $selectedUnit == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                    </div>
                </form>
                
                <!-- Summary -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="text-muted">Employees</div>
                            <div class="h4 mb-0"><?php echo $totals['employees']; ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="text-muted">Gross Earnings</div>
                            <div class="h4 mb-0 text-primary">₹<?php echo number_format($totals['gross']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="text-muted">Total Deductions</div>
                            <div class="h4 mb-0 text-danger">₹<?php echo number_format($totals['deductions']); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="text-muted">Net Pay</div>
                            <div class="h4 mb-0 text-success">₹<?php echo number_format($totals['net']); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Data Table -->
                <?php if (empty($payrollData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                    <p class="mt-2">No payroll records found for the selected period.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" style="font-size: 12px;">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Unit</th>
                                <th>Days</th>
                                <th>Basic</th>
                                <th>DA</th>
                                <th>HRA</th>
                                <th>Gross</th>
                                <th>PF</th>
                                <th>ESI</th>
                                <th>PT</th>
                                <th>Adv</th>
                                <th>Total Ded</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sr = 1; foreach ($payrollData as $row): ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td><code><?php echo $row['employee_code']; ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td class="text-center"><?php echo $row['paid_days']; ?></td>
                                <td class="text-end"><?php echo number_format($row['basic']); ?></td>
                                <td class="text-end"><?php echo number_format($row['da']); ?></td>
                                <td class="text-end"><?php echo number_format($row['hra']); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($row['gross_earnings']); ?></td>
                                <td class="text-end"><?php echo $row['pf_employee'] > 0 ? number_format($row['pf_employee']) : '-'; ?></td>
                                <td class="text-end"><?php echo $row['esi_employee'] > 0 ? number_format($row['esi_employee']) : '-'; ?></td>
                                <td class="text-end"><?php echo $row['professional_tax'] > 0 ? number_format($row['professional_tax']) : '-'; ?></td>
                                <td class="text-end"><?php echo $row['salary_advance'] > 0 ? number_format($row['salary_advance']) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo number_format($row['total_deductions']); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo number_format($row['net_pay']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] == 'Processed' ? 'success' : ($row['status'] == 'Pending' ? 'warning' : 'info'); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="index.php?page=payroll/payslips&employee_id=<?php echo $row['employee_id']; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Payslip">
                                        <i class="bi bi-file-text"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4">TOTAL</td>
                                <td></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'basic'))); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'da'))); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'hra'))); ?></td>
                                <td class="text-end"><?php echo number_format($totals['gross']); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'pf_employee'))); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'esi_employee'))); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'professional_tax'))); ?></td>
                                <td class="text-end"><?php echo number_format(array_sum(array_column($payrollData, 'salary_advance'))); ?></td>
                                <td class="text-end"><?php echo number_format($totals['deductions']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['net']); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$extraJS = <<<'JS'
<script>
// Load units when client changes
document.getElementById('clientSelect').addEventListener('change', function() {
    const clientId = this.value;
    const unitSelect = document.getElementById('unitSelect');
    
    unitSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!clientId) {
        unitSelect.innerHTML = '<option value="">All Units</option>';
        return;
    }
    
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
});
</script>
JS;
?>
