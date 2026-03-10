<?php
/**
 * RCS HRMS Pro - View Attendance Summary
 * Shows attendance_summary data like Add Attendance page
 */

$pageTitle = 'View Attendance';

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

// Get attendance summary data when unit is selected
$attendanceData = [];
$totals = ['employees' => 0, 'present' => 0, 'extra' => 0, 'ot_hours' => 0, 'wo' => 0];
if ($selectedUnit && isset($_GET['load'])) {
    try {
        // Get attendance summary with employee details
        $stmt = $db->prepare("
            SELECT e.employee_code, e.full_name, e.designation, e.worker_category,
                   COALESCE(c.name, e.client_name) as client_name_display,
                   COALESCE(u.name, e.unit_name) as unit_name_display,
                   ess.basic_wage, ess.gross_salary,
                   att.total_present, att.total_extra, att.overtime_hours, att.total_wo, att.source
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
        $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        foreach ($attendanceData as $row) {
            $totals['employees']++;
            $totals['present'] += floatval($row['total_present'] ?? 0);
            $totals['extra'] += floatval($row['total_extra'] ?? 0);
            $totals['ot_hours'] += floatval($row['overtime_hours'] ?? 0);
            $totals['wo'] += intval($row['total_wo'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Error fetching attendance: " . $e->getMessage());
    }
}

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-calendar-check me-2"></i>View Attendance</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="attendance/view">
                    
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
<!-- Attendance Summary Grid -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-secondary ms-2">Total Days: <?php echo $daysInMonth; ?></span>
                    <span class="badge bg-primary ms-2"><?php echo count($attendanceData); ?> Employees</span>
                </div>
                <div>
                    <?php if (!empty($attendanceData)): ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="exportAttendance()">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <?php endif; ?>
                    <a href="index.php?page=attendance/view" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($attendanceData)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1"></i>
                    <p class="mt-2">No employees found for this unit or no attendance data available.</p>
                    <a href="index.php?page=attendance/add&client_id=<?php echo $selectedClient; ?>&unit_id=<?php echo $selectedUnit; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&load=1" class="btn btn-primary btn-sm mt-2">
                        <i class="bi bi-plus me-1"></i>Add Attendance
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0" style="font-size: 13px;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th style="width: 100px;">Emp Code</th>
                                <th style="width: 180px;">Employee Name</th>
                                <th style="width: 120px;">Designation</th>
                                <th style="width: 100px;">Category</th>
                                <th style="width: 90px;" class="text-center bg-success text-white">Present<br><small>(Days)</small></th>
                                <th style="width: 90px;" class="text-center bg-info text-white">Extra<br><small>(Days)</small></th>
                                <th style="width: 90px;" class="text-center bg-warning text-dark">OT Hours<br><small>(Hrs)</small></th>
                                <th style="width: 90px;" class="text-center bg-secondary text-white">WO<br><small>(Days)</small></th>
                                <th style="width: 80px;" class="text-center">Source</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $sr = 1;
                            foreach ($attendanceData as $row): 
                            ?>
                            <tr>
                                <td class="text-center"><?php echo $sr++; ?></td>
                                <td><code><?php echo $row['employee_code']; ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['designation']); ?></td>
                                <td><span class="badge bg-light text-dark"><?php echo sanitize($row['worker_category']); ?></span></td>
                                <td class="text-center fw-bold <?php echo ($row['total_present'] ?? 0) > 0 ? 'text-success' : 'text-muted'; ?>">
                                    <?php echo $row['total_present'] ?? '-'; ?>
                                </td>
                                <td class="text-center <?php echo ($row['total_extra'] ?? 0) > 0 ? 'text-info' : 'text-muted'; ?>">
                                    <?php echo $row['total_extra'] ?? '-'; ?>
                                </td>
                                <td class="text-center <?php echo ($row['overtime_hours'] ?? 0) > 0 ? 'text-warning' : 'text-muted'; ?>">
                                    <?php echo $row['overtime_hours'] ?? '-'; ?>
                                </td>
                                <td class="text-center <?php echo ($row['total_wo'] ?? 0) > 0 ? 'text-secondary' : 'text-muted'; ?>">
                                    <?php echo $row['total_wo'] ?? '-'; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo ($row['source'] ?? 'Manual') == 'Manual' ? 'bg-primary' : 'bg-success'; ?>">
                                        <?php echo $row['source'] ?? 'Manual'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold">
                                <td colspan="5" class="text-end">TOTAL</td>
                                <td class="text-center text-success"><?php echo number_format($totals['present'], 1); ?></td>
                                <td class="text-center text-info"><?php echo number_format($totals['extra'], 1); ?></td>
                                <td class="text-center text-warning"><?php echo number_format($totals['ot_hours'], 1); ?></td>
                                <td class="text-center"><?php echo $totals['wo']; ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <small><i class="bi bi-info-circle me-1"></i>Showing attendance summary for <?php echo $totals['employees']; ?> employees</small>
                        </div>
                        <div>
                            <a href="index.php?page=attendance/add&client_id=<?php echo $selectedClient; ?>&unit_id=<?php echo $selectedUnit; ?>&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>&load=1" class="btn btn-primary btn-sm">
                                <i class="bi bi-pencil me-1"></i>Edit Attendance
                            </a>
                        </div>
                    </div>
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

// Export attendance
function exportAttendance() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'index.php?' + params.toString();
}
</script>
JS;
?>
