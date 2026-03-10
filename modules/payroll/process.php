<?php
/**
 * RCS HRMS Pro - Process Payroll (Wage Register)
 * Full wage register type sheet with all earnings and deductions
 * 
 * Database Schema Notes:
 * - employees.id = VARCHAR(36) UUID
 * - employees.employee_code = INT(10) UNSIGNED
 * - attendance_summary.employee_id = INT(11) → matches employee_code
 * - employee_advances.employee_id = VARCHAR(36) → matches employees.id
 * - employee_salary_structures.employee_id = VARCHAR(36) → matches employees.id
 * - payroll.employee_id = INT(11) → matches employee_code
 */

$pageTitle = 'Wage Register';

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

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);

// Helper function to calculate payroll
function calculatePayroll($emp, $daysInMonth) {
    $paidDays = floatval($emp['total_present'] ?? $daysInMonth);
    $basicWage = floatval($emp['basic_wage'] ?? 0);
    $da = floatval($emp['da'] ?? 0);
    $hra = floatval($emp['hra'] ?? 0);
    $otherAllowances = floatval($emp['other_allowance'] ?? 0);
    $grossSalary = floatval($emp['gross_salary'] ?? 0);
    
    if ($grossSalary == 0) {
        $grossSalary = $basicWage + $da + $hra + $otherAllowances;
    }
    
    // Calculate per day and actual earnings
    $perDaySalary = $grossSalary > 0 ? $grossSalary / $daysInMonth : 0;
    $basic = round(($basicWage / $daysInMonth) * $paidDays, 0);
    $daAmt = round(($da / $daysInMonth) * $paidDays, 0);
    $hraAmt = round(($hra / $daysInMonth) * $paidDays, 0);
    $otherAmt = round(($otherAllowances / $daysInMonth) * $paidDays, 0);
    $grossEarnings = round($perDaySalary * $paidDays, 0);
    
    // Overtime calculation (if OT hours present)
    $otHours = floatval($emp['overtime_hours'] ?? 0);
    $otAmount = round($otHours * ($perDaySalary / 8) * 2, 0); // Double rate for OT
    
    // PF Employee (12% of basic, max 1800)
    $pfEmployee = 0;
    if (!empty($emp['pf_applicable']) && $basicWage > 0) {
        $pfEmployee = min(round($basicWage * 0.12, 0), 1800);
    }
    
    // ESI Employee (0.75% of gross, if gross <= 21000)
    $esiEmployee = 0;
    if (!empty($emp['esi_applicable']) && $grossEarnings <= 21000) {
        $esiEmployee = round($grossEarnings * 0.0075, 0);
    }
    
    // PT (Professional Tax - fixed, varies by state)
    $pt = $grossEarnings >= 15000 ? 200 : 0;
    
    // Advance deductions
    $advDeduction = floatval(($emp['adv1'] ?? 0) + ($emp['adv2'] ?? 0));
    $officeAdv = floatval($emp['office_advance'] ?? 0);
    $dressAdv = floatval($emp['dress_advance'] ?? 0);
    
    $totalDeductions = $pfEmployee + $esiEmployee + $pt + $advDeduction + $officeAdv + $dressAdv;
    $netPay = max(0, $grossEarnings + $otAmount - $totalDeductions);
    
    // Employer contributions
    $pfEmployer = $pfEmployee; // Same as employee for simplicity
    $esiEmployer = $grossEarnings <= 21000 ? round($grossEarnings * 0.0325, 0) : 0; // 3.25%
    $employerContribution = $pfEmployer + $esiEmployer;
    $ctc = $grossEarnings + $employerContribution;
    
    return [
        'paid_days' => $paidDays,
        'basic' => $basic,
        'da' => $daAmt,
        'hra' => $hraAmt,
        'other_allowances' => $otherAmt,
        'ot_hours' => $otHours,
        'ot_amount' => $otAmount,
        'gross_earnings' => $grossEarnings,
        'pf_employee' => $pfEmployee,
        'esi_employee' => $esiEmployee,
        'pt' => $pt,
        'adv1' => floatval($emp['adv1'] ?? 0),
        'adv2' => floatval($emp['adv2'] ?? 0),
        'office_adv' => $officeAdv,
        'dress_adv' => $dressAdv,
        'advance_deduction' => $advDeduction + $officeAdv + $dressAdv,
        'other_deductions' => 0,
        'total_deductions' => $totalDeductions,
        'net_pay' => $netPay,
        'pf_employer' => $pfEmployer,
        'esi_employer' => $esiEmployer,
        'employer_contribution' => $employerContribution,
        'ctc' => $ctc
    ];
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
            SELECT id FROM payroll_periods WHERE month = ? AND year = ?
        ");
        $periodStmt->execute([$month, $year]);
        $periodId = $periodStmt->fetchColumn();
        
        if (!$periodId) {
            $periodStmt = $db->prepare("
                INSERT INTO payroll_periods (period_name, month, year, start_date, end_date, status, processed_by, processed_at)
                VALUES (?, ?, ?, ?, ?, 'Processed', ?, NOW())
            ");
            $periodName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
            $endDate = date('Y-m-t', strtotime($startDate));
            $periodStmt->execute([$periodName, $month, $year, $startDate, $endDate, $_SESSION['user_id'] ?? 1]);
            $periodId = $db->lastInsertId();
        }
        
        // Get employees with attendance and salary
        // Key: attendance_summary.employee_id matches employees.employee_code (INT)
        // Key: employee_advances.employee_id matches employees.id (UUID)
        // Key: employee_salary_structures.employee_id matches employees.id (UUID)
        $empStmt = $db->prepare("
            SELECT e.id, e.employee_code, e.full_name, e.worker_category, e.designation,
                   ess.basic_wage, ess.da, ess.hra, ess.other_allowance, ess.gross_salary,
                   ess.pf_applicable, ess.esi_applicable,
                   att.total_present, att.overtime_hours,
                   adv.adv1, adv.adv2, adv.office_advance, adv.dress_advance
            FROM employees e
            LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
            LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                AND att.unit_id = ? AND att.month = ? AND att.year = ?
            LEFT JOIN employee_advances adv ON adv.employee_id = e.id 
                AND adv.unit_id = ? AND adv.month = ? AND adv.year = ?
            WHERE e.unit_id = ? AND e.status != 'pending_hr_verification'
        ");
        $empStmt->execute([$unitId, $month, $year, $unitId, $month, $year, $unitId]);
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $processedCount = 0;
        $totals = ['gross' => 0, 'net' => 0, 'pf_emp' => 0, 'esi_emp' => 0, 'pf_employer' => 0, 'esi_employer' => 0, 'ctc' => 0];
        
        foreach ($employees as $emp) {
            // Skip if no salary structure
            if (empty($emp['basic_wage']) && empty($emp['gross_salary'])) {
                continue;
            }
            
            $calc = calculatePayroll($emp, $daysInMonth);
            $empCode = (int)$emp['employee_code'];
            
            // Check if payroll record exists
            $checkStmt = $db->prepare("SELECT id FROM payroll WHERE payroll_period_id = ? AND employee_id = ?");
            $checkStmt->execute([$periodId, $empCode]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists) {
                // Update
                $updateStmt = $db->prepare("
                    UPDATE payroll SET 
                        total_days = ?, paid_days = ?, basic = ?, da = ?, hra = ?, other_allowance = ?,
                        overtime_hours = ?, overtime_amount = ?, gross_earnings = ?,
                        pf_employee = ?, esi_employee = ?, professional_tax = ?, salary_advance = ?,
                        total_deductions = ?, net_pay = ?,
                        pf_employer = ?, esi_employer = ?, total_employer_contribution = ?, ctc = ?,
                        status = 'Processed', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $daysInMonth, $calc['paid_days'], $calc['basic'], $calc['da'], $calc['hra'], $calc['other_allowances'],
                    $calc['ot_hours'], $calc['ot_amount'], $calc['gross_earnings'],
                    $calc['pf_employee'], $calc['esi_employee'], $calc['pt'], $calc['advance_deduction'],
                    $calc['total_deductions'], $calc['net_pay'],
                    $calc['pf_employer'], $calc['esi_employer'], $calc['employer_contribution'], $calc['ctc'],
                    $exists
                ]);
            } else {
                // Insert
                $insertStmt = $db->prepare("
                    INSERT INTO payroll 
                    (payroll_period_id, employee_id, unit_id, total_days, paid_days, basic, da, hra, other_allowance,
                     overtime_hours, overtime_amount, gross_earnings,
                     pf_employee, esi_employee, professional_tax, salary_advance,
                     total_deductions, net_pay, 
                     pf_employer, esi_employer, total_employer_contribution, ctc, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Processed')
                ");
                $insertStmt->execute([
                    $periodId, $empCode, $unitId, $daysInMonth, $calc['paid_days'],
                    $calc['basic'], $calc['da'], $calc['hra'], $calc['other_allowances'],
                    $calc['ot_hours'], $calc['ot_amount'], $calc['gross_earnings'],
                    $calc['pf_employee'], $calc['esi_employee'], $calc['pt'], $calc['advance_deduction'],
                    $calc['total_deductions'], $calc['net_pay'],
                    $calc['pf_employer'], $calc['esi_employer'], $calc['employer_contribution'], $calc['ctc']
                ]);
            }
            
            $processedCount++;
            $totals['gross'] += $calc['gross_earnings'];
            $totals['net'] += $calc['net_pay'];
            $totals['pf_emp'] += $calc['pf_employee'];
            $totals['esi_emp'] += $calc['esi_employee'];
            $totals['pf_employer'] += $calc['pf_employer'];
            $totals['esi_employer'] += $calc['esi_employer'];
            $totals['ctc'] += $calc['ctc'];
        }
        
        setFlash('success', "Payroll processed for $processedCount employees. Net Pay: ₹" . number_format($totals['net']) . " | CTC: ₹" . number_format($totals['ctc']));
        
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
$totals = [
    'employees' => 0, 'present' => 0, 'basic' => 0, 'da' => 0, 'hra' => 0, 
    'gross' => 0, 'pf_emp' => 0, 'esi_emp' => 0, 'pt' => 0, 
    'adv' => 0, 'deductions' => 0, 'net' => 0,
    'pf_employer' => 0, 'esi_employer' => 0, 'employer_contrib' => 0, 'ctc' => 0
];

if ($selectedUnit && isset($_GET['load'])) {
    try {
        // First check if processed payroll records exist for this unit/month/year
        $processedStmt = $db->prepare("
            SELECT p.*, e.id as emp_uuid, e.employee_code, e.full_name, e.worker_category, e.designation,
                   att.total_present, att.overtime_hours,
                   adv.adv1, adv.adv2, adv.office_advance, adv.dress_advance,
                   pp.status as period_status
            FROM payroll p
            JOIN payroll_periods pp ON pp.id = p.payroll_period_id
            LEFT JOIN employees e ON e.employee_code = p.employee_id
            LEFT JOIN attendance_summary att ON att.employee_id = p.employee_id 
                AND att.unit_id = p.unit_id AND att.month = pp.month AND att.year = pp.year
            LEFT JOIN employee_advances adv ON adv.employee_id = e.id 
                AND adv.unit_id = p.unit_id AND adv.month = pp.month AND adv.year = pp.year
            WHERE p.unit_id = ? AND pp.month = ? AND pp.year = ?
                AND p.status IN ('Processed', 'Approved', 'Paid')
            ORDER BY e.employee_code
        ");
        $processedStmt->execute([$selectedUnit, $selectedMonth, $selectedYear]);
        $processedData = $processedStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($processedData)) {
            // Show processed data
            $payrollData = $processedData;
            $payrollPeriod = true; // Mark as processed
            
            // Calculate totals from processed data
            foreach ($payrollData as $row) {
                $totals['employees']++;
                $totals['present'] += floatval($row['paid_days']);
                $totals['basic'] += floatval($row['basic']);
                $totals['da'] += floatval($row['da']);
                $totals['hra'] += floatval($row['hra']);
                $totals['gross'] += floatval($row['gross_earnings']);
                $totals['pf_emp'] += floatval($row['pf_employee']);
                $totals['esi_emp'] += floatval($row['esi_employee']);
                $totals['pt'] += floatval($row['professional_tax']);
                $totals['adv'] += floatval($row['salary_advance']);
                $totals['deductions'] += floatval($row['total_deductions']);
                $totals['net'] += floatval($row['net_pay']);
                $totals['pf_employer'] += floatval($row['pf_employer']);
                $totals['esi_employer'] += floatval($row['esi_employer']);
                $totals['employer_contrib'] += floatval($row['total_employer_contribution']);
                $totals['ctc'] += floatval($row['ctc']);
            }
        } else {
            // Show preview - get employees with salary and attendance
            $stmt = $db->prepare("
                SELECT e.id, e.employee_code, e.full_name, e.worker_category, e.designation,
                       ess.basic_wage, ess.da, ess.hra, ess.other_allowance, ess.gross_salary,
                       ess.pf_applicable, ess.esi_applicable,
                       att.total_present, att.overtime_hours,
                       adv.adv1, adv.adv2, adv.office_advance, adv.dress_advance
                FROM employees e
                LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
                LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                    AND att.unit_id = ? AND att.month = ? AND att.year = ?
                LEFT JOIN employee_advances adv ON adv.employee_id = e.id 
                    AND adv.unit_id = ? AND adv.month = ? AND adv.year = ?
                WHERE e.unit_id = ? AND e.status != 'pending_hr_verification'
                ORDER BY e.employee_code
            ");
            $stmt->execute([$selectedUnit, $selectedMonth, $selectedYear, $selectedUnit, $selectedMonth, $selectedYear, $selectedUnit]);
            $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $payrollPeriod = null; // Mark as preview
            
            // Calculate preview totals - show ALL employees even without salary
            foreach ($payrollData as $row) {
                // Calculate even with 0 salary
                $calc = calculatePayroll($row, $daysInMonth);
                $totals['employees']++;
                $totals['present'] += $calc['paid_days'];
                $totals['basic'] += $calc['basic'];
                $totals['da'] += $calc['da'];
                $totals['hra'] += $calc['hra'];
                $totals['gross'] += $calc['gross_earnings'];
                $totals['pf_emp'] += $calc['pf_employee'];
                $totals['esi_emp'] += $calc['esi_employee'];
                $totals['pt'] += $calc['pt'];
                $totals['adv'] += $calc['advance_deduction'];
                $totals['deductions'] += $calc['total_deductions'];
                $totals['net'] += $calc['net_pay'];
                $totals['pf_employer'] += $calc['pf_employer'];
                $totals['esi_employer'] += $calc['esi_employer'];
                $totals['employer_contrib'] += $calc['employer_contribution'];
                $totals['ctc'] += $calc['ctc'];
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching payroll: " . $e->getMessage());
    }
}

// Handle Export
if (isset($_GET['export']) && !empty($payrollData)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wage_register_' . $selectedMonth . '_' . $selectedYear . '.csv"');
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['WAGE REGISTER - ' . date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear))]);
    fputcsv($output, []);
    
    $headers = ['#', 'Emp Code', 'Employee Name', 'Designation', 'Category', 'Days', 
                'Basic', 'DA', 'HRA', 'Other', 'OT Amt', 'Gross',
                'PF(Emp)', 'ESI(Emp)', 'PT', 'Adv', 'Total Ded', 'Net Pay',
                'PF(Empr)', 'ESI(Empr)', 'Empr Contrib', 'CTC'];
    fputcsv($output, $headers);
    
    $sr = 1;
    foreach ($payrollData as $row) {
        if ($payrollPeriod) {
            $totalDed = $row['pf_employee'] + $row['esi_employee'] + $row['professional_tax'] + $row['salary_advance'];
            $data = [
                $sr++,
                $row['employee_code'],
                $row['full_name'],
                $row['designation'],
                $row['worker_category'],
                $row['paid_days'],
                $row['basic'],
                $row['da'],
                $row['hra'],
                $row['other_allowance'],
                $row['overtime_amount'],
                $row['gross_earnings'],
                $row['pf_employee'],
                $row['esi_employee'],
                $row['professional_tax'],
                $row['salary_advance'],
                $totalDed,
                $row['net_pay'],
                $row['pf_employer'],
                $row['esi_employer'],
                $row['total_employer_contribution'],
                $row['ctc']
            ];
        } else {
            // Skip if no salary
            if (empty($row['basic_wage']) && empty($row['gross_salary'])) {
                continue;
            }
            $calc = calculatePayroll($row, $daysInMonth);
            $data = [
                $sr++,
                $row['employee_code'],
                $row['full_name'],
                $row['designation'],
                $row['worker_category'],
                $calc['paid_days'],
                $calc['basic'],
                $calc['da'],
                $calc['hra'],
                $calc['other_allowances'],
                $calc['ot_amount'],
                $calc['gross_earnings'],
                $calc['pf_employee'],
                $calc['esi_employee'],
                $calc['pt'],
                $calc['advance_deduction'],
                $calc['total_deductions'],
                $calc['net_pay'],
                $calc['pf_employer'],
                $calc['esi_employer'],
                $calc['employer_contribution'],
                $calc['ctc']
            ];
        }
        fputcsv($output, $data);
    }
    
    // Totals row
    fputcsv($output, [
        '', '', 'TOTAL', '', '', $totals['present'],
        $totals['basic'], $totals['da'], $totals['hra'], '', '',
        $totals['gross'], $totals['pf_emp'], $totals['esi_emp'], $totals['pt'],
        $totals['adv'], $totals['deductions'], $totals['net'],
        $totals['pf_employer'], $totals['esi_employer'], $totals['employer_contrib'], $totals['ctc']
    ]);
    
    fclose($output);
    exit;
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cash-stack me-2"></i>Wage Register</h5>
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
<!-- Wage Register Grid -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-secondary ms-2">Days: <?php echo $daysInMonth; ?></span>
                    <span class="badge bg-primary ms-2"><?php echo $totals['employees']; ?> Employees</span>
                    <?php if ($payrollPeriod && is_array($payrollPeriod) && isset($payrollPeriod['status'])): ?>
                    <span class="badge bg-success ms-2"><?php echo $payrollPeriod['status']; ?></span>
                    <?php elseif ($payrollPeriod === true): ?>
                    <span class="badge bg-success ms-2">Processed</span>
                    <?php else: ?>
                    <span class="badge bg-warning ms-2">Preview</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($payrollData)): ?>
                    <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&export=1" class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i>Export
                    </a>
                    <?php endif; ?>
                    <a href="index.php?page=payroll/process" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($payrollData) || $totals['employees'] == 0): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1"></i>
                    <p class="mt-2">No employees found for this unit with salary structure.</p>
                    <p class="small">Make sure employees have salary structures defined in employee_salary_structures table.</p>
                </div>
                <?php else: ?>
                
                <?php if (!$payrollPeriod): ?>
                <!-- Preview Mode -->
                <div class="alert alert-warning m-3 d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Preview Mode:</strong> Review the wage register below before processing.
                    </div>
                    <form method="POST">
                        <input type="hidden" name="client_id" value="<?php echo $selectedClient; ?>">
                        <input type="hidden" name="unit_id" value="<?php echo $selectedUnit; ?>">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                        <button type="submit" name="process_payroll" class="btn btn-success" onclick="return confirm('Process payroll for this period?')">
                            <i class="bi bi-check-lg me-1"></i>Process Payroll
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <!-- Processed Mode -->
                <div class="alert alert-success m-3">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Processed:</strong> Payroll has been processed. 
                    Net Pay: <strong>₹<?php echo number_format($totals['net']); ?></strong> | 
                    CTC: <strong>₹<?php echo number_format($totals['ctc']); ?></strong>
                </div>
                <?php endif; ?>
                
                <div class="table-responsive" style="max-height: 70vh;">
                    <table class="table table-bordered table-hover mb-0" style="font-size: 11px;">
                        <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                            <tr>
                                <th rowspan="2" style="width: 30px;">#</th>
                                <th rowspan="2" style="width: 60px;">Emp Code</th>
                                <th rowspan="2" style="width: 140px;">Employee Name</th>
                                <th rowspan="2" style="width: 80px;">Category</th>
                                <th rowspan="2" style="width: 40px;" class="text-center bg-secondary">Days</th>
                                <th colspan="6" class="text-center bg-success">EARNINGS</th>
                                <th colspan="5" class="text-center bg-danger">DEDUCTIONS</th>
                                <th rowspan="2" class="text-center bg-primary text-white">Net Pay</th>
                                <th colspan="3" class="text-center bg-info">EMPLOYER CONTRIBUTION</th>
                                <th rowspan="2" class="text-center bg-warning text-dark">CTC</th>
                            </tr>
                            <tr>
                                <!-- Earnings -->
                                <th class="text-end bg-success bg-opacity-25">Basic</th>
                                <th class="text-end bg-success bg-opacity-25">DA</th>
                                <th class="text-end bg-success bg-opacity-25">HRA</th>
                                <th class="text-end bg-success bg-opacity-25">Other</th>
                                <th class="text-end bg-success bg-opacity-25">OT</th>
                                <th class="text-end bg-success bg-opacity-25">Gross</th>
                                <!-- Deductions -->
                                <th class="text-end bg-danger bg-opacity-25">PF</th>
                                <th class="text-end bg-danger bg-opacity-25">ESI</th>
                                <th class="text-end bg-danger bg-opacity-25">PT</th>
                                <th class="text-end bg-danger bg-opacity-25">Adv</th>
                                <th class="text-end bg-danger bg-opacity-25">Total</th>
                                <!-- Employer -->
                                <th class="text-end bg-info bg-opacity-25">PF</th>
                                <th class="text-end bg-info bg-opacity-25">ESI</th>
                                <th class="text-end bg-info bg-opacity-25">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sr = 1;
                            foreach ($payrollData as $row): 
                                if ($payrollPeriod) {
                                    // Processed data
                                    $paidDays = $row['paid_days'];
                                    $basic = $row['basic'];
                                    $da = $row['da'];
                                    $hra = $row['hra'];
                                    $other = $row['other_allowance'];
                                    $ot = $row['overtime_amount'];
                                    $gross = $row['gross_earnings'];
                                    $pfEmp = $row['pf_employee'];
                                    $esiEmp = $row['esi_employee'];
                                    $pt = $row['professional_tax'];
                                    $adv = $row['salary_advance'];
                                    $totalDed = $row['total_deductions'];
                                    $net = $row['net_pay'];
                                    $pfEmpr = $row['pf_employer'];
                                    $esiEmpr = $row['esi_employer'];
                                    $emprContrib = $row['total_employer_contribution'];
                                    $ctc = $row['ctc'];
                                    $empCode = $row['employee_code'];
                                    $empName = $row['full_name'];
                                    $category = $row['worker_category'];
                                    $hasSalary = true; // Already processed means has salary
                                } else {
                                    // Preview data - show ALL employees even with 0 salary
                                    $calc = calculatePayroll($row, $daysInMonth);
                                    $paidDays = $calc['paid_days'];
                                    $basic = $calc['basic'];
                                    $da = $calc['da'];
                                    $hra = $calc['hra'];
                                    $other = $calc['other_allowances'];
                                    $ot = $calc['ot_amount'];
                                    $gross = $calc['gross_earnings'];
                                    $pfEmp = $calc['pf_employee'];
                                    $esiEmp = $calc['esi_employee'];
                                    $pt = $calc['pt'];
                                    $adv = $calc['advance_deduction'];
                                    $totalDed = $calc['total_deductions'];
                                    $net = $calc['net_pay'];
                                    $pfEmpr = $calc['pf_employer'];
                                    $esiEmpr = $calc['esi_employer'];
                                    $emprContrib = $calc['employer_contribution'];
                                    $ctc = $calc['ctc'];
                                    $empCode = $row['employee_code'];
                                    $empName = $row['full_name'];
                                    $category = $row['worker_category'];
                                    $hasSalary = !empty($row['basic_wage']) || !empty($row['gross_salary']);
                                }
                            ?>
                            <tr class="<?php echo !$hasSalary && !$payrollPeriod ? 'table-warning' : ''; ?>">
                                <td class="text-center"><?php echo $sr++; ?></td>
                                <td>
                                    <a href="index.php?page=employee/add&id=<?php echo $row['emp_uuid'] ?? $row['id'] ?? ''; ?>" class="text-decoration-none">
                                        <code><?php echo sanitize($empCode); ?></code>
                                        <?php if (!$hasSalary && !$payrollPeriod): ?>
                                        <i class="bi bi-exclamation-triangle text-warning" title="No Salary Structure"></i>
                                        <?php endif; ?>
                                    </a>
                                </td>
                                <td><?php echo sanitize($empName); ?></td>
                                <td><small><?php echo sanitize($category); ?></small></td>
                                <td class="text-center"><?php echo $paidDays; ?></td>
                                <!-- Earnings -->
                                <td class="text-end"><?php echo $basic > 0 ? number_format($basic) : '-'; ?></td>
                                <td class="text-end"><?php echo $da > 0 ? number_format($da) : '-'; ?></td>
                                <td class="text-end"><?php echo $hra > 0 ? number_format($hra) : '-'; ?></td>
                                <td class="text-end"><?php echo $other > 0 ? number_format($other) : '-'; ?></td>
                                <td class="text-end"><?php echo $ot > 0 ? number_format($ot) : '-'; ?></td>
                                <td class="text-end fw-bold text-success"><?php echo number_format($gross); ?></td>
                                <!-- Deductions -->
                                <td class="text-end text-danger"><?php echo $pfEmp > 0 ? number_format($pfEmp) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $esiEmp > 0 ? number_format($esiEmp) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $pt > 0 ? number_format($pt) : '-'; ?></td>
                                <td class="text-end text-danger"><?php echo $adv > 0 ? number_format($adv) : '-'; ?></td>
                                <td class="text-end text-danger fw-bold"><?php echo number_format($totalDed); ?></td>
                                <!-- Net Pay -->
                                <td class="text-end fw-bold text-primary" style="background-color: #e8f4f8;"><?php echo number_format($net); ?></td>
                                <!-- Employer Contribution -->
                                <td class="text-end text-info"><?php echo $pfEmpr > 0 ? number_format($pfEmpr) : '-'; ?></td>
                                <td class="text-end text-info"><?php echo $esiEmpr > 0 ? number_format($esiEmpr) : '-'; ?></td>
                                <td class="text-end text-info fw-bold"><?php echo number_format($emprContrib); ?></td>
                                <!-- CTC -->
                                <td class="text-end fw-bold text-warning" style="background-color: #fff3cd;"><?php echo number_format($ctc); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end">TOTAL (<?php echo $totals['employees']; ?> Employees)</td>
                                <td class="text-center"><?php echo number_format($totals['present'], 0); ?></td>
                                <!-- Earnings -->
                                <td class="text-end"><?php echo number_format($totals['basic']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['da']); ?></td>
                                <td class="text-end"><?php echo number_format($totals['hra']); ?></td>
                                <td class="text-end"></td>
                                <td class="text-end"></td>
                                <td class="text-end text-success"><?php echo number_format($totals['gross']); ?></td>
                                <!-- Deductions -->
                                <td class="text-end text-danger"><?php echo number_format($totals['pf_emp']); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['esi_emp']); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['pt']); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['adv']); ?></td>
                                <td class="text-end text-danger"><?php echo number_format($totals['deductions']); ?></td>
                                <!-- Net Pay -->
                                <td class="text-end text-primary" style="background-color: #cce5ff;"><?php echo number_format($totals['net']); ?></td>
                                <!-- Employer -->
                                <td class="text-end text-info"><?php echo number_format($totals['pf_employer']); ?></td>
                                <td class="text-end text-info"><?php echo number_format($totals['esi_employer']); ?></td>
                                <td class="text-end text-info"><?php echo number_format($totals['employer_contrib']); ?></td>
                                <!-- CTC -->
                                <td class="text-end text-warning" style="background-color: #ffeeba;"><?php echo number_format($totals['ctc']); ?></td>
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
</script>
JS;
?>
