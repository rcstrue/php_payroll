<?php
/**
 * RCS HRMS Pro - Salary Revision Module
 * Handle salary increments, revisions, and history tracking
 * Supports daily rate and monthly rate calculations
 * Location-wise salary changes
 * 
 * NOTE: This file is included via index.php which already loads:
 * - config.php
 * - database.php
 * - All class files via autoloader
 * - header.php template
 */

$pageTitle = 'Salary Revision';

// Check permissions
if (!in_array($_SESSION['role_code'] ?? '', ['admin', 'hr_executive'])) {
    setFlash('error', 'Access denied');
    redirect('index.php?page=dashboard');
}

// Get filter
$clientFilter = isset($_GET['client']) ? sanitize($_GET['client']) : '';
$unitFilter = isset($_GET['unit']) ? sanitize($_GET['unit']) : '';

// Get clients
$clients = $db->fetchAll(
    "SELECT DISTINCT c.id, c.name as client_name 
     FROM employees e LEFT JOIN clients c ON e.client_id = c.id 
     WHERE c.name IS NOT NULL ORDER BY client_name"
);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'single_revision') {
        $employeeId = (int)($_POST['employee_id'] ?? 0);
        $revisionType = sanitize($_POST['revision_type'] ?? '');
        $effectiveFrom = sanitize($_POST['effective_from'] ?? '');
        $reason = sanitize($_POST['reason'] ?? '');
        $revisionMonth = (int)($_POST['revision_month'] ?? date('n'));
        $revisionYear = (int)($_POST['revision_year'] ?? date('Y'));
        
        // Get current salary
        $currentSalary = $db->fetch(
            "SELECT * FROM employee_salary_structures 
             WHERE employee_id = :id AND (effective_to IS NULL OR effective_to >= CURDATE())
             ORDER BY effective_from DESC LIMIT 1",
            ['id' => $employeeId]
        );
        
        // Calculate new salary based on revision type
        $newBasic = floatval($_POST['new_basic'] ?? 0);
        $newDA = floatval($_POST['new_da'] ?? 0);
        $newHRA = floatval($_POST['new_hra'] ?? 0);
        $newGross = $newBasic + $newDA + $newHRA;
        
        if ($revisionType === 'percentage') {
            $percentage = floatval($_POST['percentage'] ?? 0);
            $currentBasic = floatval($currentSalary['basic_wage'] ?? 0);
            $newBasic = $currentBasic * (1 + $percentage / 100);
            $newDA = floatval($currentSalary['da'] ?? 0) * (1 + $percentage / 100);
            $newHRA = floatval($currentSalary['hra'] ?? 0) * (1 + $percentage / 100);
            $newGross = $newBasic + $newDA + $newHRA;
        } elseif ($revisionType === 'daily_rate') {
            // Convert daily rate to monthly
            $dailyRate = floatval($_POST['daily_rate'] ?? 0);
            $workingDays = intval($_POST['working_days'] ?? 26);
            $newBasic = $dailyRate * $workingDays;
            $newGross = $newBasic * 1.4; // Basic + DA + HRA typically 40% more
            $newDA = $newBasic * 0.2;    // ~20% of basic
            $newHRA = $newBasic * 0.2;   // ~20% of basic
        }
        
        try {
            $db->beginTransaction();
            
            // Close current salary structure
            if ($currentSalary) {
                $db->update('employee_salary_structures', [
                    'effective_to' => date('Y-m-d', strtotime($effectiveFrom . ' -1 day'))
                ], 'id = :id', ['id' => $currentSalary['id']]);
            }
            
            // Insert new salary structure
            $newSalaryId = $db->insert('employee_salary_structures', [
                'employee_id' => $employeeId,
                'effective_from' => $effectiveFrom,
                'basic_wage' => $newBasic,
                'da' => $newDA,
                'hra' => $newHRA,
                'conveyance' => floatval($_POST['conveyance'] ?? $currentSalary['conveyance'] ?? 0),
                'medical_allowance' => floatval($_POST['medical'] ?? $currentSalary['medical_allowance'] ?? 0),
                'special_allowance' => floatval($_POST['special'] ?? $currentSalary['special_allowance'] ?? 0),
                'gross_salary' => $newGross,
                'pf_applicable' => isset($_POST['pf_applicable']) ? 1 : ($currentSalary['pf_applicable'] ?? 1),
                'esi_applicable' => isset($_POST['esi_applicable']) ? 1 : ($currentSalary['esi_applicable'] ?? 1),
                'pt_applicable' => isset($_POST['pt_applicable']) ? 1 : ($currentSalary['pt_applicable'] ?? 1),
                'daily_rate' => $revisionType === 'daily_rate' ? floatval($_POST['daily_rate']) : null,
                'monthly_rate' => $newGross,
                'created_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Log revision history
            $db->insert('salary_revisions', [
                'employee_id' => $employeeId,
                'old_basic' => $currentSalary['basic_wage'] ?? 0,
                'new_basic' => $newBasic,
                'old_gross' => $currentSalary['gross_salary'] ?? 0,
                'new_gross' => $newGross,
                'revision_type' => $revisionType,
                'percentage' => $revisionType === 'percentage' ? floatval($_POST['percentage'] ?? 0) : null,
                'daily_rate' => $revisionType === 'daily_rate' ? floatval($_POST['daily_rate'] ?? 0) : null,
                'effective_from' => $effectiveFrom,
                'revision_month' => $revisionMonth,
                'revision_year' => $revisionYear,
                'reason' => $reason,
                'revision_by' => $_SESSION['user_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $db->commit();
            setFlash('success', 'Salary revised successfully! New gross: ' . formatCurrency($newGross));
            
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Error: ' . $e->getMessage());
        }
        
        redirect('index.php?page=payroll/salary-revision');
    }
    
    if ($action === 'bulk_revision') {
        $clientId = (int)($_POST['client_id'] ?? 0);
        $unitId = (int)($_POST['unit_id'] ?? 0);
        $percentage = floatval($_POST['bulk_percentage'] ?? 0);
        $effectiveFrom = sanitize($_POST['bulk_effective_from'] ?? '');
        $reason = sanitize($_POST['bulk_reason'] ?? '');
        
        // Get employees
        $sql = "SELECT e.id, e.employee_code, ess.* 
                FROM employees e
                LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
                    AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
                WHERE e.status = 'approved'";
        $params = [];
        
        if ($clientId) {
            $sql .= " AND e.client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        if ($unitId) {
            $sql .= " AND e.unit_id = :unit_id";
            $params['unit_id'] = $unitId;
        }
        
        $employees = $db->fetchAll($sql, $params);
        $updated = 0;
        
        try {
            $db->beginTransaction();
            
            foreach ($employees as $emp) {
                $oldGross = floatval($emp['gross_salary'] ?? 0);
                $newGross = $oldGross * (1 + $percentage / 100);
                $newBasic = floatval($emp['basic_wage'] ?? 0) * (1 + $percentage / 100);
                
                // Close old structure
                if (!empty($emp['id'])) {
                    $db->update('employee_salary_structures', [
                        'effective_to' => date('Y-m-d', strtotime($effectiveFrom . ' -1 day'))
                    ], 'id = :id', ['id' => $emp['id']]);
                }
                
                // Insert new structure
                $db->insert('employee_salary_structures', [
                    'employee_id' => $emp['employee_id'] ?? $emp['id'],
                    'effective_from' => $effectiveFrom,
                    'basic_wage' => $newBasic,
                    'da' => floatval($emp['da'] ?? 0) * (1 + $percentage / 100),
                    'hra' => floatval($emp['hra'] ?? 0) * (1 + $percentage / 100),
                    'gross_salary' => $newGross,
                    'pf_applicable' => $emp['pf_applicable'] ?? 1,
                    'esi_applicable' => $emp['esi_applicable'] ?? 1,
                    'created_by' => $_SESSION['user_id'] ?? null
                ]);
                
                // Log revision
                $db->insert('salary_revisions', [
                    'employee_id' => $emp['employee_id'] ?? $emp['id'],
                    'old_basic' => $emp['basic_wage'] ?? 0,
                    'new_basic' => $newBasic,
                    'old_gross' => $oldGross,
                    'new_gross' => $newGross,
                    'revision_type' => 'percentage',
                    'percentage' => $percentage,
                    'effective_from' => $effectiveFrom,
                    'reason' => $reason,
                    'revision_by' => $_SESSION['user_id'] ?? null
                ]);
                
                $updated++;
            }
            
            $db->commit();
            setFlash('success', "Bulk revision completed! $updated employees updated.");
            
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('error', 'Error: ' . $e->getMessage());
        }
        
        redirect('index.php?page=payroll/salary-revision');
    }
}

// Get revision history
$revisions = $db->fetchAll(
    "SELECT sr.*, e.employee_code, e.full_name, c.name as client_name, u.name as unit_name
     FROM salary_revisions sr
     JOIN employees e ON sr.employee_id = e.id
     LEFT JOIN clients c ON e.client_id = c.id
     LEFT JOIN units u ON e.unit_id = u.id
     ORDER BY sr.created_at DESC
     LIMIT 100"
);

// Get employees with current salary
$employees = $db->fetchAll(
    "SELECT e.id, e.employee_code, e.full_name, c.name as client_name, u.name as unit_name, 
            e.state, e.worker_category, e.skill_category,
            ess.basic_wage, ess.gross_salary, ess.daily_rate, ess.monthly_rate
     FROM employees e
     LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id 
        AND (ess.effective_to IS NULL OR ess.effective_to >= CURDATE())
     LEFT JOIN clients c ON e.client_id = c.id
     LEFT JOIN units u ON e.unit_id = u.id
     WHERE e.status = 'approved'
     ORDER BY c.name, e.full_name"
);

// Get units for filter
$units = $db->fetchAll(
    "SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name"
);
?>

<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up-arrow me-2"></i>Salary Revision
                </h5>
                <p class="text-muted mb-0 small">Manage salary increments, daily/monthly rates, and location changes</p>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="revisionTabs">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#singleRevision">
                    <i class="bi bi-person me-1"></i>Single Revision
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#bulkRevision">
                    <i class="bi bi-people me-1"></i>Bulk Revision
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#history">
                    <i class="bi bi-clock-history me-1"></i>History
                </a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- Single Revision Tab -->
            <div class="tab-pane fade show active" id="singleRevision">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0"><i class="bi bi-pencil-square me-2"></i>Employee Salary Revision</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="single_revision">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                                            <select class="form-select select2" name="employee_id" required id="employeeSelect">
                                                <option value="">Select Employee</option>
                                                <?php foreach ($employees as $emp): ?>
                                                <option value="<?php echo $emp['id']; ?>" 
                                                        data-basic="<?php echo $emp['basic_wage'] ?? 0; ?>"
                                                        data-gross="<?php echo $emp['gross_salary'] ?? 0; ?>">
                                                    <?php echo sanitize($emp['employee_code'] . ' - ' . $emp['full_name']); ?>
                                                    (<?php echo sanitize($emp['client_name'] ?? ''); ?>)
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Current Gross</label>
                                            <input type="text" class="form-control" id="currentGross" readonly>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <label class="form-label">Effective From <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="effective_from" required
                                                   value="<?php echo date('Y-m-01', strtotime('+1 month')); ?>">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Revision Type <span class="text-danger">*</span></label>
                                            <select class="form-select" name="revision_type" id="revisionType" required>
                                                <option value="percentage">Percentage Increment</option>
                                                <option value="fixed">Fixed Amount</option>
                                                <option value="daily_rate">Daily Rate</option>
                                                <option value="monthly_rate">Monthly Rate</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-md-4" id="percentageSection">
                                            <label class="form-label">Percentage (%)</label>
                                            <input type="number" class="form-control" name="percentage" 
                                                   step="0.1" placeholder="e.g. 10 for 10%">
                                        </div>
                                        
                                        <div class="col-md-4" id="dailyRateSection" style="display: none;">
                                            <label class="form-label">Daily Rate (₹)</label>
                                            <input type="number" class="form-control" name="daily_rate" step="0.01">
                                            <small class="text-muted">Working days: 26</small>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">New Basic</label>
                                            <input type="number" class="form-control" name="new_basic" id="newBasic" step="0.01">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">New DA</label>
                                            <input type="number" class="form-control" name="new_da" id="newDA" step="0.01">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">New HRA</label>
                                            <input type="number" class="form-control" name="new_hra" id="newHRA" step="0.01">
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">New Gross (Calculated)</label>
                                            <input type="text" class="form-control" id="newGross" readonly>
                                        </div>
                                        
                                        <div class="col-md-4">
                                            <label class="form-label">Revision Month</label>
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <select class="form-select" name="revision_month">
                                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                                        <option value="<?php echo $m; ?>" <?php echo $m == date('n') ? 'selected' : ''; ?>>
                                                            <?php echo date('M', mktime(0, 0, 0, $m, 1)); ?>
                                                        </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" name="revision_year">
                                                        <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Reason</label>
                                            <input type="text" class="form-control" name="reason" 
                                                   placeholder="e.g. Annual increment, Promotion, Location change">
                                        </div>
                                        
                                        <div class="col-12">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="pf_applicable" id="pfApplicable" checked>
                                                <label class="form-check-label" for="pfApplicable">PF Applicable</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="esi_applicable" id="esiApplicable" checked>
                                                <label class="form-check-label" for="esiApplicable">ESI Applicable</label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary mt-3">
                                        <i class="bi bi-check-lg me-1"></i>Apply Revision
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card bg-light">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="bi bi-calculator me-2"></i>Quick Calculator</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label small">Daily Rate</label>
                                    <input type="number" class="form-control form-control-sm" id="calcDailyRate" placeholder="Daily rate">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Working Days</label>
                                    <input type="number" class="form-control form-control-sm" id="calcDays" value="26">
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="calculateMonthly()">
                                    Calculate Monthly
                                </button>
                                <div class="mt-2 p-2 bg-white rounded text-center">
                                    <small class="text-muted">Monthly:</small>
                                    <div class="h5 mb-0" id="calcMonthly">-</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Revision Tab -->
            <div class="tab-pane fade" id="bulkRevision">
                <div class="card">
                    <div class="card-header bg-warning">
                        <h6 class="card-title mb-0"><i class="bi bi-people me-2"></i>Bulk Salary Revision</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> This will update salaries for ALL selected employees. 
                            Please review carefully before proceeding.
                        </div>
                        
                        <form method="POST">
                            <input type="hidden" name="action" value="bulk_revision">
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Filter by Client</label>
                                    <select class="form-select" name="client_id" id="bulkClient">
                                        <option value="">All Clients</option>
                                        <?php foreach ($clients as $c): ?>
                                        <option value="<?php echo $c['id']; ?>">
                                            <?php echo sanitize($c['client_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Filter by Unit</label>
                                    <select class="form-select" name="unit_id" id="bulkUnit">
                                        <option value="">All Units</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Employees Affected</label>
                                    <input type="text" class="form-control" id="affectedCount" readonly value="0">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Increment % <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="bulk_percentage" 
                                           step="0.1" required placeholder="e.g. 10">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Effective From <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="bulk_effective_from" required
                                           value="<?php echo date('Y-m-01', strtotime('+1 month')); ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Reason</label>
                                    <input type="text" class="form-control" name="bulk_reason" 
                                           value="Annual Increment">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning mt-3" 
                                    onclick="return confirm('Are you sure? This will update ALL selected employees.')">
                                <i class="bi bi-check-lg me-1"></i>Apply Bulk Revision
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- History Tab -->
            <div class="tab-pane fade" id="history">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Revision History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Employee</th>
                                        <th>Client/Unit</th>
                                        <th>Type</th>
                                        <th class="text-end">Old Gross</th>
                                        <th class="text-end">New Gross</th>
                                        <th>Difference</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($revisions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">No revisions yet</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($revisions as $r): ?>
                                    <tr>
                                        <td><?php echo formatDate($r['effective_from']); ?></td>
                                        <td>
                                            <div><?php echo sanitize($r['full_name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize($r['employee_code']); ?></small>
                                        </td>
                                        <td>
                                            <div><?php echo sanitize($r['client_name'] ?? ''); ?></div>
                                            <small class="text-muted"><?php echo sanitize($r['unit_name'] ?? ''); ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['percentage'])): ?>
                                            <span class="badge bg-primary">+<?php echo $r['percentage']; ?>%</span>
                                            <?php elseif (!empty($r['daily_rate'])): ?>
                                            <span class="badge bg-info">Daily: <?php echo formatCurrency($r['daily_rate']); ?></span>
                                            <?php else: ?>
                                            <span class="badge bg-secondary">Fixed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo formatCurrency($r['old_gross'] ?? 0); ?></td>
                                        <td class="text-end"><strong><?php echo formatCurrency($r['new_gross'] ?? 0); ?></strong></td>
                                        <td class="text-success">
                                            <?php 
                                            $diff = ($r['new_gross'] ?? 0) - ($r['old_gross'] ?? 0);
                                            echo '+' . formatCurrency($diff);
                                            ?>
                                        </td>
                                        <td><small><?php echo sanitize($r['reason'] ?? '-'); ?></small></td>
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
    </div>
</div>

<script>
var allUnits = <?php echo json_encode($units); ?>;

$(document).ready(function() {
    // Revision type toggle
    $('#revisionType').on('change', function() {
        var type = $(this).val();
        $('#percentageSection, #dailyRateSection').hide();
        
        if (type === 'percentage') {
            $('#percentageSection').show();
        } else if (type === 'daily_rate') {
            $('#dailyRateSection').show();
        }
    });

    // Employee select
    $('#employeeSelect').on('change', function() {
        var opt = $(this).find('option:selected');
        var gross = opt.data('gross') || 0;
        $('#currentGross').val('₹' + parseFloat(gross).toLocaleString());
    });

    // Calculate new gross
    $('#newBasic, #newDA, #newHRA').on('input', function() {
        var basic = parseFloat($('#newBasic').val()) || 0;
        var da = parseFloat($('#newDA').val()) || 0;
        var hra = parseFloat($('#newHRA').val()) || 0;
        $('#newGross').val('₹' + (basic + da + hra).toLocaleString());
    });

    // Bulk revision client/unit filter
    $('#bulkClient').on('change', function() {
        var clientId = $(this).val();
        var $unitSelect = $('#bulkUnit');
        $unitSelect.find('option:not(:first)').remove();
        
        allUnits.forEach(function(unit) {
            if (!clientId || unit.client_id == clientId) {
                $unitSelect.append('<option value="' + unit.id + '">' + unit.name + '</option>');
            }
        });
    });
});

// Quick calculator
function calculateMonthly() {
    var daily = parseFloat($('#calcDailyRate').val()) || 0;
    var days = parseInt($('#calcDays').val()) || 26;
    var monthly = daily * days;
    $('#calcMonthly').text('₹' + monthly.toLocaleString());
}
</script>
