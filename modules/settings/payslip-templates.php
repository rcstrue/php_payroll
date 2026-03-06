<?php
/**
 * RCS HRMS Pro - Payslip Templates Management
 */

$pageTitle = 'Payslip Templates';

// Get templates
$templates = $db->query(
    "SELECT * FROM payslip_templates ORDER BY is_default DESC, template_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $templateId = $_POST['template_id'] ?? null;
        $data = [
            'template_name' => sanitize($_POST['template_name']),
            'template_type' => sanitize($_POST['template_type']),
            'header_content' => $_POST['header_content'] ?? '',
            'footer_content' => $_POST['footer_content'] ?? '',
            'show_earnings' => isset($_POST['show_earnings']) ? 1 : 0,
            'show_deductions' => isset($_POST['show_deductions']) ? 1 : 0,
            'show_statutory' => isset($_POST['show_statutory']) ? 1 : 0,
            'show_leave_balance' => isset($_POST['show_leave_balance']) ? 1 : 0,
            'show_bank_details' => isset($_POST['show_bank_details']) ? 1 : 0,
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'is_default' => isset($_POST['is_default']) ? 1 : 0
        ];
        
        if ($action === 'add') {
            $stmt = $db->prepare(
                "INSERT INTO payslip_templates (template_name, template_type, header_content, footer_content,
                 show_earnings, show_deductions, show_statutory, show_leave_balance, show_bank_details, is_active, is_default)
                 VALUES (:template_name, :template_type, :header_content, :footer_content,
                 :show_earnings, :show_deductions, :show_statutory, :show_leave_balance, :show_bank_details, :is_active, :is_default)"
            );
            $stmt->execute($data);
            setFlash('success', 'Template created successfully!');
        } else {
            $stmt = $db->prepare(
                "UPDATE payslip_templates SET
                 template_name = :template_name, template_type = :template_type,
                 header_content = :header_content, footer_content = :footer_content,
                 show_earnings = :show_earnings, show_deductions = :show_deductions,
                 show_statutory = :show_statutory, show_leave_balance = :show_leave_balance,
                 show_bank_details = :show_bank_details, is_active = :is_active, is_default = :is_default
                 WHERE id = :id"
            );
            $data['id'] = $templateId;
            $stmt->execute($data);
            setFlash('success', 'Template updated successfully!');
        }
        
        // If set as default, update other templates
        if ($data['is_default']) {
            $db->query("UPDATE payslip_templates SET is_default = 0");
            $stmt = $db->prepare("UPDATE payslip_templates SET is_default = 1 WHERE id = ?");
            $stmt->execute([$templateId ?? $db->lastInsertId()]);
        }
        
        redirect('index.php?page=settings/payslip-templates');
    }
    
    if ($action === 'delete' && isset($_POST['template_id'])) {
        $stmt = $db->prepare("DELETE FROM payslip_templates WHERE id = ? AND is_default = 0");
        $stmt->execute([$_POST['template_id']]);
        
        if ($stmt->rowCount() > 0) {
            setFlash('success', 'Template deleted successfully!');
        } else {
            setFlash('error', 'Cannot delete default template!');
        }
        redirect('index.php?page=settings/payslip-templates');
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="bi bi-file-earmark-text me-2"></i>Payslip Templates</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTemplateModal">
                    <i class="bi bi-plus-lg me-1"></i>New Template
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Template Name</th>
                                <th>Type</th>
                                <th>Options</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                            <tr>
                                <td>
                                    <?php echo sanitize($t['template_name']); ?>
                                    <?php if ($t['is_default']): ?>
                                    <span class="badge bg-primary ms-1">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?php echo sanitize($t['template_type']); ?></span></td>
                                <td>
                                    <small>
                                        <?php
                                        $options = [];
                                        if ($t['show_earnings']) $options[] = 'Earnings';
                                        if ($t['show_deductions']) $options[] = 'Deductions';
                                        if ($t['show_statutory']) $options[] = 'Statutory';
                                        if ($t['show_leave_balance']) $options[] = 'Leave';
                                        if ($t['show_bank_details']) $options[] = 'Bank';
                                        echo implode(', ', $options);
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $t['is_active'] ? 'success' : 'danger'; ?>">
                                        <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            onclick="editTemplate(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php if (!$t['is_default']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteTemplate(<?php echo $t['id']; ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="addTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Template Name</label>
                            <input type="text" class="form-control" name="template_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Template Type</label>
                            <select class="form-select" name="template_type" required>
                                <option value="standard">Standard</option>
                                <option value="detailed">Detailed</option>
                                <option value="compact">Compact</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="form-label">Display Options</label>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_earnings" id="showEarnings" checked>
                                <label class="form-check-label" for="showEarnings">Show Earnings</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_deductions" id="showDeductions" checked>
                                <label class="form-check-label" for="showDeductions">Show Deductions</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_statutory" id="showStatutory" checked>
                                <label class="form-check-label" for="showStatutory">Show Statutory</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_leave_balance" id="showLeave" checked>
                                <label class="form-check-label" for="showLeave">Show Leave Balance</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_bank_details" id="showBank" checked>
                                <label class="form-check-label" for="showBank">Show Bank Details</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Header Content (HTML)</label>
                        <textarea class="form-control" name="header_content" rows="3" placeholder="Custom header HTML..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Footer Content (HTML)</label>
                        <textarea class="form-control" name="footer_content" rows="3" placeholder="Custom footer HTML..."></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="isActive" checked>
                        <label class="form-check-label" for="isActive">Active</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_default" id="isDefault">
                        <label class="form-check-label" for="isDefault">Set as Default</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editTemplateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="template_id" id="edit_template_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Template</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Template Name</label>
                            <input type="text" class="form-control" name="template_name" id="edit_template_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Template Type</label>
                            <select class="form-select" name="template_type" id="edit_template_type" required>
                                <option value="standard">Standard</option>
                                <option value="detailed">Detailed</option>
                                <option value="compact">Compact</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <label class="form-label">Display Options</label>
                        <div class="col-md-12">
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_earnings" id="edit_showEarnings">
                                <label class="form-check-label" for="edit_showEarnings">Show Earnings</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_deductions" id="edit_showDeductions">
                                <label class="form-check-label" for="edit_showDeductions">Show Deductions</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_statutory" id="edit_showStatutory">
                                <label class="form-check-label" for="edit_showStatutory">Show Statutory</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_leave_balance" id="edit_showLeave">
                                <label class="form-check-label" for="edit_showLeave">Show Leave Balance</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input type="checkbox" class="form-check-input" name="show_bank_details" id="edit_showBank">
                                <label class="form-check-label" for="edit_showBank">Show Bank Details</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Header Content (HTML)</label>
                        <textarea class="form-control" name="header_content" id="edit_header_content" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Footer Content (HTML)</label>
                        <textarea class="form-control" name="footer_content" id="edit_footer_content" rows="3"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="edit_isActive">
                        <label class="form-check-label" for="edit_isActive">Active</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_default" id="edit_isDefault">
                        <label class="form-check-label" for="edit_isDefault">Set as Default</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="template_id" id="delete_template_id">
</form>

<script>
function editTemplate(t) {
    $('#edit_template_id').val(t.id);
    $('#edit_template_name').val(t.template_name);
    $('#edit_template_type').val(t.template_type);
    $('#edit_header_content').val(t.header_content || '');
    $('#edit_footer_content').val(t.footer_content || '');
    $('#edit_showEarnings').prop('checked', t.show_earnings == 1);
    $('#edit_showDeductions').prop('checked', t.show_deductions == 1);
    $('#edit_showStatutory').prop('checked', t.show_statutory == 1);
    $('#edit_showLeave').prop('checked', t.show_leave_balance == 1);
    $('#edit_showBank').prop('checked', t.show_bank_details == 1);
    $('#edit_isActive').prop('checked', t.is_active == 1);
    $('#edit_isDefault').prop('checked', t.is_default == 1);
    new bootstrap.Modal('#editTemplateModal').show();
}

function deleteTemplate(id) {
    if (confirm('Are you sure you want to delete this template?')) {
        $('#delete_template_id').val(id);
        $('#deleteForm').submit();
    }
}
</script>
