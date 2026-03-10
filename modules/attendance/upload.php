<?php
/**
 * RCS HRMS Pro - Upload Attendance & Advances
 * Combined upload for both attendance and advances in one go
 */

$pageTitle = 'Upload Attendance & Advances';

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

// Ensure tables exist
try {
    // Attendance summary table
    $db->exec("CREATE TABLE IF NOT EXISTS `attendance_summary` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) NOT NULL,
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

    // Employee advances table
    $db->exec("CREATE TABLE IF NOT EXISTS `employee_advances` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `employee_id` int(11) NOT NULL,
        `unit_id` int(11) DEFAULT NULL,
        `month` int(2) NOT NULL,
        `year` int(4) NOT NULL,
        `adv1` decimal(10,2) DEFAULT 0.00,
        `adv2` decimal(10,2) DEFAULT 0.00,
        `office_advance` decimal(10,2) DEFAULT 0.00,
        `dress_advance` decimal(10,2) DEFAULT 0.00,
        `remarks` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_emp_month_year` (`employee_id`, `month`, `year`),
        KEY `idx_unit_month_year` (`unit_id`, `month`, `year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("Table creation failed: " . $e->getMessage());
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

// Handle file upload
$uploadResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_upload']) && isset($_FILES['data_file'])) {
    $unitId = (int)$_POST['unit_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    $file = $_FILES['data_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            $uploadDir = dirname(__FILE__) . '/../../uploads/attendance/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = 'upload_' . $unitId . '_' . $month . '_' . $year . '_' . time() . '.' . $ext;
            $filePath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Process the file
                $attendanceSaved = 0;
                $advancesSaved = 0;
                $errors = [];
                
                try {
                    if ($ext === 'csv') {
                        // Process CSV
                        $handle = fopen($filePath, 'r');
                        $headers = fgetcsv($handle); // First row - headers
                        
                        // Find column indexes
                        $colMap = [];
                        foreach ($headers as $idx => $header) {
                            $header = trim(strtolower($header));
                            $colMap[$header] = $idx;
                        }
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            // Get employee code
                            $empCode = 0;
                            if (isset($colMap['emp_code'])) $empCode = (int)$row[$colMap['emp_code']];
                            elseif (isset($colMap['employee_code'])) $empCode = (int)$row[$colMap['employee_code']];
                            elseif (isset($colMap['code'])) $empCode = (int)$row[$colMap['code']];
                            
                            if (empty($empCode)) continue;
                            
                            // Attendance fields
                            $totalPresent = isset($colMap['present']) ? floatval($row[$colMap['present']]) : 0;
                            $totalExtra = isset($colMap['extra']) ? floatval($row[$colMap['extra']]) : 0;
                            $overtimeHours = isset($colMap['ot_hours']) ? floatval($row[$colMap['ot_hours']]) : 
                                             (isset($colMap['overtime']) ? floatval($row[$colMap['overtime']]) : 0);
                            $totalWo = isset($colMap['wo']) ? intval($row[$colMap['wo']]) : 0;
                            
                            // Advance fields
                            $adv1 = isset($colMap['adv1']) ? floatval($row[$colMap['adv1']]) : 0;
                            $adv2 = isset($colMap['adv2']) ? floatval($row[$colMap['adv2']]) : 0;
                            $officeAdvance = isset($colMap['office_adv']) ? floatval($row[$colMap['office_adv']]) : 
                                             (isset($colMap['office_advance']) ? floatval($row[$colMap['office_advance']]) : 0);
                            $dressAdvance = isset($colMap['dress_adv']) ? floatval($row[$colMap['dress_adv']]) : 
                                            (isset($colMap['dress_advance']) ? floatval($row[$colMap['dress_advance']]) : 0);
                            
                            // Save attendance
                            try {
                                $stmt = $db->prepare("
                                    INSERT INTO attendance_summary 
                                    (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, source)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Excel Upload')
                                    ON DUPLICATE KEY UPDATE 
                                        total_present = VALUES(total_present),
                                        total_extra = VALUES(total_extra),
                                        overtime_hours = VALUES(overtime_hours),
                                        total_wo = VALUES(total_wo),
                                        source = 'Excel Upload',
                                        updated_at = CURRENT_TIMESTAMP
                                ");
                                $stmt->execute([$empCode, $unitId, $month, $year, $totalPresent, $totalExtra, $overtimeHours, $totalWo]);
                                $attendanceSaved++;
                            } catch (Exception $e) {
                                $errors[] = "Attendance save failed for emp $empCode";
                            }
                            
                            // Save advances (if any advance data present)
                            if ($adv1 > 0 || $adv2 > 0 || $officeAdvance > 0 || $dressAdvance > 0) {
                                try {
                                    $stmt = $db->prepare("
                                        INSERT INTO employee_advances 
                                        (employee_id, unit_id, month, year, adv1, adv2, office_advance, dress_advance)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE 
                                            adv1 = VALUES(adv1),
                                            adv2 = VALUES(adv2),
                                            office_advance = VALUES(office_advance),
                                            dress_advance = VALUES(dress_advance),
                                            updated_at = CURRENT_TIMESTAMP
                                    ");
                                    $stmt->execute([$empCode, $unitId, $month, $year, $adv1, $adv2, $officeAdvance, $dressAdvance]);
                                    $advancesSaved++;
                                } catch (Exception $e) {
                                    $errors[] = "Advance save failed for emp $empCode";
                                }
                            }
                        }
                        fclose($handle);
                    } else {
                        // Process Excel using PhpSpreadsheet (if available) or simple XML parsing
                        require_once dirname(__FILE__) . '/../../vendor/autoload.php';
                        
                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                        $worksheet = $spreadsheet->getActiveSheet();
                        $rows = $worksheet->toArray();
                        
                        // First row - headers
                        $headers = $rows[0] ?? [];
                        $colMap = [];
                        foreach ($headers as $idx => $header) {
                            $header = trim(strtolower($header ?? ''));
                            $colMap[$header] = $idx;
                        }
                        
                        // Process data rows
                        for ($i = 1; $i < count($rows); $i++) {
                            $row = $rows[$i];
                            if (empty($row)) continue;
                            
                            // Get employee code
                            $empCode = 0;
                            if (isset($colMap['emp_code'])) $empCode = (int)($row[$colMap['emp_code']] ?? 0);
                            elseif (isset($colMap['employee_code'])) $empCode = (int)($row[$colMap['employee_code']] ?? 0);
                            elseif (isset($colMap['code'])) $empCode = (int)($row[$colMap['code']] ?? 0);
                            
                            if (empty($empCode)) continue;
                            
                            // Attendance fields
                            $totalPresent = isset($colMap['present']) ? floatval($row[$colMap['present']] ?? 0) : 0;
                            $totalExtra = isset($colMap['extra']) ? floatval($row[$colMap['extra']] ?? 0) : 0;
                            $overtimeHours = isset($colMap['ot_hours']) ? floatval($row[$colMap['ot_hours']] ?? 0) : 
                                             (isset($colMap['overtime']) ? floatval($row[$colMap['overtime']] ?? 0) : 0);
                            $totalWo = isset($colMap['wo']) ? intval($row[$colMap['wo']] ?? 0) : 0;
                            
                            // Advance fields
                            $adv1 = isset($colMap['adv1']) ? floatval($row[$colMap['adv1']] ?? 0) : 0;
                            $adv2 = isset($colMap['adv2']) ? floatval($row[$colMap['adv2']] ?? 0) : 0;
                            $officeAdvance = isset($colMap['office_adv']) ? floatval($row[$colMap['office_adv']] ?? 0) : 
                                             (isset($colMap['office_advance']) ? floatval($row[$colMap['office_advance']] ?? 0) : 0);
                            $dressAdvance = isset($colMap['dress_adv']) ? floatval($row[$colMap['dress_adv']] ?? 0) : 
                                            (isset($colMap['dress_advance']) ? floatval($row[$colMap['dress_advance']] ?? 0) : 0);
                            
                            // Save attendance
                            try {
                                $stmt = $db->prepare("
                                    INSERT INTO attendance_summary 
                                    (employee_id, unit_id, month, year, total_present, total_extra, overtime_hours, total_wo, source)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Excel Upload')
                                    ON DUPLICATE KEY UPDATE 
                                        total_present = VALUES(total_present),
                                        total_extra = VALUES(total_extra),
                                        overtime_hours = VALUES(overtime_hours),
                                        total_wo = VALUES(total_wo),
                                        source = 'Excel Upload',
                                        updated_at = CURRENT_TIMESTAMP
                                ");
                                $stmt->execute([$empCode, $unitId, $month, $year, $totalPresent, $totalExtra, $overtimeHours, $totalWo]);
                                $attendanceSaved++;
                            } catch (Exception $e) {
                                $errors[] = "Attendance save failed for emp $empCode";
                            }
                            
                            // Save advances (if any advance data present)
                            if ($adv1 > 0 || $adv2 > 0 || $officeAdvance > 0 || $dressAdvance > 0) {
                                try {
                                    $stmt = $db->prepare("
                                        INSERT INTO employee_advances 
                                        (employee_id, unit_id, month, year, adv1, adv2, office_advance, dress_advance)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE 
                                            adv1 = VALUES(adv1),
                                            adv2 = VALUES(adv2),
                                            office_advance = VALUES(office_advance),
                                            dress_advance = VALUES(dress_advance),
                                            updated_at = CURRENT_TIMESTAMP
                                    ");
                                    $stmt->execute([$empCode, $unitId, $month, $year, $adv1, $adv2, $officeAdvance, $dressAdvance]);
                                    $advancesSaved++;
                                } catch (Exception $e) {
                                    $errors[] = "Advance save failed for emp $empCode";
                                }
                            }
                        }
                    }
                    
                    $uploadResult = [
                        'attendance' => $attendanceSaved,
                        'advances' => $advancesSaved,
                        'errors' => $errors
                    ];
                    
                    if ($attendanceSaved > 0) {
                        setFlash('success', "Upload successful! Attendance: $attendanceSaved records, Advances: $advancesSaved records");
                    }
                    
                } catch (Exception $e) {
                    setFlash('error', 'Error processing file: ' . $e->getMessage());
                }
                
                // Delete uploaded file
                @unlink($filePath);
            } else {
                setFlash('error', 'Failed to upload file');
            }
        } else {
            setFlash('error', 'Invalid file type. Please upload Excel (.xlsx, .xls) or CSV file');
        }
    } else {
        setFlash('error', 'File upload error');
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Attendance & Advances</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="attendance/upload">
                    
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
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check me-1"></i>Select
                        </button>
                    </div>
                </form>
                
                <?php if ($selectedUnit): ?>
                <!-- Upload Form -->
                <form method="POST" enctype="multipart/form-data" class="border-top pt-3">
                    <input type="hidden" name="unit_id" value="<?php echo $selectedUnit; ?>">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required">Upload File (Excel or CSV)</label>
                            <input type="file" class="form-control" name="data_file" 
                                   accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                Upload Excel (.xlsx, .xls) or CSV file with attendance and advance data.
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" name="process_upload" class="btn btn-success">
                                <i class="bi bi-upload me-1"></i>Upload & Process
                            </button>
                        </div>
                    </div>
                </form>
                <?php endif; ?>
                
                <?php if ($uploadResult): ?>
                <div class="alert alert-info mt-3">
                    <h6><i class="bi bi-check-circle me-2"></i>Upload Result:</h6>
                    <ul class="mb-0">
                        <li>Attendance records saved: <strong><?php echo $uploadResult['attendance']; ?></strong></li>
                        <li>Advance records saved: <strong><?php echo $uploadResult['advances']; ?></strong></li>
                    </ul>
                    <?php if (!empty($uploadResult['errors'])): ?>
                    <div class="text-danger mt-2">
                        <strong>Errors:</strong> <?php echo implode(', ', $uploadResult['errors']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>File Format</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">The file should have the following columns:</p>
                
                <h6 class="mt-3 text-primary">Attendance Columns:</h6>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light">
                        <tr>
                            <th>Column</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>emp_code</code></td><td>Employee Code</td></tr>
                        <tr><td><code>present</code></td><td>Total Present Days</td></tr>
                        <tr><td><code>extra</code></td><td>Extra Days</td></tr>
                        <tr><td><code>ot_hours</code></td><td>Overtime Hours</td></tr>
                        <tr><td><code>wo</code></td><td>Weekly Off Days</td></tr>
                    </tbody>
                </table>
                
                <h6 class="mt-3 text-success">Advance Columns:</h6>
                <table class="table table-sm table-bordered small">
                    <thead class="table-light">
                        <tr>
                            <th>Column</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>adv1</code></td><td>Advance 1 (Rs)</td></tr>
                        <tr><td><code>adv2</code></td><td>Advance 2 (Rs)</td></tr>
                        <tr><td><code>office_adv</code></td><td>Office Advance (Rs)</td></tr>
                        <tr><td><code>dress_adv</code></td><td>Dress Advance (Rs)</td></tr>
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <a href="#" class="btn btn-outline-primary btn-sm" onclick="downloadTemplate()">
                        <i class="bi bi-download me-1"></i>Download Template
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

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

// Download template
function downloadTemplate() {
    const csvContent = `emp_code,present,extra,ot_hours,wo,adv1,adv2,office_adv,dress_adv
1001,26,0,8,4,0,0,0,0
1002,25,1,12,4,500,0,200,0
1003,26,0,0,4,0,1000,0,500`;
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'attendance_advance_template.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>
JS;
?>
