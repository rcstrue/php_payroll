<?php
/**
 * RCS HRMS Pro - Add/Edit Employee Page
 * Updated for new database schema
 */

$pageTitle = 'Add Employee';
$employeeData = null;
$isEdit = false;

// Check if editing
if (isset($_GET['id'])) {
    $employeeData = $employee->getById($_GET['id']);
    if ($employeeData) {
        $pageTitle = 'Edit Employee';
        $isEdit = true;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => sanitize($_POST['full_name']),
        'mobile_number' => sanitize($_POST['mobile_number'] ?? ''),
        'alternate_mobile' => sanitize($_POST['alternate_mobile'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'gender' => sanitize($_POST['gender'] ?? ''),
        'date_of_birth' => !empty($_POST['date_of_birth']) ? date('Y-m-d', strtotime($_POST['date_of_birth'])) : null,
        'marital_status' => sanitize($_POST['marital_status'] ?? ''),
        'blood_group' => sanitize($_POST['blood_group'] ?? ''),
        'aadhaar_number' => sanitize($_POST['aadhaar_number'] ?? ''),
        'uan_number' => sanitize($_POST['uan_number'] ?? ''),
        'esic_number' => sanitize($_POST['esic_number'] ?? ''),
        'address' => sanitize($_POST['address'] ?? ''),
        'pin_code' => sanitize($_POST['pin_code'] ?? ''),
        'state' => sanitize($_POST['state'] ?? ''),
        'district' => sanitize($_POST['district'] ?? ''),
        'bank_name' => sanitize($_POST['bank_name'] ?? ''),
        'account_number' => sanitize($_POST['account_number'] ?? ''),
        'ifsc_code' => sanitize($_POST['ifsc_code'] ?? ''),
        'account_holder_name' => sanitize($_POST['account_holder_name'] ?? ''),
        'client_name' => sanitize($_POST['client_name'] ?? ''),
        'unit_name' => sanitize($_POST['unit_name'] ?? ''),
        'designation' => sanitize($_POST['designation'] ?? ''),
        'department' => sanitize($_POST['department'] ?? ''),
        'worker_category' => sanitize($_POST['worker_category'] ?? 'Unskilled'),
        'employment_type' => sanitize($_POST['employment_type'] ?? 'Contract'),
        'date_of_joining' => !empty($_POST['date_of_joining']) ? date('Y-m-d', strtotime($_POST['date_of_joining'])) : null,
        'probation_period' => (int)($_POST['probation_period'] ?? 3),
        'nominee_name' => sanitize($_POST['nominee_name'] ?? ''),
        'nominee_relationship' => sanitize($_POST['nominee_relationship'] ?? ''),
        'nominee_dob' => !empty($_POST['nominee_dob']) ? date('Y-m-d', strtotime($_POST['nominee_dob'])) : null,
        'nominee_contact' => sanitize($_POST['nominee_contact'] ?? ''),
        'emergency_contact_name' => sanitize($_POST['emergency_contact_name'] ?? ''),
        'emergency_contact_relation' => sanitize($_POST['emergency_contact_relation'] ?? ''),
        'status' => 'pending_hr_verification',
        // Salary structure fields
        'basic_wage' => floatval($_POST['basic_wage'] ?? 0),
        'da' => floatval($_POST['da'] ?? 0),
        'hra' => floatval($_POST['hra'] ?? 0),
        'conveyance' => floatval($_POST['conveyance'] ?? 0),
        'medical_allowance' => floatval($_POST['medical_allowance'] ?? 0),
        'special_allowance' => floatval($_POST['special_allowance'] ?? 0),
        'other_allowance' => floatval($_POST['other_allowance'] ?? 0),
        'gross_salary' => floatval($_POST['gross_salary'] ?? 0),
        'pf_applicable' => isset($_POST['pf_applicable']) ? 1 : 0,
        'esi_applicable' => isset($_POST['esi_applicable']) ? 1 : 0,
        'pt_applicable' => isset($_POST['pt_applicable']) ? 1 : 0,
        'lwf_applicable' => isset($_POST['lwf_applicable']) ? 1 : 0,
        'bonus_applicable' => isset($_POST['bonus_applicable']) ? 1 : 0,
        'gratuity_applicable' => isset($_POST['gratuity_applicable']) ? 1 : 0,
        'overtime_applicable' => isset($_POST['overtime_applicable']) ? 1 : 0,
    ];
    
    // For edit, also set status if provided
    if ($isEdit && !empty($_POST['status'])) {
        $data['status'] = sanitize($_POST['status']);
    }
    
    if ($isEdit) {
        $result = $employee->update($employeeData['id'], $data);
    } else {
        $result = $employee->create($data);
    }
    
    if (isset($result['success']) && $result['success']) {
        setFlash('success', $isEdit ? 'Employee updated successfully!' : 'Employee added successfully!');
        redirect('index.php?page=employee/view&id=' . ($isEdit ? $employeeData['id'] : $result['employee_id']));
    } else {
        setFlash('error', $result['message'] ?? 'Failed to save employee');
    }
}

// Get dropdown data
$stmt = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name");
$units = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->query("SELECT id, name FROM designations ORDER BY name");
$designations = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-12">
        <form method="POST" class="needs-validation" novalidate>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge me-2"></i>Personal Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required
                                   value="<?php echo sanitize($employeeData['full_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label required">Mobile Number</label>
                            <input type="tel" class="form-control" name="mobile_number" maxlength="10" required
                                   value="<?php echo sanitize($employeeData['mobile_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Alternate Mobile</label>
                            <input type="tel" class="form-control" name="alternate_mobile" maxlength="10"
                                   value="<?php echo sanitize($employeeData['alternate_mobile'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email"
                                   value="<?php echo sanitize($employeeData['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <select class="form-select" name="gender">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($employeeData['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($employeeData['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($employeeData['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth"
                                   value="<?php echo $employeeData['date_of_birth'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Marital Status</label>
                            <select class="form-select" name="marital_status">
                                <option value="">Select</option>
                                <option value="Single" <?php echo ($employeeData['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($employeeData['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo ($employeeData['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($employeeData['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Blood Group</label>
                            <select class="form-select" name="blood_group">
                                <option value="">Select</option>
                                <option value="A+" <?php echo ($employeeData['blood_group'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                <option value="A-" <?php echo ($employeeData['blood_group'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                <option value="B+" <?php echo ($employeeData['blood_group'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                <option value="B-" <?php echo ($employeeData['blood_group'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                <option value="AB+" <?php echo ($employeeData['blood_group'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                <option value="AB-" <?php echo ($employeeData['blood_group'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                <option value="O+" <?php echo ($employeeData['blood_group'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                <option value="O-" <?php echo ($employeeData['blood_group'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Aadhaar Number</label>
                            <input type="text" class="form-control" name="aadhaar_number" maxlength="12"
                                   value="<?php echo sanitize($employeeData['aadhaar_number'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-geo-alt me-2"></i>Address Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="2"><?php echo sanitize($employeeData['address'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">District</label>
                            <input type="text" class="form-control" name="district"
                                   value="<?php echo sanitize($employeeData['district'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="state"
                                   value="<?php echo sanitize($employeeData['state'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Pin Code</label>
                            <input type="text" class="form-control" name="pin_code" maxlength="6"
                                   value="<?php echo sanitize($employeeData['pin_code'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Identity & Bank Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-credit-card me-2"></i>Identity & Bank Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">UAN Number</label>
                            <input type="text" class="form-control" name="uan_number" maxlength="12"
                                   value="<?php echo sanitize($employeeData['uan_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">ESIC Number</label>
                            <input type="text" class="form-control" name="esic_number"
                                   value="<?php echo sanitize($employeeData['esic_number'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bank_name"
                                   value="<?php echo sanitize($employeeData['bank_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="account_number"
                                   value="<?php echo sanitize($employeeData['account_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" name="ifsc_code" maxlength="11"
                                   value="<?php echo sanitize($employeeData['ifsc_code'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" class="form-control" name="account_holder_name"
                                   value="<?php echo sanitize($employeeData['account_holder_name'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Employment Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-briefcase me-2"></i>Employment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Client</label>
                            <select class="form-select" name="client_name" id="client_name">
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?php echo sanitize($c['name']); ?>" <?php echo ($employeeData['client_name'] ?? '') == $c['name'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select" name="unit_name" id="unit_name">
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?php echo sanitize($u['name']); ?>" <?php echo ($employeeData['unit_name'] ?? '') == $u['name'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($u['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Designation</label>
                            <input type="text" class="form-control" name="designation" list="designation_list"
                                   value="<?php echo sanitize($employeeData['designation'] ?? ''); ?>">
                            <datalist id="designation_list">
                                <?php foreach ($designations as $d): ?>
                                <option value="<?php echo sanitize($d['name']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department"
                                   value="<?php echo sanitize($employeeData['department'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">Worker Category</label>
                            <select class="form-select" name="worker_category">
                                <option value="Unskilled" <?php echo ($employeeData['worker_category'] ?? '') === 'Unskilled' ? 'selected' : ''; ?>>Unskilled</option>
                                <option value="Semi-Skilled" <?php echo ($employeeData['worker_category'] ?? '') === 'Semi-Skilled' ? 'selected' : ''; ?>>Semi-Skilled</option>
                                <option value="Skilled" <?php echo ($employeeData['worker_category'] ?? '') === 'Skilled' ? 'selected' : ''; ?>>Skilled</option>
                                <option value="Supervisor" <?php echo ($employeeData['worker_category'] ?? '') === 'Supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                                <option value="Manager" <?php echo ($employeeData['worker_category'] ?? '') === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Employment Type</label>
                            <select class="form-select" name="employment_type">
                                <option value="Contract" <?php echo ($employeeData['employment_type'] ?? '') === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="Permanent" <?php echo ($employeeData['employment_type'] ?? '') === 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                                <option value="Temporary" <?php echo ($employeeData['employment_type'] ?? '') === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                <option value="Daily Wages" <?php echo ($employeeData['employment_type'] ?? '') === 'Daily Wages' ? 'selected' : ''; ?>>Daily Wages</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" class="form-control" name="date_of_joining"
                                   value="<?php echo $employeeData['date_of_joining'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Probation Period (Months)</label>
                            <input type="number" class="form-control" name="probation_period" min="0" max="12"
                                   value="<?php echo $employeeData['probation_period'] ?? 3; ?>">
                        </div>
                        
                        <?php if ($isEdit): ?>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="approved" <?php echo ($employeeData['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($employeeData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="terminated" <?php echo ($employeeData['status'] ?? '') === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Salary Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-currency-rupee me-2"></i>Salary Details</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Basic Wage</label>
                            <input type="number" class="form-control" name="basic_wage" step="0.01" id="basic_wage"
                                   value="<?php echo $employeeData['basic_wage'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">DA</label>
                            <input type="number" class="form-control" name="da" step="0.01" id="da"
                                   value="<?php echo $employeeData['da'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">HRA</label>
                            <input type="number" class="form-control" name="hra" step="0.01" id="hra"
                                   value="<?php echo $employeeData['hra'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Conveyance</label>
                            <input type="number" class="form-control" name="conveyance" step="0.01"
                                   value="<?php echo $employeeData['conveyance'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Medical</label>
                            <input type="number" class="form-control" name="medical_allowance" step="0.01"
                                   value="<?php echo $employeeData['medical_allowance'] ?? ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Gross Salary</label>
                            <input type="number" class="form-control" name="gross_salary" step="0.01" id="gross_salary"
                                   value="<?php echo $employeeData['gross_salary'] ?? ''; ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statutory Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Statutory Applicability</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="pf_applicable" id="pf_applicable"
                                       <?php echo ($employeeData['pf_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pf_applicable">PF Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="esi_applicable" id="esi_applicable"
                                       <?php echo ($employeeData['esi_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="esi_applicable">ESI Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="pt_applicable"
                                       <?php echo ($employeeData['pt_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">PT Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="lwf_applicable"
                                       <?php echo ($employeeData['lwf_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">LWF Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="bonus_applicable"
                                       <?php echo ($employeeData['bonus_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Bonus Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="gratuity_applicable"
                                       <?php echo ($employeeData['gratuity_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Gratuity Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="overtime_applicable"
                                       <?php echo ($employeeData['overtime_applicable'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Overtime Applicable</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-telephone me-2"></i>Emergency Contact & Nominee</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" class="form-control" name="emergency_contact_name"
                                   value="<?php echo sanitize($employeeData['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Relation</label>
                            <input type="text" class="form-control" name="emergency_contact_relation"
                                   value="<?php echo sanitize($employeeData['emergency_contact_relation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee Name</label>
                            <input type="text" class="form-control" name="nominee_name"
                                   value="<?php echo sanitize($employeeData['nominee_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee Relation</label>
                            <input type="text" class="form-control" name="nominee_relationship"
                                   value="<?php echo sanitize($employeeData['nominee_relationship'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="card mb-4">
                <div class="card-body text-end">
                    <a href="index.php?page=employee/list" class="btn btn-secondary">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i><?php echo $isEdit ? 'Update Employee' : 'Add Employee'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$inlineJS = <<<'JS'
// Calculate gross salary
function calculateGross() {
    const basic = parseFloat($('#basic_wage').val()) || 0;
    const da = parseFloat($('#da').val()) || 0;
    const hra = parseFloat($('#hra').val()) || 0;
    const conveyance = parseFloat($('input[name="conveyance"]').val()) || 0;
    const medical = parseFloat($('input[name="medical_allowance"]').val()) || 0;
    const special = parseFloat($('input[name="special_allowance"]').val()) || 0;
    const other = parseFloat($('input[name="other_allowance"]').val()) || 0;
    
    $('#gross_salary').val(basic + da + hra + conveyance + medical + special + other);
}

$('#basic_wage, #da, #hra, input[name="conveyance"], input[name="medical_allowance"], input[name="special_allowance"], input[name="other_allowance"]').on('input', calculateGross);
JS;
?>
