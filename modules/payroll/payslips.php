<?php
/**
 * RCS HRMS Pro - Payslips Page
 * Client/Unit wise payslips with previous month default
 */

$pageTitle = 'Payslips';

// Get clients
$clients = [];
try {
    $stmt = $db->query("SELECT id, name, client_code FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist
}

// Get selected filters - default to previous month
$previousMonth = date('n') - 1;
$previousYear = date('Y');
if ($previousMonth < 1) {
    $previousMonth = 12;
    $previousYear--;
}
$selectedClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$selectedUnit = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : $previousMonth;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $previousYear;

// Get units based on selected client
$units = [];
if ($selectedClient) {
    try {
        $stmt = $db->prepare("SELECT id, name, unit_code FROM units WHERE client_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$selectedClient]);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Table doesn't exist
    }
}

// Get payroll data
$payrollData = [];
$payrollPeriod = null;

if ($selectedUnit && isset($_GET['load'])) {
    try {
        // Check if payroll exists for this period
        $periodStmt = $db->prepare("
            SELECT * FROM payroll_periods 
            WHERE month = ? AND year = ? AND unit_id = ?
        ");
        $periodStmt->execute([$selectedMonth, $selectedYear, $selectedUnit]);
        $payrollPeriod = $periodStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payrollPeriod) {
            // Get payroll records with employee details
            $stmt = $db->prepare("
                SELECT pr.*, e.full_name, e.worker_category, e.designation,
                       e.bank_name, e.account_number, e.ifsc_code
                FROM payroll_records pr
                LEFT JOIN employees e ON e.employee_code = pr.employee_id
                WHERE pr.period_id = ?
                ORDER BY e.employee_code
            ");
            $stmt->execute([$payrollPeriod['id']]);
            $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching payslips: " . $e->getMessage());
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-file-text me-2"></i>Payslips</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="payroll/payslips">
                    
                    <div class="col-md-3">
                        <label class="form-label">Client</label>
                        <select class="form-select" name="client_id" id="clientSelect" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $selectedClient == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Unit</label>
                        <select class="form-select" name="unit_id" id="unitSelect" required>
                            <option value="">Select Unit</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $selectedUnit == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
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
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 2; $y--):
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="load" value="1" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Load
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedUnit && isset($_GET['load'])): ?>
<!-- Payslips List -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-primary ms-2"><?php echo count($payrollData); ?> Employees</span>
                    <?php if ($payrollPeriod): ?>
                    <span class="badge bg-success ms-2"><?php echo $payrollPeriod['status']; ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($payrollData)): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="printSelectedPayslips()">
                        <i class="bi bi-printer me-1"></i>Print Selected
                    </button>
                    <?php endif; ?>
                    <a href="index.php?page=payroll/payslips" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (!$payrollPeriod): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-exclamation-circle fs-1"></i>
                    <h5 class="mt-3">Payroll Not Processed</h5>
                    <p>No payroll found for this period. Please process payroll first.</p>
                    <a href="index.php?page=payroll/process&client_id=<?php echo $selectedClient; ?>&unit_id=<?php echo $selectedUnit; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&load=1" class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-cash-stack me-1"></i>Go to Wage Register
                    </a>
                </div>
                <?php elseif (empty($payrollData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-text fs-1"></i>
                    <h5 class="mt-3">No Payslips Found</h5>
                    <p>No payslips available for the selected criteria.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAll" checked>
                                </th>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Category</th>
                                <th class="text-end">Days</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net Pay</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payrollData as $p): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input payslip-check" 
                                           value="<?php echo $p['id']; ?>" checked>
                                </td>
                                <td><code><?php echo $p['employee_id']; ?></code></td>
                                <td><?php echo sanitize($p['full_name']); ?></td>
                                <td><?php echo sanitize($p['designation']); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo sanitize($p['worker_category']); ?></span></td>
                                <td class="text-end"><?php echo $p['paid_days']; ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($p['gross_earnings']); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($p['total_deductions']); ?></td>
                                <td class="text-end fw-bold text-success"><?php echo number_format($p['net_pay']); ?></td>
                                <td>
                                    <a href="index.php?page=payroll/print_payslip&id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="index.php?page=payroll/print_payslip&id=<?php echo $p['id']; ?>&print=1" 
                                       class="btn btn-sm btn-outline-success" target="_blank" title="Print">
                                        <i class="bi bi-printer"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraJS = <<<'JS'
<script>
// Load units when client changes
document.getElementById('clientSelect').addEventListener('change', function() {
    const clientId = this.value;
    const unitSelect = document.getElementById('unitSelect');
    
    unitSelect.innerHTML = '<option value="">Loading...</option>';
    
    if (!clientId) {
        unitSelect.innerHTML = '<option value="">Select Unit</option>';
        return;
    }
    
    fetch('index.php?page=api/units&client_id=' + clientId)
        .then(response => response.json())
        .then(data => {
            unitSelect.innerHTML = '<option value="">Select Unit</option>';
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
            unitSelect.innerHTML = '<option value="">Select Unit</option>';
        });
});

// Select all checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    document.querySelectorAll('.payslip-check').forEach(cb => {
        cb.checked = this.checked;
    });
});

// Print selected payslips
function printSelectedPayslips() {
    const selected = [];
    document.querySelectorAll('.payslip-check:checked').forEach(cb => {
        selected.push(cb.value);
    });
    
    if (selected.length === 0) {
        alert('Please select at least one payslip');
        return;
    }
    
    window.open('index.php?page=payroll/print_payslips&ids=' + selected.join(','), '_blank');
}
</script>
JS;
?>
