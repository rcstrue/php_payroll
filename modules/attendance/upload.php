<?php
/**
 * RCS HRMS Pro - Attendance Upload Page
 */

$pageTitle = 'Upload Attendance';

// Get units
$stmt = $db->query("SELECT u.id, u.name as unit_name, c.name as client_name FROM units u LEFT JOIN clients c ON u.client_id = c.id WHERE u.is_active = 1 ORDER BY c.name as client_name, u.name as unit_name");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle upload
$uploadResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_file'])) {
    $unitId = (int)$_POST['unit_id'];
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    $file = $_FILES['attendance_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Check file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
            // Move uploaded file
            $fileName = 'attendance_' . $unitId . '_' . $month . '_' . $year . '_' . time() . '.' . $ext;
            $filePath = UPLOAD_PATH . 'attendance/' . $fileName;
            
            if (!is_dir(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $filePath)) {
                // Process upload
                if ($ext === 'csv') {
                    // Process CSV
                    $uploadResult = $attendance->uploadFromCSV($filePath, $unitId, $month, $year);
                } else {
                    // Process Excel
                    $uploadResult = $attendance->uploadFromExcel($filePath, $unitId, $month, $year);
                }
                
                if (isset($uploadResult['error'])) {
                    setFlash('error', $uploadResult['error']);
                } else {
                    setFlash('success', "Attendance uploaded successfully! Imported: {$uploadResult['imported']} records");
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
}
?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cloud-upload me-2"></i>Upload Attendance</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Unit</label>
                            <select class="form-select select2" name="unit_id" required>
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo sanitize($u['client_name'] . ' - ' . $u['unit_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Month</label>
                            <select class="form-select" name="month" required>
                                <?php 
                                for ($m = 1; $m <= 12; $m++):
                                    $selected = $m == date('n') ? 'selected' : '';
                                ?>
                                <option value="<?php echo $m; ?>" <?php echo $selected; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Year</label>
                            <select class="form-select" name="year" required>
                                <?php 
                                $currentYear = date('Y');
                                for ($y = $currentYear; $y >= $currentYear - 2; $y--):
                                ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label required">Attendance File</label>
                            <input type="file" class="form-control" name="attendance_file" 
                                   accept=".xlsx,.xls,.csv" required>
                            <div class="form-text">
                                Upload Excel (.xlsx, .xls) or CSV file. 
                                First row should be headers with employee code and day columns (1-31).
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i>Upload Attendance
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>File Format</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small">The attendance file should have the following format:</p>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Emp Code</th>
                            <th>1</th>
                            <th>2</th>
                            <th>...</th>
                            <th>31</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>EMP001</td>
                            <td>P</td>
                            <td>P</td>
                            <td>...</td>
                            <td>A</td>
                        </tr>
                        <tr>
                            <td>EMP002</td>
                            <td>P</td>
                            <td>WO</td>
                            <td>...</td>
                            <td>P</td>
                        </tr>
                    </tbody>
                </table>
                
                <h6 class="mt-3">Status Codes:</h6>
                <ul class="list-unstyled small">
                    <li><span class="badge bg-success-soft">P</span> Present</li>
                    <li><span class="badge bg-danger-soft">A</span> Absent</li>
                    <li><span class="badge bg-secondary-soft">WO</span> Weekly Off</li>
                    <li><span class="badge bg-info-soft">H</span> Holiday</li>
                    <li><span class="badge bg-warning-soft">PL</span> Paid Leave</li>
                    <li><span class="badge bg-warning-soft">SL</span> Sick Leave</li>
                    <li><span class="badge bg-warning-soft">CL</span> Casual Leave</li>
                    <li><span class="badge bg-warning-soft">HD</span> Half Day</li>
                </ul>
                
                <div class="mt-3">
                    <a href="assets/templates/attendance_template.xlsx" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i>Download Template
                    </a>
                </div>
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
                                <th>Unit</th>
                                <th>Month/Year</th>
                                <th>Records</th>
                                <th>Uploaded By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $db->query(
                                "SELECT DISTINCT unit_id, COUNT(*) as record_count, 
                                        MONTH(attendance_date) as month, YEAR(attendance_date) as year,
                                        uploaded_by, MAX(created_at) as upload_date
                                 FROM attendance 
                                 WHERE source = 'Excel Upload'
                                 GROUP BY unit_id, MONTH(attendance_date), YEAR(attendance_date)
                                 ORDER BY upload_date DESC LIMIT 10"
                            );
                            $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (empty($recentUploads)):
                            ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No uploads yet</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($recentUploads as $upload): 
                                $stmt2 = $db->prepare("SELECT name as unit_name FROM units WHERE id = ?");
                                $stmt2->execute([$upload['unit_id']]);
                                $unitName = $stmt2->fetchColumn();
                            ?>
                            <tr>
                                <td><?php echo sanitize($unitName); ?></td>
                                <td><?php echo date('F Y', mktime(0, 0, 0, $upload['month'], 1, $upload['year'])); ?></td>
                                <td><?php echo number_format($upload['record_count']); ?></td>
                                <td><?php echo $upload['uploaded_by'] ?? 'System'; ?></td>
                                <td><?php echo formatDate($upload['upload_date'], 'd-m-Y H:i'); ?></td>
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
