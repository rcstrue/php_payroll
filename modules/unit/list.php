<?php
/**
 * RCS HRMS Pro - Unit List
 */

$pageTitle = 'Units';

// Get filter
$clientFilter = isset($_GET['client']) ? (int)$_GET['client'] : 0;

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'unit_name' => sanitize($_POST['unit_name']),
            'unit_code' => sanitize($_POST['unit_code'] ?? ''),
            'location' => sanitize($_POST['location'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];

        $result = $unit->create($data);
        setFlash($result['success'] ? 'success' : 'error', $result['message']);
        redirect('index.php?page=unit/list');
    }

    if ($action === 'edit' && isset($_POST['unit_id'])) {
        $data = [
            'client_id' => (int)$_POST['client_id'],
            'unit_name' => sanitize($_POST['unit_name']),
            'unit_code' => sanitize($_POST['unit_code'] ?? ''),
            'location' => sanitize($_POST['location'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'contact_person' => sanitize($_POST['contact_person'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
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
                                <?php echo sanitize($c['client_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    </div>
                </form>
            </div>
            <div class="card-body p-0 pt-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="unitsTable">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Unit Name</th>
                                <th>Unit Code</th>
                                <th>Location</th>
                                <th>Contact Person</th>
                                <th class="text-center">Employees</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($units as $u): ?>
                            <tr>
                                <td><?php echo sanitize($u['client_name'] ?? '-'); ?></td>
                                <td><strong><?php echo sanitize($u['unit_name']); ?></strong></td>
                                <td><?php echo sanitize($u['unit_code'] ?? '-'); ?></td>
                                <td><?php echo sanitize($u['state'] ?? $u['city'] ?? '-'); ?></td>
                                <td><?php echo sanitize($u['contact_person'] ?? '-'); ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $u['employee_count'] ?? 0; ?></span>
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
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['client_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Unit Name</label>
                            <input type="text" class="form-control" name="unit_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_active" id="unitActive" checked>
                        <label class="form-check-label" for="unitActive">Active</label>
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
                            <option value="<?php echo $c['id']; ?>"><?php echo sanitize($c['client_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Unit Name</label>
                            <input type="text" class="form-control" name="unit_name" id="edit_unit_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Unit Code</label>
                            <input type="text" class="form-control" name="unit_code" id="edit_unit_code">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" id="edit_location">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Contact Person</label>
                            <input type="text" class="form-control" name="contact_person" id="edit_contact_person">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="edit_phone">
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
        pageLength: 25
    });
});

function editUnit(u) {
    $('#edit_unit_id').val(u.id);
    $('#edit_client_id').val(u.client_id);
    $('#edit_unit_name').val(u.unit_name);
    $('#edit_unit_code').val(u.unit_code || '');
    $('#edit_location').val(u.state || '');
    $('#edit_address').val(u.address || '');
    $('#edit_contact_person').val(u.contact_person || '');
    $('#edit_phone').val(u.contact_phone || '');
    $('#edit_is_active').prop('checked', u.is_active == 1);
    new bootstrap.Modal('#editUnitModal').show();
}

function deleteUnit(id) {
    if (confirm('Are you sure you want to delete this unit?')) {
        $('#delete_unit_id').val(id);
        $('#deleteForm').submit();
    }
}
</script>
