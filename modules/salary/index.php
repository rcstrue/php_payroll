<?php
/**
 * RCS HRMS Pro - Salary Structure Management
 * Manage employee salary structures for entire unit at once
 */

$pageTitle = 'Salary Structures';

// Get clients
$clients = [];
try {
    $stmt = $db->query("SELECT id, name, client_code FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist
}

// Get selected filters
$selectedClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : null;
$selectedUnit = isset($_GET['unit_id']) ? (int)$_GET['unit_id'] : null;

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

// Handle Save All
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_salaries'])) {
    $savedCount = 0;
    $errors = [];
    
    try {
        $db->beginTransaction();
        
        foreach ($_POST['employee_id'] as $index => $empId) {
            $basicWage = floatval($_POST['basic_wage'][$index] ?? 0);
            $da = floatval($_POST['da'][$index] ?? 0);
            $hra = floatval($_POST['hra'][$index] ?? 0);
            $conveyance = floatval($_POST['conveyance'][$index] ?? 0);
            $medical = floatval($_POST['medical_allowance'][$index] ?? 0);
            $special = floatval($_POST['special_allowance'][$index] ?? 0);
            $other = floatval($_POST['other_allowance'][$index] ?? 0);
            $grossSalary = floatval($_POST['gross_salary'][$index] ?? 0);
            $pfApplicable = isset($_POST['pf_applicable'][$index]) ? 1 : 0;
            $esiApplicable = isset($_POST['esi_applicable'][$index]) ? 1 : 0;
            $ptApplicable = isset($_POST['pt_applicable'][$index]) ? 1 : 0;
            
            // If gross not entered, calculate
            if ($grossSalary == 0) {
                $grossSalary = $basicWage + $da + $hra + $conveyance + $medical + $special + $other;
            }
            
            // Check if salary structure exists
            $checkStmt = $db->prepare("SELECT id FROM employee_salary_structures WHERE employee_id = ? AND effective_to IS NULL");
            $checkStmt->execute([$empId]);
            $existingId = $checkStmt->fetchColumn();
            
            if ($existingId) {
                // Update
                $updateStmt = $db->prepare("
                    UPDATE employee_salary_structures 
                    SET basic_wage = ?, da = ?, hra = ?, conveyance = ?, medical_allowance = ?, 
                        special_allowance = ?, other_allowance = ?, gross_salary = ?,
                        pf_applicable = ?, esi_applicable = ?, pt_applicable = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$basicWage, $da, $hra, $conveyance, $medical, $special, $other, $grossSalary, $pfApplicable, $esiApplicable, $ptApplicable, $existingId]);
            } else {
                // Insert
                $insertStmt = $db->prepare("
                    INSERT INTO employee_salary_structures 
                    (employee_id, effective_from, basic_wage, da, hra, conveyance, medical_allowance, 
                     special_allowance, other_allowance, gross_salary, pf_applicable, esi_applicable, pt_applicable)
                    VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$empId, $basicWage, $da, $hra, $conveyance, $medical, $special, $other, $grossSalary, $pfApplicable, $esiApplicable, $ptApplicable]);
            }
            $savedCount++;
        }
        
        $db->commit();
        setFlash('success', "Salary structures saved for $savedCount employees.");
        
        // Redirect
        $redirectUrl = "index.php?page=salary/index&client_id=$selectedClient&unit_id=$selectedUnit&load=1";
        echo "<script>window.location.href='$redirectUrl';</script>";
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        setFlash('error', 'Error saving salary structures: ' . $e->getMessage());
    }
}

// Get employees with salary data
$employees = [];
if ($selectedUnit && isset($_GET['load'])) {
    try {
        $stmt = $db->prepare("
            SELECT e.id, e.employee_code, e.full_name, e.designation, e.worker_category, e.status,
                   ess.id as salary_id, ess.basic_wage, ess.da, ess.hra, ess.conveyance,
                   ess.medical_allowance, ess.special_allowance, ess.other_allowance, ess.gross_salary,
                   ess.pf_applicable, ess.esi_applicable, ess.pt_applicable
            FROM employees e
            LEFT JOIN employee_salary_structures ess ON e.id = ess.employee_id AND ess.effective_to IS NULL
            WHERE e.unit_id = ? AND e.status != 'pending_hr_verification'
            ORDER BY e.employee_code
        ");
        $stmt->execute([$selectedUnit]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching employees: " . $e->getMessage());
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-currency-rupee me-2"></i>Salary Structure Management</h5>
            </div>
            <div class="card-body">
                <!-- Filters Form -->
                <form method="GET" class="row g-3 mb-4" id="filterForm">
                    <input type="hidden" name="page" value="salary/index">
                    
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
<!-- Salary Structure Form -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <span class="badge bg-primary"><?php echo count($employees); ?> Employees</span>
                </div>
                <div>
                    <a href="index.php?page=salary/index" class="btn btn-outline-secondary btn-sm">
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
                
                <form method="POST" id="salaryForm">
                    <input type="hidden" name="client_id" value="<?php echo $selectedClient; ?>">
                    <input type="hidden" name="unit_id" value="<?php echo $selectedUnit; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" style="font-size: 12px;">
                            <thead class="table-dark" style="position: sticky; top: 0; z-index: 10;">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th style="width: 60px;">Emp Code</th>
                                    <th style="width: 150px;">Employee Name</th>
                                    <th style="width: 100px;">Category</th>
                                    <th style="width: 90px;">Basic Wage</th>
                                    <th style="width: 80px;">DA</th>
                                    <th style="width: 80px;">HRA</th>
                                    <th style="width: 80px;">Conv.</th>
                                    <th style="width: 80px;">Medical</th>
                                    <th style="width: 80px;">Special</th>
                                    <th style="width: 80px;">Other</th>
                                    <th style="width: 100px;">Gross Salary</th>
                                    <th style="width: 50px;" class="text-center bg-info text-white">
                                        PF<br>
                                        <input type="checkbox" id="selectAllPF" class="form-check-input" checked>
                                    </th>
                                    <th style="width: 50px;" class="text-center bg-warning text-dark">
                                        ESI<br>
                                        <input type="checkbox" id="selectAllESI" class="form-check-input" checked>
                                    </th>
                                    <th style="width: 50px;" class="text-center bg-secondary text-white">
                                        PT<br>
                                        <input type="checkbox" id="selectAllPT" class="form-check-input" checked>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $sr = 1;
                                foreach ($employees as $emp): 
                                    $grossCalculated = ($emp['basic_wage'] ?? 0) + ($emp['da'] ?? 0) + ($emp['hra'] ?? 0) + 
                                                       ($emp['conveyance'] ?? 0) + ($emp['medical_allowance'] ?? 0) + 
                                                       ($emp['special_allowance'] ?? 0) + ($emp['other_allowance'] ?? 0);
                                    $hasSalary = !empty($emp['salary_id']);
                                ?>
                                <tr class="<?php echo !$hasSalary ? 'table-warning' : ''; ?>">
                                    <td class="text-center"><?php echo $sr++; ?></td>
                                    <td>
                                        <input type="hidden" name="employee_id[]" value="<?php echo $emp['id']; ?>">
                                        <code><?php echo sanitize($emp['employee_code']); ?></code>
                                        <?php if (!$hasSalary): ?>
                                        <i class="bi bi-exclamation-triangle text-warning" title="No Salary Structure"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo sanitize($emp['full_name']); ?></td>
                                    <td><small><?php echo sanitize($emp['worker_category']); ?></small></td>
                                    <td>
                                        <input type="number" name="basic_wage[]" class="form-control form-control-sm text-end basic-input" 
                                               value="<?php echo $emp['basic_wage'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="da[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['da'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="hra[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['hra'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="conveyance[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['conveyance'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="medical_allowance[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['medical_allowance'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="special_allowance[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['special_allowance'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="other_allowance[]" class="form-control form-control-sm text-end" 
                                               value="<?php echo $emp['other_allowance'] ?? 0; ?>" step="0.01" min="0">
                                    </td>
                                    <td>
                                        <input type="number" name="gross_salary[]" class="form-control form-control-sm text-end fw-bold gross-input" 
                                               value="<?php echo $emp['gross_salary'] ?? $grossCalculated; ?>" step="0.01" min="0">
                                    </td>
                                    <td class="text-center bg-light">
                                        <input type="checkbox" name="pf_applicable[]" value="<?php echo $emp['id']; ?>" 
                                               class="form-check-input pf-check" <?php echo ($emp['pf_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center bg-light">
                                        <input type="checkbox" name="esi_applicable[]" value="<?php echo $emp['id']; ?>" 
                                               class="form-check-input esi-check" <?php echo ($emp['esi_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center bg-light">
                                        <input type="checkbox" name="pt_applicable[]" value="<?php echo $emp['id']; ?>" 
                                               class="form-check-input pt-check" <?php echo ($emp['pt_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-3 bg-light border-top">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Yellow rows indicate employees without salary structure.
                                </small>
                            </div>
                            <div class="col-md-6 text-end">
                                <button type="submit" name="save_salaries" class="btn btn-success">
                                    <i class="bi bi-check-lg me-1"></i>Save All Salary Structures
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

// Select/Unselect All PF
document.getElementById('selectAllPF').addEventListener('change', function() {
    const checks = document.querySelectorAll('.pf-check');
    checks.forEach(check => check.checked = this.checked);
});

// Select/Unselect All ESI
document.getElementById('selectAllESI').addEventListener('change', function() {
    const checks = document.querySelectorAll('.esi-check');
    checks.forEach(check => check.checked = this.checked);
});

// Select/Unselect All PT
document.getElementById('selectAllPT').addEventListener('change', function() {
    const checks = document.querySelectorAll('.pt-check');
    checks.forEach(check => check.checked = this.checked);
});

// Auto-calculate gross when any component changes
document.querySelectorAll('.basic-input, input[name="da[]"], input[name="hra[]"], input[name="conveyance[]"], input[name="medical_allowance[]"], input[name="special_allowance[]"], input[name="other_allowance[]"]').forEach(function(input, index) {
    input.addEventListener('input', function() {
        const row = this.closest('tr');
        const basic = parseFloat(row.querySelector('input[name="basic_wage[]"]').value) || 0;
        const da = parseFloat(row.querySelector('input[name="da[]"]').value) || 0;
        const hra = parseFloat(row.querySelector('input[name="hra[]"]').value) || 0;
        const conv = parseFloat(row.querySelector('input[name="conveyance[]"]').value) || 0;
        const med = parseFloat(row.querySelector('input[name="medical_allowance[]"]').value) || 0;
        const special = parseFloat(row.querySelector('input[name="special_allowance[]"]').value) || 0;
        const other = parseFloat(row.querySelector('input[name="other_allowance[]"]').value) || 0;
        
        row.querySelector('.gross-input').value = (basic + da + hra + conv + med + special + other).toFixed(2);
    });
});
</script>
JS;
?>
