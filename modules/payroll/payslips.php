<?php
/**
 * RCS HRMS Pro - Payslips Page
 * Updated for new database schema
 *
 * IMPORTANT: employees table does NOT have client_name or unit_name columns.
 * Always use JOINs: LEFT JOIN units u ON e.unit_id = u.id
 * Select as aliases: u.name AS unit_name
 */

$pageTitle = 'Payslips';

// Get period
$periodId = $_GET['period_id'] ?? null;
$unitName = $_GET['unit_name'] ?? null;

// Get periods
$periods = $payroll->getPeriods();

// Get units - use JOIN with units table since employees has unit_id, not unit_name
$stmt = $db->query("SELECT DISTINCT u.name as unit_name FROM employees e LEFT JOIN units u ON e.unit_id = u.id WHERE u.name IS NOT NULL AND u.name != '' ORDER BY u.name");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

$payslips = [];
$selectedPeriod = null;

if ($periodId) {
    $stmt = $db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
    $stmt->execute([(int)$periodId]);
    $selectedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $filters = [];
    if ($unitName) {
        $filters['unit_name'] = $unitName;
    }
    
    $payslips = $payroll->getPayrollReport((int)$periodId, $filters);
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-file-text me-2"></i>Payslips</h5>
                <div class="card-actions">
                    <?php if ($payslips): ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="printAllPayslips()">
                        <i class="bi bi-printer me-1"></i>Print All
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="page" value="payroll/payslips">
                    
                    <div class="col-md-4">
                        <select class="form-select" name="period_id" required>
                            <option value="">Select Period</option>
                            <?php foreach ($periods as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo $periodId == $p['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($p['period_name']); ?> (<?php echo sanitize($p['status']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <select class="form-select" name="unit_name">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo sanitize($u['unit_name']); ?>" <?php echo $unitName == $u['unit_name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['unit_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>View Payslips
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Payslip List -->
            <div class="card-body">
                <?php if (!$selectedPeriod): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-text fs-1"></i>
                    <h5 class="mt-3">Select a Payroll Period</h5>
                    <p>Choose a processed period to view payslips</p>
                </div>
                <?php elseif (empty($payslips)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-exclamation-circle fs-1"></i>
                    <h5 class="mt-3">No Payslips Found</h5>
                    <p>No payslips available for the selected criteria</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" class="form-check-input" id="selectAll" checked>
                                </th>
                                <th>Emp Code</th>
                                <th>Name</th>
                                <th>Client/Unit</th>
                                <th>Paid Days</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payslips as $p): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input payslip-check" 
                                           value="<?php echo $p['id']; ?>" checked>
                                </td>
                                <td><code><?php echo sanitize($p['employee_id']); ?></code></td>
                                <td><?php echo sanitize($p['full_name'] ?? '-'); ?></td>
                                <td>
                                    <small><?php echo sanitize($p['client_name'] ?? '-'); ?> / <?php echo sanitize($p['unit_name'] ?? '-'); ?></small>
                                </td>
                                <td><?php echo $p['paid_days'] ?? 0; ?></td>
                                <td><?php echo formatCurrency($p['gross_earnings'] ?? 0); ?></td>
                                <td class="text-danger"><?php echo formatCurrency($p['total_deductions'] ?? 0); ?></td>
                                <td class="fw-bold text-success"><?php echo formatCurrency($p['net_pay'] ?? 0); ?></td>
                                <td>
                                    <a href="index.php?page=payroll/print_payslip&id=<?php echo $p['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="index.php?page=payroll/print_payslip&id=<?php echo $p['id']; ?>&print=1" 
                                       class="btn btn-sm btn-outline-success" target="_blank">
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

<script>
function printAllPayslips() {
    const selected = [];
    $('.payslip-check:checked').each(function() {
        selected.push($(this).val());
    });
    
    if (selected.length === 0) {
        alert('Please select at least one payslip');
        return;
    }
    
    window.open('index.php?page=payroll/print_payslips&ids=' + selected.join(','), '_blank');
}

$('#selectAll').on('change', function() {
    $('.payslip-check').prop('checked', $(this).prop('checked'));
});
</script>
