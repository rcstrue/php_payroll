<?php
/**
 * RCS HRMS Pro - Statutory Settings
 */

$pageTitle = 'Statutory Settings';

// Get current settings
$settings = [];
$result = $db->query("SELECT setting_key, setting_value FROM company_settings WHERE setting_key LIKE 'statutory_%'");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get PF rates
$pfRates = $db->query("SELECT * FROM pf_rates WHERE is_active = 1 ORDER BY effective_from DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get ESI rates
$esiRates = $db->query("SELECT * FROM esi_rates WHERE is_active = 1 ORDER BY effective_from DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get PT rates by state
$ptRates = $db->query(
    "SELECT ptr.*, s.state_name 
     FROM professional_tax_rates ptr 
     JOIN states s ON ptr.state_id = s.id 
     WHERE ptr.is_active = 1 
     ORDER BY s.state_name, ptr.effective_from DESC, ptr.salary_from ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Get LWF rates by state
$lwfRates = $db->query(
    "SELECT lr.*, s.state_name 
     FROM lwf_rates lr 
     JOIN states s ON lr.state_id = s.id 
     WHERE lr.is_active = 1 
     ORDER BY s.state_name, lr.effective_from DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    
    if ($section === 'company') {
        $statutorySettings = [
            'statutory_pf_establishment_id',
            'statutory_pf_establishment_name',
            'statutory_esi_establishment_id',
            'statutory_esi_establishment_name',
            'statutory_pt_registration_no',
            'statutory_lwf_registration_no',
            'statutory_pt_state',
            'statutory_lwf_state'
        ];
        
        foreach ($statutorySettings as $key) {
            $value = sanitize($_POST[$key] ?? '');
            
            $stmt = $db->prepare(
                "INSERT INTO company_settings (setting_key, setting_value) VALUES (:key, :value)
                 ON DUPLICATE KEY UPDATE setting_value = :value"
            );
            $stmt->execute(['key' => $key, 'value' => $value]);
        }
        
        setFlash('success', 'Statutory settings updated successfully!');
        redirect('index.php?page=settings/statutory');
    }
    
    if ($section === 'pf_rate') {
        $stmt = $db->prepare(
            "INSERT INTO pf_rates (employee_rate, employer_rate, eps_rate, edli_rate, admin_charges, effective_from, is_active)
             VALUES (:employee_rate, :employer_rate, :eps_rate, :edli_rate, :admin_charges, :effective_from, 1)"
        );
        $stmt->execute([
            'employee_rate' => floatval($_POST['pf_employee_rate']),
            'employer_rate' => floatval($_POST['pf_employer_rate']),
            'eps_rate' => floatval($_POST['eps_rate'] ?? 8.33),
            'edli_rate' => floatval($_POST['edli_rate'] ?? 0.5),
            'admin_charges' => floatval($_POST['pf_admin_charges'] ?? 0.5),
            'effective_from' => sanitize($_POST['pf_effective_from'])
        ]);
        setFlash('success', 'PF rate added successfully!');
        redirect('index.php?page=settings/statutory');
    }
    
    if ($section === 'esi_rate') {
        $stmt = $db->prepare(
            "INSERT INTO esi_rates (employee_rate, employer_rate, wage_ceiling, effective_from, is_active)
             VALUES (:employee_rate, :employer_rate, :wage_ceiling, :effective_from, 1)"
        );
        $stmt->execute([
            'employee_rate' => floatval($_POST['esi_employee_rate']),
            'employer_rate' => floatval($_POST['esi_employer_rate']),
            'wage_ceiling' => floatval($_POST['esi_wage_ceiling'] ?? 21000),
            'effective_from' => sanitize($_POST['esi_effective_from'])
        ]);
        setFlash('success', 'ESI rate added successfully!');
        redirect('index.php?page=settings/statutory');
    }
}

// Get states for dropdown
$states = $db->query("SELECT id, state_name FROM states ORDER BY state_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <!-- Company Statutory Details -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-building me-2"></i>Company Statutory Details</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="section" value="company">
                    
                    <h6 class="text-primary mb-3">Provident Fund (PF)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PF Establishment ID</label>
                            <input type="text" class="form-control" name="statutory_pf_establishment_id"
                                   value="<?php echo sanitize($settings['statutory_pf_establishment_id'] ?? ''); ?>"
                                   placeholder="e.g., MHBAN1234567000">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PF Establishment Name</label>
                            <input type="text" class="form-control" name="statutory_pf_establishment_name"
                                   value="<?php echo sanitize($settings['statutory_pf_establishment_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 mt-4">Employee State Insurance (ESI)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ESI Establishment ID</label>
                            <input type="text" class="form-control" name="statutory_esi_establishment_id"
                                   value="<?php echo sanitize($settings['statutory_esi_establishment_id'] ?? ''); ?>"
                                   placeholder="e.g., 1234567890">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ESI Establishment Name</label>
                            <input type="text" class="form-control" name="statutory_esi_establishment_name"
                                   value="<?php echo sanitize($settings['statutory_esi_establishment_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 mt-4">Professional Tax (PT)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PT Registration No.</label>
                            <input type="text" class="form-control" name="statutory_pt_registration_no"
                                   value="<?php echo sanitize($settings['statutory_pt_registration_no'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">PT State</label>
                            <select class="form-select" name="statutory_pt_state">
                                <option value="">Select State</option>
                                <?php foreach ($states as $s): ?>
                                <option value="<?php echo $s['id']; ?>" 
                                        <?php echo ($settings['statutory_pt_state'] ?? '') == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['state_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <h6 class="text-primary mb-3 mt-4">Labour Welfare Fund (LWF)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">LWF Registration No.</label>
                            <input type="text" class="form-control" name="statutory_lwf_registration_no"
                                   value="<?php echo sanitize($settings['statutory_lwf_registration_no'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">LWF State</label>
                            <select class="form-select" name="statutory_lwf_state">
                                <option value="">Select State</option>
                                <?php foreach ($states as $s): ?>
                                <option value="<?php echo $s['id']; ?>"
                                        <?php echo ($settings['statutory_lwf_state'] ?? '') == $s['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($s['state_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- PF Rates -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-piggy-bank me-2"></i>PF Rates</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPfRateModal">
                    <i class="bi bi-plus"></i> Add Rate
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Effective From</th>
                                <th>Employee %</th>
                                <th>Employer %</th>
                                <th>EPS %</th>
                                <th>Admin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pfRates as $r): ?>
                            <tr>
                                <td><?php echo formatDate($r['effective_from']); ?></td>
                                <td><?php echo $r['employee_rate']; ?>%</td>
                                <td><?php echo $r['employer_rate']; ?>%</td>
                                <td><?php echo $r['eps_rate']; ?>%</td>
                                <td><?php echo $r['admin_charges']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- ESI Rates -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-hospital me-2"></i>ESI Rates</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addEsiRateModal">
                    <i class="bi bi-plus"></i> Add Rate
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Effective From</th>
                                <th>Employee %</th>
                                <th>Employer %</th>
                                <th>Wage Ceiling</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($esiRates as $r): ?>
                            <tr>
                                <td><?php echo formatDate($r['effective_from']); ?></td>
                                <td><?php echo $r['employee_rate']; ?>%</td>
                                <td><?php echo $r['employer_rate']; ?>%</td>
                                <td><?php echo formatCurrency($r['wage_ceiling']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- PT Rates by State -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-receipt me-2"></i>Professional Tax Rates by State</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>State</th>
                                <th>Salary From</th>
                                <th>Salary To</th>
                                <th>Monthly PT</th>
                                <th>Effective From</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ptRates as $r): ?>
                            <tr>
                                <td><?php echo sanitize($r['state_name']); ?></td>
                                <td><?php echo formatCurrency($r['salary_from']); ?></td>
                                <td><?php echo $r['salary_to'] ? formatCurrency($r['salary_to']) : 'Unlimited'; ?></td>
                                <td><?php echo formatCurrency($r['pt_amount']); ?></td>
                                <td><?php echo formatDate($r['effective_from']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- LWF Rates by State -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-cash-stack me-2"></i>Labour Welfare Fund Rates by State</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>State</th>
                                <th>Employee Contribution</th>
                                <th>Employer Contribution</th>
                                <th>Payment Frequency</th>
                                <th>Effective From</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lwfRates as $r): ?>
                            <tr>
                                <td><?php echo sanitize($r['state_name']); ?></td>
                                <td><?php echo formatCurrency($r['employee_contribution']); ?></td>
                                <td><?php echo formatCurrency($r['employer_contribution']); ?></td>
                                <td><?php echo sanitize($r['payment_frequency'] ?? 'Monthly'); ?></td>
                                <td><?php echo formatDate($r['effective_from']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add PF Rate Modal -->
<div class="modal fade" id="addPfRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="section" value="pf_rate">
                <div class="modal-header">
                    <h5 class="modal-title">Add PF Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Effective From</label>
                        <input type="date" class="form-control" name="pf_effective_from" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Employee Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="pf_employee_rate" value="12" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Employer Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="pf_employer_rate" value="12" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">EPS Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="eps_rate" value="8.33">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Admin Charges (%)</label>
                            <input type="number" step="0.01" class="form-control" name="pf_admin_charges" value="0.5">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">EDLI Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="edli_rate" value="0.5">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add ESI Rate Modal -->
<div class="modal fade" id="addEsiRateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="section" value="esi_rate">
                <div class="modal-header">
                    <h5 class="modal-title">Add ESI Rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Effective From</label>
                        <input type="date" class="form-control" name="esi_effective_from" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Employee Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="esi_employee_rate" value="0.75" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Employer Rate (%)</label>
                            <input type="number" step="0.01" class="form-control" name="esi_employer_rate" value="3.25" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Wage Ceiling</label>
                        <input type="number" class="form-control" name="esi_wage_ceiling" value="21000">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rate</button>
                </div>
            </form>
        </div>
    </div>
</div>
