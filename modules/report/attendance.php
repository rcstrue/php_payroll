<?php
/**
 * RCS HRMS Pro - Attendance Reports
 * Client/Unit wise reports with previous month default
 */

$pageTitle = 'Attendance Reports';

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
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';

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

// Get report data
$reportData = [];
$totals = ['employees' => 0, 'present' => 0, 'extra' => 0, 'ot_hours' => 0, 'wo' => 0, 'net_pay' => 0];

if ($selectedUnit && isset($_GET['load'])) {
    try {
        if ($reportType === 'summary') {
            // Unit-wise summary
            $stmt = $db->prepare("
                SELECT 
                    COALESCE(u.name, e.unit_name) as unit_name,
                    COUNT(DISTINCT e.employee_code) as total_employees,
                    SUM(COALESCE(att.total_present, 0)) as total_present,
                    SUM(COALESCE(att.total_extra, 0)) as total_extra,
                    SUM(COALESCE(att.overtime_hours, 0)) as total_ot_hours,
                    SUM(COALESCE(att.total_wo, 0)) as total_wo,
                    COUNT(CASE WHEN att.total_present IS NOT NULL THEN 1 END) as marked_employees
                FROM employees e
                LEFT JOIN units u ON e.unit_id = u.id
                LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                    AND att.unit_id = ? AND att.month = ? AND att.year = ?
                WHERE e.unit_id = ? AND e.status = 'approved'
                GROUP BY u.name, e.unit_name
            ");
            $stmt->execute([$selectedUnit, $selectedMonth, $selectedYear, $selectedUnit]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } elseif ($reportType === 'detailed') {
            // Detailed employee-wise report
            $stmt = $db->prepare("
                SELECT 
                    e.employee_code,
                    e.full_name,
                    e.designation,
                    e.worker_category,
                    COALESCE(c.name, e.client_name) as client_name,
                    COALESCE(u.name, e.unit_name) as unit_name,
                    ess.basic_wage,
                    ess.gross_salary,
                    COALESCE(att.total_present, 0) as total_present,
                    COALESCE(att.total_extra, 0) as total_extra,
                    COALESCE(att.overtime_hours, 0) as overtime_hours,
                    COALESCE(att.total_wo, 0) as total_wo,
                    att.source
                FROM employees e
                LEFT JOIN clients c ON e.client_id = c.id
                LEFT JOIN units u ON e.unit_id = u.id
                LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
                LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                    AND att.unit_id = ? AND att.month = ? AND att.year = ?
                WHERE e.unit_id = ? AND e.status = 'approved'
                ORDER BY e.employee_code
            ");
            $stmt->execute([$selectedUnit, $selectedMonth, $selectedYear, $selectedUnit]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            foreach ($reportData as $row) {
                $totals['employees']++;
                $totals['present'] += floatval($row['total_present']);
                $totals['extra'] += floatval($row['total_extra']);
                $totals['ot_hours'] += floatval($row['overtime_hours']);
                $totals['wo'] += intval($row['total_wo']);
            }
            
        } elseif ($reportType === 'low_attendance') {
            // Employees with low attendance (< 20 days)
            $stmt = $db->prepare("
                SELECT 
                    e.employee_code,
                    e.full_name,
                    e.designation,
                    COALESCE(c.name, e.client_name) as client_name,
                    COALESCE(u.name, e.unit_name) as unit_name,
                    COALESCE(att.total_present, 0) as total_present,
                    ? as total_days,
                    ROUND(COALESCE(att.total_present, 0) / ? * 100, 1) as attendance_percent
                FROM employees e
                LEFT JOIN clients c ON e.client_id = c.id
                LEFT JOIN units u ON e.unit_id = u.id
                LEFT JOIN attendance_summary att ON att.employee_id = e.employee_code 
                    AND att.unit_id = ? AND att.month = ? AND att.year = ?
                WHERE e.unit_id = ? AND e.status = 'approved'
                HAVING total_present < 20 OR total_present = 0
                ORDER BY total_present ASC
            ");
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
            $stmt->execute([$daysInMonth, $daysInMonth, $selectedUnit, $selectedMonth, $selectedYear, $selectedUnit]);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Report error: " . $e->getMessage());
    }
}

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel' && !empty($reportData)) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_report_' . $selectedMonth . '_' . $selectedYear . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // BOM for Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Attendance Report - ' . date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear))]);
    fputcsv($output, []);
    
    if (!empty($reportData)) {
        fputcsv($output, array_keys($reportData[0]));
        foreach ($reportData as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-bar-chart-line me-2"></i>Attendance Reports</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="report/attendance">
                    
                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type" id="reportType">
                            <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary</option>
                            <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                            <option value="low_attendance" <?php echo $reportType === 'low_attendance' ? 'selected' : ''; ?>>Low Attendance</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
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
                    
                    <div class="col-md-2">
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
                            <i class="bi bi-search me-1"></i>Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selectedUnit && isset($_GET['load'])): ?>
<!-- Report Results -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-secondary ms-2">Days: <?php echo $daysInMonth; ?></span>
                    <span class="badge bg-primary ms-2"><?php echo count($reportData); ?> Records</span>
                </div>
                <div>
                    <?php if (!empty($reportData)): ?>
                    <a href="<?php echo $_SERVER['REQUEST_URI']; ?>&export=excel" class="btn btn-success btn-sm">
                        <i class="bi bi-download me-1"></i>Export Excel
                    </a>
                    <?php endif; ?>
                    <a href="index.php?page=report/attendance" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reportData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-text fs-1"></i>
                    <p class="mt-2">No data found for the selected criteria.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <?php if ($reportType === 'summary'): ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Unit</th>
                                <th class="text-end">Total Employees</th>
                                <th class="text-end">Marked</th>
                                <th class="text-end">Present Days</th>
                                <th class="text-end">Extra Days</th>
                                <th class="text-end">OT Hours</th>
                                <th class="text-end">WO Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td class="text-end"><?php echo $row['total_employees']; ?></td>
                                <td class="text-end"><?php echo $row['marked_employees']; ?></td>
                                <td class="text-end text-success fw-bold"><?php echo number_format($row['total_present'], 1); ?></td>
                                <td class="text-end text-info"><?php echo number_format($row['total_extra'], 1); ?></td>
                                <td class="text-end text-warning"><?php echo number_format($row['total_ot_hours'], 1); ?></td>
                                <td class="text-end"><?php echo $row['total_wo']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif ($reportType === 'detailed'): ?>
                    <table class="table table-bordered table-hover mb-0" style="font-size: 12px;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="width: 80px;">Emp Code</th>
                                <th style="width: 150px;">Employee Name</th>
                                <th style="width: 100px;">Designation</th>
                                <th style="width: 80px;">Category</th>
                                <th style="width: 70px;" class="text-center bg-success text-white">Present</th>
                                <th style="width: 70px;" class="text-center bg-info text-white">Extra</th>
                                <th style="width: 70px;" class="text-center bg-warning text-dark">OT Hrs</th>
                                <th style="width: 70px;" class="text-center bg-secondary text-white">WO</th>
                                <th style="width: 60px;" class="text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sr = 1;
                            foreach ($reportData as $row): 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $sr++; ?></td>
                                <td><code><?php echo $row['employee_code']; ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['designation']); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo sanitize($row['worker_category']); ?></span></td>
                                <td class="text-center fw-bold <?php echo $row['total_present'] > 0 ? 'text-success' : 'text-muted'; ?>">
                                    <?php echo $row['total_present'] ?: '-'; ?>
                                </td>
                                <td class="text-center <?php echo $row['total_extra'] > 0 ? 'text-info' : 'text-muted'; ?>">
                                    <?php echo $row['total_extra'] ?: '-'; ?>
                                </td>
                                <td class="text-center <?php echo $row['overtime_hours'] > 0 ? 'text-warning' : 'text-muted'; ?>">
                                    <?php echo $row['overtime_hours'] ?: '-'; ?>
                                </td>
                                <td class="text-center"><?php echo $row['total_wo'] ?: '-'; ?></td>
                                <td class="text-center">
                                    <span class="badge <?php echo ($row['source'] ?? 'Manual') == 'Manual' ? 'bg-primary' : 'bg-success'; ?>" style="font-size: 10px;">
                                        <?php echo $row['source'] ?? 'Manual'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="5" class="text-end">TOTAL (<?php echo $totals['employees']; ?> Employees)</td>
                                <td class="text-center text-success"><?php echo number_format($totals['present'], 1); ?></td>
                                <td class="text-center text-info"><?php echo number_format($totals['extra'], 1); ?></td>
                                <td class="text-center text-warning"><?php echo number_format($totals['ot_hours'], 1); ?></td>
                                <td class="text-center"><?php echo $totals['wo']; ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <?php elseif ($reportType === 'low_attendance'): ?>
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Designation</th>
                                <th>Client</th>
                                <th class="text-end">Present Days</th>
                                <th class="text-end">Total Days</th>
                                <th class="text-end">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><code><?php echo $row['employee_code']; ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['designation']); ?></td>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td class="text-end">
                                    <span class="badge bg-danger"><?php echo $row['total_present']; ?></span>
                                </td>
                                <td class="text-end"><?php echo $row['total_days']; ?></td>
                                <td class="text-end">
                                    <span class="badge <?php echo $row['attendance_percent'] < 50 ? 'bg-danger' : 'bg-warning'; ?>">
                                        <?php echo $row['attendance_percent']; ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
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
