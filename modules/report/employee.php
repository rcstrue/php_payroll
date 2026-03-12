<?php
/**
 * RCS HRMS Pro - Employee Reports
 */

$pageTitle = 'Employee Reports';

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$clientName = isset($_GET['client_name']) ? sanitize($_GET['client_name']) : '';
$designation = isset($_GET['designation']) ? sanitize($_GET['designation']) : '';
$reportType = isset($_GET['report_type']) ? sanitize($_GET['report_type']) : 'summary';

// Build query
$where = "1=1";
$params = [];

if ($status) {
    $where .= " AND e.status = :status";
    $params['status'] = $status;
}

if ($clientName) {
    $where .= " AND e.client_name = :client_name";
    $params['client_name'] = $clientName;
}

if ($designation) {
    $where .= " AND e.designation = :designation";
    $params['designation'] = $designation;
}

// Get data based on report type
if ($reportType === 'summary') {
    // Summary report by client
    $sql = "SELECT 
                e.client_name,
                e.unit_name,
                COUNT(*) as total_employees,
                SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) as active_employees,
                SUM(CASE WHEN e.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN e.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM employees e
            WHERE {$where}
            GROUP BY e.client_name, e.unit_name
            ORDER BY e.client_name, e.unit_name";
} elseif ($reportType === 'detailed') {
    // Detailed employee list
    $sql = "SELECT 
                e.employee_code,
                e.full_name,
                e.gender,
                e.designation,
                e.department,
                e.client_name,
                e.unit_name,
                e.date_of_joining,
                e.status,
                ess.gross_salary,
                ess.pf_applicable,
                ess.esi_applicable
            FROM employees e
            LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id
            WHERE {$where}
            ORDER BY e.client_name, e.unit_name, e.full_name";
} elseif ($reportType === 'age_analysis') {
    // Age analysis
    $sql = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) < 25 THEN 'Below 25'
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) BETWEEN 25 AND 35 THEN '25-35'
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) BETWEEN 36 AND 45 THEN '36-45'
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_birth, CURDATE()) BETWEEN 46 AND 55 THEN '46-55'
                    ELSE 'Above 55'
                END as age_group,
                COUNT(*) as count,
                SUM(CASE WHEN e.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
                SUM(CASE WHEN e.gender = 'Female' THEN 1 ELSE 0 END) as female_count
            FROM employees e
            WHERE {$where}
            GROUP BY age_group
            ORDER BY FIELD(age_group, 'Below 25', '25-35', '36-45', '46-55', 'Above 55')";
} elseif ($reportType === 'tenure') {
    // Tenure analysis
    $sql = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(MONTH, e.date_of_joining, CURDATE()) < 6 THEN 'Less than 6 months'
                    WHEN TIMESTAMPDIFF(MONTH, e.date_of_joining, CURDATE()) BETWEEN 6 AND 11 THEN '6-12 months'
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_joining, CURDATE()) BETWEEN 1 AND 2 THEN '1-2 years'
                    WHEN TIMESTAMPDIFF(YEAR, e.date_of_joining, CURDATE()) BETWEEN 3 AND 5 THEN '3-5 years'
                    ELSE 'More than 5 years'
                END as tenure_group,
                COUNT(*) as count
            FROM employees e
            WHERE {$where}
            GROUP BY tenure_group
            ORDER BY FIELD(tenure_group, 'Less than 6 months', '6-12 months', '1-2 years', '3-5 years', 'More than 5 years')";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get filter options
$clients = $db->query("SELECT DISTINCT client_name FROM employees WHERE client_name IS NOT NULL ORDER BY client_name")->fetchAll(PDO::FETCH_ASSOC);
$designations = $db->query("SELECT DISTINCT designation FROM employees WHERE designation IS NOT NULL ORDER BY designation")->fetchAll(PDO::FETCH_ASSOC);

// Handle export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="employee_report_' . $reportType . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Employee Report - ' . ucfirst($reportType)]);
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
                <h5 class="card-title mb-0"><i class="bi bi-people me-2"></i>Employee Reports</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-4">
                    <input type="hidden" name="page" value="report/employee">
                    
                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="report_type">
                            <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary by Client</option>
                            <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed List</option>
                            <option value="age_analysis" <?php echo $reportType === 'age_analysis' ? 'selected' : ''; ?>>Age Analysis</option>
                            <option value="tenure" <?php echo $reportType === 'tenure' ? 'selected' : ''; ?>>Tenure Analysis</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="terminated" <?php echo $status === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            <option value="pending_hr_verification" <?php echo $status === 'pending_hr_verification' ? 'selected' : ''; ?>>Pending</option>
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
                    
                    <div class="col-md-3">
                        <label class="form-label">Designation</label>
                        <select class="form-select" name="designation">
                            <option value="">All Designations</option>
                            <?php foreach ($designations as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['designation']); ?>"
                                    <?php echo $designation === $d['designation'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($d['designation']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
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
                                <th class="text-end">Total</th>
                                <th class="text-end">Active</th>
                                <th class="text-end">Male</th>
                                <th class="text-end">Female</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grandTotal = 0;
                            $grandActive = 0;
                            $grandMale = 0;
                            $grandFemale = 0;
                            ?>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td class="text-end"><?php echo $row['total_employees']; ?></td>
                                <td class="text-end"><?php echo $row['active_employees']; ?></td>
                                <td class="text-end"><?php echo $row['male_count']; ?></td>
                                <td class="text-end"><?php echo $row['female_count']; ?></td>
                            </tr>
                            <?php 
                            $grandTotal += $row['total_employees'];
                            $grandActive += $row['active_employees'];
                            $grandMale += $row['male_count'];
                            $grandFemale += $row['female_count'];
                            ?>
                            <?php endforeach; ?>
                            <tr class="table-dark">
                                <td colspan="2"><strong>Total</strong></td>
                                <td class="text-end"><strong><?php echo $grandTotal; ?></strong></td>
                                <td class="text-end"><strong><?php echo $grandActive; ?></strong></td>
                                <td class="text-end"><strong><?php echo $grandMale; ?></strong></td>
                                <td class="text-end"><strong><?php echo $grandFemale; ?></strong></td>
                            </tr>
                        </tbody>
                        
                        <?php elseif ($reportType === 'detailed'): ?>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Designation</th>
                                <th>Client</th>
                                <th>Unit</th>
                                <th>DOJ</th>
                                <th>Status</th>
                                <th class="text-end">Gross</th>
                                <th>PF</th>
                                <th>ESI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><code><?php echo sanitize($row['employee_code']); ?></code></td>
                                <td><?php echo sanitize($row['full_name']); ?></td>
                                <td><?php echo sanitize($row['gender']); ?></td>
                                <td><?php echo sanitize($row['designation']); ?></td>
                                <td><?php echo sanitize($row['client_name']); ?></td>
                                <td><?php echo sanitize($row['unit_name']); ?></td>
                                <td><?php echo formatDate($row['date_of_joining']); ?></td>
                                <td><span class="badge bg-<?php echo $row['status'] === 'approved' ? 'success' : 'secondary'; ?>"><?php echo sanitize($row['status']); ?></span></td>
                                <td class="text-end"><?php echo formatCurrency($row['gross_salary']); ?></td>
                                <td><?php echo $row['pf_applicable'] ? '<i class="bi bi-check text-success"></i>' : ''; ?></td>
                                <td><?php echo $row['esi_applicable'] ? '<i class="bi bi-check text-success"></i>' : ''; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        
                        <?php elseif ($reportType === 'age_analysis' || $reportType === 'tenure'): ?>
                        <thead>
                            <tr>
                                <th><?php echo $reportType === 'age_analysis' ? 'Age Group' : 'Tenure'; ?></th>
                                <th class="text-end">Count</th>
                                <?php if ($reportType === 'age_analysis'): ?>
                                <th class="text-end">Male</th>
                                <th class="text-end">Female</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td><?php echo sanitize($row[$reportType === 'age_analysis' ? 'age_group' : 'tenure_group']); ?></td>
                                <td class="text-end"><?php echo $row['count']; ?></td>
                                <?php if ($reportType === 'age_analysis'): ?>
                                <td class="text-end"><?php echo $row['male_count']; ?></td>
                                <td class="text-end"><?php echo $row['female_count']; ?></td>
                                <?php endif; ?>
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
