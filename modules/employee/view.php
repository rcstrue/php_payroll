<?php
/**
 * RCS HRMS Pro - Employee View Page
 * Updated for new database schema
 */

$pageTitle = 'Employee Details';

// Get employee ID
$employeeId = isset($_GET['id']) ? $_GET['id'] : '';

if (!$employeeId) {
    setFlash('error', 'Employee ID is required');
    redirect('index.php?page=employee/list');
}

// Get employee details using the Employee class
$emp = $employee->getById($employeeId);

if (!$emp) {
    setFlash('error', 'Employee not found');
    redirect('index.php?page=employee/list');
}

// Get employee documents
$documents = $emp['documents'] ?? [];

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $docType = sanitize($_POST['document_type']);
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/employee_documents/' . $emp['employee_code'] . '/';
        
        if (!is_dir(APP_ROOT . '/' . $uploadDir)) {
            mkdir(APP_ROOT . '/' . $uploadDir, 0755, true);
        }
        
        $fileName = $docType . '_' . time() . '_' . basename($_FILES['document_file']['name']);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], APP_ROOT . '/' . $filePath)) {
            // Insert document record
            $stmt = $db->prepare("INSERT INTO employee_documents (employee_id, document_type, document_name, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), file_size = VALUES(file_size), file_type = VALUES(file_type), uploaded_by = VALUES(uploaded_by)");
            $stmt->execute([$employeeId, $docType, $fileName, $filePath, $_FILES['document_file']['size'], $_FILES['document_file']['type'], $_SESSION['user_id']]);
            
            setFlash('success', 'Document uploaded successfully!');
            redirect('index.php?page=employee/view&id=' . $employeeId);
        } else {
            setFlash('error', 'Failed to upload document');
        }
    }
}

// Calculate age
$age = '';
if (!empty($emp['date_of_birth'])) {
    $dob = new DateTime($emp['date_of_birth']);
    $now = new DateTime();
    $age = $dob->diff($now)->y;
}

// Calculate tenure
$tenure = '';
if (!empty($emp['date_of_joining'])) {
    $doj = new DateTime($emp['date_of_joining']);
    $endDate = !empty($emp['date_of_leaving']) ? new DateTime($emp['date_of_leaving']) : new DateTime();
    $diff = $doj->diff($endDate);
    $tenure = $diff->y . ' years, ' . $diff->m . ' months';
}

// Document types
$documentTypes = [
    'Aadhaar Card' => 'Aadhaar Card',
    'PAN Card' => 'PAN Card',
    'Bank Passbook' => 'Bank Passbook',
    'Photo' => 'Photo',
    'Police Verification' => 'Police Verification',
    'Education Certificate' => 'Education Certificate',
    'Experience Certificate' => 'Experience Certificate',
    'Medical Certificate' => 'Medical Certificate',
    'Other' => 'Other Document'
];
?>

<div class="row">
    <!-- Sidebar Quick Actions -->
    <div class="col-lg-3 col-md-4 mb-3">
        <!-- Employee Photo & Quick Info -->
        <div class="card mb-3">
            <div class="card-body text-center">
                <?php if (!empty($emp['profile_pic_url']) && file_exists($emp['profile_pic_url'])): ?>
                <img src="<?php echo sanitize($emp['profile_pic_url']); ?>" alt="Photo" class="rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                <?php else: ?>
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 100px; height: 100px; font-size: 36px;">
                    <?php echo substr($emp['full_name'] ?? 'U', 0, 1); ?>
                </div>
                <?php endif; ?>
                <h5 class="mb-1"><?php echo sanitize($emp['full_name'] ?? '-'); ?></h5>
                <p class="text-muted mb-2"><code><?php echo sanitize($emp['employee_code']); ?></code></p>
                <?php 
                $statusClass = 'secondary';
                $statusText = $emp['status'] ?? 'Unknown';
                if ($statusText === 'approved') {
                    $statusClass = 'success';
                    $statusText = 'Active';
                } elseif (strpos($statusText, 'pending') !== false) {
                    $statusClass = 'warning';
                    $statusText = 'Pending';
                } elseif ($statusText === 'inactive' || $statusText === 'terminated') {
                    $statusClass = 'danger';
                }
                ?>
                <span class="badge bg-<?php echo $statusClass; ?> fs-6"><?php echo sanitize($statusText); ?></span>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h6>
            </div>
            <div class="card-body p-2">
                <div class="d-grid gap-2">
                    <a href="index.php?page=employee/add&id=<?php echo $employeeId; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-pencil me-2"></i>Edit Employee
                    </a>
                    <a href="index.php?page=forms/appointment&id=<?php echo $employeeId; ?>" class="btn btn-outline-success btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-text me-2"></i>Appointment Letter
                    </a>
                    <?php if ($emp['status'] === 'approved'): ?>
                    <a href="index.php?page=payroll/payslips&emp=<?php echo $emp['employee_code']; ?>" class="btn btn-outline-info btn-sm">
                        <i class="bi bi-receipt me-2"></i>View Payslips
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Salary Summary -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-cash-stack me-2"></i>Salary Info</h6>
            </div>
            <div class="card-body p-2">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <td class="text-muted">Gross Salary</td>
                        <td class="text-end fw-bold"><?php echo formatCurrency($emp['gross_salary'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Basic</td>
                        <td class="text-end"><?php echo formatCurrency($emp['basic_wage'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">DA</td>
                        <td class="text-end"><?php echo formatCurrency($emp['da'] ?? 0); ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">HRA</td>
                        <td class="text-end"><?php echo formatCurrency($emp['hra'] ?? 0); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="col-lg-9 col-md-8">
        <!-- Personal Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person me-2"></i>Personal Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Full Name</label>
                        <div class="fw-medium"><?php echo sanitize($emp['full_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Gender</label>
                        <div><?php echo sanitize($emp['gender'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Date of Birth</label>
                        <div><?php echo formatDate($emp['date_of_birth']); ?>
                            <?php if ($age): ?><span class="text-muted">(<?php echo $age; ?> years)</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Marital Status</label>
                        <div><?php echo sanitize($emp['marital_status'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Blood Group</label>
                        <div>
                            <?php if (!empty($emp['blood_group'])): ?>
                            <span class="badge bg-danger"><?php echo sanitize($emp['blood_group']); ?></span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-telephone me-2"></i>Contact Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Mobile Number</label>
                        <div>
                            <?php if (!empty($emp['mobile_number'])): ?>
                            <a href="tel:<?php echo sanitize($emp['mobile_number']); ?>"><?php echo sanitize($emp['mobile_number']); ?></a>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Alternate Mobile</label>
                        <div><?php echo sanitize($emp['alternate_mobile'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Email</label>
                        <div>
                            <?php if (!empty($emp['email'])): ?>
                            <a href="mailto:<?php echo sanitize($emp['email']); ?>"><?php echo sanitize($emp['email']); ?></a>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Address Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-geo-alt me-2"></i>Address Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label class="text-muted small">Address</label>
                        <div>
                            <?php 
                            $addr = [];
                            if (!empty($emp['address'])) $addr[] = sanitize($emp['address']);
                            if (!empty($emp['district'])) $addr[] = sanitize($emp['district']);
                            if (!empty($emp['state'])) $addr[] = sanitize($emp['state']);
                            if (!empty($emp['pin_code'])) $addr[] = sanitize($emp['pin_code']);
                            echo !empty($addr) ? implode(', ', $addr) : '-';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Identity Documents -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-card-heading me-2"></i>Identity Documents</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Aadhaar Number</label>
                        <div>
                            <?php if (!empty($emp['aadhaar_number'])): ?>
                            <code><?php echo maskAadhaar($emp['aadhaar_number']); ?></code>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">UAN Number</label>
                        <div>
                            <?php if (!empty($emp['uan_number'])): ?>
                            <code><?php echo sanitize($emp['uan_number']); ?></code>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">ESIC Number</label>
                        <div>
                            <?php if (!empty($emp['esic_number'])): ?>
                            <code><?php echo sanitize($emp['esic_number']); ?></code>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Bank Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-bank me-2"></i>Bank Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Bank Name</label>
                        <div><?php echo sanitize($emp['bank_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Account Number</label>
                        <div>
                            <?php if (!empty($emp['account_number'])): ?>
                            <code><?php echo sanitize($emp['account_number']); ?></code>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">IFSC Code</label>
                        <div>
                            <?php if (!empty($emp['ifsc_code'])): ?>
                            <code><?php echo sanitize($emp['ifsc_code']); ?></code>
                            <?php else: ?>-<?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Account Holder Name</label>
                        <div><?php echo sanitize($emp['account_holder_name'] ?? '-'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Employment Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-briefcase me-2"></i>Employment Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Client</label>
                        <div><?php echo sanitize($emp['client_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Unit</label>
                        <div><?php echo sanitize($emp['unit_name'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Designation</label>
                        <div><?php echo sanitize($emp['designation'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Department</label>
                        <div><?php echo sanitize($emp['department'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Worker Category</label>
                        <div><span class="badge bg-info-soft"><?php echo sanitize($emp['worker_category'] ?? '-'); ?></span></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Employment Type</label>
                        <div><?php echo sanitize($emp['employment_type'] ?? '-'); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Date of Joining</label>
                        <div><?php echo formatDate($emp['date_of_joining']); ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Tenure</label>
                        <div><?php echo $tenure ?: '-'; ?></div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="text-muted small">Probation Period</label>
                        <div><?php echo !empty($emp['probation_period']) ? $emp['probation_period'] . ' months' : '-'; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Wage Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-currency-rupee me-2"></i>Wage Details</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Basic</th>
                                <th>DA</th>
                                <th>HRA</th>
                                <th>Conveyance</th>
                                <th>Medical</th>
                                <th>Special</th>
                                <th>Other</th>
                                <th>Gross</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?php echo formatCurrency($emp['basic_wage'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['da'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['hra'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['conveyance'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['medical_allowance'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['special_allowance'] ?? 0); ?></td>
                                <td><?php echo formatCurrency($emp['other_allowance'] ?? 0); ?></td>
                                <td class="fw-bold"><?php echo formatCurrency($emp['gross_salary'] ?? 0); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Statutory Details -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-shield-check me-2"></i>Statutory Applicability</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>PF</th>
                                    <th>ESI</th>
                                    <th>PT</th>
                                    <th>LWF</th>
                                    <th>Bonus</th>
                                    <th>Gratuity</th>
                                    <th>Overtime</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo !empty($emp['pf_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['esi_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['pt_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['lwf_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['bonus_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['gratuity_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                    <td><?php echo !empty($emp['overtime_applicable']) ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Nominee & Emergency Contact -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Nominee & Emergency Contact</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Emergency Contact</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Name:</td>
                                <td><?php echo sanitize($emp['emergency_contact_name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Relation:</td>
                                <td><?php echo sanitize($emp['emergency_contact_relation'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-primary">Nominee</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Name:</td>
                                <td><?php echo sanitize($emp['nominee_name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Relation:</td>
                                <td><?php echo sanitize($emp['nominee_relationship'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Documents Section -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="bi bi-folder me-2"></i>Documents</h6>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                    <i class="bi bi-upload me-1"></i>Upload Document
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Document Type</th>
                                <th>File</th>
                                <th>Uploaded On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No documents uploaded</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?php echo sanitize($doc['document_type']); ?></td>
                                <td><?php echo sanitize($doc['document_name'] ?? $doc['file_path']); ?></td>
                                <td><?php echo formatDateTime($doc['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo sanitize($doc['file_path']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
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

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="upload_document" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <select class="form-select" name="document_type" required>
                            <option value="">Select Type</option>
                            <?php foreach ($documentTypes as $type): ?>
                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="document_file" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
