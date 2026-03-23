<?php
/**
 * RCS HRMS Pro - Employee Documents Management
 * View and manage employee documents (uploaded files)
 */

$pageTitle = 'Employee Documents';

// Get filters
$filters = [
    'employee_id' => !empty($_GET['employee_id']) ? (int)$_GET['employee_id'] : null,
    'document_type' => sanitize($_GET['document_type'] ?? ''),
    'search' => sanitize($_GET['search'] ?? '')
];

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $docId = (int)$_GET['delete'];
    
    // Get document info before deleting
    $docStmt = $db->prepare("SELECT d.*, e.full_name FROM employee_documents d 
                             JOIN employees e ON d.employee_id = e.id 
                             WHERE d.id = ?");
    $docStmt->execute([$docId]);
    $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($doc) {
        // Delete file from filesystem
        $filePath = APP_ROOT . '/' . $doc['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Delete from database
        $deleteStmt = $db->prepare("DELETE FROM employee_documents WHERE id = ?");
        $deleteStmt->execute([$docId]);
        
        logActivity('document_deleted', "Deleted {$doc['document_type']} for {$doc['full_name']}");
        setFlash('success', 'Document deleted successfully');
    }
    
    redirect('index.php?page=employee/documents');
}

// Get documents with pagination
$page = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = "WHERE 1=1";
$params = [];

if (!empty($filters['employee_id'])) {
    $where .= " AND d.employee_id = ?";
    $params[] = $filters['employee_id'];
}

if (!empty($filters['document_type'])) {
    $where .= " AND d.document_type = ?";
    $params[] = $filters['document_type'];
}

if (!empty($filters['search'])) {
    $where .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ?)";
    $params[] = "%{$filters['search']}%";
    $params[] = "%{$filters['search']}%";
}

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM employee_documents d 
                           JOIN employees e ON d.employee_id = e.id $where");
$countStmt->execute($params);
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $perPage);

// Get documents - use id for ordering since uploaded_at may not exist
$docStmt = $db->prepare("SELECT d.*, e.full_name, e.employee_code 
                          FROM employee_documents d 
                          JOIN employees e ON d.employee_id = e.id 
                          $where 
                          ORDER BY d.id DESC 
                          LIMIT $perPage OFFSET $offset");
$docStmt->execute($params);
$documents = $docStmt->fetchAll(PDO::FETCH_ASSOC);

// Get employees for filter
$empStmt = $db->query("SELECT id, employee_code, full_name FROM employees WHERE status = 'approved' ORDER BY full_name");
$employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

// Document types
$documentTypes = [
    'aadhaar_card' => 'Aadhaar Card',
    'pan_card' => 'PAN Card',
    'bank_passbook' => 'Bank Passbook',
    'photo' => 'Photograph',
    'id_proof' => 'ID Proof',
    'address_proof' => 'Address Proof',
    'education_certificate' => 'Education Certificate',
    'experience_certificate' => 'Experience Certificate',
    'other' => 'Other'
];
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-file-earmark-text me-2"></i>Employee Documents
                </h5>
            </div>
            
            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" class="row g-3">
                    <input type="hidden" name="page" value="employee/documents">
                    
                    <div class="col-md-3">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by name or code..." 
                               value="<?php echo htmlspecialchars($filters['search']); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>" <?php echo $filters['employee_id'] == $emp['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($emp['employee_code'] . ' - ' . $emp['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <select class="form-select" name="document_type">
                            <option value="">All Types</option>
                            <?php foreach ($documentTypes as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $filters['document_type'] === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Search
                        </button>
                        <a href="index.php?page=employee/documents" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
            
            <!-- Documents Table -->
            <div class="card-body p-0">
                <?php if (empty($documents)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-x fs-1 text-muted"></i>
                    <h5 class="mt-3 text-muted">No documents found</h5>
                    <p class="text-muted">Upload documents from the employee profile page.</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Document Type</th>
                                <th>File Name</th>
                                <th>Size</th>
                                <th>Uploaded On</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td>
                                    <a href="index.php?page=employee/view&id=<?php echo $doc['employee_id']; ?>">
                                        <span class="fw-medium"><?php echo sanitize($doc['full_name']); ?></span>
                                        <br><small class="text-muted"><?php echo sanitize($doc['employee_code']); ?></small>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $typeLabel = $documentTypes[$doc['document_type']] ?? ucfirst(str_replace('_', ' ', $doc['document_type']));
                                    ?>
                                    <span class="badge bg-info-soft"><?php echo sanitize($typeLabel); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                    $icon = 'file-earmark';
                                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'file-earmark-image';
                                    elseif ($ext === 'pdf') $icon = 'file-earmark-pdf';
                                    elseif (in_array($ext, ['doc', 'docx'])) $icon = 'file-earmark-word';
                                    ?>
                                    <i class="bi bi-<?php echo $icon; ?> me-1"></i>
                                    <?php echo sanitize($doc['file_name']); ?>
                                </td>
                                <td>
                                    <?php 
                                    $size = $doc['file_size'] ?? 0;
                                    if ($size < 1024) echo $size . ' B';
                                    elseif ($size < 1048576) echo round($size / 1024, 1) . ' KB';
                                    else echo round($size / 1048576, 1) . ' MB';
                                    ?>
                                </td>
                                <td><?php echo formatDate($doc['created_at'] ?? '', 'd M Y H:i'); ?></td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (file_exists(APP_ROOT . '/' . $doc['file_path'])): ?>
                                        <a href="<?php echo BASE_URL . '/' . $doc['file_path']; ?>" 
                                           class="btn btn-outline-primary" target="_blank" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?php echo BASE_URL . '/' . $doc['file_path']; ?>" 
                                           class="btn btn-outline-success" download title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-danger" 
                                                onclick="deleteDocument(<?php echo $doc['id']; ?>)" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Showing <?php echo number_format(($page - 1) * $perPage + 1); ?> to 
                        <?php echo number_format(min($page * $perPage, $total)); ?> of 
                        <?php echo number_format($total); ?> documents
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=employee/documents&pg=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=employee/documents&pg=<?php echo $i; ?>&<?php echo http_build_query(array_filter($filters)); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=employee/documents&pg=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter($filters)); ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deleteDocument(id) {
    if (confirm('Are you sure you want to delete this document?\n\nThis action cannot be undone.')) {
        window.location.href = 'index.php?page=employee/documents&delete=' + id;
    }
}
</script>
