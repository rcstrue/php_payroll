<?php
/**
 * RCS HRMS Pro - Payroll Processing Page
 * Version: 4.0.0 - Hybrid Payroll System
 * 
 * Features:
 * - Unit-wise batch processing
 * - Excel-like grid interface
 * - Independent unit finalization
 * - Status tracking per unit
 * - Bulk salary editing
 */

$pageTitle = 'Process Payroll';

// Get all periods
$periods = $payroll->getPeriods();

// Get current month/year
$currentMonth = date('n');
$currentYear = date('Y');

// Get filter values
$filterClientId = (int)($_GET['client_id'] ?? 0);
$filterUnitId = (int)($_GET['unit_id'] ?? 0);
$searchTerm = sanitize($_GET['search'] ?? '');

// Get clients and units
$clients = $db->fetchAll("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
$allUnits = $db->fetchAll("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name");

// Filter units by client
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
    $month = (int)($_POST['month'] ?? $currentMonth);
    $year = (int)($_POST['year'] ?? $currentYear);
    
    $result = $payroll->createPeriod($month, $year);
    if (!empty($result['success'])) {
        setFlash('success', 'Payroll period created successfully!');
        redirect('index.php?page=payroll/process&period_id=' . $result['period_id']);
    } else {
        setFlash('error', $result['message'] ?? 'Failed to create period');
    }
}

// Get selected period
$selectedPeriod = null;
$unitStatuses = [];
$payrollData = [];
$totals = null;

if (isset($_GET['period_id']) && !empty($_GET['period_id'])) {
    $periodId = (int)$_GET['period_id'];
    
    $stmt = $db->prepare("SELECT * FROM payroll_periods WHERE id = ?");
    $stmt->execute([$periodId]);
    $selectedPeriod = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($selectedPeriod) {
        // Get unit-wise status
        $unitStatuses = $db->fetchAll(
            "SELECT pus.*, c.name as client_name, u.name as unit_name
             FROM payroll_unit_status pus
             LEFT JOIN clients c ON pus.client_id = c.id
             JOIN units u ON pus.unit_id = u.id
             WHERE pus.payroll_period_id = ?
             ORDER BY c.name, u.name",
            [$selectedPeriod['id']]
        );
        
        // If no unit statuses, create them from active units
        if (empty($unitStatuses)) {
            $activeUnits = $db->fetchAll(
                "SELECT DISTINCT e.unit_id, e.client_id 
                 FROM employees e 
                 WHERE e.status = 'approved' AND e.unit_id IS NOT NULL"
            );
            
            foreach ($activeUnits as $au) {
                try {
                    $db->insert('payroll_unit_status', [
                        'payroll_period_id' => $selectedPeriod['id'],
                        'client_id' => $au['client_id'],
                        'unit_id' => $au['unit_id'],
                        'status' => 'pending'
                    ]);
                } catch (Exception $e) {
                    // Ignore duplicate
                }
            }
            
            // Refresh statuses
            $unitStatuses = $db->fetchAll(
                "SELECT pus.*, c.name as client_name, u.name as unit_name
                 FROM payroll_unit_status pus
                 LEFT JOIN clients c ON pus.client_id = c.id
                 JOIN units u ON pus.unit_id = u.id
                 WHERE pus.payroll_period_id = ?
                 ORDER BY c.name, u.name",
                [$selectedPeriod['id']]
            );
        }
        
        // Get payroll data
        $whereClause = "p.payroll_period_id = :period_id";
        $params = ['period_id' => $selectedPeriod['id']];
        
        if ($filterClientId) {
            $whereClause .= " AND e.client_id = :client_id";
            $params['client_id'] = $filterClientId;
        }
        if ($filterUnitId) {
            $whereClause .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $filterUnitId;
        }
        if ($searchTerm) {
            $whereClause .= " AND (e.full_name LIKE :search OR e.employee_code LIKE :search)";
            $params['search'] = '%' . $searchTerm . '%';
        }
        
        $payrollData = $db->fetchAll(
            "SELECT p.*, e.employee_code, e.full_name, e.designation,
                    c.name as client_name, u.name as unit_name,
                    COALESCE(p.basic_da, p.basic + p.da) as basic_da_display
             FROM payroll p
             JOIN employees e ON p.employee_id = e.employee_code
             LEFT JOIN clients c ON e.client_id = c.id
             LEFT JOIN units u ON e.unit_id = u.id
             WHERE $whereClause
             ORDER BY c.name, u.name, e.employee_code",
            $params
        );
        
        // Get totals
        $totals = $db->fetch(
            "SELECT COUNT(*) as employee_count,
                    SUM(gross_earnings) as total_gross,
                    SUM(total_deductions) as total_deductions,
                    SUM(net_pay) as total_net_pay,
                    SUM(ctc) as total_ctc,
                    SUM(CASE WHEN salary_hold = 1 THEN 1 ELSE 0 END) as held_count
             FROM payroll
             WHERE payroll_period_id = :period_id",
            ['period_id' => $selectedPeriod['id']]
        );
    }
}

// Handle process unit payroll
if (isset($_POST['process_unit']) && isset($_POST['period_id']) && isset($_POST['unit_id'])) {
    $periodId = (int)$_POST['period_id'];
    $unitId = (int)$_POST['unit_id'];
    
    $result = $payroll->processPayroll($periodId, ['unit_id' => $unitId]);
    
    if (!empty($result['success'])) {
        // Update unit status
        $db->update('payroll_unit_status', [
            'status' => 'processed',
            'employee_count' => $result['processed'],
            'total_gross' => $result['total_gross'],
            'total_net' => $result['total_net'],
            'processed_at' => date('Y-m-d H:i:s'),
            'processed_by' => $_SESSION['user_id']
        ], 'payroll_period_id = :period_id AND unit_id = :unit_id', 
           ['period_id' => $periodId, 'unit_id' => $unitId]);
        
        setFlash('success', "Processed {$result['processed']} employees for unit!");
    } else {
        setFlash('error', $result['message'] ?? 'Processing failed');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle finalize unit
if (isset($_POST['finalize_unit']) && isset($_POST['period_id']) && isset($_POST['unit_id'])) {
    $periodId = (int)$_POST['period_id'];
    $unitId = (int)$_POST['unit_id'];
    
    // Update unit status
    $db->update('payroll_unit_status', [
        'status' => 'finalized',
        'finalized_at' => date('Y-m-d H:i:s'),
        'finalized_by' => $_SESSION['user_id']
    ], 'payroll_period_id = :period_id AND unit_id = :unit_id', 
       ['period_id' => $periodId, 'unit_id' => $unitId]);
    
    // Update period finalized count
    $finalizedCount = $db->fetch(
        "SELECT COUNT(*) as count FROM payroll_unit_status 
         WHERE payroll_period_id = ? AND status = 'finalized'",
        [$periodId]
    );
    
    $totalUnits = $db->fetch(
        "SELECT COUNT(*) as count FROM payroll_unit_status WHERE payroll_period_id = ?",
        [$periodId]
    );
    
    // If all units finalized, update period status
    if ($finalizedCount['count'] >= $totalUnits['count']) {
        $db->update('payroll_periods', [
            'status' => 'Approved',
            'finalized_units' => $finalizedCount['count']
        ], 'id = :id', ['id' => $periodId]);
    }
    
    setFlash('success', 'Unit finalized successfully!');
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle approve payroll (all units)
if (isset($_POST['approve_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    
    // Approve all units
    $db->update('payroll_unit_status', [
        'status' => 'finalized',
        'approved_at' => date('Y-m-d H:i:s'),
        'approved_by' => $_SESSION['user_id']
    ], 'payroll_period_id = :period_id AND status = "processed"', 
       ['period_id' => $periodId]);
    
    $db->update('payroll_periods', [
        'status' => 'Approved',
        'approved_at' => date('Y-m-d H:i:s'),
        'approved_by' => $_SESSION['user_id']
    ], 'id = :id', ['id' => $periodId]);
    
    setFlash('success', 'Payroll approved successfully!');
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle bulk salary update
if (isset($_POST['bulk_update_salary']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $updates = $_POST['salary_updates'] ?? [];
    
    $updated = 0;
    $db->beginTransaction();
    
    try {
        foreach ($updates as $empCode => $data) {
            $basicDA = floatval($data['basic_da'] ?? 0);
            $hra = floatval($data['hra'] ?? 0);
            $lww = floatval($data['lww'] ?? 0);
            $bonus = floatval($data['bonus'] ?? 0);
            $washing = floatval($data['washing'] ?? 0);
            $other = floatval($data['other'] ?? 0);
            
            $newGross = $basicDA + $hra + $lww + $bonus + $washing + $other;
            
            // Recalculate deductions
            $pfEmp = round(min($basicDA, 15000) * 0.12, 2);
            $esiEmp = ($newGross <= 21000) ? round($newGross * 0.0075, 2) : 0;
            $pt = 200; // Simplified PT
            
            $totalDed = $pfEmp + $esiEmp + $pt;
            $netPay = $newGross - $totalDed;
            
            $db->update('payroll', [
                'basic_da' => $basicDA,
                'basic' => $basicDA * 0.6,
                'da' => $basicDA * 0.4,
                'hra' => $hra,
                'lww' => $lww,
                'bonus_amount' => $bonus,
                'washing_allowance' => $washing,
                'other_allowance' => $other,
                'gross_earnings' => $newGross,
                'gross_salary' => $newGross,
                'pf_employee' => $pfEmp,
                'esi_employee' => $esiEmp,
                'professional_tax' => $pt,
                'total_deductions' => $totalDed,
                'net_pay' => $netPay
            ], 'payroll_period_id = :period_id AND employee_id = :emp_code',
               ['period_id' => $periodId, 'emp_code' => $empCode]);
            
            $updated++;
        }
        
        $db->commit();
        setFlash('success', "$updated salary records updated!");
        
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Update failed: ' . $e->getMessage());
    }
    
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Handle delete payroll
if (isset($_POST['delete_payroll']) && isset($_POST['period_id'])) {
    $periodId = (int)$_POST['period_id'];
    $result = $payroll->deletePayroll($periodId);
    
    if (!empty($result['success'])) {
        // Reset unit statuses
        $db->update('payroll_unit_status', [
            'status' => 'pending',
            'employee_count' => 0,
            'total_gross' => 0,
            'total_net' => 0,
            'processed_at' => null
        ], 'payroll_period_id = :id', ['id' => $periodId]);
        
        setFlash('success', 'Payroll deleted successfully!');
    } else {
        setFlash('error', $result['message'] ?? 'Failed to delete');
    }
    redirect('index.php?page=payroll/process&period_id=' . $periodId);
}

// Group periods by year
$periodsByYear = [];
foreach ($periods as $p) {
    $year = $p['year'] ?? date('Y', strtotime($p['start_date'] ?? 'now'));
    if (!isset($periodsByYear[$year])) {
        $periodsByYear[$year] = [];
    }
    $periodsByYear[$year][] = $p;
}
krsort($periodsByYear);

// Define visible columns
$visibleColumns = [
    'employee_code' => ['label' => 'Emp Code', 'default' => true],
    'full_name' => ['label' => 'Name', 'default' => true],
    'client_unit' => ['label' => 'Client/Unit', 'default' => true],
    'paid_days' => ['label' => 'Days', 'default' => true],
    'basic_da' => ['label' => 'Basic+DA', 'default' => true],
    'hra' => ['label' => 'HRA', 'default' => false],
    'lww' => ['label' => 'LWW', 'default' => false],
    'bonus' => ['label' => 'Bonus', 'default' => false],
    'washing' => ['label' => 'Washing', 'default' => false],
    'other' => ['label' => 'Other', 'default' => false],
    'gross' => ['label' => 'Gross', 'default' => true],
    'deductions' => ['label' => 'Ded', 'default' => true],
    'net_pay' => ['label' => 'Net Pay', 'default' => true],
    'status' => ['label' => 'Status', 'default' => true],
];

$selectedColumns = isset($_COOKIE['payroll_columns']) ? json_decode($_COOKIE['payroll_columns'], true) : null;
if ($selectedColumns === null) {
    $selectedColumns = array_keys(array_filter($visibleColumns, fn($c) => $c['default']));
}
?>

<div class="row">
    <div class="col-12">
        <?php if (!$selectedPeriod): ?>
        <!-- Period Selection View -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-calendar me-2"></i>Payroll Processing</h5>
                <form method="POST" class="d-flex gap-2">
                    <select class="form-select form-select-sm" name="month" style="width: auto;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $currentMonth ? 'selected' : ''; ?>>
                            <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                    <select class="form-select form-select-sm" name="year" style="width: auto;">
                        <?php for ($y = $currentYear; $y >= $currentYear - 2; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit" name="create_period" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus"></i> Create Period
                    </button>
                </form>
            </div>
            
            <div class="card-body p-0">
                <!-- Year Tabs -->
                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <?php $firstYear = true; foreach ($periodsByYear as $year => $yearPeriods): ?>
                    <li class="nav-item">
                        <button class="nav-link <?php echo $firstYear ? 'active' : ''; ?>" 
                                data-bs-toggle="tab" data-bs-target="#year-<?php echo $year; ?>">
                            <?php echo $year; ?>
                        </button>
                    </li>
                    <?php $firstYear = false; endforeach; ?>
                    <?php if (empty($periodsByYear)): ?>
                    <li class="nav-item"><span class="nav-link active"><?php echo $currentYear; ?></span></li>
                    <?php endif; ?>
                </ul>
                
                <div class="tab-content p-3">
                    <?php $firstYear = true; foreach ($periodsByYear as $year => $yearPeriods): ?>
                    <div class="tab-pane fade <?php echo $firstYear ? 'show active' : ''; ?>" id="year-<?php echo $year; ?>">
                        <div class="row g-3">
                            <?php foreach ($yearPeriods as $p): ?>
                            <div class="col-md-3">
                                <a href="index.php?page=payroll/process&period_id=<?php echo $p['id']; ?>" 
                                   class="card h-100 text-decoration-none hover-lift">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0"><?php echo sanitize($p['period_name']); ?></h6>
                                            <span class="badge bg-<?php 
                                                echo $p['status'] === 'Draft' ? 'secondary' : 
                                                    ($p['status'] === 'Processed' ? 'info' : 
                                                    ($p['status'] === 'Approved' ? 'success' : 
                                                    ($p['status'] === 'Paid' ? 'primary' : 'warning'))); 
                                            ?>"><?php echo sanitize($p['status']); ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <i class="bi bi-people me-1"></i><?php echo $p['employee_count'] ?? 0; ?> employees
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
                        <p class="mt-3">No payroll periods found. Create a new period above.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Payroll Details View -->
        
        <!-- Header -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <a href="index.php?page=payroll/process" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cash-stack me-2"></i>
                        <?php echo sanitize($selectedPeriod['period_name']); ?>
                        <span class="badge bg-<?php 
                            echo $selectedPeriod['status'] === 'Draft' ? 'secondary' : 
                                ($selectedPeriod['status'] === 'Approved' ? 'success' : 'warning'); 
                        ?> ms-2"><?php echo sanitize($selectedPeriod['status']); ?></span>
                    </h5>
                </div>
                <div class="btn-group btn-group-sm">
                    <?php if ($selectedPeriod['status'] === 'Draft'): ?>
                    <a href="index.php?page=bulk-upload/salary" class="btn btn-outline-primary">
                        <i class="bi bi-cloud-upload me-1"></i>Bulk Upload Salary
                    </a>
                    <?php elseif ($selectedPeriod['status'] === 'Processed'): ?>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="approve_payroll" class="btn btn-success"
                                onclick="return confirm('Approve all payroll for this period?')">
                            <i class="bi bi-check-lg me-1"></i>Approve All
                        </button>
                    </form>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                        <button type="submit" name="delete_payroll" class="btn btn-outline-danger"
                                onclick="return confirm('Delete payroll and re-process?')">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if (in_array($selectedPeriod['status'], ['Processed', 'Approved'])): ?>
                    <a href="index.php?page=payroll/payslips&period_id=<?php echo $selectedPeriod['id']; ?>" 
                       class="btn btn-outline-primary">
                        <i class="bi bi-file-text me-1"></i>Payslips
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Summary -->
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
                    <?php if ($totals['held_count'] > 0): ?>
                    <div class="col">
                        <div class="small text-muted">Held</div>
                        <div class="h5 mb-0 text-warning"><?php echo $totals['held_count']; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Unit-wise Status -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-building me-2"></i>Unit-wise Processing Status</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Client</th>
                                <th>Unit</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Employees</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($unitStatuses)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    No units found. Please upload attendance first.
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($unitStatuses as $us): ?>
                            <tr>
                                <td><?php echo sanitize($us['client_name'] ?? 'N/A'); ?></td>
                                <td><?php echo sanitize($us['unit_name']); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?php 
                                        echo $us['status'] === 'pending' ? 'secondary' : 
                                            ($us['status'] === 'processed' ? 'info' : 
                                            ($us['status'] === 'finalized' ? 'success' : 'warning'));
                                    ?>"><?php echo ucfirst($us['status']); ?></span>
                                </td>
                                <td class="text-end"><?php echo $us['employee_count'] ?? 0; ?></td>
                                <td class="text-end"><?php echo formatCurrency($us['total_gross'] ?? 0); ?></td>
                                <td class="text-end"><?php echo formatCurrency($us['total_net'] ?? 0); ?></td>
                                <td class="text-center">
                                    <?php if ($us['status'] === 'pending'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                                        <input type="hidden" name="unit_id" value="<?php echo $us['unit_id']; ?>">
                                        <button type="submit" name="process_unit" class="btn btn-sm btn-primary">
                                            <i class="bi bi-play-fill"></i> Process
                                        </button>
                                    </form>
                                    <?php elseif ($us['status'] === 'processed'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                                        <input type="hidden" name="unit_id" value="<?php echo $us['unit_id']; ?>">
                                        <button type="submit" name="finalize_unit" class="btn btn-sm btn-success"
                                                onclick="return confirm('Finalize this unit?')">
                                            <i class="bi bi-check-lg"></i> Finalize
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-success"><i class="bi bi-check-circle"></i> Done</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-end">
                    <input type="hidden" name="page" value="payroll/process">
                    <input type="hidden" name="period_id" value="<?php echo $selectedPeriod['id']; ?>">
                    
                    <div class="col-md-3">
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
                    
                    <div class="col-md-3">
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
                    
                    <div class="col-md-3">
                        <label class="form-label small">Search</label>
                        <input type="text" name="search" class="form-control form-control-sm" 
                               placeholder="Name or Code" value="<?php echo sanitize($searchTerm); ?>">
                    </div>
                    
                    <div class="col-md-3">
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
                </form>
            </div>
        </div>
        
        <!-- Payroll Data Grid (Excel-like) -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-table me-2"></i>Payroll Data</h6>
                <?php if (!empty($payrollData) && $selectedPeriod['status'] !== 'Approved'): ?>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleEditMode()">
                    <i class="bi bi-pencil me-1"></i>Edit Mode
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" id="payrollTable">
                        <thead class="table-light">
                            <tr>
                                <th>Emp Code</th>
                                <th>Name</th>
                                <th>Client/Unit</th>
                                <th class="text-center">Days</th>
                                <th class="text-end">Basic+DA</th>
                                <th class="text-end">HRA</th>
                                <th class="text-end">LWW</th>
                                <th class="text-end">Bonus</th>
                                <th class="text-end">Wash</th>
                                <th class="text-end">Other</th>
                                <th class="text-end">Gross</th>
                                <th class="text-end">Ded</th>
                                <th class="text-end">Net Pay</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payrollData)): ?>
                            <tr>
                                <td colspan="14" class="text-center py-4 text-muted">
                                    <?php if ($selectedPeriod['status'] === 'Draft'): ?>
                                    No payroll data. Process units above to generate payroll.
                                    <?php else: ?>
                                    No records found matching your filters.
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payrollData as $row): 
                                $basicDA = floatval($row['basic_da_display'] ?? ($row['basic'] ?? 0) + ($row['da'] ?? 0));
                            ?>
                            <tr class="<?php echo ($row['salary_hold'] ?? 0) ? 'table-warning' : ''; ?>"
                                data-emp-code="<?php echo $row['employee_code']; ?>">
                                <td><?php echo sanitize($row['employee_code']); ?></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><small><?php echo sanitize($row['client_name']); ?> / <?php echo sanitize($row['unit_name']); ?></small></td>
                                <td class="text-center"><?php echo $row['paid_days'] ?? 0; ?></td>
                                <td class="text-end editable-cell" data-field="basic_da"><?php echo formatCurrency($basicDA); ?></td>
                                <td class="text-end editable-cell" data-field="hra"><?php echo formatCurrency($row['hra'] ?? 0); ?></td>
                                <td class="text-end editable-cell" data-field="lww"><?php echo formatCurrency($row['lww'] ?? 0); ?></td>
                                <td class="text-end editable-cell" data-field="bonus"><?php echo formatCurrency($row['bonus_amount'] ?? 0); ?></td>
                                <td class="text-end editable-cell" data-field="washing"><?php echo formatCurrency($row['washing_allowance'] ?? 0); ?></td>
                                <td class="text-end editable-cell" data-field="other"><?php echo formatCurrency($row['other_allowance'] ?? 0); ?></td>
                                <td class="text-end"><strong class="gross-display"><?php echo formatCurrency($row['gross_earnings'] ?? 0); ?></strong></td>
                                <td class="text-end text-danger"><?php echo formatCurrency($row['total_deductions'] ?? 0); ?></td>
                                <td class="text-end"><strong class="text-success net-display"><?php echo formatCurrency($row['net_pay'] ?? 0); ?></strong></td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo ($row['status'] ?? '') === 'Processed' ? 'info' : 
                                            (($row['status'] ?? '') === 'Approved' ? 'success' : 'secondary');
                                    ?>"><?php echo sanitize($row['status'] ?? 'Draft'); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<script>
var allUnits = <?php echo json_encode($allUnits); ?>;

function filterUnitsDropdown() {
    var clientId = $('#filterClient').val();
    var $unitSelect = $('#filterUnit');
    $unitSelect.find('option:not(:first)').remove();
    
    allUnits.forEach(function(unit) {
        if (!clientId || unit.client_id == clientId) {
            $unitSelect.append('<option value="' + unit.id + '">' + unit.name + '</option>');
        }
    });
}

var editMode = false;

function toggleEditMode() {
    editMode = !editMode;
    $('.editable-cell').each(function() {
        var $cell = $(this);
        var field = $cell.data('field');
        var value = parseFloat($cell.text().replace(/[₹,]/g, '')) || 0;
        
        if (editMode) {
            $cell.html('<input type="number" class="form-control form-control-sm text-end salary-input" ' +
                       'step="0.01" value="' + value + '" data-field="' + field + '" style="width: 100px;">');
        } else {
            $cell.text('₹' + value.toLocaleString('en-IN', {minimumFractionDigits: 2}));
        }
    });
    
    if (editMode) {
        $('#payrollTable').find('thead tr').append('<th>Action</th>');
        $('#payrollTable tbody tr').each(function() {
            var $row = $(this);
            var empCode = $row.data('emp-code');
            $row.append('<td><button class="btn btn-sm btn-success save-row" data-emp="' + empCode + '"><i class="bi bi-check"></i></button></td>');
        });
    } else {
        $('#payrollTable th:last-child, #payrollTable td:last-child').remove();
    }
}

$(document).on('input', '.salary-input', function() {
    // Recalculate row totals
    var $row = $(this).closest('tr');
    var gross = 0;
    $row.find('.salary-input').each(function() {
        gross += parseFloat($(this).val()) || 0;
    });
    
    $row.find('.gross-display').text('₹' + gross.toLocaleString('en-IN', {minimumFractionDigits: 2}));
    
    // Simple net calculation (gross - deductions)
    var ded = parseFloat($row.find('.text-danger').text().replace(/[₹,]/g, '')) || 0;
    var net = gross - ded;
    $row.find('.net-display').text('₹' + net.toLocaleString('en-IN', {minimumFractionDigits: 2}));
});

$(document).on('click', '.save-row', function() {
    var empCode = $(this).data('emp');
    var $row = $(this).closest('tr');
    
    var data = {
        period_id: <?php echo $selectedPeriod['id'] ?? 0; ?>,
        emp_code: empCode,
        basic_da: $row.find('[data-field="basic_da"]').val(),
        hra: $row.find('[data-field="hra"]').val(),
        lww: $row.find('[data-field="lww"]').val(),
        bonus: $row.find('[data-field="bonus"]').val(),
        washing: $row.find('[data-field="washing"]').val(),
        other: $row.find('[data-field="other"]').val()
    };
    
    // AJAX call to update
    $.post('index.php?page=api/payroll-update', data, function(res) {
        if (res.success) {
            alert('Saved!');
        } else {
            alert('Error: ' + (res.message || 'Unknown error'));
        }
    });
});
</script>
