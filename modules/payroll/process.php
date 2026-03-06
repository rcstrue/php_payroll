<?php
/**
 * RCS HRMS Pro - Payroll Processing Page
 * Updated for new database schema
 */

$pageTitle = 'Process Payroll';

// Get all periods
$periods = $payroll->getPeriods();

// Get current month/year
$currentMonth = date('n');
$currentYear = date('Y');

// Handle create period
if (isset($_POST['create_period'])) {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    $result = $payroll->createPeriod($month, $year);
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll period created successfully!');
        redirect('index.php?page=payroll/process&period_id=' . $result['period_id']);
    } else {
        setFlash('error', $result['message'] ?? 'Failed to create period');
    }
}

// Handle process payroll
if (isset($_POST['process_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->processPayroll($periodId);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', "Payroll processed for {$result['processed']} employees!");
    } else {
        setFlash('error', $result['message'] ?? 'Payroll processing failed');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle approve payroll
if (isset($_POST['approve_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->approvePayroll($periodId, $_SESSION['user_id']);
    setFlash('success', 'Payroll approved successfully!');
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle delete payroll
if (isset($_POST['delete_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->deletePayroll($periodId);
    setFlash('success', 'Payroll deleted successfully!');
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Get selected period
$selectedPeriod = null;
$payrollData = [];
$totals = null;

if (isset($_GET['period_id'])) {
    $stmt = $db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
    $stmt->execute([(int)$_GET['period_id']]);
    $selectedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedPeriod) {
        $payrollData = $payroll->getPayrollReport($selectedPeriod['id']);
        $totals = $payroll->getPeriodSummary($selectedPeriod['id']);
    }
}
?>

<div class="row">
    <!-- Period Selection -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar me-2"></i>Payroll Periods</h5>
            </div>
            <div class="card-body">
                <!-- Create New Period -->
                <form method="POST" class="mb-4">
                    <div class="row g-2">
                        <div class="col-5">
                            <select class="form-select" name="month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <select class="form-select" name="year">
                                <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <button type="submit" name="create_period" class="btn btn-primary w-100">
                                <i class="bi bi-plus"></i> Create
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Period List -->
                <div class="list-group">
                    <?php foreach ($periods as $p): ?>
                    <a href="index.php?page=payroll/process&period_id=<?php echo $p['id']; ?>" 
                       class="list-group-item list-group-item-action <?php echo $selectedPeriod && $selectedPeriod['id'] == $p['id'] ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium"><?php echo sanitize($p['period_name']); ?></div>
                                <small><?php echo $p['pay_days']; ?> days</small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $p['status'] === 'Draft' ? 'secondary' : 
                                    ($p['status'] === 'Processed' ? 'success' : 
                                    ($p['status'] === 'Approved' ? 'primary' : 
                                    ($p['status'] === 'Paid' ? 'info' : 'warning'))); 
                            ?>"><?php echo sanitize($p['status']); ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if (empty($periods)): ?>
                    <div class="text-center py-4 text-muted">No payroll periods created yet</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payroll Details -->
    <div class="col-lg-8">
        <?php if (!$selectedPeriod): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-event fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Select a Payroll Period</h5>
                <p class="text-muted">Choose a period from the left to view or process payroll</p>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cash-stack me-2"></i>
                    Payroll - <?php echo sanitize($selectedPeriod['period_name']); ?>
                </h5>
                <div class="card-actions">
                    <?php if ($selectedPeriod['status'] === 'Draft'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="process_payroll" class="btn btn-primary btn-sm"
                                onclick="return confirm('Process payroll for this period?')">
                            <i class="bi bi-play-fill me-1"></i>Process Payroll
                        </button>
                    </form>
                    <?php elseif ($selectedPeriod['status'] === 'Processed'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="approve_payroll" class="btn btn-success btn-sm"
                                onclick="return confirm('Approve payroll for this period?')">
                            <i class="bi bi-check-lg me-1"></i>Approve
                        </button>
                        <button type="submit" name="delete_payroll" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Delete payroll and re-process?')">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Re-process
                        </button>
                    </form>
                    <?php elseif ($selectedPeriod['status'] === 'Approved'): ?>
                    <a href="index.php?page=payroll/payslips&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-success btn-sm">
                        <i class="bi bi-file-text me-1"></i>View Payslips
                    </a>
                    <a href="index.php?page=payroll/bank-advice&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-bank me-1"></i>Bank Advice
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($totals && $totals['employee_count'] > 0): ?>
            <div class="card-body border-bottom">
                <div class="row text-center">
                    <div class="col-md-2 mb-3 mb-md-0">
                        <div class="small text-muted">Employees</div>
                        <div class="h4 mb-0"><?php echo number_format($totals['employee_count']); ?></div>
                    </div>
                    <div class="col-md-2 mb-3 mb-md-0">
                        <div class="small text-muted">Gross</div>
                        <div class="h4 mb-0 text-primary"><?php echo formatCurrency($totals['total_gross']); ?></div>
                    </div>
                    <div class="col-md-2 mb-3 mb-md-0">
                        <div class="small text-muted">Deductions</div>
                        <div class="h4 mb-0 text-danger"><?php echo formatCurrency($totals['total_deductions']); ?></div>
                    </div>
                    <div class="col-md-3 mb-3 mb-md-0">
                        <div class="small text-muted">Net Pay</div>
                        <div class="h4 mb-0 text-success"><?php echo formatCurrency($totals['total_net_pay']); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="small text-muted">Total CTC</div>
                        <div class="h4 mb-0"><?php echo formatCurrency($totals['total_ctc']); ?></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Emp Code</th>
                                <th>Name</th>
                                <th>Client/Unit</th>
                                <th>Paid Days</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payrollData)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No payroll data. Click "Process Payroll" to generate.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payrollData as $row): ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_id']); ?></code></td>
                                <td><?php echo sanitize($row['full_name'] ?? '-'); ?></td>
                                <td>
                                    <small><?php echo sanitize($row['client_name'] ?? '-'); ?> / <?php echo sanitize($row['unit_name'] ?? '-'); ?></small>
                                </td>
                                <td><?php echo $row['paid_days'] ?? 0; ?></td>
                                <td><?php echo formatCurrency($row['gross_earnings'] ?? 0); ?></td>
                                <td class="text-danger"><?php echo formatCurrency($row['total_deductions'] ?? 0); ?></td>
                                <td class="fw-bold text-success"><?php echo formatCurrency($row['net_pay'] ?? 0); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $row['status'] === 'Paid' ? 'success' : 'warning'; ?>">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statutory Summary -->
        <?php if ($totals && $totals['employee_count'] > 0): ?>
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Statutory Summary</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Provident Fund</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Employee Share</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_pf_employee'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer Share (EPF)</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_pf_employer'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer Share (EPS)</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_eps_employer'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>EDLIS</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['edli_contribution'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Admin Charges</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['epf_admin_charges'] ?? 0); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-bold">Total PF</td>
                                <td class="text-end fw-bold text-primary">
                                    <?php echo formatCurrency(($totals['total_pf_employee'] ?? 0) + ($totals['total_pf_employer'] ?? 0) + ($totals['total_eps_employer'] ?? 0)); ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">ESI & Others</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>ESI (Employee)</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_esi_employee'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>ESI (Employer)</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_esi_employer'] ?? 0); ?></td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-bold">Total ESI</td>
                                <td class="text-end fw-bold text-primary">
                                    <?php echo formatCurrency(($totals['total_esi_employee'] ?? 0) + ($totals['total_esi_employer'] ?? 0)); ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Professional Tax</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_pt'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer Contribution</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_employer_contribution'] ?? 0); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="index.php?page=compliance/pf&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>Generate ECR
                    </a>
                    <a href="index.php?page=compliance/esi&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-outline-success btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i>ESI Return
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
