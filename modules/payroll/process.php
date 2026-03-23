<?php
/**
 * RCS HRMS Pro - Payroll Processing Page
 * Version: 3.1.0 - Fixed all errors, proper error handling
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
$filterMonth = $_GET['month'] ?? $currentMonth;
$filterYear = $_GET['year'] ?? $currentYear;

// Get clients and units for filters
$clients = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$allUnits = $db->query("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Filter units by selected client for dropdown
$units = [];
if ($filterClientId) {
    foreach ($allUnits as $u) {
        if ($u['client_id'] == $filterClientId) {
            $units[] = $u;
        }
    }
} else {
    $units = $allUnits;
}

// Handle create period
if (isset($_POST['create_period'])) {
    $month = (int)($_POST['month'] ?? date('n'));
    $year = (int)($_POST['year'] ?? date('Y'));
    
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
            $msg .= " (" . count($result['exceptions']) . " exceptions found)";
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

if (isset($_GET['period_id']) && !empty($_GET['period_id'])) {
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
    }
}

// Group periods by year for tabs
$periodsByYear = [];
foreach ($periods as $p) {
    $year = $p['year'] ?? date('Y', strtotime($p['start_date'] ?? 'now'));
    if (!isset($periodsByYear[$year])) {
        $periodsByYear[$year] = [];
    }
    $periodsByYear[$year][] = $p;
}
krsort($periodsByYear);

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

// Convert allUnits to JSON for JavaScript
$allUnitsJson = json_encode($allUnits);
?>

<div class="row">
    <div class="col-12">
        <?php if (!$selectedPeriod): ?>
        <!-- Period Selection View -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar me-2"></i>Payroll Processing</h5>
                <div class="card-actions">
                    <form method="POST" class="d-inline">
                        <div class="input-group input-group-sm">
                            <select class="form-select form-select-sm" name="month">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                                    <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-select form-select-sm" name="year">
                                <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button type="submit" name="create_period" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Create Period
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Year Tabs -->
            <div class="card-body p-0">
                <ul class="nav nav-tabs px-3 pt-2" id="yearTabs" role="tablist">
                    <?php $firstYear = true; foreach ($periodsByYear as $year => $yearPeriods): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $firstYear ? 'active' : ''; ?>" 
                                id="year-<?php echo $year; ?>-tab" data-bs-toggle="tab" 
                                data-bs-target="#year-<?php echo $year; ?>" type="button">
                            <?php echo $year; ?>
                        </button>
                    </li>
                    <?php $firstYear = false; endforeach; ?>
                    <?php if (empty($periodsByYear)): ?>
                    <li class="nav-item">
                        <span class="nav-link active"><?php echo $currentYear; ?></span>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content p-3" id="yearTabsContent">
                    <?php $firstYear = true; foreach ($periodsByYear as $year => $yearPeriods): ?>
                    <div class="tab-pane fade <?php echo $firstYear ? 'show active' : ''; ?>" 
                         id="year-<?php echo $year; ?>" role="tabpanel">
                         
                        <div class="row g-3">
                            <?php foreach ($yearPeriods as $p): ?>
                            <div class="col-md-3">
                                <a href="index.php?page=payroll/process&period_id=<?php echo $p['id']; ?>" 
                                   class="card h-100 text-decoration-none <?php echo $selectedPeriod && $selectedPeriod['id'] == $p['id'] ? 'border-primary' : ''; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo sanitize($p['period_name']); ?></h6>
                                            <span class="badge bg-<?php 
                                                echo $p['status'] === 'Draft' ? 'secondary' : 
                                                    ($p['status'] === 'Processed' ? 'info' : 
                                                    ($p['status'] === 'Approved' ? 'success' : 
                                                    ($p['status'] === 'Paid' ? 'primary' : 
                                                    ($p['status'] === 'Frozen' || $p['status'] === 'Locked' ? 'danger' : 'warning')))); 
                                            ?>"><?php echo sanitize($p['status']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-people me-1"></i><?php echo $p['employee_count'] ?? 0; ?> employees
                                            <?php if (($p['hold_count'] ?? 0) > 0): ?>
                                            <span class="text-warning ms-2"><i class="bi bi-pause-circle"></i> <?php echo $p['hold_count']; ?> held</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                    </div>
                    <?php $firstYear = false; endforeach; ?>
                    
                    <?php if (empty($periodsByYear)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-calendar-plus fs-1"></i>
                        <p class="mt-3">No payroll periods found.</p>
                        <p>Create a new period using the button above.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Payroll Details View -->
        
        <!-- Header with Actions -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?page=payroll/process" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cash-stack me-2"></i>
                        Payroll - <?php echo sanitize($selectedPeriod['period_name']); ?>
                        <?php if (in_array($selectedPeriod['status'], ['Frozen', 'Locked'])): ?>
                        <span class="badge bg-danger ms-2"><i class="bi bi-lock"></i> Frozen</span>
                        <?php endif; ?>
                    </h5>
                </div>
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
                <form method="GET" class="row g-2 align-items-end" id="filterForm">
                    <input type="hidden" name="page" value="payroll/process">
                    <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                    
                    <div class="col-md-2">
                        <label class="form-label small">Client</label>
                        <select name="client_id" id="filterClient" class="form-select form-select-sm" onchange="filterUnitsDropdown()">
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
                        <select name="unit_id" id="filterUnit" class="form-select form-select-sm">
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
            <?php if ($totals && ($totals['employee_count'] ?? 0) > 0): ?>
            <div class="card-body border-bottom py-2">
                <div class="row text-center g-2">
                    <div class="col">
                        <div class="small text-muted">Employees</div>
                        <div class="h5 mb-0"><?php echo number_format($totals['employee_count'] ?? 0); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Gross</div>
                        <div class="h5 mb-0 text-primary"><?php echo formatCurrency($totals['total_gross'] ?? 0); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Deductions</div>
                        <div class="h5 mb-0 text-danger"><?php echo formatCurrency($totals['total_deductions'] ?? 0); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">Net Pay</div>
                        <div class="h5 mb-0 text-success"><?php echo formatCurrency($totals['total_net_pay'] ?? 0); ?></div>
                    </div>
                    <div class="col">
                        <div class="small text-muted">CTC</div>
                        <div class="h5 mb-0"><?php echo formatCurrency($totals['total_ctc'] ?? 0); ?></div>
                    </div>
                    <?php if (($totals['held_count'] ?? 0) > 0): ?>
                    <div class="col">
                        <div class="small text-muted">Held</div>
                        <div class="h5 mb-0 text-warning"><?php echo $totals['held_count']; ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (($totals['dirty_count'] ?? 0) > 0): ?>
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
                                    <td><?php echo sanitize($row['employee_code']); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('full_name', $selectedColumns)): ?>
                                    <td>
                                        <?php echo sanitize($row['full_name']); ?>
                                        <?php if ($row['salary_hold'] ?? 0): ?>
                                        <span class="badge bg-warning text-dark ms-1">Hold</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (in_array('client_unit', $selectedColumns)): ?>
                                    <td>
                                        <small><?php echo sanitize($row['client_name'] ?? ''); ?></small>
                                        <?php if ($row['unit_name']): ?>
                                        <small class="text-muted">/ <?php echo sanitize($row['unit_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <?php if (in_array('paid_days', $selectedColumns)): ?>
                                    <td class="text-center"><?php echo $row['paid_days'] ?? 0; ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('basic', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['basic'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('da', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['da'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('hra', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['hra'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('gross', $selectedColumns)): ?>
                                    <td class="text-end"><strong><?php echo formatCurrency($row['gross_earnings'] ?? 0); ?></strong></td>
                                    <?php endif; ?>
                                    <?php if (in_array('pf_emp', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['pf_employee'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('esi_emp', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['esi_employee'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('pt', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['professional_tax'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('advance', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['salary_advance'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('deductions', $selectedColumns)): ?>
                                    <td class="text-end text-danger"><?php echo formatCurrency($row['total_deductions'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('net_pay', $selectedColumns)): ?>
                                    <td class="text-end"><strong class="text-success"><?php echo formatCurrency($row['net_pay'] ?? 0); ?></strong></td>
                                    <?php endif; ?>
                                    <?php if (in_array('ctc', $selectedColumns)): ?>
                                    <td class="text-end"><?php echo formatCurrency($row['ctc'] ?? 0); ?></td>
                                    <?php endif; ?>
                                    <?php if (in_array('status', $selectedColumns)): ?>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo ($row['status'] ?? '') === 'Processed' ? 'info' : 
                                                (($row['status'] ?? '') === 'Hold' ? 'warning text-dark' : 
                                                (($row['status'] ?? '') === 'Approved' ? 'success' : 
                                                (($row['status'] ?? '') === 'Paid' ? 'primary' : 'secondary')));
                                        ?>"><?php echo sanitize($row['status'] ?? 'Draft'); ?></span>
                                        <?php if ($row['payroll_dirty'] ?? 0): ?>
                                        <span class="badge bg-info" title="Needs recalculation">!</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <a href="index.php?page=payroll/view&id=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
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
                    <p>Process payroll for <strong><?php echo sanitize($selectedPeriod['period_name'] ?? ''); ?></strong>?</p>
                    
                    <div class="mb-3">
                        <label class="form-label">Client (Optional)</label>
                        <select name="process_client_id" class="form-select" id="processClient">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Unit (Optional)</label>
                        <select name="process_unit_id" class="form-select" id="processUnit">
                            <option value="">All Units</option>
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

<!-- Hold Modal -->
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
                    <div class="mb-3">
                        <label class="form-label">Reason for Hold</label>
                        <textarea name="hold_reason" class="form-control" rows="2" required></textarea>
                    </div>
                    <div id="holdEmployeesList"></div>
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

<!-- Release Modal -->
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
                    <p>Release held salary for selected employees?</p>
                    <div id="releaseEmployeesList"></div>
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
                    <p>Recalculate payroll for selected employees?</p>
                    <div id="recalculateEmployeesList"></div>
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

<script>
var allUnits = <?php echo $allUnitsJson; ?>;

$(document).ready(function() {
    // Select all checkbox
    $('#selectAll').on('change', function() {
        $('.row-checkbox').prop('checked', this.checked);
        updateSelectedCount();
    });
    
    // Row checkbox change
    $(document).on('change', '.row-checkbox', function() {
        updateSelectedCount();
    });
    
    // Column visibility toggle
    $('.column-toggle').on('change', function() {
        var columns = [];
        $('.column-toggle:checked').each(function() {
            columns.push($(this).data('column'));
        });
        document.cookie = 'payroll_columns=' + JSON.stringify(columns) + ';path=/;max-age=31536000';
        location.reload();
    });
    
    // Process client filter
    $('#processClient').on('change', function() {
        var clientId = $(this).val();
        var $unitSelect = $('#processUnit');
        $unitSelect.find('option:not(:first)').remove();
        
        allUnits.forEach(function(unit) {
            if (!clientId || unit.client_id == clientId) {
                $unitSelect.append('<option value="' + unit.id + '">' + unit.name + '</option>');
            }
        });
    });
    
    // Filter units dropdown
    if ($('#filterClient').val()) {
        filterUnitsDropdown();
    }
});

function updateSelectedCount() {
    var count = $('.row-checkbox:checked').length;
    $('#selectedCount').text(count);
}

function filterUnitsDropdown() {
    var clientId = $('#filterClient').val();
    var $unitSelect = $('#filterUnit');
    var currentVal = $unitSelect.val();
    
    $unitSelect.find('option:not(:first)').remove();
    
    allUnits.forEach(function(unit) {
        if (!clientId || unit.client_id == clientId) {
            var selected = unit.id == currentVal ? ' selected' : '';
            $unitSelect.append('<option value="' + unit.id + '"' + selected + '>' + unit.name + '</option>');
        }
    });
}

function openHoldModal() {
    var selected = [];
    $('.row-checkbox:checked').each(function() {
        selected.push($(this).val());
    });
    
    if (selected.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    var html = '<input type="hidden" name="hold_employees[]" value="' + selected.join(',') + '">';
    html += '<p class="text-muted">' + selected.length + ' employee(s) selected</p>';
    $('#holdEmployeesList').html(html);
    
    var modal = new bootstrap.Modal(document.getElementById('holdModal'));
    modal.show();
}

function openReleaseModal() {
    var selected = [];
    $('.row-checkbox:checked').each(function() {
        selected.push($(this).val());
    });
    
    if (selected.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    var html = '<input type="hidden" name="release_employees[]" value="' + selected.join(',') + '">';
    html += '<p class="text-muted">' + selected.length + ' employee(s) selected</p>';
    $('#releaseEmployeesList').html(html);
    
    var modal = new bootstrap.Modal(document.getElementById('releaseModal'));
    modal.show();
}

function openRecalculateModal() {
    var selected = [];
    $('.row-checkbox:checked').each(function() {
        selected.push($(this).val());
    });
    
    if (selected.length === 0) {
        alert('Please select at least one employee.');
        return;
    }
    
    var html = '<input type="hidden" name="employee_codes[]" value="' + selected.join(',') + '">';
    html += '<p class="text-muted">' + selected.length + ' employee(s) selected</p>';
    $('#recalculateEmployeesList').html(html);
    
    var modal = new bootstrap.Modal(document.getElementById('recalculateModal'));
    modal.show();
}
</script>
