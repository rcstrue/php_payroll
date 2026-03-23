<?php
/**
 * RCS HRMS Pro - Monthly Attendance Upload
 * Bulk upload monthly attendance summary with advance deductions
 */

$pageTitle = 'Upload Monthly Attendance';

// Ensure attendance_summary table exists with all columns
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `attendance_summary` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` varchar(50) NOT NULL,
        `unit_id` int(11) DEFAULT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `total_present` decimal(5,2) DEFAULT 0.00,
        `total_extra` decimal(5,2) DEFAULT 0.00,
        `overtime_hours` decimal(6,2) DEFAULT 0.00,
        `total_wo` int(3) DEFAULT 0,
        `adv1` decimal(10,2) DEFAULT 0.00,
        `adv2` decimal(10,2) DEFAULT 0.00,
        `office_adv` decimal(10,2) DEFAULT 0.00,
        `dress_adv` decimal(10,2) DEFAULT 0.00,
        `source` enum('Manual','Excel Upload') DEFAULT 'Manual',
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_emp_month_year` (`employee_id`, `month`, `year`),
        KEY `idx_unit_month_year` (`unit_id`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // Add advance columns if they don't exist
    $columns = $db->query("SHOW COLUMNS FROM attendance_summary LIKE 'adv1'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE attendance_summary 
                   ADD COLUMN `adv1` decimal(10,2) DEFAULT 0.00 AFTER total_wo,
                   ADD COLUMN `adv2` decimal(10,2) DEFAULT 0.00 AFTER adv1,
                   ADD COLUMN `office_adv` decimal(10,2) DEFAULT 0.00 AFTER adv2,
                   ADD COLUMN `dress_adv` decimal(10,2) DEFAULT 0.00 AFTER office_adv");
    }
} catch (Exception $e) {
    // Table creation failed
}

// Get clients for dropdown
$clients = [];
try {
    $stmt = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get selected filters
$selectedClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$selectedUnit = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;

// Get units based on selected client
$units = [];
if ($selectedClient) {
    try {
        $stmt = $db->prepare("SELECT id, name FROM units WHERE client_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$selectedClient]);
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_file'])) {
    $clientId = (int)$_POST['client_id'];
    $unitId = (int)$_POST['unit_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    $file = $_FILES['attendance_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            // Move uploaded file
            $fileName = 'monthly_attendance_' . $unitId . '_' . $month . '_' . $year . '_' . time() . '.' . $ext;
            $uploadDir = APP_ROOT . '/uploads/attendance/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                $imported = 0;
                $errors = [];
                $skipped = 0;
                
                try {
                    // Load SimpleXLSX
                    require_once APP_ROOT . '/includes/SimpleXLSX.php';
                    
                    if ($ext === 'csv') {
                        // Process CSV
                        $handle = fopen($filePath, 'r');
                        $headers = fgetcsv($handle); // Skip header row
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) < 2) continue;
                            
                            $empCode = trim($row[0]);
                            $totalPresent = isset($row[1]) ? (float)$row[1] : 0;
                            $totalExtra = isset($row[2]) ? (float)$row[2] : 0;
                            $otHours = isset($row[3]) ? (float)$row[3] : 0;
                            $totalWO = isset($row[4]) ? (int)$row[4] : 0;
                            $adv1 = isset($row[5]) ? (float)$row[5] : 0;
                            $adv2 = isset($row[6]) ? (float)$row[6] : 0;
                            $officeAdv = isset($row[7]) ? (float)$row[7] : 0;
                            $dressAdv = isset($row[8]) ? (float)$row[8] : 0;
                            
                            // Get employee ID
                            $empStmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND unit_id = ?");
                            $empStmt->execute([$empCode, $unitId]);
                            $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($emp) {
                                // Insert or update attendance summary
                                $insertStmt = $db->prepare(
                                    "INSERT INTO attendance_summary 
                                    (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, adv1, adv2, office_adv, dress_adv, source)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Excel Upload')
                                    ON DUPLICATE KEY UPDATE 
                                        total_present = VALUES(total_present),
                                        total_extra = VALUES(total_extra),
                                        overtime_hours = VALUES(overtime_hours),
                                        total_wo = VALUES(total_wo),
                                        adv1 = VALUES(adv1),
                                        adv2 = VALUES(adv2),
                                        office_adv = VALUES(office_adv),
                                        dress_adv = VALUES(dress_adv),
                                        source = 'Excel Upload'"
                                );
                                $insertStmt->execute([$emp['id'], $unitId, $month, $year, $totalPresent, $totalExtra, $otHours, $totalWO, $adv1, $adv2, $officeAdv, $dressAdv]);
                                $imported++;
                            } else {
                                $skipped++;
                            }
                        }
                        fclose($handle);
                    } else {
                        // Process Excel
                        if ($xlsx = SimpleXLSX::parse($filePath)) {
                            $rows = $xlsx->rows();
                            array_shift($rows); // Skip header row
                            
                            foreach ($rows as $row) {
                                if (count($row) < 2) continue;
                                
                                $empCode = trim($row[0]);
                                $totalPresent = isset($row[1]) ? (float)$row[1] : 0;
                                $totalExtra = isset($row[2]) ? (float)$row[2] : 0;
                                $otHours = isset($row[3]) ? (float)$row[3] : 0;
                                $totalWO = isset($row[4]) ? (int)$row[4] : 0;
                                $adv1 = isset($row[5]) ? (float)$row[5] : 0;
                                $adv2 = isset($row[6]) ? (float)$row[6] : 0;
                                $officeAdv = isset($row[7]) ? (float)$row[7] : 0;
                                $dressAdv = isset($row[8]) ? (float)$row[8] : 0;
                                
                                // Get employee ID
                                $empStmt = $db->prepare("SELECT id FROM employees WHERE employee_code = ? AND unit_id = ?");
                                $empStmt->execute([$empCode, $unitId]);
                                $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($emp) {
                                    // Insert or update attendance summary
                                    $insertStmt = $db->prepare(
                                        "INSERT INTO attendance_summary 
                                        (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, adv1, adv2, office_adv, dress_adv, source)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Excel Upload')
                                        ON DUPLICATE KEY UPDATE 
                                            total_present = VALUES(total_present),
                                            total_extra = VALUES(total_extra),
                                            overtime_hours = VALUES(overtime_hours),
                                            total_wo = VALUES(total_wo),
                                            adv1 = VALUES(adv1),
                                            adv2 = VALUES(adv2),
                                            office_adv = VALUES(office_adv),
                                            dress_adv = VALUES(dress_adv),
                                            source = 'Excel Upload'"
                                    );
                                    $insertStmt->execute([$emp['id'], $unitId, $month, $year, $totalPresent, $totalExtra, $otHours, $totalWO, $adv1, $adv2, $officeAdv, $dressAdv]);
                                    $imported++;
                                } else {
                                    $skipped++;
                                }
                            }
                        }
                    }
                    
                    $message = "Monthly attendance uploaded successfully! Imported: {$imported} employees.";
                    if ($skipped > 0) {
                        $message .= " Skipped: {$skipped} (employee not found in unit)";
                    }
                    setFlash('success', $message);
                    
                } catch (Exception $e) {
                    setFlash('error', 'Error processing file: ' . $e->getMessage());
                }
            } else {
                setFlash('error', 'Failed to upload file');
            }
        } else {
            setFlash('error', 'Invalid file type. Please upload Excel (.xlsx, .xls) or CSV file');
        }
    } else {
        setFlash('error', 'File upload error');
    }
    
    redirect("index.php?page=attendance/upload&client_id=$clientId&unit_id=$unitId");
}

// Get recent uploads
$recentUploads = [];
try {
    $stmt = $db->query(
        "SELECT s.unit_id, s.month, s.year, s.source, s.updated_at,
                COUNT(DISTINCT s.employee_id) as employee_count,
                SUM(s.total_present) as total_present,
                SUM(s.adv1) as total_adv1,
                SUM(s.adv2) as total_adv2,
                SUM(s.office_adv) as total_office_adv,
                SUM(s.dress_adv) as total_dress_adv,
                u.name as unit_name, c.name as client_name
         FROM attendance_summary s
         LEFT JOIN units u ON s.unit_id = u.id
         LEFT JOIN clients c ON u.client_id = c.id
         WHERE s.source = 'Excel Upload'
         GROUP BY s.unit_id, s.month, s.year
         ORDER BY s.updated_at DESC LIMIT 10"
    );
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Monthly Attendance</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Monthly Attendance Upload:</strong> Upload employee attendance summary for the entire month at once. 
                    This includes attendance data and advance deductions.
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="uploadForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label required">Client</label>
                            <select class="form-select" name="client_id" id="clientSelect" required onchange="loadUnits()">
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo $selectedClient == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label required">Unit</label>
                            <select class="form-select" name="unit_id" id="unitSelect" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?php echo $u['id']; ?>" <?php echo $selectedUnit == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($u['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label required">Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
                                $prevMonth = date('n') - 1;
                                $prevYear = date('Y');
                                if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
                                for ($m = 1; $m <= 12; $m++):
                                    $selected = $m == $prevMonth ? 'selected' : '';
                                ?>
                                <option value="<?php echo $m; ?>" <?php echo $selected; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label required">Year</label>
                            <select class="form-select" name="year" required>
                                <?php 
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 2; $y--):
                                    $selected = $y == $prevYear ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selected; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label required">Attendance File</label>
                            <input type="file" class="form-control" name="attendance_file" 
                                   accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                Upload Excel (.xlsx, .xls) or CSV file. <a href="#" onclick="downloadTemplate()"><i class="bi bi-download me-1"></i>Download Excel Template</a>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i>Upload Monthly Attendance
                            </button>
                            <a href="index.php?page=attendance/add" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-pencil me-1"></i>Manual Entry
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel Format</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Monthly attendance file columns:</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Emp Code</th>
                                <th>Present Days</th>
                                <th>Extra Days</th>
                                <th>OT Hours</th>
                                <th>WO Days</th>
                                <th>Adv 1</th>
                                <th>Adv 2</th>
                                <th>Office Adv</th>
                                <th>Dress Adv</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>EMP001</td>
                                <td>26</td>
                                <td>2</td>
                                <td>8</td>
                                <td>4</td>
                                <td>500</td>
                                <td>0</td>
                                <td>1000</td>
                                <td>200</td>
                            </tr>
                            <tr>
                                <td>EMP002</td>
                                <td>25</td>
                                <td>0</td>
                                <td>4</td>
                                <td>4</td>
                                <td>0</td>
                                <td>1000</td>
                                <td>0</td>
                                <td>0</td>
                            </tr>
                            <tr>
                                <td>EMP003</td>
                                <td>24.5</td>
                                <td>1</td>
                                <td>0</td>
                                <td>4</td>
                                <td>500</td>
                                <td>500</td>
                                <td>500</td>
                                <td>300</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <h6 class="mt-3">Column Details:</h6>
                <ul class="list-unstyled small text-muted">
                    <li><strong>Emp Code</strong> - Employee Code (must match system)</li>
                    <li><strong>Present Days</strong> - Total present days (can be decimal like 24.5)</li>
                    <li><strong>Extra Days</strong> - Extra/Overtime days worked</li>
                    <li><strong>OT Hours</strong> - Overtime hours</li>
                    <li><strong>WO Days</strong> - Weekly Off days</li>
                    <li><strong>Adv 1</strong> - Advance 1 deduction amount</li>
                    <li><strong>Adv 2</strong> - Advance 2 deduction amount</li>
                    <li><strong>Office Adv</strong> - Office advance deduction</li>
                    <li><strong>Dress Adv</strong> - Dress advance deduction</li>
                </ul>
                
                <div class="d-grid gap-2 mt-3">
                    <button type="button" class="btn btn-success" onclick="downloadTemplate()">
                        <i class="bi bi-file-earmark-excel me-1"></i>Download Excel Template
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Important Notes</h6>
            </div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Employees must already exist in the selected unit</li>
                    <li>Uploading again for same month will overwrite existing data</li>
                    <li>Present days can include half days (e.g., 24.5)</li>
                    <li>Advance amounts will be deducted from salary</li>
                    <li>Leave advance columns blank or 0 if not applicable</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Recent Uploads -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Recent Uploads</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Unit</th>
                                <th>Month/Year</th>
                                <th>Employees</th>
                                <th>Present Days</th>
                                <th>Total Advances</th>
                                <th>Uploaded On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentUploads)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No uploads yet</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentUploads as $upload): 
                                $totalAdv = ($upload['total_adv1'] ?? 0) + ($upload['total_adv2'] ?? 0) + 
                                            ($upload['total_office_adv'] ?? 0) + ($upload['total_dress_adv'] ?? 0);
                            ?>
                            <tr>
                                <td><?php echo sanitize($upload['client_name'] ?? '-'); ?></td>
                                <td><?php echo sanitize($upload['unit_name'] ?? '-'); ?></td>
                                <td><?php echo date('F Y', mktime(0, 0, 0, $upload['month'], 1, $upload['year'])); ?></td>
                                <td><?php echo number_format($upload['employee_count']); ?></td>
                                <td><?php echo number_format($upload['total_present'], 1); ?> days</td>
                                <td>₹<?php echo number_format($totalAdv, 2); ?></td>
                                <td><?php echo formatDate($upload['updated_at'], 'd-m-Y H:i'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadUnits() {
    const clientId = document.getElementById('clientSelect').value;
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
}

function downloadTemplate() {
    // Create CSV content with all columns
    let csv = 'Emp Code,Present Days,Extra Days,OT Hours,WO Days,Adv 1,Adv 2,Office Adv,Dress Adv\n';
    csv += 'EMP001,26,2,8,4,500,0,1000,200\n';
    csv += 'EMP002,25,0,4,4,0,1000,0,0\n';
    csv += 'EMP003,24.5,1,0,4,500,500,500,300\n';
    
    // Create download
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'monthly_attendance_template.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Load units on page load if client is selected
document.addEventListener('DOMContentLoaded', function() {
    const clientId = document.getElementById('clientSelect').value;
    if (clientId) {
        loadUnits();
    }
});
</script>
