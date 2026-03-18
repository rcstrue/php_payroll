<?php
/**
 * RCS HRMS Pro - Unit List
 * Updated with compulsory unit_code and state dropdown
 */

$pageTitle = 'Units';

// Get filter
$clientFilter = isset($_GET['client']) ? (int)$_GET['client'] : 0;

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

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $unitCode = sanitize($_POST['unit_code'] ?? '');
        
        // Auto-generate unit code if empty
        if (empty($unitCode)) {
            try {
                $stmt = $db->query("SELECT unit_code FROM units WHERE unit_code IS NOT NULL AND unit_code != '' ORDER BY id DESC LIMIT 1");
                $lastUnit = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($lastUnit && !empty($lastUnit['unit_code'])) {
                    if (preg_match('/(\d+)$/', $lastUnit['unit_code'], $matches)) {
                        $num = (int)$matches[1] + 1;
                        $prefix = preg_replace('/\d+$/', '', $lastUnit['unit_code']);
                        if (empty($prefix)) {
                            $prefix = 'UNT';
                        }
                        $unitCode = $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
                    } else {
                        $unitCode = 'UNT001';
                    }
                } else {
                    $unitCode = 'UNT001';
                }
            } catch (Exception $e) {
                $unitCode = 'UNT001';
            }
        }
        
        // Check for duplicate unit code
        $stmt = $db->prepare("SELECT id FROM units WHERE unit_code = ?");
        $stmt->execute([$unitCode]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            setFlash('error', "Unit code '$unitCode' already exists! Please use a different code.");
            redirect('index.php?page=unit/list');
        }
        
        $state = sanitize($_POST['state'] ?? '');
        if (empty($state)) {
            setFlash('error', 'State is required!');
            redirect('index.php?page=unit/list');
        }
        
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'unit_name' => sanitize($_POST['unit_name']),
            'unit_code' => $unitCode,
            'state' => $state,
            'city' => sanitize($_POST['city'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'pincode' => sanitize($_POST['pincode'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $unit->create($data);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('index.php?page=unit/list');
    }

    if ($action === 'edit' && isset($_POST['unit_id'])) {
        $unitCode = sanitize($_POST['unit_code'] ?? '');
        
        // Check for duplicate unit code (exclude current unit)
        if (!empty($unitCode)) {
            $stmt = $db->prepare("SELECT id FROM units WHERE unit_code = ? AND id != ?");
            $stmt->execute([$unitCode, $_POST['unit_id']]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                setFlash('error', "Unit code '$unitCode' already exists! Please use a different code.");
                redirect('index.php?page=unit/list');
            }
        } else {
            setFlash('error', 'Unit Code is required!');
            redirect('index.php?page=unit/list');
        }
        
        $state = sanitize($_POST['state'] ?? '');
        if (empty($state)) {
            setFlash('error', 'State is required!');
            redirect('index.php?page=unit/list');
        }
        
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'unit_name' => sanitize($_POST['unit_name']),
            'unit_code' => $unitCode,
            'state' => $state,
            'city' => sanitize($_POST['city'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'pincode' => sanitize($_POST['pincode'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $unit->update($_POST['unit_id'], $data);
        setFlash('success', 'Unit updated successfully!');
        redirect('index.php?page=unit/list');
    }

    if ($action === 'delete' && isset($_POST['unit_id'])) {
        $result = $unit->delete($_POST['unit_id']);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('index.php?page=unit/list');
    }
}

// Get all clients for dropdown
$clients = $client->getList();

// Get units
$units = $unit->getAll($clientFilter ?: null, false);
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><i class="bi bi-geo-alt me-2"></i>Units</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUnitModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Unit
                </button>
            </div>
            <div class="card-body">
                <!-- Filter -->
                <form method="GET" class="row g-2 mb-3">
                    <input type="hidden" name="page" value="unit/list">
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="client">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $clientFilter == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo sanitize($c['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <?php if ($clientFilter): ?>
                        <a href="index.php?page=unit/list" class="btn btn-sm btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="card-body p-0 pt-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="unitsTable">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Unit Code</th>
                                <th>Unit Name</th>
                                <th>State</th>
                                <th>City</th>
                                <th>Contact Person</th>
                                <th class="text-center">Employees</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($units)): ?>
                                <?php foreach ($units as $u): ?>
                                <tr>
                                    <td><?php echo sanitize($u['client_name'] ?? '-'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($u['unit_code'] ?? '-'); ?></span></td>
                                    <td><strong><?php echo sanitize($u['unit_name'] ?? $u['name']); ?></strong></td>
                                    <td><?php echo sanitize($u['state'] ?? '-'); ?></td>
                                    <td><?php echo sanitize($u['city'] ?? '-'); ?></td>
                                    <td><?php echo sanitize($u['contact_person'] ?? '-'); ?></td>
                                    <td class="text-center">
                                        <a href="index.php?page=employee/list&unit=<?php echo urlencode($u['unit_name'] ?? $u['name']); ?>">
                                            <span class="badge bg-success"><?php echo $u['employee_count'] ?? 0; ?></span>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $u['is_active'] ? 'success' : 'danger'; ?>">
                                            <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                                onclick='editUnit(<?php echo htmlspecialchars(json_encode($u)); ?>)'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                onclick="deleteUnit(<?php echo $u['id']; ?>)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No units found. Click "Add Unit" to create one.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Unit Modal -->
<div class="modal fade" id="addUnitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Client</label>
                        <select class="form-select" name="client_id" id="add_client_id" required>
                            <option value="">Select Client</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Unit Name</label>
                            <input type="text" class="form-control" name="unit_name" id="add_unit_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code" id="add_unit_code" placeholder="Auto-generated">
                            <small class="text-muted">Leave blank to auto-generate</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">State</label>
                            <select class="form-select" name="state" required>
                                <option value="">Select State</option>
                                <?php foreach ($statesList as $state): ?>
                                <option value="<?php echo sanitize($state); ?>"><?php echo sanitize($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pincode</label>
                        <input type="text" class="form-control" name="pincode" maxlength="6">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" maxlength="10">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="add_is_active" checked>
                        <label class="form-check-label" for="add_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Unit Modal -->
<div class="modal fade" id="editUnitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="unit_id" id="edit_unit_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Unit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label required">Client</label>
                        <select class="form-select" name="client_id" id="edit_client_id" required>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Unit Name</label>
                            <input type="text" class="form-control" name="unit_name" id="edit_unit_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code" id="edit_unit_code" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">State</label>
                            <select class="form-select" name="state" id="edit_state" required>
                                <option value="">Select State</option>
                                <?php foreach ($statesList as $state): ?>
                                <option value="<?php echo sanitize($state); ?>"><?php echo sanitize($state); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="edit_city">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pincode</label>
                        <input type="text" class="form-control" name="pincode" id="edit_pincode" maxlength="6">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" id="edit_contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="edit_phone" maxlength="10">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="edit_is_active">
                        <label class="form-check-label" for="edit_is_active">Active</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Unit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form method="POST" id="deleteForm">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="unit_id" id="delete_unit_id">
</form>

<script>
$(document).ready(function() {
    $('#unitsTable').DataTable({
        responsive: true,
        pageLength: 25,
        order: [[0, 'asc'], [2, 'asc']]
    });
    
    // Auto-generate unit code when add modal opens
    $('#addUnitModal').on('shown.bs.modal', function() {
        // Clear the form
        $('#add_unit_name').val('');
        $('#add_unit_code').val('');
        $('#add_client_id').val('');
        
        // Generate unit code
        generateUnitCode();
    });
});

// Generate next unit code via AJAX
function generateUnitCode() {
    $.ajax({
        url: 'index.php?page=api/next-unit-code',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success && response.unit_code) {
                $('#add_unit_code').val(response.unit_code);
            }
        },
        error: function() {
            // Default code if API fails
            $('#add_unit_code').val('UNT001');
        }
    });
}

// Global functions for onclick handlers
window.editUnit = function(u) {
    $('#edit_unit_id').val(u.id);
    $('#edit_client_id').val(u.client_id);
    $('#edit_unit_name').val(u.unit_name || u.name);
    $('#edit_unit_code').val(u.unit_code || '');
    $('#edit_state').val(u.state || '');
    $('#edit_city').val(u.city || '');
    $('#edit_address').val(u.address || '');
    $('#edit_pincode').val(u.pincode || '');
    $('#edit_contact_person').val(u.contact_person || '');
    $('#edit_phone').val(u.contact_phone || '');
    $('#edit_is_active').prop('checked', u.is_active == 1);
    new bootstrap.Modal('#editUnitModal').show();
};

window.deleteUnit = function(id) {
    if (confirm('Are you sure you want to delete this unit?')) {
        $('#delete_unit_id').val(id);
        $('#deleteForm').submit();
    }
};
</script>
