<?php
/**
 * RCS HRMS Pro - Attendance Reports
 */

$pageTitle = 'Attendance Reports';

// Get filter parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$clientName = isset($_GET['client_name']) ? sanitize($_GET['client_name']) : '';
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';

// Build query
$where = "a.month = :month AND a.year = :year";
$params = ['month' => $month, 'year' => $year];

if ($clientName) {
    $where .= " AND e.client_name = :client_name";
    $params['client_name'] = $clientName;
}

// Get data based on report type
if ($reportType === 'summary') {
    $sql = "SELECT 
                e.client_name,
                e.unit_name,
                COUNT(DISTINCT a.employee_code) as total_employees,
                SUM(a.days_present) as total_present,
                SUM(a.days_absent) as total_absent,
                SUM(a.overtime_hours) as total_ot_hours,
                SUM(a.days_present) / (COUNT(DISTINCT a.employee_code) * 30) * 100 as avg_attendance
            FROM attendance a
            JOIN employees e ON a.employee_code = e.employee_code
            WHERE {$where}
            GROUP BY e.client_name, e.unit_name
            ORDER BY e.client_name, e.unit_name";
} elseif ($reportType === 'detailed') {
    $sql = "SELECT 
                e.employee_code,
                e.full_name,
                e.designation,
                e.client_name,
                e.unit_name,
                a.days_present,
                a.days_absent,
                a.half_days,
                a.overtime_hours,
                a.ot_amount,
                a.remarks
            FROM attendance a
            JOIN employees e ON a.employee_code = e.employee_code
            WHERE {$where}
            ORDER BY e.client_name, e.unit_name, e.full_name";
} elseif ($reportType === 'absenteeism') {
    $sql = "SELECT 
                e.employee_code,
                e.full_name,
                e.client_name,
                a.days_absent,
                a.days_present,
                ROUND(a.days_absent / (a.days_present + a.days_absent) * 100, 1) as absence_rate
            FROM attendance a
            JOIN employees e ON a.employee_code = e.employee_code
            WHERE {$where} AND a.days_absent > 3
            ORDER BY a.days_absent DESC
            LIMIT 50";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$clients = $db->query("SELECT DISTINCT client_name FROM employees WHERE client_name IS NOT NULL ORDER BY client_name")->fetchAll(PDO::FETCH_ASSOC);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $month . '_' . $year . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Attendance Report - ' . date('F Y', mktime(0, 0, 0, $month, 1, $year))]);
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
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i>Attendance Reports</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="report/attendance">
                    
                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary</option>
                            <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                            <option value="absenteeism" <?php echo $reportType === 'absenteeism' ? 'selected' : ''; ?>>High Absenteeism</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year">
                            <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Client</label>
                        <select class="form-select" name="client_name">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['client_name']); ?>"
                                    <?php echo $clientName === $c['client_name'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['client_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-search me-1"></i>Generate
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportExcel()">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                </form>
                
                <!-- Report Data -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <?php if ($reportType === 'summary'): ?>
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Unit</th>
                                <th class="text-end">Employees</th>
                                <th class="text-end">Present Days</th>
                                <th class="text-end">Absent Days</th>
                                <th class="text-end">OT Hours</th>
                                <th class="text-end">Avg Attendance %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td class="text-end"><?php echo $row['total_employees']; ?></td>
                                <td class="text-end"><?php echo $row['total_present']; ?></td>
                                <td class="text-end"><?php echo $row['total_absent']; ?></td>
                                <td class="text-end"><?php echo number_format($row['total_ot_hours'], 1); ?></td>
                                <td class="text-end"><?php echo number_format($row['avg_attendance'], 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <?php elseif ($reportType === 'detailed'): ?>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Designation</th>
                                <th>Client</th>
                                <th>Unit</th>
                                <th class="text-end">Present</th>
                                <th class="text-end">Absent</th>
                                <th class="text-end">Half Days</th>
                                <th class="text-end">OT Hrs</th>
                                <th class="text-end">OT Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_code']); ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['designation']); ?></td>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td class="text-end"><?php echo $row['days_present']; ?></td>
                                <td class="text-end"><?php echo $row['days_absent']; ?></td>
                                <td class="text-end"><?php echo $row['half_days']; ?></td>
                                <td class="text-end"><?php echo number_format($row['overtime_hours'], 1); ?></td>
                                <td class="text-end"><?php echo formatCurrency($row['ot_amount']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <?php elseif ($reportType === 'absenteeism'): ?>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Client</th>
                                <th class="text-end">Absent Days</th>
                                <th class="text-end">Present Days</th>
                                <th class="text-end">Absence Rate %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_code']); ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td class="text-end"><span class="badge bg-danger"><?php echo $row['days_absent']; ?></span></td>
                                <td class="text-end"><?php echo $row['days_present']; ?></td>
                                <td class="text-end"><?php echo $row['absence_rate']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportExcel() {
    var params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = 'index.php?' + params.toString();
}
</script>
