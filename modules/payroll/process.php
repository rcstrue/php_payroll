<?php
/**
 * RCS HRMS Pro - Process Payroll
 * Client/Unit wise payroll processing like Add Attendance
 */

$pageTitle = 'Process Payroll';

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

// Ensure payroll tables exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `payroll_periods` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `period_name` varchar(50) NOT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `unit_id` int(11) DEFAULT NULL,
        `client_id` int(11) DEFAULT NULL,
        `status` enum('Draft','Processed','Approved','Paid') DEFAULT 'Draft',
        `pay_days` int(2) DEFAULT 30,
        `created_by` int(11) DEFAULT NULL,
        `processed_at` timestamp NULL DEFAULT NULL,
        `approved_at` timestamp NULL DEFAULT NULL,
        `approved_by` int(11) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_period_unit` (`month`, `year`, `unit_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `payroll_records` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `period_id` int(11) NOT NULL,
        `employee_id` int(11) NOT NULL,
        `paid_days` decimal(5,2) DEFAULT 0.00,
        `basic_wage` decimal(12,2) DEFAULT 0.00,
        `da` decimal(12,2) DEFAULT 0.00,
        `hra` decimal(12,2) DEFAULT 0.00,
        `gross_earnings` decimal(12,2) DEFAULT 0.00,
        `pf_employee` decimal(12,2) DEFAULT 0.00,
        `esi_employee` decimal(12,2) DEFAULT 0.00,
        `pt` decimal(12,2) DEFAULT 0.00,
        `advance_deduction` decimal(12,2) DEFAULT 0.00,
        `other_deductions` decimal(12,2) DEFAULT 0.00,
        `total_deductions` decimal(12,2) DEFAULT 0.00,
        `net_pay` decimal(12,2) DEFAULT 0.00,
        `pf_employer` decimal(12,2) DEFAULT 0.00,
        `esi_employer` decimal(12,2) DEFAULT 0.00,
        `status` enum('Draft','Processed','Paid') DEFAULT 'Draft',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_period_emp` (`period_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("Table creation failed: " . $e->getMessage());
}

// Handle Process Payroll
$processResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payroll'])) {
    $unitId = (int)$_POST['unit_id'];
    $clientId = (int)$_POST['client_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    try {
        // Create or get payroll period
        $periodStmt = $db->prepare("
            INSERT INTO payroll_periods (period_name, month, year, unit_id, client_id, status, pay_days, created_by)
            VALUES (?, ?, ?, ?, ?, 'Draft', ?, ?)
            ON DUPLICATE KEY UPDATE pay_days = VALUES(pay_days)
        ");
        $periodName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $payDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $periodStmt->execute([$periodName, $month, $year, $unitId, $clientId, $payDays, $_SESSION['user_id'] ?? 1]);
        
        // Get period ID
        $periodId = $db->lastInsertId();
        if (!$periodId) {
            $periodStmt = $db->prepare("SELECT id FROM payroll_periods WHERE month = ? AND year = ? AND unit_id = ?");
            $periodStmt->execute([$month, $year, $unitId]);
            $periodId = $periodStmt->fetchColumn();
        }
        
        // Get employees with attendance and salary
        $empStmt = $db->prepare("
            SELECT e.employee_code, e.full_name, e.worker_category,
                   ess.basic_wage, ess.da, ess.hra, ess.gross_salary,
                   ess.pf_applicable, ess.esi_applicable,
                   att.total_present, att.total_extra, att.overtime_hours,
                   adv.adv1, adv.adv2, adv.office_advance, adv.dress_advance
            FROM employees e
            LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
            LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                AND att.unit_id = ? AND att.month = ? AND att.year = ?
            LEFT JOIN employee_advances adv ON adv.employee_id = e.employee_code 
                AND adv.unit_id = ? AND adv.month = ? AND adv.year = ?
            WHERE e.unit_id = ? AND e.status = 'approved'
        ");
        $empStmt->execute([$unitId, $month, $year, $unitId, $month, $year, $unitId]);
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $totalGross = 0;
        $totalNet = 0;
        $totalPF = 0;
        $totalESI = 0;
        
        foreach ($employees as $emp) {
            $empCode = (int)$emp['employee_code'];
            $paidDays = floatval($emp['total_present'] ?? $payDays);
            $basicWage = floatval($emp['basic_wage'] ?? 0);
            $grossSalary = floatval($emp['gross_salary'] ?? 0);
            
            // Calculate per day salary
            $perDaySalary = $grossSalary / $payDays;
            $grossEarnings = round($perDaySalary * $paidDays, 2);
            
            // Calculate deductions
            $pfEmployee = 0;
            $esiEmployee = 0;
            $pt = 200; // Fixed PT (adjust as needed)
            
            if (!empty($emp['pf_applicable']) && $basicWage > 0) {
                $pfEmployee = min(round($basicWage * 0.12, 2), 1800); // 12% of basic, max 1800
            }
            
            if (!empty($emp['esi_applicable']) && $grossEarnings <= 21000) {
                $esiEmployee = round($grossEarnings * 0.0075, 2); // 0.75% of gross
            }
            
            // Advance deductions
            $advDeduction = floatval(($emp['adv1'] ?? 0) + ($emp['adv2'] ?? 0));
            
            $totalDeductions = $pfEmployee + $esiEmployee + $pt + $advDeduction;
            $netPay = max(0, $grossEarnings - $totalDeductions);
            
            // Employer contributions
            $pfEmployer = $pfEmployee;
            $esiEmployer = round($grossEarnings * 0.0325, 2); // 3.25%
            
            // Insert payroll record
            $insertStmt = $db->prepare("
                INSERT INTO payroll_records 
                (period_id, employee_id, paid_days, basic_wage, da, hra, gross_earnings,
                 pf_employee, esi_employee, pt, advance_deduction, total_deductions, net_pay,
                 pf_employer, esi_employer, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Processed')
                ON DUPLICATE KEY UPDATE 
                    paid_days = VALUES(paid_days),
                    gross_earnings = VALUES(gross_earnings),
                    net_pay = VALUES(net_pay),
                    status = 'Processed'
            ");
            $insertStmt->execute([
                $periodId, $empCode, $paidDays, $basicWage, 
                floatval($emp['da'] ?? 0), floatval($emp['hra'] ?? 0), $grossEarnings,
                $pfEmployee, $esiEmployee, $pt, $advDeduction, $totalDeductions, $netPay,
                $pfEmployer, $esiEmployer
            ]);
            
            $processedCount++;
            $totalGross += $grossEarnings;
            $totalNet += $netPay;
            $totalPF += $pfEmployee;
            $totalESI += $esiEmployee;
        }
        
        // Update period status
        $db->prepare("UPDATE payroll_periods SET status = 'Processed', processed_at = NOW() WHERE id = ?")
           ->execute([$periodId]);
        
        $processResult = [
            'success' => true,
            'processed' => $processedCount,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_pf' => $totalPF,
            'total_esi' => $totalESI
        ];
        
        setFlash('success', "Payroll processed for $processedCount employees. Total Net Pay: ₹" . number_format($totalNet));
        
        // Redirect
        $redirectUrl = "index.php?page=payroll/process&client_id=$clientId&unit_id=$unitId&month=$month&year=$year&load=1";
        echo "<script>window.location.href='$redirectUrl';</script>";
        exit;
        
    } catch (Exception $e) {
        setFlash('error', 'Payroll processing failed: ' . $e->getMessage());
    }
}

// Get payroll data when unit is selected
$payrollData = [];
$payrollPeriod = null;
$totals = ['employees' => 0, 'gross' => 0, 'deductions' => 0, 'net' => 0, 'pf' => 0, 'esi' => 0];

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
                SELECT pr.*, e.full_name, e.worker_category, e.designation
                FROM payroll_records pr
                LEFT JOIN employees e ON e.employee_code = pr.employee_id
                WHERE pr.period_id = ?
                ORDER BY e.employee_code
            ");
            $stmt->execute([$payrollPeriod['id']]);
            $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            foreach ($payrollData as $row) {
                $totals['employees']++;
                $totals['gross'] += floatval($row['gross_earnings']);
                $totals['deductions'] += floatval($row['total_deductions']);
                $totals['net'] += floatval($row['net_pay']);
                $totals['pf'] += floatval($row['pf_employee']);
                $totals['esi'] += floatval($row['esi_employee']);
            }
        } else {
            // Get employees for preview (no payroll yet)
            $stmt = $db->prepare("
                SELECT e.employee_code, e.full_name, e.worker_category, e.designation,
                       ess.basic_wage, ess.gross_salary,
                       att.total_present, att.total_extra,
                       adv.adv1, adv.adv2, adv.office_advance, adv.dress_advance
                FROM employees e
                LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
                LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                    AND att.unit_id = ? AND att.month = ? AND att.year = ?
                LEFT JOIN employee_advances adv ON adv.employee_id = e.employee_code 
                    AND adv.unit_id = ? AND adv.month = ? AND adv.year = ?
                WHERE e.unit_id = ? AND e.status = 'approved'
                ORDER BY e.employee_code
            ");
            $stmt->execute([$selectedUnit, $selectedMonth, $selectedYear, $selectedUnit, $selectedMonth, $selectedYear, $selectedUnit]);
            $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error fetching payroll: " . $e->getMessage());
    }
}

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cash-stack me-2"></i>Process Payroll</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="payroll/process">
                    
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
<!-- Payroll Grid -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-secondary ms-2">Days: <?php echo $daysInMonth; ?></span>
                    <span class="badge bg-primary ms-2"><?php echo count($payrollData); ?> Employees</span>
                    <?php if ($payrollPeriod): ?>
                    <span class="badge bg-<?php echo $payrollPeriod['status'] == 'Processed' ? 'success' : ($payrollPeriod['status'] == 'Approved' ? 'primary' : 'secondary'); ?> ms-2">
                        <?php echo $payrollPeriod['status']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($payrollData)): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportPayroll()">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <?php endif; ?>
                    <a href="index.php?page=payroll/process" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payrollData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1"></i>
                    <p class="mt-2">No employees found for this unit.</p>
                </div>
                <?php else: ?>
                
                <?php if (!$payrollPeriod): ?>
                <!-- Preview Mode - Show Process Button -->
                <div class="alert alert-warning m-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Preview Mode:</strong> Payroll not yet processed for this period. 
                    Review the data below and click "Process Payroll" to generate salary.
                </div>
                <form method="POST" class="m-3">
                    <input type="hidden" name="client_id" value="<?php echo $selectedClient; ?>">
                    <input type="hidden" name="unit_id" value="<?php echo $selectedUnit; ?>">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    <button type="submit" name="process_payroll" class="btn btn-success" onclick="return confirm('Process payroll for this period?')">
                        <i class="bi bi-play-fill me-1"></i>Process Payroll
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0" style="font-size: 12px;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 80px;">Emp Code</th>
                                <th style="width: 150px;">Employee Name</th>
                                <th style="width: 80px;">Category</th>
                                <th style="width: 60px;" class="text-center">Days</th>
                                <th style="width: 100px;" class="text-end">Basic</th>
                                <th style="width: 100px;" class="text-end">Gross</th>
                                <th style="width: 80px;" class="text-end text-danger">PF</th>
                                <th style="width: 80px;" class="text-end text-danger">ESI</th>
                                <th style="width: 80px;" class="text-end text-danger">PT</th>
                                <th style="width: 80px;" class="text-end text-danger">Adv</th>
                                <th style="width: 100px;" class="text-end text-success">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sr = 1;
                            foreach ($payrollData as $row): 
                                if ($payrollPeriod) {
                                    // Processed payroll data
                                    $paidDays = $row['paid_days'];
                                    $basic = $row['basic_wage'];
                                    $gross = $row['gross_earnings'];
                                    $pf = $row['pf_employee'];
                                    $esi = $row['esi_employee'];
                                    $pt = $row['pt'];
                                    $adv = $row['advance_deduction'];
                                    $net = $row['net_pay'];
                                } else {
                                    // Preview data
                                    $paidDays = $row['total_present'] ?? $daysInMonth;
                                    $basic = $row['basic_wage'] ?? 0;
                                    $grossSalary = $row['gross_salary'] ?? 0;
                                    $perDay = $grossSalary / $daysInMonth;
                                    $gross = round($perDay * $paidDays, 2);
                                    $pf = ($basic > 0 && $basic <= 15000) ? min(round($basic * 0.12, 2), 1800) : 0;
                                    $esi = ($gross <= 21000) ? round($gross * 0.0075, 2) : 0;
                                    $pt = 200;
                                    $adv = ($row['adv1'] ?? 0) + ($row['adv2'] ?? 0);
                                    $net = max(0, $gross - $pf - $esi - $pt - $adv);
                                }
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $sr++; ?></td>
                                <td><code><?php echo $row['employee_code'] ?? $row['employee_id']; ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo sanitize($row['worker_category']); ?></span></td>
                                <td class="text-center"><?php echo $paidDays; ?></td>
                                <td class="text-end"><?php echo number_format($basic, 0); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($gross, 0); ?></td>
                                <td class="text-end text-danger"><?php echo $pf > 0 ? number_format($pf, 0) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $esi > 0 ? number_format($esi, 0) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $pt > 0 ? number_format($pt, 0) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $adv > 0 ? number_format($adv, 0) : '-'; ?></td>
                                <td class="text-end fw-bold text-success"><?php echo number_format($net, 0); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">TOTAL</td>
                                <td></td>
                                <td></td>
                                <td class="text-end"><?php echo number_format($totals['gross'] > 0 ? $totals['gross'] : array_sum(array_column($payrollData, 'gross_earnings')), 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['pf'] > 0 ? $totals['pf'] : 0, 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['esi'] > 0 ? $totals['esi'] : 0, 0); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['employees'] * 200, 0); ?></td>
                                <td class="text-end text-danger"></td>
                                <td class="text-end text-success"><?php echo number_format($totals['net'] > 0 ? $totals['net'] : 0, 0); ?></td>
                            </tr>
                        </tfoot>
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

// Export payroll
function exportPayroll() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'index.php?' + params.toString();
}
</script>
JS;
?>
