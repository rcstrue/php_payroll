<?php
/**
 * RCS HRMS Pro - Add/Edit Employee Page
 * Updated with file upload support
 */

$pageTitle = 'Add Employee';
$employeeData = null;
$isEdit = false;

// Define upload path constants
define('EMPLOYEE_PHOTO_UPLOAD_PATH', 'uploads/employees/photos/');
define('EMPLOYEE_DOC_UPLOAD_PATH', 'uploads/employees/documents/');

// Check if editing
if (isset($_GET['id'])) {
    $employeeData = $employee->getById($_GET['id']);
    if ($employeeData) {
        $pageTitle = 'Edit Employee';
        $isEdit = true;
    }
}

/**
 * Handle file uploads with single exit point
 * @param array $file The $_FILES array element
 * @param string $uploadDir Directory to upload to
 * @return array|null Result with 'path' or 'error' key, or null if no file
 */
function handleFileUpload($file, $uploadDir = 'uploads/employees/') {
    $response = null;
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return $response;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return $response;
    }
    
    // Validate file type
    if (!in_array($file['type'], $allowedTypes)) {
        $response = ['error' => 'Invalid file type. Only JPG, PNG, GIF, PDF allowed.'];
        return $response;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        $response = ['error' => 'File size must be less than 5MB.'];
        return $response;
    }
    
    // Create upload directory if not exists
    $fullPath = APP_ROOT . '/' . $uploadDir;
    if (!is_dir($fullPath)) {
        mkdir($fullPath, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], APP_ROOT . '/' . $filepath)) {
        $response = ['path' => $filepath];
    } else {
        $response = ['error' => 'Failed to upload file.'];
    }
    
    return $response;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => sanitize($_POST['full_name']),
        'father_name' => sanitize($_POST['father_name'] ?? ''),
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
        'client_id' => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
        'client_name' => sanitize($_POST['client_name'] ?? ''),
        'unit_id' => !empty($_POST['unit_id']) ? (int)$_POST['unit_id'] : null,
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
        'emergency_contact_number' => sanitize($_POST['emergency_contact_number'] ?? ''),
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
    
    // Handle file uploads
    if (!empty($_FILES['profile_pic']['name'])) {
        $result = handleFileUpload($_FILES['profile_pic'], EMPLOYEE_PHOTO_UPLOAD_PATH);
        if (isset($result['path'])) {
            $data['profile_pic_url'] = $result['path'];
        }
    }
    
    if (!empty($_FILES['aadhaar_front']['name'])) {
        $result = handleFileUpload($_FILES['aadhaar_front'], EMPLOYEE_DOC_UPLOAD_PATH);
        if (isset($result['path'])) {
            $data['aadhaar_front_url'] = $result['path'];
        }
    }
    
    if (!empty($_FILES['aadhaar_back']['name'])) {
        $result = handleFileUpload($_FILES['aadhaar_back'], EMPLOYEE_DOC_UPLOAD_PATH);
        if (isset($result['path'])) {
            $data['aadhaar_back_url'] = $result['path'];
        }
    }
    
    if (!empty($_FILES['bank_document']['name'])) {
        $result = handleFileUpload($_FILES['bank_document'], EMPLOYEE_DOC_UPLOAD_PATH);
        if (isset($result['path'])) {
            $data['bank_document_url'] = $result['path'];
        }
    }
    
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
$clients = [];
try {
    $stmt = $db->query("SELECT id, name FROM clients WHERE is_active = 1 ORDER BY name");
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet - log error silently
    error_log('Failed to load clients: ' . $e->getMessage());
}

$units = [];
try {
    $stmt = $db->query("SELECT id, name, client_id FROM units WHERE is_active = 1 ORDER BY name");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table may not exist yet - log error silently
    error_log('Failed to load units: ' . $e->getMessage());
}

// Indian states list
$statesList = [
    'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
    'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand',
    'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
    'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
    'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
    'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
    'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Puducherry',
    'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu', 'Lakshadweep'
];
?>

<div class="row">
    <div class="col-12">
        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            
            <!-- Document Upload Section -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-file-earmark-image me-2"></i>Documents & Photo</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="profile_pic" class="form-label">Profile Photo</label>
                            <?php if (!empty($employeeData['profile_pic_url'])): ?>
                            <div class="mb-2">
                                <img src="<?php echo sanitize($employeeData['profile_pic_url']); ?>" 
                                     alt="Profile photo" class="img-thumbnail" style="max-height: 100px;">
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF (max 5MB)</small>
                        </div>
                        <div class="col-md-3">
                            <label for="aadhaar_front" class="form-label">Aadhaar Front</label>
                            <?php if (!empty($employeeData['aadhaar_front_url'])): ?>
                            <div class="mb-2">
                                <a href="<?php echo sanitize($employeeData['aadhaar_front_url']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="aadhaar_front" name="aadhaar_front" accept="image/*,.pdf">
                            <small class="text-muted">JPG, PNG, PDF (max 5MB)</small>
                        </div>
                        <div class="col-md-3">
                            <label for="aadhaar_back" class="form-label">Aadhaar Back</label>
                            <?php if (!empty($employeeData['aadhaar_back_url'])): ?>
                            <div class="mb-2">
                                <a href="<?php echo sanitize($employeeData['aadhaar_back_url']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="aadhaar_back" name="aadhaar_back" accept="image/*,.pdf">
                            <small class="text-muted">JPG, PNG, PDF (max 5MB)</small>
                        </div>
                        <div class="col-md-3">
                            <label for="bank_document" class="form-label">Bank Passbook</label>
                            <?php if (!empty($employeeData['bank_document_url'])): ?>
                            <div class="mb-2">
                                <a href="<?php echo sanitize($employeeData['bank_document_url']); ?>" target="_blank" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-eye"></i> View
                                </a>
                            </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="bank_document" name="bank_document" accept="image/*,.pdf">
                            <small class="text-muted">JPG, PNG, PDF (max 5MB)</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Personal Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-person-badge me-2"></i>Personal Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="full_name" class="form-label required">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required
                                   value="<?php echo sanitize($employeeData['full_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="father_name" class="form-label">Father's Name</label>
                            <input type="text" class="form-control" id="father_name" name="father_name"
                                   value="<?php echo sanitize($employeeData['father_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="mobile_number" class="form-label required">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile_number" name="mobile_number" maxlength="10" required
                                   value="<?php echo sanitize($employeeData['mobile_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="alternate_mobile" class="form-label">Alternate Mobile</label>
                            <input type="tel" class="form-control" id="alternate_mobile" name="alternate_mobile" maxlength="10"
                                   value="<?php echo sanitize($employeeData['alternate_mobile'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo sanitize($employeeData['email'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">Select</option>
                                <option value="Male" <?php echo ($employeeData['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($employeeData['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($employeeData['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo $employeeData['date_of_birth'] ?? ''; ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="marital_status" class="form-label">Marital Status</label>
                            <select class="form-select" id="marital_status" name="marital_status">
                                <option value="">Select</option>
                                <option value="Single" <?php echo ($employeeData['marital_status'] ?? '') === 'Single' ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($employeeData['marital_status'] ?? '') === 'Married' ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo ($employeeData['marital_status'] ?? '') === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($employeeData['marital_status'] ?? '') === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="blood_group" class="form-label">Blood Group</label>
                            <select class="form-select" id="blood_group" name="blood_group">
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
                            <select class="form-select" name="state">
                                <option value="">Select State</option>
                                <?php foreach ($statesList as $state): ?>
                                <option value="<?php echo $state; ?>" <?php echo ($employeeData['state'] ?? '') === $state ? 'selected' : ''; ?>>
                                    <?php echo $state; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
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
                            <select class="form-select" name="client_id" id="client_id" onchange="filterUnits()">
                                <option value="">Select Client</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?php echo $c['id']; ?>" 
                                        data-name="<?php echo sanitize($c['name']); ?>"
                                        <?php echo ($employeeData['client_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($c['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="client_name" id="client_name" value="<?php echo sanitize($employeeData['client_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <select class="form-select" name="unit_id" id="unit_id" onchange="updateUnitName()">
                                <option value="">Select Unit</option>
                                <?php foreach ($units as $u): ?>
                                <option value="<?php echo $u['id']; ?>" 
                                        data-name="<?php echo sanitize($u['name']); ?>"
                                        data-client="<?php echo $u['client_id']; ?>"
                                        <?php echo ($employeeData['unit_id'] ?? '') == $u['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($u['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="unit_name" id="unit_name" value="<?php echo sanitize($employeeData['unit_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Designation</label>
                            <input type="text" class="form-control" name="designation"
                                   value="<?php echo sanitize($employeeData['designation'] ?? ''); ?>">
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
            <!-- NOTE: Checkboxes use !empty() to properly check saved values. Default to unchecked for new employees. -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="bi bi-shield-check me-2"></i>Statutory Applicability</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="pf_applicable" id="pf_applicable" value="1"
                                       <?php echo !empty($employeeData['pf_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pf_applicable">PF Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="esi_applicable" id="esi_applicable" value="1"
                                       <?php echo !empty($employeeData['esi_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="esi_applicable">ESI Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="pt_applicable" id="pt_applicable" value="1"
                                       <?php echo !empty($employeeData['pt_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="pt_applicable">PT Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="lwf_applicable" id="lwf_applicable" value="1"
                                       <?php echo !empty($employeeData['lwf_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="lwf_applicable">LWF Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="bonus_applicable" id="bonus_applicable" value="1"
                                       <?php echo !empty($employeeData['bonus_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="bonus_applicable">Bonus Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="gratuity_applicable" id="gratuity_applicable" value="1"
                                       <?php echo !empty($employeeData['gratuity_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="gratuity_applicable">Gratuity Applicable</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="overtime_applicable" id="overtime_applicable" value="1"
                                       <?php echo !empty($employeeData['overtime_applicable']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="overtime_applicable">Overtime Applicable</label>
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
                            <label class="form-label">Emergency Contact Number</label>
                            <input type="tel" class="form-control" name="emergency_contact_number" maxlength="10"
                                   value="<?php echo sanitize($employeeData['emergency_contact_number'] ?? ''); ?>">
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
                        <div class="col-md-3">
                            <label class="form-label">Nominee DOB</label>
                            <input type="date" class="form-control" name="nominee_dob"
                                   value="<?php echo $employeeData['nominee_dob'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Nominee Contact</label>
                            <input type="tel" class="form-control" name="nominee_contact" maxlength="10"
                                   value="<?php echo sanitize($employeeData['nominee_contact'] ?? ''); ?>">
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

// Filter units by client - Must be on window for onclick access
window.filterUnits = function() {
    const clientId = $('#client_id').val();
    const clientOption = $('#client_id option:selected');
    const clientName = clientOption.data('name') || '';
    
    $('#client_name').val(clientName);
    
    // Filter unit dropdown
    $('#unit_id option').each(function() {
        const unitClient = $(this).data('client');
        if (!clientId || unitClient == clientId || !$(this).val()) {
            $(this).show();
        } else {
            $(this).hide();
        }
    });
    
    // Reset unit selection if not matching
    const currentUnit = $('#unit_id option:selected');
    if (currentUnit.data('client') != clientId) {
        $('#unit_id').val('');
        $('#unit_name').val('');
    }
};

window.updateUnitName = function() {
    const unitOption = $('#unit_id option:selected');
    const unitName = unitOption.data('name') || '';
    $('#unit_name').val(unitName);
};

// Initialize on page load
$(document).ready(function() {
    filterUnits();
});
JS;
?>
