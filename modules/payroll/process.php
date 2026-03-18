<?php
/**
 * RCS HRMS Pro - Payroll Processing Page
 * Version: 2.2.0 - Enhanced with filters, bulk actions, exceptions, charts
 * 
 * IMPORTANT NOTES FOR DEVELOPERS:
 * =================================
 * 1. Always use client_id and unit_id for filtering (NOT client_name/unit_name)
 * 2. Use JOINs to get client/unit names from their respective tables
 * 3. clients table uses 'name' column
 * 4. units table uses 'name' column
 * 5. AADHAAR NUMBER SHOULD NEVER BE HIDDEN IN INTERNAL VIEWS
 * 6. Status Flow: Draft -> Processed -> Approved -> Paid/Frozen
 * 7. Frozen status prevents all modifications
 * 8. salary_hold prevents individual from being paid
 */

$pageTitle = 'Process Payroll';

// Get all periods
$periods = $payroll->getPeriods();

// Get current month/year
$currentMonth = date('n');
$currentYear = date('Y');

// Get filter values
$filterClientId = $_GET['client_id'] ?? '';
$filterUnitId = $_GET['unit_id'] ?? '';
$filterStatus = $_GET['filter_status'] ?? '';
$filterHold = $_GET['salary_hold'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Get clients and units for filters
$clients = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$units = $db->query("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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
    
    $filters = [];
    if (!empty($_POST['process_client_id'])) {
        $filters['client_id'] = (int)$_POST['process_client_id'];
    }
    if (!empty($_POST['process_unit_id'])) {
        $filters['unit_id'] = (int)$_POST['process_unit_id'];
    }
    
    $result = $payroll->processPayroll($periodId, $filters);
    
    if (isset($result['success']) && $result['success']) {
        $msg = "Payroll processed for {$result['processed']} employees!";
        if (!empty($result['exceptions'])) {
            $msg .= " ({$result['exceptions']} exceptions found)";
        }
        setFlash('success', $msg);
    } else {
        setFlash('error', $result['message'] ?? 'Payroll processing failed');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle recalculate payroll
if (isset($_POST['recalculate_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $employeeCodes = isset($_POST['employee_codes']) ? $_POST['employee_codes'] : [];
    
    $result = $payroll->recalculatePayroll($periodId, $employeeCodes);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', $result['message']);
    } else {
        setFlash('error', $result['message'] ?? 'Recalculation failed');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle approve payroll
if (isset($_POST['approve_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->approvePayroll($periodId, $_SESSION['user_id']);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll approved successfully!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to approve');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle freeze payroll
if (isset($_POST['freeze_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->freezePeriod($periodId);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll frozen successfully!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to freeze');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle unfreeze payroll
if (isset($_POST['unfreeze_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->unfreezePeriod($periodId);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll unfrozen successfully!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to unfreeze');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle mark as paid
if (isset($_POST['mark_paid']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $paymentDate = $_POST['payment_date'] ?? date('Y-m-d');
    
    $result = $payroll->markAsPaid($periodId, $paymentDate);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll marked as paid!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to mark as paid');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle delete payroll
if (isset($_POST['delete_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->deletePayroll($periodId);
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', 'Payroll deleted successfully!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to delete');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle hold salary
if (isset($_POST['hold_salary']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $employeeCodes = $_POST['hold_employees'] ?? [];
    $reason = $_POST['hold_reason'] ?? '';
    
    if (!empty($employeeCodes)) {
        $result = $payroll->holdSalary($periodId, $employeeCodes, $reason);
        setFlash('success', $result['message']);
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle release salary
if (isset($_POST['release_salary']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $employeeCodes = $_POST['release_employees'] ?? [];
    
    if (!empty($employeeCodes)) {
        $result = $payroll->releaseSalary($periodId, $employeeCodes);
        setFlash('success', $result['message']);
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle resolve exception
if (isset($_POST['resolve_exception']) && isset($_POST['exception_id'])) {
    $exceptionId = (int)$_POST['exception_id'];
    $payroll->resolveException($exceptionId);
    setFlash('success', 'Exception resolved!');
    redirect('index.php?page=payroll/process&period_id=' . ($_GET['period_id'] ?? ''));
}

// Get selected period
$selectedPeriod = null;
$payrollData = [];
$totals = null;
$exceptions = [];
$clientSummary = [];
$unitSummary = [];

if (isset($_GET['period_id'])) {
    $stmt = $db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
    $stmt->execute([(int)$_GET['period_id']]);
    $selectedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedPeriod) {
        // Build filters
        $filters = [];
        if ($filterClientId) $filters['client_id'] = $filterClientId;
        if ($filterUnitId) $filters['unit_id'] = $filterUnitId;
        if ($filterStatus) $filters['status'] = $filterStatus;
        if ($filterHold !== '') $filters['salary_hold'] = $filterHold;
        if ($searchTerm) $filters['search'] = $searchTerm;
        
        $payrollData = $payroll->getPayrollReport($selectedPeriod['id'], $filters);
        $totals = $payroll->getPeriodSummary($selectedPeriod['id']);
        $exceptions = $payroll->getExceptions($selectedPeriod['id']);
        $clientSummary = $payroll->getClientWiseSummary($selectedPeriod['id']);
        $unitSummary = $payroll->getUnitWiseSummary($selectedPeriod['id']);
    }
}

// Define column visibility options
$visibleColumns = [
    'employee_code' => ['label' => 'Emp Code', 'default' => true],
    'full_name' => ['label' => 'Name', 'default' => true],
    'client_unit' => ['label' => 'Client/Unit', 'default' => true],
    'paid_days' => ['label' => 'Paid Days', 'default' => true],
    'basic' => ['label' => 'Basic', 'default' => false],
    'da' => ['label' => 'DA', 'default' => false],
    'hra' => ['label' => 'HRA', 'default' => false],
    'gross' => ['label' => 'Gross', 'default' => true],
    'pf_emp' => ['label' => 'PF (Emp)', 'default' => false],
    'esi_emp' => ['label' => 'ESI (Emp)', 'default' => false],
    'pt' => ['label' => 'PT', 'default' => false],
    'advance' => ['label' => 'Advance', 'default' => false],
    'deductions' => ['label' => 'Deductions', 'default' => true],
    'net_pay' => ['label' => 'Net Pay', 'default' => true],
    'ctc' => ['label' => 'CTC', 'default' => false],
    'status' => ['label' => 'Status', 'default' => true],
];

// Store selected columns from cookie or use defaults
$selectedColumns = isset($_COOKIE['payroll_columns']) ? json_decode($_COOKIE['payroll_columns'], true) : null;
if ($selectedColumns === null) {
    $selectedColumns = array_keys(array_filter($visibleColumns, fn($c) => $c['default']));
}
?>

<div class="row">
    <!-- Period Selection -->
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar me-2"></i>Payroll Periods</h5>
            </div>
            <div class="card-body">
                <!-- Create New Period -->
                <form method="POST" class="mb-4">
                    <div class="row g-2">
                        <div class="col-5">
                            <select class="form-select form-select-sm" name="month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-4">
                            <select class="form-select form-select-sm" name="year">
                                <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-3">
                            <button type="submit" name="create_period" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Period List -->
                <div class="list-group list-group-flush">
                    <?php foreach ($periods as $p): ?>
                    <a href="index.php?page=payroll/process&period_id=<?php echo $p['id']; ?>" 
                       class="list-group-item list-group-item-action <?php echo $selectedPeriod && $selectedPeriod['id'] == $p['id'] ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-medium"><?php echo sanitize($p['period_name']); ?></div>
                                <small class="text-muted">
                                    <?php echo $p['employee_count'] ?? 0; ?> emp
                                    <?php if (($p['hold_count'] ?? 0) > 0): ?>
                                    <span class="text-warning" title="Held salaries"><i class="bi bi-pause-circle"></i> <?php echo $p['hold_count']; ?></span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php 
                                echo $p['status'] === 'Draft' ? 'secondary' : 
                                    ($p['status'] === 'Processed' ? 'info' : 
                                    ($p['status'] === 'Approved' ? 'success' : 
                                    ($p['status'] === 'Paid' ? 'primary' : 
                                    ($p['status'] === 'Frozen' || $p['status'] === 'Locked' ? 'danger' : 'warning')))); 
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
    <div class="col-lg-9">
        <?php if (!$selectedPeriod): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-event fs-1 text-muted"></i>
                <h5 class="mt-3 text-muted">Select a Payroll Period</h5>
                <p class="text-muted">Choose a period from the left to view or process payroll</p>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Header with Actions -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="card-title mb-0">
                    <i class="bi bi-cash-stack me-2"></i>
                    Payroll - <?php echo sanitize($selectedPeriod['period_name']); ?>
                    <?php if (in_array($selectedPeriod['status'], ['Frozen', 'Locked'])): ?>
                    <span class="badge bg-danger ms-2"><i class="bi bi-lock"></i> Frozen</span>
                    <?php endif; ?>
                </h5>
                <div class="btn-group btn-group-sm">
                    <?php if ($selectedPeriod['status'] === 'Draft'): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#processModal">
                        <i class="bi bi-play-fill me-1"></i>Process
                    </button>
                    <?php elseif ($selectedPeriod['status'] === 'Processed'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="approve_payroll" class="btn btn-success"
                                onclick="return confirm('Approve payroll for this period?')">
                            <i class="bi bi-check-lg me-1"></i>Approve
                        </button>
                    </form>
                    <button type="button" class="btn btn-outline-info" onclick="openRecalculateModal()">
                        <i class="bi bi-arrow-repeat me-1"></i>Recalculate
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="delete_payroll" class="btn btn-outline-danger"
                                onclick="return confirm('Delete payroll and re-process?')">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                    <?php elseif ($selectedPeriod['status'] === 'Approved'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <input type="date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="form-control form-control-sm d-inline-block" style="width: auto;">
                        <button type="submit" name="mark_paid" class="btn btn-success"
                                onclick="return confirm('Mark payroll as paid?')">
                            <i class="bi bi-cash me-1"></i>Mark Paid
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="freeze_payroll" class="btn btn-outline-danger"
                                onclick="return confirm('Freeze payroll? This will prevent any further modifications.')">
                            <i class="bi bi-lock me-1"></i>Freeze
                        </button>
                    </form>
                    <?php elseif ($selectedPeriod['status'] === 'Frozen' || $selectedPeriod['status'] === 'Locked'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="unfreeze_payroll" class="btn btn-warning"
                                onclick="return confirm('Unfreeze payroll? This will allow modifications.')">
                            <i class="bi bi-unlock me-1"></i>Unfreeze
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if (in_array($selectedPeriod['status'], ['Processed', 'Approved', 'Paid'])): ?>
                    <a href="index.php?page=payroll/payslips&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-outline-primary">
                        <i class="bi bi-file-text me-1"></i>Payslips
                    </a>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download me-1"></i>Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="index.php?page=payroll/export&period_id=<?php echo $selectedPeriod['id']; ?>&format=excel">
                                <i class="bi bi-file-excel me-2"></i>Excel
                            </a></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll/export&period_id=<?php echo $selectedPeriod['id']; ?>&format=pdf">
                                <i class="bi bi-file-pdf me-2"></i>PDF
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll/bank-advice&period_id=<?php echo $selectedPeriod['id']; ?>">
                                <i class="bi bi-bank me-2"></i>Bank Advice
                            </a></li>
                            <li><a class="dropdown-item" href="index.php?page=payroll/export&period_id=<?php echo $selectedPeriod['id']; ?>&format=neft">
                                <i class="bi bi-credit-card me-2"></i>NEFT Format
                            </a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card-body border-bottom py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="payroll/process">
                    <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                    
                    <div class="col-md-2">
                        <label class="form-label small">Client</label>
                        <select name="client_id" class="form-select form-select-sm">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $filterClientId == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Unit</label>
                        <select name="unit_id" class="form-select form-select-sm">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>" <?php echo $filterUnitId == $u['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($u['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Status</label>
                        <select name="filter_status" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <option value="Processed" <?php echo $filterStatus === 'Processed' ? 'selected' : ''; ?>>Processed</option>
                            <option value="Hold" <?php echo $filterStatus === 'Hold' ? 'selected' : ''; ?>>Hold</option>
                            <option value="Approved" <?php echo $filterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Paid" <?php echo $filterStatus === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label small">Hold</label>
                        <select name="salary_hold" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="1" <?php echo $filterHold === '1' ? 'selected' : ''; ?>>Yes</option>
                            <option value="0" <?php echo $filterHold === '0' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label small">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Name or Code" value="<?php echo sanitize($searchTerm); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <div class="btn-group w-100">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-filter"></i> Filter
                            </button>
                            <a href="index.php?page=payroll/process&period_id=<?php echo $selectedPeriod['id']; ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="col-md-1">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-layout-three-columns"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-3" style="width: 200px;">
                                <h6 class="mb-2">Show Columns</h6>
                                <?php foreach ($visibleColumns as $col => $info): ?>
                                <div class="form-check">
                                    <input class="form-check-input column-toggle" type="checkbox" 
                                           id="col_<?php echo $col; ?>" 
                                           data-column="<?php echo $col; ?>"
                                           <?php echo in_array($col, $selectedColumns) ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="col_<?php echo $col; ?>">
                                        <?php echo $info['label']; ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <?php if ($totals && $totals['employee_count'] > 0): ?>
            <div class="card-body border-bottom py-2">
                <div class="row text-center g-2">
                    <div class="col">
                        <div class="small text-muted">Employees</div>
                        <div class="h5 mb-0"><?php echo number_format($totals['employee_count']); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Gross</div>
                        <div class="h5 mb-0 text-primary"><?php echo formatCurrency($totals['total_gross']); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Deductions</div>
                        <div class="h5 mb-0 text-danger"><?php echo formatCurrency($totals['total_deductions']); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Net Pay</div>
                        <div class="h5 mb-0 text-success"><?php echo formatCurrency($totals['total_net_pay']); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">CTC</div>
                        <div class="h5 mb-0"><?php echo formatCurrency($totals['total_ctc']); ?></div>
                    </div>
                    <?php if ($totals['held_count'] > 0): ?>
                    <div class="col">
                        <div class="small text-muted">Held</div>
                        <div class="h5 mb-0 text-warning"><?php echo $totals['held_count']; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($totals['dirty_count'] > 0): ?>
                    <div class="col">
                        <div class="small text-muted">Needs Recalc</div>
                        <div class="h5 mb-0 text-info"><?php echo $totals['dirty_count']; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Exceptions Panel -->
        <?php if (!empty($exceptions)): ?>
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning text-dark py-2">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Payroll Exceptions (<?php echo count($exceptions); ?>)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exceptions as $ex): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=employee/view&id=<?php echo $ex['employee_id']; ?>">
                                        <?php echo sanitize($ex['full_name']); ?>
                                    </a>
                                    <small class="text-muted d-block"><?php echo sanitize($ex['employee_code']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $ex['exception_type'] === 'Missing Attendance' ? 'warning' :
                                            ($ex['exception_type'] === 'Missing Bank Details' ? 'danger' :
                                            ($ex['exception_type'] === 'Undefined Salary' ? 'danger' : 'secondary'));
                                    ?>"><?php echo sanitize($ex['exception_type']); ?></span>
                                </td>
                                <td><small><?php echo sanitize($ex['exception_message']); ?></small></td>
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="exception_id" value="<?php echo $ex['id']; ?>">
                                        <button type="submit" name="resolve_exception" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Charts Section -->
        <?php if (!empty($clientSummary) || !empty($unitSummary)): ?>
        <div class="row mb-3">
            <?php if (!empty($clientSummary)): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Client-wise Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="clientChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($unitSummary)): ?>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header py-2">
                        <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Unit-wise Distribution</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="unitChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Payroll Table -->
        <div class="card">
            <div class="card-body p-0">
                <form id="bulkForm" method="POST">
                    <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                    
                    <!-- Bulk Actions Bar -->
                    <?php if (!in_array($selectedPeriod['status'], ['Frozen', 'Locked']) && !empty($payrollData)): ?>
                    <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted small">Selected: <span id="selectedCount">0</span></span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-warning" onclick="openHoldModal()">
                                <i class="bi bi-pause-circle me-1"></i>Hold
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="openReleaseModal()">
                                <i class="bi bi-play-circle me-1"></i>Release
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="openRecalculateModal()">
                                <i class="bi bi-arrow-repeat me-1"></i>Recalculate
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0" id="payrollTable">
                            <thead class="table-light">
                                <tr>
                                    <?php if (!in_array($selectedPeriod['status'], ['Frozen', 'Locked'])): ?>
                                    <th style="width: 30px;">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <?php endif; ?>
                                    <?php if (in_array('employee_code', $selectedColumns)): ?>
                                    <th>Emp Code</th>
                                    <?php endif; ?>
                                    <?php if (in_array('full_name', $selectedColumns)): ?>
                                    <th>Name</th>
                                    <?php endif; ?>
                                    <?php if (in_array('client_unit', $selectedColumns)): ?>
                                    <th>Client/Unit</th>
                                    <?php endif; ?>
                                    <?php if (in_array('paid_days', $selectedColumns)): ?>
                                    <th class="text-center">Days</th>
                                    <?php endif; ?>
                                    <?php if (in_array('basic', $selectedColumns)): ?>
                                    <th class="text-end">Basic</th>
                                    <?php endif; ?>
                                    <?php if (in_array('da', $selectedColumns)): ?>
                                    <th class="text-end">DA</th>
                                    <?php endif; ?>
                                    <?php if (in_array('hra', $selectedColumns)): ?>
                                    <th class="text-end">HRA</th>
                                    <?php endif; ?>
                                    <?php if (in_array('gross', $selectedColumns)): ?>
                                    <th class="text-end">Gross</th>
                                    <?php endif; ?>
                                    <?php if (in_array('pf_emp', $selectedColumns)): ?>
                                    <th class="text-end">PF (E)</th>
                                    <?php endif; ?>
                                    <?php if (in_array('esi_emp', $selectedColumns)): ?>
                                    <th class="text-end">ESI (E)</th>
                                    <?php endif; ?>
                                    <?php if (in_array('pt', $selectedColumns)): ?>
                                    <th class="text-end">PT</th>
                                    <?php endif; ?>
                                    <?php if (in_array('advance', $selectedColumns)): ?>
                                    <th class="text-end">Adv</th>
                                    <?php endif; ?>
                                    <?php if (in_array('deductions', $selectedColumns)): ?>
                                    <th class="text-end">Ded</th>
                                    <?php endif; ?>
                                    <?php if (in_array('net_pay', $selectedColumns)): ?>
                                    <th class="text-end">Net Pay</th>
                                    <?php endif; ?>
                                    <?php if (in_array('ctc', $selectedColumns)): ?>
                                    <th class="text-end">CTC</th>
                                    <?php endif; ?>
                                    <?php if (in_array('status', $selectedColumns)): ?>
                                    <th>Status</th>
                                    <?php endif; ?>
                                    <th style="width: 60px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payrollData)): ?>
                                <tr>
                                    <td colspan="20" class="text-center py-4 text-muted">
                                        <?php if ($selectedPeriod['status'] === 'Draft'): ?>
                                        No payroll data. Click "Process" to generate payroll.
                                        <?php else: ?>
                                        No records found matching your filters.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($payrollData as $row): ?>
                                <tr class="<?php echo ($row['salary_hold'] ?? 0) ? 'table-warning' : ''; ?>">
                                    <?php if (!in_array($selectedPeriod['status'], ['Frozen', 'Locked'])): ?>
                                    <td>
                                        <input type="checkbox" class="form-check-input row-checkbox" 
                                               name="employee_codes[]" value="<?php echo $row['employee_id']; ?>">
                                    </td>
                                    <?php endif; ?>
                                    <?php if (in_array('employee_code', $selectedColumns)): ?>
                                    <td><code><?php echo sanitize($row['employee_id']); ?></code></td>
                                    <?php endif; ?>
                                    <?php if (in_array('full_name', $selectedColumns)): ?>
                                    <td><?php echo sanitize($row['full_name'] ?? '-'); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('client_unit', $selectedColumns)): ?>
                                    <td>
                                        <small>
                                            <?php echo sanitize($row['client_name'] ?? '-'); ?>
                                            <?php if ($row['unit_name']): ?>
                                            <br><span class="text-muted"><?php echo sanitize($row['unit_name']); ?></span>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (in_array('paid_days', $selectedColumns)): ?>
                                    <td class="text-center">
                                        <?php echo $row['paid_days'] ?? 0; ?>
                                        <?php if (($row['unpaid_days'] ?? 0) > 0): ?>
                                        <small class="text-danger">(-<?php echo $row['unpaid_days']; ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (in_array('basic', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['basic'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('da', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['da'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('hra', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['hra'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('gross', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['gross_earnings'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('pf_emp', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['pf_employee'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('esi_emp', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['esi_employee'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('pt', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['professional_tax'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('advance', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['salary_advance'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('deductions', $selectedColumns)): ?>
                                    <td class="text-end text-danger"><?php echo formatCurrency($row['total_deductions'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('net_pay', $selectedColumns)): ?>
                                    <td class="text-end fw-bold text-success"><?php echo formatCurrency($row['net_pay'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('ctc', $selectedColumns)): ?>
                                    <td class="text-end small"><?php echo formatCurrency($row['ctc'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('status', $selectedColumns)): ?>
                                    <td>
                                        <?php if ($row['salary_hold'] ?? 0): ?>
                                        <span class="badge bg-warning text-dark">Hold</span>
                                        <?php elseif ($row['payroll_dirty'] ?? 0): ?>
                                        <span class="badge bg-info">Dirty</span>
                                        <?php else: ?>
                                        <span class="badge bg-<?php 
                                            echo $row['status'] === 'Paid' ? 'success' : 
                                                ($row['status'] === 'Approved' ? 'primary' : 'secondary'); 
                                        ?>"><?php echo sanitize($row['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="viewPayrollDetail('<?php echo $row['employee_id']; ?>')"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Statutory Summary -->
        <?php if ($totals && $totals['employee_count'] > 0): ?>
        <div class="card mt-3">
            <div class="card-header py-2">
                <h6 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Statutory Summary</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Provident Fund</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td>Employee Share</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_pf_employee'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer EPF</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_pf_employer'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer EPS</td>
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
                        <h6 class="text-muted mb-2">ESI & Others</h6>
                        <table class="table table-sm table-borderless">
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
                                <td>Salary Advance</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_advance'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Employer Contribution</td>
                                <td class="text-end fw-bold"><?php echo formatCurrency($totals['total_employer_contribution'] ?? 0); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="mt-2">
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

<!-- Process Modal -->
<div class="modal fade" id="processModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id'] ?? ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Process Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Process payroll for <strong><?php echo $selectedPeriod['period_name'] ?? ''; ?></strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Client Filter (Optional)</label>
                        <select name="process_client_id" class="form-select">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Unit Filter (Optional)</label>
                        <select name="process_unit_id" class="form-select">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo sanitize($u['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="process_payroll" class="btn btn-primary">
                        <i class="bi bi-play-fill me-1"></i>Process Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hold Salary Modal -->
<div class="modal fade" id="holdModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="holdForm">
                <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id'] ?? ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Hold Salary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Hold salary for <span id="holdCount">0</span> selected employee(s)?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea name="hold_reason" class="form-control" rows="2" 
                                  placeholder="Enter reason for holding salary"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="hold_salary" class="btn btn-warning">
                        <i class="bi bi-pause-circle me-1"></i>Hold Salary
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Release Salary Modal -->
<div class="modal fade" id="releaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="releaseForm">
                <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id'] ?? ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Release Salary</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Release held salary for <span id="releaseCount">0</span> selected employee(s)?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="release_salary" class="btn btn-success">
                        <i class="bi bi-play-circle me-1"></i>Release Salary
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Recalculate Modal -->
<div class="modal fade" id="recalculateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="recalculateForm">
                <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id'] ?? ''; ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Recalculate Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Recalculate payroll for <span id="recalcCount">0</span> selected employee(s)?</p>
                    <p class="text-muted small">Leave selection empty to recalculate all dirty records.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="recalculate_payroll" class="btn btn-info">
                        <i class="bi bi-arrow-repeat me-1"></i>Recalculate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payroll Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payroll Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="printPayslipBtn" class="btn btn-primary" target="_blank">
                    <i class="bi bi-printer me-1"></i>Print Payslip
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Store data for JavaScript
$clientChartData = json_encode(array_map(function($c) {
    return ['name' => $c['client_name'], 'value' => (float)$c['total_net']];
}, $clientSummary));

$unitChartData = json_encode(array_map(function($u) {
    return ['name' => $u['unit_name'], 'value' => (float)$u['total_net']];
}, $unitSummary));

$inlineJS = "
// Column visibility toggle
$('.column-toggle').on('change', function() {
    var selected = [];
    $('.column-toggle:checked').each(function() {
        selected.push($(this).data('column'));
    });
    
    // Save to cookie
    document.cookie = 'payroll_columns=' + JSON.stringify(selected) + ';path=/;max-age=31536000';
    
    // Reload page
    location.reload();
});

// Select all checkbox
$('#selectAll').on('change', function() {
    $('.row-checkbox').prop('checked', this.checked);
    updateSelectedCount();
});

$('.row-checkbox').on('change', updateSelectedCount);

function updateSelectedCount() {
    var count = $('.row-checkbox:checked').length;
    $('#selectedCount').text(count);
}

// Open hold modal
function openHoldModal() {
    var checked = $('.row-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    var form = $('#holdForm');
    form.find('input[name=\"hold_employees[]\"]').remove();
    checked.each(function() {
        form.append('<input type=\"hidden\" name=\"hold_employees[]\" value=\"' + this.value + '\">');
    });
    $('#holdCount').text(checked.length);
    new bootstrap.Modal(document.getElementById('holdModal')).show();
}

// Open release modal
function openReleaseModal() {
    var checked = $('.row-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    var form = $('#releaseForm');
    form.find('input[name=\"release_employees[]\"]').remove();
    checked.each(function() {
        form.append('<input type=\"hidden\" name=\"release_employees[]\" value=\"' + this.value + '\">');
    });
    $('#releaseCount').text(checked.length);
    new bootstrap.Modal(document.getElementById('releaseModal')).show();
}

// Open recalculate modal
function openRecalculateModal() {
    var checked = $('.row-checkbox:checked');
    var form = $('#recalculateForm');
    form.find('input[name=\"employee_codes[]\"]').remove();
    
    if (checked.length > 0) {
        checked.each(function() {
            form.append('<input type=\"hidden\" name=\"employee_codes[]\" value=\"' + this.value + '\">');
        });
    }
    $('#recalcCount').text(checked.length || 'all dirty');
    new bootstrap.Modal(document.getElementById('recalculateModal')).show();
}

// View payroll detail
function viewPayrollDetail(employeeId) {
    $('#detailContent').html('<div class=\"text-center py-4\"><div class=\"spinner-border text-primary\"></div></div>');
    $('#printPayslipBtn').attr('href', 'index.php?page=payroll/print_payslip&period_id=" . ($selectedPeriod['id'] ?? '') . "&employee_id=' + employeeId);
    
    $.ajax({
        url: 'index.php?page=payroll/view&action=detail',
        method: 'GET',
        data: {
            period_id: " . ($selectedPeriod['id'] ?? 0) . ",
            employee_id: employeeId
        },
        success: function(response) {
            $('#detailContent').html(response);
        },
        error: function() {
            $('#detailContent').html('<div class=\"alert alert-danger\">Failed to load payroll details.</div>');
        }
    });
    
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// Charts
var clientData = $clientChartData;
var unitData = $unitChartData;

if (clientData && clientData.length > 0) {
    var ctx1 = document.getElementById('clientChart').getContext('2d');
    new Chart(ctx1, {
        type: 'doughnut',
        data: {
            labels: clientData.map(d => d.name),
            datasets: [{
                data: clientData.map(d => d.value),
                backgroundColor: [
                    '#0d6efd', '#6610f2', '#d63384', '#dc3545', '#fd7e14',
                    '#ffc107', '#198754', '#20c997', '#0dcaf0', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, font: { size: 11 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ₹' + context.raw.toLocaleString('en-IN');
                        }
                    }
                }
            }
        }
    });
}

if (unitData && unitData.length > 0) {
    var ctx2 = document.getElementById('unitChart').getContext('2d');
    new Chart(ctx2, {
        type: 'bar',
        data: {
            labels: unitData.map(d => d.name).slice(0, 10),
            datasets: [{
                label: 'Net Pay',
                data: unitData.map(d => d.value).slice(0, 10),
                backgroundColor: '#0d6efd'
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return '₹' + context.raw.toLocaleString('en-IN');
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: function(value) {
                            return '₹' + value.toLocaleString('en-IN');
                        }
                    }
                }
            }
        }
    });
}
";

// Include Chart.js
$extraJS = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>';
