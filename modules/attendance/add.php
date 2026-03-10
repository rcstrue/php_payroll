<?php
/**
 * RCS HRMS Pro - Add Attendance (Manual Entry)
 * Manual attendance entry like Excel sheet
 */

$pageTitle = 'Add Attendance';

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

// Ensure attendance_summary table exists
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `attendance_summary` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` varchar(36) NOT NULL,
        `unit_id` int(11) DEFAULT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `total_present` decimal(5,2) DEFAULT 0.00,
        `total_extra` decimal(5,2) DEFAULT 0.00,
        `overtime_hours` decimal(6,2) DEFAULT 0.00,
        `total_wo` int(3) DEFAULT 0,
        `source` enum('Manual','Excel Upload') DEFAULT 'Manual',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_emp_month_year` (`employee_id`, `month`, `year`),
        KEY `idx_unit_month_year` (`unit_id`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // Table creation failed
}

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

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $debugOutput = [];
    $debugOutput[] = "=== ATTENDANCE SAVE DEBUG ===";
    $debugOutput[] = "POST method: " . $_SERVER['REQUEST_METHOD'];
    $debugOutput[] = "save_attendance set: " . (isset($_POST['save_attendance']) ? 'YES' : 'NO');
    $debugOutput[] = "POST keys: " . implode(', ', array_keys($_POST));
    
    $unitId = (int)$_POST['unit_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    $employeeIds = $_POST['employee_id'] ?? [];
    
    $debugOutput[] = "Unit ID: $unitId, Month: $month, Year: $year";
    $debugOutput[] = "Employee IDs count: " . count($employeeIds);
    
    // Get client_id from unit
    $stmt = $db->prepare("SELECT client_id FROM units WHERE id = ?");
    $stmt->execute([$unitId]);
    $unitData = $stmt->fetch(PDO::FETCH_ASSOC);
    $clientId = $unitData ? $unitData['client_id'] : 0;
    
    $savedCount = 0;
    $errors = [];
    
    try {
        foreach ($employeeIds as $empId) {
            $totalPresent = isset($_POST['total_present'][$empId]) ? (float)$_POST['total_present'][$empId] : 0;
            $totalExtra = isset($_POST['total_extra'][$empId]) ? (float)$_POST['total_extra'][$empId] : 0;
            $otHours = isset($_POST['overtime_hours'][$empId]) ? (float)$_POST['overtime_hours'][$empId] : 0;
            $totalWO = isset($_POST['total_wo'][$empId]) ? (int)$_POST['total_wo'][$empId] : 0;
            
            $debugOutput[] = "Employee $empId: Present=$totalPresent, Extra=$totalExtra, OT=$otHours, WO=$totalWO";
            
            // Insert or update using ON DUPLICATE KEY
            $stmt = $db->prepare("
                INSERT INTO attendance_summary 
                (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Manual')
                ON DUPLICATE KEY UPDATE 
                    total_present = VALUES(total_present),
                    total_extra = VALUES(total_extra),
                    overtime_hours = VALUES(overtime_hours),
                    total_wo = VALUES(total_wo),
                    source = 'Manual',
                    updated_at = CURRENT_TIMESTAMP
            ");
            $result = $stmt->execute([$empId, $unitId, $month, $year, $totalPresent, $totalExtra, $otHours, $totalWO]);
            $debugOutput[] = "Insert result for $empId: " . ($result ? 'SUCCESS' : 'FAILED');
            if ($result) {
                $savedCount++;
            }
        }
        
        $debugOutput[] = "Total saved: $savedCount";
        
        // Show debug and redirect
        $_SESSION['attendance_debug'] = $debugOutput;
        setFlash('success', "Attendance saved! {$savedCount} employees updated.");
        
        // Redirect to same page with filters
        header("Location: index.php?page=attendance/add&client_id={$clientId}&unit_id={$unitId}&month={$month}&year={$year}&load=1");
        exit;
        
    } catch (Exception $e) {
        $debugOutput[] = "ERROR: " . $e->getMessage();
        $_SESSION['attendance_debug'] = $debugOutput;
        setFlash('error', 'Error saving attendance: ' . $e->getMessage());
    }
}

// Show debug from previous save
$debugInfo = isset($_SESSION['attendance_debug']) ? $_SESSION['attendance_debug'] : [];
unset($_SESSION['attendance_debug']);

// Get employees and their attendance when unit is selected
$employees = [];
if ($selectedUnit && isset($_GET['load'])) {
    // Get employees for this unit
    $stmt = $db->prepare("
        SELECT e.id, e.employee_code, e.full_name, e.designation, e.worker_category,
               ess.basic_wage, ess.gross_salary
        FROM employees e
        LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
        WHERE e.unit_id = ? AND e.status = 'approved'
        ORDER BY e.employee_code
    ");
    $stmt->execute([$selectedUnit]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get existing attendance summary if any
    foreach ($employees as &$emp) {
        try {
            $stmt = $db->prepare("
                SELECT total_present, total_extra, overtime_hours, total_wo
                FROM attendance_summary
                WHERE employee_id = ? AND month = ? AND year = ?
            ");
            $stmt->execute([$emp['id'], $selectedMonth, $selectedYear]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $emp['total_present'] = $existing['total_present'];
                $emp['total_extra'] = $existing['total_extra'];
                $emp['overtime_hours'] = $existing['overtime_hours'];
                $emp['total_wo'] = $existing['total_wo'];
            } else {
                $emp['total_present'] = '';
                $emp['total_extra'] = '';
                $emp['overtime_hours'] = '';
                $emp['total_wo'] = '';
            }
        } catch (Exception $e) {
            $emp['total_present'] = '';
            $emp['total_extra'] = '';
            $emp['overtime_hours'] = '';
            $emp['total_wo'] = '';
        }
    }
    unset($emp);
}

// Days in selected month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
?>

<?php if (!empty($debugInfo)): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <strong>Debug Information</strong>
            </div>
            <div class="card-body">
                <pre style="font-size: 11px; margin: 0;"><?php echo implode("\n", $debugInfo); ?></pre>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-table me-2"></i>Add Attendance (Manual Entry)</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="attendance/add">
                    
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
<!-- Attendance Entry Grid -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-info"><?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></span>
                    <span class="badge bg-secondary ms-2">Total Days: <?php echo $daysInMonth; ?></span>
                    <span class="badge bg-primary ms-2"><?php echo count($employees); ?> Employees</span>
                </div>
                <div>
                    <a href="index.php?page=attendance/add" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg me-1"></i>Clear
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($employees)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1"></i>
                    <p class="mt-2">No employees found for this unit.</p>
                </div>
                <?php else: ?>
                <form method="POST" id="attendanceForm">
                    <input type="hidden" name="unit_id" value="<?php echo $selectedUnit; ?>">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sr = 1;
                                foreach ($employees as $emp): 
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $sr++; ?></td>
                                    <td>
                                        <input type="hidden" name="employee_id[]" value="<?php echo $emp['id']; ?>">
                                        <code><?php echo $emp['employee_code']; ?></code>
                                    </td>
                                    <td><?php echo sanitize($emp['full_name']); ?></td>
                                    <td><?php echo sanitize($emp['designation']); ?></td>
                                    <td><span class="badge bg-light text-dark"><?php echo sanitize($emp['worker_category']); ?></span></td>
                                    <td>
                                        <input type="number" name="total_present[<?php echo $emp['id']; ?>]" 
                                               value="<?php echo $emp['total_present']; ?>" 
                                               class="form-control form-control-sm text-center" 
                                               min="0" max="31" step="0.5">
                                    </td>
                                    <td>
                                        <input type="number" name="total_extra[<?php echo $emp['id']; ?>]" 
                                               value="<?php echo $emp['total_extra']; ?>" 
                                               class="form-control form-control-sm text-center" 
                                               min="0" max="31" step="0.5">
                                    </td>
                                    <td>
                                        <input type="number" name="overtime_hours[<?php echo $emp['id']; ?>]" 
                                               value="<?php echo $emp['overtime_hours']; ?>" 
                                               class="form-control form-control-sm text-center" 
                                               min="0" max="300" step="0.5">
                                    </td>
                                    <td>
                                        <input type="number" name="total_wo[<?php echo $emp['id']; ?>]" 
                                               value="<?php echo $emp['total_wo']; ?>" 
                                               class="form-control form-control-sm text-center" 
                                               min="0" max="8">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <small><i class="bi bi-info-circle me-1"></i>Present: Total present days | Extra: Additional working days | OT: Overtime hours | WO: Weekly off days</small>
                            </div>
                            <div>
                                <button type="submit" name="save_attendance" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>Save Attendance
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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
